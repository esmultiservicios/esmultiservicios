<?php
require __DIR__.'/bootstrap.php';
require_permission('tips.manage');
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $a=$_POST['action']??'';
    $id=(int)($_POST['id']??0);
    if($a==='save') {
        $title=trim((string)($_POST['title']??''));
        $url=trim((string)($_POST['url']??'#'));
        $sort=(int)($_POST['sort_order']??0);
        $active=isset($_POST['active'])?1:0;
        if($title!=='') {
            if($id)$pdo->prepare('UPDATE tips SET title=?,url=?,sort_order=?,active=? WHERE id=?')->execute([$title,$url,$sort,$active,$id]);
            else $pdo->prepare('INSERT INTO tips(title,url,sort_order,active) VALUES(?,?,?,?)')->execute([$title,$url,$sort,$active]);
            flash('success','Home tip saved.');
        }
    } elseif($a==='delete'&&$id) {
        $pdo->prepare('DELETE FROM tips WHERE id=?')->execute([$id]);
        flash('success','Home tip deleted.');
    }
    header('Location: tips.php');
    exit;
}
$edit=null;
if(isset($_GET['edit'])) {
    $s=$pdo->prepare('SELECT * FROM tips WHERE id=?');
    $s->execute([(int)$_GET['edit']]);
    $edit=$s->fetch();
}
$rows=$pdo->query('SELECT * FROM tips ORDER BY sort_order,id')->fetchAll();
$pageTitle='Home Tips';
$active='tips';
require __DIR__.'/_header.php';
?>

<div class="page-heading">
<div>
<p class="eyebrow">HELPFUL TIPS</p>
<h1>Educational content</h1>
<p class="muted">Manage short educational links now; a richer WordPress-style page editor can be added in the next phase.</p>
</div>
</div>
<section class="panel">
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="save">
<input type="hidden" name="id" value="<?=h((string)($edit['id']??0))?>">
<label>Title<input name="title" required value="<?=h($edit['title']??'')?>">
</label>
<div class="two-col">
<label>URL<input name="url" value="<?=h($edit['url']??'#')?>">
</label>
<label>Order<input type="number" name="sort_order" value="<?=h((string)($edit['sort_order']??0))?>">
</label>
</div>
<label class="check-row status-switch">
<input type="checkbox" name="active" <?=!$edit||!empty($edit['active'])?'checked':''?>
> Publish</label>
<div class="form-actions">
<button>Save tip</button><?php
if($edit):
?>
<a class="button secondary" href="tips.php">Cancel</a><?php
endif;
?>
</div>
</form>
</section>
<div class="list-grid"><?php
foreach($rows as $r):
?>
<article class="list-card">
<div class="list-head">
<div>
<strong><?=h($r['title'])?>
</strong>
<small><?=h($r['url'])?>
</small>
</div>
<span class="badge <?=$r['active']?'contacted':'closed'?>"><?=$r['active']?'Published':'Hidden'?>
</span>
</div>
<div class="actions">
<a class="button secondary small" href="?edit=<?=$r['id']?>">Edit</a>
<form method="post" data-swal-confirm="Delete this tip?" data-swal-text="This educational item will be removed.">
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
require __DIR__.'/_footer.php';
