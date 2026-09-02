<?php
require __DIR__.'/bootstrap.php';
require_permission('integrations.manage');
$pdo=db();
$error='';
$apiTypes=['payments'=>'Payments',
'banking'=>'Banking / Finance',
'crm'=>'CRM',
'accounting'=>'Accounting',
'maps'=>'Maps / Location',
'analytics'=>'Analytics',
'messaging'=>'Messaging',
'storage'=>'Storage / Files',
'custom'=>'Custom API'];
$authTypes=['api_key'=>'API Key',
'bearer'=>'Bearer Token',
'oauth2'=>'OAuth 2.0',
'basic'=>'Basic Auth',
'custom'=>'Custom / Provider specific'];
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $a=$_POST['action']??'';
    $id=(int)($_POST['id']??0);
    try {
        if($a==='save') {
            $name=trim((string)($_POST['provider_name']??''));
            if($name==='')throw new RuntimeException('Provider name is required.');
            $apiType=array_key_exists($_POST['api_type']??'custom',$apiTypes)?$_POST['api_type']:'custom';
            $category=trim((string)($_POST['category']??$apiTypes[$apiType]));
            $env=in_array($_POST['environment']??'sandbox',['sandbox','live'],true)?$_POST['environment']:'sandbox';
            $authType=array_key_exists($_POST['auth_type']??'api_key',$authTypes)?$_POST['auth_type']:'api_key';
            $base=trim((string)($_POST['base_url']??''));
            $pub=trim((string)($_POST['public_key']??''));
            $notes=trim((string)($_POST['notes']??''));
            $old=null;
            if($id) {
                $s=$pdo->prepare('SELECT * FROM api_integrations WHERE id=?');
                $s->execute([$id]);
                $old=$s->fetch();
            }
            $secret=trim((string)($_POST['secret_key']??''));
            $web=trim((string)($_POST['webhook_secret']??''));
            $secret=$secret!==''?secret_encrypt($secret):($old['secret_key']??'');
            $web=$web!==''?secret_encrypt($web):($old['webhook_secret']??'');
            $active=isset($_POST['active'])?1:0;
            if($id)$pdo->prepare('UPDATE api_integrations SET provider_name=?,api_type=?,category=?,environment=?,auth_type=?,base_url=?,public_key=?,secret_key=?,webhook_secret=?,notes=?,active=? WHERE id=?')->execute([$name,$apiType,$category,$env,$authType,$base,$pub,$secret,$web,$notes,$active,$id]);
            else $pdo->prepare('INSERT INTO api_integrations(provider_name,api_type,category,environment,auth_type,base_url,public_key,secret_key,webhook_secret,notes,active) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([$name,$apiType,$category,$env,$authType,$base,$pub,$secret,$web,$notes,$active]);
            flash('success','Integration saved.');
        } elseif($a==='delete'&&$id) {
            $pdo->prepare('DELETE FROM api_integrations WHERE id=?')->execute([$id]);
            flash('success','Integration deleted.');
        }
        header('Location: integrations.php');
        exit;
    } catch(Throwable $e) {
        $error=$e->getMessage();
    }
}
$edit=null;
if(isset($_GET['edit'])) {
    $s=$pdo->prepare('SELECT * FROM api_integrations WHERE id=?');
    $s->execute([(int)$_GET['edit']]);
    $edit=$s->fetch();
}
$rows=$pdo->query('SELECT * FROM api_integrations ORDER BY id DESC')->fetchAll();
$pageTitle='Integrations & APIs';
$active='integrations';
require __DIR__.'/_header.php';
?>


<div class="page-heading">
<div>
<p class="eyebrow">INTEGRATIONS & APIS</p>
<h1>External provider connections</h1>
<p class="muted">Choose what kind of API you are connecting first, then configure the provider credentials and environment.</p>
</div>
</div><?php
if($error):
?>

<div class="alert error"><?=h($error)?>

</div><?php
endif;
?>


<section class="panel animate-in">
<div class="panel-heading">
<div class="panel-icon"><?=icon('api')?>

</div>
<div>
<h2><?=$edit?'Edit integration':'Add integration'?>

