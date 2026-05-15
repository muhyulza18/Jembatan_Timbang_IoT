<?php
include 'koneksi.php';

$tgl_hari_ini = date('Y-m-d');

$q_total     = $conn->query("SELECT COUNT(*) as jml FROM log_penimbangan WHERE DATE(waktu) = '$tgl_hari_ini'");
$row_total   = $q_total->fetch_assoc();
$tot_timbang = $row_total['jml'] ?? 0;

$q_overload   = $conn->query("SELECT COUNT(*) as jml FROM log_penimbangan WHERE DATE(waktu) = '$tgl_hari_ini' AND status = 'OVERLOAD'");
$row_overload = $q_overload->fetch_assoc();
$tot_overload = $row_overload['jml'] ?? 0;

$tot_dimensi = 0;
$tot_dokumen = 0;

$data_bulanan      = array_fill(0, 12, 0);
$data_overload_bln = array_fill(0, 12, 0);

$q_bln = $conn->query("SELECT MONTH(waktu) as bln, COUNT(*) as jml FROM log_penimbangan WHERE YEAR(waktu) = YEAR(CURDATE()) GROUP BY MONTH(waktu)");
while ($row = $q_bln->fetch_assoc()) {
    $data_bulanan[$row['bln'] - 1] = (int)$row['jml'];
}

$q_ovr_bln = $conn->query("SELECT MONTH(waktu) as bln, COUNT(*) as jml FROM log_penimbangan WHERE YEAR(waktu) = YEAR(CURDATE()) AND status = 'OVERLOAD' GROUP BY MONTH(waktu)");
while ($row = $q_ovr_bln->fetch_assoc()) {
    $data_overload_bln[$row['bln'] - 1] = (int)$row['jml'];
}

