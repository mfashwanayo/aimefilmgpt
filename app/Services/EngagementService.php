<?php
declare(strict_types=1);

require_once __DIR__ . '/NotificationService.php';
require_once __DIR__ . '/CatalogService.php';

/**
 * Everything a signed-in visitor can DO: like, save to lists, follow artists,
 * comment and report comments. Every method works for one user id and never
 * trusts a content/comment id until it has been checked here.
 * User-facing problems are thrown as InvalidArgumentException / RuntimeException.
 */
final class EngagementService
{
    public const LISTS = ['watchlist', 'favorite'];
    public const REASONS = [
        'spam'    => 'Spam or advertising',
        'abusive' => 'Abusive or harassing',
        'hate'    => 'Hate speech',
        'spoiler' => 'Spoiler',
        'other'   => 'Something else',
    ];
    public const COMMENT_MIN = 2;
    public const COMMENT_MAX = 1000;
    public const RATE_LIMIT = 5;        // comments per minute per user

    private NotificationService $notifier;

    public function __construct(private PDO $db)
    {
        $this->notifier = new NotificationService($db);
    }

    // ---- likes & lists ----------------------------------------------------

    /** @return bool true if the title is now liked */
    public function toggleLike(int $userId, int $contentId): bool
    {
        $st = $this->db->prepare('SELECT 1 FROM content_likes WHERE user_id = ? AND content_id = ?');
        $st->execute([$userId, $contentId]);
        if ($st->fetchColumn()) {
            $this->db->prepare('DELETE FROM content_likes WHERE user_id = ? AND content_id = ?')->execute([$userId, $contentId]);
            $liked = false;
        } else {
            $this->publicContent($contentId);
            $this->insertIgnoringDuplicate('INSERT INTO content_likes (user_id, content_id) VALUES (?,?)', [$userId, $contentId]);
            $liked = true;
        }
        // Recount instead of +1/-1 so the number can never drift.
        $this->db->prepare('UPDATE artist_content SET likes_count = (SELECT COUNT(*) FROM content_likes WHERE content_id = ?) WHERE id = ?')
            ->execute([$contentId, $contentId]);
        return $liked;
    }

    /** @return bool true if the title is now in the list */
    public function toggleList(int $userId, int $contentId, string $list): bool
    {
        if (!in_array($list, self::LISTS, true)) throw new InvalidArgumentException('Unknown list.');
        $st = $this->db->prepare('SELECT 1 FROM user_lists WHERE user_id = ? AND content_id = ? AND list_type = ?');
        $st->execute([$userId, $contentId, $list]);
        if ($st->fetchColumn()) {
            $this->db->prepare('DELETE FROM user_lists WHERE user_id = ? AND content_id = ? AND list_type = ?')->execute([$userId, $contentId, $list]);
            return false;
        }
        $this->publicContent($contentId);
        $this->insertIgnoringDuplicate('INSERT INTO user_lists (user_id, content_id, list_type) VALUES (?,?,?)', [$userId, $contentId, $list]);
        return true;
    }

    /** @return array{liked:bool,watchlist:bool,favorite:bool} */
    public function state(int $userId, int $contentId): array
    {
        $out = ['liked' => false, 'watchlist' => false, 'favorite' => false];
        $st = $this->db->prepare('SELECT 1 FROM content_likes WHERE user_id = ? AND content_id = ?');
        $st->execute([$userId, $contentId]);
        $out['liked'] = (bool)$st->fetchColumn();
        $st = $this->db->prepare('SELECT list_type FROM user_lists WHERE user_id = ? AND content_id = ?');
        $st->execute([$userId, $contentId]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $t) $out[$t] = true;
        return $out;
    }

    // ---- follows ----------------------------------------------------------

