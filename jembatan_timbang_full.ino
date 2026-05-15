#include <WiFi.h>
#include <HTTPClient.h>
#include <LiquidCrystal_I2C.h>
#include <SPI.h>
#include <MFRC522.h>
#include "HX711.h"

const char* WIFI_SSID = "nama wifi";
const char* WIFI_PASSWORD = "password wifi";
const String BASE_URL = "http://--diganti menjadi ip address laptop---//jto";
const float CALIBRATION_FACTOR = 422.552;
const float BERAT_MINIMUM = 1.0;
const float DELTA_UPDATE_LCD = 0.5;

#define MOVING_AVG_SIZE 8
#define LOADCELL_DOUT 16
#define LOADCELL_SCK 4
#define RFID_SS_PIN 5
#define RFID_RST_PIN 27

LiquidCrystal_I2C lcd(0x27, 16, 2);

#define BUZZER_PIN 13
#define BUZZER_RES 8
#define LED_MERAH 25
#define LED_KUNING 26
#define LED_HIJAU 33

HX711 scale;
MFRC522 rfid(RFID_SS_PIN, RFID_RST_PIN);
String urlUpdateLive = BASE_URL + "/update_live.php";
String urlUpdateIdentitas = BASE_URL + "/update_identitas.php";
String urlUpdateStatus = BASE_URL + "/update_status.php";

unsigned long lastBeratSend = 0;
unsigned long lastStatusSend = 0;
unsigned long lastReconnect = 0;
unsigned long lastBuzzerTime = 0;
unsigned long lastLcdUpdate = 0;

bool isWifiOk = false;
bool isReconnecting = false;

float jbiKendaraan = 0;
float movingAvgBuf[MOVING_AVG_SIZE] = {0};

uint8_t movingAvgIdx = 0;

bool movingAvgFull = false;

float beratLcdTerakhir = -999;

enum StatusBerat { IDLE, AMAN, MENDEKATI, OVERLOAD_RINGAN, OVERLOAD_BERAT };
StatusBerat statusSaatIni = IDLE;
StatusBerat statusSebelum = IDLE;

void lcdTampilkan(String baris1, String baris2);
void lcdUpdateBerat(float berat);
void matikanSemuaLED();
void animasiStartup();
void koneksiWifi();
void beepKonfirmasi();
void nyalakanLED(bool merah, bool kuning, bool hijau);
void updateStatusBerat(float berat);
void updateLCD(float berat);
void updateLEDdanBuzzer(unsigned long sekarang);
void cekRFID();
void kirimBerat(float berat);
void kirimStatusAlat(String esp, String loadcell, String rfidStatus);
void buzzerDiam();
void buzzerNyala(int frekuensi);
void beepTone(int frekuensi, int durasi);
void beepAlarm();
void beepKonfirmasiSingkat();
void ambilJBI(String uid);
void kirimIdentitas(String uid);
float bacaBeratSmooth();

void setup() {
  Serial.begin(115200);
  pinMode(LED_MERAH, OUTPUT);
  pinMode(LED_KUNING, OUTPUT);
  pinMode(LED_HIJAU, OUTPUT);
  matikanSemuaLED();
  buzzerDiam();
  lcd.init();
  lcd.backlight();
  lcdTampilkan("JEMBATAN TIMBANG", "Inisialisasi...");
  animasiStartup();
  SPI.begin();
  rfid.PCD_Init();
  scale.begin(LOADCELL_DOUT, LOADCELL_SCK);
  scale.set_scale(CALIBRATION_FACTOR);
  scale.tare();
  koneksiWifi();
  beepKonfirmasi();
  lcdTampilkan("System Ready!", "Tap kartu RFID..");
  nyalakanLED(false, false, true);
  delay(1500);
}

void loop() {
  unsigned long sekarang = millis();
  if (WiFi.status() != WL_CONNECTED) {
    isWifiOk = false;
    if (!isReconnecting) {
      isReconnecting = true;
      lcdTampilkan("WiFi Terputus!", "Reconnecting...");
      nyalakanLED(false, true, false);
    }
    if (sekarang - lastReconnect > 10000) {
      lastReconnect = sekarang;
      WiFi.disconnect();
      delay(500);
      WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
      unsigned long tunggu = millis();
      while (WiFi.status() != WL_CONNECTED && millis() - tunggu < 8000) {
        digitalWrite(LED_KUNING, HIGH); delay(250);
        digitalWrite(LED_KUNING, LOW); delay(250);
      }
      if (WiFi.status() == WL_CONNECTED) {
        isWifiOk = true;
        isReconnecting = false;
        beepKonfirmasi();
        lcdTampilkan("WiFi Terhubung!", WiFi.localIP().toString());
        nyalakanLED(false, false, true);
        beratLcdTerakhir = -999;
        delay(1500);
      }
    }
  } else {
    if (isReconnecting) {
      isReconnecting = false;
      beratLcdTerakhir = -999;
    }
    isWifiOk = true;
  }
  float berat = bacaBeratSmooth();
  updateStatusBerat(berat);
  updateLCD(berat);
  updateLEDdanBuzzer(sekarang);
  if (sekarang - lastBeratSend >= 1000) {
    if (isWifiOk) kirimBerat(berat);
    lastBeratSend = sekarang;
  }
  if (sekarang - lastStatusSend >= 5000) {
    if (isWifiOk) kirimStatusAlat("Online", "Online", "Online");
    lastStatusSend = sekarang;
  }
  cekRFID();
}

