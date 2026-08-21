<?php
// WattWatch — ApiController (all JSON endpoints)

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

set_exception_handler(fn($e) => die(json_encode(['status'=>'error','message'=>$e->getMessage()])));
set_error_handler(fn($n,$s) => die(json_encode(['status'=>'error','message'=>$s])));

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/Auth.php';

Auth::start();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    match ($action) {
        'login'           => handleLogin(),
        'logout'          => handleLogout(),
        'me'              => handleMe(),
        'dashboard_stats' => guard(fn() => dashboardStats()),
        'get_rooms'       => guard(fn() => getRooms()),
        'add_room'        => guard(fn() => addRoom(),    'rooms'),
        'update_room'     => guard(fn() => updateRoom(), 'rooms'),
        'delete_room'     => guard(fn() => deleteRoom(), 'rooms'),
        'post_reading'    => postReading(),
        'get_readings'    => guard(fn() => getReadings()),
        'get_chart'       => guard(fn() => getChart()),
        'get_anomalies'   => guard(fn() => getAnomalies()),
        'resolve_anomaly' => guard(fn() => resolveAnomaly(), 'anomalies'),
        'set_threshold'   => guard(fn() => setThreshold(), 'thresholds'),
        'get_users'       => guard(fn() => getUsers(),   'users'),
        'add_user'        => guard(fn() => addUser(),    'users'),
        'update_user'     => guard(fn() => updateUser(), 'users'),
        'delete_user'     => guard(fn() => deleteUser(), 'users'),
        'toggle_user'     => guard(fn() => toggleUser(), 'users'),
        'get_logs'        => guard(fn() => getLogs(), 'logs'),
        'get_report'      => guard(fn() => getReport()),
        'get_analytics'   => guard(fn() => getAnalytics()),
        'cleanup_anomalies' => guard(fn() => cleanupAnomalies()),
        'get_settings'    => guard(fn() => getSettings(),  'settings'),
        'save_settings'   => guard(fn() => saveSettings(), 'settings'),
        'update_profile'  => guard(fn() => updateProfile()),
        'change_password' => guard(fn() => changePassword()),
        default           => out('error', 'Unknown action'),
    };
} catch (Throwable $e) { out('error', $e->getMessage()); }

// ── Helpers ────────────────────────────────────────────────────

function out(string $status, mixed $data = null): void {
    echo json_encode(is_string($data) ? ['status'=>$status,'message'=>$data] : ['status'=>$status,'data'=>$data]);
    exit;
}

function body(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw ?: '{}', true) ?? $_POST;
}

function guard(callable $fn, string $perm = ''): void {
    if (!Auth::check()) out('error', 'Unauthenticated');
    if ($perm && !Auth::can($perm)) out('error', 'Forbidden');
    $fn();
}

// ── Auth ───────────────────────────────────────────────────────

function handleLogin(): void {
    $b    = body();
    $user = Auth::attempt($b['email'] ?? '', $b['password'] ?? '');
    if (!$user) out('error', 'Invalid credentials or account inactive.');
    echo json_encode(['status'=>'ok','data'=>$user]); exit;
}

function handleLogout(): void { Auth::logout(); out('ok', 'Logged out'); }

function handleMe(): void {
    $u = Auth::user();
    if (!$u) out('error', 'Not authenticated');
    out('ok', $u);
}

// ── Dashboard ─────────────────────────────────────────────────

function dashboardStats(): void {
    $db = Database::connect();

    $rooms = $db->query(
        'SELECT r.room_id, r.room_name, r.equipment_label, r.threshold_watts, r.status,
                b.building_name, et.type_name, et.icon_key,
                rd.voltage, rd.current_amp, rd.power_watts, rd.energy_kwh, rd.read_at
         FROM rooms r
         JOIN buildings b       ON b.building_id = r.building_id
         JOIN equipment_types et ON et.type_id   = r.type_id
         LEFT JOIN readings rd  ON rd.reading_id = (
             SELECT MAX(reading_id) FROM readings WHERE room_id = r.room_id
         )
         WHERE r.is_active = 1 ORDER BY r.room_id'
    )->fetchAll();

    out('ok', [
        'total_power'      => (float) array_sum(array_column($rooms, 'power_watts')),
        'today_energy'     => (float) $db->query("SELECT COALESCE(SUM(energy_kwh),0) FROM readings WHERE DATE(read_at)=CURDATE()")->fetchColumn(),
        'month_energy'     => (float) $db->query("SELECT COALESCE(SUM(energy_kwh),0) FROM readings WHERE YEAR(read_at)=YEAR(NOW()) AND MONTH(read_at)=MONTH(NOW())")->fetchColumn(),
        'active_anomalies' => (int)   $db->query("SELECT COUNT(*) FROM anomalies WHERE status='active'")->fetchColumn(),
        'rooms'            => $rooms,
    ]);
}

