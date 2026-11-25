<?php
declare(strict_types=1);

/** Gated media delivery: files are only ever read through stream.php / download.php. */

const MEDIA_MIME = ['mp4' => 'video/mp4', 'webm' => 'video/webm', 'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'wav' => 'audio/wav'];

function stream_url(int $contentId): string   { return APP_BASE_URL . '/stream.php?id=' . $contentId; }
function download_url(int $contentId): string { return APP_BASE_URL . '/download.php?id=' . $contentId; }

/** Absolute path of a stored media file, or null if the stored value is not a real upload. */
function media_file_path(?string $dbPath): ?string
{
    if ($dbPath === null || !preg_match('#^uploads/artists/\d+/[a-f0-9]{32}\.(mp4|webm|mp3|m4a|wav)$#', $dbPath)) return null;
    $root = realpath(dirname(__DIR__, 2) . '/public/uploads');
    $file = realpath(dirname(__DIR__, 2) . '/public/' . $dbPath);
    if ($root === false || $file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) return null;
    return $file;
}

function media_mime(string $path): string
{
    return MEDIA_MIME[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
}

/** "Title - Artist.ext" made safe for a Content-Disposition header and any file system. */
function safe_download_name(string $title, string $artist, string $ext): string
{
    $clean = fn(string $s) => trim(preg_replace('/[\x00-\x1f\x7f"\\\\\/:*?<>|]+/u', ' ', $s));
    $name = trim($clean($title) . ' - ' . $clean($artist), ' -');
    $name = mb_substr(preg_replace('/\s+/u', ' ', $name), 0, 120);
    return ($name !== '' ? $name : 'aimefilms-download') . '.' . $ext;
}

/**
 * Parse an HTTP Range header (single range only).
 * @return array{status:int,start:int,end:int}  status 200 = whole file, 206 = partial, 416 = unsatisfiable
 */
function media_range(int $size, ?string $header): array
{
    $whole = ['status' => 200, 'start' => 0, 'end' => max(0, $size - 1)];
    if ($header === null || $header === '') return $whole;
    if (!preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $m) || ($m[1] === '' && $m[2] === '')) return $whole;   // malformed Range: ignore it and send the whole file
    if ($m[1] === '') {                       // suffix range: last N bytes
        $n = (int)$m[2];
        if ($n <= 0) return ['status' => 416, 'start' => 0, 'end' => 0];
        $start = max(0, $size - $n); $end = $size - 1;
    } else {
        $start = (int)$m[1];
        $end = $m[2] === '' ? $size - 1 : min((int)$m[2], $size - 1);
    }
    if ($size === 0 || $start >= $size || $start > $end) return ['status' => 416, 'start' => 0, 'end' => 0];
    return ['status' => 206, 'start' => $start, 'end' => $end];
}

/** Send a file (inline for playback, attachment when $downloadName is given). Ends the request. */
function serve_media(string $file, string $mime, ?string $downloadName = null): never
{
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();   // don't lock the session while streaming
    $size = (int)filesize($file);
    $r = media_range($size, $_SERVER['HTTP_RANGE'] ?? null);

    header('X-Content-Type-Options: nosniff');
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=0, must-revalidate');
    if ($r['status'] === 416) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }
    http_response_code($r['status']);
    header('Content-Type: ' . $mime);
    if ($downloadName !== null) {
        $ascii = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $downloadName);
        header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
    } else {
        header('Content-Disposition: inline');
    }
    $length = $r['end'] - $r['start'] + 1;
    if ($r['status'] === 206) header('Content-Range: bytes ' . $r['start'] . '-' . $r['end'] . '/' . $size);
    header('Content-Length: ' . $length);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') exit;
    @set_time_limit(0);
    while (ob_get_level() > 0) ob_end_clean();
    $fh = fopen($file, 'rb');
    if ($fh === false) exit;
    fseek($fh, $r['start']);
    $left = $length;
    while ($left > 0 && !feof($fh) && !connection_aborted()) {
        $chunk = fread($fh, min(262144, $left));
        if ($chunk === false || $chunk === '') break;
        echo $chunk;
        $left -= strlen($chunk);
        flush();
    }
    fclose($fh);
    exit;
}
