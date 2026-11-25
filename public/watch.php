<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once PROJECT_ROOT . '/app/Services/CatalogService.php';
require_once PROJECT_ROOT . '/app/Helpers/catalog.php';
require_once PROJECT_ROOT . '/app/Helpers/engage.php';
require_once PROJECT_ROOT . '/app/Services/EngagementService.php';

$catalog = new CatalogService(db());
$id = (int)($_GET['id'] ?? 0);
$user = current_user();

$item = $catalog->find($id);
$preview = false;
if (!$item && $id > 0 && $user) {
    // Owners and admins may preview content that is not public yet.
    $candidate = $catalog->find($id, true);
    if ($candidate && ($user['role'] === 'admin' || (int)$candidate['artist_user_id'] === (int)$user['id'])) {
        $item = $candidate; $preview = true;
    }
}
if (!$item) {
    http_response_code(404);
    $pageTitle = 'Not found — AIMEFILMS';
    require __DIR__ . '/partials/header.php';
    echo '<main class="section"><div class="catalog-empty"><strong>This title is not available.</strong><p>It may have been removed or is still being reviewed.</p><a class="btn btn-small btn-primary" href="' . e(APP_BASE_URL) . '/index.php">Back to home</a></div></main>';
    require __DIR__ . '/partials/footer.php';
    exit;
}

// Count one view per visitor session (never for previews).
if (!$preview) {
    $seen = $_SESSION['_viewed'] ?? [];
    if (empty($seen[$id])) {
        $catalog->recordView($id);
        $seen[$id] = 1;
        $_SESSION['_viewed'] = array_slice($seen, -200, null, true);
        $item['views_count'] = (int)$item['views_count'] + 1;
    }
}

$mediaUrl = upload_url($item['media_path']);
$audio = is_audio_path($item['media_path']);
$art = card_image($item);
$children = in_array($item['content_type'], ['series', 'album'], true) ? $catalog->children($id) : [];
$parent = !empty($item['parent_id']) ? $catalog->parent((int)$item['parent_id']) : null;
$nav = $catalog->neighbors($item);
$more = $catalog->moreFromArtist((int)$item['artist_user_id'], $id);
$platformHome = APP_BASE_URL . '/' . $item['platform'] . '/';

$eng = new EngagementService(db());
$state = ($user && !$preview) ? $eng->state((int)$user['id'], $id) : null;
$commentPage = $eng->comments($id, (int)($_GET['page'] ?? 1));

