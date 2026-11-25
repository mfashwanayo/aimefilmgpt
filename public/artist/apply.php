<?php
require_once dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/Services/ArtistService.php';
require_once dirname(__DIR__,2).'/app/Helpers/artist_uploads.php';
require_auth();
$user=current_user(); $db=Database::connection(); $service=new ArtistService($db);
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  verify_csrf_or_abort();
  try {
    $proof=artist_upload($_FILES['proof']??[],'image',(int)$user['id']);
    $service->submitApplication((int)$user['id'],$_POST,$proof);
    flash('success','Your artist application has been submitted for review.');
    header('Location: '.APP_BASE_URL.'/artist/index.php'); exit;
  } catch(Throwable $e) { $error=$e->getMessage(); }
}
require dirname(__DIR__).'/partials/header.php';
?>
<main class="container" style="max-width:760px;padding:48px 20px">
<h1>Apply to become an artist</h1><p>Tell the AIMEFILMS team about your creative work. Approval is required before publishing content.</p>
<?php if($error): ?><p class="alert alert-error"><?=e($error)?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data">
<?=csrf_field()?>
<label>Artist / stage name</label><input name="stage_name" maxlength="120" required>
<label>Biography</label><textarea name="bio" minlength="30" rows="6" required></textarea>
<label>Portfolio or social profile URL (optional)</label><input type="url" name="portfolio_url">
<label>Proof of identity or artist work (image, optional)</label><input type="file" name="proof" accept=".jpg,.jpeg,.png,.webp">
<button type="submit">Submit application</button>
</form></main>
<?php require dirname(__DIR__).'/partials/footer.php'; ?>
