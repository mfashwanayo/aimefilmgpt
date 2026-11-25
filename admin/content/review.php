<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once PROJECT_ROOT . '/app/Services/ModerationService.php';
require_once PROJECT_ROOT . '/app/Helpers/admin.php';
require_once PROJECT_ROOT . '/app/Helpers/catalog.php';
require_once PROJECT_ROOT . '/app/Helpers/media.php';
require_role('admin');

$mod = new ModerationService(db());
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    try {
        $new = $mod->decide($id, (string)($_POST['action'] ?? ''), (string)($_POST['note'] ?? ''), (int)current_user()['id']);
        flash('success', 'Content is now "' . $new . '".');
        header('Location: ' . ($new === 'published' || $new === 'rejected' ? 'index.php?status=pending' : 'review.php?id=' . $id));
    } catch (InvalidArgumentException $e) {
        flash('error', $e->getMessage());
        header('Location: review.php?id=' . $id);
    } catch (Throwable $e) {
        error_log('Moderation failed: ' . $e->getMessage());
        flash('error', 'Could not save the decision. Please try again.');
        header('Location: review.php?id=' . $id);
    }
    exit;
}

$item = $mod->find($id);
if (!$item) { http_response_code(404); exit('Content not found.'); }
$history = $mod->history('content', $id);
$actions = ModerationService::actionsFor($item['visibility']);
$mediaUrl = ($item['media_path'] && media_file_path($item['media_path'])) ? stream_url((int)$item['id']) : null;   // admins may play unpublished media via stream.php
$isAudio = is_audio_path($item['media_path']);
$image = upload_url($item['poster_path'] ?: ($item['cover_path'] ?: $item['thumbnail_path']));

$pageTitle = 'Review: ' . $item['title'] . ' — AIMEFILMS';
require PROJECT_ROOT . '/public/partials/header.php';
?>
<main class="container admin-wrap">
  <p class="crumbs"><a href="../index.php">Dashboard</a> / <a href="index.php">Content moderation</a> / Review</p>
  <h1><?= e($item['title']) ?> <?= status_badge($item['visibility']) ?></h1>

  <div class="review-grid">
    <section class="dashboard-panel">
      <h2>Preview</h2>
      <?php if ($mediaUrl && $isAudio): ?><audio controls controlsList="nodownload" preload="none" src="<?= e($mediaUrl) ?>"></audio>
      <?php elseif ($mediaUrl): ?><video controls controlsList="nodownload" preload="metadata" <?= $image ? 'poster="' . e($image) . '"' : '' ?> src="<?= e($mediaUrl) ?>"></video>
      <?php elseif ($image): ?><img class="review-image" src="<?= e($image) ?>" alt="Artwork">
      <?php else: ?><p class="muted">No media or artwork uploaded.</p><?php endif; ?>
      <?php if ($mediaUrl && $image): ?><img class="review-image" src="<?= e($image) ?>" alt="Artwork"><?php endif; ?>
    </section>

    <section class="dashboard-panel">
      <h2>Details</h2>
      <dl class="detail-list">
        <dt>Artist</dt><dd><?= e($item['stage_name'] ?: $item['user_name']) ?> (<?= e($item['user_email']) ?>)<?= $item['user_status'] !== 'active' ? ' — <strong class="text-bad">account ' . e($item['user_status']) . '</strong>' : '' ?></dd>
        <dt>Platform</dt><dd><?= e(platform_label($item['platform'])) ?></dd>
        <dt>Type</dt><dd><?= e(type_label($item['content_type'])) ?></dd>
        <dt>Genre / Language</dt><dd><?= e(($item['genre'] ?? '—') . ' / ' . ($item['language'] ?? '—')) ?></dd>
        <dt>Release date</dt><dd><?= e($item['release_date'] ?? '—') ?></dd>
        <dt>Duration</dt><dd><?= $item['duration_seconds'] ? e(gmdate('H:i:s', (int)$item['duration_seconds'])) : '—' ?></dd>
        <dt>Submitted</dt><dd><?= e($item['created_at']) ?></dd>
        <dt>Description</dt><dd><?= nl2br(e($item['description'] ?: '—')) ?></dd>
        <?php if ($item['moderation_note']): ?><dt>Current note</dt><dd><?= nl2br(e($item['moderation_note'])) ?></dd><?php endif; ?>
      </dl>
    </section>
  </div>

  <section class="dashboard-panel">
    <h2>Decision</h2>
    <?php if (!$actions): ?>
      <p class="muted">This item is a <?= e($item['visibility']) ?> and has no moderation actions available.</p>
    <?php else: ?>
      <form method="post" class="decision-form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
        <label for="note">Note to the artist <small>(required when rejecting)</small></label>
        <textarea id="note" name="note" rows="4" maxlength="1000" placeholder="e.g. Audio quality is too low — please upload a clearer file."></textarea>
        <div class="decision-buttons">
          <?php foreach ($actions as $a): $danger = in_array($a, ['reject', 'archive'], true); ?>
            <button class="btn btn-small <?= $a === 'approve' || $a === 'restore' ? 'btn-primary' : ($danger ? 'btn-danger' : 'btn-glass') ?>" type="submit" name="action" value="<?= e($a) ?>"><?= e(ModerationService::ACTIONS[$a]['label']) ?></button>
          <?php endforeach; ?>
        </div>
      </form>
    <?php endif; ?>
  </section>

  <section class="dashboard-panel">
    <h2>History</h2>
    <?php if (!$history): ?><p class="muted">No moderation decisions yet.</p><?php else: ?>
    <ul class="history">
      <?php foreach ($history as $h): ?>
        <li><strong><?= e($h['action']) ?></strong> — <?= e($h['from_status'] ?? '—') ?> → <?= e($h['to_status']) ?>
          by <?= e($h['admin_name'] ?? 'removed admin') ?> <span class="muted"><?= e($h['created_at']) ?></span>
          <?php if ($h['note']): ?><br><em><?= e($h['note']) ?></em><?php endif; ?></li>
      <?php endforeach; ?>
    </ul><?php endif; ?>
  </section>
</main>
<?php require PROJECT_ROOT . '/public/partials/footer.php'; ?>
