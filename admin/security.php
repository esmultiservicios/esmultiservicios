<?php
require __DIR__.'/bootstrap.php';
require_permission('security.manage');
$pdo=db();
$me=current_admin();
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $action=(string)($_POST['action']??'');
    try {
        if($action==='revoke') {
            $id=(int)($_POST['session_id']??0);
            $st=$pdo->prepare('SELECT s.admin_id,s.session_hash,r.role_key FROM admin_sessions s JOIN admin_users u ON u.id=s.admin_id LEFT JOIN admin_roles r ON r.id=u.role_id WHERE s.id=?');
            $st->execute([$id]);
            $sess=$st->fetch();
            if(!$sess)throw new RuntimeException('Session not found.');
            if(($sess['role_key']??'')==='owner'&&!role_is_owner($me))throw new RuntimeException('Only the Owner can revoke an Owner session.');
            $pdo->prepare('UPDATE admin_sessions SET revoked_at=NOW() WHERE id=?')->execute([$id]);
            log_activity('session_revoke','Revoked an administrator session',['session_id'=>$id]);
            if(hash_equals($sess['session_hash'],session_fingerprint())) {
                clear_remember_cookie();
                $_SESSION=[];
                session_destroy();
                header('Location: login.php?revoked=1');
                exit;
            }
            flash('success','Session signed out.');
        } elseif($action==='revoke_others') {
            $pdo->prepare('UPDATE admin_sessions SET revoked_at=NOW() WHERE admin_id=? AND session_hash<>? AND revoked_at IS NULL')->execute([(int)$me['id'],session_fingerprint()]);
            flash('success','All your other sessions were signed out.');
        } elseif($action==='cleanup') {
            $pdo->exec('DELETE FROM admin_sessions WHERE last_seen_at < DATE_SUB(NOW(),INTERVAL 60 DAY) OR revoked_at IS NOT NULL');
            flash('success','Old session records cleaned up.');
        }
        header('Location: security.php');
        exit;
    } catch(Throwable $e) {
        $error=$e->getMessage();
    }
}
$sessions=$pdo->query('SELECT s.*,u.username,u.full_name,r.role_key,r.role_name FROM admin_sessions s JOIN admin_users u ON u.id=s.admin_id LEFT JOIN admin_roles r ON r.id=u.role_id ORDER BY (s.revoked_at IS NULL) DESC,s.last_seen_at DESC LIMIT 100')->fetchAll();
$events=$pdo->query('SELECT e.*,u.username FROM admin_login_events e LEFT JOIN admin_users u ON u.id=e.admin_id ORDER BY e.id DESC LIMIT 80')->fetchAll();
$pageTitle='Security Center';
$active='security';
require __DIR__.'/_header.php';
?>


<div class="page-heading">
<div>
<p class="eyebrow">SECURITY CENTER</p>
<h1>Sessions & login activity</h1>
<p class="muted">See where administrator accounts are signed in and immediately revoke access when needed.</p>
</div>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<button class="button secondary" name="action" value="revoke_others">Sign out my other sessions</button>
</form>
</div><?php
if($error):
?>

<div class="alert error"><?=h($error)?>

</div><?php
endif;
?>


<div class="security-layout">
<section class="panel">
<div class="section-heading">
<div>
<p class="eyebrow">ACTIVE SESSIONS</p>
<h2>Administrator devices</h2>
</div>
</div>
<div class="session-list"><?php
foreach($sessions as $s):
?>

<article class="session-card <?=$s['revoked_at']?'revoked':''?>">
<span class="session-device"><?=icon('shield')?>

</span>
<div>
<strong><?=h($s['full_name']?:$s['username'])?>

</strong>
<small><?=h($s['role_name']?:'User')?>

 · <?=h($s['ip_address']?:'Unknown IP')?>

 · <?=h($s['last_seen_at'])?>

</small>
<p><?=h($s['user_agent']?:'Unknown browser/device')?>

</p>
</div>
<div><?php
if(!$s['revoked_at']):
?>

<span class="badge success">Active</span>
<form method="post" data-swal-confirm="Sign out this session?" data-swal-text="The user will need to sign in again on this device.">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="revoke">
<input type="hidden" name="session_id" value="<?=$s['id']?>">
<button class="button danger-lite small">Sign out</button>
</form><?php
else:
?>

<span class="badge closed">Revoked</span><?php
endif;
?>

</div>
</article><?php
endforeach;
?>

</div>
</section>
<section class="panel">
<div class="section-heading">
<div>
<p class="eyebrow">LOGIN HISTORY</p>
<h2>Recent sign-in attempts</h2>
</div>
</div>
<div class="login-event-list"><?php
foreach($events as $e):
?>

<article>
<span class="login-event-dot <?=$e['success']?'success':'error'?>">
</span>
<div>
<strong><?=h($e['username']?:$e['username_attempt']?:'Unknown')?>

</strong>
<small><?=h($e['created_at'])?>

 · <?=h($e['ip_address']?:'Unknown IP')?>

</small>
</div>
<span class="badge <?=$e['success']?'success':'closed'?>"><?=$e['success']?'Successful':'Failed'?>

</span>
</article><?php
endforeach;
?>

</div>
</section>
</div>
<?php
require __DIR__.'/_footer.php';