$label_vdf = [];
$data_vdf  = [];
$q_vdf     = $conn->query("SELECT DATE(waktu) as tgl, AVG(vdf) as avg_vdf FROM log_penimbangan WHERE MONTH(waktu) = MONTH(CURDATE()) AND YEAR(waktu) = YEAR(CURDATE()) GROUP BY DATE(waktu) ORDER BY tgl ASC");
if ($q_vdf && $q_vdf->num_rows > 0) {
    while ($row = $q_vdf->fetch_assoc()) {
        $label_vdf[] = date('d M', strtotime($row['tgl']));
        $data_vdf[]  = round($row['avg_vdf'], 4);
    }
} else {
    $label_vdf = [date('d M')];
    $data_vdf  = [0];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard Monitoring – Kemenhub RI</title>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', sans-serif; background: #f0f4f8; color: #1e293b; display: flex; flex-direction: column; min-height: 100vh; }

    header { background: linear-gradient(135deg, #1e3a5f, #2563eb); color: #fff; padding: 12px 24px; display: flex; align-items: center; gap: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.25); }
    header .flag { font-size: 1.8rem; }
    header .title-block h1 { font-size: 1rem; font-weight: 700; letter-spacing: 0.5px; }
    header .title-block p  { font-size: 0.75rem; opacity: 0.85; }
    header .badge { margin-left: auto; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3); padding: 4px 12px; border-radius: 20px; font-size: 0.72rem; }

    nav.tabs { background: #1e3a5f; display: flex; gap: 2px; padding: 0 16px; overflow-x: auto; }
    nav.tabs button { background: transparent; border: none; color: rgba(255,255,255,0.65); padding: 10px 18px; cursor: pointer; font-size: 0.82rem; border-bottom: 3px solid transparent; white-space: nowrap; transition: all 0.2s; }
    nav.tabs button:hover { color: #fff; background: rgba(255,255,255,0.07); }
    nav.tabs button.active { color: #fff; border-bottom-color: #60a5fa; background: rgba(255,255,255,0.1); }

    main { flex: 1; padding: 24px; max-width: 1400px; width: 100%; margin: 0 auto; }
    .page { display: none; }
    .page.active { display: block; }

    .cards-row { display: grid; gap: 16px; margin-bottom: 24px; }
    .cards-row.col3 { grid-template-columns: repeat(3, 1fr); }
    .cards-row.col4 { grid-template-columns: repeat(4, 1fr); }
    .card { background: #fff; border-radius: 12px; box-shadow: 0 1px 6px rgba(0,0,0,0.08); padding: 20px; position: relative; overflow: hidden; }
    .card .card-icon { font-size: 2rem; margin-bottom: 8px; }
    .card .card-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
    .card .card-value { font-size: 2rem; font-weight: 700; margin: 4px 0; }
    .card .card-sub { font-size: 0.78rem; color: #64748b; }
    .card.blue  { border-left: 4px solid #2563eb; }
    .card.red   { border-left: 4px solid #ef4444; }
    .card.green { border-left: 4px solid #22c55e; }
    .card.orange{ border-left: 4px solid #f97316; }
    .val-green { color:#22c55e; } .val-red { color:#ef4444; } .val-yellow { color:#eab308; }

    .chart-wrap { background: #fff; border-radius: 12px; box-shadow: 0 1px 6px rgba(0,0,0,0.08); padding: 20px; margin-bottom: 24px; }
    .chart-wrap h3 { font-size: 0.9rem; color: #1e293b; margin-bottom: 16px; font-weight: 600; }
    .chart-wrap canvas { max-height: 260px; }
    .charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
    .charts-grid .chart-wrap { margin-bottom: 0; }

    #page-penimbangan { padding: 0; }
    .ptb-body { display: grid; grid-template-columns: 280px 1fr 300px; gap: 0; height: calc(100vh - 130px); overflow: hidden; border: 1px solid #e2e8f0; border-radius: 8px; }
    .ptb-left { background: #1a2332; color: #fff; padding: 14px; display: flex; flex-direction: column; align-items: center; border-right: 1px solid #2d3748; overflow-y: auto; }
    .ptb-left .panel-title { width: 100%; display: flex; justify-content: space-between; align-items: center; font-size: 0.78rem; font-weight: 600; margin-bottom: 14px; padding-bottom: 8px; border-bottom: 1px solid #2d4a6b; }
    .ptb-left .panel-title button { background: #f59e0b; border: none; color: #fff; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 0.72rem; font-weight: 600; }
    .gauge-wrap { width: 100%; position: relative; margin-bottom: 10px; }
    .gauge-wrap canvas { width: 100% !important; }
    .gauge-center-text { text-align: center; margin-top: -10px; }
    .gauge-center-text .kg-val { font-size: 1.4rem; font-weight: 700; color: #60a5fa; }
    .gauge-center-text .kg-pct { font-size: 0.78rem; color: #94a3b8; }
    .gauge-center-text .overload-text { font-size: 1rem; font-weight: 700; color: #fff; margin: 4px 0; }
    .passed-btn { background: #22c55e; color: #fff; border: none; border-radius: 6px; padding: 6px 32px; font-size: 0.9rem; font-weight: 700; cursor: default; margin: 8px 0; width: 80%; }
    .passed-btn.fail { background: #ef4444; }
    .ptb-stats { width: 100%; margin-top: 10px; }
    .ptb-stat-row { display: flex; justify-content: space-between; padding: 5px 8px; background: #0f1c2e; border-radius: 4px; margin-bottom: 4px; font-size: 0.75rem; }
    .ptb-stat-row span:first-child { color: #94a3b8; }
    .ptb-stat-row span:last-child { color: #60a5fa; font-weight: 600; }
    .ptb-center { background: #f8fafc; overflow-y: auto; padding: 0; }
    .ptb-section { background: #fff; border-bottom: 1px solid #e2e8f0; }
    .ptb-section-header { background: #2563eb; color: #fff; padding: 8px 16px; font-size: 0.82rem; font-weight: 700; display: flex; justify-content: space-between; align-items: center; }
    .ptb-section-header button { background: #f59e0b; border: none; color: #fff; padding: 3px 10px; border-radius: 4px; cursor: pointer; font-size: 0.72rem; font-weight: 600; }
    .ptb-section-body { padding: 14px 16px; }
    .qr-row { display: flex; gap: 8px; margin-bottom: 12px; }
    .qr-input { flex: 1; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.82rem; background: #f8fafc; }
    .qr-btn { padding: 7px 12px; border: 1px solid #cbd5e1; border-radius: 6px; background: #f1f5f9; cursor: pointer; font-size: 0.9rem; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .form-group { display: flex; flex-direction: column; gap: 4px; }
    .form-group label { font-size: 0.72rem; font-weight: 600; color: #374151; }
    .form-group label .req { color: #ef4444; }
    .form-group input, .form-group select, .form-group textarea { padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.82rem; background: #fff; width: 100%; }
    .form-group input:focus, .form-group select:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,0.15); }
    .input-with-icon { position: relative; }
    .input-with-icon input { padding-right: 40px; }
    .input-suffix { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); font-size: 0.75rem; color: #64748b; }
    .input-btns { position: absolute; right: 4px; top: 50%; transform: translateY(-50%); display: flex; gap: 3px; }
    .input-btns button { padding: 3px 7px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.75rem; }
    .btn-search { background: #f59e0b; color: #fff; }
    .gandengan-row { display: flex; align-items: center; gap: 8px; margin-top: 10px; }
    .gandengan-row label { font-size: 0.78rem; font-weight: 600; color: #374151; }
    .dim-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
    .dim-table thead th { background: #1e3a5f; color: #fff; padding: 7px 10px; text-align: center; }
    .dim-table thead th.red-col { color: #fca5a5; }
    .dim-table tbody td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; text-align: center; }
    .dim-table tbody td:first-child { font-weight: 600; text-align: left; }
    .dim-table input { width: 60px; padding: 4px 6px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.75rem; text-align: center; }
    .dim-table .kelebihan { color: #ef4444; font-weight: 700; }
    .ptb-right { background: #f8fafc; border-left: 1px solid #e2e8f0; overflow-y: auto; padding: 0; }
    .pelanggaran-box { padding: 10px 14px; }
    .pelanggaran-box select { width: 100%; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.82rem; }
    .tilang-row { padding: 10px 14px; display: flex; align-items: center; gap: 10px; font-size: 0.82rem; font-weight: 600; background: #fff; border-bottom: 1px solid #e2e8f0; }

    .content-grid { display: grid; grid-template-columns: 1fr 320px; gap: 20px; }
    .panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 1px 6px rgba(0,0,0,0.08); }
    .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
    .panel-title { font-size: 14px; font-weight: 600; color: #1e293b; }
    .live-badge { background: #ef4444; color: #fff; font-size: 10px; padding: 3px 10px; border-radius: 20px; font-weight: 700; display: flex; align-items: center; gap: 4px; }
    .live-badge::before { content: ''; width: 6px; height: 6px; background: #fff; border-radius: 50%; animation: pulse 1.5s infinite; }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th { text-align: left; font-size: 11px; color: #64748b; text-transform: uppercase; padding: 10px 12px; border-bottom: 2px solid #e2e8f0; background: #f8fafc; }
    .data-table td { padding: 10px 12px; font-size: 13px; border-bottom: 1px solid #e2e8f0; }
    .data-table .empty { text-align: center; color: #64748b; padding: 60px 0; }
    .status-normal { color: #22c55e; font-weight: 600; }
    .status-overload { color: #ef4444; font-weight: 600; }
    .right-panels { display: flex; flex-direction: column; gap: 16px; }
    .notif-panel .notif-empty { color: #64748b; font-size: 13px; }
    .notif-title { color: #eab308; font-size: 14px; font-weight: 600; margin-bottom: 8px; }
    .vdf-panel .gauge-bar { width: 100%; height: 8px; background: linear-gradient(to right,#22c55e,#eab308,#ef4444); border-radius: 4px; margin: 12px 0 6px; position: relative; }
    .vdf-panel .gauge-labels { display: flex; justify-content: space-between; font-size: 11px; color: #64748b; }
    .vdf-panel .gauge-needle { width: 2px; height: 16px; background: #1e293b; position: absolute; top: -4px; left: 20%; transition: left 0.5s; }
    .vdf-panel .legend { margin-top: 12px; display: flex; flex-direction: column; gap: 4px; }
    .vdf-panel .legend-item { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #64748b; }
    .vdf-panel .legend-item .line { width: 16px; height: 2px; }
    .info-table { width: 100%; }
    .info-table tr td { padding: 6px 0; font-size: 13px; }
    .info-table tr td:first-child { color: #64748b; }
    .info-table tr td:last-child { text-align: right; font-weight: 600; color: #1e293b; }

    footer { background: #1e3a5f; color: rgba(255,255,255,0.55); text-align: center; padding: 10px; font-size: 0.72rem; margin-top: auto; }
  </style>
</head>
<body>

<header>
  <span class="flag">🇮🇩</span>
  <div class="title-block">
    <h1>KEMENTERIAN PERHUBUNGAN RI</h1>
    <p>Direktorat Jenderal Perhubungan Darat — Sistem Monitoring Penimbangan Kendaraan</p>
  </div>
  <span class="badge" id="clock">--:--:--</span>
</header>

<nav class="tabs">
  <button class="active" onclick="showPage('penimbangan', this)">📋 Penimbangan</button>
  <button onclick="showPage('monitoring', this)">📉 Monitoring Live</button>
</nav>

<main>
  <!-- ===================== HALAMAN PENIMBANGAN ===================== -->
  <div id="page-penimbangan" class="page active">
    <div class="ptb-body">

      <!-- KOLOM KIRI: Speedometer -->
      <div class="ptb-left">
        <div class="panel-title">
          <span>Grafik</span>
          <button>Manual</button>
        </div>
        <div class="gauge-wrap">
          <canvas id="gaugeChart" height="160"></canvas>
        </div>
        <div class="gauge-center-text">
          <div class="kg-val" id="g-kg">0 Kg</div>
          <div class="kg-pct" id="kg-pct">(0%)</div>
          <div class="overload-text" id="g-status-text">Menunggu Data...</div>
        </div>
        <button class="passed-btn" id="g-passed-btn">Standby</button>

        <div class="ptb-stats">
          <div class="ptb-stat-row"><span>JBI</span><span id="g-jbi">0 KG</span></div>
          <div class="ptb-stat-row"><span>BERAT</span><span id="g-berat">0 KG</span></div>
          <div class="ptb-stat-row"><span>LEBIH</span><span id="g-lebih">0 KG %</span></div>
        </div>
      </div>

      <!-- KOLOM TENGAH: Form Penimbangan -->
      <div class="ptb-center">

        <div class="ptb-section">
          <div class="ptb-section-header"><span>Informasi Kendaraan</span></div>
          <div class="ptb-section-body">
            <div class="qr-row">
              <input class="qr-input" type="text" placeholder="QR Code / UID RFID" />
              <button class="qr-btn">⊞</button>
            </div>
            <div class="form-grid">
              <div class="form-group">
                <label>Nomor Kendaraan <span class="req">*</span></label>
                <div class="input-with-icon">
                  <input type="text" id="f-nopol" placeholder="" />
                  <div class="input-btns"><button class="btn-search">🔍</button></div>
                </div>
                <div id="warning-tilang" style="color:#ef4444; font-size:11px; font-weight:bold; margin-top:4px; display:none;"></div>
              </div>
              <div class="form-group">
                <label>Nomor Uji <span class="req">*</span></label>
                <input type="number" id="f-nomuji" placeholder="" min="0" />
              </div>
              <div class="form-group">
                <label>Masa Berlaku Uji <span class="req">*</span></label>
                <input type="date" id="f-masaUji" />
              </div>
              <div class="form-group">
                <label>JBI <span class="req">*</span></label>
                <div class="input-with-icon">
                  <input type="number" id="f-jbi" placeholder="0" min="0" />
                  <span class="input-suffix">Kg</span>
                </div>
              </div>
              <div class="form-group">
                <label>Jenis Kendaraan <span class="req">*</span></label>
                <select id="f-jenis">
                  <option value="">-- Pilih --</option>
                  <option>Mobil Penumpang</option>
                  <option>Mobil Bus</option>
                  <option>Mobil Barang (Truk)</option>
                  <option>Kendaraan Khusus</option>
                  <option>Truk Gandeng (Trailer)</option>
                  <option>Truk Tempelan (Semi Trailer)</option>
                </select>
              </div>
              <div class="form-group">
                <label>Konfigurasi Sumbu <span class="req">*</span></label>
                <select id="f-sumbu">
                  <option value="">-- Pilih --</option>
                  <option>1.2</option>
                  <option>1.22</option>
                  <option>1.2+2.2</option>
                  <option>1.2+2.22</option>
                </select>
              </div>
              <div class="form-group">
                <label>Kepemilikan <span class="req">*</span></label>
                <select id="f-kepemilikan">
                  <option value="">-- Pilih --</option>
                  <option>Pribadi</option>
                  <option>Perusahaan</option>
                  <option>Pemerintah</option>
                </select>
              </div>
              <div class="form-group">
                <label>Nama Pemilik <span class="req">*</span></label>
                <input type="text" id="f-namaPemilik" />
              </div>
              <div class="form-group" style="grid-column: 1/-1;">
                <label>Alamat Pemilik</label>
                <input type="text" id="f-alamatPemilik" />
              </div>
            </div>

            <div class="gandengan-row">
              <label>Gandengan :</label>
              <input type="checkbox" id="cb-gandengan" onchange="toggleGandengan(this)" />
              <label for="cb-gandengan" id="lbl-gandengan">Tidak</label>
            </div>
            <div id="gandengan-extra" style="display:none; margin-top:10px;">
              <div class="form-group">
                <label>Nama Pemilik Gandengan <span class="req">*</span></label>
                <input type="text" id="f-namaGandengan" placeholder="Masukkan nama pemilik gandengan" />
              </div>
            </div>
          </div>
        </div>

        <div class="ptb-section">
          <div class="ptb-section-header">
            <span>Dimensi</span>
            <button>Baca</button>
          </div>
          <div class="ptb-section-body">
            <table class="dim-table">
              <thead>
                <tr>
                  <th></th>
                  <th>DI IZINKAN <span style="color:#fca5a5;">*</span></th>
                  <th>PENGUKURAN</th>
                  <th>TOLERANSI</th>
                  <th class="red-col">KELEBIHAN</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>PANJANG</td>
                  <td><input type="number" id="f-dim-p" value="0" min="0" /> mm</td>
                  <td><input type="number" value="0" min="0" /> mm</td>
                  <td><input type="number" value="0" min="0" /> mm</td>
                  <td class="kelebihan">0 <span style="color:#ef4444;">mm</span></td>
                </tr>
                <tr>
                  <td>LEBAR</td>
                  <td><input type="number" id="f-dim-l" value="0" min="0" /> mm</td>
                  <td><input type="number" value="0" min="0" /> mm</td>
                  <td><input type="number" value="0" min="0" /> mm</td>
                  <td class="kelebihan">0 <span style="color:#ef4444;">mm</span></td>
                </tr>
                <tr>
                  <td>TINGGI</td>
                  <td><input type="number" id="f-dim-t" value="0" min="0" /> mm</td>
                  <td><input type="number" value="0" min="0" /> mm</td>
                  <td><input type="number" value="0" min="0" /> mm</td>
                  <td class="kelebihan">0 <span style="color:#ef4444;">mm</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="ptb-section">
          <div class="ptb-section-header"><span>Pelanggaran</span></div>
          <div class="pelanggaran-box">
            <select>
              <option value="">-- Pilih Pelanggaran --</option>
              <option>Kelebihan Muatan</option>
              <option>Dimensi Berlebih</option>
              <option>Dokumen Tidak Lengkap</option>
            </select>
          </div>
        </div>

        <div class="tilang-row" style="flex-direction: column; align-items: flex-start; gap: 5px;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <input type="checkbox" id="cb-tilang" />
            <label for="cb-tilang">Kendaraan Sudah Tilang ?</label>
            <span id="lbl-tilang" style="color:#ef4444;">Tidak</span>
          </div>
          <div id="input-no-tilang" style="display: none; width: 100%; margin-top: 8px;">
            <label style="font-size: 11px; color: #64748b;">NOMOR TILANG:</label>
            <input type="text" id="f-no-tilang" placeholder="Masukkan No. Tilang..."
              style="width: 100%; padding: 7px; border: 1px solid #ef4444; border-radius: 4px; font-size: 12px;" />
          </div>
        </div>

        <div style="padding: 15px;">
          <button onclick="simpanTransaksi()" style="width:100%; padding:12px; background:#2563eb; color:#fff; border:none; border-radius:8px; font-weight:bold; cursor:pointer; font-size:14px; box-shadow: 0 4px 6px rgba(37,99,235,0.3);">
            💾 SIMPAN DATA PENIMBANGAN
          </button>
        </div>
      </div>

      <!-- KOLOM KANAN: Komoditi, Asal, Tujuan -->
      <div class="ptb-right">
        <div class="ptb-section">
          <div class="ptb-section-header">
            <span>Komoditi &amp; Asal Tujuan</span>
          </div>
          <div class="ptb-section-body">
            <div class="form-group" style="margin-bottom:10px;">
              <label>Komoditi <span class="req">*</span></label>
              <select id="f-komoditi">
                <option value="">-- Pilih --</option>
                <option>Barang Sembako</option>
                <option>Bahan Bangunan</option>
                <option>Hasil Pertanian dan Perkebunan</option>
                <option>Barang Manufaktur</option>
                <option>Logistik</option>
                <option>Bahan Kimia</option>
              </select>
            </div>
            <div class="form-grid">
              <div class="form-group">
                <label>Asal Pengiriman <span class="req">*</span></label>
                <select id="f-asal">
                  <option value="">-- Pilih Wilayah --</option>
                  <option>Bangkalan</option><option>Banyuwangi</option><option>Blitar (Kab)</option>
                  <option>Blitar (Kota)</option><option>Bojonegoro</option><option>Bondowoso</option>
                  <option>Gresik</option><option>Jember</option><option>Jombang</option>
                  <option>Kediri (Kab)</option><option>Kediri (Kota)</option><option>Lamongan</option>
                  <option>Lumajang</option><option>Madiun (Kab)</option><option>Madiun (Kota)</option>
                  <option>Magetan</option><option>Malang (Kab)</option><option>Malang (Kota)</option>
                  <option>Batu (Kota)</option><option>Mojokerto (Kab)</option><option>Mojokerto (Kota)</option>
                  <option>Nganjuk</option><option>Ngawi</option><option>Pacitan</option>
                  <option>Pamekasan</option><option>Pasuruan (Kab)</option><option>Pasuruan (Kota)</option>
                  <option>Ponorogo</option><option>Probolinggo (Kab)</option><option>Probolinggo (Kota)</option>
                  <option>Sampang</option><option>Sidoarjo</option><option>Situbondo</option>
                  <option>Sumenep</option><option>Trenggalek</option><option>Tuban</option>
                  <option>Tulungagung</option><option>Surabaya (Kota)</option>
                </select>
              </div>
              <div class="form-group">
                <label>Tujuan Pengiriman <span class="req">*</span></label>
                <select id="f-tujuan">
                  <option value="">-- Pilih Wilayah --</option>
                  <option>Bangkalan</option><option>Banyuwangi</option><option>Blitar (Kab)</option>
                  <option>Blitar (Kota)</option><option>Bojonegoro</option><option>Bondowoso</option>
                  <option>Gresik</option><option>Jember</option><option>Jombang</option>
                  <option>Kediri (Kab)</option><option>Kediri (Kota)</option><option>Lamongan</option>
                  <option>Lumajang</option><option>Madiun (Kab)</option><option>Madiun (Kota)</option>
                  <option>Magetan</option><option>Malang (Kab)</option><option>Malang (Kota)</option>
                  <option>Batu (Kota)</option><option>Mojokerto (Kab)</option><option>Mojokerto (Kota)</option>
                  <option>Nganjuk</option><option>Ngawi</option><option>Pacitan</option>
                  <option>Pamekasan</option><option>Pasuruan (Kab)</option><option>Pasuruan (Kota)</option>
                  <option>Ponorogo</option><option>Probolinggo (Kab)</option><option>Probolinggo (Kota)</option>
                  <option>Sampang</option><option>Sidoarjo</option><option>Situbondo</option>
                  <option>Sumenep</option><option>Trenggalek</option><option>Tuban</option>
                  <option>Tulungagung</option><option>Surabaya (Kota)</option>
                </select>
              </div>
            </div>

            <div class="form-group" style="margin-top:10px;">
              <label>Nomor Surat Jalan</label>
              <input type="text" id="f-suratJalan" />
            </div>
            <div class="form-group" style="margin-top:8px;">
              <label>Pemilik Komoditi <span class="req">*</span></label>
              <input type="text" id="f-pemilikKomoditi" />
            </div>
            <div class="form-group" style="margin-top:8px;">
              <label>Alamat Pemilik Komoditi</label>
              <input type="text" id="f-alamatKomoditi" />
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- ===================== HALAMAN MONITORING ===================== -->
  <div id="page-monitoring" class="page">

    <div class="cards-row col4" style="text-align: center;">
      <div class="card" style="display:flex;flex-direction:column;justify-content:center;align-items:center;padding:20px;">
        <h3 style="margin-bottom:10px;font-size:14px;color:#64748b;">Total Timbang Hari Ini</h3>
        <div style="font-size:36px;font-weight:bold;color:#0f172a;"><?php echo $tot_timbang; ?></div>
        <p style="margin-top:5px;color:#22c55e;font-size:12px;">Data Aktual</p>
      </div>
      <div class="card" style="display:flex;flex-direction:column;justify-content:center;align-items:center;padding:20px;">
        <h3 style="margin-bottom:10px;font-size:14px;color:#64748b;">Kelebihan Muatan (Overload)</h3>
        <div style="font-size:36px;font-weight:bold;color:#ef4444;"><?php echo $tot_overload; ?></div>
        <p style="margin-top:5px;font-size:12px;">Berdasarkan JBI</p>
      </div>
      <div class="card" style="display:flex;flex-direction:column;justify-content:center;align-items:center;padding:20px;">
        <h3 style="margin-bottom:10px;font-size:14px;color:#64748b;">Dimensi Berlebih</h3>
        <div style="font-size:36px;font-weight:bold;color:#f59e0b;"><?php echo $tot_dimensi; ?></div>
        <p style="margin-top:5px;font-size:12px;">Melebihi toleransi</p>
      </div>
      <div class="card" style="display:flex;flex-direction:column;justify-content:center;align-items:center;padding:20px;">
        <h3 style="margin-bottom:10px;font-size:14px;color:#64748b;">Dokumen Tdk Lengkap</h3>
        <div style="font-size:36px;font-weight:bold;color:#8b5cf6;"><?php echo $tot_dokumen; ?></div>
        <p style="margin-top:5px;font-size:12px;">KIR mati / Tanpa Surat</p>
      </div>
    </div>

    <div class="content-grid" style="align-items: start;">

      <div class="left-panels" style="display:flex;flex-direction:column;gap:20px;">
        <div class="panel">
          <div class="panel-header">
            <div class="panel-title">📋 Log Penimbangan Hari Ini (Real-Time)</div>
            <div class="live-badge">LIVE</div>
          </div>
          <table class="data-table">
            <thead>
              <tr><th>Waktu</th><th>Plat Nomor</th><th>Berat (KG)</th><th>JBI (KG)</th><th>Status</th><th>VDF</th></tr>
            </thead>
            <tbody id="logContent">
              <tr><td colspan="6" class="empty">Menghubungkan ke database...</td></tr>
            </tbody>
          </table>
        </div>

        <div class="panel notif-panel" style="border: 1px solid #ef4444;">
          <div class="notif-title" style="color:#ef4444;margin-bottom:10px;font-weight:bold;">⚠️ Notifikasi Overload Terbaru</div>
          <div id="notifArea" style="background:#fef2f2;padding:15px;border-radius:8px;color:#7f1d1d;">
            Belum ada pelanggaran hari ini.
          </div>
        </div>
      </div>

      <div class="right-panels" style="display:flex;flex-direction:column;gap:20px;">

        <!-- VDF PANEL — menampilkan VDF kendaraan TERAKHIR yang timbang -->
        <div class="panel vdf-panel">
          <div class="panel-title">📊 Indikator VDF (Kerusakan Jalan)</div>
          <div class="gauge-bar" style="margin-top:20px;">
            <div class="gauge-needle" id="vdfNeedle"></div>
          </div>
          <div class="gauge-labels"><span>0</span><span>Ambang: 1.0</span><span>5+</span></div>

          <div style="text-align:center; margin-top:15px;">
            <span style="font-size:12px;color:#64748b;">Nilai VDF Kendaraan Terakhir:</span><br>
            <span id="angkaVdfTerakhir" style="font-size:28px;font-weight:bold;color:#0ea5e9;">0.0000</span>
          </div>

          <!-- Info kendaraan terakhir — diupdate oleh fetchVdfTerakhir() -->
          <div id="info-vdf-kendaraan" style="text-align:center;margin-top:8px;font-size:12px;color:#64748b;min-height:20px;padding:6px;background:#f8fafc;border-radius:6px;"></div>

          <div class="legend" style="margin-top:16px;">
            <div class="legend-item"><div class="line" style="background:#ef4444"></div> Risiko Tinggi (&gt;1.0)</div>
            <div class="legend-item"><div class="line" style="background:#22c55e"></div> Normal (&lt;1.0)</div>
          </div>
        </div>

        <!-- IOT STATUS PANEL -->
        <div class="panel">
          <div class="panel-title">🖥️ Info Sistem IoT</div>
          <table class="info-table" style="margin-top:12px;width:100%;">
            <tr>
              <td>Mikrokontroler</td>
              <td id="stat-esp" style="text-align:right;font-weight:bold;color:#ef4444;">● Offline</td>
            </tr>
            <tr>
              <td>Sensor Berat</td>
              <td id="stat-loadcell" style="text-align:right;font-weight:bold;color:#ef4444;">● Offline</td>
            </tr>
            <tr>
              <td>Identifikasi (RFID)</td>
              <td id="stat-rfid" style="text-align:right;font-weight:bold;color:#ef4444;">● Offline</td>
            </tr>
            <tr>
              <td>Database</td>
              <td style="text-align:right;font-weight:bold;color:#22c55e;">● MySQL Lokal</td>
            </tr>
            <tr>
              <td>Konektivitas</td>
              <td id="status-koneksi-alat" style="text-align:right;font-weight:bold;color:#ef4444;">● Terputus</td>
            </tr>
          </table>
        </div>

      </div>
    </div>

    <!-- GRAFIK STATISTIK -->
    <div style="margin-top:30px;padding-top:20px;border-top:2px solid #e2e8f0;">
      <h3 style="margin-bottom:20px;color:#0f172a;font-size:1.2rem;">📈 Analisis & Laporan Statistik UPPKB</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        <div style="background:white;padding:20px;border-radius:12px;border:1px solid #e2e8f0;">
          <h4 style="margin-bottom:15px;color:#1e293b;font-size:14px;">Riwayat Penimbangan Per-Bulan</h4>
          <div style="position:relative;height:250px;"><canvas id="chartBulanan"></canvas></div>
        </div>
        <div style="background:white;padding:20px;border-radius:12px;border:1px solid #e2e8f0;">
          <h4 style="margin-bottom:15px;color:#1e293b;font-size:14px;">Pelanggaran Overload Per-Bulan</h4>
          <div style="position:relative;height:250px;"><canvas id="chartPelanggaranBaru"></canvas></div>
        </div>
      </div>
      <div style="background:white;padding:20px;border-radius:12px;border:1px solid #e2e8f0;margin-top:20px;">
        <h4 style="margin-bottom:15px;color:#1e293b;font-size:14px;">Rata-Rata VDF Harian (Bulan Ini)</h4>
        <div style="position:relative;height:250px;"><canvas id="chartVdfHarian"></canvas></div>
      </div>
    </div>

  </div>
</main>

<footer>© 2026 Kementerian Perhubungan RI – Direktorat Jenderal Perhubungan Darat</footer>

<script>
  // ===== 1. JAM DIGITAL =====
  function updateClock() {
    const now  = new Date();
    const hari = now.toLocaleDateString('id-ID', { weekday:'long', day:'2-digit', month:'long', year:'numeric' });
    const jam  = now.toLocaleTimeString('id-ID');
    let el = document.getElementById('clock');
    if (el) el.textContent = hari + '  |  ' + jam;
  }
  setInterval(updateClock, 1000); updateClock();

  // ===== 2. NAVIGASI TAB =====
  function showPage(name, btn) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('nav.tabs button').forEach(b => b.classList.remove('active'));
    let pg = document.getElementById('page-' + name);
    if (pg) pg.classList.add('active');
    if (btn) btn.classList.add('active');
  }

  // ===== 3. TOGGLE GANDENGAN & TILANG =====
  function toggleGandengan(cb) {
    const extra = document.getElementById('gandengan-extra');
    const lbl   = document.getElementById('lbl-gandengan');
    if (extra && lbl) {
      if (cb.checked) { extra.style.display = 'block'; lbl.textContent = 'Ya'; }
      else { extra.style.display = 'none'; lbl.textContent = 'Tidak'; }
    }
  }

  let cbTilang = document.getElementById('cb-tilang');
  if (cbTilang) {
    cbTilang.addEventListener('change', function() {
      const lbl      = document.getElementById('lbl-tilang');
      const inputBar = document.getElementById('input-no-tilang');
      if (this.checked) {
        if (lbl) { lbl.textContent = 'Ya'; lbl.style.color = '#22c55e'; }
        if (inputBar) inputBar.style.display = 'block';
      } else {
        if (lbl) { lbl.textContent = 'Tidak'; lbl.style.color = '#ef4444'; }
        if (inputBar) inputBar.style.display = 'none';
      }
    });
  }

  // ===== 4. PENCARIAN MANUAL (TOMBOL KACA PEMBESAR) =====
  let btnSearch = document.querySelector('.btn-search');
  if (btnSearch) {
    btnSearch.addEventListener('click', function(e) {
      e.preventDefault();
      let keyword = document.getElementById('f-nopol').value;
      if (keyword === "") {
        let qrInput = document.querySelector('.qr-input');
        if (qrInput) keyword = qrInput.value;
      }
      if (keyword === "") { alert("Masukkan Plat Nomor terlebih dahulu!"); return; }

      fetch('cari_kendaraan.php?keyword=' + encodeURIComponent(keyword))
        .then(r => r.json())
        .then(res => {
          let warningBox = document.getElementById('warning-tilang');
          if (res.status === 'success') {
            let d = res.data;
            if (document.querySelector('.qr-input'))              document.querySelector('.qr-input').value       = d.uid_rfid       || '';
            if (document.getElementById('f-nopol'))              document.getElementById('f-nopol').value        = d.plat_nomor     || '';
            if (document.getElementById('f-jbi'))                document.getElementById('f-jbi').value          = d.jbi            || '';
            if (document.getElementById('f-nomuji'))             document.getElementById('f-nomuji').value       = d.no_uji         || '';
            if (document.getElementById('f-masaUji'))            document.getElementById('f-masaUji').value      = d.masa_uji       || '';
            if (document.getElementById('f-namaPemilik'))        document.getElementById('f-namaPemilik').value  = d.nama_pemilik   || '';
            if (document.getElementById('f-alamatPemilik'))      document.getElementById('f-alamatPemilik').value= d.alamat_pemilik || '';
            if (document.getElementById('f-dim-p'))              document.getElementById('f-dim-p').value        = d.dim_panjang    || 0;
            if (document.getElementById('f-dim-l'))              document.getElementById('f-dim-l').value        = d.dim_lebar      || 0;
            if (document.getElementById('f-dim-t'))              document.getElementById('f-dim-t').value        = d.dim_tinggi     || 0;
            // SELECT: wajib setSelectValue (option tanpa atribut value)
            setSelectValue(document.getElementById('f-jenis'),       d.jenis_karoseri    || '');
            setSelectValue(document.getElementById('f-sumbu'),       d.konfigurasi_sumbu || '');
            setSelectValue(document.getElementById('f-kepemilikan'), d.kepemilikan       || '');

            if (d.jumlah_tilang > 0 && warningBox) {
              warningBox.innerText = "⚠️ Riwayat: " + d.jumlah_tilang + " kali pelanggaran.";
              warningBox.style.display = "block";
            } else if (warningBox) { warningBox.style.display = "none"; }
          } else {
            alert("⚠️ Kendaraan Belum Terdaftar!\nSilakan isi form manual.");
            if (warningBox) warningBox.style.display = "none";
          }
        });
    });
  }

  // ===== 5. SIMPAN TRANSAKSI =====
  function simpanTransaksi() {
    const formInputs = document.querySelectorAll('#page-penimbangan input, #page-penimbangan select');
    const inputWajib = [
      document.getElementById('f-nopol'),
      document.getElementById('f-jbi'),
      document.getElementById('f-jenis'),
      document.getElementById('f-nomuji'),
      document.getElementById('f-komoditi'),
      document.getElementById('f-asal'),
      document.getElementById('f-tujuan'),
      document.getElementById('f-pemilikKomoditi')
    ];

    let isValid = true;
    inputWajib.forEach(el => {
      if (el && el.value.trim() === "") {
        el.style.borderColor = "#ef4444"; el.style.borderWidth = "2px"; isValid = false;
      } else if (el) {
        el.style.borderColor = "#cbd5e1"; el.style.borderWidth = "1px";
      }
    });

    if (!isValid) { alert("⚠️ Harap lengkapi semua field yang wajib diisi!"); return; }

    // Ambil berat dari tampilan speedometer
    let beratSekarang = document.getElementById('g-kg').innerText.replace(' Kg', '').trim();

    let formData = new URLSearchParams();
    formData.append('uid_rfid',          document.querySelector('.qr-input')?.value       || '');
    formData.append('berat_aktual',      beratSekarang);
    // --- Data per-transaksi (masuk log_penimbangan) ---
    formData.append('no_tilang',         document.getElementById('f-no-tilang')?.value    || '');
    formData.append('komoditi',          document.getElementById('f-komoditi').value);
    formData.append('asal',              document.getElementById('f-asal').value);
    formData.append('tujuan',            document.getElementById('f-tujuan').value);
    formData.append('surat_jalan',       document.getElementById('f-suratJalan')?.value   || '');
    formData.append('pemilik_komoditi',  document.getElementById('f-pemilikKomoditi').value);
    formData.append('alamat_komoditi',   document.getElementById('f-alamatKomoditi')?.value || '');
    // --- [FIX] Data identitas kendaraan (untuk UPDATE tabel kendaraan) ---
    // Tanpa ini, konfigurasi sumbu/kepemilikan/dimensi tidak pernah tersimpan ke DB
    // dan auto-fill besok akan selalu kosong untuk field-field tersebut
    formData.append('no_uji',            document.getElementById('f-nomuji')?.value       || '');
    formData.append('masa_uji',          document.getElementById('f-masaUji')?.value      || '');
    formData.append('nama_pemilik',      document.getElementById('f-namaPemilik')?.value  || '');
    formData.append('alamat_pemilik',    document.getElementById('f-alamatPemilik')?.value|| '');
    formData.append('jenis_karoseri',    document.getElementById('f-jenis')?.value        || '');
    formData.append('konfigurasi_sumbu', document.getElementById('f-sumbu')?.value        || '');
    formData.append('kepemilikan',       document.getElementById('f-kepemilikan')?.value  || '');
    formData.append('dim_panjang',       document.getElementById('f-dim-p')?.value        || 0);
    formData.append('dim_lebar',         document.getElementById('f-dim-l')?.value        || 0);
    formData.append('dim_tinggi',        document.getElementById('f-dim-t')?.value        || 0);

    fetch('simpan_transaksi.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    })
    .then(r => r.text())
    .then(hasil => {
      if (hasil === 'success') {
        alert("✅ DATA BERHASIL DISIMPAN!");

        // Reset semua input form
        formInputs.forEach(el => {
          if (el.type === 'checkbox') el.checked = false;
          else if (el.tagName === 'SELECT') el.selectedIndex = 0;
          else el.value = "";
          el.style.borderColor = "#cbd5e1"; el.style.borderWidth = "1px";
        });

        let warningBox = document.getElementById('warning-tilang');
        if (warningBox) warningBox.style.display = "none";

        // [FIX] Reset visual speedometer ke 0 setelah simpan
        updateTimbanganVisual(0, 1, "");
        let gKg = document.getElementById('g-kg');
        if (gKg) gKg.innerText = "0 Kg";
        let gPersen = document.getElementById('kg-pct');
        if (gPersen) gPersen.innerText = "(0%)";

        // Reset memori UID agar kartu yang sama bisa ditap ulang (timbang lagi)
        uidTerakhir = "";

      } else {
        alert("Gagal menyimpan: " + hasil);
      }
    });
  }

  document.querySelectorAll('input, select').forEach(el => {
    el.addEventListener('input', function() {
      this.style.borderColor = "#cbd5e1"; this.style.borderWidth = "1px";
    });
  });

  // ===== 6. GRAFIK STATISTIK =====
  let canvasBulanan = document.getElementById('chartBulanan');
  if (canvasBulanan && typeof Chart !== 'undefined') {
    new Chart(canvasBulanan.getContext('2d'), {
      type: 'bar',
      data: {
        labels: ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'],
        datasets: [{ label: 'Total Kendaraan', data: <?php echo json_encode($data_bulanan); ?>, backgroundColor: '#2563eb' }]
      },
      options: { responsive: true, maintainAspectRatio: false }
    });
  }

  let canvasPelanggaran = document.getElementById('chartPelanggaranBaru');
  if (canvasPelanggaran && typeof Chart !== 'undefined') {
    new Chart(canvasPelanggaran.getContext('2d'), {
      type: 'line',
      data: {
        labels: ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'],
        datasets: [{ label: 'Overload', data: <?php echo json_encode($data_overload_bln); ?>, borderColor: '#ef4444', backgroundColor: '#ef4444', tension: 0.4 }]
      },
      options: { responsive: true, maintainAspectRatio: false }
    });
  }

  let canvasVdf = document.getElementById('chartVdfHarian');
  if (canvasVdf && typeof Chart !== 'undefined') {
    new Chart(canvasVdf.getContext('2d'), {
      type: 'line',
      data: {
        labels: <?php echo json_encode($label_vdf); ?>,
        datasets: [{ label: 'Rata-rata VDF', data: <?php echo json_encode($data_vdf); ?>, borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.1)', fill: true, tension: 0.3 }]
      },
      options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
    });
  }

  // ===== 7. SPEEDOMETER (GRAFIK JARUM) =====
  let gaugeChartObj = null;
  const ctxGauge    = document.getElementById('gaugeChart');
  if (ctxGauge && typeof Chart !== 'undefined') {
    const gaugeNeedle = {
      id: 'gaugeNeedle',
      afterDatasetDraw(chart) {
        const { ctx, data, chartArea: { left, width } } = chart;
        ctx.save();
        const needleValue = data.datasets[0].needleValue;
        const dataTotal   = data.datasets[0].data.reduce((a, b) => a + b, 0);
        let safeValue     = needleValue > 100 ? 100 : needleValue;
        const angle       = Math.PI + (safeValue / dataTotal * Math.PI);
        const cx          = width / 2 + left;
        const cy          = chart._metasets[0].data[0].y;

        ctx.translate(cx, cy);
        ctx.rotate(angle);
        ctx.beginPath();
        ctx.moveTo(0, -6);
        ctx.lineTo(chart.outerRadius - 15, 0);
        ctx.lineTo(0, 6);
        ctx.fillStyle = '#475569';
        ctx.fill();

        ctx.beginPath();
        ctx.arc(0, 0, 12, 0, Math.PI * 2);
        ctx.fillStyle = '#1e293b';
        ctx.fill();
        ctx.restore();
      }
    };

    gaugeChartObj = new Chart(ctxGauge.getContext('2d'), {
      type: 'doughnut',
      plugins: [gaugeNeedle],
      data: {
        labels: ['Aman', 'Hati-hati', 'Rawan', 'Overload'],
        datasets: [{
          data: [25, 25, 25, 25],
          backgroundColor: ['#22c55e', '#facc15', '#f97316', '#ef4444'],
          borderWidth: 2, borderColor: '#ffffff', needleValue: 0
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        rotation: 270, circumference: 180, cutout: '65%',
        plugins: { legend: { display: false }, tooltip: { enabled: false } }
      }
    });
  }

  // ===== 8. UPDATE VISUAL SPEEDOMETER =====
  function updateTimbanganVisual(beratAktual, batasJbi, platNomor) {
    // Bulatkan ke bilangan bulat — hilangkan desimal
    beratAktual = Math.floor(beratAktual);

    let textBerat = document.getElementById('g-kg');
    if (textBerat) textBerat.innerText = beratAktual + " Kg";

    let gJbi   = document.getElementById('g-jbi');
    let gBerat = document.getElementById('g-berat');
    let gLebih = document.getElementById('g-lebih');
    if (gJbi)   gJbi.innerText   = (batasJbi || 0) + " KG";
    if (gBerat) gBerat.innerText = beratAktual + " KG";
    let lebih = (beratAktual - (batasJbi || 0));
    if (gLebih) gLebih.innerText = (lebih > 0 ? "+" : "") + Math.round(lebih) + " KG";

    if (!gaugeChartObj) return;

    let persentase = 0;
    if (batasJbi > 0 && !isNaN(batasJbi)) { persentase = (beratAktual / batasJbi) * 100; }

    let textPersen = document.getElementById('kg-pct');
    if (textPersen) textPersen.innerText = "(" + persentase.toFixed(1) + "%)";

    // Warna jarum menyala berurutan sesuai beban
    let warnaMati  = '#334155';
    let warnaAktif = [warnaMati, warnaMati, warnaMati, warnaMati];
    if (beratAktual > 0)   warnaAktif[0] = '#22c55e';
    if (persentase > 25)   warnaAktif[1] = '#facc15';
    if (persentase > 50)   warnaAktif[2] = '#f97316';
    if (persentase > 75)   warnaAktif[3] = '#ef4444';

    gaugeChartObj.data.datasets[0].backgroundColor = warnaAktif;
    gaugeChartObj.data.datasets[0].needleValue     = persentase;
    gaugeChartObj.update();

    let textStatus = document.getElementById('g-status-text');
    let btnStatus  = document.getElementById('g-passed-btn');

    if (textStatus) {
      if (!platNomor || platNomor === "") {
        textStatus.innerText = "Menunggu Kendaraan..."; textStatus.style.color = "#94a3b8";
        if (btnStatus) { btnStatus.innerText = "STANDBY"; btnStatus.style.backgroundColor = "#94a3b8"; }
      } else if (beratAktual <= 0) {
        textStatus.innerText = "Kendaraan: " + platNomor + " (Siap)"; textStatus.style.color = "#3b82f6";
        if (btnStatus) { btnStatus.innerText = "STANDBY"; btnStatus.style.backgroundColor = "#3b82f6"; }
      } else if (persentase <= 25) {
        textStatus.innerText = "Sangat Aman"; textStatus.style.color = "#22c55e";
        if (btnStatus) { btnStatus.innerText = "DIIZINKAN"; btnStatus.style.backgroundColor = "#22c55e"; }
      } else if (persentase <= 50) {
        textStatus.innerText = "Aman"; textStatus.style.color = "#facc15";
        if (btnStatus) { btnStatus.innerText = "DIIZINKAN"; btnStatus.style.backgroundColor = "#facc15"; }
      } else if (persentase <= 100) {
        textStatus.innerText = "Rawan / Mendekati Batas"; textStatus.style.color = "#f97316";
        if (btnStatus) { btnStatus.innerText = "DIIZINKAN"; btnStatus.style.backgroundColor = "#f97316"; }
      } else {
        textStatus.innerText = "⚠️ OVERLOAD!"; textStatus.style.color = "#ef4444";
        if (btnStatus) { btnStatus.innerText = "DITOLAK / OVERLOAD"; btnStatus.style.backgroundColor = "#ef4444"; }
      }
    }
  }

  // ── HELPER setSelectValue ─────────────────────────────────────────────────
  // Wajib untuk <select> yang option-nya tidak punya atribut value=
  function setSelectValue(el, nilai) {
    if (!el || nilai === null || nilai === undefined) return;
    var cari = String(nilai).trim().toLowerCase();
    if (cari === '') return;
    // Pass 1: exact match pada text atau value
    for (var i = 0; i < el.options.length; i++) {
      if (el.options[i].text.trim().toLowerCase()  === cari ||
          el.options[i].value.trim().toLowerCase() === cari) {
        el.selectedIndex = i; return;
      }
    }
    // Pass 2: partial/contains match sebagai fallback
    for (var j = 0; j < el.options.length; j++) {
      var opTxt = el.options[j].text.trim().toLowerCase();
      if (opTxt.includes(cari) || cari.includes(opTxt)) {
        el.selectedIndex = j; return;
      }
    }
  }

  // ── AUTO-FILL RFID (polling tiap 1 detik) ────────────────────────────────
  // sessionStorage agar uidTerakhir tidak hilang saat refresh
  var uidTerakhir = sessionStorage.getItem('rfid_uid_terakhir') || '';

  setInterval(function() {
    fetch('fetch_logs.php?t=' + Date.now())
      .then(function(r) { return r.json(); })
      .then(function(d) {

        // ── Auto-fill hanya jika UID baru & segar (max 5 menit dari tap) ──
        if (d.uid_rfid && d.uid_rfid !== '' && d.uid_rfid !== uidTerakhir) {

          // INPUT TEXT — langsung .value
          var set = function(id, val) {
            var el = document.getElementById(id);
            if (el) el.value = (val !== null && val !== undefined) ? val : '';
          };
          if (document.querySelector('.qr-input'))
            document.querySelector('.qr-input').value = d.uid_rfid || '';

          set('f-nopol',         d.plat        || '');
          set('f-nomuji',        d.no_uji      || '');
          set('f-jbi',           d.jbi         || '');
          set('f-masaUji',       d.masa_uji    || '');
          set('f-namaPemilik',   d.nama_pemilik|| '');
          set('f-alamatPemilik', d.alamat      || '');
          set('f-dim-p',         d.panjang     || 0);
          set('f-dim-l',         d.lebar       || 0);
          set('f-dim-t',         d.tinggi      || 0);

          // SELECT — harus pakai setSelectValue
          setSelectValue(document.getElementById('f-jenis'),       d.jenis       || '');
          setSelectValue(document.getElementById('f-sumbu'),       d.sumbu       || '');
          setSelectValue(document.getElementById('f-kepemilikan'), d.kepemilikan || '');

          // Dari log penimbangan TERAKHIR kendaraan ini
          set('f-suratJalan',       d.last_surat_jalan      || '');
          set('f-pemilikKomoditi',  d.last_pemilik          || '');
          set('f-alamatKomoditi',   d.last_alamat_komoditi  || '');
          // f-komoditi, f-asal, f-tujuan → TIDAK diisi (manual tiap transaksi)

          uidTerakhir = d.uid_rfid;
          sessionStorage.setItem('rfid_uid_terakhir', uidTerakhir);
        }

        // Jika sesi dikosongkan setelah simpan → reset memori
        if (!d.uid_rfid || d.uid_rfid === '') {
          uidTerakhir = '';
          sessionStorage.removeItem('rfid_uid_terakhir');
        }

        // ── UPDATE SPEEDOMETER ───────────────────────────────────────────
        var kotakJbi    = document.getElementById('f-jbi');
        var jbiAktual   = (kotakJbi && kotakJbi.value !== '') ? parseFloat(kotakJbi.value) : (d.jbi || 1);
        var kotakNopol  = document.getElementById('f-nopol');
        var platDiLayar = kotakNopol ? kotakNopol.value : '';

        if (d.berat !== undefined) {
          updateTimbanganVisual(d.berat, jbiAktual, platDiLayar);
        }
      })
      .catch(() => {});
  }, 1000);

  // ===== 10. STATUS IOT (POLLING TIAP 2 DETIK) =====
  function fetchAlatStatus() {
    fetch('fetch_status.php')
      .then(r => r.json())
      .then(data => {
        let elEsp  = document.getElementById('stat-esp');
        let elLoad = document.getElementById('stat-loadcell');
        let elRfid = document.getElementById('stat-rfid');
        let elKon  = document.getElementById('status-koneksi-alat');

        if (elEsp)  { elEsp.innerHTML  = "● " + data.esp;      elEsp.style.color  = (data.esp      === "Online")    ? "#22c55e" : "#ef4444"; }
        if (elLoad) { elLoad.innerHTML = "● " + data.loadcell; elLoad.style.color = (data.loadcell === "Online")    ? "#22c55e" : "#ef4444"; }
        if (elRfid) { elRfid.innerHTML = "● " + data.rfid;     elRfid.style.color = (data.rfid     === "Online")    ? "#22c55e" : "#ef4444"; }
        if (elKon)  { elKon.innerHTML  = "● " + data.koneksi;  elKon.style.color  = (data.koneksi  === "Terhubung") ? "#22c55e" : "#ef4444"; }
      })
      .catch(() => {});
  }
  setInterval(fetchAlatStatus, 2000);
  fetchAlatStatus();

  // ===== 11. UPDATE LOG TABLE + NOTIF OVERLOAD (POLLING TIAP 3 DETIK) =====
  function fetchLogTable() {
    fetch('fetch_log_table.php?t=' + new Date().getTime())
      .then(r => r.json())
      .then(d => {
        // Update tabel log penimbangan
        let logContent = document.getElementById('logContent');
        if (logContent) logContent.innerHTML = d.log_html || '<tr><td colspan="6" class="empty">Belum ada data.</td></tr>';

        // Update area notifikasi overload
        let notifArea = document.getElementById('notifArea');
        if (notifArea) notifArea.innerHTML = d.notif_html || 'Belum ada pelanggaran hari ini.';
      })
      .catch(() => {
        let logContent = document.getElementById('logContent');
        if (logContent) logContent.innerHTML = '<tr><td colspan="6" class="empty" style="color:#ef4444;">Gagal memuat data. Periksa koneksi server.</td></tr>';
      });
  }
  setInterval(fetchLogTable, 3000);
  fetchLogTable(); // Langsung tampil saat halaman dibuka

  // ===== 12. UPDATE VDF TERAKHIR PER KENDARAAN (POLLING TIAP 3 DETIK) =====
  function fetchVdfTerakhir() {
    fetch('fetch_vdf_terakhir.php?t=' + new Date().getTime())
      .then(r => r.json())
      .then(data => {
        // Update angka VDF
        let teksVdf = document.getElementById('angkaVdfTerakhir');
        if (teksVdf) {
          teksVdf.innerText    = parseFloat(data.vdf).toFixed(4);
          teksVdf.style.color  = (data.vdf > 1.0) ? "#ef4444" : "#0ea5e9";
        }

        // Update posisi jarum VDF
        let needle = document.getElementById('vdfNeedle');
        if (needle) {
          let posisi = (data.vdf / 5) * 100;
          if (posisi > 100) posisi = 100;
          if (posisi < 0)   posisi = 0;
          needle.style.left = posisi + "%";
        }

        // Tampilkan info kendaraan terakhir yang timbang
        let infoEl = document.getElementById('info-vdf-kendaraan');
        if (infoEl) {
          if (data.plat && data.plat !== '-') {
            let warna  = (data.status === 'OVERLOAD') ? '#ef4444' : '#22c55e';
            infoEl.innerHTML =
              '<span style="color:#64748b;">Kendaraan Terakhir: </span>' +
              '<strong>' + data.plat + '</strong>' +
              ' &nbsp;|&nbsp; <span style="color:' + warna + ';font-weight:bold;">' + data.status + '</span>' +
              ' &nbsp;|&nbsp; <span style="color:#94a3b8;font-size:11px;">' + data.waktu + '</span>';
          } else {
            infoEl.innerHTML = '<span style="color:#94a3b8;">Belum ada data tersimpan.</span>';
          }
        }
      })
      .catch(() => {});
  }
  setInterval(fetchVdfTerakhir, 3000);
  fetchVdfTerakhir(); // Langsung tampil saat halaman dibuka

</script>
</body>
</html>