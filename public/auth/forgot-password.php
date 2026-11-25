<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

$authTitle = 'Reset your password';
require __DIR__ . '/auth-header.php';
?>
<div class="auth-heading"><span class="eyebrow">ACCOUNT RECOVERY</span><h2>Forgot your password?</h2><p>Password reset email delivery is the next backend module. This page is intentionally not connected until secure one-time reset tokens and email delivery are configured.</p></div>
<div class="server-notice">Password reset is not enabled yet. Please contact the platform administrator for assistance.</div>
<p class="auth-switch"><a href="login.php">← Back to log in</a></p>
<?php require __DIR__ . '/auth-footer.php'; ?>