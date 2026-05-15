<?php
include 'koneksi.php';

if (!isset($_POST['berat'])) {
    echo "Data tidak ada.";
    exit;
}

if (!is_numeric($_POST['berat'])) {
    echo "Nilai berat tidak valid.";
    exit;
}

$berat = (float)$_POST['berat'];
$stmt  = $conn->prepare("UPDATE sesi_timbang SET berat_live = ? WHERE id = 1");
$stmt->bind_param("d", $berat);

if ($stmt->execute()) {
    echo "Berat Terupdate";
} else {
    echo "Gagal Update";
}
$stmt->close();
?>