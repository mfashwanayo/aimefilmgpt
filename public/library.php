<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once PROJECT_ROOT . '/app/Services/EngagementService.php';
require_once PROJECT_ROOT . '/app/Helpers/catalog.php';
require_auth();

$uid = (int)current_user()['id'];
$svc = new EngagementService(db());
$counts = $svc->counts($uid);
$tabs = ['watchlist' => 'Watchlist', 'favorite' => 'Favourites', 'liked' => 'Liked', 'following' => 'Following'];
$tab = (string)($_GET['list'] ?? 'watchlist');
if (!isset($tabs[$tab])) $tab = 'watchlist';
$items = $tab === 'following' ? [] : $svc->savedItems($uid, $tab);
$artists = $tab === 'following' ? $svc->followedArtists($uid) : [];
$empty = [
    'watchlist' => 'Save titles to watch later with the “+ Watchlist” button.',
    'favorite'  => 'Mark the titles you love with “★ Favourite”.',
    'liked'     => 'Titles you like will show up here.',
    'following' => 'Follow artists to keep up with their releases.',
];

$pageTitle = 'My library — AIMEFILMS';
require __DIR__ . '/partials/header.php';
?>
<main class="section library-page">
  <div class="section-heading"><div><span class="eyebrow">YOUR SPACE</span><h1 class="page-h1">My <span>library.</span></h1></div>
    <p><?= e(current_user()['name']) ?> · <a class="text-link" href="<?= e(APP_BASE_URL) ?>/account/password.php">Change password</a></p></div>

  <nav class="status-tabs" aria-label="Library">
    <?php foreach ($tabs as $k => $label): $n = $counts[$k]; ?>
      <a class="<?= $tab === $k ? 'active' : '' ?>" href="?list=<?= e($k) ?>"><?= e($label) ?> <small><?= (int)$n ?></small></a>
    <?php endforeach; ?>
  </nav>

  <?php if ($tab === 'following'): ?>
    <?php if ($artists): ?>
      <ul class="artist-list">
        <?php foreach ($artists as $a): $av = upload_url($a['avatar_path']); ?>
          <li><a href="<?= e(artist_url((int)$a['user_id'])) ?>">
            <span class="avatar avatar-md" <?= $av ? 'style="background-image:url(\'' . e($av) . '\')"' : '' ?>><?= $av ? '' : e(mb_strtoupper(mb_substr($a['stage_name'], 0, 1))) ?></span>
            <span><strong><?= e($a['stage_name']) ?></strong><small><?= e(trim(implode(', ', array_filter([$a['city'], $a['country']])))) ?></small></span></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  <?php elseif ($items): ?>
    <div class="catalog-grid"><?php foreach ($items as $i) echo render_card($i); ?></div>
  <?php endif; ?>

  <?php if (!$items && !$artists): ?>
    <div class="catalog-empty"><strong>Nothing here yet.</strong><p><?= e($empty[$tab]) ?></p>
      <a class="btn btn-small btn-primary" href="<?= e(APP_BASE_URL) ?>/index.php">Start exploring</a></div>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
