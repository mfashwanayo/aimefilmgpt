<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * Use at the top of any protected PHP page:
 * require_once PROJECT_ROOT . '/app/Middleware/AuthMiddleware.php';
 * AuthMiddleware::handle();
 * or AuthMiddleware::handle(['admin']);
 */
final class AuthMiddleware
{
    public static function handle(array $allowedRoles = []): void
    {
        require_auth();

        if ($allowedRoles !== [] && !has_role(...$allowedRoles)) {
            http_response_code(403);
            exit('403 Forbidden — your account role cannot access this page.');
        }
    }
}
