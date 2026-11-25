<?php
require_once dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/Services/ArtistService.php';
require_auth();
$user=current_user(); $db=Database::connection(); $service=new ArtistService($db);
$profile=$service->profile((int)$user['id']);
$q=$db->prepare('SELECT status,admin_note FROM artist_applications WHERE user_id=? ORDER BY submitted_at DESC LIMIT 1');
$q->execute([$user['id']]); $application=$q->fetch(PDO::FETCH_ASSOC) ?: null;
$stats=$profile ? $service->dashboardStats((int)$user['id']) : [];
require dirname(__DIR__).'/partials/header.php';
?>
<main class="container" style="padding:40px 20px">
<h1>Artist dashboard</h1>
<?php if(!$profile || $profile['verification_status']!=='approved'): ?>
<section class="dashboard-panel"><h2>Artist access</h2>
<?php if($application && $application['status']==='pending'): ?><p>Your application is being reviewed.</p>
<?php elseif($application && $application['status']==='rejected'): ?><p>Your previous application was not approved. Note: <?=e($application['admin_note']??'Please update your details and apply again.')?></p><a href="apply.php">Submit a new application</a>
<?php else: ?><p>Apply to create an artist profile. You can prepare your profile after submitting.</p><a href="apply.php">Apply to become an artist</a><?php endif; ?>
</section>
<?php else: ?>
<p>Welcome, <?=e($profile['stage_name'])?>. Your artist profile is verified.</p>
<nav class="artist-actions"><a href="profile.php">Edit profile</a> · <a href="content-create.php">Upload content</a> · <a href="content.php">Manage content</a> · <a href="community.php">Followers & comments</a></nav>
<div class="artist-stats">
<?php foreach(['total_content'=>'Total content','published_content'=>'Published','pending_content'=>'In review','total_views'=>'Views','followers'=>'Followers','comments'=>'Comments'] as $key=>$label): ?>
<div class="stat-card"><strong><?=number_format($stats[$key]??0)?></strong><span><?=e($label)?></span></div>
<?php endforeach; ?>
</div>
<section class="dashboard-panel"><h2>Earnings</h2><p><?=number_format($stats['earnings_total']??0,2)?> RWF approved/paid earnings</p><small>Monetization is not active yet. This figure will remain zero until earning records are added by the platform.</small></section>
<?php endif; ?>
</main>
<?php require dirname(__DIR__).'/partials/footer.php'; ?>
