<?php
require __DIR__.'/bootstrap.php';
require_permission('estimates.view');
$pdo=db();
$me=current_admin();
$canAll=user_can('estimates.manage_all');
$canAssigned=user_can('estimates.manage_assigned')||$canAll;
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    if(!$canAssigned) {
        http_response_code(403);
        exit('Access denied.');
    }
    $id=(int)($_POST['id']??0);
    try {
        $st=$pdo->prepare('SELECT * FROM estimate_requests WHERE id=?');
        $st->execute([$id]);
        $cur=$st->fetch();
        if(!$cur)throw new RuntimeException('Request not found.');
        if(!$canAll&&(int)($cur['assigned_to']??0)!==(int)$me['id'])throw new RuntimeException('This request is not assigned to you.');
        $action=(string)($_POST['action']??'update');
        if($action==='update') {
            $status=(string)($_POST['status']??'new');
            $priority=(string)($_POST['priority']??'normal');
            $follow=trim((string)($_POST['follow_up_date']??''))?:null;
            $assigned=$canAll?((int)($_POST['assigned_to']??0)?:null):((int)$me['id']);
            if(!in_array($status,['new','contacted','in_progress','won','lost','closed'],true))throw new RuntimeException('Invalid status.');
            if(!in_array($priority,['low','normal','high','urgent'],true))$priority='normal';
            $pdo->prepare('UPDATE estimate_requests SET status=?,priority=?,follow_up_date=?,assigned_to=? WHERE id=?')->execute([$status,$priority,$follow,$assigned,$id]);
            log_activity('estimate_update','Updated estimate request workflow',['estimate_id'=>$id]);
            flash('success','Request updated.');
        } elseif($action==='note') {
            $note=trim((string)($_POST['note']??''));
            if($note==='')throw new RuntimeException('Write a note first.');
            $pdo->prepare('INSERT INTO estimate_notes(estimate_id,admin_id,note) VALUES(?,?,?)')->execute([$id,(int)$me['id'],$note]);
            log_activity('estimate_note','Added internal estimate note',['estimate_id'=>$id]);
            flash('success','Internal note added.');
        }
        header('Location: estimates.php?view='.$id);
        exit;
    } catch(Throwable $e) {
        $error=$e->getMessage();
    }
}
$where=[];
$args=[];
if(!$canAll) {
    $where[]='e.assigned_to=?';
    $args[]=(int)$me['id'];
}
if(!empty($_GET['status'])) {
    $where[]='e.status=?';
    $args[]=$_GET['status'];
}
if(($_GET['follow']??'')==='due') {
    $where[]='e.follow_up_date IS NOT NULL AND e.follow_up_date<=CURDATE()';
}
$sql='SELECT e.*,u.full_name assigned_name,u.username assigned_username FROM estimate_requests e LEFT JOIN admin_users u ON u.id=e.assigned_to'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY CASE e.priority WHEN "urgent" THEN 0 WHEN "high" THEN 1 WHEN "normal" THEN 2 ELSE 3 END,e.id DESC';
$st=$pdo->prepare($sql);
$st->execute($args);
$rows=$st->fetchAll();
$view=null;
$atts=[];
$notes=[];
if(isset($_GET['view'])) {
    $st=$pdo->prepare('SELECT e.*,u.full_name assigned_name,u.username assigned_username FROM estimate_requests e LEFT JOIN admin_users u ON u.id=e.assigned_to WHERE e.id=?');
    $st->execute([(int)$_GET['view']]);
    $view=$st->fetch();
    if($view&&!$canAll&&(int)($view['assigned_to']??0)!==(int)$me['id'])$view=null;
    if($view) {
        $a=$pdo->prepare('SELECT * FROM estimate_attachments WHERE estimate_id=? ORDER BY id');
        $a->execute([$view['id']]);
        $atts=$a->fetchAll();
        $n=$pdo->prepare('SELECT n.*,u.full_name,u.username FROM estimate_notes n LEFT JOIN admin_users u ON u.id=n.admin_id WHERE n.estimate_id=? ORDER BY n.id DESC');
        $n->execute([$view['id']]);
        $notes=$n->fetchAll();
    }
}
$assignees=$canAll?$pdo->query("SELECT u.id,u.full_name,u.username,r.role_name FROM admin_users u LEFT JOIN admin_roles r ON r.id=u.role_id WHERE u.active=1 ORDER BY u.full_name,u.username")->fetchAll():[];
$due=(int)$pdo->query("SELECT COUNT(*) FROM estimate_requests WHERE follow_up_date IS NOT NULL AND follow_up_date<=CURDATE() AND status NOT IN ('won','lost','closed')")->fetchColumn();
$pageTitle='Estimate Requests';
$active='estimates';
require __DIR__.'/_header.php';
?>