// ── Rooms ─────────────────────────────────────────────────────

function getRooms(): void {
    $rows = Database::connect()->query(
        'SELECT r.*, b.building_name, et.type_name, et.icon_key,
                (SELECT power_watts FROM readings WHERE room_id=r.room_id ORDER BY read_at DESC LIMIT 1) AS power_watts
         FROM rooms r
         JOIN buildings b       ON b.building_id = r.building_id
         JOIN equipment_types et ON et.type_id   = r.type_id
         WHERE r.is_active=1 ORDER BY r.room_id'
    )->fetchAll();
    out('ok', $rows);
}

function addRoom(): void {
    $b = body(); $db = Database::connect();
    $bid = $db->prepare('SELECT building_id FROM buildings WHERE building_name=?');
    $bid->execute([$b['building_name'] ?? 'Building A']);
    $buildingId = $bid->fetchColumn();
    if (!$buildingId) {
        $db->prepare('INSERT INTO buildings (building_name) VALUES (?)')->execute([$b['building_name']]);
        $buildingId = $db->lastInsertId();
    }
    $db->prepare('INSERT INTO rooms (building_id,type_id,room_name,equipment_label,threshold_watts) VALUES (?,?,?,?,?)')
       ->execute([$buildingId, $b['type_id']??8, $b['room_name'], $b['equipment_label'], $b['threshold_watts']??1000]);
    Auth::log(Auth::user()['user_id'], 'room', 'Added room: ' . $b['room_name']);
    out('ok', 'Room added');
}

function updateRoom(): void {
    $b = body(); $db = Database::connect();
    $db->prepare('UPDATE rooms SET room_name=?,equipment_label=?,threshold_watts=? WHERE room_id=?')
       ->execute([$b['room_name'], $b['equipment_label'], $b['threshold_watts'], $b['room_id']]);
    Auth::log(Auth::user()['user_id'], 'room', 'Updated room ID: ' . $b['room_id']);
    out('ok', 'Room updated');
}

function deleteRoom(): void {
    $b = body();
    Database::connect()->prepare('UPDATE rooms SET is_active=0 WHERE room_id=?')->execute([$b['room_id']]);
    Auth::log(Auth::user()['user_id'], 'room', 'Removed room ID: ' . $b['room_id']);
    out('ok', 'Room removed');
}

// ── Readings (ESP32 endpoint — token auth) ────────────────────

function postReading(): void {
    if (($_SERVER['HTTP_X_API_TOKEN'] ?? '') !== 'ESP32_SECRET_TOKEN_CHANGE_ME') {
        http_response_code(401); out('error', 'Unauthorized');
    }
    $b = body(); $db = Database::connect();
    $db->prepare('INSERT INTO readings (room_id,voltage,current_amp,power_watts,energy_kwh) VALUES (?,?,?,?,?)')
       ->execute([$b['room_id'], $b['voltage'], $b['current'], $b['power'], $b['energy']]);
    $rid = $db->lastInsertId();
    $limit = (float) $db->prepare('SELECT threshold_watts FROM rooms WHERE room_id=?')
                        ->execute([$b['room_id']]) ? $db->query("SELECT threshold_watts FROM rooms WHERE room_id={$b['room_id']}")->fetchColumn() : 1000;
    if ((float)$b['power'] > $limit) {
        $typeId = $db->query("SELECT anomaly_type_id FROM anomaly_types WHERE type_label='HIGH POWER'")->fetchColumn();
        $db->prepare('INSERT INTO anomalies (room_id,reading_id,anomaly_type_id,power_at_event,threshold_used) VALUES (?,?,?,?,?)')
           ->execute([$b['room_id'], $rid, $typeId, $b['power'], $limit]);
        $db->prepare("UPDATE rooms SET status='anomaly' WHERE room_id=?")->execute([$b['room_id']]);
        Auth::log(null, 'anomaly', "Anomaly: room_id {$b['room_id']} — {$b['power']} W");
    } else {
        $db->prepare("UPDATE rooms SET status='normal' WHERE room_id=?")->execute([$b['room_id']]);
    }
    out('ok', ['reading_id' => (int)$rid]);
}

