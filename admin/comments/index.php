<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once PROJECT_ROOT . '/app/Services/ModerationService.php';
require_once PROJECT_ROOT . '/app/Helpers/admin.php';
require_role('admin');

$mod = new ModerationService(db());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $status = (string)($_POST['return_status'] ?? 'reported');
    try {
        $mod->setCommentStatus((int)($_POST['comment_id'] ?? 0), (string)($_POST['to'] ?? ''), (int)current_user()['id']);
        flash('success', 'Comment updated.');
    } catch (InvalidArgumentException | RuntimeException $e) {
        flash('error', $e->getMessage());
    } catch (Throwable $e) {
        error_log('Comment moderation failed: ' . $e->getMessage());
        flash('error', 'Could not update the comment.');
    }
    header('Location: index.php?status=' . urlencode($status) . '&page=' . (int)($_POST['page'] ?? 1));
    exit;
}

$status = (string)($_GET['status'] ?? 'reported');
$result = $mod->listComments($status, (int)($_GET['page'] ?? 1));
$status = in_array($status, ['visible', 'reported', 'hidden'], true) ? $status : 'reported';
$counts = $mod->commentCounts();

$pageTitle = 'Comment moderation — AIMEFILMS';
require PROJECT_ROOT . '/public/partials/header.php';
?>
<main class="container admin-wrap">
  <p class="crumbs"><a href="../index.php">Dashboard</a> / Comment moderation</p>
  <h1>Comment moderation</h1>
  <nav class="status-tabs">
    <?php foreach (['reported', 'visible', 'hidden'] as $s): ?>
      <a class="<?= $status === $s ? 'active' : '' ?>" href="?status=<?= e($s) ?>"><?= e(ucfirst($s)) ?> <small><?= (int)$counts[$s] ?></small></a>
    <?php endforeach; ?>
  </nav>
  <div class="table-wrap"><table class="admin-table">
    <thead><tr><th>Comment</th><th>User</th><th>On content</th><th>Posted</th><th></th></tr></thead><tbody>
    <?php foreach ($result['items'] as $c): ?>
      <tr>
        <td class="comment-cell"><?= nl2br(e($c['comment_text'])) ?>
          <?php if (!empty($c['reports'])): ?><br><small class="text-bad"><?= count($c['reports']) ?> report(s): <?= e(implode(', ', array_unique($c['reports']))) ?></small><?php endif; ?></td>
        <td><?= e($c['user_name']) ?></td>
        <td><a href="../content/review.php?id=<?= (int)$c['content_id'] ?>"><?= e($c['title']) ?></a></td>
        <td><?= e($c['created_at']) ?></td>
        <td>
          <form method="post" class="inline-form"><?= csrf_field() ?>
            <input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>">
            <input type="hidden" name="return_status" value="<?= e($status) ?>">
            <input type="hidden" name="page" value="<?= (int)$result['page'] ?>">
            <?php if ($c['status'] !== 'hidden'): ?><button class="btn btn-small btn-danger" name="to" value="hidden">Hide</button><?php endif; ?>
            <?php if ($c['status'] !== 'visible'): ?><button class="btn btn-small btn-glass" name="to" value="visible"><?= $c['status'] === 'reported' ? 'Dismiss report' : 'Restore' ?></button><?php endif; ?>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$result['items']): ?><tr><td colspan="5">No <?= e($status) ?> comments.</td></tr><?php endif; ?>
    </tbody></table></div>
  <?= admin_pagination(['status' => $status], $result['page'], $result['pages']) ?>
</main>
<?php require PROJECT_ROOT . '/public/partials/footer.php'; ?>