float bacaBeratSmooth() {
  if (!scale.is_ready()) {
    float sum = 0;
    int n = movingAvgFull ? MOVING_AVG_SIZE : movingAvgIdx;
    if (n == 0) return 0.0;
    for (int i = 0; i < n; i++) sum += movingAvgBuf[i];
    return sum / n;
  }
  float raw = scale.get_units(3);
  if (raw < BERAT_MINIMUM) raw = 0.0;
  movingAvgBuf[movingAvgIdx] = raw;
  movingAvgIdx = (movingAvgIdx + 1) % MOVING_AVG_SIZE;
  if (movingAvgIdx == 0) movingAvgFull = true;
  float sum = 0;
  int n = movingAvgFull ? MOVING_AVG_SIZE : movingAvgIdx;
  for (int i = 0; i < n; i++) sum += movingAvgBuf[i];
  return sum / n;
}

void updateStatusBerat(float berat) {
  statusSebelum = statusSaatIni;
  if (berat <= BERAT_MINIMUM || jbiKendaraan <= 0) {
    statusSaatIni = IDLE;
    return;
  }
  float persen = (berat / jbiKendaraan) * 100.0;
  if (persen > 150.0) statusSaatIni = OVERLOAD_BERAT;
  else if (persen > 100.0) statusSaatIni = OVERLOAD_RINGAN;
  else if (persen >= 80.0) statusSaatIni = MENDEKATI;
  else statusSaatIni = AMAN;
}

void updateLEDdanBuzzer(unsigned long sekarang) {
  static bool ledState = false;
  static unsigned long lastToggle = 0;
  switch (statusSaatIni) {
    case IDLE:
      if (sekarang - lastToggle >= 1000) {
        ledState = !ledState;
        nyalakanLED(false, false, ledState);
        lastToggle = sekarang;
      }
      buzzerDiam();
      break;
    case AMAN:
      nyalakanLED(false, false, true);
      buzzerDiam();
      break;
    case MENDEKATI:
      if (sekarang - lastToggle >= 500) {
        ledState = !ledState;
        nyalakanLED(false, ledState, false);
        lastToggle = sekarang;
      }
      buzzerDiam();
      break;
    case OVERLOAD_RINGAN:
      if (sekarang - lastToggle >= 400) {
        ledState = !ledState;
        nyalakanLED(ledState, false, false);
        lastToggle = sekarang;
      }
      if (sekarang - lastBuzzerTime >= 2000) {
        beepTone(1000, 150);
        lastBuzzerTime = sekarang;
      }
      break;
    case OVERLOAD_BERAT:
      if (sekarang - lastToggle >= 150) {
        ledState = !ledState;
        nyalakanLED(ledState, false, false);
        lastToggle = sekarang;
      }
      if (sekarang - lastBuzzerTime >= 800) {
        beepAlarm();
        lastBuzzerTime = sekarang;
      }
      break;
  }
}

void updateLCD(float berat) {
  unsigned long sekarang = millis();
  bool beratBerubah = abs(berat - beratLcdTerakhir) >= DELTA_UPDATE_LCD;
  bool statusBerubah = (statusSaatIni != statusSebelum);
  bool waktunya = (sekarang - lastLcdUpdate >= 300);
  if (!beratBerubah && !statusBerubah && !waktunya) return;
  lcd.setCursor(0, 0);
  char buf1[17];
  float beratTampil = (berat < BERAT_MINIMUM) ? 0.0 : berat;
  int bulat = (int)beratTampil;
  int desimal = (int)((beratTampil - bulat) * 100 + 0.5);
  if (desimal >= 100) { bulat++; desimal = 0; }
  snprintf(buf1, sizeof(buf1), "Berat:%4d,%02d kg", bulat, desimal);
  lcd.print(buf1);
  lcd.setCursor(0, 1);
  char buf2[17];
  if (statusSaatIni == IDLE || jbiKendaraan <= 0) {
    snprintf(buf2, sizeof(buf2), "Tap kartu RFID..");
  } else {
    float persen = (berat / jbiKendaraan) * 100.0;
    int pInt = (int)persen;
    int jbiInt = (int)jbiKendaraan;
    snprintf(buf2, sizeof(buf2), "JBI:%5d |%3d%%", jbiInt, pInt);
  }
  lcd.print(buf2);
  beratLcdTerakhir = berat;
  lastLcdUpdate = sekarang;
}

