<?php
declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require_once PROJECT_ROOT . '/config/app.php';
require_once PROJECT_ROOT . '/config/database.php';
require_once PROJECT_ROOT . '/app/Helpers/auth.php';
require_once PROJECT_ROOT . '/app/Helpers/csrf.php';
require_once PROJECT_ROOT . '/app/Helpers/flash.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

enforce_session_lifetime();
