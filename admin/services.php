<?php
require __DIR__.'/bootstrap.php';
require_permission('services.manage');
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $a=$_POST['action']??'';
    $id=(int)($_POST['id']??0);
    if($a==='save') {
        $title=trim((string)($_POST['title']??''));
        $details=trim((string)($_POST['details']??''));
        $sort=(int)($_POST['sort_order']??0);
        $active=isset($_POST['active'])?1:0;
        if($title!=='') {
            if($id)$pdo->prepare('UPDATE services SET title=?,details=?,sort_order=?,active=? WHERE id=?')->execute([$title,$details,$sort,$active,$id]);
            else $pdo->prepare('INSERT INTO services(title,details,sort_order,active) VALUES(?,?,?,?)')->execute([$title,$details,$sort,$active]);
            flash('success','Service saved.');
        }
    } elseif($a==='delete'&&$id) {
        $pdo->prepare('DELETE FROM services WHERE id=?')->execute([$id]);
        flash('success','Service deleted.');
    }
    header('Location: services.php');
    exit;
}
$edit=null;
if(isset($_GET['edit'])) {
    $s=$pdo->prepare('SELECT * FROM services WHERE id=?');
    $s->execute([(int)$_GET['edit']]);
    $edit=$s->fetch();
}
$rows=$pdo->query('SELECT * FROM services ORDER BY sort_order,id')->fetchAll();
$pageTitle='Services';
$active='services';
require __DIR__.'/_header.php';
?>

<div class="page-heading">
<div>
<p class="eyebrow">SERVICES</p>
<h1>Manage services</h1>
<p class="muted">Keep the service catalog organized without touching the website code.</p>
</div>
<a class="button secondary" href="services.php">New service</a>
</div>
<section class="panel animate-in">
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<input type="hidden" name="action" value="save">
<input type="hidden" name="id" value="<?=h((string)($edit['id']??0))?>
">
<div class="two-col">
<label>Service title<input name="title" required value="<?=h($edit['title']??'')?>
">
</label>
<label>Display order<input type="number" name="sort_order" value="<?=h((string)($edit['sort_order']??0))?>
">
</label>
</div>
<label>Subservices / details<textarea name="details" required><?=h($edit['details']??'')?>
</textarea>
</label>
<label class="check-row status-switch">
<input type="checkbox" name="active" <?=!$edit||!empty($edit['active'])?'checked':''?>
> Publish on website</label>
<div class="form-actions">
<button>Save service</button><?php
if($edit):
?>
<a class="button secondary" href="services.php">Cancel</a><?php
endif;
?>
</div>
</form>
</section>
<div class="list-grid"><?php
foreach($rows as $r):
?>
<article class="list-card animate-in">
<div class="list-head">
<div>
<strong><?=h($r['title'])?>
</strong>
<small>Order <?=$r['sort_order']?>
</small>
</div>
<span class="badge <?=$r['active']?'contacted':'closed'?>
"><?=$r['active']?'Published':'Hidden'?>
</span>
</div>
<p><?=h($r['details'])?>
</p>
<div class="actions">
<a class="button secondary small" href="?edit=<?=$r['id']?>
">Edit</a>
<details class="action-menu">
<summary>Actions ▾</summary>
<nav>
<form method="post" data-swal-confirm="Delete this service?" data-swal-text="This action cannot be undone.">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?=$r['id']?>
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
require __DIR__.'/_footer.php';
