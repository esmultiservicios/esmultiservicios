<?php
require __DIR__.'/bootstrap.php';
require_permission('seo.manage');
$set=settings();
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    try {
        foreach(['seo_title','seo_description','seo_robots','google_site_verification'] as $k)save_setting($k,trim((string)($_POST[$k]??'')));
        if(!empty($_FILES['seo_social_image']['name'])) {
            $p=upload_image($_FILES['seo_social_image'],'seo','social',8);
            save_setting('seo_social_image',$p);
            media_add($p,'SEO social image');
        }
        if(isset($_POST['remove_social']))save_setting('seo_social_image','');
        log_activity('seo_update','Updated SEO settings');
        flash('success','SEO settings saved.');
        header('Location: seo.php');
        exit;
    } catch(Throwable $e) {
        $error=$e->getMessage();
    }
}
$set=settings();
$pageTitle='SEO Manager';
$active='seo';
require __DIR__.'/_header.php';
?>
<div class="page-heading">
<div>
<p class="eyebrow">SEO MANAGER</p>
<h1>Search & social appearance</h1>
<p class="muted">Manage search metadata, indexing, Google verification and social sharing.</p>
</div>
</div><?php
if($error):
?>
<div class="alert error"><?=h($error)?>
</div><?php
endif;
?>
<div class="seo-layout">
<section class="panel">
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<label>Browser / search title<input name="seo_title" maxlength="70" value="<?=h($set['seo_title']??'')?>">
</label>
<label>Meta description<textarea name="seo_description" maxlength="180"><?=h($set['seo_description']??'')?>
</textarea>
</label>
<label>Robots<select name="seo_robots">
<option value="index,follow" <?=($set['seo_robots']??'')==='index,follow'?'selected':''?>
>Index & follow</option>
<option value="noindex,nofollow" <?=($set['seo_robots']??'')==='noindex,nofollow'?'selected':''?>
>Hide from search engines</option>
</select>
</label>
<label>Google Search Console verification
<input name="google_site_verification" maxlength="255" value="<?=h($set['google_site_verification']??'')?>" placeholder="Paste only the verification token">
<small class="muted">Optional. Use this for the HTML tag verification method. For a Domain property, Google normally verifies with a DNS TXT record instead.</small>
</label>
<div class="upload-zone" data-upload-zone tabindex="0">
<div class="upload-icon"><?=icon('image')?>
</div>
<strong>Social sharing image</strong>
<small data-upload-name>Drag, paste or choose</small>
<input type="file" name="seo_social_image" accept="image/jpeg,image/png,image/webp">
<div class="upload-preview" data-upload-preview>
</div>
</div>
<div class="form-actions">
<button>Save SEO settings</button>
</div>
</form>
</section>
<aside class="search-preview">
<span>Search preview</span>
<div class="search-domain"><?=h(preg_replace('~^https?://~i','',(string)($set['website']??'esmultiservicios.com')))?></div>
<h3><?=h($set['seo_title']??"Your Company | Professional Services")?>
</h3>
<p><?=h($set['seo_description']??'Professional services tailored to your customers.')?>
</p>
</aside>
</div><?php
require __DIR__.'/_footer.php';
