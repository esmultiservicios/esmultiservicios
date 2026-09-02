<?php
require __DIR__.'/bootstrap.php';
if(is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}
$id=(int)($_SESSION['escms_2fa_pending_id']??0);
if(!$id) {
    header('Location: login.php');
    exit;
}
$error='';
$set=settings();
$favicon=$set['favicon_path']??($set['admin_logo_path']??'');
$brand=$set['admin_brand_name']??"ES CMS Core Admin";
$logo=$set['admin_logo_path']??'';
$st=db()->prepare('SELECT id,username,two_factor_secret_enc,two_factor_enabled,active FROM admin_users WHERE id=?');
$st->execute([$id]);
$row=$st->fetch();
if(!$row||(int)$row['active']!==1||(int)$row['two_factor_enabled']!==1) {
    unset($_SESSION['escms_2fa_pending_id'],$_SESSION['escms_2fa_pending_user'],$_SESSION['escms_2fa_remember']);
    header('Location: login.php');
    exit;
}
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $code=trim((string)($_POST['code']??''));
    $secret=secret_decrypt((string)$row['two_factor_secret_enc']);
    if(verify_totp($secret,$code)) {
        session_regenerate_id(true);
        $_SESSION['escms_admin_id']=(int)$row['id'];
        $_SESSION['escms_admin_user']=$row['username'];
        $remember=!empty($_SESSION['escms_2fa_remember']);
        unset($_SESSION['escms_2fa_pending_id'],$_SESSION['escms_2fa_pending_user'],$_SESSION['escms_2fa_remember']);
        db()->prepare('UPDATE admin_users SET last_login_at=NOW(),last_login_ip=?,last_user_agent=? WHERE id=?')->execute([request_ip(),request_user_agent(),(int)$row['id']]);
        record_login_event((int)$row['id'],$row['username'],true);
        log_activity('login_2fa','Administrator signed in with two-factor authentication');
        admin_notify('info','Secure administrator login',$row['username'].' signed in with two-factor authentication.','security.php');
        if($remember)create_remember_token((int)$row['id']);
        else clear_remember_cookie();
        sync_admin_session();
        header('Location: dashboard.php');
        exit;
    }
    $error='The verification code is not valid. Try the current code from your authenticator app.';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" href="../<?=h($favicon)?>
">
<title>Two-factor verification</title>
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
<p class="eyebrow">TWO-FACTOR AUTHENTICATION</p>
<h1>Verify your sign-in</h1>
<p>Enter the 6-digit code from your authenticator app to continue to <?=h($brand)?>
.</p><?php
if($error):
?>
<div class="alert error"><?=h($error)?>
</div><?php
endif;
?>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<label>Authentication code<input class="totp-input" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
</label>
<button>Verify & continue</button>
</form>
<div class="auth-footer-note">Protected administrator access · two-factor verification</div>
</div>
</main>
<script src="../assets/vendor/show-notify/showNotify.js">
</script>
</body>
</html>
