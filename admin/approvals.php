<?php
require __DIR__.'/bootstrap.php';
require_permission('content.approve');
$pdo=db();
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $id=(int)($_POST['id']??0);
    $action=(string)($_POST['action']??'');
    $note=trim((string)($_POST['reviewer_note']??''));
    try {
        $st=$pdo->prepare("SELECT * FROM content_approvals WHERE id=? AND status='pending'");
        $st->execute([$id]);
        $approval=$st->fetch();
        if(!$approval)throw new RuntimeException('This approval request is no longer pending.');
        if($action==='approve') {
            $draft=draft_content();
            if(!$draft)throw new RuntimeException('There is no draft to publish.');
            $live=site_content();
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO content_versions(snapshot_json,note,created_by) VALUES(?,?,?)')->execute([json_encode($live,JSON_UNESCAPED_UNICODE),'Automatic version before approved publish',(int)$_SESSION['escms_admin_id']]);
            $up=$pdo->prepare('INSERT INTO site_content(content_key,content_value) VALUES(?,?) ON DUPLICATE KEY UPDATE content_value=VALUES(content_value)');
            foreach($draft as $k=>$v)$up->execute([$k,$v]);
            $pdo->exec('DELETE FROM content_drafts');
            $pdo->prepare("UPDATE content_approvals SET status='approved',reviewer_note=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?")->execute([$note,(int)$_SESSION['escms_admin_id'],$id]);
            $pdo->commit();
            log_activity('content_approve','Approved and published submitted content',['approval_id'=>$id]);
            admin_notify('success','Content approved','Submitted website changes were approved and published.','content.php');
            flash('success','Changes approved and published.');
        } elseif($action==='changes') {
            $pdo->prepare("UPDATE content_approvals SET status='changes_requested',reviewer_note=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?")->execute([$note,(int)$_SESSION['escms_admin_id'],$id]);
            log_activity('content_changes_requested','Requested changes to submitted content',['approval_id'=>$id]);
            admin_notify('warning','Changes requested','A reviewer requested changes to the current website draft.','content.php');
            flash('success','Changes requested. The draft remains available for editing.');
        }
        header('Location: approvals.php');
        exit;
    } catch(Throwable $e) {
        if($pdo->inTransaction())$pdo->rollBack();
        $error=$e->getMessage();
    }
}
$rows=$pdo->query("SELECT a.*,u.full_name,u.username,rv.full_name reviewer_name FROM content_approvals a LEFT JOIN admin_users u ON u.id=a.submitted_by LEFT JOIN admin_users rv ON rv.id=a.reviewed_by ORDER BY FIELD(a.status,'pending','changes_requested','approved','cancelled'),a.id DESC LIMIT 100")->fetchAll();
$pageTitle='Approval Queue';
$active='approvals';
require __DIR__.'/_header.php';
?>


<div class="page-heading">
<div>
<p class="eyebrow">PUBLISHING WORKFLOW</p>
<h1>Approval queue</h1>
<p class="muted">Review submitted landing-page drafts before they become public.</p>
</div>
<a class="button secondary" href="../?preview=1&draft=1" target="_blank">Preview current draft</a>
</div><?php
if($error):
?>

<div class="alert error"><?=h($error)?>

</div><?php
endif;
?>


<div class="approval-list"><?php
foreach($rows as $a):
?>

<article class="approval-card <?=h($a['status'])?>">
<div class="approval-icon"><?=icon('approval')?>

</div>
<div>
<div class="approval-title">
<strong><?=h(ucwords(str_replace('_',' ',$a['status'])))?>

</strong>
<span>#<?=$a['id']?>

</span>
</div>
<p>Submitted by <b><?=h($a['full_name']?:$a['username']?:'Unknown user')?>

</b> · <?=h($a['submitted_at'])?>

</p><?php
if($a['note']):
?>

<blockquote><?=nl2br(h($a['note']))?>

</blockquote><?php
endif;
?>
<?php
if($a['reviewer_note']):
?>

<small>Reviewer note: <?=h($a['reviewer_note'])?>

</small><?php
endif;
?>

</div><?php
if($a['status']==='pending'):
?>

<form method="post" class="approval-actions">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="id" value="<?=$a['id']?>">
<label>Reviewer note<textarea name="reviewer_note" placeholder="Optional note">
</textarea>
</label>
<div class="actions">
<button name="action" value="approve">Approve & publish</button>
<button class="button warning" name="action" value="changes">Request changes</button>
</div>
</form><?php
endif;
?>

</article><?php
endforeach;
?>

</div><?php
if(!$rows):
?>

<div class="empty-state">
<strong>No approval requests yet</strong>
<p>Editors can submit drafts here when they are ready for review.</p>
</div><?php
endif;
?>
<?php
require __DIR__.'/_footer.php';
