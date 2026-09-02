<?php
require __DIR__.'/bootstrap.php';
require_permission('dashboard.view');
$pdo=db();
$me=current_admin();
$counts=['services'=>(int)$pdo->query('SELECT COUNT(*) FROM services WHERE active=1')->fetchColumn(),
'gallery'=>(int)$pdo->query('SELECT COUNT(*) FROM gallery WHERE active=1')->fetchColumn(),
'new'=>(int)$pdo->query("SELECT COUNT(*) FROM estimate_requests WHERE status='new'")->fetchColumn(),
'drafts'=>(int)$pdo->query('SELECT COUNT(*) FROM content_drafts')->fetchColumn(),
'notifications'=>unread_notification_count(),
'approvals'=>(int)$pdo->query("SELECT COUNT(*) FROM content_approvals WHERE status='pending'")->fetchColumn()];
$missing=(int)$pdo->query("SELECT COUNT(*) FROM gallery WHERE active=1 AND (image_path IS NULL OR image_path='')")->fetchColumn();
$emailOk=(int)$pdo->query('SELECT COUNT(*) FROM correo WHERE estado=1')->fetchColumn()>0;
$areas=(int)$pdo->query('SELECT COUNT(*) FROM service_areas WHERE active=1')->fetchColumn();
if(user_can('estimates.manage_all')) {
    $due=(int)$pdo->query("SELECT COUNT(*) FROM estimate_requests WHERE follow_up_date IS NOT NULL AND follow_up_date<=CURDATE() AND status NOT IN ('won','lost','closed')")->fetchColumn();
    $recent=$pdo->query('SELECT id,full_name,service_needed,status,priority,created_at FROM estimate_requests ORDER BY id DESC LIMIT 5')->fetchAll();
} else {
    $st=$pdo->prepare("SELECT COUNT(*) FROM estimate_requests WHERE assigned_to=? AND follow_up_date IS NOT NULL AND follow_up_date<=CURDATE() AND status NOT IN ('won','lost','closed')");
    $st->execute([(int)$me['id']]);
    $due=(int)$st->fetchColumn();
    $st=$pdo->prepare('SELECT id,full_name,service_needed,status,priority,created_at FROM estimate_requests WHERE assigned_to=? ORDER BY id DESC LIMIT 5');
    $st->execute([(int)$me['id']]);
    $recent=$st->fetchAll();
}
$stats=[];
if(user_can('services.manage'))$stats[]=['Active services',
$counts['services'],
'Published',
'services.php'];
if(user_can('gallery.manage'))$stats[]=['Gallery items',
$counts['gallery'],
'Project images',
'gallery.php'];
if(user_can('estimates.view'))$stats[]=['New estimates',
$counts['new'],
'Need attention',
'estimates.php'];
$stats[]=['Notifications',
$counts['notifications'],
'Unread alerts',
'notifications.php'];
$attention=[];
if(user_can('estimates.view'))$attention[]=['Follow-ups due',
$due,
$due?'Customer follow-ups need attention.':'No overdue follow-ups.',
'estimates.php?follow=due',
'mail'];
if(user_can('content.view'))$attention[]=['Unpublished content',
$counts['drafts'],
$counts['drafts']?'A landing page draft is waiting.':'Public content is up to date.',
'content.php',
'edit'];
if(user_can('content.approve'))$attention[]=['Pending approvals',
$counts['approvals'],
$counts['approvals']?'Drafts are waiting for review.':'No content awaiting approval.',
'approvals.php',
'approval'];
if(user_can('gallery.manage'))$attention[]=['Gallery images missing',
$missing,
$missing?'Replace fallback previews with project images.':'All gallery items have images.',
'gallery.php',
'image'];
if(user_can('email.manage'))$attention[]=['Email delivery',
$emailOk?'Ready':'Setup',
$emailOk?'Email delivery is configured.':'Configure SMTP or Microsoft Graph.',
'email.php',
'mail'];
$cards=[['content.view','content.php','edit','Visual content editor','Draft, preview, approval and publishing.'],
['settings.manage','appearance.php','eye','Appearance','Typography, banner, navigation and visual presets.'],
['sections.manage','sections.php','dashboard','Section manager','Show, hide and reorder landing page sections.'],
['media.manage','media.php','image','Media Library','Upload, search, preview and reuse images.'],
['services.manage','services.php','tools','Services','Add, edit, order or hide services.'],
['gallery.manage','gallery.php','image','Gallery','Manage project images and public gallery.'],
['areas.manage','areas.php','pin','Service areas','Manage cities, ZIP codes and coverage.'],
['seo.manage','seo.php','eye','SEO Manager','Search title, description and social image.'],
['health.view','health.php','gear','Website Health','Automatic readiness and configuration checks.'],
['notifications.view','notifications.php','bell','Activity Center','Notifications and change history.'],
['backups.manage','backups.php','dashboard','Backup & Restore','Create restore points before major changes.'],
['users.manage','users.php','users','Users','Create team accounts and assign roles.'],
['roles.manage','roles.php','shield','Roles & permissions','Configure access without changing code.'],
['content.approve','approvals.php','approval','Approval queue','Review and publish submitted drafts.'],
['security.manage','security.php','shield','Security Center','Active sessions and login activity.']];
$pageTitle='Dashboard';
$active='dashboard';
require __DIR__.'/_header.php';
?>

