<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
if (!isset($pageTitle)) $pageTitle = 'AIMEFILMS';
$base = $base ?? (APP_BASE_URL . '/');
$activePlatform = $activePlatform ?? '';
$loggedInUser = current_user();
$unreadCount = 0;
if ($loggedInUser) {
    require_once dirname(__DIR__, 2) . '/app/Services/NotificationService.php';
    $unreadCount = (new NotificationService(db()))->unreadCount((int)$loggedInUser['id']);
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="description" content="Discover Rwandan films, original productions and music on AIMEFILMS."><title><?= e($pageTitle) ?></title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="<?= e($base) ?>assets/css/main.css"><link rel="stylesheet" href="<?= e($base) ?>assets/css/artist-dashboard.css"><link rel="stylesheet" href="<?= e($base) ?>assets/css/admin.css"><link rel="stylesheet" href="<?= e($base) ?>assets/css/catalog.css"></head><body>
<header class="site-header"><a class="brand" href="<?= e($base) ?>index.php"><span class="brand-mark">A</span><span>AIME<span class="brand-accent">FILMS</span><small>STORIES · SOUND · CULTURE</small></span></a><button class="nav-toggle" aria-label="Open menu" aria-expanded="false">☰</button><nav class="main-nav"><a class="<?= $activePlatform==='home'?'active':'' ?>" href="<?= e($base) ?>index.php">Home</a><a class="<?= $activePlatform==='filmsnyarwanda'?'active':'' ?>" href="<?= e($base) ?>filmsnyarwanda/">FilmsNyarwanda</a><a class="<?= $activePlatform==='aimefilms'?'active':'' ?>" href="<?= e($base) ?>aimefilms/">AIMEFILMS</a><a class="<?= $activePlatform==='musicspart'?'active':'' ?>" href="<?= e($base) ?>musicspart/">MusicSpart</a><?php if ($loggedInUser): ?><a href="<?= e(APP_BASE_URL) ?>/library.php">My library</a><?php endif; ?></nav><div class="header-actions">
<?php if ($loggedInUser): ?><?php if ($loggedInUser): ?><a class="bell" href="<?= e(APP_BASE_URL) ?>/notifications.php" aria-label="Notifications<?= $unreadCount ? ' (' . $unreadCount . ' unread)' : '' ?>">&#128276;<?php if ($unreadCount): ?><span class="bell-count"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span><?php endif; ?></a><?php endif; ?><?php if (($loggedInUser['role'] ?? '') === 'admin'): ?><a class="login-link" href="<?= e(APP_ADMIN_URL) ?>/index.php">Admin</a><?php elseif (($loggedInUser['role'] ?? '') === 'artist'): ?><a class="login-link" href="<?= e(APP_BASE_URL) ?>/artist/index.php">Studio</a><?php endif; ?><span class="login-link">Hi, <?= e($loggedInUser['name']) ?></span><form method="post" action="<?= e($base) ?>auth/logout.php" class="logout-inline"><?= csrf_field() ?><button class="btn btn-small btn-glass" type="submit">Log out</button></form>
<?php else: ?><a class="login-link" href="<?= e($base) ?>auth/login.php">Log in</a><a class="btn btn-small btn-primary" href="<?= e($base) ?>auth/register.php">Join now</a><?php endif; ?>
</div></header>
<?php if ($flashMessage = get_flash()): ?><div class="flash-bar flash-<?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div><?php endif; ?>
