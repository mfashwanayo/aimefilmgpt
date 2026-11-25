<?php
declare(strict_types=1);

final class ArtistService
{
    public function __construct(private PDO $db) {}

    public function profile(int $userId): ?array
    {
        $q = $this->db->prepare('SELECT * FROM artist_profiles WHERE user_id = ? LIMIT 1');
        $q->execute([$userId]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function submitApplication(int $userId, array $data, ?string $proofPath): void
    {
        $stage = trim((string)($data['stage_name'] ?? ''));
        $bio = trim((string)($data['bio'] ?? ''));
        $portfolio = trim((string)($data['portfolio_url'] ?? ''));
        if ($stage === '' || mb_strlen($stage) > 120) throw new InvalidArgumentException('Enter a stage/artist name (maximum 120 characters).');
        if ($bio === '' || mb_strlen($bio) < 30) throw new InvalidArgumentException('Your biography must contain at least 30 characters.');
        if ($portfolio !== '' && !filter_var($portfolio, FILTER_VALIDATE_URL)) throw new InvalidArgumentException('Enter a valid portfolio URL.');

        $check = $this->db->prepare("SELECT id FROM artist_applications WHERE user_id=? AND status='pending' LIMIT 1");
        $check->execute([$userId]);
        if ($check->fetch()) throw new RuntimeException('You already have an application waiting for review.');

        $q = $this->db->prepare("INSERT INTO artist_applications (user_id,stage_name,bio,portfolio_url,proof_path) VALUES (?,?,?,?,?)");
        $q->execute([$userId, $stage, $bio, $portfolio ?: null, $proofPath]);
    }

    public function saveProfile(int $userId, array $data, array $files): void
    {
        $stage = trim((string)($data['stage_name'] ?? ''));
        if ($stage === '') throw new InvalidArgumentException('Artist name is required.');
        $existing = $this->profile($userId);
        $avatar = $files['avatar_path'] ?? ($existing['avatar_path'] ?? null);
        $banner = $files['banner_path'] ?? ($existing['banner_path'] ?? null);
        $q = $this->db->prepare("INSERT INTO artist_profiles (user_id,stage_name,bio,country,city,website_url,avatar_path,banner_path)
          VALUES (?,?,?,?,?,?,?,?)
          ON DUPLICATE KEY UPDATE stage_name=VALUES(stage_name),bio=VALUES(bio),country=VALUES(country),city=VALUES(city),
          website_url=VALUES(website_url),avatar_path=VALUES(avatar_path),banner_path=VALUES(banner_path)");
        $q->execute([$userId,$stage,trim((string)($data['bio']??'')),trim((string)($data['country']??'')),
          trim((string)($data['city']??'')),trim((string)($data['website_url']??'')) ?: null,$avatar,$banner]);
    }

    public function dashboardStats(int $userId): array
    {
        $stats = [];
        foreach ([
          'total_content' => "SELECT COUNT(*) FROM artist_content WHERE artist_user_id=?",
          'published_content' => "SELECT COUNT(*) FROM artist_content WHERE artist_user_id=? AND visibility='published'",
          'pending_content' => "SELECT COUNT(*) FROM artist_content WHERE artist_user_id=? AND visibility='pending'",
          'total_views' => "SELECT COALESCE(SUM(views_count),0) FROM artist_content WHERE artist_user_id=?",
          'followers' => "SELECT COUNT(*) FROM artist_follows WHERE artist_user_id=?",
          'comments' => "SELECT COUNT(*) FROM content_comments c JOIN artist_content a ON a.id=c.content_id WHERE a.artist_user_id=? AND c.status='visible'"
        ] as $key=>$sql) {
            $q=$this->db->prepare($sql); $q->execute([$userId]); $stats[$key]=(int)$q->fetchColumn();
        }
        $q=$this->db->prepare("SELECT COALESCE(SUM(amount),0) FROM artist_earnings WHERE artist_user_id=? AND status IN ('approved','paid')");
        $q->execute([$userId]); $stats['earnings_total']=(float)$q->fetchColumn();
        return $stats;
    }
}
