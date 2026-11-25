<?php
require_once dirname(__DIR__,2).'/app/bootstrap.php';
require_role('admin');$db=Database::connection();$id=(int)($_GET['id']??0);
$q=$db->prepare('SELECT a.*,u.name,u.email FROM artist_applications a JOIN users u ON u.id=a.user_id WHERE a.id=?');$q->execute([$id]);$app=$q->fetch(PDO::FETCH_ASSOC);
if(!$app){http_response_code(404);exit('Application not found.');}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf_or_abort();$decision=$_POST['decision']??'';$note=trim((string)($_POST['admin_note']??''));if(!in_array($decision,['approved','rejected'],true))$error='Invalid decision.';else{
$db->beginTransaction();try{
$q=$db->prepare('UPDATE artist_applications SET status=?,admin_note=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?');$q->execute([$decision,$note ?: null,current_user()['id'],$id]);
if($decision==='approved'){
$q=$db->prepare("INSERT INTO artist_profiles (user_id,stage_name,bio,verification_status,submitted_at,reviewed_at,reviewed_by) VALUES (?,?,?,'approved',NOW(),NOW(),?) ON DUPLICATE KEY UPDATE stage_name=VALUES(stage_name),bio=VALUES(bio),verification_status='approved',reviewed_at=NOW(),reviewed_by=VALUES(reviewed_by)");
$q->execute([$app['user_id'],$app['stage_name'],$app['bio'],current_user()['id']]);
$q=$db->prepare("UPDATE users SET role_id=(SELECT id FROM roles WHERE name='artist') WHERE id=?");$q->execute([$app['user_id']]);
}else{
$q=$db->prepare("UPDATE artist_profiles SET verification_status='rejected',verification_note=?,reviewed_at=NOW(),reviewed_by=? WHERE user_id=?");$q->execute([$note,current_user()['id'],$app['user_id']]);
}
$db->commit();flash('success','Application '.$decision.'.');header('Location: index.php');exit;
}catch(Throwable $e){$db->rollBack();$error='Could not save review: '.$e->getMessage();}
}}
require dirname(__DIR__,2).'/public/partials/header.php'; ?>
<main class="container" style="max-width:800px;padding:40px 20px"><h1>Review artist application</h1>
<p><strong>Applicant:</strong> <?=e($app['name'])?> (<?=e($app['email'])?>)</p><p><strong>Artist name:</strong> <?=e($app['stage_name'])?></p><p><strong>Biography:</strong><br><?=nl2br(e($app['bio']))?></p><p><strong>Portfolio:</strong> <?=e($app['portfolio_url']??'Not supplied')?></p>
<?php if($error):?><p class="alert alert-error"><?=e($error)?></p><?php endif;?>
<form method="post"><?=csrf_field()?><label>Admin note</label><textarea name="admin_note" rows="4"><?=e($app['admin_note']??'')?></textarea>
<button name="decision" value="approved">Approve artist</button> <button name="decision" value="rejected">Reject application</button></form></main>
<?php require dirname(__DIR__,2).'/public/partials/footer.php'; ?>
