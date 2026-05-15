<?php
include 'koneksi.php';
if (!isset($_POST['uid_rfid']) || !isset($_POST['berat_aktual'])) {
    echo "Data tidak lengkap.";
    exit;
}
$uid   = $_POST['uid_rfid'];
$berat = (float)$_POST['berat_aktual'];
$stmt = $conn->prepare("SELECT plat_nomor, jbi FROM kendaraan WHERE uid_rfid = ?");
$stmt->bind_param("s", $uid);
$stmt->execute();
$d = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($d) {
    $plat = $d['plat_nomor'];
    $jbi  = (float)$d['jbi'];
    if ($jbi <= 0) {
        echo "Data JBI kendaraan tidak valid.";
        exit;
    }
    $status = ($berat > $jbi) ? "OVERLOAD" : "NORMAL";
    $vdf    = pow(($berat / $jbi), 4);
    // ============================================================
    // Data per-transaksi → INSERT ke log_penimbangan (selalu baru)
    // ============================================================
    $no_tilang        = isset($_POST['no_tilang'])        ? trim($_POST['no_tilang'])        : null;
    $komoditi         = isset($_POST['komoditi'])         ? trim($_POST['komoditi'])         : null;
    $asal             = isset($_POST['asal'])             ? trim($_POST['asal'])             : null;
    $tujuan           = isset($_POST['tujuan'])           ? trim($_POST['tujuan'])           : null;
    $no_surat_jalan   = isset($_POST['surat_jalan'])      ? trim($_POST['surat_jalan'])      : null;
    $pemilik_komoditi = isset($_POST['pemilik_komoditi']) ? trim($_POST['pemilik_komoditi']) : null;
    $alamat_komoditi  = isset($_POST['alamat_komoditi'])  ? trim($_POST['alamat_komoditi'])  : null;
    // ============================================================
    // Data identitas kendaraan → UPDATE tabel kendaraan
    // ============================================================
    $no_uji_baru         = isset($_POST['no_uji'])            ? trim($_POST['no_uji'])            : null;
    $masa_uji_baru       = isset($_POST['masa_uji'])          ? trim($_POST['masa_uji'])          : null;
    $nama_pemilik_baru   = isset($_POST['nama_pemilik'])      ? trim($_POST['nama_pemilik'])      : null;
    $alamat_pemilik_baru = isset($_POST['alamat_pemilik'])    ? trim($_POST['alamat_pemilik'])    : null;
    $jenis_baru          = isset($_POST['jenis_karoseri'])    ? trim($_POST['jenis_karoseri'])    : null;
    $sumbu_baru          = isset($_POST['konfigurasi_sumbu']) ? trim($_POST['konfigurasi_sumbu']) : null;
    $kepemilikan_baru    = isset($_POST['kepemilikan'])       ? trim($_POST['kepemilikan'])       : null;
    $dim_p_baru          = isset($_POST['dim_panjang'])  && is_numeric($_POST['dim_panjang']) ? (int)$_POST['dim_panjang'] : null;
    $dim_l_baru          = isset($_POST['dim_lebar'])    && is_numeric($_POST['dim_lebar'])   ? (int)$_POST['dim_lebar']   : null;
    $dim_t_baru          = isset($_POST['dim_tinggi'])   && is_numeric($_POST['dim_tinggi'])  ? (int)$_POST['dim_tinggi']  : null;
    // =====================================================
    // LANGKAH 1: INSERT ke log_penimbangan (selalu baru)
    // =====================================================
    $stmt2 = $conn->prepare(
        "INSERT INTO log_penimbangan
            (uid_rfid, plat_nomor, berat_aktual, jbi, status, vdf,
             no_tilang, komoditi, asal, tujuan, no_surat_jalan, pemilik_komoditi, alamat_komoditi)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt2->bind_param(
        "ssddsdsssssss",
        $uid, $plat, $berat, $jbi, $status, $vdf,
        $no_tilang, $komoditi, $asal, $tujuan, $no_surat_jalan, $pemilik_komoditi, $alamat_komoditi
    );
    if ($stmt2->execute()) {
        $stmt2->close();
        // =====================================================
        // LANGKAH 2: UPDATE tabel kendaraan dengan data terbaru
        // [FIX] Ganti !empty() → isset() agar field yang dikosongkan
        //       tetap terupdate, bukan dilewati
        // Contoh: no_uji lama "123/2024" → diisi "" → tersimpan ""
        //         sehingga tap RFID berikutnya tidak baca data lama
        // =====================================================
        $update_fields = [];
        $update_values = [];
        $update_types  = "";

        // [FIX] isset() → selalu update jika field dikirim dari form,
        //       meskipun nilainya kosong/berubah
        if (isset($_POST['no_uji']))            { $update_fields[] = "no_uji = ?";            $update_values[] = $no_uji_baru;         $update_types .= "s"; }
        if (isset($_POST['masa_uji']))          { $update_fields[] = "masa_uji = ?";          $update_values[] = $masa_uji_baru;       $update_types .= "s"; }
        if (isset($_POST['nama_pemilik']))      { $update_fields[] = "nama_pemilik = ?";      $update_values[] = $nama_pemilik_baru;   $update_types .= "s"; }
        if (isset($_POST['alamat_pemilik']))    { $update_fields[] = "alamat_pemilik = ?";    $update_values[] = $alamat_pemilik_baru; $update_types .= "s"; }
        if (isset($_POST['jenis_karoseri']))    { $update_fields[] = "jenis_karoseri = ?";    $update_values[] = $jenis_baru;          $update_types .= "s"; }
        if (isset($_POST['konfigurasi_sumbu'])) { $update_fields[] = "konfigurasi_sumbu = ?"; $update_values[] = $sumbu_baru;          $update_types .= "s"; }
        if (isset($_POST['kepemilikan']))       { $update_fields[] = "kepemilikan = ?";       $update_values[] = $kepemilikan_baru;    $update_types .= "s"; }
        if ($dim_p_baru !== null)               { $update_fields[] = "dim_panjang = ?";       $update_values[] = $dim_p_baru;          $update_types .= "i"; }
        if ($dim_l_baru !== null)               { $update_fields[] = "dim_lebar = ?";         $update_values[] = $dim_l_baru;          $update_types .= "i"; }
        if ($dim_t_baru !== null)               { $update_fields[] = "dim_tinggi = ?";        $update_values[] = $dim_t_baru;          $update_types .= "i"; }

        if (!empty($update_fields)) {
            $update_types   .= "s";
            $update_values[] = $uid;
            $stmt_upd = $conn->prepare(
                "UPDATE kendaraan SET " . implode(", ", $update_fields) . " WHERE uid_rfid = ?"
            );
            $stmt_upd->bind_param($update_types, ...$update_values);
            $stmt_upd->execute();
            $stmt_upd->close();
        }
        // =====================================================
        // LANGKAH 3: Kosongkan sesi timbang
        // =====================================================
        $conn->query("UPDATE sesi_timbang SET uid_rfid = '', berat_live = 0 WHERE id = 1");
        // =====================================================
        // LANGKAH 4: Kirim notifikasi Telegram jika OVERLOAD
        // =====================================================
        if ($status == "OVERLOAD") {
            $lebih_kg = round($berat - $jbi, 2);
            $persen   = round(($berat / $jbi) * 100, 1);
            $pesan  = "🚨 *PELANGGARAN OVERLOAD* 🚨\n\n";
            $pesan .= "🚛 *Plat Nomor:* $plat\n";
            $pesan .= "⚖️ *Berat Aktual:* $berat Kg\n";
            $pesan .= "📋 *Batas JBI:* $jbi Kg\n";
            $pesan .= "📈 *Kelebihan:* +" . $lebih_kg . " Kg (" . $persen . "%)\n";
            $pesan .= "📊 *Nilai VDF:* " . round($vdf, 4) . "\n";
            $pesan .= "Waktu: " . date('d/m/Y H:i:s') . "\n\n";
            $pesan .= "Mohon segera lakukan penindakan!";
            kirimNotifikasiTelegram($telegram_token, $telegram_chat_id, $pesan);
        }
        echo "success";
    } else {
        echo "Error Database: " . $conn->error;
    }
} else {
    echo "Kartu Tidak Terdaftar";
}
?>