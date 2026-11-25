<?php
require_once dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/Services/ArtistService.php';
require_auth();$user=current_user();$db=Database::connection();
$profile=(new ArtistService($db))->profile((int)$user['id']);
if(!$profile || $profile['verification_status']!=='approved'){http_response_code(403);exit('Verified artist access required.');}
$q=$db->prepare("SELECT u.name, f.created_at FROM artist_follows f JOIN users u ON u.id=f.follower_user_id WHERE f.artist_user_id=? ORDER BY f.created_at DESC LIMIT 100");$q->execute([$user['id']]);$followers=$q->fetchAll(PDO::FETCH_ASSOC);
$q=$db->prepare("SELECT c.id,c.comment_text,c.status,c.created_at,u.name,a.title FROM content_comments c JOIN artist_content a ON a.id=c.content_id JOIN users u ON u.id=c.user_id WHERE a.artist_user_id=? ORDER BY c.created_at DESC LIMIT 100");$q->execute([$user['id']]);$comments=$q->fetchAll(PDO::FETCH_ASSOC);
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf_or_abort();$id=(int)($_POST['comment_id']??0);$q=$db->prepare("UPDATE content_comments c JOIN artist_content a ON a.id=c.content_id SET c.status='hidden' WHERE c.id=? AND a.artist_user_id=?");$q->execute([$id,$user['id']]);flash('success','Comment hidden from your content.');header('Location: community.php');exit;}
require dirname(__DIR__).'/partials/header.php'; ?>
<main class="container" style="padding:40px 20px"><h1>Community</h1><h2>Recent followers</h2><ul><?php foreach($followers as $f):?><li><?=e($f['name'])?> — <?=e($f['created_at'])?></li><?php endforeach;?><?php if(!$followers):?><li>No followers yet.</li><?php endif;?></ul>
<h2>Comments on your content</h2><div style="overflow-x:auto"><table><thead><tr><th>Content</th><th>User</th><th>Comment</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php foreach($comments as $c):?><tr><td><?=e($c['title'])?></td><td><?=e($c['name'])?></td><td><?=e($c['comment_text'])?></td><td><?=e($c['status'])?></td><td><?php if($c['status']==='visible'):?><form method="post"><?=csrf_field()?><input type="hidden" name="comment_id" value="<?=(int)$c['id']?>"><button>Hide</button></form><?php endif;?></td></tr><?php endforeach;?>
<?php if(!$comments):?><tr><td colspan="5">No comments yet.</td></tr><?php endif;?></tbody></table></div></main>
<?php require dirname(__DIR__).'/partials/footer.php'; ?>
