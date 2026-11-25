<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once PROJECT_ROOT . '/app/Services/RoleService.php';

require_role('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}
verify_csrf_or_abort();

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$role = (string)($_POST['role'] ?? '');
if (!$userId || $userId === (int)current_user()['id']) {
    http_response_code(400);
    exit('Invalid user. You cannot change your own role using this action.');
}

try {
    $service = new RoleService(db());
    $service->setUserRole((int)$userId, $role);
    set_flash('success', 'User role updated.');
} catch (Throwable $e) {
    error_log('Role update failed: ' . $e->getMessage());
    set_flash('error', 'Could not update the user role.');
}
header('Location: index.php');
exit;
