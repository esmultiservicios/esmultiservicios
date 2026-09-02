<?php
require __DIR__.'/bootstrap.php';
require_permission('media.manage');
$pdo=db();
$error='';
try {
    $pdo->exec("INSERT IGNORE INTO media_library(title,file_path,mime_type,file_size,uploaded_by) SELECT title,image_path,NULL,0,NULL FROM gallery WHERE image_path IS NOT NULL AND image_path<>''");
} catch(Throwable $e) {
}
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $a=$_POST['action']??'';
    try {
        if($a==='upload') {
            $files=normalized_files('media_files');
            if(!$files)throw new RuntimeException('Choose at least one media file.');
            foreach(array_slice($files,0,12) as $f) {
                $p=upload_media_file($f,'media','media',60);
                media_add($p,pathinfo($f['name']??'',PATHINFO_FILENAME));
            }
            log_activity('media_upload','Uploaded media files');
            flash('success','Media added to the Media Library.');
        } elseif($a==='delete') {
            $id=(int)($_POST['id']??0);
            $pdo->prepare('DELETE FROM media_library WHERE id=?')->execute([$id]);
            log_activity('media_delete','Removed media library item',['id'=>$id]);
            flash('success','Media item removed.');
        }
        header('Location: media.php');
        exit;
    } catch(Throwable $e) {
        $error=$e->getMessage();
    }
}
$q=trim((string)($_GET['q']??''));
if($q!=='') {
    $st=$pdo->prepare('SELECT * FROM media_library WHERE title LIKE ? OR file_path LIKE ? OR mime_type LIKE ? ORDER BY id DESC');
    $st->execute(['%'.$q.'%','%'.$q.'%','%'.$q.'%']);
    $rows=$st->fetchAll();
} else {
    $rows=$pdo->query('SELECT * FROM media_library ORDER BY id DESC')->fetchAll();
}
$pageTitle='Media Library';
$active='media';
require __DIR__.'/_header.php';
?>

<div class="page-heading">
<div>
<p class="eyebrow">MEDIA LIBRARY</p>
<h1>Website media in one place</h1>
<p class="muted">Upload, search, preview and reuse images, videos and PDF documents.</p>
</div>
<form class="compact-search">
<input name="q" value="<?=h($q)?>
" placeholder="Search media">
<button class="button small">Search</button>
</form>
</div>
<?php
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
<input type="hidden" name="action" value="upload">
<div class="upload-zone large" data-upload-zone tabindex="0">
<div class="upload-icon">＋</div>
<strong>Add media to the library</strong>
<small data-upload-name>Drag & drop, paste images, or choose up to 12 files · JPG, PNG, WEBP, MP4, WEBM, PDF</small>
<input type="file" name="media_files[]" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,application/pdf" multiple>
<div class="upload-preview" data-upload-preview>
</div>
</div>
<div class="form-actions">
<button>Upload media</button>
</div>
</form>
</section>
<div class="media-grid"><?php
foreach($rows as $r):$mime=(string)($r['mime_type']??'');
$path=(string)$r['file_path'];
$isVideo=str_starts_with($mime,'video/')||preg_match('/\.(mp4|webm)$/i',$path);
$isPdf=$mime==='application/pdf'||preg_match('/\.pdf$/i',$path);
?>
<article class="media-card animate-in">
<?php
if($isVideo):
?>
<div class="media-preview media-video">
<video controls preload="metadata" src="../<?=h($path)?>
">
</video>
<span class="media-kind">VIDEO</span>
</div><?php
elseif($isPdf):
?>
<a class="media-preview media-document" href="../<?=h($path)?>
" target="_blank" rel="noopener">
<div class="document-icon">PDF</div>
<span class="media-kind">DOCUMENT</span>
</a><?php
else:
?>
<button type="button" class="media-preview" data-preview-src="../<?=h($path)?>
" data-preview-caption="<?=h($r['title']?:'Media image')?>
">
<img src="../<?=h($path)?>
" alt="">
<span><?=icon('eye')?>
</span>
</button><?php
endif;
?>

<div class="media-meta">
<strong><?=h($r['title']?:basename($path))?>
</strong>
<small><?=h($r['created_at'])?>
<?=$mime!==''?' · '.h($mime):''?>
</small>
<code><?=h($path)?>
</code>
</div>
<form method="post" data-swal-confirm="Remove this media item?" data-swal-text="The library record will be removed. The file is kept to avoid breaking pages that may use it.">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?=$r['id']?>
">
<button class="button danger-lite small">Remove</button>
</form>
</article><?php
endforeach;
?>
</div>
<?php
require __DIR__.'/_footer.php';
