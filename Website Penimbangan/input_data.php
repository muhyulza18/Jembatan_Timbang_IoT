<?php
include 'koneksi.php';

$telegram_token   = '8640602684:AAHOXDvswhxV-DhAdJCDD81dw_q7Ohzrmjc'; // Masukkan Token Bot Anda di sini
$telegram_chat_id = '8366398687'; // Masukkan ID Chat Anda di sini

if (!isset($_POST['uid_rfid']) || !isset($_POST['berat_aktual'])) {
    echo "Tidak ada data.";
    exit;
}

$uid   = $_POST['uid_rfid'];
$berat = (float)$_POST['berat_aktual'];

$stmt = $conn->prepare("SELECT plat_nomor, jbi FROM kendaraan WHERE uid_rfid = ?");
$stmt->bind_param("s", $uid);
$stmt->execute();
$result = $stmt->get_result();
$row    = $result->fetch_assoc();
$stmt->close();

if ($row) {
    $plat = $row['plat_nomor'];
    $jbi  = (float)$row['jbi'];

    if ($jbi <= 0) {
        echo "Data JBI kendaraan tidak valid.";
        exit;
    }

    $status = ($berat > $jbi) ? "OVERLOAD" : "NORMAL";
    $vdf    = pow(($berat / $jbi), 4);

    $stmt2 = $conn->prepare(
        "INSERT INTO log_penimbangan (uid_rfid, plat_nomor, berat_aktual, jbi, status, vdf) 
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt2->bind_param("ssddsd", $uid, $plat, $berat, $jbi, $status, $vdf);

    if ($stmt2->execute()) {
        if ($status == "OVERLOAD") {
            $pesan  = "⚠️ *PELANGGARAN OVERLOAD* ⚠️\n\n";
            $pesan .= "🚛 *Plat Nomor:* $plat\n";
            $pesan .= "⚖️ *Berat Aktual:* $berat Kg\n";
            $pesan .= "📋 *Batas JBI:* $jbi Kg\n";
            $pesan .= "📊 *Nilai VDF:* " . round($vdf, 4) . "\n\n";
            $pesan .= "Mohon segera lakukan penindakan/tilang!";

            $url = "https://api.telegram.org/bot" . $telegram_token
                 . "/sendMessage?chat_id=" . $telegram_chat_id
                 . "&text=" . urlencode($pesan)
                 . "&parse_mode=Markdown";

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_exec($ch);
            curl_close($ch);
        }
        echo "Sukses: Data tersimpan.";
    } else {
        echo "Error Database.";
    }
    $stmt2->close();

} else {
    echo "Ditolak: RFID tidak terdaftar!";
}
?>