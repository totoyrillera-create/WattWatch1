<?php
/**
 * ajax/get-live-data.php
 * Session-authenticated AJAX endpoint polled by assets/js/script.js
 * every 10s to refresh the dashboard's live figures.
 */
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}


$totalPowerNow = (float) $pdo->query(
    "SELECT COALESCE(SUM(power_watts),0) FROM readings r
     INNER JOIN (SELECT equipment_id, MAX(recorded_at) AS max_t FROM readings GROUP BY equipment_id) latest
       ON latest.equipment_id = r.equipment_id AND latest.max_t = r.recorded_at"
)->fetchColumn();

$activeAnomalies = (int) $pdo->query("SELECT COUNT(*) FROM anomalies WHERE status='open'")->fetchColumn();

$equipRows = $pdo->query(
    "SELECT e.equipment_id, t.max_power, lr.power_watts
     FROM equipment e
     LEFT JOIN thresholds t ON t.equipment_id = e.equipment_id
     LEFT JOIN (
         SELECT rd.* FROM readings rd
         INNER JOIN (SELECT equipment_id, MAX(recorded_at) mt FROM readings GROUP BY equipment_id) x
           ON x.equipment_id = rd.equipment_id AND x.mt = rd.recorded_at
     ) lr ON lr.equipment_id = e.equipment_id
     WHERE e.status = 'active'"
)->fetchAll();

$equipment = array_map(function ($row) {
    $power = (float) ($row['power_watts'] ?? 0);
    return [
        'equipment_id' => $row['equipment_id'],
        'power_watts'  => $power,
        'is_anomaly'   => $row['max_power'] !== null && $power > (float) $row['max_power'],
    ];
}, $equipRows);

echo json_encode([
    'total_power_now'  => $totalPowerNow,
    'active_anomalies' => $activeAnomalies,
    'equipment'         => $equipment,
]);
