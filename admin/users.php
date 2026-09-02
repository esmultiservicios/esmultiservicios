<?php
require __DIR__.'/bootstrap.php';
require_permission('users.manage');
$pdo=db();
$me=current_admin();
$error='';
function owner_count(PDO $pdo): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM admin_users u JOIN admin_roles r ON r.id=u.role_id WHERE r.role_key='owner' AND u.active=1")->fetchColumn();
}
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $action=(string)($_POST['action']??'');
    try {
        if($action==='save') {
            $id=(int)($_POST['id']??0);
            $username=trim((string)($_POST['username']??''));
            $full=trim((string)($_POST['full_name']??''));
            $email=trim((string)($_POST['email']??''));
            $roleId=(int)($_POST['role_id']??0);
            $active=isset($_POST['active'])?1:0;
            $password=(string)($_POST['password']??'');
            $avatar='';
            if(!empty($_FILES['avatar']['name']))$avatar=upload_image($_FILES['avatar'],'admin','avatar',5);
            if(strlen($username)<4)throw new RuntimeException('Username must have at least 4 characters.');
            if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');
            $role=$pdo->prepare('SELECT role_key FROM admin_roles WHERE id=? AND active=1');
            $role->execute([$roleId]);
            $newRole=(string)$role->fetchColumn();
            if($newRole==='')throw new RuntimeException('Choose a valid role.');
            if($newRole==='owner'&&!role_is_owner($me))throw new RuntimeException('Only the Owner can assign the Owner role.');
            if($id) {
                $st=$pdo->prepare('SELECT u.*,r.role_key FROM admin_users u LEFT JOIN admin_roles r ON r.id=u.role_id WHERE u.id=?');
                $st->execute([$id]);
                $old=$st->fetch();
                if(!$old)throw new RuntimeException('User not found.');
                if(($old['role_key']??'')==='owner'&&!role_is_owner($me))throw new RuntimeException('Only the Owner can modify another Owner account.');
                if($id===(int)$me['id']&&!$active)throw new RuntimeException('You cannot disable your own account.');
                if(($old['role_key']??'')==='owner'&&($newRole!=='owner'||!$active)&&owner_count($pdo)<=1)throw new RuntimeException('The last active Owner cannot be removed or downgraded.');
                $sql='UPDATE admin_users SET username=?,full_name=?,email=?,role_id=?,active=?';
                $args=[$username,
                $full,
                $email,
                $roleId,
                $active];
                if($avatar!=='') {
                    $sql.=',avatar_path=?';
                    $args[]=$avatar;
                }
                if($password!=='') {
                    if(strlen($password)<10)throw new RuntimeException('Password must have at least 10 characters.');
                    $sql.=',password_hash=?';
                    $args[]=password_hash($password,PASSWORD_DEFAULT);
                }
                $sql.=' WHERE id=?';
                $args[]=$id;
                $pdo->prepare($sql)->execute($args);
                log_activity('user_update','Updated administrator user',['user_id'=>$id]);
                admin_notify('info','Administrator updated',$username.' was updated.','users.php');
                flash('success','User updated.');
            } else {
                if(strlen($password)<10)throw new RuntimeException('Password must have at least 10 characters.');
                $pdo->prepare('INSERT INTO admin_users(username,full_name,email,avatar_path,password_hash,role_id,active,created_by) VALUES(?,?,?,?,?,?,?,?)')->execute([$username,$full,$email,$avatar?:null,password_hash($password,PASSWORD_DEFAULT),$roleId,$active,(int)$me['id']]);
                $id=(int)$pdo->lastInsertId();
                log_activity('user_create','Created administrator user',['user_id'=>$id]);
                admin_notify('success','Administrator created',$username.' can now sign in to the CMS.','users.php');
                flash('success','User created.');
            }
        } elseif($action==='delete') {
            $id=(int)($_POST['id']??0);
            if($id===(int)$me['id'])throw new RuntimeException('You cannot delete your own account.');
            $st=$pdo->prepare('SELECT u.username,r.role_key FROM admin_users u LEFT JOIN admin_roles r ON r.id=u.role_id WHERE u.id=?');
            $st->execute([$id]);
            $u=$st->fetch();
            if(!$u)throw new RuntimeException('User not found.');
            if(($u['role_key']??'')==='owner'&&!role_is_owner($me))throw new RuntimeException('Only the Owner can delete an Owner account.');
            if(($u['role_key']??'')==='owner'&&owner_count($pdo)<=1)throw new RuntimeException('The last Owner cannot be deleted.');
            $pdo->prepare('DELETE FROM admin_remember_tokens WHERE admin_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM admin_sessions WHERE admin_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM admin_users WHERE id=?')->execute([$id]);
            log_activity('user_delete','Deleted administrator user',['username'=>$u['username']]);
            flash('success','User deleted.');
        }
        header('Location: users.php');
        exit;
    } catch(Throwable $e) {
        $error=$e->getMessage();
    }
}
$edit=null;
if(isset($_GET['edit'])) {
    $st=$pdo->prepare('SELECT * FROM admin_users WHERE id=?');
    $st->execute([(int)$_GET['edit']]);
    $edit=$st->fetch();
}
$roles=$pdo->query('SELECT * FROM admin_roles WHERE active=1 ORDER BY CASE role_key WHEN "owner" THEN 0 ELSE 1 END,role_name')->fetchAll();
$rows=$pdo->query('SELECT u.*,r.role_name,r.role_key FROM admin_users u LEFT JOIN admin_roles r ON r.id=u.role_id ORDER BY u.active DESC,u.id')->fetchAll();
$pageTitle='Users';
$active='users';
require __DIR__.'/_header.php';
?>


