<?php
declare(strict_types=1);

require_once __DIR__ . '/NotificationService.php';

/**
 * Admin moderation for artist_content and content_comments.
 * Uses the EXISTING status values: draft, pending, published, rejected, archived.
 * Every decision runs in a transaction and is written to moderation_log.
 */
final class ModerationService
{
    public const STATUSES  = ['draft', 'pending', 'published', 'rejected', 'archived'];
    public const PLATFORMS = ['aimefilms', 'filmsnyarwanda', 'musicspart'];
    public const TYPES     = ['movie', 'series', 'episode', 'music_video', 'album', 'single'];

    /** action => [allowed current statuses, resulting status, note required?] */
    public const ACTIONS = [
        'approve'   => ['from' => ['pending'],             'to' => 'published', 'note' => false, 'label' => 'Approve & publish'],
        'reject'    => ['from' => ['pending', 'published'], 'to' => 'rejected',  'note' => true,  'label' => 'Reject'],
        'unpublish' => ['from' => ['published'],           'to' => 'pending',   'note' => false, 'label' => 'Send back to review'],
        'archive'   => ['from' => ['published'],           'to' => 'archived',  'note' => false, 'label' => 'Archive'],
        'restore'   => ['from' => ['archived'],            'to' => 'published', 'note' => false, 'label' => 'Restore & publish'],
    ];

    /** Content types that are containers and need no media file of their own. */
    private const CONTAINERS = ['series', 'album'];
    private const NOTE_MAX = 1000;

    private NotificationService $notifier;

    public function __construct(private PDO $db)
    {
        $this->notifier = new NotificationService($db);
    }

    /** @return array<string,int> status => count (all statuses present) */
    public function statusCounts(): array
    {
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($this->db->query('SELECT visibility, COUNT(*) c FROM artist_content GROUP BY visibility') as $row) {
            $counts[$row['visibility']] = (int)$row['c'];
        }
        return $counts;
    }