void cekRFID() {
  if (!rfid.PICC_IsNewCardPresent()) return;
  if (!rfid.PICC_ReadCardSerial()) return;
  String uid = "";
  for (byte i = 0; i < rfid.uid.size; i++) {
    if (rfid.uid.uidByte[i] < 0x10) uid += "0";
    uid += String(rfid.uid.uidByte[i], HEX);
    if (i < rfid.uid.size - 1) uid += " ";
  }
  uid.toUpperCase();
  beepKonfirmasi();
  lcdTampilkan("UID:" + uid.substring(0, 11), "Mengirim data...");
  if (isWifiOk) {
    kirimIdentitas(uid);
    ambilJBI(uid);
    beratLcdTerakhir = -999;
    lcdTampilkan("UID:" + uid.substring(0, 11), "ID Terkirim!    ");
  } else {
    lcdTampilkan("UID:" + uid.substring(0, 11), "Offline! Gagal  ");
  }
  delay(1500);
  rfid.PICC_HaltA();
  rfid.PCD_StopCrypto1();
}

void ambilJBI(String uid) {
  HTTPClient http;
  String url = BASE_URL + "/cari_kendaraan.php?keyword=" + uid;
  url.replace(" ", "%20");
  http.begin(url);
  int code = http.GET();
  if (code == 200) {
    String body = http.getString();
    int idx = body.indexOf("\"jbi\":");
    if (idx >= 0) {
      int start = idx + 6;
      if (body[start] == '"') start++;
      int end = start;
      while (end < (int)body.length() && (isDigit(body[end]) || body[end] == '.')) end++;
      jbiKendaraan = body.substring(start, end).toFloat();
    }
  }
  http.end();
}

void kirimBerat(float berat) {
  HTTPClient http;
  http.begin(urlUpdateLive);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");
  http.POST("berat=" + String((int)berat));
  http.end();
}

void kirimIdentitas(String uid) {
  HTTPClient http;
  http.begin(urlUpdateIdentitas);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");
  http.POST("uid=" + uid);
  http.end();
}

void kirimStatusAlat(String esp, String loadcell, String rfidStatus) {
  HTTPClient http;
  http.begin(urlUpdateStatus + "?esp=" + esp + "&loadcell=" + loadcell + "&rfid=" + rfidStatus);
  http.GET();
  http.end();
}

void nyalakanLED(bool merah, bool kuning, bool hijau) {
  digitalWrite(LED_MERAH, merah ? HIGH : LOW);
  digitalWrite(LED_KUNING, kuning ? HIGH : LOW);
  digitalWrite(LED_HIJAU, hijau ? HIGH : LOW);
}

void matikanSemuaLED() {
  nyalakanLED(false, false, false);
}

void buzzerDiam() {
  ledcDetach(BUZZER_PIN);
  pinMode(BUZZER_PIN, OUTPUT);
  digitalWrite(BUZZER_PIN, HIGH);
}

void buzzerNyala(int frekuensi) {
  ledcAttach(BUZZER_PIN, frekuensi, BUZZER_RES);
  ledcWriteTone(BUZZER_PIN, frekuensi);
}

void beepTone(int frekuensi, int durasi) {
  buzzerNyala(frekuensi);
  delay(durasi);
  buzzerDiam();
}

void beepKonfirmasi() {
  beepTone(523, 100);
  delay(60);
  beepTone(659, 100);
}

void beepAlarm() {
  beepTone(880, 150);
  delay(50);
  beepTone(1760, 150);
}

void lcdTampilkan(String baris1, String baris2) {
  lcd.clear();
  lcd.setCursor(0, 0); lcd.print(baris1.substring(0, 16));
  lcd.setCursor(0, 1); lcd.print(baris2.substring(0, 16));
}

void animasiStartup() {
  nyalakanLED(true, false, false); delay(200);
  nyalakanLED(false, true, false); delay(200);
  nyalakanLED(false, false, true); delay(200);
  matikanSemuaLED();
}

void koneksiWifi() {
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    digitalWrite(LED_KUNING, HIGH); delay(250);
    digitalWrite(LED_KUNING, LOW); delay(250);
    attempts++;
  }
  if (WiFi.status() == WL_CONNECTED) isWifiOk = true;
}