<?php
require __DIR__.'/bootstrap.php';
require_permission('settings.manage');
$pdo=db();
$set=settings();
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $action=$_POST['action']??'';
    try {
        if($action==='identity') {
            foreach(['admin_brand_name','company_name','phone','phone_digits','email','youtube','facebook','tiktok','website','business_hours','developer_credit_text'] as $k)save_setting($k,trim((string)($_POST[$k]??'')));
            if(!empty($_FILES['admin_logo']['name']))save_setting('admin_logo_path',upload_image($_FILES['admin_logo'],'branding','admin-logo',5));
            if(!empty($_FILES['favicon']['name']))save_setting('favicon_path',upload_image($_FILES['favicon'],'branding','favicon',3));
            if(isset($_POST['remove_favicon']))save_setting('favicon_path','');
            save_setting('developer_credit_enabled',isset($_POST['developer_credit_enabled'])?'1':'0');
            flash('success','Brand and contact settings saved.');
        } elseif($action==='maintenance') {
            save_setting('maintenance_mode',isset($_POST['maintenance_mode'])?'1':'0');
            save_setting('maintenance_title',trim((string)($_POST['maintenance_title']??'')));
            save_setting('maintenance_text',trim((string)($_POST['maintenance_text']??'')));
            if(!empty($_FILES['maintenance_image']['name']))save_setting('maintenance_image_path',upload_image($_FILES['maintenance_image'],'maintenance','maintenance',8));
            if(isset($_POST['remove_maintenance_image']))save_setting('maintenance_image_path','');
            flash('success','Website status updated.');
        } elseif($action==='whatsapp') {
            save_setting('whatsapp_enabled',isset($_POST['whatsapp_enabled'])?'1':'0');
            save_setting('whatsapp_message',trim((string)($_POST['whatsapp_message']??'')));
            save_setting('whatsapp_position',in_array($_POST['whatsapp_position']??'right',['left','right'],true)?$_POST['whatsapp_position']:'right');
            flash('success','WhatsApp widget updated.');
        }
        header('Location: settings.php'.($action==='maintenance'?'#site-status':''));
        exit;
    } catch(Throwable $e) {
        $error=$e->getMessage();
    }
}
$set=settings();
$pageTitle='Settings';
$active='settings';
require __DIR__.'/_header.php';
?>

<div class="page-heading">
<div>
<p class="eyebrow">SETTINGS</p>
<h1>Brand, contact & website status</h1>
<p class="muted">Control the admin identity and important public website behavior from one place.</p>
</div>
</div><?php
if($error):
?>
<div class="alert error"><?=h($error)?>
</div><?php
endif;
?>

