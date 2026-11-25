<?php
declare(strict_types=1);
/**
 * Playback endpoint used by the <video>/<audio> players. Free for everyone,
 * but only for titles that are public. The owner and admins may also play
 * titles that are still under review. Supports Range requests (seeking).
 */
require_once __DIR__ . '/../app/bootstrap.php';
require_once PROJECT_ROOT . '/app/Helpers/media.php';

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare("SELECT c.media_path, c.visibility, c.artist_user_id, u.status AS artist_status
    FROM artist_content c JOIN users u ON u.id = c.artist_user_id WHERE c.id = ?");
$st->execute([$id]);
$row = $st->fetch(PDO::FETCH_ASSOC);

$allowed = false;
if ($row) {
    if ($row['visibility'] === 'published' && $row['artist_status'] === 'active') {
        $allowed = true;
    } else {
        $u = current_user();
        $allowed = $u && ($u['role'] === 'admin' || (int)$u['id'] === (int)$row['artist_user_id']);
    }
}
$file = $allowed ? media_file_path($row['media_path']) : null;
if (!$file) { http_response_code(404); exit('Not found.'); }
serve_media($file, media_mime($file));
