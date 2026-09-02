<?php
require __DIR__.'/bootstrap.php';
require_permission('email.manage');
require_once __DIR__.'/../core/EmailService.php';
$pdo=db();
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $action=$_POST['action']??'';
    $id=(int)($_POST['id']??0);
    try {
        if($action==='save') {
            $type=(int)($_POST['correo_tipo_id']??1);
            $method=in_array($_POST['metodo_envio']??'SMTP',['SMTP','GRAPH'],true)?$_POST['metodo_envio']:'SMTP';
            $server=trim((string)($_POST['server']??''));
            $email=trim((string)($_POST['correo']??''));
            $port=(int)($_POST['port']??587);
            $secure=trim((string)($_POST['smtp_secure']??'tls'));
            $tenant=trim((string)($_POST['tenant_id']??''));
            $client=trim((string)($_POST['client_id']??''));
            $graph=trim((string)($_POST['graph_user']??''));
            $sent=isset($_POST['save_to_sent_items'])?1:0;
            $estado=isset($_POST['estado'])?1:2;
            if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid sender email.');
            $old=$id?(new EmailService())->configById($id):null;
            $pass=trim((string)($_POST['password']??''));
            $secret=trim((string)($_POST['client_secret']??''));
            $passEnc=$pass!==''?secret_encrypt($pass):($old['password']??'');
            $secretEnc=$secret!==''?secret_encrypt($secret):($old['client_secret']??'');
            if($method==='GRAPH') {
                $server='graph.microsoft.com';
                $port=0;
                $secure='';
            }
            if($id) {
                $st=$pdo->prepare('UPDATE correo SET correo_tipo_id=?,metodo_envio=?,server=?,correo=?,password=?,port=?,smtp_secure=?,tenant_id=?,client_id=?,client_secret=?,graph_user=?,save_to_sent_items=?,estado=? WHERE correo_id=?');
                $st->execute([$type,$method,$server,$email,$passEnc,$port,$secure,$tenant?:null,$client?:null,$secretEnc?:null,$graph?:null,$sent,$estado,$id]);
                $savedId=$id;
            } else {
                $st=$pdo->prepare('INSERT INTO correo(correo_tipo_id,metodo_envio,server,correo,password,port,smtp_secure,tenant_id,client_id,client_secret,graph_user,save_to_sent_items,estado,fecha_registro) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
                $st->execute([$type,$method,$server,$email,$passEnc,$port,$secure,$tenant?:null,$client?:null,$secretEnc?:null,$graph?:null,$sent,$estado]);
                $savedId=(int)$pdo->lastInsertId();
            }
            if($estado===1) {
                $off=$pdo->prepare('UPDATE correo SET estado=2 WHERE correo_tipo_id=? AND correo_id<>?');
                $off->execute([$type,$savedId]);
            }
            flash('success','Email configuration saved.');
        } elseif($action==='delete'&&$id) {
            $pdo->prepare('DELETE FROM correo WHERE correo_id=?')->execute([$id]);
            flash('success','Email configuration deleted.');
        } elseif($action==='toggle'&&$id) {
            $pdo->prepare('UPDATE correo SET estado=IF(estado=1,2,1) WHERE correo_id=?')->execute([$id]);
            flash('success','Email status updated.');
        } elseif($action==='test'&&$id) {
            $res=(new EmailService())->test($id,trim((string)($_POST['test_to']??'')));
            flash($res['success']?'success':'error',$res['message']);
        }
        header('Location: email.php'.($id?'?edit='.$id:''));
        exit;
    } catch(Throwable $e) {
        $error=$e->getMessage();
    }
}
$types=$pdo->query('SELECT * FROM correo_tipo ORDER BY correo_tipo_id')->fetchAll();
$rows=$pdo->query('SELECT c.*,t.nombre tipo_nombre FROM correo c JOIN correo_tipo t ON t.correo_tipo_id=c.correo_tipo_id ORDER BY c.correo_id DESC')->fetchAll();
$edit=null;
if(isset($_GET['edit'])) {
    $edit=(new EmailService())->configById((int)$_GET['edit']);
}
$pageTitle='Email Configuration';
$active='email';
require __DIR__.'/_header.php';
?>

<div class="page-heading">
<div>
<p class="eyebrow">EMAIL</p>
<h1>SMTP & Microsoft Graph</h1>
<p class="muted">Configure purpose-specific senders for website alerts, administrator security, estimate requests and automatic customer replies.</p>
</div>
<a class="button secondary" href="email.php">New configuration</a>
</div><?php
if($error):
?>
<div class="alert error"><?=h($error)?>
</div><?php
endif;
?>

