<?php
require_once dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/Services/ArtistService.php';
require_once dirname(__DIR__,2).'/app/Helpers/artist_uploads.php';
require_auth(); $user=current_user(); $db=Database::connection(); $service=new ArtistService($db);
$profile=$service->profile((int)$user['id']);
if(!$profile || $profile['verification_status']!=='approved'){http_response_code(403);exit('Artist approval required.');}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf_or_abort();try{
 $uploads=[];
 foreach(['avatar'=>'avatar_path','banner'=>'banner_path'] as $field=>$key){$p=artist_upload($_FILES[$field]??[],'image',(int)$user['id']);if($p)$uploads[$key]=$p;}
 $service->saveProfile((int)$user['id'],$_POST,$uploads);flash('success','Profile updated.');header('Location: profile.php');exit;
}catch(Throwable $e){$error=$e->getMessage();}}
require dirname(__DIR__).'/partials/header.php'; ?>
<main class="container" style="max-width:760px;padding:40px 20px"><h1>Edit artist profile</h1>
<?php if($error):?><p class="alert alert-error"><?=e($error)?></p><?php endif;?>
<form method="post" enctype="multipart/form-data"><?=csrf_field()?>
<label>Artist name</label><input name="stage_name" required value="<?=e($profile['stage_name'])?>">
<label>Biography</label><textarea name="bio" rows="5"><?=e($profile['bio']??'')?></textarea>
<label>Country</label><input name="country" value="<?=e($profile['country']??'')?>">
<label>City</label><input name="city" value="<?=e($profile['city']??'')?>">
<label>Website</label><input type="url" name="website_url" value="<?=e($profile['website_url']??'')?>">
<label>Avatar image</label><input type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp">
<label>Banner image</label><input type="file" name="banner" accept=".jpg,.jpeg,.png,.webp">
<button>Save profile</button></form></main>
<?php require dirname(__DIR__).'/partials/footer.php'; ?>