<div class="page-heading">
<div>
<p class="eyebrow">ADMINISTRATION</p>
<h1>Users</h1>
<p class="muted">Create team accounts, assign roles and disable access without deleting website content.</p>
</div>
<button class="button" type="button" data-toggle-panel="user-editor">+ Add user</button>
</div><?php
if($error):
?>

<div class="alert error"><?=h($error)?>

</div><?php
endif;
?>


<section class="panel <?=($edit||$error)?'':'is-collapsed'?>" id="user-editor">
<div class="panel-heading">
<div class="panel-icon"><?=icon('users')?>

</div>
<div>
<h2><?=$edit?'Edit user':'Create user'?>

</h2>
<p>Password is required for new accounts and optional while editing.</p>
</div>
</div>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="save">
<input type="hidden" name="id" value="<?=$edit['id']??0?>">
<div class="two-col">
<label>Full name<input name="full_name" value="<?=h($edit['full_name']??'')?>">
</label>
<label>Username<input name="username" required value="<?=h($edit['username']??'')?>">
</label>
</div>
<div class="two-col">
<label>Email<input type="email" name="email" value="<?=h($edit['email']??'')?>">
</label>
<label>Role<select name="role_id" required>
<option value="">Choose role</option><?php
foreach($roles as $r):if($r['role_key']==='owner'&&!role_is_owner($me))continue;
?>

<option value="<?=$r['id']?>" <?=isset($edit['role_id'])&&(int)$edit['role_id']===(int)$r['id']?'selected':''?>

><?=h($r['role_name'])?>

</option><?php
endforeach;
?>

</select>
</label>
</div>
<div class="user-photo-editor"><?php
if(!empty($edit['avatar_path'])):
?>

<button class="current-avatar-preview" type="button" data-preview-src="../<?=h($edit['avatar_path'])?>" data-preview-caption="<?=h($edit['full_name']?:$edit['username'])?>">
<img src="../<?=h($edit['avatar_path'])?>" alt="Current profile picture">
<span>Current photo</span>
</button><?php
endif;
?>

<div class="upload-zone user-photo-upload" data-upload-zone tabindex="0">
<div class="upload-icon"><?=icon('image')?>

</div>
<strong><?=$edit?'Replace profile picture':'Profile picture'?>

</strong>
<small data-upload-name>Drag & drop, paste or choose image</small>
<input type="file" name="avatar" accept="image/jpeg,image/png,image/webp">
<div class="upload-preview" data-upload-preview>
</div>
</div>
</div>
<label><?=$edit?'New password (leave blank to keep current)':'Temporary password'?>

<input type="password" name="password" <?=$edit?'':'required'?>

 minlength="10">
</label>
<label class="premium-switch">
<input type="checkbox" name="active" value="1" <?=!isset($edit['active'])||(int)$edit['active']===1?'checked':''?>

>
<span class="switch-ui">
</span>
<span>
<b>Account active</b>
<small>Disabled users cannot sign in.</small>
</span>
</label>
<div class="form-actions">
<button><?=$edit?'Save user':'Create user'?>

</button><?php
if($edit):
?>

<a class="button secondary" href="users.php">Cancel</a><?php
endif;
?>

</div>
</form>
</section>
<div class="user-grid"><?php
foreach($rows as $u):
?>

<article class="user-card animate-in"><?php
if(!empty($u['avatar_path'])):
?>

<button class="user-avatar user-avatar-photo" type="button" data-preview-src="../<?=h($u['avatar_path'])?>" data-preview-caption="<?=h($u['full_name']?:$u['username'])?>">
<img src="../<?=h($u['avatar_path'])?>" alt="<?=h($u['full_name']?:$u['username'])?>">
</button><?php
else:
?>

<div class="user-avatar"><?=strtoupper(substr($u['full_name']?:$u['username'],0,1))?>

</div><?php
endif;
?>

<div class="user-main">
<div class="user-title">
<strong><?=h($u['full_name']?:$u['username'])?>

</strong>
<span class="badge <?=$u['active']?'success':'closed'?>"><?=$u['active']?'Active':'Disabled'?>

</span>
</div>
<small>@<?=h($u['username'])?>

 · <?=h($u['role_name']?:'No role')?>

 · <?=((int)($u['two_factor_enabled']??0)===1)?'2FA on':'2FA off'?>

</small>
<p><?=h($u['email']?:'No email')?>

</p>
<div class="user-meta">
<span>Last login <b><?=h($u['last_login_at']?:'Never')?>

</b>
</span>
<span>Created <b><?=h($u['created_at'])?>

</b>
</span>
</div>
</div>
<div class="actions">
<a class="button secondary small" href="?edit=<?=$u['id']?>">Edit</a><?php
if((int)$u['id']!==(int)$me['id']):
?>

<form method="post" data-swal-confirm="Delete this administrator?" data-swal-text="This removes login access. Website content will not be deleted.">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?=$u['id']?>">
<button class="button danger-lite small">Delete</button>
</form><?php
endif;
?>

</div>
</article><?php
endforeach;
?>

</div>
<?php
require __DIR__.'/_footer.php';
