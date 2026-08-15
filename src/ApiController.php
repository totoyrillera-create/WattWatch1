<?php
// src/ApiController.php — REST-style API endpoints
// All responses are JSON.  Called from JS fetch() or ESP32 HTTP POST.

// Must set JSON header FIRST — before any output or errors
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Catch any PHP errors/exceptions and return them as JSON (never HTML)
set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
});
set_error_handler(function (int $errno, string $errstr) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $errstr]);
    exit;
});

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/Auth.php';

Auth::start();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ── Router ─────────────────────────────────────────────────────
try {
    match ($action) {

        // ── Auth ─────────────────────────────────────────────
        'login'  => handleLogin(),
        'logout' => handleLogout(),
        'me'     => handleMe(),

        // ── Dashboard stats ──────────────────────────────────
        'dashboard_stats' => requireAuth(fn() => dashboardStats()),

        // ── Rooms ────────────────────────────────────────────
        'get_rooms'    => requireAuth(fn() => getRooms()),
        'add_room'     => requireAuth(fn() => addRoom(),    'rooms'),
        'update_room'  => requireAuth(fn() => updateRoom(), 'rooms'),
        'delete_room'  => requireAuth(fn() => deleteRoom(), 'rooms'),

        // ── Readings (ESP32 POST + live fetch) ───────────────
        'post_reading'  => postReading(),   // no auth — ESP32 uses API token
        'get_readings'  => requireAuth(fn() => getReadings()),
        'get_chart'     => requireAuth(fn() => getChart()),

        // ── Anomalies ────────────────────────────────────────
        'get_anomalies'    => requireAuth(fn() => getAnomalies()),
        'resolve_anomaly'  => requireAuth(fn() => resolveAnomaly(), 'anomalies'),

        // ── Thresholds ───────────────────────────────────────
        'set_threshold' => requireAuth(fn() => setThreshold(), 'thresholds'),

        // ── Users ────────────────────────────────────────────
        'get_users'    => requireAuth(fn() => getUsers(),   'users'),
        'add_user'     => requireAuth(fn() => addUser(),    'users'),
        'update_user'  => requireAuth(fn() => updateUser(), 'users'),
        'delete_user'  => requireAuth(fn() => deleteUser(), 'users'),
        'toggle_user'  => requireAuth(fn() => toggleUser(), 'users'),

        // ── Logs ─────────────────────────────────────────────
        'get_logs' => requireAuth(fn() => getLogs(), 'logs'),

        // ── Reports ──────────────────────────────────────────
        'get_report' => requireAuth(fn() => getReport()),

        // ── Settings ─────────────────────────────────────────
        'get_settings'  => requireAuth(fn() => getSettings(),  'settings'),
        'save_settings' => requireAuth(fn() => saveSettings(), 'settings'),

        // ── Profile ──────────────────────────────────────────
        'update_profile'  => requireAuth(fn() => updateProfile()),
        'change_password' => requireAuth(fn() => changePassword()),

        default => json('error', 'Unknown action')
    };
} catch (Throwable $e) {
    json('error', $e->getMessage());
}

// ── Helpers ─────────────────────────────────────────────────────

function json(string $status, mixed $data = null, mixed $payload = null): void {
    $out = ['status' => $status];
    if (is_string($data)) $out['message'] = $data;
    else                  $out['data']    = $data;
    if ($payload !== null) $out['extra']  = $payload;
    echo json_encode($out);
    exit;
}

function body(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw ?: '{}', true) ?? $_POST;
}

function requireAuth(callable $fn, string $perm = ''): void {
    if (!Auth::check()) { json('error', 'Unauthenticated'); }
    if ($perm && !Auth::can($perm)) { json('error', 'Forbidden'); }
    $fn();
}

// ── Auth handlers ────────────────────────────────────────────────

function handleLogin(): void {
    $b    = body();
    $user = Auth::attempt($b['email'] ?? '', $b['password'] ?? '');
    if (!$user) { json('error', 'Invalid credentials or account inactive.'); }
    // Return user data directly — JS reads json.data
    echo json_encode(['status' => 'ok', 'data' => $user]);
    exit;
}

