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
require_once __DIR__.'/../core/EmailService.php';
$error='';
$success='';
$set=settings();
$favicon=trim((string)($set['favicon_path']??''))?:'assets/brand/favicon.png';
$brand=$set['admin_brand_name']??"ES CMS Core Admin";
$logo=$set['admin_logo_path']??'';
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $email=trim((string)($_POST['email']??''));
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)) {
        $error='Enter a valid administrator email address.';
    } else {
        $st=db()->prepare('SELECT id,username,full_name,email FROM admin_users WHERE email=? LIMIT 1');
        $st->execute([$email]);
        $admin=$st->fetch();
        if($admin) {
            db()->prepare('DELETE FROM admin_password_resets WHERE admin_id=? OR expires_at<NOW()')->execute([(int)$admin['id']]);
            $token=bin2hex(random_bytes(32));
            $hash=hash('sha256',$token);
            $expires=date('Y-m-d H:i:s',time()+3600);
            db()->prepare('INSERT INTO admin_password_resets(admin_id,token_hash,expires_at) VALUES(?,?,?)')->execute([(int)$admin['id'],$hash,$expires]);
            $scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
            $host=$_SERVER['HTTP_HOST']??'localhost';
            $dir=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/admin/')),'/');
            $url=$scheme.'://'.$host.$dir.'/reset-password.php?token='.rawurlencode($token);
            $mailer=new EmailService();
            $result=$mailer->sendWithFallback([2,1],$email,'Reset your ES MULTISERVICIOS administrator password',EmailTemplates::passwordReset($admin['full_name']?:$admin['username'],$url,$set));
            if(!$result['success']) {
                $error='The reset link could not be emailed. Configure an active “Admin Security” or “Website Notifications” sender first. '.$result['message'];
            } else {
                $success='A secure reset link was sent to your administrator email. It expires in 60 minutes.';
            }
        } else {
            $success='If that email belongs to an administrator, a reset link will be sent.';
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
<title>Forgot Password</title>
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
<p class="eyebrow">ACCOUNT RECOVERY</p>
<h1>Reset your password</h1>
<p>Enter the email saved in your administrator profile. We will send a one-time secure link.</p><?php
if($success):
?>

<div class="alert success"><?=h($success)?>

</div><?php
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
<label>Administrator email<input type="email" name="email" required autocomplete="email" autofocus>
</label>
<button type="submit">Send reset link</button>
</form>
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