<div class="content-grid">
<section class="panel animate-in">
<div class="panel-heading">
<div class="panel-icon"><?=icon('image')?>
</div>
<div>
<h2>Admin branding</h2>
<p>Change the name and logo displayed throughout this administrator.</p>
</div>
</div>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<input type="hidden" name="action" value="identity">
<label>Admin name<input name="admin_brand_name" value="<?=h($set['admin_brand_name']??"ES CMS Core Admin")?>
">
</label>
<label>Company / website name<input name="company_name" value="<?=h($set['company_name']??'Your Company')?>
">
</label>
<div class="branding-upload-grid">
<div class="upload-zone" data-upload-zone tabindex="0">
<div class="upload-icon"><?=icon('image')?>
</div>
<strong>Admin logo</strong>
<small data-upload-name>Drag & drop, paste, or choose image</small>
<input type="file" name="admin_logo" accept="image/jpeg,image/png,image/webp">
<div class="upload-preview" data-upload-preview>
</div>
</div>
<div class="upload-zone favicon-zone" data-upload-zone tabindex="0">
<div class="upload-icon"><?=icon('image')?>
</div>
<strong>Browser tab icon (favicon)</strong>
<small data-upload-name>Use a square PNG, JPG or WebP · drag, paste, or choose</small>
<input type="file" name="favicon" accept="image/jpeg,image/png,image/webp">
<div class="upload-preview" data-upload-preview>
</div>
</div>
</div><?php
if(!empty($set['favicon_path'])):
?>
<div class="saved-favicon">
<img src="../<?=h($set['favicon_path'])?>
" alt="Current favicon">
<div>
<strong>Current browser tab icon</strong>
<small><?=h($set['favicon_path'])?>
</small>
</div>
<label class="premium-check">
<input type="checkbox" name="remove_favicon" value="1">
<span>Remove favicon</span>
</label>
</div><?php
endif;
?>
<div class="two-col">
<label>Phone<input name="phone" value="<?=h($set['phone']??'')?>
">
</label>
<label>Phone digits<input name="phone_digits" value="<?=h($set['phone_digits']??'')?>
">
</label>
</div>
<label>Public email<input type="email" name="email" value="<?=h($set['email']??'')?>
">
</label>
<div class="two-col">
<label>YouTube<input name="youtube" value="<?=h($set['youtube']??'')?>
">
</label>
<label>Facebook<input name="facebook" value="<?=h($set['facebook']??'')?>
">
</label>
</div>
<div class="two-col">
<label>TikTok<input name="tiktok" value="<?=h($set['tiktok']??'')?>
">
</label>
<label>Website<input name="website" value="<?=h($set['website']??'')?>
">
</label>
</div>
<label>Business hours<textarea name="business_hours"><?=h($set['business_hours']??'')?>
</textarea>
</label>
<label class="premium-switch">
<input type="checkbox" name="developer_credit_enabled" <?=($set['developer_credit_enabled']??'0')==='1'?'checked':''?>
>
<span class="switch-ui">
</span>
<span>
<b>Developer credit in footer</b>
<small>Optional. Enable only if the client agrees to show a discreet ES MULTISERVICIOS credit.</small>
</span>
</label>
<label>Developer credit text<input name="developer_credit_text" value="<?=h($set['developer_credit_text']??'Website by ES MULTISERVICIOS')?>
">
</label>
<div class="form-actions">
<button>Save identity & contact</button>
</div>
</form>
</section>
<section class="panel animate-in" id="site-status">
<div class="panel-heading">
<div class="panel-icon"><?=icon('eye')?>
</div>
<div>
<h2>Website status</h2>
<p>Temporarily replace the public site with a polished maintenance screen while you make changes.</p>
</div>
</div>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<input type="hidden" name="action" value="maintenance">
<label class="status-switch premium-switch">
<input type="checkbox" name="maintenance_mode" <?=($set['maintenance_mode']??'0')==='1'?'checked':''?>
>
<span class="switch-ui" aria-hidden="true">
</span>
<span>
<b>Maintenance mode</b>
<small>Visitors will see the maintenance screen when enabled.</small>
</span>
</label>
<label>Maintenance title<input name="maintenance_title" value="<?=h($set['maintenance_title']??'')?>
">
</label>
<label>Maintenance message<textarea name="maintenance_text"><?=h($set['maintenance_text']??'')?>
</textarea>
</label>
<div class="upload-zone" data-upload-zone tabindex="0">
<div class="upload-icon"><?=icon('image')?>
</div>
<strong>Maintenance image</strong>
<small data-upload-name>Drag & drop, paste, or choose an optional image</small>
<input type="file" name="maintenance_image" accept="image/jpeg,image/png,image/webp">
<div class="upload-preview" data-upload-preview>
</div>
</div><?php
if(!empty($set['maintenance_image_path'])):
?>
<div class="saved-image-row">
<img src="../<?=h($set['maintenance_image_path'])?>
" alt="Maintenance preview">
<div>
<strong>Current maintenance image</strong>
<small><?=h($set['maintenance_image_path'])?>
</small>
</div>
<label class="compact-check">
<input type="checkbox" name="remove_maintenance_image" value="1"> Remove image</label>
</div><?php
endif;
?>
<p class="secret-hint">When maintenance is active, opening the normal website shows the maintenance screen even if you are logged in. Use the preview button below to inspect the real site privately.</p>
<div class="form-actions">
<button>Save website status</button>
<a class="button secondary" href="../?preview=1" target="_blank" rel="noopener">Preview real site</a>
<a class="button ghost" href="../" target="_blank" rel="noopener">View public status</a>
</div>
</form>
</section>
<section class="panel wide animate-in">
<div class="panel-heading">
<div class="panel-icon">💬</div>
<div>
<h2>Floating WhatsApp contact</h2>
<p>A discreet floating contact button that respects the page content on desktop and mobile.</p>
</div>
</div>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<input type="hidden" name="action" value="whatsapp">
<label class="premium-switch">
<input type="checkbox" name="whatsapp_enabled" <?=($set['whatsapp_enabled']??'1')==='1'?'checked':''?>
>
<span class="switch-ui" aria-hidden="true">
</span>
<span>
<b>Floating WhatsApp</b>
<small>Show a compact WhatsApp contact button on the public website.</small>
</span>
</label>
<div class="two-col">
<label>Default message<textarea name="whatsapp_message"><?=h($set['whatsapp_message']??'')?>
</textarea>
</label>
<label>Position<select name="whatsapp_position">
<option value="right" <?=($set['whatsapp_position']??'right')==='right'?'selected':''?>
>Bottom right</option>
<option value="left" <?=($set['whatsapp_position']??'right')==='left'?'selected':''?>
>Bottom left</option>
</select>
</label>
</div>
<div class="form-actions">
<button>Save WhatsApp widget</button>
</div>
</form>
</section>
</div><?php
require __DIR__.'/_footer.php';
