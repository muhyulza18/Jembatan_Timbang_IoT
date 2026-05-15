<?php

header('Content-Type: application/json');
include 'koneksi.php';

$sql_log = "SELECT 
                DATE_FORMAT(waktu, '%H:%i:%s') AS waktu,
                plat_nomor,
                berat_aktual,
                jbi,
                status,
                ROUND(vdf, 4) AS vdf
            FROM log_penimbangan
            WHERE DATE(waktu) = CURDATE()
            ORDER BY waktu DESC
            LIMIT 50";

$res_log = mysqli_query($conn, $sql_log);
$log_html = '';

if ($res_log && mysqli_num_rows($res_log) > 0) {
    while ($row = mysqli_fetch_assoc($res_log)) {
        $cls      = ($row['status'] === 'OVERLOAD') ? 'status-overload' : 'status-normal';
        $log_html .= '<tr>';
        $log_html .= '<td>'  . htmlspecialchars($row['waktu'])        . '</td>';
        $log_html .= '<td><b>' . htmlspecialchars($row['plat_nomor']) . '</b></td>';
        $log_html .= '<td>'  . (int)round($row['berat_aktual']) . ' Kg</td>';
        $log_html .= '<td>'  . htmlspecialchars($row['jbi'])          . ' Kg</td>';
        $log_html .= '<td class="' . $cls . '">' . htmlspecialchars($row['status']) . '</td>';
        $log_html .= '<td>'  . htmlspecialchars($row['vdf'])          . '</td>';
        $log_html .= '</tr>';
    }
} else {
    $log_html = '<tr><td colspan="6" class="empty">Belum ada data penimbangan hari ini.</td></tr>';
}

$sql_notif = "SELECT 
                  plat_nomor,
                  berat_aktual,
                  jbi,
                  ROUND(vdf, 4)                          AS vdf,
                  DATE_FORMAT(waktu, '%H:%i:%s')         AS waktu_fmt
              FROM log_penimbangan
              WHERE DATE(waktu) = CURDATE()
                AND status = 'OVERLOAD'
              ORDER BY waktu DESC
              LIMIT 5";

$res_notif  = mysqli_query($conn, $sql_notif);
$notif_html = '';

if ($res_notif && mysqli_num_rows($res_notif) > 0) {
    while ($row = mysqli_fetch_assoc($res_notif)) {
        $lebih_kg = round((float)$row['berat_aktual'] - (float)$row['jbi'], 2);
        $persen   = ($row['jbi'] > 0)
                    ? round(((float)$row['berat_aktual'] / (float)$row['jbi']) * 100, 1)
                    : 0;

        $notif_html .= '<div style="background:#fff;border:1px solid #fca5a5;border-radius:6px;'
                     . 'padding:10px 14px;margin-bottom:8px;">';

        $notif_html .= '<div style="display:flex;justify-content:space-between;align-items:center;">';
        $notif_html .= '<span style="font-weight:bold;font-size:13px;color:#991b1b;">'
                     . '🚛 ' . htmlspecialchars($row['plat_nomor'])
                     . '</span>';
        $notif_html .= '<span style="font-size:11px;color:#64748b;">'
                     . htmlspecialchars($row['waktu_fmt'])
                     . '</span>';
        $notif_html .= '</div>';

        $notif_html .= '<div style="font-size:12px;margin-top:5px;color:#374151;">';
        $notif_html .= 'Berat: <b>' . (int)floor($row['berat_aktual']) . ' Kg</b>';
        $notif_html .= ' &nbsp;|&nbsp; JBI: '  . htmlspecialchars($row['jbi']) . ' Kg';
        $notif_html .= ' &nbsp;|&nbsp; <span style="color:#ef4444;font-weight:bold;">';
        $notif_html .= 'Lebih: +' . $lebih_kg . ' Kg (' . $persen . '%)';
        $notif_html .= '</span>';
        $notif_html .= ' &nbsp;|&nbsp; VDF: ' . htmlspecialchars($row['vdf']);
        $notif_html .= '</div>';

        $notif_html .= '</div>';
    }
} else {
    $notif_html = '<div style="color:#16a34a;font-size:13px;">✅ Belum ada pelanggaran <i>overload</i> hari ini.</div>';
}

echo json_encode([
    'log_html'   => $log_html,
    'notif_html' => $notif_html,
]);
?>