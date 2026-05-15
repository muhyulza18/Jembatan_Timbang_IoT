<?php
// Mengembalikan VDF dari transaksi TERAKHIR yang tersimpan di log_penimbangan
// (bukan live, bukan rata-rata — per kendaraan terakhir timbang)
header('Content-Type: application/json');
include 'koneksi.php';

$sql    = "SELECT plat_nomor, vdf, berat_aktual, jbi, status, 
           DATE_FORMAT(waktu, '%d/%m %H:%i') as waktu_fmt
           FROM log_penimbangan 
           ORDER BY waktu DESC 
           LIMIT 1";
$result = mysqli_query($conn, $sql);
$data   = mysqli_fetch_assoc($result);

if ($data) {
    echo json_encode([
        "vdf"    => round((float)$data['vdf'], 4),
        "plat"   => $data['plat_nomor'] ?? '-',
        "berat"  => $data['berat_aktual'] ?? 0,
        "jbi"    => $data['jbi'] ?? 0,
        "status" => $data['status'] ?? 'NORMAL',
        "waktu"  => $data['waktu_fmt'] ?? ''
    ]);
} else {
    echo json_encode(["vdf" => 0, "plat" => '-', "berat" => 0, "jbi" => 0, "status" => 'NORMAL', "waktu" => '']);
}
?>