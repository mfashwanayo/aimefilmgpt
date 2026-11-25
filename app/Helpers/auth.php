<?php
declare(strict_types=1);

function current_user(): ?array
{
    return $_SESSION['auth_user'] ?? null;
}

function is_authenticated(): bool
{
    return current_user() !== null;
}

function has_role(string ...$roles): bool
{
    $user = current_user();
    return $user !== null && in_array($user['role'], $roles, true);
}

function require_auth(): void
{
    if (!is_authenticated()) {
        set_flash('error', 'Please log in to continue.');
        header('Location: ' . APP_BASE_URL . '/auth/login.php');
        exit;
    }
}

function require_role(string ...$roles): void
{
    require_auth();
    if (!has_role(...$roles)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['auth_user'] = [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];
    $_SESSION['_created_at'] = time();
    $_SESSION['_last_activity'] = time();
    rotate_csrf_token();
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool)$params['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    session_destroy();
}

function enforce_session_lifetime(): void
{
    if (!isset($_SESSION['auth_user'])) {
        return;
    }

    $now = time();
    $created = (int)($_SESSION['_created_at'] ?? $now);
    $last = (int)($_SESSION['_last_activity'] ?? $now);

    if (($now - $last) > SESSION_IDLE_TIMEOUT ||
        ($now - $created) > SESSION_ABSOLUTE_TIMEOUT) {
        logout_user();
        session_start();
        set_flash('error', 'Your session expired. Please log in again.');
        return;
    }

    $_SESSION['_last_activity'] = $now;
}
