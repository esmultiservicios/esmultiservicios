<?php
require __DIR__.'/bootstrap.php';
require_permission('videos.manage');
$pdo=db();

function video_provider_id(string $type,string $url): string {
    $url=trim($url);
    if($type==='youtube') {
        if(preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{6,})~',$url,$m)) return $m[1];
    }
    if($type==='vimeo' && preg_match('~vimeo\.com/(?:video/)?([0-9]+)~',$url,$m)) return $m[1];
    return '';
}

function admin_video_embed_url(array $video): string {
    $type=(string)($video['video_type']??'');
    $url=trim((string)($video['video_url']??''));
    $id=video_provider_id($type,$url);
    if($type==='youtube' && $id!=='') return 'https://www.youtube.com/embed/'.rawurlencode($id).'?rel=0';
    if($type==='vimeo' && $id!=='') return 'https://player.vimeo.com/video/'.rawurlencode($id);
    return '';
}

function render_admin_video_preview(array $video,string $label='Preview'): void {
    $embed=admin_video_embed_url($video);
    $type=(string)($video['video_type']??'');
    $file=trim((string)($video['file_path']??''));
    $poster=trim((string)($video['poster_path']??''));
    if($type==='upload' && $file==='') return;
    if($type!=='upload' && $embed==='') return;
    ?>
    <div class="admin-video-preview-card">
        <div class="admin-video-preview-head">
            <div>
                <span class="admin-video-preview-label"><?=h($label)?></span>
                <strong><?=h((string)($video['title']??'Video'))?></strong>
            </div>
            <span class="badge <?=!empty($video['active'])?'contacted':'closed'?>"><?=!empty($video['active'])?'Published':'Hidden'?></span>
        </div>
        <div class="admin-video-preview-frame">
            <?php if($type==='upload'): ?>
                <video controls preload="metadata" playsinline <?php if($poster!==''): ?>poster="../<?=h($poster)?>"<?php endif; ?>>
                    <source src="../<?=h($file)?>">
                </video>
            <?php else: ?>
                <iframe src="<?=h($embed)?>" title="<?=h((string)($video['title']??'Video preview'))?>" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $action=$_POST['action']??'';
    $id=(int)($_POST['id']??0);
    try {
        if($action==='save') {
            $title=trim((string)($_POST['title']??''));
            $description=trim((string)($_POST['description']??''));
            $type=(string)($_POST['video_type']??'youtube');
            $url=trim((string)($_POST['video_url']??''));
            $filePath=trim((string)($_POST['current_file_path']??''));
            $posterPath=trim((string)($_POST['current_poster_path']??''));
            $sort=(int)($_POST['sort_order']??0);
            $active=isset($_POST['active'])?1:0;

            if(!in_array($type,['youtube','vimeo','upload'],true)) throw new RuntimeException('Select a valid video type.');
            if($title==='') throw new RuntimeException('Video title is required.');

            if($type==='upload') {
                $url='';
                if(!empty($_FILES['video_file']['name'])) {
                    $uploaded=upload_media_file($_FILES['video_file'],'videos','website-video',250);
                    $ext=strtolower(pathinfo($uploaded,PATHINFO_EXTENSION));
                    if(!in_array($ext,['mp4','webm'],true)) throw new RuntimeException('Uploaded video must be MP4 or WEBM.');
                    $filePath=$uploaded;
                }
                if($filePath==='') throw new RuntimeException('Choose an MP4 or WEBM video file.');
            } else {
                $filePath='';
                if(video_provider_id($type,$url)==='') throw new RuntimeException('Enter a valid '.($type==='youtube'?'YouTube':'Vimeo').' URL.');
            }

            if(isset($_POST['remove_poster'])) $posterPath='';
            if(!empty($_FILES['poster']['name'])) $posterPath=upload_image($_FILES['poster'],'videos','video-poster',8);

            if($id) {
                $pdo->prepare('UPDATE videos SET title=?,description=?,video_type=?,video_url=?,file_path=?,poster_path=?,sort_order=?,active=? WHERE id=?')
                    ->execute([$title,$description,$type,$url,$filePath,$posterPath,$sort,$active,$id]);
            } else {
                $pdo->prepare('INSERT INTO videos(title,description,video_type,video_url,file_path,poster_path,sort_order,active) VALUES(?,?,?,?,?,?,?,?)')
                    ->execute([$title,$description,$type,$url,$filePath,$posterPath,$sort,$active]);
            }
            log_activity('video_save','Saved website video',['video_id'=>$id?:null,'title'=>$title]);
            flash('success','Video saved.');
        } elseif($action==='delete'&&$id) {
            $pdo->prepare('DELETE FROM videos WHERE id=?')->execute([$id]);
            log_activity('video_delete','Deleted website video',['video_id'=>$id]);
            flash('success','Video deleted.');
        }
    } catch(Throwable $e) { flash('error',$e->getMessage()); }
    header('Location: videos.php'); exit;
}

$edit=null;
if(isset($_GET['edit'])) { $st=$pdo->prepare('SELECT * FROM videos WHERE id=?'); $st->execute([(int)$_GET['edit']]); $edit=$st->fetch(); }
$rows=$pdo->query('SELECT * FROM videos ORDER BY sort_order,id')->fetchAll();
$pageTitle='Videos'; $active='videos'; require __DIR__.'/_header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">WEBSITE VIDEOS</p>
        <h1>Manage videos</h1>
        <p class="muted">Add as many videos as needed. YouTube or Vimeo is recommended for streaming, but direct MP4/WEBM upload is also supported.</p>
    </div>
    <a class="button secondary" href="videos.php">New video</a>
</div>

