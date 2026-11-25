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

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $name = trim((string)($_POST['full_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (!isset($_POST['terms'])) {
        $errors[] = 'You must accept the Terms and Privacy Policy.';
    }
    if (!hash_equals($password, $confirm)) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        try {
            $service = new AuthService(db());
            $id = $service->register($name, $email, $password);
            $user = $service->publicUserById($id);
            if (!$user) {
                throw new RuntimeException('Account created but could not be loaded. Please log in.');
            }
            login_user($user);
            set_flash('success', 'Your AIMEFILMS account has been created.');
            header('Location: ' . ($next ?? '../index.php'));
            exit;
        } catch (InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors[] = 'An account with this email already exists. Please log in.';
            } else {
                error_log('Registration database error: ' . $e->getMessage());
                $errors[] = 'We could not create your account right now. Please try again.';
            }
        } catch (Throwable $e) {
            error_log('Registration error: ' . $e->getMessage());
            $errors[] = 'We could not create your account right now. Please try again.';
        }
    }
}

$authTitle = 'Create your account';
require __DIR__ . '/auth-header.php';
?>
<div class="auth-heading"><span class="eyebrow">JOIN THE COMMUNITY</span><h2>Create your account</h2><p>One account gives you access to all three destinations.</p></div>
<?php if ($errors): ?><div class="server-errors" role="alert"><?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div><?php endif; ?>
<form class="auth-form" method="post" action="register.php" data-auth-form="register" novalidate>
<?= csrf_field() ?><?php if ($next): ?><input type="hidden" name="next" value="<?= e($next) ?>"><?php endif; ?>
<div class="form-row"><label for="full_name">Full name</label><input id="full_name" name="full_name" type="text" autocomplete="name" placeholder="Your full name" value="<?= e($name) ?>" required minlength="2" maxlength="100"><small class="field-error"></small></div>
<div class="form-row"><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="email" placeholder="you@example.com" value="<?= e($email) ?>" required maxlength="190"><small class="field-error"></small></div>
<div class="form-row"><label for="password">Password</label><div class="password-wrap"><input id="password" name="password" type="password" autocomplete="new-password" placeholder="At least 8 characters" required minlength="8"><button class="password-toggle" type="button" aria-label="Show password">Show</button></div><small class="field-hint">Use at least 8 characters. Never reuse a password from another site.</small><small class="field-error"></small></div>
<div class="form-row"><label for="confirm_password">Confirm password</label><div class="password-wrap"><input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" placeholder="Enter your password again" required><button class="password-toggle" type="button" aria-label="Show password">Show</button></div><small class="field-error"></small></div>
<label class="check-row"><input type="checkbox" name="terms" required><span>I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>.</span></label>
<small class="form-message" aria-live="polite"></small><button class="btn btn-primary btn-submit" type="submit">Create account <span>→</span></button>
</form><p class="auth-switch">Already have an account? <a href="login.php<?= $next ? '?next=' . rawurlencode($next) : '' ?>">Log in</a></p>
<?php require __DIR__ . '/auth-footer.php'; ?>