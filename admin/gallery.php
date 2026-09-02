<?php
require __DIR__.'/bootstrap.php';
require_permission('gallery.manage');
$pdo=db();
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $a=$_POST['action']??'';
    $id=(int)($_POST['id']??0);
    try {
        if($a==='save') {
            $title=trim((string)($_POST['title']??''));
            $sort=(int)($_POST['sort_order']??0);
            $active=isset($_POST['active'])?1:0;
            $path=trim((string)($_POST['existing_image']??''));
            if(!empty($_FILES['image']['name'])) {
                $path=upload_image($_FILES['image'],'gallery','project',8);
                media_add($path,$title?:'Gallery image');
            } elseif(!empty($_POST['media_path'])) {
                $path=trim((string)$_POST['media_path']);
            }
            if($title==='')throw new RuntimeException('Title is required.');
            if($id)$pdo->prepare('UPDATE gallery SET title=?,image_path=?,sort_order=?,active=? WHERE id=?')->execute([$title,$path,$sort,$active,$id]);
            else $pdo->prepare('INSERT INTO gallery(title,image_path,sort_order,active) VALUES(?,?,?,?)')->execute([$title,$path,$sort,$active]);
            flash('success','Gallery item saved.');
        } elseif($a==='delete'&&$id) {
            $pdo->prepare('DELETE FROM gallery WHERE id=?')->execute([$id]);
            flash('success','Gallery item deleted.');
        }
        header('Location: gallery.php');
        exit;
    } catch(Throwable $e) {
        $error=$e->getMessage();
    }
}
$edit=null;
if(isset($_GET['edit'])) {
    $s=$pdo->prepare('SELECT * FROM gallery WHERE id=?');
    $s->execute([(int)$_GET['edit']]);
    $edit=$s->fetch();
}
$rows=$pdo->query('SELECT * FROM gallery ORDER BY sort_order,id')->fetchAll();
$pageTitle='Gallery';
$active='gallery';
require __DIR__.'/_header.php';
?>

<div class="page-heading">
<div>
<p class="eyebrow">GALLERY</p>
<h1>Project image manager</h1>
<p class="muted">Drop, paste or choose an image, preview it before saving, and open saved images at full size.</p>
</div>
</div><?php
if($error):
?>
<div class="alert error"><?=h($error)?>
</div><?php
endif;
?>
<section class="panel">
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<input type="hidden" name="action" value="save">
<input type="hidden" name="id" value="<?=h((string)($edit['id']??0))?>
">
<input type="hidden" name="existing_image" value="<?=h($edit['image_path']??'')?>
">
<div class="two-col">
<label>Title<input name="title" required value="<?=h($edit['title']??'')?>
">
</label>
<label>Order<input type="number" name="sort_order" value="<?=h((string)($edit['sort_order']??0))?>
">
</label>
</div>
<label>Or reuse image from Media Library<select name="media_path">
<option value="">Keep current / upload new</option><?php
foreach($pdo->query("SELECT file_path,title FROM media_library ORDER BY id DESC LIMIT 100")->fetchAll() as $m):
?>
<option value="<?=h($m['file_path'])?>
"><?=h($m['title']?:basename($m['file_path']))?>
</option><?php
endforeach;
?>
</select>
</label>
<div class="upload-zone" data-upload-zone tabindex="0">
<div class="upload-icon"><?=icon('image')?>
</div>
<strong>Project image</strong>
<small data-upload-name>Drop, paste or choose image</small>
<input type="file" name="image" accept="image/jpeg,image/png,image/webp">
<div class="upload-preview" data-upload-preview>
</div>
</div><?php
if($edit&&$edit['image_path']):
?>
<button type="button" class="button secondary small" data-preview-src="../<?=h($edit['image_path'])?>
" data-preview-caption="<?=h($edit['title'])?>
">Preview current image</button><?php
endif;
?>
<label class="premium-switch">
<input type="checkbox" name="active" <?=!$edit||!empty($edit['active'])?'checked':''?>
>
<span class="switch-ui" aria-hidden="true">
</span>
<span>
<b>Publish image</b>
<small>Show this project image on the public gallery.</small>
</span>
</label>
<div class="form-actions">
<button>Save gallery item</button><?php
if($edit):
?>
<a class="button secondary" href="gallery.php">Cancel</a><?php
endif;
?>
</div>
</form>
</section>
<div class="gallery-editor"><?php
foreach($rows as $i=>$r):$displayImage=$r['image_path']?'../'.$r['image_path']:gallery_fallback($i);
?>
<article class="gallery-item animate-in">
<div class="gallery-media">
<img src="<?=h($displayImage)?>
" alt="<?=h($r['title'])?>
">
<button type="button" class="zoom-btn" data-preview-src="<?=h($displayImage)?>
" data-preview-caption="<?=h($r['title'])?>
" aria-label="View large"><?=icon('eye')?>
</button><?php
if(!$r['image_path']):
?>
<span class="gallery-source-badge">Website preview image</span><?php
endif;
?>
</div>
<footer>
<div class="gallery-meta">
<strong><?=h($r['title'])?>
</strong>
<small>
<span>Order <?=$r['sort_order']?>
</span>
<span class="meta-dot">•</span>
<span><?=$r['active']?'Published':'Hidden'?>
</span>
</small>
</div>
<details class="action-menu">
<summary>Actions ▾</summary>
<nav>
<a href="?edit=<?=$r['id']?>
">Edit</a>
<form method="post" data-swal-confirm="Delete this image?" data-swal-text="The gallery record will be removed.">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?=$r['id']?>
">
<button class="danger-text">Delete</button>
</form>
</nav>
</details>
</footer>
</article><?php
endforeach;
?>
</div><?php
require __DIR__.'/_footer.php';
