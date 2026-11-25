<?php
declare(strict_types=1);
/**
 * Download endpoint. Anyone can watch for free; downloading needs a login.
 * Guests are sent to the login page and brought back here afterwards.
 */
require_once __DIR__ . '/../app/bootstrap.php';
require_once PROJECT_ROOT . '/app/Helpers/media.php';
require_once PROJECT_ROOT . '/app/Helpers/redirect.php';
require_once PROJECT_ROOT . '/app/Services/DownloadService.php';

$id = (int)($_GET['id'] ?? 0);
$user = current_user();
$back = APP_BASE_URL . '/watch.php?id=' . $id;

if (!$user) {
    flash('error', 'Please log in to download. Watching stays free.');
    header('Location: ' . APP_BASE_URL . '/auth/login.php?next=' . rawurlencode($back));
    exit;
}

try {
    $svc = new DownloadService(db());
    $item = $svc->authorize((int)$user['id'], $id);
    $file = media_file_path($item['media_path']);
    if (!$file) throw new RuntimeException('The file for this title is missing. Please tell the artist or an administrator.');
    $isRange = isset($_SERVER['HTTP_RANGE']) && !preg_match('/^bytes=0-/', (string)$_SERVER['HTTP_RANGE']);
    if (!$isRange) $svc->record((int)$user['id'], $id, $_SERVER['REMOTE_ADDR'] ?? null);   // a resumed download is not a new one
} catch (RuntimeException $e) {
    flash('error', $e->getMessage());
    header('Location: ' . ($id > 0 ? $back : APP_BASE_URL . '/index.php'));
    exit;
} catch (Throwable $e) {
    error_log('Download failed: ' . $e->getMessage());
    flash('error', 'Downloads are temporarily unavailable. Please try again later.');
    header('Location: ' . ($id > 0 ? $back : APP_BASE_URL . '/index.php'));
    exit;
}
serve_media($file, media_mime($file), safe_download_name($item['title'], $item['artist_name'], strtolower(pathinfo($file, PATHINFO_EXTENSION))));