</h2>
<p>Secrets are encrypted locally before being stored in MySQL.</p>
</div>
</div>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="save">
<input type="hidden" name="id" value="<?=h((string)($edit['id']??0))?>">
<div class="three-col">
<label>API type<select name="api_type" data-api-type><?php
foreach($apiTypes as $k=>$v):
?>

<option value="<?=h($k)?>" <?=($edit['api_type']??'custom')===$k?'selected':''?>

><?=h($v)?>

</option><?php
endforeach;
?>

</select>
</label>
<label>Provider name<input name="provider_name" required placeholder="Stripe, PayPal, HubSpot..." value="<?=h($edit['provider_name']??'')?>">
</label>
<label>Environment<select name="environment">
<option value="sandbox" <?=($edit['environment']??'sandbox')==='sandbox'?'selected':''?>

>Sandbox / Test</option>
<option value="live" <?=($edit['environment']??'')==='live'?'selected':''?>

>Live / Production</option>
</select>
</label>
</div>
<div class="two-col">
<label>Display category<input name="category" placeholder="Payments, CRM, Banking..." value="<?=h($edit['category']??'')?>">
</label>
<label>Authentication type<select name="auth_type"><?php
foreach($authTypes as $k=>$v):
?>

<option value="<?=h($k)?>" <?=($edit['auth_type']??'api_key')===$k?'selected':''?>

><?=h($v)?>

</option><?php
endforeach;
?>

</select>
</label>
</div>
<label>Base API URL<input name="base_url" placeholder="https://api.provider.com/v1" value="<?=h($edit['base_url']??'')?>">
</label>
<label>Public key / Client ID / Username<input name="public_key" value="<?=h($edit['public_key']??'')?>">
</label>
<div class="two-col">
<label>Secret key / Token / Client secret<input type="password" name="secret_key" placeholder="<?=$edit?'Leave blank to keep saved secret':'Secret credential'?>">
</label>
<label>Webhook secret<input type="password" name="webhook_secret" placeholder="<?=$edit?'Leave blank to keep saved secret':'Optional webhook secret'?>">
</label>
</div>
<label>Notes<textarea name="notes" placeholder="Scopes, webhook URL, account reference, setup notes..."><?=h($edit['notes']??'')?>

</textarea>
</label>
<label class="premium-switch">
<input type="checkbox" name="active" <?=!empty($edit['active'])?'checked':''?>

>
<span class="switch-ui" aria-hidden="true">
</span>
<span>
<b>Enable integration</b>
<small>Marks this provider as ready for use by provider-specific code.</small>
</span>
</label>
<div class="form-actions">
<button>Save integration</button><?php
if($edit):
?>

<a class="button secondary" href="integrations.php">Cancel</a><?php
endif;
?>

</div>
</form>
</section>
<?php
if(!$rows):
?>

<div class="empty-state">
<strong>No integrations yet</strong>
<p>Add a provider only when you are ready to connect it.</p>
</div><?php
else:
?>

<div class="integration-grid"><?php
foreach($rows as $r):
?>

<article class="integration-card animate-in">
<div class="list-head">
<div>
<span class="email-method"><?=h(strtoupper($r['environment']))?>

</span>
<h3><?=h($r['provider_name'])?>

</h3>
<small><?=h($apiTypes[$r['api_type']??'custom']??ucfirst((string)($r['api_type']??'custom')))?>

 · <?=h($r['auth_type']??'api_key')?>

</small>
</div>
<span class="badge <?=$r['active']?'contacted':'closed'?>"><?=$r['active']?'Enabled':'Disabled'?>

</span>
</div>
<p class="muted"><?=h($r['base_url']?:'No base URL configured yet.')?>

</p>
<div class="actions">
<a class="button secondary small" href="?edit=<?=$r['id']?>">Edit</a>
<form method="post" data-swal-confirm="Delete this integration?" data-swal-text="Stored integration settings for this provider will be removed.">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?=$r['id']?>">
<button class="button danger small">Delete</button>
</form>
</div>
</article><?php
endforeach;
?>

</div><?php
endif;
?>
<?php
require __DIR__.'/_footer.php';
