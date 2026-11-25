<?php
require_once dirname(__DIR__,2).'/app/bootstrap.php';
require_role('admin');$db=Database::connection();
$q=$db->query("SELECT a.*,u.name,u.email FROM artist_applications a JOIN users u ON u.id=a.user_id ORDER BY FIELD(a.status,'pending','rejected','approved'),a.submitted_at DESC");
$applications=$q->fetchAll(PDO::FETCH_ASSOC);
require dirname(__DIR__,2).'/public/partials/header.php'; ?>
<main class="container" style="padding:40px 20px"><h1>Artist applications</h1><div style="overflow-x:auto"><table><thead><tr><th>Applicant</th><th>Artist name</th><th>Portfolio</th><th>Status</th><th>Submitted</th><th>Review</th></tr></thead><tbody>
<?php foreach($applications as $a):?><tr><td><?=e($a['name'])?><br><?=e($a['email'])?></td><td><?=e($a['stage_name'])?></td><td><?php if($a['portfolio_url']):?><a href="<?=e($a['portfolio_url'])?>" target="_blank" rel="noopener">Open</a><?php endif;?></td><td><?=e($a['status'])?></td><td><?=e($a['submitted_at'])?></td><td><a href="review.php?id=<?=(int)$a['id']?>">Review</a></td></tr><?php endforeach;?>
<?php if(!$applications):?><tr><td colspan="6">No applications.</td></tr><?php endif;?></tbody></table></div></main>
<?php require dirname(__DIR__,2).'/public/partials/footer.php'; ?>
