<?php
/**
 * includes/functions.php
 * Small shared helpers used across pages, ajax, and api endpoints.
 */

function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Records an entry in activity_logs. Call after any create/update/delete/auth action. */
function log_activity($user_id, $action, $details = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$user_id, $action, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Exception $ex) {
        error_log('log_activity failed: ' . $ex->getMessage());
    }
}

function format_watts($w) {
    return number_format($w, 0) . ' W';
}

function format_kwh($k) {
    return number_format($k, 2) . ' kWh';
}

function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}

/** CSRF token helpers — call csrf_field() inside every state-changing <form>. */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(400);
        die('Invalid or expired form submission. Please go back and try again.');
    }
}
