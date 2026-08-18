<?php
/**
 * api/sensor-data.php
 * POST endpoint the ESP32 + PZEM-004T firmware calls to submit a reading.
 *
 * Auth: header  X-API-KEY: <per-device key, see `devices` table>
 *
 * Expected JSON body:
 * {
 *   "device_uid": "ESP32-R204-AC01",
 *   "voltage": 230.1,
 *   "current": 21.78,
 *   "power": 5012.0,
 *   "energy": 3.42          // cumulative kWh counter from the meter
 * }
 *
 * Response: { "status": "ok", "anomaly": true|false }
 */

require_once '../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST only.']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!$payload || empty($payload['device_uid']) || !isset($payload['power'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Malformed payload']);
    exit;
}

$api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($api_key === '') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Missing X-API-KEY header']);
    exit;
}

$stmt = $pdo->prepare("SELECT device_id FROM devices WHERE api_key = ?");
$stmt->execute([$api_key]);
$device = $stmt->fetch();

if (!$device) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid API key']);
    exit;
}

$stmt = $pdo->prepare("SELECT equipment_id, status FROM equipment WHERE device_uid = ?");
$stmt->execute([$payload['device_uid']]);
$equipment = $stmt->fetch();

if (!$equipment) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Unknown device_uid — register this equipment first']);
    exit;
}
$equipment_id = $equipment['equipment_id'];

$pdo->prepare("UPDATE devices SET last_seen = NOW() WHERE device_id = ?")->execute([$device['device_id']]);

// Store the reading
$stmt = $pdo->prepare(
    "INSERT INTO readings (equipment_id, voltage, current_amp, power_watts, energy_kwh, recorded_at)
     VALUES (?,?,?,?,?,NOW())"
);
$stmt->execute([
    $equipment_id,
    $payload['voltage'] ?? null,
    $payload['current'] ?? null,
    (float) $payload['power'],
    $payload['energy'] ?? null,
]);
$reading_id = $pdo->lastInsertId();

// Threshold-based anomaly detection
$stmt = $pdo->prepare("SELECT min_power, max_power FROM thresholds WHERE equipment_id = ?");
$stmt->execute([$equipment_id]);
$threshold = $stmt->fetch();

$is_anomaly = false;
if ($threshold) {
    $power = (float) $payload['power'];
    if ($power > (float) $threshold['max_power']) {
        $is_anomaly = true;
        $pdo->prepare(
            "INSERT INTO anomalies (equipment_id, reading_id, anomaly_type, reading_value, threshold_value, status)
             VALUES (?,?, 'high_power', ?, ?, 'open')"
        )->execute([$equipment_id, $reading_id, $power, $threshold['max_power']]);
    } elseif ($power < (float) $threshold['min_power']) {
        $is_anomaly = true;
        $pdo->prepare(
            "INSERT INTO anomalies (equipment_id, reading_id, anomaly_type, reading_value, threshold_value, status)
             VALUES (?,?, 'low_power', ?, ?, 'open')"
        )->execute([$equipment_id, $reading_id, $power, $threshold['min_power']]);
    }
}

log_activity(null, 'sensor_reading', "Reading from {$payload['device_uid']}: {$payload['power']} W" . ($is_anomaly ? ' (ANOMALY)' : ''));

echo json_encode(['status' => 'ok', 'anomaly' => $is_anomaly, 'reading_id' => $reading_id]);
