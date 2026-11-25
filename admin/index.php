<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once PROJECT_ROOT . '/app/Services/ModerationService.php';
require_once PROJECT_ROOT . '/app/Helpers/admin.php';
require_role('admin');

$db = db();
$mod = new ModerationService($db);
$content = $mod->statusCounts();
$comments = $mod->commentCounts();
$one = fn(string $sql) => (int)$db->query($sql)->fetchColumn();
$users = $one('SELECT COUNT(*) FROM users');
$artists = $one("SELECT COUNT(*) FROM artist_profiles WHERE verification_status = 'approved'");
$pendingApps = $one("SELECT COUNT(*) FROM artist_applications WHERE status = 'pending'");

$pageTitle = 'Admin dashboard — AIMEFILMS';
require PROJECT_ROOT . '/public/partials/header.php';
?>
<main class="container admin-wrap">
  <span class="eyebrow">ADMINISTRATION</span>
  <h1>Dashboard</h1>

  <div class="queue-cards">
    <a class="stat-card stat-alert" href="content/index.php?status=pending"><strong><?= number_format($content['pending']) ?></strong><span>Content awaiting review</span></a>
    <a class="stat-card stat-alert" href="artists/index.php"><strong><?= number_format($pendingApps) ?></strong><span>Artist applications</span></a>
    <a class="stat-card stat-alert" href="comments/index.php?status=reported"><strong><?= number_format($comments['reported']) ?></strong><span>Reported comments</span></a>
  </div>

  <h2>Platform overview</h2>
  <div class="artist-stats">
    <div class="stat-card"><strong><?= number_format($users) ?></strong><span>Registered users</span></div>
    <div class="stat-card"><strong><?= number_format($artists) ?></strong><span>Verified artists</span></div>
    <div class="stat-card"><strong><?= number_format($content['published']) ?></strong><span>Published items</span></div>
    <div class="stat-card"><strong><?= number_format($content['rejected']) ?></strong><span>Rejected items</span></div>
    <div class="stat-card"><strong><?= number_format($content['archived']) ?></strong><span>Archived items</span></div>
  </div>

  <h2>Manage</h2>
  <nav class="artist-actions">
    <a href="content/index.php">Content moderation</a> · <a href="artists/index.php">Artist applications</a> ·
    <a href="comments/index.php">Comment moderation</a> · <a href="users/index.php">Users &amp; roles</a>
  </nav>
</main>
<?php require PROJECT_ROOT . '/public/partials/footer.php'; ?>
