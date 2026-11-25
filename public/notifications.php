<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once PROJECT_ROOT . '/app/Services/NotificationService.php';
require_once PROJECT_ROOT . '/app/Helpers/admin.php';
require_auth();

$uid = (int)current_user()['id'];
$svc = new NotificationService(db());

// Clicking a notification marks it read, then goes to its page.
if (isset($_GET['open'])) {
    $link = $svc->open((int)$_GET['open'], $uid);
    header('Location: ' . ($link ?? APP_BASE_URL . '/notifications.php'));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $svc->markAllRead($uid);
    flash('success', 'All notifications marked as read.');
    header('Location: notifications.php');
    exit;
}

$result = $svc->list($uid, (int)($_GET['page'] ?? 1));
$pageTitle = 'Notifications — AIMEFILMS';
require __DIR__ . '/partials/header.php';
?>
<main class="container admin-wrap" style="max-width:820px">
  <h1>Notifications</h1>
  <?php if ($result['total'] > 0): ?>
    <form method="post" class="inline-form"><?= csrf_field() ?><button class="btn btn-small btn-glass" type="submit">Mark all as read</button></form>
  <?php endif; ?>
  <ul class="notif-list">
    <?php foreach ($result['items'] as $n): ?>
      <li class="<?= $n['is_read'] ? '' : 'unread' ?>">
        <a href="?open=<?= (int)$n['id'] ?>">
          <strong><?= e($n['title']) ?></strong>
          <?php if ($n['body']): ?><span><?= e($n['body']) ?></span><?php endif; ?>
          <small><?= e($n['created_at']) ?></small>
        </a>
      </li>
    <?php endforeach; ?>
    <?php if (!$result['items']): ?><li class="empty">You have no notifications yet.</li><?php endif; ?>
  </ul>
  <?= admin_pagination([], $result['page'], $result['pages']) ?>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