<div class="page-heading animate-in">
<div>
<p class="eyebrow">DASHBOARD</p>
<h1>Website administration</h1>
<p class="muted">Welcome, <?=h($me['full_name']?:$me['username'])?>
. Your workspace shows only the tools available to your <?=h($me['role_name']?:'assigned')?>
 role.</p>
</div>
<div class="heading-actions"><?php
if(user_can('content.view')):
?>
<a class="button" href="content.php"><?=icon('edit')?>
Page content</a><?php
endif;
?>
<a class="button secondary" href="../?preview=1" target="_blank"><?=icon('eye')?>
Preview site</a>
</div>
</div>
<div class="stat-grid"><?php
foreach($stats as $s):
?>
<a class="stat animate-in" href="<?=$s[3]?>
">
<span><?=h($s[0])?>
</span>
<strong data-stat="<?=$s[1]?>
"><?=$s[1]?>
</strong>
<small><?=h($s[2])?>
</small>
</a><?php
endforeach;
?>
</div>
<?php
if($attention):
?>
<section class="dashboard-section">
<div class="section-heading">
<div>
<p class="eyebrow">WHAT NEEDS ATTENTION</p>
<h2>Action center</h2>
</div><?php
if(user_can('health.view')):
?>
<a class="button secondary small" href="health.php">Website health</a><?php
endif;
?>
</div>
<div class="attention-grid"><?php
foreach($attention as $a):
?>
<a class="attention-card" href="<?=$a[3]?>
">
<span class="manage-icon"><?=icon($a[4])?>
</span>
<div>
<strong><?=h((string)$a[1])?>
</strong>
<b><?=h($a[0])?>
</b>
<small><?=h($a[2])?>
</small>
</div>
<i>→</i>
</a><?php
endforeach;
?>
</div>
</section><?php
endif;
?>

<section class="dashboard-section">
<div class="section-heading">
<div>
<p class="eyebrow">YOUR TOOLS</p>
<h2>Available workspace</h2>
</div>
<p>Access is automatically controlled by your role.</p>
</div>
<div class="manage-grid"><?php
foreach($cards as $c):if(!user_can($c[0]))continue;
?>
<a class="manage-card animate-in" href="<?=$c[1]?>
">
<span class="manage-icon"><?=icon($c[2])?>
</span>
<div>
<strong><?=h($c[3])?>
</strong>
<small><?=h($c[4])?>
</small>
</div>
<span class="manage-arrow">→</span>
</a><?php
endforeach;
?>
</div>
</section>
<?php
if(user_can('estimates.view')):
?>
<section class="dashboard-section">
<div class="section-heading">
<div>
<p class="eyebrow">CUSTOMER REQUESTS</p>
<h2><?=user_can('estimates.manage_all')?'Recent free estimates':'My assigned estimates'?>
</h2>
</div>
<a class="button secondary small" href="estimates.php">Open requests</a>
</div><?php
if(!$recent):
?>
<div class="empty-state">
<strong>No requests in your workspace</strong>
<p>Assigned estimate requests will appear here.</p>
</div><?php
else:
?>
<div class="request-grid"><?php
foreach($recent as $r):
?>
<article class="request-card animate-in priority-<?=h($r['priority']??'normal')?>
">
<div class="request-top">
<div>
<strong><?=h($r['full_name']?:'Website visitor')?>
</strong>
<small><?=h($r['created_at'])?>
</small>
</div>
<span class="badge <?=h($r['status'])?>
"><?=h(str_replace('_',' ',$r['status']))?>
</span>
</div>
<p><?=h($r['service_needed']?:'General project')?>
</p>
<a href="estimates.php?view=<?=$r['id']?>
">Open request →</a>
</article><?php
endforeach;
?>
</div><?php
endif;
?>
</section><?php
endif;
?>
<?php
require __DIR__.'/_footer.php';