function getReadings(): void {
    $db = Database::connect();
    $rid = (int)($_GET['room_id']??0);
    $lim = min((int)($_GET['limit']??50), 500);
    $s = $db->prepare('SELECT * FROM readings WHERE room_id=? ORDER BY read_at DESC LIMIT ?');
    $s->execute([$rid, $lim]);
    out('ok', $s->fetchAll());
}

function getChart(): void {
    $db = Database::connect();
    $rid = (int)($_GET['room_id']??0);
    $where = match ($_GET['period']??'today') {
        'week'  => 'DATE(read_at)>=DATE_SUB(CURDATE(),INTERVAL 7 DAY)',
        'month' => 'YEAR(read_at)=YEAR(NOW()) AND MONTH(read_at)=MONTH(NOW())',
        default => 'DATE(read_at)=CURDATE()',
    };
    $s = $db->prepare("SELECT DATE_FORMAT(read_at,'%H:%i') AS label, power_watts AS value FROM readings WHERE room_id=? AND $where ORDER BY read_at ASC");
    $s->execute([$rid]);
    out('ok', $s->fetchAll());
}

// ── Anomalies ─────────────────────────────────────────────────

function getAnomalies(): void {
    $db = Database::connect();
    $filter = $_GET['status'] ?? 'all';
    $where = $filter !== 'all' ? "WHERE a.status='$filter'" : '';
    out('ok', $db->query(
        "SELECT a.*,r.room_name,r.equipment_label,at2.type_label,u.full_name AS resolved_by_name
         FROM anomalies a
         JOIN rooms r           ON r.room_id          = a.room_id
         JOIN anomaly_types at2 ON at2.anomaly_type_id = a.anomaly_type_id
         LEFT JOIN users u      ON u.user_id           = a.resolved_by
         $where ORDER BY a.detected_at DESC LIMIT 100"
    )->fetchAll());
}

function resolveAnomaly(): void {
    $b = body(); $uid = Auth::user()['user_id'];
    Database::connect()->prepare("UPDATE anomalies SET status='resolved',resolved_by=?,resolved_at=NOW() WHERE anomaly_id=?")
        ->execute([$uid, $b['anomaly_id']]);
    Auth::log($uid, 'anomaly', 'Resolved anomaly ID: ' . $b['anomaly_id']);
    out('ok', 'Resolved');
}

// ── Thresholds ────────────────────────────────────────────────

function setThreshold(): void {
    $b = body();
    Database::connect()->prepare('UPDATE rooms SET threshold_watts=? WHERE room_id=?')
        ->execute([$b['threshold_watts'], $b['room_id']]);
    Auth::log(Auth::user()['user_id'], 'settings', "Threshold set: room_id {$b['room_id']} → {$b['threshold_watts']} W");
    out('ok', 'Threshold updated');
}

// ── Users ─────────────────────────────────────────────────────

function getUsers(): void {
    out('ok', Database::connect()->query(
        'SELECT u.user_id,u.full_name,u.email,u.avatar,u.department,u.status,u.last_login,r.role_key,r.role_name
         FROM users u JOIN roles r ON r.role_id=u.role_id ORDER BY u.user_id'
    )->fetchAll());
}

function addUser(): void {
    $b = body(); $db = Database::connect();
    $rid = $db->prepare('SELECT role_id FROM roles WHERE role_key=?');
    $rid->execute([$b['role_key'] ?? 'staff']);
    $roleId = $rid->fetchColumn();
    $db->prepare('INSERT INTO users (role_id,full_name,email,password,department,avatar,status) VALUES (?,?,?,?,?,?,?)')
       ->execute([$roleId, $b['full_name'], $b['email'], password_hash($b['password'], PASSWORD_BCRYPT),
                  $b['department']??null, strtoupper(substr($b['full_name'],0,2)), 'active']);
    Auth::log(Auth::user()['user_id'], 'settings', 'Added user: ' . $b['email']);
    out('ok', 'User added');
}

