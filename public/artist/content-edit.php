<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once PROJECT_ROOT . '/app/Services/ArtistService.php';
require_once PROJECT_ROOT . '/app/Services/ContentService.php';
require_once PROJECT_ROOT . '/app/Helpers/artist_uploads.php';
require_once PROJECT_ROOT . '/app/Helpers/admin.php';
require_auth();

$user = current_user();
$uid = (int)$user['id'];
$db = db();
$profile = (new ArtistService($db))->profile($uid);
if (!$profile || $profile['verification_status'] !== 'approved') { http_response_code(403); exit('Verified artist access required.'); }

$service = new ContentService($db);
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$item = $service->findOwn($id, $uid);
if (!$item) { http_response_code(404); exit('Content not found.'); }

$editable = in_array($item['visibility'], ContentService::EDITABLE, true);
$error = '';

if ($editable && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $new = [];
    try {
        foreach (['media' => 'media', 'poster' => 'image', 'thumbnail' => 'image', 'cover' => 'image'] as $field => $kind) {
            $p = artist_upload($_FILES[$field] ?? [], $kind, $uid);
            if ($p) $new[$field] = $p;
        }
        $result = $service->update($id, $uid, $_POST, $new);
        foreach ($result['replaced'] as $old) artist_upload_delete($old, $uid);   // remove files that were replaced
        flash('success', $result['from'] === 'pending'
            ? 'Changes saved. Your content is still waiting for review.'
            : 'Changes saved and sent for review. An administrator will check it shortly.');
        header('Location: content.php');
        exit;
    } catch (InvalidArgumentException | RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Content edit failed: ' . $e->getMessage());
        $error = 'Could not save your changes. Please try again.';
    }
    foreach ($new as $p) artist_upload_delete($p, $uid);   // don't keep files from a failed save
}

$v = fn(string $k) => (string)($_POST[$k] ?? ($item[$k] ?? ''));
$fileNote = fn(?string $path) => $path ? 'Current file: ' . basename($path) . ' (leave empty to keep it)' : 'No file uploaded yet';

$pageTitle = 'Edit: ' . $item['title'] . ' — AIMEFILMS';
require PROJECT_ROOT . '/public/partials/header.php';
?>
<main class="container admin-wrap" style="max-width:850px">
  <p class="crumbs"><a href="index.php">Dashboard</a> / <a href="content.php">Manage content</a> / Edit</p>
  <h1><?= e($item['title']) ?> <?= status_badge($item['visibility']) ?></h1>
  <p class="muted"><?= e(platform_label($item['platform'])) ?> · <?= e(type_label($item['content_type'])) ?> (platform and type cannot be changed)</p>

  <?php if ($item['visibility'] === 'rejected' && $item['moderation_note']): ?>
    <div class="note-banner note-bad"><strong>Why this was rejected</strong><br><?= nl2br(e($item['moderation_note'])) ?></div>
    <p class="muted">Fix the issues below, then save. It will go back to the admin team for review.</p>
  <?php elseif ($item['visibility'] === 'published'): ?>
    <div class="note-banner note-warn"><strong>This content is live.</strong> Saving any change takes it offline and sends it back for review. It becomes public again once an administrator approves it.</div>
  <?php elseif ($item['visibility'] === 'pending'): ?>
    <div class="note-banner note-info">This content is waiting for review. Saving keeps it in the queue.</div>
  <?php elseif (!$editable): ?>
    <div class="note-banner note-warn">Archived content can't be edited. Please contact an administrator.</div>
  <?php endif; ?>

  <?php if ($error): ?><p class="alert alert-error flash-bar"><?= e($error) ?></p><?php endif; ?>

  <?php if ($editable): ?>
  <form method="post" enctype="multipart/form-data"
        <?= $item['visibility'] === 'published' ? 'onsubmit="return confirm(\'This will take your content offline until an administrator approves it again. Continue?\')"' : '' ?>>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
    <label for="title">Title</label><input id="title" name="title" maxlength="180" required value="<?= e($v('title')) ?>">
    <label for="description">Description</label><textarea id="description" name="description" rows="5"><?= e($v('description')) ?></textarea>
    <label for="release_date">Release date</label><input id="release_date" type="date" name="release_date" value="<?= e($v('release_date')) ?>">
    <label for="language">Language</label><input id="language" name="language" maxlength="80" value="<?= e($v('language')) ?>">
    <label for="genre">Genre</label><input id="genre" name="genre" maxlength="100" value="<?= e($v('genre')) ?>">
    <label for="duration_seconds">Duration (seconds)</label><input id="duration_seconds" type="number" min="1" name="duration_seconds" value="<?= e($v('duration_seconds')) ?>">
    <label>Main media file (MP4/WebM video or MP3/M4A/WAV audio)</label>
    <input type="file" name="media" accept=".mp4,.webm,.mp3,.m4a,.wav"><small class="muted"><?= e($fileNote($item['media_path'])) ?></small>
    <label>Poster image</label><input type="file" name="poster" accept=".jpg,.jpeg,.png,.webp"><small class="muted"><?= e($fileNote($item['poster_path'])) ?></small>
    <label>Thumbnail image</label><input type="file" name="thumbnail" accept=".jpg,.jpeg,.png,.webp"><small class="muted"><?= e($fileNote($item['thumbnail_path'])) ?></small>
    <label>Album / cover image</label><input type="file" name="cover" accept=".jpg,.jpeg,.png,.webp"><small class="muted"><?= e($fileNote($item['cover_path'])) ?></small>
    <button type="submit"><?= $item['visibility'] === 'rejected' ? 'Save & resubmit for review' : 'Save changes' ?></button>
    <a class="btn btn-small btn-glass" href="content.php">Cancel</a>
  </form>
  <?php endif; ?>
</main>
<?php require PROJECT_ROOT . '/public/partials/footer.php'; ?>