    /**
     * @param array{status?:string,platform?:string,type?:string,q?:string} $f
     * @return array{items:array,total:int,page:int,pages:int}
     */
    public function listContent(array $f, int $page = 1, int $perPage = 20): array
    {
        $where = []; $args = [];
        $status = (string)($f['status'] ?? 'pending');
        if ($status === 'all') {
            $where[] = "c.visibility <> 'draft'";   // drafts are private to the artist
        } elseif (in_array($status, self::STATUSES, true) && $status !== 'draft') {
            $where[] = 'c.visibility = :status'; $args['status'] = $status;
        } else {
            $where[] = 'c.visibility = :status'; $args['status'] = 'pending';
        }
        if (in_array($f['platform'] ?? '', self::PLATFORMS, true)) { $where[] = 'c.platform = :platform'; $args['platform'] = $f['platform']; }
        if (in_array($f['type'] ?? '', self::TYPES, true))         { $where[] = 'c.content_type = :type'; $args['type'] = $f['type']; }
        $q = trim((string)($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(c.title LIKE :q1 OR u.name LIKE :q2 OR p.stage_name LIKE :q3)';
            $args['q1'] = $args['q2'] = $args['q3'] = $like;
        }
        $from = 'FROM artist_content c JOIN users u ON u.id = c.artist_user_id LEFT JOIN artist_profiles p ON p.user_id = c.artist_user_id WHERE ' . implode(' AND ', $where);

        $st = $this->db->prepare('SELECT COUNT(*) ' . $from);
        $st->execute($args);
        $total = (int)$st->fetchColumn();

        $perPage = max(1, min($perPage, 100));
        $pages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($page, $pages));

        $st = $this->db->prepare('SELECT c.id, c.title, c.platform, c.content_type, c.visibility, c.created_at, c.views_count,
                u.name AS user_name, p.stage_name ' . $from . ' ORDER BY c.created_at ASC, c.id ASC LIMIT :lim OFFSET :off');
        foreach ($args as $k => $v) $st->bindValue(':' . $k, $v);
        $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $st->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
        $st->execute();
        return ['items' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public function find(int $id): ?array
    {
        $st = $this->db->prepare('SELECT c.*, u.name AS user_name, u.email AS user_email, u.status AS user_status, p.stage_name, p.verification_status
            FROM artist_content c JOIN users u ON u.id = c.artist_user_id
            LEFT JOIN artist_profiles p ON p.user_id = c.artist_user_id WHERE c.id = ? LIMIT 1');
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function history(string $type, int $id): array
    {
        $st = $this->db->prepare('SELECT l.*, u.name AS admin_name FROM moderation_log l LEFT JOIN users u ON u.id = l.admin_user_id
            WHERE l.entity_type = ? AND l.entity_id = ? ORDER BY l.created_at DESC, l.id DESC');
        $st->execute([$type, $id]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Which actions an admin may take right now for this status. */
    public static function actionsFor(string $status): array
    {
        return array_keys(array_filter(self::ACTIONS, fn($a) => in_array($status, $a['from'], true)));
    }

    /**
     * @throws InvalidArgumentException for bad input / disallowed transition
     * @throws RuntimeException if content is missing
     */
    public function decide(int $contentId, string $action, ?string $note, int $adminId): string
    {
        if (!isset(self::ACTIONS[$action])) throw new InvalidArgumentException('Unknown moderation action.');
        $rule = self::ACTIONS[$action];
        $note = trim((string)$note);
        if (mb_strlen($note) > self::NOTE_MAX) throw new InvalidArgumentException('The note must be ' . self::NOTE_MAX . ' characters or fewer.');
        if ($rule['note'] && mb_strlen($note) < 5) throw new InvalidArgumentException('Please give the artist a reason (at least 5 characters).');

        $this->db->beginTransaction();
        try {
            $st = $this->db->prepare('SELECT c.id, c.artist_user_id, c.title, c.visibility, c.content_type, c.media_path, u.status AS user_status
                FROM artist_content c JOIN users u ON u.id = c.artist_user_id WHERE c.id = ?' . $this->lockClause());
            $st->execute([$contentId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException('Content not found.');

            $from = (string)$row['visibility'];
            if (!in_array($from, $rule['from'], true)) {
                throw new InvalidArgumentException("Cannot {$action} content that is currently \"{$from}\". Reload the page; someone may have changed it.");
            }
            if ($rule['to'] === 'published') {
                if ($row['user_status'] !== 'active') throw new InvalidArgumentException("The artist's account is {$row['user_status']}. Content cannot be published.");
                if (!in_array($row['content_type'], self::CONTAINERS, true) && empty($row['media_path'])) {
                    throw new InvalidArgumentException('This content has no media file. Reject it and ask the artist to upload one.');
                }
            }

            $keepNote = $note !== '' ? $note : null;   // approving with no note clears an old rejection note
            $up = $this->db->prepare('UPDATE artist_content SET visibility = ?, moderation_note = ? WHERE id = ? AND visibility = ?');
            $up->execute([$rule['to'], $keepNote, $contentId, $from]);
            if ($up->rowCount() !== 1) throw new RuntimeException('The content changed while saving. Please try again.');

            $this->log('content', $contentId, $adminId, $action, $from, $rule['to'], $keepNote);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }

        $this->tellArtist((int)$row['artist_user_id'], $action, (string)$row['title'], $contentId, $keepNote);
        return $rule['to'];
    }

    private function tellArtist(int $artistId, string $action, string $title, int $contentId, ?string $note): void
    {
        $edit = APP_BASE_URL . '/artist/content-edit.php?id=' . $contentId;
        $list = APP_BASE_URL . '/artist/content.php';
        [$type, $head, $body, $link] = match ($action) {
            'approve'   => ['content_approved', 'Your content was approved', '"' . $title . '" is now published.', $list],
            'reject'    => ['content_rejected', 'Your content was rejected', '"' . $title . '": ' . $note . ' You can edit it and resubmit.', $edit],
            'unpublish' => ['content_review', 'Your content was sent back to review', '"' . $title . '" is being reviewed again.' . ($note ? ' ' . $note : ''), $edit],
            'archive'   => ['content_archived', 'Your content was archived', '"' . $title . '" is no longer public.' . ($note ? ' ' . $note : ''), $list],
            'restore'   => ['content_approved', 'Your content was republished', '"' . $title . '" is published again.', $list],
            default     => [null, null, null, null],
        };
        if ($type !== null) $this->notifier->notify($artistId, $type, $head, $body, $link);
    }

    // ---- comments ---------------------------------------------------------

    public function commentCounts(): array
    {
        $c = ['visible' => 0, 'reported' => 0, 'hidden' => 0];
        foreach ($this->db->query('SELECT status, COUNT(*) n FROM content_comments GROUP BY status') as $r) $c[$r['status']] = (int)$r['n'];
        return $c;
    }

    public function listComments(string $status, int $page = 1, int $perPage = 25): array
    {
        if (!in_array($status, ['visible', 'reported', 'hidden'], true)) $status = 'reported';
        $st = $this->db->prepare('SELECT COUNT(*) FROM content_comments WHERE status = ?');
        $st->execute([$status]);
        $total = (int)$st->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($page, $pages));

        $st = $this->db->prepare('SELECT m.id, m.comment_text, m.status, m.created_at, u.name AS user_name, c.id AS content_id, c.title
            FROM content_comments m JOIN users u ON u.id = m.user_id JOIN artist_content c ON c.id = m.content_id
            WHERE m.status = :s ORDER BY m.created_at DESC, m.id DESC LIMIT :lim OFFSET :off');
        $st->bindValue(':s', $status);
        $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $st->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
        $st->execute();
        $items = $st->fetchAll(PDO::FETCH_ASSOC);
        $reports = $this->reportsFor(array_map('intval', array_column($items, 'id')));
        foreach ($items as &$it) $it['reports'] = $reports[(int)$it['id']] ?? [];
        unset($it);
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** @return array<int,string[]> comment id => report reasons (labels) */
    public function reportsFor(array $commentIds): array
    {
        if (!$commentIds) return [];
        try {
            $in = implode(',', array_fill(0, count($commentIds), '?'));
            $st = $this->db->prepare("SELECT comment_id, reason FROM comment_reports WHERE comment_id IN ($in) ORDER BY id");
            $st->execute($commentIds);
        } catch (Throwable $e) {   // Stage 6 table not imported yet
            return [];
        }
        $labels = ['spam' => 'Spam', 'abusive' => 'Abusive', 'hate' => 'Hate speech', 'spoiler' => 'Spoiler', 'other' => 'Other'];
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(int)$r['comment_id']][] = $labels[$r['reason']] ?? $r['reason'];
        return $out;
    }

    /** $to is 'visible' (restore/dismiss report) or 'hidden'. */
    public function setCommentStatus(int $commentId, string $to, int $adminId): void
    {
        if (!in_array($to, ['visible', 'hidden'], true)) throw new InvalidArgumentException('Invalid comment status.');
        $this->db->beginTransaction();
        try {
            $st = $this->db->prepare('SELECT status FROM content_comments WHERE id = ?' . $this->lockClause());
            $st->execute([$commentId]);
            $from = $st->fetchColumn();
            if ($from === false) throw new RuntimeException('Comment not found.');
            if ($from === $to) throw new InvalidArgumentException('The comment already has that status.');
            $up = $this->db->prepare('UPDATE content_comments SET status = ? WHERE id = ?');
            $up->execute([$to, $commentId]);
            if ($to === 'visible') {   // dismissing/restoring clears old reports so the comment can be reported again
                try { $this->db->prepare('DELETE FROM comment_reports WHERE comment_id = ?')->execute([$commentId]); } catch (Throwable $e) { /* table missing */ }
            }
            $this->log('comment', $commentId, $adminId, $to === 'hidden' ? 'hide' : 'restore', (string)$from, $to, null);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    // ---- internals --------------------------------------------------------

    private function log(string $type, int $id, int $adminId, string $action, ?string $from, string $to, ?string $note): void
    {
        $st = $this->db->prepare('INSERT INTO moderation_log (entity_type, entity_id, admin_user_id, action, from_status, to_status, note) VALUES (?,?,?,?,?,?,?)');
        $st->execute([$type, $id, $adminId, $action, $from, $to, $note]);
    }

    /** Row lock on MySQL/MariaDB; SQLite (used only by tests) has no FOR UPDATE. */
    private function lockClause(): string
    {
        return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    }
}
