<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    static $user = false;
    if ($user === false) {
        $stmt = get_db()->prepare('SELECT * FROM users WHERE id = ? AND active = 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
        if (!$user) { session_destroy(); return null; }
    }
    return $user;
}

function require_login(): array {
    $u = current_user();
    if (!$u) { header('Location: login.php'); exit; }
    if ($u['must_change_password'] && basename($_SERVER['SCRIPT_NAME']) !== 'profile.php') {
        header('Location: profile.php?force=1'); exit;
    }
    return $u;
}

function require_admin(): array {
    $u = require_login();
    if ($u['role'] !== 'admin' && $u['role'] !== 'kadry') { http_response_code(403); die('Brak uprawnień'); }
    return $u;
}

function is_admin(): bool {
    $u = current_user();
    return $u && ($u['role'] === 'admin' || $u['role'] === 'kadry');
}

function is_super_admin(): bool {
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
}

function verify_csrf(): void {
    if (empty($_POST['_token']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['_token'])) {
        http_response_code(403); die('Nieprawidłowy token CSRF');
    }
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
