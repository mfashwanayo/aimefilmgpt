<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once PROJECT_ROOT . '/app/Services/AuthService.php';
require_once PROJECT_ROOT . '/app/Helpers/redirect.php';

$next = safe_next((string)($_GET['next'] ?? $_POST['next'] ?? ''));

if (is_authenticated()) {
    header('Location: ' . ($next ?? '../index.php'));
    exit;
}

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $now = time();
    $attempts = $_SESSION['_login_attempts'] ?? ['count' => 0, 'locked_until' => 0];

    if (($attempts['locked_until'] ?? 0) > $now) {
        $remaining = (int)ceil(($attempts['locked_until'] - $now) / 60);
        $error = "Too many login attempts. Please wait about {$remaining} minute(s) and try again.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Enter a valid email address and password.';
    } else {
        try {
            $service = new AuthService(db());
            $user = $service->findActiveUserByEmail($email);
            if ($user && password_verify($password, $user['password_hash'])) {
                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                    $rehash = db()->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id');
                    $rehash->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]);
                }
                $lastLogin = db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
                $lastLogin->execute(['id' => $user['id']]);
                unset($_SESSION['_login_attempts']);
                login_user($user);
                set_flash('success', 'Welcome back to AIMEFILMS.');
                header('Location: ' . ($next ?? '../index.php'));
                exit;
            }

            $attempts['count'] = (int)($attempts['count'] ?? 0) + 1;
            if ($attempts['count'] >= LOGIN_MAX_ATTEMPTS) {
                $attempts['locked_until'] = $now + (LOGIN_LOCK_MINUTES * 60);
                $attempts['count'] = 0;
            }
            $_SESSION['_login_attempts'] = $attempts;
            $error = 'The email or password is incorrect, or the account is unavailable.';
        } catch (Throwable $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = 'We could not log you in right now. Please try again.';
        }
    }
}

$authTitle = 'Log in';
require __DIR__ . '/auth-header.php';
?>
<div class="auth-heading"><span class="eyebrow">WELCOME BACK</span><h2>Log in to AIMEFILMS</h2><p>Pick up where your next favourite story left off.</p></div>
<?php if ($error): ?><div class="server-errors" role="alert"><p><?= e($error) ?></p></div><?php endif; ?>
<?php if ($flash = get_flash()): ?><div class="server-notice <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
<form class="auth-form" method="post" action="login.php" data-auth-form="login" novalidate>
<?= csrf_field() ?><?php if ($next): ?><input type="hidden" name="next" value="<?= e($next) ?>"><?php endif; ?>
<div class="form-row"><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="email" placeholder="you@example.com" value="<?= e($email) ?>" required><small class="field-error"></small></div>
<div class="form-row"><div class="label-line"><label for="password">Password</label><a href="forgot-password.php">Forgot password?</a></div><div class="password-wrap"><input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required><button class="password-toggle" type="button" aria-label="Show password">Show</button></div><small class="field-error"></small></div>
<label class="check-row"><input type="checkbox" name="remember" value="1"><span>Keep me logged in on this device (not enabled yet)</span></label>
<small class="form-message" aria-live="polite"></small><button class="btn btn-primary btn-submit" type="submit">Log in <span>→</span></button>
</form><p class="auth-switch">New to AIMEFILMS? <a href="register.php<?= $next ? '?next=' . rawurlencode($next) : '' ?>">Create an account</a></p>
<?php require __DIR__ . '/auth-footer.php'; ?>