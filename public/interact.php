<?php
declare(strict_types=1);
/**
 * Single POST endpoint for like / watchlist / favourite / follow / comment / report.
 * Always redirects back to a page built on the server from validated ids,
 * never to an address supplied by the request.
 */
require_once __DIR__ . '/../app/bootstrap.php';
require_once PROJECT_ROOT . '/app/Services/EngagementService.php';
require_once PROJECT_ROOT . '/app/Helpers/catalog.php';

if (!function_exists('redirect_to')) {
    function redirect_to(string $url): never { header('Location: ' . $url); exit; }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}
require_auth();
verify_csrf_or_abort();

$uid = (int)current_user()['id'];
$action = (string)($_POST['action'] ?? '');
$contentId = (int)($_POST['content_id'] ?? 0);
$svc = new EngagementService(db());
$home = APP_BASE_URL . '/index.php';
$dest = $contentId > 0 ? watch_url($contentId) : $home;

try {
    switch ($action) {
        case 'like':
            $on = $svc->toggleLike($uid, $contentId);
            flash('success', $on ? 'Added to your liked titles.' : 'Like removed.');
            break;
        case 'watchlist':
        case 'favorite':
            $on = $svc->toggleList($uid, $contentId, $action);
            $name = $action === 'watchlist' ? 'your watchlist' : 'your favourites';
            flash('success', $on ? "Added to $name." : "Removed from $name.");
            break;
        case 'follow':
            $artistId = (int)($_POST['artist_id'] ?? 0);
            $dest = artist_url($artistId);
            $on = $svc->toggleFollow($uid, $artistId);
            flash('success', $on ? 'You are now following this artist.' : 'You unfollowed this artist.');
            break;
        case 'comment_add':
            $svc->addComment($uid, $contentId, (string)($_POST['comment'] ?? ''));
            flash('success', 'Comment posted.');
            $dest .= '#comments';
            break;
        case 'comment_delete':
            $dest = watch_url($svc->deleteComment($uid, (int)($_POST['comment_id'] ?? 0))) . '#comments';
            flash('success', 'Comment deleted.');
            break;
        case 'comment_report':
            $dest = watch_url($svc->reportComment($uid, (int)($_POST['comment_id'] ?? 0), (string)($_POST['reason'] ?? ''))) . '#comments';
            flash('success', 'Thank you. Our team will review this comment.');
            break;
        default:
            flash('error', 'Unknown action.');
            $dest = $home;
    }
} catch (InvalidArgumentException | RuntimeException $e) {
    flash('error', $e->getMessage());
} catch (Throwable $e) {
    error_log('Interaction failed (' . $action . '): ' . $e->getMessage());
    flash('error', 'Something went wrong. Please try again.');
}
redirect_to($dest);
