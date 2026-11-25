<?php
require_once dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/Services/ArtistService.php';
require_once dirname(__DIR__,2).'/app/Services/ContentService.php';
require_once dirname(__DIR__,2).'/app/Helpers/artist_uploads.php';
require_auth();$user=current_user();$db=Database::connection();
$profile=(new ArtistService($db))->profile((int)$user['id']);
if(!$profile || $profile['verification_status']!=='approved'){http_response_code(403);exit('Verified artist access required.');}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf_or_abort();try{
 $paths=[];
 foreach(['media'=>'media','poster'=>'image','thumbnail'=>'image','cover'=>'image'] as $field=>$kind){$p=artist_upload($_FILES[$field]??[],$kind,(int)$user['id']);if($p)$paths[$field]=$p;}
 $id=(new ContentService($db))->create((int)$user['id'],$_POST,$paths);
 flash('success','Content uploaded and submitted for moderation.');header('Location: content.php');exit;
}catch(Throwable $e){$error=$e->getMessage();}}
require dirname(__DIR__).'/partials/header.php'; ?>
<main class="container" style="max-width:850px;padding:40px 20px"><h1>Upload content</h1><p>All new uploads enter moderation and are not public until approved.</p>
<?php if($error):?><p class="alert alert-error"><?=e($error)?></p><?php endif;?>
<form method="post" enctype="multipart/form-data"><?=csrf_field()?>
<label>Platform</label><select name="platform" required><option value="aimefilms">AIMEFILMS</option><option value="filmsnyarwanda">FilmsNyarwanda</option><option value="musicspart">MusicSpart</option></select>
<label>Content type</label><select name="content_type" required><option value="movie">Movie</option><option value="series">Series</option><option value="episode">Episode</option><option value="music_video">Music video</option><option value="album">Album</option><option value="single">Single / song</option></select>
<label>Title</label><input name="title" maxlength="180" required>
<label>Description</label><textarea name="description" rows="5"></textarea>
<label>Release date</label><input type="date" name="release_date">
<label>Language</label><input name="language" placeholder="Kinyarwanda, English...">
<label>Genre</label><input name="genre" placeholder="Drama, Afrobeat...">
<label>Duration (seconds, optional)</label><input type="number" min="1" name="duration_seconds">
<label>Parent series or album ID (for episodes/tracks, optional)</label><input type="number" min="1" name="parent_id">
<label>Main media file (MP4/WebM video or MP3/M4A/WAV audio)</label><input type="file" name="media" accept=".mp4,.webm,.mp3,.m4a,.wav">
<label>Poster image</label><input type="file" name="poster" accept=".jpg,.jpeg,.png,.webp">
<label>Thumbnail image</label><input type="file" name="thumbnail" accept=".jpg,.jpeg,.png,.webp">
<label>Album / cover image</label><input type="file" name="cover" accept=".jpg,.jpeg,.png,.webp">
<button>Upload for review</button></form></main>
<?php require dirname(__DIR__).'/partials/footer.php'; ?>