<div class="page-heading">
<div>
<p class="eyebrow">FREE ESTIMATES</p>
<h1>Customer requests</h1>
<p class="muted">Assign leads, track follow-ups and keep private team notes in one place.</p>
</div>
<div class="heading-actions">
<a class="button secondary" href="estimates.php">All visible</a>
<a class="button warning" href="?follow=due">Follow-ups due: <?=$due?>

</a>
</div>
</div><?php
if($error):
?>

<div class="alert error"><?=h($error)?>

</div><?php
endif;
?>
<?php
if($view):
?>

<section class="panel animate-in">
<div class="section-heading">
<div>
<p class="eyebrow">REQUEST #<?=$view['id']?>

</p>
<h2><?=h($view['full_name']?:'Website visitor')?>

</h2>
</div>
<div class="request-badges">
<span class="badge <?=h($view['priority'])?>"><?=h($view['priority'])?>

</span>
<span class="badge <?=h($view['status'])?>"><?=h(str_replace('_',' ',$view['status']))?>

</span>
</div>
</div>
<div class="three-col">
<div class="list-card">
<small>PHONE</small>
<strong><?=h($view['phone']?:'Not provided')?>

</strong>
</div>
<div class="list-card">
<small>EMAIL</small>
<strong><?=h($view['email']?:'Not provided')?>

</strong>
</div>
<div class="list-card">
<small>FOLLOW-UP</small>
<strong><?=h($view['follow_up_date']?:'Not scheduled')?>

</strong>
</div>
</div>
<div class="two-col" style="margin-top:14px">
<div class="list-card">
<small>SERVICE</small>
<strong><?=h($view['service_needed']?:'General project')?>

</strong>
<p><?=h($view['address']?:'No address provided')?>

</p>
</div>
<div class="list-card">
<small>PROJECT DETAILS</small>
<p><?=nl2br(h($view['message']?:'No additional details.'))?>

</p>
</div>
</div>
<?php
if($atts||$view['photo_path']):
?>

<div class="section-heading" style="margin-top:20px">
<div>
<h2>Attached images</h2>
</div>
</div>
<div class="gallery-editor"><?php
$all=$atts;
if(!$atts&&$view['photo_path'])$all=[['file_path'=>$view['photo_path'],'original_name'=>'Project photo']];
foreach($all as $a):
?>

<article class="gallery-item">
<div class="gallery-media">
<img src="../<?=h($a['file_path'])?>" alt="Attachment">
<button type="button" class="zoom-btn" data-preview-src="../<?=h($a['file_path'])?>" data-preview-caption="<?=h($a['original_name']??'Project image')?>"><?=icon('eye')?>

</button>
</div>
</article><?php
endforeach;
?>

</div><?php
endif;
?>
<?php
if($canAssigned):
?>

<form method="post" class="estimate-workflow">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="id" value="<?=$view['id']?>">
<input type="hidden" name="action" value="update">
<div class="four-col">
<label>Status<select name="status"><?php
foreach(['new'=>'New','contacted'=>'Contacted','in_progress'=>'In progress','won'=>'Won','lost'=>'Lost','closed'=>'Closed'] as $k=>$v):
?>

