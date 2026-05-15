<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_jembatan_timbang";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$telegram_token   = '---'; // Masukkan Token Bot Anda di sini
$telegram_chat_id = '---'; // Masukkan ID Chat Anda di sini

function kirimNotifikasiTelegram($token, $chat_id, $pesan) {
    if (empty($token) || empty($chat_id)) return;

    $url  = "https://api.telegram.org/bot" . $token . "/sendMessage";
    $data = [
        'chat_id'    => trim($chat_id),
        'text'       => $pesan,
        'parse_mode' => 'Markdown'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $response = curl_exec($ch);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("[Telegram] cURL Error: " . $error);
    }
}
?>