$pageTitle = $item['title'] . ' — ' . platform_label($item['platform']);
$activePlatform = $item['platform'];
require __DIR__ . '/partials/header.php';
?>
<main class="section watch-page">
  <p class="crumbs"><a href="<?= e($platformHome) ?>"><?= e(platform_label($item['platform'])) ?></a>
    <?php if ($parent): ?> / <a href="<?= e(watch_url((int)$parent['id'])) ?>"><?= e($parent['title']) ?></a><?php endif; ?> / <?= e($item['title']) ?></p>

  <?php if ($preview): ?><div class="note-banner note-warn"><strong>Preview only.</strong> This title is "<?= e($item['visibility']) ?>" and is not visible to the public.</div><?php endif; ?>

  <div class="watch-grid">
    <div class="player-col">
      <div class="player <?= $audio ? 'player-audio' : '' ?>" data-player
           <?= $nav['next'] ? 'data-next="' . e(watch_url((int)$nav['next']['id'])) . '"' : '' ?>>
        <?php if ($mediaUrl && $audio): ?>
          <?php if ($art): ?><img class="player-cover" src="<?= e($art) ?>" alt="Cover art for <?= e($item['title']) ?>"><?php endif; ?>
          <audio controls preload="metadata" src="<?= e($mediaUrl) ?>">Your browser does not support audio playback.</audio>
        <?php elseif ($mediaUrl): ?>
          <video controls playsinline preload="metadata" <?= $art ? 'poster="' . e($art) . '"' : '' ?> src="<?= e($mediaUrl) ?>">Your browser does not support video playback.</video>
        <?php else: ?>
          <div class="player-empty <?= $art ? '' : 'no-image' ?>" <?= $art ? 'style="background-image:url(\'' . e($art) . '\')"' : '' ?>>
            <?php if ($children): ?><a class="btn btn-primary" href="<?= e(watch_url((int)$children[0]['id'])) ?>">▶ Play first <?= $item['content_type'] === 'album' ? 'track' : 'episode' ?></a><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($nav['prev'] || $nav['next']): ?>
        <div class="episode-nav">
          <?php if ($nav['prev']): ?><a class="btn btn-small btn-glass" href="<?= e(watch_url((int)$nav['prev']['id'])) ?>">← <?= e($nav['prev']['title']) ?></a><?php endif; ?>
          <label class="autoplay"><input type="checkbox" id="autoplay-next" checked> Autoplay next</label>
          <?php if ($nav['next']): ?><a class="btn btn-small btn-primary" href="<?= e(watch_url((int)$nav['next']['id'])) ?>">Next: <?= e($nav['next']['title']) ?> →</a><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <aside class="info-col">
      <span class="eyebrow"><?= e(type_label($item['content_type'])) ?> · <?= e(platform_label($item['platform'])) ?></span>
      <h1><?= e($item['title']) ?></h1>
      <a class="artist-chip" href="<?= e(artist_url((int)$item['artist_user_id'])) ?>">
        <?php $av = upload_url($item['artist_avatar'] ?? null); ?>
        <span class="avatar" <?= $av ? 'style="background-image:url(\'' . e($av) . '\')"' : '' ?>><?= $av ? '' : e(mb_strtoupper(mb_substr($item['artist_name'], 0, 1))) ?></span>
        <span><?= e($item['artist_name']) ?></span>
      </a>
      <ul class="facts">
        <?php if ($item['genre']): ?><li><b>Genre</b><?= e($item['genre']) ?></li><?php endif; ?>
        <?php if ($item['language']): ?><li><b>Language</b><?= e($item['language']) ?></li><?php endif; ?>
        <?php if ($item['release_date']): ?><li><b>Released</b><?= e(date('j M Y', strtotime($item['release_date']))) ?></li><?php endif; ?>
        <?php if ($item['duration_seconds']): ?><li><b>Length</b><?= e(fmt_duration((int)$item['duration_seconds'])) ?></li><?php endif; ?>
        <li><b>Views</b><?= number_format((int)$item['views_count']) ?></li>
      </ul>
      <?php if ($item['description']): ?><p class="description"><?= nl2br(e($item['description'])) ?></p><?php endif; ?>
      <?php if (!$preview): ?>
      <div class="action-bar">
        <?php $likeLabel = '♥ Like <span class="count">' . (int)$item['likes_count'] . '</span>'; ?>
        <?php if ($state !== null): ?>
          <?= engage_form('like', ['content_id' => $id], $likeLabel, $state['liked']) ?>
          <?= engage_form('watchlist', ['content_id' => $id], $state['watchlist'] ? '✓ In watchlist' : '+ Watchlist', $state['watchlist']) ?>
          <?= engage_form('favorite', ['content_id' => $id], $state['favorite'] ? '★ Favourited' : '☆ Favourite', $state['favorite']) ?>
        <?php else: ?>
          <?= login_button($likeLabel) ?><?= login_button('+ Watchlist') ?><?= login_button('☆ Favourite') ?>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </aside>
  </div>

  <?php if ($children): ?>
    <h2 class="row-title"><?= $item['content_type'] === 'album' ? 'Tracks' : 'Episodes' ?> <small><?= count($children) ?></small></h2>
    <ol class="track-list">
      <?php foreach ($children as $n => $c): $cimg = card_image($c); ?>
        <li><a href="<?= e(watch_url((int)$c['id'])) ?>"><span class="num"><?= $n + 1 ?></span><span class="t"><?= e($c['title']) ?></span><span class="d"><?= e(fmt_duration((int)$c['duration_seconds'])) ?></span></a></li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>

  <?php if (!$preview): ?>
  <section id="comments" class="comments">
    <h2 class="row-title">Comments <small><?= (int)$commentPage['total'] ?></small></h2>
    <?php if ($user): ?>
      <form method="post" action="<?= e(APP_BASE_URL) ?>/interact.php" class="comment-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="comment_add"><input type="hidden" name="content_id" value="<?= (int)$id ?>">
        <label class="sr-only" for="comment">Add a comment</label>
        <textarea id="comment" name="comment" rows="3" maxlength="<?= EngagementService::COMMENT_MAX ?>" required placeholder="Share what you think…"></textarea>
        <button class="btn btn-small btn-primary" type="submit">Post comment</button>
      </form>
    <?php else: ?>
      <p class="muted"><a class="text-link" href="<?= e(APP_BASE_URL) ?>/auth/login.php">Log in</a> or <a class="text-link" href="<?= e(APP_BASE_URL) ?>/auth/register.php">create a free account</a> to join the conversation.</p>
    <?php endif; ?>

    <ul class="comment-list">
      <?php foreach ($commentPage['items'] as $c): ?>
        <li>
          <div class="comment-head"><strong><?= e($c['user_name']) ?></strong><small><?= e($c['created_at']) ?></small></div>
          <p><?= nl2br(e($c['comment_text'])) ?></p>
          <?php if ($user && (int)$c['user_id'] === (int)$user['id']): ?>
            <form method="post" action="<?= e(APP_BASE_URL) ?>/interact.php" class="inline-form" onsubmit="return confirm('Delete your comment?')">
              <?= csrf_field() ?><input type="hidden" name="action" value="comment_delete"><input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>">
              <button class="link-button" type="submit">Delete</button></form>
          <?php elseif ($user): ?>
            <details class="report"><summary>Report</summary>
              <form method="post" action="<?= e(APP_BASE_URL) ?>/interact.php" class="inline-form">
                <?= csrf_field() ?><input type="hidden" name="action" value="comment_report"><input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>">
                <select name="reason" required aria-label="Reason"><option value="">Why are you reporting this?</option>
                  <?php foreach (EngagementService::REASONS as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
                <button class="btn btn-small btn-glass" type="submit">Send report</button>
              </form></details>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
      <?php if (!$commentPage['items']): ?><li class="empty">No comments yet. Be the first to share your thoughts.</li><?php endif; ?>
    </ul>
    <?= admin_pagination(['id' => $id], $commentPage['page'], $commentPage['pages']) ?>
  </section>
  <?php endif; ?>

  <?php if ($more): ?>
    <h2 class="row-title">More from <?= e($item['artist_name']) ?></h2>
    <div class="catalog-grid"><?php foreach ($more as $m) echo render_card($m); ?></div>
  <?php endif; ?>
</main>
<script src="<?= e(APP_BASE_URL) ?>/assets/js/player.js"></script>
<?php require __DIR__ . '/partials/footer.php'; ?>
