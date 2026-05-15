<?php
header('Content-Type: application/json');
include 'koneksi.php';

// ── 1. Cek online/offline ─────────────────────────────────────────────────
$sql_ping   = "SELECT TIMESTAMPDIFF(SECOND, last_ping, NOW()) as selisih FROM status_alat WHERE id = 1";
$res_ping   = mysqli_query($conn, $sql_ping);
$ping_row   = mysqli_fetch_assoc($res_ping);
$is_offline = ($ping_row && (int)$ping_row['selisih'] > 10);

// ── 2. Sesi timbang + data kendaraan + freshness check ───────────────────
$sql = "SELECT 
            s.uid_rfid  AS rfid_sesi,
            s.berat_live,
            CASE
                WHEN s.waktu_tap IS NULL THEN 0
                WHEN TIMESTAMPDIFF(SECOND, s.waktu_tap, NOW()) <= 300 THEN 1
                ELSE 0
            END AS is_fresh,
            k.plat_nomor,
            k.no_uji,
            k.jbi,
            k.nama_pemilik,
            k.masa_uji,
            k.jenis_karoseri,
            k.konfigurasi_sumbu,
            k.kepemilikan,
            k.alamat_pemilik,
            k.dim_panjang,
            k.dim_lebar,
            k.dim_tinggi
        FROM sesi_timbang s
        LEFT JOIN kendaraan k ON s.uid_rfid = k.uid_rfid
        WHERE s.id = 1";

$result = mysqli_query($conn, $sql);
$data   = mysqli_fetch_assoc($result);

if (!$data) {
    echo json_encode(["error" => "Data sesi tidak ditemukan."]);
    exit;
}

$berat     = $is_offline ? 0 : (float)($data['berat_live'] ?? 0);
$jbi       = (isset($data['jbi']) && $data['jbi'] > 0) ? (float)$data['jbi'] : null;
$uid_aktif = ($data['is_fresh'] == 1) ? ($data['rfid_sesi'] ?? '') : '';

if ($jbi && !$is_offline) {
    $persen   = ($berat / $jbi) * 100;
    $vdf_live = pow(($berat / $jbi), 4);
    $status   = ($berat > $jbi) ? "OVERLOAD" : "NORMAL";
} else {
    $persen   = 0;
    $vdf_live = 0;
    $status   = "NORMAL";
}

// ── 3. Ambil data penimbangan TERAKHIR kendaraan ini dari log ─────────────
// Dipakai untuk auto-fill komoditi, asal, tujuan, surat jalan, dll
// Sekaligus update konfigurasi_sumbu & kepemilikan jika sempat berubah
$last_log = null;
if ($uid_aktif && !empty($data['plat_nomor'])) {
    $plat = $data['plat_nomor'];
    $sql_last = "SELECT 
                    komoditi,
                    asal,
                    tujuan,
                    no_surat_jalan,
                    pemilik_komoditi,
                    alamat_komoditi,
                    berat_aktual,
                    jbi            AS jbi_log,
                    vdf,
                    status
                 FROM log_penimbangan
                 WHERE plat_nomor = ?
                 ORDER BY waktu DESC
                 LIMIT 1";
    $stmt = $conn->prepare($sql_last);
    $stmt->bind_param("s", $plat);
    $stmt->execute();
    $last_log = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ── 4. Output JSON ────────────────────────────────────────────────────────
echo json_encode([
    // Berat & status live
    "berat"                => (int)floor($berat),
    "persen"               => round($persen, 1),
    "vdf_live"             => round($vdf_live, 4),
    "status"               => $status,

    // Identitas kendaraan (dari tabel kendaraan — selalu terbaru)
    "uid_rfid"             => $uid_aktif,
    "plat"                 => $uid_aktif ? ($data['plat_nomor']         ?? '') : '',
    "no_uji"               => $uid_aktif ? ($data['no_uji']             ?? '') : '',
    "jbi"                  => $uid_aktif ? ($jbi                        ?? 0)  : 0,
    "nama_pemilik"         => $uid_aktif ? ($data['nama_pemilik']       ?? '') : '',
    "masa_uji"             => $uid_aktif ? ($data['masa_uji']           ?? '') : '',
    "jenis"                => $uid_aktif ? ($data['jenis_karoseri']     ?? '') : '',
    "sumbu"                => $uid_aktif ? ($data['konfigurasi_sumbu']  ?? '') : '',
    "kepemilikan"          => $uid_aktif ? ($data['kepemilikan']        ?? '') : '',
    "alamat"               => $uid_aktif ? ($data['alamat_pemilik']     ?? '') : '',
    "panjang"              => $uid_aktif ? (float)($data['dim_panjang'] ?? 0)  : 0,
    "lebar"                => $uid_aktif ? (float)($data['dim_lebar']   ?? 0)  : 0,
    "tinggi"               => $uid_aktif ? (float)($data['dim_tinggi']  ?? 0)  : 0,

    // Data dari log penimbangan TERAKHIR (auto-fill surat jalan, komoditi, dll)
    "last_komoditi"        => ($uid_aktif && $last_log) ? ($last_log['komoditi']         ?? '') : '',
    "last_asal"            => ($uid_aktif && $last_log) ? ($last_log['asal']             ?? '') : '',
    "last_tujuan"          => ($uid_aktif && $last_log) ? ($last_log['tujuan']           ?? '') : '',
    "last_surat_jalan"     => ($uid_aktif && $last_log) ? ($last_log['no_surat_jalan']   ?? '') : '',
    "last_pemilik"         => ($uid_aktif && $last_log) ? ($last_log['pemilik_komoditi'] ?? '') : '',
    "last_alamat_komoditi" => ($uid_aktif && $last_log) ? ($last_log['alamat_komoditi']  ?? '') : '',
]);
?>