<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}
verify_csrf_or_abort();
logout_user();
session_start();
set_flash('success', 'You have been logged out safely.');
header('Location: login.php');
exit;
