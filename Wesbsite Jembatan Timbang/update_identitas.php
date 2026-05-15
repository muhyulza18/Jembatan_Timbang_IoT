<?php
include 'koneksi.php';

$uid = $_REQUEST['uid'] ?? $_REQUEST['uid_rfid'] ?? null;

if (!$uid) {
    echo "Data UID tidak ada/kosong.";
    exit;
}

$uid  = trim($uid);

$stmt = $conn->prepare("UPDATE sesi_timbang SET uid_rfid = ?, waktu_tap = NOW() WHERE id = 1");
$stmt->bind_param("s", $uid);

if ($stmt->execute()) {
    echo "Identitas Terupdate: " . $uid;
} else {
    echo "Gagal Update";
}
$stmt->close();
?>