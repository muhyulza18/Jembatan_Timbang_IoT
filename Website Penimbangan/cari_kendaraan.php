<?php
header('Content-Type: application/json');
include 'koneksi.php';

if (!isset($_GET['keyword'])) {
    echo json_encode(["status" => "error", "message" => "Keyword tidak diberikan."]);
    exit;
}

$keyword = trim($_GET['keyword']);

$stmt = $conn->prepare("SELECT * FROM kendaraan WHERE plat_nomor = ? OR uid_rfid = ?");
$stmt->bind_param("ss", $keyword, $keyword);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    $plat = $data['plat_nomor'];

    $stmt2 = $conn->prepare(
        "SELECT COUNT(*) as jml_tilang FROM log_penimbangan WHERE plat_nomor = ? AND status = 'OVERLOAD'"
    );
    $stmt2->bind_param("s", $plat);
    $stmt2->execute();
    $res_tilang = $stmt2->get_result();
    $jml_tilang = 0;
    if ($row_tilang = $res_tilang->fetch_assoc()) {
        $jml_tilang = (int)$row_tilang['jml_tilang'];
    }
    $stmt2->close();

    $data['jumlah_tilang'] = $jml_tilang;
    echo json_encode(["status" => "success", "data" => $data]);
} else {
    echo json_encode(["status" => "not_found"]);
}

$stmt->close();
?>