<?php
require __DIR__.'/bootstrap.php';
require_permission('sections.manage');
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $keys=$_POST['section_key']??[];
    $orders=$_POST['sort_order']??[];
    $active=$_POST['active']??[];
    $st=$pdo->prepare('UPDATE site_sections SET sort_order=?,active=? WHERE section_key=?');
    foreach($keys as $i=>$key)$st->execute([(int)($orders[$i]??0),isset($active[$key])?1:0,$key]);
    log_activity('sections_update','Updated public website sections');
    flash('success','Section visibility and order saved.');
    header('Location: sections.php');
    exit;
}
$rows=$pdo->query('SELECT * FROM site_sections ORDER BY sort_order,section_key')->fetchAll();
$pageTitle='Section Manager';
$active='sections';
require __DIR__.'/_header.php';
?>
<div class="page-heading">
<div>
<p class="eyebrow">VISUAL STRUCTURE</p>
<h1>Landing page sections</h1>
<p class="muted">Show, hide and drag sections into the order you want visitors to see.</p>
</div>
<a class="button secondary" href="../?preview=1" target="_blank">Preview website</a>
</div>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<div class="section-sort-list" data-sortable-list><?php
foreach($rows as $r):
?>
<article class="section-sort-card" draggable="true">
<span class="drag-handle">⋮⋮</span>
<input type="hidden" name="section_key[]" value="<?=h($r['section_key'])?>
">
<input type="hidden" name="sort_order[]" value="<?=$r['sort_order']?>
" data-sort-order>
<div>
<strong><?=h($r['label'])?>
</strong>
<small>#<?=h($r['section_key'])?>
 · order <b data-order-label><?=$r['sort_order']?>
</b>
</small>
</div>
<label class="premium-switch compact">
<input type="checkbox" name="active[<?=h($r['section_key'])?>
]" <?=$r['active']?'checked':''?>
>
<span class="switch-ui">
</span>
<span>
<b><?=$r['active']?'Visible':'Hidden'?>
</b>
<small>Public section</small>
</span>
</label>
</article><?php
endforeach;
?>
</div>
<div class="form-actions">
<button>Save section layout</button>
</div>
</form><?php
require __DIR__.'/_footer.php';