<?php if($edit): ?>
<section class="panel admin-video-current-preview animate-in">
    <?php render_admin_video_preview($edit,'CURRENT VIDEO PREVIEW'); ?>
</section>
<?php endif; ?>

<section class="panel animate-in">
<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?=h((string)($edit['id']??0))?>">
    <input type="hidden" name="current_file_path" value="<?=h($edit['file_path']??'')?>">
    <input type="hidden" name="current_poster_path" value="<?=h($edit['poster_path']??'')?>">

    <div class="two-col">
        <label>Video title<input name="title" required value="<?=h($edit['title']??'')?>"></label>
        <label>Display order<input type="number" name="sort_order" value="<?=h((string)($edit['sort_order']??0))?>"></label>
    </div>
    <label>Description<textarea name="description"><?=h($edit['description']??'')?></textarea></label>
    <div class="two-col">
        <label>Video type
            <select name="video_type" data-video-type>
                <?php $vt=$edit['video_type']??'youtube'; foreach(['youtube'=>'YouTube','vimeo'=>'Vimeo','upload'=>'Upload MP4 / WEBM'] as $k=>$v): ?>
                    <option value="<?=$k?>" <?=$vt===$k?'selected':''?>><?=$v?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label data-video-url-wrap>Video URL<input type="url" name="video_url" value="<?=h($edit['video_url']??'')?>" placeholder="https://www.youtube.com/watch?v=..."></label>
    </div>
    <div class="video-upload-field" data-video-upload-wrap>
        <div class="field-heading">
            <div>
                <strong>Video file</strong>
                <small>MP4 or WEBM · Direct upload is available when you do not use YouTube or Vimeo.</small>
            </div>
        </div>
        <div class="upload-zone premium-media-zone" data-upload-zone>
            <input type="file" name="video_file" accept="video/mp4,video/webm" data-empty-label="Drop, paste or choose an MP4 / WEBM video">
            <div class="upload-icon" aria-hidden="true">🎬</div>
            <strong>Drop video here</strong>
            <small>Drag & drop, paste from clipboard, or click to choose a file.</small>
            <span class="upload-zone-action">Choose video</span>
            <div class="upload-selection-name" data-upload-name>Drop, paste or choose an MP4 / WEBM video</div>
            <div class="upload-preview premium-upload-preview" data-upload-preview></div>
        </div>
        <?php if(!empty($edit['file_path'])): ?>
            <div class="current-media-file">
                <span class="current-media-icon" aria-hidden="true">▶</span>
                <div><strong>Current uploaded video</strong><small><?=h($edit['file_path'])?></small></div>
            </div>
        <?php endif; ?>
    </div>

    <div class="video-poster-editor premium-poster-editor">
        <div class="poster-upload-column">
            <div class="field-heading">
                <div>
                    <strong>Poster / cover image</strong>
                    <small>Optional, but recommended. It gives the video a professional cover before playback.</small>
                </div>
            </div>
            <div class="upload-zone premium-media-zone" data-upload-zone>
                <input type="file" name="poster" accept="image/jpeg,image/png,image/webp" data-empty-label="Drop, paste or choose a cover image">
                <div class="upload-icon" aria-hidden="true">▣</div>
                <strong>Drop cover image here</strong>
                <small>Drag & drop, paste an image from the clipboard, or click to browse.</small>
                <span class="upload-zone-action">Choose image</span>
                <div class="upload-selection-name" data-upload-name>Drop, paste or choose a cover image</div>
                <div class="upload-preview premium-upload-preview" data-upload-preview></div>
            </div>
        </div>
        <?php if(!empty($edit['poster_path'])): ?>
            <div class="current-poster-card">
                <span class="current-media-label">Current cover</span>
                <img src="../<?=h($edit['poster_path'])?>" alt="Current video poster">
                <label class="check-row remove-media-check"><input type="checkbox" name="remove_poster"> Remove current cover when saving</label>
            </div>
        <?php endif; ?>
    </div>
    <label class="check-row status-switch"><input type="checkbox" name="active" <?=!$edit||!empty($edit['active'])?'checked':''?>> Publish on website</label>
    <div class="form-actions"><button>Save video</button><?php if($edit): ?><a class="button secondary" href="videos.php">Cancel</a><?php endif; ?></div>
</form>
</section>

<div class="list-grid">
<?php foreach($rows as $r): ?>
<article class="list-card video-admin-list-card animate-in">
    <?php render_admin_video_preview($r,'SAVED VIDEO'); ?>
    <div class="video-admin-card-meta">
        <div class="list-head"><div><strong><?=h($r['title'])?></strong><small><?=h(ucfirst($r['video_type']))?> · order <?=$r['sort_order']?></small></div></div>
        <?php if(trim((string)$r['description'])!==''): ?><p><?=h($r['description'])?></p><?php endif; ?>
        <div class="actions"><a class="button secondary small" href="?edit=<?=$r['id']?>">Edit</a><details class="action-menu"><summary>Actions ▾</summary><nav><form method="post" data-swal-confirm="Delete this video?" data-swal-text="This action cannot be undone."><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="danger-text">Delete</button></form></nav></details></div>
    </div>
</article>
<?php endforeach; ?>
</div>
<script>
(function(){
    const type=document.querySelector('[data-video-type]');
    const url=document.querySelector('[data-video-url-wrap]');
    const upload=document.querySelector('[data-video-upload-wrap]');
    function sync(){ if(!type)return; const isUpload=type.value==='upload'; if(url)url.hidden=isUpload; if(upload)upload.hidden=!isUpload; }
    if(type){ type.addEventListener('change',sync); sync(); }
})();
</script>
<?php require __DIR__.'/_footer.php';
