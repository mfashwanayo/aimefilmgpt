<?php
require_once dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/Services/ArtistService.php';
require_once dirname(__DIR__,2).'/app/Services/ContentService.php';
require_once dirname(__DIR__,2).'/app/Services/DownloadService.php';
require_auth();$user=current_user();$db=Database::connection();
$profile=(new ArtistService($db))->profile((int)$user['id']);
if(!$profile || $profile['verification_status']!=='approved'){http_response_code(403);exit('Verified artist access required.');}
$service=new ContentService($db);
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf_or_abort();try{
  if(($_POST['op']??'')==='downloads'){$allow=($_POST['allow']??'')==='1';(new DownloadService($db))->setAllowed((int)($_POST['id']??0),(int)$user['id'],$allow);flash('success',$allow?'Downloads are now on for this title.':'Downloads are now off for this title. People can still watch it.');}
  else{$service->deleteDraft((int)($_POST['id']??0),(int)$user['id']);flash('success','Content deleted.');}
}catch(Throwable $e){flash('error',$e instanceof RuntimeException?$e->getMessage():'Could not save that change.');}header('Location: content.php');exit;}
$dlCounts=[];$dlBlocked=[];try{$ds=new DownloadService($db);$dlCounts=$ds->countsFor((int)$user['id']);$dlBlocked=$ds->blockedIds((int)$user['id']);}catch(Throwable $e){/* Stage 7 tables not imported yet */}
$items=$service->ownContent((int)$user['id']);
require dirname(__DIR__).'/partials/header.php'; ?>
<main class="container" style="padding:40px 20px"><h1>Manage content</h1><p><a href="content-create.php">+ Upload new content</a></p>
<div style="overflow-x:auto"><table><thead><tr><th>Title</th><th>Platform</th><th>Type</th><th>Status</th><th>Views</th><th>Downloads</th><th>Admin note</th><th>Created</th><th>Action</th></tr></thead><tbody>
<?php foreach($items as $item):?><tr><td><?=e($item['title'])?></td><td><?=e($item['platform'])?></td><td><?=e($item['content_type'])?></td><td><?=e($item['visibility'])?></td><td><?=number_format((int)$item['views_count'])?></td><td><?=number_format($dlCounts[(int)$item['id']]??0)?></td><td><?=e($item['moderation_note']??'')?></td><td><?=e($item['created_at'])?></td><td><?php if(in_array($item['visibility'],ContentService::EDITABLE,true)):?><a class="btn btn-small btn-glass" href="content-edit.php?id=<?=(int)$item['id']?>"><?=$item['visibility']==='rejected'?'Fix &amp; resubmit':'Edit'?></a><?php endif;?>
<?php if(!empty($item['media_path'])):$off=in_array((int)$item['id'],$dlBlocked,true);?><form method="post" class="inline-form"><?=csrf_field()?><input type="hidden" name="op" value="downloads"><input type="hidden" name="id" value="<?=(int)$item['id']?>"><input type="hidden" name="allow" value="<?=$off?'1':'0'?>"><button type="submit" class="btn btn-small btn-glass" title="Controls whether logged-in users can download this title"><?=$off?'Downloads: off — turn on':'Downloads: on — turn off'?></button></form><?php endif;?>
<?php if(in_array($item['visibility'],['draft','rejected'],true)):?><form method="post" class="inline-form" onsubmit="return confirm('Delete this content permanently?')"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$item['id']?>"><button type="submit" class="btn btn-small btn-danger">Delete</button></form><?php elseif(!in_array($item['visibility'],ContentService::EDITABLE,true)):?>—<?php endif;?></td></tr><?php endforeach;?>
<?php if(!$items):?><tr><td colspan="9">No content uploaded yet.</td></tr><?php endif;?></tbody></table></div></main>
<?php require dirname(__DIR__).'/partials/footer.php'; ?>
