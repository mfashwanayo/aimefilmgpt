<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once PROJECT_ROOT . '/app/Services/ModerationService.php';
require_once PROJECT_ROOT . '/app/Helpers/admin.php';
require_role('admin');

$mod = new ModerationService(db());
$filters = [
    'status'   => (string)($_GET['status'] ?? 'pending'),
    'platform' => (string)($_GET['platform'] ?? ''),
    'type'     => (string)($_GET['type'] ?? ''),
    'q'        => trim((string)($_GET['q'] ?? '')),
];
$result = $mod->listContent($filters, (int)($_GET['page'] ?? 1));
$counts = $mod->statusCounts();
$query = array_filter($filters, fn($v) => $v !== '');

$pageTitle = 'Content moderation — AIMEFILMS';
require PROJECT_ROOT . '/public/partials/header.php';
?>
<main class="container admin-wrap">
  <p class="crumbs"><a href="../index.php">Dashboard</a> / Content moderation</p>
  <h1>Content moderation</h1>

  <nav class="status-tabs" aria-label="Status">
    <?php foreach (['pending', 'published', 'rejected', 'archived', 'all'] as $s):
        $n = $s === 'all' ? array_sum($counts) - $counts['draft'] : $counts[$s]; ?>
      <a class="<?= $filters['status'] === $s ? 'active' : '' ?>" href="?<?= e(http_build_query(array_merge($query, ['status' => $s, 'page' => 1]))) ?>"><?= e(ucfirst($s)) ?> <small><?= (int)$n ?></small></a>
    <?php endforeach; ?>
  </nav>

  <form method="get" class="filter-bar">
    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
    <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search title or artist…">
    <select name="platform"><option value="">All platforms</option>
      <?php foreach (ModerationService::PLATFORMS as $p): ?><option value="<?= e($p) ?>" <?= $filters['platform'] === $p ? 'selected' : '' ?>><?= e(platform_label($p)) ?></option><?php endforeach; ?></select>
    <select name="type"><option value="">All types</option>
      <?php foreach (ModerationService::TYPES as $t): ?><option value="<?= e($t) ?>" <?= $filters['type'] === $t ? 'selected' : '' ?>><?= e(type_label($t)) ?></option><?php endforeach; ?></select>
    <button class="btn btn-small btn-primary" type="submit">Filter</button>
  </form>

  <p class="muted"><?= number_format($result['total']) ?> item(s). Oldest submissions are shown first.</p>
  <div class="table-wrap"><table class="admin-table">
    <thead><tr><th>Title</th><th>Artist</th><th>Platform</th><th>Type</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($result['items'] as $i): ?>
      <tr>
        <td><strong><?= e($i['title']) ?></strong></td>
        <td><?= e($i['stage_name'] ?: $i['user_name']) ?></td>
        <td><?= e(platform_label($i['platform'])) ?></td>
        <td><?= e(type_label($i['content_type'])) ?></td>
        <td><?= status_badge($i['visibility']) ?></td>
        <td><?= e($i['created_at']) ?></td>
        <td><a class="btn btn-small btn-glass" href="review.php?id=<?= (int)$i['id'] ?>">Review</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$result['items']): ?><tr><td colspan="7">Nothing here. 🎬</td></tr><?php endif; ?>
    </tbody></table></div>
  <?= admin_pagination($query, $result['page'], $result['pages']) ?>
</main>
<?php require PROJECT_ROOT . '/public/partials/footer.php'; ?>
