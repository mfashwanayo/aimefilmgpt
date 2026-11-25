<?php
declare(strict_types=1);

define('APP_NAME', 'AIMEFILMS');
define('APP_BASE_URL', '/aimefilms/public'); // Change if your project URL differs.
define('SESSION_IDLE_TIMEOUT', 1800);        // 30 minutes.
define('SESSION_ABSOLUTE_TIMEOUT', 43200);   // 12 hours.
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCK_MINUTES', 15);
define('APP_ADMIN_URL', dirname(APP_BASE_URL) . '/admin'); // /aimefilms/admin