function handleLogout(): void {
    Auth::logout();
    json('ok', 'Logged out');
}

function handleMe(): void {
    $user = Auth::user();
    if (!$user) { json('error', 'Not authenticated'); }
    json('ok', $user);
}

// ── Dashboard ────────────────────────────────────────────────────

function dashboardStats(): void {
    $db = Database::connect();

    // Latest reading per room
    $rooms = $db->query(
        'SELECT r.room_id, r.room_name, r.equipment_label, r.threshold_watts, r.status,
                b.building_name,
                et.type_name, et.icon_key,
                rd.voltage, rd.current_amp, rd.power_watts, rd.energy_kwh, rd.read_at
         FROM   rooms r
         JOIN   buildings      b  ON b.building_id = r.building_id
         JOIN   equipment_types et ON et.type_id   = r.type_id
         LEFT JOIN readings rd ON rd.reading_id = (
             SELECT MAX(reading_id) FROM readings WHERE room_id = r.room_id
         )
         WHERE  r.is_active = 1
         ORDER  BY r.room_id'
    )->fetchAll();

    $totalPower = array_sum(array_column($rooms, 'power_watts'));

    // Today energy sum
    $todayEnergy = $db->query(
        'SELECT COALESCE(SUM(energy_kwh),0) FROM readings WHERE DATE(read_at)=CURDATE()'
    )->fetchColumn();

    // Month energy
    $monthEnergy = $db->query(
        'SELECT COALESCE(SUM(energy_kwh),0) FROM readings
         WHERE YEAR(read_at)=YEAR(NOW()) AND MONTH(read_at)=MONTH(NOW())'
    )->fetchColumn();

    // Active anomalies
    $activeAnomalies = $db->query(
        'SELECT COUNT(*) FROM anomalies WHERE status="active"'
    )->fetchColumn();

    json('ok', [
        'total_power'      => (float)$totalPower,
        'today_energy'     => (float)$todayEnergy,
        'month_energy'     => (float)$monthEnergy,
        'active_anomalies' => (int)$activeAnomalies,
        'rooms'            => $rooms,
    ]);
}

// ── Rooms ─────────────────────────────────────────────────────────

function getRooms(): void {
    $db   = Database::connect();
    $rows = $db->query(
        'SELECT r.*, b.building_name, et.type_name, et.icon_key
         FROM   rooms r
         JOIN   buildings b       ON b.building_id = r.building_id
         JOIN   equipment_types et ON et.type_id   = r.type_id
         WHERE  r.is_active = 1 ORDER BY r.room_id'
    )->fetchAll();
    json('ok', $rows);
}

function addRoom(): void {
    $b  = body();
    $db = Database::connect();

    // Resolve or insert building
    $bid = $db->prepare('SELECT building_id FROM buildings WHERE building_name=?');
    $bid->execute([$b['building_name'] ?? 'Building A']);
    $buildingId = $bid->fetchColumn();
    if (!$buildingId) {
        $db->prepare('INSERT INTO buildings (building_name) VALUES (?)')->execute([$b['building_name']]);
        $buildingId = $db->lastInsertId();
    }

    $db->prepare(
        'INSERT INTO rooms (building_id, type_id, room_name, equipment_label, threshold_watts)
         VALUES (?,?,?,?,?)'
    )->execute([$buildingId, $b['type_id'] ?? 8, $b['room_name'], $b['equipment_label'], $b['threshold_watts'] ?? 1000]);

    Auth::log(Auth::user()['user_id'], 'room', 'Added room: ' . $b['room_name']);
    json('ok', 'Room added');
}

function updateRoom(): void {
    $b  = body();
    $db = Database::connect();
    $db->prepare(
        'UPDATE rooms SET room_name=?, equipment_label=?, threshold_watts=? WHERE room_id=?'
    )->execute([$b['room_name'], $b['equipment_label'], $b['threshold_watts'], $b['room_id']]);
    Auth::log(Auth::user()['user_id'], 'room', 'Updated room ID: ' . $b['room_id']);
    json('ok', 'Room updated');
}

