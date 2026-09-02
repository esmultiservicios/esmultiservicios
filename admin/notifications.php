<?php
require __DIR__.'/bootstrap.php';
require_permission('notifications.view');
$pdo=db();
if(isset($_GET['open'])) {
    $id=(int)$_GET['open'];
    mark_notification_read($id);
    $st=$pdo->prepare('SELECT action_url FROM admin_notifications WHERE id=?');
    $st->execute([$id]);
    $url=(string)$st->fetchColumn();
    header('Location: '.($url?:'notifications.php'));
    exit;
}
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    if(($_POST['action']??'')==='read_all')mark_all_notifications_read();
    flash('success','Notifications marked as read.');
    header('Location: notifications.php');
    exit;
}
$notes=recent_notifications(100);
$activity=user_can('activity.view')?$pdo->query('SELECT l.*,u.username FROM activity_log l LEFT JOIN admin_users u ON u.id=l.admin_id ORDER BY l.id DESC LIMIT 100')->fetchAll():[];
$pageTitle='Notifications & Activity';
$active='notifications';
require __DIR__.'/_header.php';
?>

<div class="page-heading">
<div>
<p class="eyebrow">ACTIVITY CENTER</p>
<h1>Notifications<?=user_can('activity.view')?' & change history':''?>
</h1>
<p class="muted">See what needs attention<?=user_can('activity.view')?' and review recent administrator actions':''?>
.</p>
</div>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<button class="button secondary small" name="action" value="read_all">Mark all read</button>
</form>
</div>
<div class="activity-layout <?=user_can('activity.view')?'':'single'?>
">
<section class="panel">
<div class="section-heading">
<div>
<p class="eyebrow">NOTIFICATIONS</p>
<h2>Attention center</h2>
</div>
</div>
<div class="notification-list"><?php
if(!$notes):
?>
<div class="empty-state">
<strong>All clear</strong>
<p>System notifications will appear here.</p>
</div><?php
endif;
foreach($notes as $n):
?>
<a class="notification-item <?=$n['read_by_me']?'read':''?>
" href="notifications.php?open=<?=$n['id']?>
">
<span class="notification-dot <?=h($n['notification_type'])?>
">
</span>
<div>
<strong><?=h($n['title'])?>
</strong>
<p><?=h($n['message'])?>
</p>
<small><?=h($n['created_at'])?>
</small>
</div>
</a><?php
endforeach;
?>
</div>
</section>
<?php
if(user_can('activity.view')):
?>
<section class="panel">
<div class="section-heading">
<div>
<p class="eyebrow">ACTIVITY LOG</p>
<h2>Recent changes</h2>
</div>
</div>
<div class="timeline"><?php
foreach($activity as $a):
?>
<article>
<span>
</span>
<div>
<strong><?=h($a['description'])?>
</strong>
<small><?=h($a['username']?:'System')?>
 · <?=h($a['created_at'])?>
</small>
</div>
</article><?php
endforeach;
?>
</div>
</section><?php
endif;
?>
</div>
<?php
require __DIR__.'/_footer.php';