function updateUser(): void {
    $b = body(); $db = Database::connect();
    $rid = $db->prepare('SELECT role_id FROM roles WHERE role_key=?');
    $rid->execute([$b['role_key'] ?? 'staff']);
    $roleId = $rid->fetchColumn();
    $db->prepare('UPDATE users SET role_id=?,full_name=?,email=?,department=? WHERE user_id=?')
       ->execute([$roleId, $b['full_name'], $b['email'], $b['department']??null, $b['user_id']]);
    Auth::log(Auth::user()['user_id'], 'settings', 'Updated user ID: ' . $b['user_id']);
    out('ok', 'User updated');
}

function deleteUser(): void {
    $b = body();
    if ((int)$b['user_id'] === Auth::user()['user_id']) out('error', 'Cannot delete yourself');
    Database::connect()->prepare('DELETE FROM users WHERE user_id=?')->execute([$b['user_id']]);
    Auth::log(Auth::user()['user_id'], 'settings', 'Deleted user ID: ' . $b['user_id']);
    out('ok', 'User deleted');
}

function toggleUser(): void {
    $b = body();
    Database::connect()->prepare("UPDATE users SET status=IF(status='active','inactive','active') WHERE user_id=?")
        ->execute([$b['user_id']]);
    Auth::log(Auth::user()['user_id'], 'settings', 'Toggled user ID: ' . $b['user_id']);
    out('ok', 'Status toggled');
}

// ── Logs ──────────────────────────────────────────────────────

function getLogs(): void {
    out('ok', Database::connect()->query(
        'SELECT l.*,u.full_name FROM activity_logs l LEFT JOIN users u ON u.user_id=l.user_id ORDER BY l.logged_at DESC LIMIT 200'
    )->fetchAll());
}

// ── Reports ───────────────────────────────────────────────────

function getReport(): void {
    $db = Database::connect();
    $f = match ($_GET['period'] ?? 'daily') {
        'weekly'  => 'DATE(r.read_at)>=DATE_SUB(CURDATE(),INTERVAL 7 DAY)',
        'monthly' => 'YEAR(r.read_at)=YEAR(NOW()) AND MONTH(r.read_at)=MONTH(NOW())',
        default   => 'DATE(r.read_at)=CURDATE()',
    };
    out('ok', [
        'summary'       => $db->query("SELECT COALESCE(SUM(r.energy_kwh),0) AS total_energy,COALESCE(MAX(r.power_watts),0) AS peak_power,COUNT(DISTINCT r.room_id) AS rooms_monitored FROM readings r WHERE $f")->fetch(),
        'anomaly_count' => (int) $db->query("SELECT COUNT(*) FROM anomalies WHERE $f")->fetchColumn(),
        'by_room'       => $db->query("SELECT rm.room_name,rm.equipment_label,COALESCE(SUM(r.energy_kwh),0) AS energy,COALESCE(AVG(r.power_watts),0) AS avg_power,COALESCE(MAX(r.power_watts),0) AS peak_power FROM rooms rm LEFT JOIN readings r ON r.room_id=rm.room_id AND $f WHERE rm.is_active=1 GROUP BY rm.room_id ORDER BY energy DESC")->fetchAll(),
    ]);
}

// ── Analytics ────────────────────────────────────────────────