function deleteRoom(): void {
    $b  = body();
    $db = Database::connect();
    $db->prepare('UPDATE rooms SET is_active=0 WHERE room_id=?')->execute([$b['room_id']]);
    Auth::log(Auth::user()['user_id'], 'room', 'Deleted room ID: ' . $b['room_id']);
    json('ok', 'Room removed');
}

// ── Readings (ESP32 endpoint) ────────────────────────────────────

function postReading(): void {
    // Validate simple API token (set in ESP32 firmware)
    $token = $_SERVER['HTTP_X_API_TOKEN'] ?? '';
    if ($token !== 'ESP32_SECRET_TOKEN_CHANGE_ME') {
        http_response_code(401);
        json('error', 'Unauthorized');
    }

    $b  = body();
    $db = Database::connect();

    $db->prepare(
        'INSERT INTO readings (room_id, voltage, current_amp, power_watts, energy_kwh)
         VALUES (?,?,?,?,?)'
    )->execute([$b['room_id'], $b['voltage'], $b['current'], $b['power'], $b['energy']]);
    $readingId = $db->lastInsertId();

    // Fetch threshold
    $threshold = $db->prepare('SELECT threshold_watts FROM rooms WHERE room_id=?');
    $threshold->execute([$b['room_id']]);
    $limit = (float)$threshold->fetchColumn();

    // Anomaly detection
    if ((float)$b['power'] > $limit) {
        $typeId = $db->query('SELECT anomaly_type_id FROM anomaly_types WHERE type_label="HIGH POWER"')->fetchColumn();
        $db->prepare(
            'INSERT INTO anomalies (room_id, reading_id, anomaly_type_id, power_at_event, threshold_used)
             VALUES (?,?,?,?,?)'
        )->execute([$b['room_id'], $readingId, $typeId, $b['power'], $limit]);

        $db->prepare("UPDATE rooms SET status='anomaly' WHERE room_id=?")->execute([$b['room_id']]);
        Auth::log(null, 'anomaly', 'Anomaly detected in room_id ' . $b['room_id'] . ' — power: ' . $b['power'] . ' W');
    } else {
        // Clear anomaly flag if back to normal
        $db->prepare("UPDATE rooms SET status='normal' WHERE room_id=?")->execute([$b['room_id']]);
    }

    json('ok', ['reading_id' => (int)$readingId]);
}

function getReadings(): void {
    $db     = Database::connect();
    $roomId = (int)($_GET['room_id'] ?? 0);
    $limit  = min((int)($_GET['limit'] ?? 50), 500);

    $stmt = $db->prepare(
        'SELECT * FROM readings WHERE room_id=? ORDER BY read_at DESC LIMIT ?'
    );
    $stmt->execute([$roomId, $limit]);
    json('ok', $stmt->fetchAll());
}

function getChart(): void {
    $db     = Database::connect();
    $roomId = (int)($_GET['room_id'] ?? 0);
    $period = $_GET['period'] ?? 'today';

    $where = match ($period) {
        'week'  => 'DATE(read_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)',
        'month' => 'YEAR(read_at)=YEAR(NOW()) AND MONTH(read_at)=MONTH(NOW())',
        default => 'DATE(read_at) = CURDATE()',
    };

    $stmt = $db->prepare(
        "SELECT DATE_FORMAT(read_at,'%H:%i') AS label, power_watts AS value
         FROM readings WHERE room_id=? AND $where ORDER BY read_at ASC"
    );
    $stmt->execute([$roomId]);
    json('ok', $stmt->fetchAll());
}

// ── Anomalies ────────────────────────────────────────────────────

function getAnomalies(): void {
    $db     = Database::connect();
    $status = $_GET['status'] ?? 'all';
    $where  = $status !== 'all' ? "WHERE a.status='$status'" : '';

    $rows = $db->query(
        "SELECT a.*, r.room_name, r.equipment_label,
                at2.type_label, u.full_name AS resolved_by_name
         FROM anomalies a
         JOIN rooms r           ON r.room_id         = a.room_id
         JOIN anomaly_types at2 ON at2.anomaly_type_id = a.anomaly_type_id
         LEFT JOIN users u      ON u.user_id          = a.resolved_by
         $where
         ORDER BY a.detected_at DESC LIMIT 100"
    )->fetchAll();
    json('ok', $rows);
}