    /** @return bool true if the user now follows the artist */
    public function toggleFollow(int $userId, int $artistId): bool
    {
        if ($userId === $artistId) throw new InvalidArgumentException('You cannot follow yourself.');
        $st = $this->db->prepare('SELECT 1 FROM artist_follows WHERE artist_user_id = ? AND follower_user_id = ?');
        $st->execute([$artistId, $userId]);
        if ($st->fetchColumn()) {
            $this->db->prepare('DELETE FROM artist_follows WHERE artist_user_id = ? AND follower_user_id = ?')->execute([$artistId, $userId]);
            return false;
        }
        $a = $this->db->prepare("SELECT 1 FROM artist_profiles p JOIN users u ON u.id = p.user_id
            WHERE p.user_id = ? AND p.verification_status = 'approved' AND u.status = 'active'");
        $a->execute([$artistId]);
        if (!$a->fetchColumn()) throw new RuntimeException('This artist is not available.');
        $new = $this->insertIgnoringDuplicate('INSERT INTO artist_follows (artist_user_id, follower_user_id) VALUES (?,?)', [$artistId, $userId]);
        if ($new) {
            $n = $this->db->prepare('SELECT name FROM users WHERE id = ?');
            $n->execute([$userId]);
            $this->notifier->notify($artistId, 'new_follower', 'You have a new follower', ($n->fetchColumn() ?: 'Someone') . ' started following you.', APP_BASE_URL . '/artist/community.php');
        }
        return true;
    }

    public function isFollowing(int $userId, int $artistId): bool
    {
        $st = $this->db->prepare('SELECT 1 FROM artist_follows WHERE artist_user_id = ? AND follower_user_id = ?');
        $st->execute([$artistId, $userId]);
        return (bool)$st->fetchColumn();
    }

    // ---- comments ---------------------------------------------------------

    public function addComment(int $userId, int $contentId, string $text): int
    {
        $item = $this->publicContent($contentId);
        $text = trim(preg_replace("/\r\n?/", "\n", $text));
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        $len = mb_strlen($text);
        if ($len < self::COMMENT_MIN) throw new InvalidArgumentException('Write a little more before posting.');
        if ($len > self::COMMENT_MAX) throw new InvalidArgumentException('Comments can be at most ' . self::COMMENT_MAX . ' characters.');

        $since = date('Y-m-d H:i:s', time() - 60);
        $r = $this->db->prepare('SELECT COUNT(*) FROM content_comments WHERE user_id = ? AND created_at >= ?');
        $r->execute([$userId, $since]);
        if ((int)$r->fetchColumn() >= self::RATE_LIMIT) throw new RuntimeException('You are commenting too fast. Please wait a minute.');
        $d = $this->db->prepare('SELECT 1 FROM content_comments WHERE user_id = ? AND content_id = ? AND comment_text = ? AND created_at >= ?');
        $d->execute([$userId, $contentId, $text, $since]);
        if ($d->fetchColumn()) throw new InvalidArgumentException('You already posted that comment.');

        $ins = $this->db->prepare("INSERT INTO content_comments (content_id, user_id, comment_text, status, created_at) VALUES (?,?,?,'visible',?)");
        $ins->execute([$contentId, $userId, $text, date('Y-m-d H:i:s')]);
        $id = (int)$this->db->lastInsertId();

        if ((int)$item['artist_user_id'] !== $userId) {
            $n = $this->db->prepare('SELECT name FROM users WHERE id = ?');
            $n->execute([$userId]);
            $this->notifier->notify((int)$item['artist_user_id'], 'new_comment', 'New comment on "' . $item['title'] . '"',
                ($n->fetchColumn() ?: 'Someone') . ': ' . mb_substr($text, 0, 120), APP_BASE_URL . '/watch.php?id=' . $contentId . '#comments');
        }
        return $id;
    }

    /** @return array{items:array,total:int,page:int,pages:int} visible comments, newest first */
    public function comments(int $contentId, int $page = 1, int $perPage = 20): array
    {
        $st = $this->db->prepare("SELECT COUNT(*) FROM content_comments WHERE content_id = ? AND status = 'visible'");
        $st->execute([$contentId]);
        $total = (int)$st->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $st = $this->db->prepare("SELECT m.id, m.user_id, m.comment_text, m.created_at, u.name AS user_name
            FROM content_comments m JOIN users u ON u.id = m.user_id
            WHERE m.content_id = :c AND m.status = 'visible' ORDER BY m.created_at DESC, m.id DESC LIMIT :lim OFFSET :off");
        $st->bindValue(':c', $contentId, PDO::PARAM_INT);
        $st->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $st->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
        $st->execute();
        return ['items' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** Authors may delete their own comments. @return int the content id (for redirecting) */
    public function deleteComment(int $userId, int $commentId): int
    {
        $st = $this->db->prepare('SELECT content_id, user_id FROM content_comments WHERE id = ?');
        $st->execute([$commentId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Comment not found.');
        if ((int)$row['user_id'] !== $userId) throw new RuntimeException('You can only delete your own comments.');
        $this->db->prepare('DELETE FROM content_comments WHERE id = ? AND user_id = ?')->execute([$commentId, $userId]);
        return (int)$row['content_id'];
    }

    /** @return int the content id (for redirecting) */
    public function reportComment(int $userId, int $commentId, string $reason): int
    {
        if (!isset(self::REASONS[$reason])) throw new InvalidArgumentException('Choose a reason for the report.');
        $this->db->beginTransaction();
        try {
            $st = $this->db->prepare('SELECT content_id, user_id, status FROM content_comments WHERE id = ?');
            $st->execute([$commentId]);
            $c = $st->fetch(PDO::FETCH_ASSOC);
            if (!$c || $c['status'] === 'hidden') throw new RuntimeException('Comment not found.');
            if ((int)$c['user_id'] === $userId) throw new InvalidArgumentException('You cannot report your own comment.');

            $ins = $this->db->prepare('INSERT INTO comment_reports (comment_id, reporter_user_id, reason) VALUES (?,?,?)');
            try {
                $ins->execute([$commentId, $userId, $reason]);
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') throw new InvalidArgumentException('You have already reported this comment.');
                throw $e;
            }
            $flag = $this->db->prepare("UPDATE content_comments SET status = 'reported' WHERE id = ? AND status = 'visible'");
            $flag->execute([$commentId]);
            $first = $flag->rowCount() === 1;
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        if ($first) {   // one admin notice per comment, not one per report
            $this->notifier->notifyAdmins('comment_reported', 'A comment was reported', 'Reason: ' . self::REASONS[$reason], APP_ADMIN_URL . '/comments/index.php?status=reported');
        }
        return (int)$c['content_id'];
    }

    // ---- library ----------------------------------------------------------

    /** @return array{watchlist:int,favorite:int,liked:int,following:int} */
    public function counts(int $userId): array
    {
        $q = fn(string $sql) => (function () use ($sql, $userId) { $s = $this->db->prepare($sql); $s->execute([$userId]); return (int)$s->fetchColumn(); })();
        return [
            'watchlist' => $q("SELECT COUNT(*) FROM user_lists WHERE user_id = ? AND list_type = 'watchlist'"),
            'favorite'  => $q("SELECT COUNT(*) FROM user_lists WHERE user_id = ? AND list_type = 'favorite'"),
            'liked'     => $q('SELECT COUNT(*) FROM content_likes WHERE user_id = ?'),
            'following' => $q('SELECT COUNT(*) FROM artist_follows WHERE follower_user_id = ?'),
        ];
    }

    /** Saved titles that are still public. @param string $kind watchlist|favorite|liked */
    public function savedItems(int $userId, string $kind, int $limit = 60): array
    {
        if ($kind === 'liked') {
            $join = 'JOIN content_likes x ON x.content_id = c.id AND x.user_id = :u';
        } elseif (in_array($kind, self::LISTS, true)) {
            $join = "JOIN user_lists x ON x.content_id = c.id AND x.user_id = :u AND x.list_type = '" . $kind . "'";
        } else {
            return [];
        }
        $st = $this->db->prepare('SELECT ' . CatalogService::CARD . ' ' . CatalogService::FROM . ' ' . $join
            . " WHERE c.visibility = 'published' ORDER BY x.created_at DESC, c.id DESC LIMIT :lim");
        $st->bindValue(':u', $userId, PDO::PARAM_INT);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function followedArtists(int $userId): array
    {
        $st = $this->db->prepare("SELECT p.user_id, p.stage_name, p.avatar_path, p.city, p.country
            FROM artist_follows f JOIN artist_profiles p ON p.user_id = f.artist_user_id
            JOIN users u ON u.id = p.user_id AND u.status = 'active'
            WHERE f.follower_user_id = ? AND p.verification_status = 'approved' ORDER BY f.created_at DESC, f.id DESC");
        $st->execute([$userId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---- internals --------------------------------------------------------

    /** The title must be published and its artist active, otherwise nobody can interact with it. */
    private function publicContent(int $id): array
    {
        $st = $this->db->prepare("SELECT c.id, c.artist_user_id, c.title FROM artist_content c
            JOIN users u ON u.id = c.artist_user_id AND u.status = 'active' WHERE c.id = ? AND c.visibility = 'published'");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('This title is not available.');
        return $row;
    }

    /** @return bool true if a row was inserted, false if it already existed */
    private function insertIgnoringDuplicate(string $sql, array $args): bool
    {
        try {
            $this->db->prepare($sql)->execute($args);
            return true;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') return false;   // double click / two tabs
            throw $e;
        }
    }
}
