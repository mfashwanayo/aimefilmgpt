<?php
declare(strict_types=1);

require_once __DIR__ . '/NotificationService.php';

final class ContentService
{
    /** Statuses an artist may edit. Archived content is admin-controlled. */
    public const EDITABLE = ['draft', 'pending', 'rejected', 'published'];
    private const CONTAINERS = ['series', 'album'];   // need no media file of their own

    private NotificationService $notifier;

    public function __construct(private PDO $db)
    {
        $this->notifier = new NotificationService($db);
    }

    public function create(int $artistId, array $data, array $paths): int
    {
        $title = trim((string)($data['title'] ?? ''));
        $type = (string)($data['content_type'] ?? '');
        $platform = (string)($data['platform'] ?? '');
        $allowedTypes = ['movie', 'series', 'episode', 'music_video', 'album', 'single'];
        $allowedPlatforms = ['aimefilms', 'filmsnyarwanda', 'musicspart'];
        if (!in_array($type, $allowedTypes, true) || !in_array($platform, $allowedPlatforms, true)) throw new InvalidArgumentException('Choose a valid platform and content type.');
        if (($platform === 'musicspart' && !in_array($type, ['album', 'single', 'music_video'], true)) ||
            ($platform === 'filmsnyarwanda' && !in_array($type, ['movie', 'series', 'episode', 'music_video'], true))) {
            throw new InvalidArgumentException('That content type is not available on the selected platform.');
        }
        $f = $this->fields($data);   // validates title, date, etc.

        $slug = $this->slug($title) . '-' . bin2hex(random_bytes(4));
        $parent = filter_var($data['parent_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        if ($parent) {
            $p = $this->db->prepare('SELECT id FROM artist_content WHERE id=? AND artist_user_id=? AND content_type IN (\'series\',\'album\')');
            $p->execute([$parent, $artistId]);
            if (!$p->fetch()) throw new InvalidArgumentException('Select a valid series or album belonging to your account.');
        }
        $q = $this->db->prepare("INSERT INTO artist_content
          (artist_user_id,platform,content_type,title,slug,description,release_date,language,genre,duration_seconds,media_path,poster_path,thumbnail_path,cover_path,parent_id,visibility)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending')");
        $q->execute([$artistId, $platform, $type, $f['title'], $slug, $f['description'], $f['release_date'], $f['language'], $f['genre'], $f['duration_seconds'],
            $paths['media'] ?? null, $paths['poster'] ?? null, $paths['thumbnail'] ?? null, $paths['cover'] ?? null, $parent]);
        $id = (int)$this->db->lastInsertId();

        $this->notifier->notifyAdmins('content_submitted', 'New content to review',
            '"' . $f['title'] . '" (' . $platform . ' / ' . str_replace('_', ' ', $type) . ') is waiting for review.',
            APP_ADMIN_URL . '/content/review.php?id=' . $id);
        return $id;
    }

    public function ownContent(int $artistId): array
    {
        $q = $this->db->prepare('SELECT * FROM artist_content WHERE artist_user_id=? ORDER BY created_at DESC');
        $q->execute([$artistId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    /** One item, only if it belongs to this artist. */
    public function findOwn(int $id, int $artistId): ?array
    {
        $q = $this->db->prepare('SELECT * FROM artist_content WHERE id=? AND artist_user_id=? LIMIT 1');
        $q->execute([$id, $artistId]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Edit own content. Platform and type cannot change. Any save sends the item
     * back to "pending" (and clears the old moderation note) so an admin always
     * reviews what becomes public. Archived content cannot be edited.
     *
     * @param array $newPaths uploaded files keyed media|poster|thumbnail|cover
     * @return array{from:string,replaced:string[]} old file paths the caller should delete
     */
    public function update(int $id, int $artistId, array $data, array $newPaths): array
    {
        $f = $this->fields($data);
        $this->db->beginTransaction();
        try {
            $st = $this->db->prepare('SELECT * FROM artist_content WHERE id=? AND artist_user_id=?' . $this->lockClause());
            $st->execute([$id, $artistId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException('Content not found.');
            $from = (string)$row['visibility'];
            if (!in_array($from, self::EDITABLE, true)) {
                throw new InvalidArgumentException('Archived content cannot be edited. Please contact an administrator.');
            }

            $cols = ['media' => 'media_path', 'poster' => 'poster_path', 'thumbnail' => 'thumbnail_path', 'cover' => 'cover_path'];
            $vals = []; $replaced = [];
            foreach ($cols as $key => $col) {
                if (!empty($newPaths[$key])) {
                    $vals[$col] = $newPaths[$key];
                    if (!empty($row[$col])) $replaced[] = (string)$row[$col];
                } else {
                    $vals[$col] = $row[$col];
                }
            }
            if (!in_array($row['content_type'], self::CONTAINERS, true) && empty($vals['media_path'])) {
                throw new InvalidArgumentException('Upload a media file before submitting this content for review.');
            }

            $up = $this->db->prepare("UPDATE artist_content SET title=?,description=?,release_date=?,language=?,genre=?,duration_seconds=?,
                media_path=?,poster_path=?,thumbnail_path=?,cover_path=?,visibility='pending',moderation_note=NULL WHERE id=? AND artist_user_id=?");
            $up->execute([$f['title'], $f['description'], $f['release_date'], $f['language'], $f['genre'], $f['duration_seconds'],
                $vals['media_path'], $vals['poster_path'], $vals['thumbnail_path'], $vals['cover_path'], $id, $artistId]);

            $action = $from === 'rejected' ? 'resubmit' : ($from === 'published' ? 'edit_published' : 'edit');
            $log = $this->db->prepare('INSERT INTO moderation_log (entity_type, entity_id, admin_user_id, action, from_status, to_status, note) VALUES (?,?,?,?,?,?,?)');
            $log->execute(['content', $id, $artistId, $action, $from, 'pending', null]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }

        if ($from !== 'pending') {   // pending items are already in the admin queue
            $this->notifier->notifyAdmins('content_resubmitted',
                $from === 'rejected' ? 'Rejected content was resubmitted' : 'Published content was edited',
                '"' . $f['title'] . '" is back in the review queue.',
                APP_ADMIN_URL . '/content/review.php?id=' . $id);
        }
        return ['from' => $from, 'replaced' => $replaced];
    }

    public function deleteDraft(int $id, int $artistId): void
    {
        $q = $this->db->prepare("DELETE FROM artist_content WHERE id=? AND artist_user_id=? AND visibility IN ('draft','rejected')");
        $q->execute([$id, $artistId]);
        if (!$q->rowCount()) throw new RuntimeException('Only your own draft or rejected content can be deleted.');
    }

    /** Validate and normalise the fields an artist can edit. */
    private function fields(array $data): array
    {
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 180) throw new InvalidArgumentException('Title is required and must be under 180 characters.');
        $date = trim((string)($data['release_date'] ?? ''));
        if ($date !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $date);
            if (!$d || $d->format('Y-m-d') !== $date) throw new InvalidArgumentException('Enter a valid release date.');
        }
        $language = trim((string)($data['language'] ?? ''));
        $genre = trim((string)($data['genre'] ?? ''));
        if (mb_strlen($language) > 80 || mb_strlen($genre) > 100) throw new InvalidArgumentException('Language or genre is too long.');
        return [
            'title' => $title,
            'description' => trim((string)($data['description'] ?? '')),
            'release_date' => $date ?: null,
            'language' => $language ?: null,
            'genre' => $genre ?: null,
            'duration_seconds' => filter_var($data['duration_seconds'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null,
        ];
    }

    private function lockClause(): string
    {
        return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    }

    private function slug(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($value)), '-'));
        return $value ?: 'content';
    }
}