function resolveAnomaly(): void {
    $b   = body();
    $db  = Database::connect();
    $uid = Auth::user()['user_id'];
    $db->prepare(
        "UPDATE anomalies SET status='resolved', resolved_by=?, resolved_at=NOW() WHERE anomaly_id=?"
    )->execute([$uid, $b['anomaly_id']]);
    Auth::log($uid, 'anomaly', 'Resolved anomaly ID: ' . $b['anomaly_id']);
    json('ok', 'Anomaly resolved');
}

// ── Thresholds ───────────────────────────────────────────────────

function setThreshold(): void {
    $b  = body();
    $db = Database::connect();
    $db->prepare('UPDATE rooms SET threshold_watts=? WHERE room_id=?')
       ->execute([$b['threshold_watts'], $b['room_id']]);
    Auth::log(Auth::user()['user_id'], 'settings', 'Set threshold for room_id ' . $b['room_id'] . ' to ' . $b['threshold_watts'] . ' W');
    json('ok', 'Threshold updated');
}

// ── Users ─────────────────────────────────────────────────────────

function getUsers(): void {
    $db   = Database::connect();
    $rows = $db->query(
        'SELECT u.user_id, u.full_name, u.email, u.avatar, u.department,
                u.status, u.last_login, u.created_at,
                r.role_key, r.role_name
         FROM users u JOIN roles r ON r.role_id = u.role_id
         ORDER BY u.user_id'
    )->fetchAll();
    json('ok', $rows);
}

function addUser(): void {
    $b  = body();
    $db = Database::connect();

    $roleId = $db->prepare('SELECT role_id FROM roles WHERE role_key=?');
    $roleId->execute([$b['role_key'] ?? 'viewer']);
    $rid = $roleId->fetchColumn();

    $hash = password_hash($b['password'], PASSWORD_BCRYPT);
    $db->prepare(
        'INSERT INTO users (role_id, full_name, email, password, department, avatar, status)
         VALUES (?,?,?,?,?,?,?)'
    )->execute([$rid, $b['full_name'], $b['email'], $hash, $b['department'] ?? null,
                strtoupper(substr($b['full_name'], 0, 2)), 'active']);

    Auth::log(Auth::user()['user_id'], 'settings', 'Added user: ' . $b['email']);
    json('ok', 'User added');
}

function updateUser(): void {
    $b  = body();
    $db = Database::connect();

    $roleId = $db->prepare('SELECT role_id FROM roles WHERE role_key=?');
    $roleId->execute([$b['role_key'] ?? 'viewer']);
    $rid = $roleId->fetchColumn();

    $db->prepare(
        'UPDATE users SET role_id=?, full_name=?, email=?, department=? WHERE user_id=?'
    )->execute([$rid, $b['full_name'], $b['email'], $b['department'] ?? null, $b['user_id']]);

    Auth::log(Auth::user()['user_id'], 'settings', 'Updated user ID: ' . $b['user_id']);
    json('ok', 'User updated');
}

function deleteUser(): void {
    $b  = body();
    if ((int)$b['user_id'] === Auth::user()['user_id']) json('error', 'Cannot delete yourself');
    Database::connect()->prepare('DELETE FROM users WHERE user_id=?')->execute([$b['user_id']]);
    Auth::log(Auth::user()['user_id'], 'settings', 'Deleted user ID: ' . $b['user_id']);
    json('ok', 'User deleted');
}

function toggleUser(): void {
    $b  = body();
    $db = Database::connect();
    $db->prepare(
        "UPDATE users SET status = IF(status='active','inactive','active') WHERE user_id=?"
    )->execute([$b['user_id']]);
    Auth::log(Auth::user()['user_id'], 'settings', 'Toggled status for user ID: ' . $b['user_id']);
    json('ok', 'Status toggled');
}

// ── Logs ──────────────────────────────────────────────────────────

