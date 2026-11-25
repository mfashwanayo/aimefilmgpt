<?php
declare(strict_types=1);

function artist_upload(array $file, string $kind, int $userId): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload failed. Please try again.');

    $rules = [
        'image' => ['ext' => ['jpg', 'jpeg', 'png', 'webp'], 'mime' => ['image/jpeg', 'image/png', 'image/webp'], 'max' => 5 * 1024 * 1024],
        'video' => ['ext' => ['mp4', 'webm'], 'mime' => ['video/mp4', 'video/webm'], 'max' => 500 * 1024 * 1024],
        'audio' => ['ext' => ['mp3', 'm4a', 'wav'], 'mime' => ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/wav', 'audio/x-wav'], 'max' => 100 * 1024 * 1024],
    ];
    // Main media upload: music artists send audio, filmmakers send video.
    $rules['media'] = [
        'ext'  => array_merge($rules['video']['ext'], $rules['audio']['ext']),
        'mime' => array_merge($rules['video']['mime'], $rules['audio']['mime']),
        'max'  => $rules['video']['max'],
    ];
    if (!isset($rules[$kind])) throw new RuntimeException('Unsupported upload type.');
    $rule = $rules[$kind];

    $ext  = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $max = $rule['max'];
    if (in_array($ext, $rules['audio']['ext'], true)) $max = min($max, $rules['audio']['max']);
    if (!in_array($ext, $rule['ext'], true) || !in_array($mime, $rule['mime'], true) || $file['size'] > $max) {
        throw new RuntimeException('Invalid file type or size for this upload.');
    }

    $root = dirname(__DIR__, 2) . '/public/uploads/artists/' . $userId;
    if (!is_dir($root) && !mkdir($root, 0750, true) && !is_dir($root)) throw new RuntimeException('Cannot create upload directory.');
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $root . '/' . $name)) throw new RuntimeException('Could not save uploaded file.');
    return 'uploads/artists/' . $userId . '/' . $name;
}

/** Delete a file this artist uploaded earlier. Ignores anything that is not their own upload path. */
function artist_upload_delete(?string $path, int $userId): void
{
    if ($path === null || !preg_match('#^uploads/artists/' . $userId . '/[a-f0-9]{32}\.[a-z0-9]{2,4}$#', $path)) return;
    $full = dirname(__DIR__, 2) . '/public/' . $path;
    if (is_file($full)) @unlink($full);
}
