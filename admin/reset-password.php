<?php
require __DIR__.'/bootstrap.php';
if(admin_count()===0) {
    header('Location: setup.php');
    exit;
}
$error='';
$set=settings();
$favicon=trim((string)($set['favicon_path']??''))?:'assets/brand/favicon.png';
$brand=$set['admin_brand_name']??"ES CMS Core Admin";
$logo=$set['admin_logo_path']??'';
$token=trim((string)($_GET['token']??$_POST['token']??''));
$reset=null;
if($token!=='') {
    $hash=hash('sha256',$token);
    $st=db()->prepare('SELECT r.id,r.admin_id,r.expires_at,r.used_at,u.username,u.email FROM admin_password_resets r JOIN admin_users u ON u.id=r.admin_id WHERE r.token_hash=? AND r.used_at IS NULL AND r.expires_at>NOW() LIMIT 1');
    $st->execute([$hash]);
    $reset=$st->fetch();
}
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $p=(string)($_POST['password']??'');
    $p2=(string)($_POST['password2']??'');
    if(!$reset)$error='This reset link is invalid or has expired.';
    elseif(strlen($p)<10)$error='Password must have at least 10 characters.';
    elseif($p!==$p2)$error='Passwords do not match.';
    else {
        $pdo=db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE admin_users SET password_hash=? WHERE id=?')->execute([password_hash($p,PASSWORD_DEFAULT),(int)$reset['admin_id']]);
            $pdo->prepare('UPDATE admin_password_resets SET used_at=NOW() WHERE id=?')->execute([(int)$reset['id']]);
            $pdo->prepare('DELETE FROM admin_remember_tokens WHERE admin_id=?')->execute([(int)$reset['admin_id']]);
            $pdo->commit();
            clear_remember_cookie();
            header('Location: login.php?reset=1');
            exit;
        } catch(Throwable $e) {
            $pdo->rollBack();
            $error='Unable to update the password. Please try again.';
        }
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
<title>New Password</title>
<link rel="stylesheet" href="<?=h(versioned_asset('../assets/vendor/sweetalert2/sweetalert2.min.css', 'assets/vendor/sweetalert2/sweetalert2.min.css'))?>">
<link rel="stylesheet" href="<?=h(versioned_asset('../assets/vendor/show-notify/showNotify.css', 'assets/vendor/show-notify/showNotify.css'))?>">
<link rel="stylesheet" href="<?=h(versioned_asset('admin.css', 'admin/admin.css'))?>">
</head>
<body>
<main class="auth-wrap">
<div class="auth-card auth-card-premium">
<div class="auth-brand-mark">
<?php if($logo!==''): ?><img src="../<?=h($logo)?>" alt=""><?php else: ?><span class="auth-brand-fallback">CMS</span><?php endif; ?>
</div>
<p class="eyebrow">SECURE PASSWORD RESET</p>
<h1>Create a new password</h1><?php
if(!$reset):
?>

<div class="alert error">This reset link is invalid, already used or expired.</div>
<a class="button" href="forgot-password.php">Request another link</a><?php
else:
?>

<p>Choose a new password for <strong><?=h($reset['username'])?>

</strong>. Your password is never sent by email.</p><?php
if($error):
?>

<div class="alert error"><?=h($error)?>

</div><?php
endif;
?>

<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="token" value="<?=h($token)?>">
<label>New password<input type="password" name="password" required minlength="10" autocomplete="new-password" autofocus>
</label>
<label>Repeat new password<input type="password" name="password2" required minlength="10" autocomplete="new-password">
</label>
<button type="submit">Update password</button>
</form><?php
endif;
?>

<a class="auth-back-link" href="login.php">← Back to login</a>
</div>
</main>
<script src="<?=h(versioned_asset('../assets/vendor/sweetalert2/sweetalert2.all.min.js', 'assets/vendor/sweetalert2/sweetalert2.all.min.js'))?>">
</script>
<script src="<?=h(versioned_asset('../assets/vendor/show-notify/showNotify.js', 'assets/vendor/show-notify/showNotify.js'))?>">
</script>
<script>document.querySelectorAll(".alert.success,.alert.error,.alert.info,.alert.warning").forEach(function(el){var t=el.classList.contains("error")?"error":el.classList.contains("warning")?"warning":el.classList.contains("success")?"success":"info";if(window.showNotify){showNotify(el.textContent.trim(),t);el.hidden=true;}});</script>
</body>
</html>
