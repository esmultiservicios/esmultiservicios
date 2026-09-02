<?php
require __DIR__.'/bootstrap.php';
if(admin_count()>0) {
    header('Location: login.php');
    exit;
}
$error='';
$set=settings();
$favicon=$set['favicon_path']??($set['admin_logo_path']??'');
$brand=$set['admin_brand_name']??"ES CMS Core Admin";
$logo=$set['admin_logo_path']??'';
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $u=trim((string)($_POST['username']??''));
    $full=trim((string)($_POST['full_name']??''));
    $email=trim((string)($_POST['email']??''));
    $p=(string)($_POST['password']??'');
    $p2=(string)($_POST['password2']??'');
    if(strlen($u)<4)$error='Username must have at least 4 characters.';
    elseif($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))$error='Enter a valid email address.';
    elseif(strlen($p)<10)$error='Password must have at least 10 characters.';
    elseif($p!==$p2)$error='Passwords do not match.';
    else {
        $role=(int)db()->query("SELECT id FROM admin_roles WHERE role_key='owner' LIMIT 1")->fetchColumn();
        $st=db()->prepare('INSERT INTO admin_users(username,full_name,email,password_hash,role_id,active) VALUES(?,?,?,?,?,1)');
        $st->execute([$u,$full,$email,password_hash($p,PASSWORD_DEFAULT),$role]);
        header('Location: login.php?setup=1');
        exit;
    }
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php if($favicon!==''): ?>
<link rel="icon" href="../<?=h($favicon)?>">
<link rel="shortcut icon" href="../<?=h($favicon)?>">
<?php endif; ?>
<title>Admin Setup</title>
<link rel="stylesheet" href="../assets/vendor/sweetalert2/sweetalert2.min.css">
<link rel="stylesheet" href="../assets/vendor/show-notify/showNotify.css">
<link rel="stylesheet" href="admin.css">
</head>
<body>
<main class="auth-wrap">
<div class="auth-card">
<div class="auth-brand-mark">
<?php if($logo!==''): ?><img src="../<?=h($logo)?>" alt=""><?php else: ?><span class="auth-brand-fallback">CMS</span><?php endif; ?>
</div>
<p class="eyebrow">FIRST-TIME ADMIN SETUP</p>
<h1>Create administrator</h1>
<p>Set the account that will manage <?=h($brand)?>

.</p><?php
if(isset($_GET['reset'])):
?>

<div class="alert success">Administrator ownership was reset. Create the new owner's account below.</div><?php
endif;
?>
<?php
if($error):
?>

<div class="alert error"><?=h($error)?>

</div><?php
endif;
?>

<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<label>Full name<input name="full_name" autocomplete="name">
</label>
<label>Email<input type="email" name="email" autocomplete="email">
</label>
<label>Username<input name="username" required autocomplete="username">
</label>
<label>Password<input type="password" name="password" required minlength="10" autocomplete="new-password">
</label>
<label>Repeat password<input type="password" name="password2" required minlength="10" autocomplete="new-password">
</label>
<button type="submit">Create administrator</button>
</form>
</div>
</main>
<script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js">
</script>
<script src="../assets/vendor/show-notify/showNotify.js">
</script>
<script>document.querySelectorAll(".alert.success,.alert.error,.alert.info,.alert.warning").forEach(function(el){var t=el.classList.contains("error")?"error":el.classList.contains("warning")?"warning":el.classList.contains("success")?"success":"info";if(window.showNotify){showNotify(el.textContent.trim(),t);el.hidden=true;}});</script>
</body>
</html>
