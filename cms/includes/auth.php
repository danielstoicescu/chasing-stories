<?php
/**
 * Admin sessions. Roles: admin (everything, incl. users and mail settings), editor (content and enquiries).
 */

function auth_attempt(string $username, string $password): bool
{
    $st = db()->prepare('SELECT * FROM ' . t('admins') . ' WHERE username = ? LIMIT 1');
    $st->execute([$username]);
    $a = $st->fetch();
    if (!$a || !password_verify($password, $a['password_hash'])) {
        return false;
    }
    session_boot();
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $a['id'];
    $_SESSION['admin_name'] = $a['display_name'] ?: $a['username'];
    $_SESSION['admin_role'] = $a['role'];
    $_SESSION['seen'] = time();
    if (password_needs_rehash($a['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE ' . t('admins') . ' SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $a['id']]);
    }
    db()->prepare('UPDATE ' . t('admins') . ' SET last_login = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $a['id']]);
    return true;
}

function auth_check(): void
{
    session_boot();
    if (isset($_SESSION['seen']) && time() - $_SESSION['seen'] > 4 * 3600) {   // 4h idle
        $_SESSION = [];
    }
    if (empty($_SESSION['admin_id'])) {
        redirect('login.php');
    }
    $_SESSION['seen'] = time();
}

function auth_name(): string { return $_SESSION['admin_name'] ?? 'Admin'; }
function auth_id(): int { return (int) ($_SESSION['admin_id'] ?? 0); }
function is_admin(): bool { return ($_SESSION['admin_role'] ?? '') === 'admin'; }

function require_admin(): void
{
    if (!is_admin()) {
        http_response_code(403);
        exit('Doar administratorii au acces aici.');
    }
}

function auth_logout(): void
{
    session_boot();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
    redirect('login.php');
}
