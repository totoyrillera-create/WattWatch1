<?php
// src/Auth.php — Session & privilege helpers

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

class Auth {

    // ── Session bootstrap ──────────────────────────────────────
    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => '/',
                'secure'   => false,   // set true on HTTPS
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    // ── Login ──────────────────────────────────────────────────
    public static function attempt(string $email, string $password): array|false {
        $db   = Database::connect();
        $stmt = $db->prepare(
            'SELECT u.user_id, u.full_name, u.email, u.password,
                    u.avatar, u.department, u.status,
                    r.role_key, r.role_name
             FROM   users  u
             JOIN   roles  r ON r.role_id = u.role_id
             WHERE  u.email = ?
             LIMIT  1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) return false;
        if ($user['status'] !== 'active') return false;
        if (!password_verify($password, $user['password'])) return false;

        // Update last_login
        $db->prepare('UPDATE users SET last_login = NOW() WHERE user_id = ?')
           ->execute([$user['user_id']]);

        // Log it
        self::log($user['user_id'], 'auth', $user['full_name'] . ' logged in');

        unset($user['password']);
        $_SESSION['user'] = $user;
        return $user;
    }

    // ── Logout ─────────────────────────────────────────────────
    public static function logout(): void {
        self::start();
        if (isset($_SESSION['user'])) {
            self::log($_SESSION['user']['user_id'], 'auth', $_SESSION['user']['full_name'] . ' logged out');
        }
        session_destroy();
    }

    // ── Current user ───────────────────────────────────────────
    public static function user(): array|null {
        self::start();
        return $_SESSION['user'] ?? null;
    }

    // ── Check login ────────────────────────────────────────────
    public static function check(): bool {
        return self::user() !== null;
    }

    // ── Require login; redirect to login page if not ───────────
    public static function requireLogin(string $redirect = '/WattWatch/public/index.php'): void {
        self::start();
        if (!self::check()) {
            header('Location: ' . $redirect);
            exit;
        }
    }

    // ── Permission check ───────────────────────────────────────
    public static function can(string $page): bool {
        $user = self::user();
        if (!$user) return false;
        $perms = ROLE_PERMISSIONS[$user['role_key']] ?? [];
        return in_array($page, $perms, true);
    }

    // ── Require specific permission ────────────────────────────
    public static function requirePermission(string $page): void {
        if (!self::can($page)) {
            http_response_code(403);
            die('Access denied.');
        }
    }

    // ── Activity log helper ────────────────────────────────────
    public static function log(int|null $userId, string $type, string $action): void {
        try {
            $db = Database::connect();
            $db->prepare(
                'INSERT INTO activity_logs (user_id, log_type, action, ip_address)
                 VALUES (?, ?, ?, ?)'
            )->execute([$userId, $type, $action, $_SERVER['REMOTE_ADDR'] ?? null]);
        } catch (Throwable) { /* non-fatal */ }
    }

    public static function isAdmin(): bool {
        return (self::user()['role_key'] ?? '') === ROLE_ADMIN;
    }

    public static function isManagerOrAbove(): bool {
        return in_array(self::user()['role_key'] ?? '', [ROLE_ADMIN, ROLE_MANAGER], true);
    }
}
