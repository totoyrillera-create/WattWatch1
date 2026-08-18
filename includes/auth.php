<?php
/**
 * includes/auth.php
 * Session bootstrap + role-based access control (RBAC).
 * Include this near the top of every protected page, before any output:
 *   require_once 'includes/auth.php';   (from root pages)
 *   require_once '../includes/auth.php'; (from ajax/ or api/)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Idle session timeout */
if (!empty($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

/** Redirect to login if not authenticated. Call at the top of every protected page. */
function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/** Currently logged-in user's basic info (cached in session at login time). */
function current_user() {
    if (empty($_SESSION['user_id'])) return null;
    return [
        'user_id'   => $_SESSION['user_id'],
        'username'  => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'role_id'   => $_SESSION['role_id'],
        'role_name' => $_SESSION['role_name'],
    ];
}

/**
 * Loads the permission_key set for the logged-in user's role, once per
 * session, from role_permissions — so privileges can be regranted by
 * editing DB rows, no code changes needed.
 */
function user_permissions() {
    global $pdo;
    if (empty($_SESSION['user_id'])) return [];
    if (!isset($_SESSION['permissions'])) {
        $stmt = $pdo->prepare(
            "SELECT p.permission_key FROM role_permissions rp
             JOIN permissions p ON p.permission_id = rp.permission_id
             WHERE rp.role_id = ?"
        );
        $stmt->execute([$_SESSION['role_id']]);
        $_SESSION['permissions'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    return $_SESSION['permissions'];
}

function can($permission_key) {
    return in_array($permission_key, user_permissions(), true);
}

/** Call at the top of a page that needs a specific privilege. */
function require_permission($permission_key) {
    require_login();
    if (!can($permission_key)) {
        http_response_code(403);
        require __DIR__ . '/../403.php';
        exit;
    }
}
