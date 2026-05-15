<?php
include 'koneksi.php';

if (!isset($_GET['esp']) || !isset($_GET['loadcell']) || !isset($_GET['rfid'])) {
    echo "Parameter tidak lengkap.";
    exit;
}

$esp      = $_GET['esp'];
$loadcell = $_GET['loadcell'];
$rfid     = $_GET['rfid'];

$nilai_valid = ['Online', 'Offline'];
if (!in_array($esp, $nilai_valid) || !in_array($loadcell, $nilai_valid) || !in_array($rfid, $nilai_valid)) {
    echo "Nilai parameter tidak valid.";
    exit;
}

$stmt = $conn->prepare(
    "UPDATE status_alat SET 
     mikrokontroler = ?, 
     sensor_berat   = ?, 
     identifikasi   = ?, 
     last_ping      = NOW() 
     WHERE id = 1"
);
$stmt->bind_param("sss", $esp, $loadcell, $rfid);

if ($stmt->execute()) {
    echo "Status Updated";
} else {
    echo "Gagal Update";
}
$stmt->close();
?>