<option value="<?=$k?>" <?=$view['status']===$k?'selected':''?>

><?=$v?>

</option><?php
endforeach;
?>

</select>
</label>
<label>Priority<select name="priority"><?php
foreach(['low'=>'Low','normal'=>'Normal','high'=>'High','urgent'=>'Urgent'] as $k=>$v):
?>

<option value="<?=$k?>" <?=$view['priority']===$k?'selected':''?>

><?=$v?>

</option><?php
endforeach;
?>

</select>
</label>
<label>Follow-up date<input type="date" name="follow_up_date" value="<?=h($view['follow_up_date']??'')?>">
</label><?php
if($canAll):
?>

<label>Assigned to<select name="assigned_to">
<option value="">Unassigned</option><?php
foreach($assignees as $u):
?>

<option value="<?=$u['id']?>" <?=(int)($view['assigned_to']??0)===(int)$u['id']?'selected':''?>

><?=h($u['full_name']?:$u['username'])?>

 · <?=h($u['role_name']?:'User')?>

</option><?php
endforeach;
?>

</select>
</label><?php
else:
?>

<label>Assigned to<input value="<?=h($view['assigned_name']?:$view['assigned_username']?:'You')?>" disabled>
</label><?php
endif;
?>

</div>
<div class="form-actions">
<button>Save workflow</button>
<a class="button secondary" href="estimates.php">Back</a>
</div>
</form>
<section class="estimate-notes">
<div class="section-heading">
<div>
<h2>Internal notes</h2>
<p>Only administrator users can see these notes.</p>
</div>
</div>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="id" value="<?=$view['id']?>">
<input type="hidden" name="action" value="note">
<label>Add note<textarea name="note" required placeholder="Call outcome, next step, customer preference…">
</textarea>
</label>
<div class="form-actions">
<button>Add internal note</button>
</div>
</form>
<div class="note-timeline"><?php
foreach($notes as $n):
?>

<article>
<span>
</span>
<div>
<strong><?=h($n['full_name']?:$n['username']?:'Administrator')?>

</strong>
<p><?=nl2br(h($n['note']))?>

</p>
<small><?=h($n['created_at'])?>

</small>
</div>
</article><?php
endforeach;
?>

</div>
</section><?php
endif;
?>

</section><?php
endif;
?>


<div class="request-grid"><?php
foreach($rows as $r):
?>

<article class="request-card animate-in priority-<?=h($r['priority'])?>">
<div class="request-top">
<div>
<strong><?=h($r['full_name']?:'Website visitor')?>

</strong>
<small><?=h($r['created_at'])?>

</small>
</div>
<span class="badge <?=h($r['status'])?>"><?=h(str_replace('_',' ',$r['status']))?>

</span>
</div>
<p>
<strong><?=h($r['service_needed']?:'General project')?>

</strong>
<br><?=h($r['phone']?:$r['email']?:'No contact provided')?>

</p>
<div class="request-assignment">
<span class="badge <?=h($r['priority'])?>"><?=h($r['priority'])?>

</span>
<small>Assigned: <?=h($r['assigned_name']?:$r['assigned_username']?:'Unassigned')?>
<?php
if($r['follow_up_date']):
?>

 · Follow-up <?=h($r['follow_up_date'])?>
<?php
endif;
?>

</small>
</div>
<div class="actions">
<a class="button secondary small" href="?view=<?=$r['id']?>">Open request</a>
</div>
</article><?php
endforeach;
?>

</div><?php
if(!$rows):
?>

<div class="empty-state">
<strong>No requests match this view</strong>
<p>Assignments and permissions determine which requests are visible.</p>
</div><?php
endif;
?>
<?php
require __DIR__.'/_footer.php';