function getLogs(): void {
    $db   = Database::connect();
    $rows = $db->query(
        'SELECT l.*, u.full_name FROM activity_logs l
         LEFT JOIN users u ON u.user_id = l.user_id
         ORDER BY l.logged_at DESC LIMIT 200'
    )->fetchAll();
    json('ok', $rows);
}

// ── Reports ───────────────────────────────────────────────────────

function getReport(): void {
    $db     = Database::connect();
    $period = $_GET['period'] ?? 'daily';

    $dateFilter = match ($period) {
        'weekly'  => 'DATE(r.read_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)',
        'monthly' => 'YEAR(r.read_at)=YEAR(NOW()) AND MONTH(r.read_at)=MONTH(NOW())',
        default   => 'DATE(r.read_at) = CURDATE()',
    };

    $summary = $db->query(
        "SELECT COALESCE(SUM(r.energy_kwh),0) AS total_energy,
                COALESCE(MAX(r.power_watts),0) AS peak_power,
                COUNT(DISTINCT r.room_id)      AS rooms_monitored
         FROM readings r WHERE $dateFilter"
    )->fetch();

    $anomalyCount = $db->query(
        "SELECT COUNT(*) FROM anomalies WHERE $dateFilter"
        // reuse same date filter string — anomalies uses detected_at; quick approximation
    )->fetchColumn();

    $byRoom = $db->query(
        "SELECT rm.room_name, rm.equipment_label,
                COALESCE(SUM(r.energy_kwh),0) AS energy,
                COALESCE(AVG(r.power_watts),0) AS avg_power,
                COALESCE(MAX(r.power_watts),0) AS peak_power
         FROM rooms rm
         LEFT JOIN readings r ON r.room_id = rm.room_id AND $dateFilter
         WHERE rm.is_active=1
         GROUP BY rm.room_id ORDER BY energy DESC"
    )->fetchAll();

    json('ok', [
        'summary'       => $summary,
        'anomaly_count' => (int)$anomalyCount,
        'by_room'       => $byRoom,
    ]);
}

// ── Settings ──────────────────────────────────────────────────────

function getSettings(): void {
    $db   = Database::connect();
    $rows = $db->query('SELECT setting_key, setting_value FROM system_settings')->fetchAll();
    $out  = [];
    foreach ($rows as $r) $out[$r['setting_key']] = $r['setting_value'];
    json('ok', $out);
}

function saveSettings(): void {
    $b  = body();
    $db = Database::connect();
    $s  = $db->prepare('INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?');
    foreach ($b as $k => $v) $s->execute([$k, $v, $v]);
    Auth::log(Auth::user()['user_id'], 'settings', 'System settings updated');
    json('ok', 'Settings saved');
}

// ── Profile ───────────────────────────────────────────────────────

function updateProfile(): void {
    $b   = body();
    $db  = Database::connect();
    $uid = Auth::user()['user_id'];
    $db->prepare('UPDATE users SET full_name=?, email=?, department=? WHERE user_id=?')
       ->execute([$b['full_name'], $b['email'], $b['department'] ?? null, $uid]);

    // Refresh session
    $_SESSION['user']['full_name']  = $b['full_name'];
    $_SESSION['user']['email']      = $b['email'];
    Auth::log($uid, 'auth', 'Profile updated');
    json('ok', 'Profile updated');
}

function changePassword(): void {
    $b   = body();
    $db  = Database::connect();
    $uid = Auth::user()['user_id'];

    $hash = $db->prepare('SELECT password FROM users WHERE user_id=?');
    $hash->execute([$uid]);
    if (!password_verify($b['current_password'], $hash->fetchColumn())) {
        json('error', 'Current password is incorrect.');
    }
    if ($b['new_password'] !== $b['confirm_password']) json('error', 'Passwords do not match.');
    if (strlen($b['new_password']) < 6)                json('error', 'Password must be at least 6 characters.');

    $db->prepare('UPDATE users SET password=? WHERE user_id=?')
       ->execute([password_hash($b['new_password'], PASSWORD_BCRYPT), $uid]);
    Auth::log($uid, 'auth', 'Password changed');
    json('ok', 'Password updated');
}
