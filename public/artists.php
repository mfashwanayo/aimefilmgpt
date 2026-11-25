<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once PROJECT_ROOT . '/app/Services/CatalogService.php';
require_once PROJECT_ROOT . '/app/Helpers/catalog.php';
require_once PROJECT_ROOT . '/app/Helpers/engage.php';
require_once PROJECT_ROOT . '/app/Services/EngagementService.php';

$catalog = new CatalogService(db());
$artist = $catalog->artist((int)($_GET['id'] ?? 0));
if (!$artist) {
    http_response_code(404);
    $pageTitle = 'Artist not found — AIMEFILMS';
    require __DIR__ . '/partials/header.php';
    echo '<main class="section"><div class="catalog-empty"><strong>Artist not found.</strong><a class="btn btn-small btn-primary" href="' . e(APP_BASE_URL) . '/index.php">Back to home</a></div></main>';
    require __DIR__ . '/partials/footer.php';
    exit;
}
$items = $catalog->artistContent((int)$artist['user_id']);
$viewer = current_user();
$isOwn = $viewer && (int)$viewer['id'] === (int)$artist['user_id'];
$following = ($viewer && !$isOwn) ? (new EngagementService(db()))->isFollowing((int)$viewer['id'], (int)$artist['user_id']) : false;
$banner = upload_url($artist['banner_path']);
$avatar = upload_url($artist['avatar_path']);
// Only http(s) links are ever rendered as links (blocks javascript: URLs).
$site = (string)($artist['website_url'] ?? '');
$siteOk = (bool)preg_match('#^https?://#i', $site) && filter_var($site, FILTER_VALIDATE_URL);
$place = trim(implode(', ', array_filter([$artist['city'] ?? '', $artist['country'] ?? ''])));

$pageTitle = $artist['stage_name'] . ' — AIMEFILMS';
require __DIR__ . '/partials/header.php';
?>
<main class="section artist-page">
  <div class="artist-banner" <?= $banner ? 'style="background-image:url(\'' . e($banner) . '\')"' : '' ?>></div>
  <div class="artist-head">
    <span class="avatar avatar-lg" <?= $avatar ? 'style="background-image:url(\'' . e($avatar) . '\')"' : '' ?>><?= $avatar ? '' : e(mb_strtoupper(mb_substr($artist['stage_name'], 0, 1))) ?></span>
    <div>
      <span class="eyebrow">VERIFIED ARTIST</span>
      <h1><?= e($artist['stage_name']) ?></h1>
      <p class="muted"><?= number_format($artist['followers']) ?> follower<?= $artist['followers'] === 1 ? '' : 's' ?><?= $place ? ' · ' . e($place) : '' ?><?php if ($siteOk): ?> · <a href="<?= e($site) ?>" target="_blank" rel="noopener nofollow">Website ↗</a><?php endif; ?></p>
    </div>
    <div class="follow-box">
      <?php if ($isOwn): ?><a class="btn btn-small btn-glass" href="<?= e(APP_BASE_URL) ?>/artist/index.php">Open your studio</a>
      <?php elseif ($viewer): ?><?= engage_form('follow', ['artist_id' => (int)$artist['user_id']], $following ? '✓ Following' : '+ Follow', $following) ?>
      <?php else: ?><?= login_button('+ Follow') ?><?php endif; ?>
    </div>
  </div>
  <?php if ($artist['bio']): ?><p class="description"><?= nl2br(e($artist['bio'])) ?></p><?php endif; ?>

  <h2 class="row-title">Releases <small><?= count($items) ?></small></h2>
  <?php if ($items): ?><div class="catalog-grid"><?php foreach ($items as $i) echo render_card($i); ?></div>
  <?php else: ?><div class="catalog-empty"><strong>No published releases yet.</strong></div><?php endif; ?>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