<section class="panel animate-in">
<div class="panel-heading">
<div class="panel-icon"><?=icon('mail')?>
</div>
<div>
<h2><?=$edit?'Edit configuration':'Add email configuration'?>
</h2>
<p>Secrets are encrypted before they are stored in MySQL.</p>
</div>
</div>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<input type="hidden" name="action" value="save">
<input type="hidden" name="id" value="<?=h((string)($edit['correo_id']??0))?>
">
<div class="three-col">
<label>Email purpose<select name="correo_tipo_id"><?php
foreach($types as $t):
?>
<option value="<?=$t['correo_tipo_id']?>
" <?=($edit['correo_tipo_id']??1)==$t['correo_tipo_id']?'selected':''?>
><?=h($t['nombre'])?>
</option><?php
endforeach;
?>
</select>
</label>
<label>Method<select name="metodo_envio" data-method-select>
<option value="SMTP" <?=($edit['metodo_envio']??'SMTP')==='SMTP'?'selected':''?>
>SMTP</option>
<option value="GRAPH" <?=($edit['metodo_envio']??'')==='GRAPH'?'selected':''?>
>Microsoft Graph</option>
</select>
</label>
<label class="premium-switch">
<input type="checkbox" name="estado" <?=!$edit||($edit['estado']??1)==1?'checked':''?>
>
<span class="switch-ui" aria-hidden="true">
</span>
<span>
<b>Active configuration</b>
<small>Use this sender for the selected email purpose.</small>
</span>
</label>
</div>
<label>Sender email<input type="email" name="correo" required value="<?=h($edit['correo']??'')?>
">
</label>
<div data-method="SMTP">
<div class="three-col">
<label>SMTP server<input name="server" value="<?=h(($edit['metodo_envio']??'SMTP')==='SMTP'?($edit['server']??''):'')?>
">
</label>
<label>Port<input type="number" name="port" value="<?=h((string)($edit['port']??587))?>
">
</label>
<label>Security<select name="smtp_secure">
<option value="tls" <?=($edit['smtp_secure']??'tls')==='tls'?'selected':''?>
>TLS</option>
<option value="ssl" <?=($edit['smtp_secure']??'')==='ssl'?'selected':''?>
>SSL</option>
</select>
</label>
</div>
<label>SMTP password<input type="password" name="password" autocomplete="new-password" placeholder="<?=$edit?'Leave blank to keep saved password':'SMTP password'?>
">
</label>
</div>
<div data-method="GRAPH">
<div class="two-col">
<label>Tenant ID<input name="tenant_id" value="<?=h($edit['tenant_id']??'')?>
">
</label>
<label>Client ID<input name="client_id" value="<?=h($edit['client_id']??'')?>
">
</label>
</div>
<label>Client Secret VALUE<input type="password" name="client_secret" placeholder="<?=$edit?'Leave blank to keep saved secret':'Microsoft Entra client secret'?>
">
</label>
<label>Graph User / mailbox<input type="email" name="graph_user" value="<?=h($edit['graph_user']??'')?>
">
</label>
<label class="premium-switch">
<input type="checkbox" name="save_to_sent_items" <?=!$edit||!empty($edit['save_to_sent_items'])?'checked':''?>
>
<span class="switch-ui" aria-hidden="true">
</span>
<span>
<b>Save a copy in Sent Items</b>
<small>Microsoft Graph will keep a copy in the sender mailbox.</small>
</span>
</label>
</div>
<div class="form-actions">
<button>Save email configuration</button><?php
if($edit):
?>
<a class="button secondary" href="email.php">Cancel edit</a><?php
endif;
?>
</div>
</form>
</section>
<?php
if($edit):
?>
<section class="panel email-test-panel animate-in">
<div class="panel-heading">
<div class="panel-icon"><?=icon('mail')?>
</div>
<div>
<h2>Test this configuration</h2>
<p>Send a real test message before using this sender on the website.</p>
</div>
</div>
<form method="post" class="test-email-form">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<input type="hidden" name="action" value="test">
<input type="hidden" name="id" value="<?=$edit['correo_id']?>
">
<label>Test destination email<input type="email" name="test_to" required placeholder="you@example.com" value="<?=h($edit['correo']??'')?>
">
</label>
<div class="form-actions">
<button class="button">Send test email</button>
</div>
</form>
</section><?php
else:
?>
<div class="info-strip">
<strong>Want to test it?</strong>
<span>Save the configuration first. The test tool will appear here immediately after saving.</span>
</div><?php
endif;
?>

<div class="section-heading">
<div>
<p class="eyebrow">CONFIGURED SENDERS</p>
<h2>Email connections</h2>
</div>
</div><?php
if(!$rows):
?>
<div class="empty-state">
<strong>No email configured</strong>
<p>Add SMTP or Microsoft Graph above.</p>
</div><?php
else:
?>
<div class="email-grid"><?php
foreach($rows as $r):
?>
<article class="email-card animate-in">
<div class="list-head">
<div>
<span class="email-method"><?=h($r['metodo_envio'])?>
</span>
<h3><?=h($r['tipo_nombre'])?>
</h3>
<small><?=h($r['correo'])?>
</small>
</div>
<span class="badge <?=$r['estado']==1?'contacted':'closed'?>
"><?=$r['estado']==1?'Active':'Inactive'?>
</span>
</div>
<p class="muted"><?=$r['metodo_envio']==='GRAPH'?'Microsoft 365 / Graph mailbox: '.h($r['graph_user']?:$r['correo']):'SMTP: '.h($r['server']).':'.(int)$r['port']?>
</p>
<div class="actions">
<form method="post" class="actions">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<input type="hidden" name="action" value="test">
<input type="hidden" name="id" value="<?=$r['correo_id']?>
">
<input style="max-width:230px" type="email" name="test_to" placeholder="Test destination">
<button class="button small">Send test</button>
</form>
<details class="action-menu">
<summary>Actions ▾</summary>
<nav>
<a href="?edit=<?=$r['correo_id']?>
">Edit</a>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<input type="hidden" name="action" value="toggle">
<input type="hidden" name="id" value="<?=$r['correo_id']?>
">
<button><?=$r['estado']==1?'Deactivate':'Activate'?>
</button>
</form>
<form method="post" data-swal-confirm="Delete this email configuration?" data-swal-text="Email delivery using this configuration will stop.">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?=$r['correo_id']?>
">
<button class="danger-text">Delete</button>
</form>
</nav>
</details>
</div>
</article><?php
endforeach;
?>
</div><?php
endif;
?>
<?php
require __DIR__.'/_footer.php';