function getAnalytics(): void {
    $db = Database::connect();

    $hourly = $db->query("SELECT HOUR(read_at) AS hr,ROUND(AVG(power_watts),1) AS avg_power FROM readings WHERE read_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY HOUR(read_at) ORDER BY hr")->fetchAll();
    $hourlyMap = array_fill(0, 24, 0);
    foreach ($hourly as $h) $hourlyMap[(int)$h['hr']] = (float)$h['avg_power'];

    $weekly = $db->query("SELECT DATE(read_at) AS day,DAYNAME(read_at) AS day_name,ROUND(SUM(energy_kwh),2) AS total_kwh FROM readings WHERE read_at>=DATE_SUB(CURDATE(),INTERVAL 7 DAY) GROUP BY DATE(read_at) ORDER BY day")->fetchAll();

    $rooms = $db->query("SELECT r.room_id,r.room_name,r.equipment_label,ROUND(AVG(rd.power_watts),1) AS avg_power,ROUND(MAX(rd.power_watts),1) AS peak_power,ROUND(SUM(rd.energy_kwh),2) AS total_kwh,COUNT(DISTINCT a.anomaly_id) AS anomaly_count FROM rooms r LEFT JOIN readings rd ON rd.room_id=r.room_id AND rd.read_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) LEFT JOIN anomalies a ON a.room_id=r.room_id AND a.detected_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) WHERE r.is_active=1 GROUP BY r.room_id ORDER BY total_kwh DESC")->fetchAll();

    $weekTotal  = (float)($db->query("SELECT COALESCE(SUM(energy_kwh),0) FROM readings WHERE read_at>=DATE_SUB(CURDATE(),INTERVAL 7 DAY)")->fetchColumn());
    $dailyAvg   = (float)($db->query("SELECT COALESCE(AVG(day_total),0) FROM (SELECT DATE(read_at) d,SUM(energy_kwh) day_total FROM readings WHERE read_at>=DATE_SUB(CURDATE(),INTERVAL 30 DAY) GROUP BY d) t")->fetchColumn());
    $rate       = (float)($db->query("SELECT setting_value FROM system_settings WHERE setting_key='kwh_rate'")->fetchColumn() ?: 6.00);
    $aStats     = $db->query("SELECT COUNT(*) AS total,SUM(power_at_event-threshold_used) AS total_excess,MIN(HOUR(detected_at)) AS first_hour,MAX(HOUR(detected_at)) AS last_hour FROM anomalies WHERE detected_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetch();
    $topRoom    = $db->query("SELECT r.room_name,r.equipment_label,COUNT(*) AS cnt FROM anomalies a JOIN rooms r ON r.room_id=a.room_id WHERE a.detected_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY a.room_id ORDER BY cnt DESC LIMIT 1")->fetch();

    out('ok', [
        'hourly_pattern' => array_values($hourlyMap),
        'weekly'         => $weekly,
        'rooms'          => $rooms,
        'week_total_kwh' => $weekTotal,
        'daily_avg_kwh'  => $dailyAvg,
        'month_forecast' => round($dailyAvg * 30, 2),
        'kwh_rate'       => $rate,
        'anomaly_stats'  => $aStats,
        'top_room'       => $topRoom ?: null,
    ]);
}

function cleanupAnomalies(): void {
    $db = Database::connect();
    $db->prepare("DELETE FROM anomalies WHERE status='resolved' AND resolved_at<DATE_SUB(NOW(),INTERVAL 30 DAY)")->execute();
    out('ok', 'Cleaned up old anomalies');
}

// ── Settings ──────────────────────────────────────────────────

function getSettings(): void {
    $rows = Database::connect()->query('SELECT setting_key,setting_value FROM system_settings')->fetchAll();
    $out = [];
    foreach ($rows as $r) $out[$r['setting_key']] = $r['setting_value'];
    out('ok', $out);
}

function saveSettings(): void {
    $b = body(); $db = Database::connect();
    $s = $db->prepare('INSERT INTO system_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?');
    foreach ($b as $k => $v) $s->execute([$k, $v, $v]);
    Auth::log(Auth::user()['user_id'], 'settings', 'System settings updated');
    out('ok', 'Settings saved');
}

// ── Profile ───────────────────────────────────────────────────

function updateProfile(): void {
    $b = body(); $uid = Auth::user()['user_id'];
    Database::connect()->prepare('UPDATE users SET full_name=?,email=?,department=? WHERE user_id=?')
        ->execute([$b['full_name'], $b['email'], $b['department']??null, $uid]);
    $_SESSION['user']['full_name'] = $b['full_name'];
    $_SESSION['user']['email']     = $b['email'];
    Auth::log($uid, 'auth', 'Profile updated');
    out('ok', 'Profile updated');
}

function changePassword(): void {
    $b = body(); $uid = Auth::user()['user_id']; $db = Database::connect();
    $hash = $db->prepare('SELECT password FROM users WHERE user_id=?');
    $hash->execute([$uid]);
    if (!password_verify($b['current_password'], $hash->fetchColumn())) out('error', 'Current password is incorrect.');
    if ($b['new_password'] !== $b['confirm_password']) out('error', 'Passwords do not match.');
    if (strlen($b['new_password']) < 6) out('error', 'Password must be at least 6 characters.');
    $db->prepare('UPDATE users SET password=? WHERE user_id=?')
       ->execute([password_hash($b['new_password'], PASSWORD_BCRYPT), $uid]);
    Auth::log($uid, 'auth', 'Password changed');
    out('ok', 'Password updated');
}
