<?php
require __DIR__.'/bootstrap.php';
if(admin_count()===0) {
    header('Location: setup.php');
    exit;
}
if(is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}
$error='';
$set=settings();
$favicon=trim((string)($set['favicon_path']??''))?:'assets/brand/favicon.png';
$brand=$set['admin_brand_name']??"ES CMS Core Admin";
$logo=$set['admin_logo_path']??'';
$rememberedUsername=remember_username();
$rememberedEnabled=$rememberedUsername!=='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $u=trim((string)($_POST['username']??''));
    $p=(string)($_POST['password']??'');
    $st=db()->prepare('SELECT id,username,password_hash,active,two_factor_enabled,two_factor_secret_enc FROM admin_users WHERE username=? LIMIT 1');
    $st->execute([$u]);
    $row=$st->fetch();
    if($row&&(int)$row['active']===1&&password_verify($p,$row['password_hash'])) {
        if((int)($row['two_factor_enabled']??0)===1&&!empty($row['two_factor_secret_enc'])) {
            session_regenerate_id(true);
            $_SESSION['escms_2fa_pending_id']=(int)$row['id'];
            $_SESSION['escms_2fa_pending_user']=$row['username'];
            $_SESSION['escms_2fa_remember']=isset($_POST['remember_me'])?1:0;
            header('Location: two-factor.php');
            exit;
        }
        session_regenerate_id(true);
        $_SESSION['escms_admin_id']=(int)$row['id'];
        $_SESSION['escms_admin_user']=$row['username'];
        db()->prepare('UPDATE admin_users SET last_login_at=NOW(),last_login_ip=?,last_user_agent=? WHERE id=?')->execute([request_ip(),request_user_agent(),(int)$row['id']]);
        record_login_event((int)$row['id'],$u,true);
        log_activity('login','Administrator signed in');
        admin_notify('info','Administrator login',($row['username']??'Administrator').' signed in to the CMS.','security.php');
        if(isset($_POST['remember_me'])) {
            create_remember_token((int)$row['id']);
            save_remember_username((string)$row['username']);
        } else {
            clear_remember_cookie();
            clear_remember_username();
        }
        sync_admin_session();
        header('Location: dashboard.php');
        exit;
    }
    record_login_event($row?(int)$row['id']:null,$u,false);
    $error=($row&&(int)($row['active']??0)!==1)?'This account is disabled. Contact an administrator.':'Invalid username or password.';
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
<title>Admin Login</title>
<link rel="stylesheet" href="../assets/vendor/sweetalert2/sweetalert2.min.css">
<link rel="stylesheet" href="../assets/vendor/show-notify/showNotify.css">
<link rel="stylesheet" href="admin.css">
</head>
<body>
<main class="auth-wrap">
<div class="auth-card auth-card-premium">
<div class="auth-brand-mark">
<?php if($logo!==''): ?><img src="../<?=h($logo)?>" alt=""><?php else: ?><span class="auth-brand-fallback">CMS</span><?php endif; ?>
</div>
<p class="eyebrow">SECURE ADMINISTRATION</p>
<h1><?=h($brand)?>

</h1>
<p>Manage website content, customer requests and system settings.</p><?php
if(isset($_GET['setup'])):
?>

<div class="alert success">Administrator created. You can log in now.</div><?php
endif;
?>
<?php
if(isset($_GET['reset'])):
?>

<div class="alert success">Password updated. Sign in with your new password.</div><?php
endif;
?>
<?php
if(isset($_GET['revoked'])):
?>

<div class="alert warning">That administrator session was signed out from the Security Center.</div><?php
endif;
?>
<?php
if(isset($_GET['disabled'])):
?>

<div class="alert warning">Your administrator account is disabled.</div><?php
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
<label>Username<input name="username" value="<?=h((string)($_POST['username']??$rememberedUsername))?>" required autocomplete="username" autofocus>
</label>
<label>Password<input type="password" name="password" required autocomplete="current-password">
</label>
<div class="auth-options">
<label class="remember-check cr-check">
<input type="checkbox" name="remember_me" value="1"<?=isset($_POST['remember_me'])||($_SERVER['REQUEST_METHOD']!=='POST'&&$rememberedEnabled)?' checked':''?>>
<span class="cr-check-box" aria-hidden="true">
</span>
<span class="cr-check-text">Remember me</span>
</label>
<a href="forgot-password.php">Forgot password?</a>
</div>
<button type="submit">Log in securely</button>
</form>
<div class="auth-footer-note">Protected administrator access · ES CMS Core</div>
</div>
</main>
<script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js">
</script>
<script src="../assets/vendor/show-notify/showNotify.js">
</script>
<script>document.querySelectorAll(".alert.success,.alert.error,.alert.info,.alert.warning").forEach(function(el){var t=el.classList.contains("error")?"error":el.classList.contains("warning")?"warning":el.classList.contains("success")?"success":"info";if(window.showNotify){showNotify(el.textContent.trim(),t);el.hidden=true;}});</script>
</body>
</html>
