<?php
header('Content-Type: application/json');
include 'koneksi.php';

$sql    = "SELECT mikrokontroler, sensor_berat, identifikasi, 
           TIMESTAMPDIFF(SECOND, last_ping, NOW()) as selisih_detik 
           FROM status_alat WHERE id = 1";
$result = mysqli_query($conn, $sql);
$data   = mysqli_fetch_assoc($result);

if ($data) {
    if ($data['selisih_detik'] > 10) {
        $status  = "Offline";
        $koneksi = "Terputus";
    } else {
        $status  = "Online";
        $koneksi = "Terhubung";
    }

    echo json_encode([
        "esp"      => ($status == "Offline") ? "Offline" : $data['mikrokontroler'],
        "loadcell" => ($status == "Offline") ? "Offline" : $data['sensor_berat'],
        "rfid"     => ($status == "Offline") ? "Offline" : $data['identifikasi'],
        "koneksi"  => $koneksi
    ]);
} else {
    echo json_encode(["esp" => "Offline", "loadcell" => "Offline", "rfid" => "Offline", "koneksi" => "Error"]);
}
?>