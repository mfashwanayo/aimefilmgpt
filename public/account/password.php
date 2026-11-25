<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once PROJECT_ROOT . '/app/Services/AuthService.php';
require_auth();

$user = current_user();
$error = '';
const PW_MAX_ATTEMPTS = 5;
const PW_LOCK_SECONDS = 900;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $now = time();
    $att = $_SESSION['_pw_attempts'] ?? ['count' => 0, 'locked_until' => 0];
    $current = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (($att['locked_until'] ?? 0) > $now) {
        $error = 'Too many wrong attempts. Please wait about ' . (int)ceil(($att['locked_until'] - $now) / 60) . ' minute(s).';
    } elseif ($new !== $confirm) {
        $error = 'The new password and its confirmation do not match.';
    } else {
        try {
            (new AuthService(db()))->changePassword((int)$user['id'], $current, $new);
            unset($_SESSION['_pw_attempts']);
            try {   // audit trail (best effort)
                db()->prepare('INSERT INTO auth_logs (user_id, event, ip_address, user_agent) VALUES (?,?,?,?)')
                    ->execute([(int)$user['id'], 'password_changed', substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
            } catch (Throwable $e) { error_log('auth_logs insert failed: ' . $e->getMessage()); }
            session_regenerate_id(true);
            rotate_csrf_token();
            flash('success', 'Your password was changed.');
            header('Location: ' . APP_BASE_URL . '/library.php');
            exit;
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
            if (str_contains($error, 'current password')) {   // count only wrong-current-password guesses
                $att['count'] = (int)($att['count'] ?? 0) + 1;
                if ($att['count'] >= PW_MAX_ATTEMPTS) { $att = ['count' => 0, 'locked_until' => $now + PW_LOCK_SECONDS]; }
                $_SESSION['_pw_attempts'] = $att;
            }
        } catch (Throwable $e) {
            error_log('Password change failed: ' . $e->getMessage());
            $error = 'Could not change your password. Please try again.';
        }
    }
}

$pageTitle = 'Change password — AIMEFILMS';
require dirname(__DIR__) . '/partials/header.php';
?>
<main class="container admin-wrap" style="max-width:520px">
  <p class="crumbs"><a href="<?= e(APP_BASE_URL) ?>/library.php">My library</a> / Change password</p>
  <h1>Change password</h1>
  <?php if ($error): ?><p class="flash-bar" role="alert" style="width:100%"><?= e($error) ?></p><?php endif; ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <label for="current_password">Current password</label>
    <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
    <label for="new_password">New password <small class="muted">(at least 8 characters)</small></label>
    <input id="new_password" type="password" name="new_password" required minlength="8" maxlength="200" autocomplete="new-password">
    <label for="confirm_password">Confirm new password</label>
    <input id="confirm_password" type="password" name="confirm_password" required minlength="8" maxlength="200" autocomplete="new-password">
    <button type="submit">Update password</button>
  </form>
</main>
<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
