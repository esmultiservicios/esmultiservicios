<?php
require __DIR__.'/bootstrap.php';
require_login();
$pdo=db();
$admin=current_admin();
$error='';
$st=$pdo->prepare('SELECT * FROM admin_users WHERE id=?');
$st->execute([(int)$admin['id']]);
$row=$st->fetch();
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $action=(string)($_POST['action']??'');
    $current=(string)($_POST['current_password']??'');
    if(!password_verify($current,$row['password_hash']))$error='Current password is incorrect.';
    elseif($action==='profile') {
        $username=trim((string)($_POST['username']??''));
        $full=trim((string)($_POST['full_name']??''));
        $email=trim((string)($_POST['email']??''));
        if(strlen($username)<4)$error='Username must have at least 4 characters.';
        elseif($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))$error='Enter a valid email address.';
        else {
            $avatar=$row['avatar_path']??'';
            if(!empty($_FILES['avatar']['name']))$avatar=upload_image($_FILES['avatar'],'admin','avatar',5);
            $up=$pdo->prepare('UPDATE admin_users SET username=?,full_name=?,email=?,avatar_path=? WHERE id=?');
            $up->execute([$username,$full,$email,$avatar,$row['id']]);
            $_SESSION['escms_admin_user']=$username;
            flash('success','Profile updated.');
            header('Location: profile.php');
            exit;
        }
    } elseif($action==='password') {
        $n=(string)($_POST['new_password']??'');
        $n2=(string)($_POST['new_password2']??'');
        if(strlen($n)<10)$error='New password must have at least 10 characters.';
        elseif($n!==$n2)$error='New passwords do not match.';
        else {
            $pdo->prepare('UPDATE admin_users SET password_hash=? WHERE id=?')->execute([password_hash($n,PASSWORD_DEFAULT),$row['id']]);
            session_regenerate_id(true);
            sync_admin_session();
            $pdo->prepare('UPDATE admin_sessions SET revoked_at=NOW() WHERE admin_id=? AND session_hash<>? AND revoked_at IS NULL')->execute([$row['id'],session_fingerprint()]);
            flash('success','Password updated and your other sessions were signed out.');
            header('Location: profile.php');
            exit;
        }
    } elseif($action==='prepare_2fa') {
        $_SESSION['escms_pending_totp_secret']=new_totp_secret();
        flash('success','Authenticator setup key generated. Add it to your authenticator app, then verify a code.');
        header('Location: profile.php#two-factor');
        exit;
    } elseif($action==='enable_2fa') {
        $secret=(string)($_SESSION['escms_pending_totp_secret']??'');
        $code=trim((string)($_POST['totp_code']??''));
        if($secret===''||!verify_totp($secret,$code))$error='The authenticator code is not valid. Generate a setup key and enter the current 6-digit code.';
        else {
            $pdo->prepare('UPDATE admin_users SET two_factor_secret_enc=?,two_factor_enabled=1 WHERE id=?')->execute([secret_encrypt($secret),$row['id']]);
            unset($_SESSION['escms_pending_totp_secret']);
            log_activity('2fa_enable','Enabled two-factor authentication');
            admin_notify('success','Two-factor authentication enabled','Two-factor authentication was enabled for '.$row['username'].'.','profile.php');
            flash('success','Two-factor authentication is now enabled.');
            header('Location: profile.php#two-factor');
            exit;
        }
    } elseif($action==='disable_2fa') {
        $pdo->prepare('UPDATE admin_users SET two_factor_secret_enc=NULL,two_factor_enabled=0 WHERE id=?')->execute([$row['id']]);
        unset($_SESSION['escms_pending_totp_secret']);
        log_activity('2fa_disable','Disabled two-factor authentication');
        admin_notify('warning','Two-factor authentication disabled','Two-factor authentication was disabled for '.$row['username'].'.','profile.php');
        flash('success','Two-factor authentication disabled.');
        header('Location: profile.php#two-factor');
        exit;
    } elseif($action==='reset_admin') {
        if(!role_is_owner()) {
            $error='Only the Owner can reset administrator ownership.';
        } else {
            $confirm=trim((string)($_POST['confirmation']??''));
            if($confirm!=='RESET ADMIN')$error='Type RESET ADMIN exactly to confirm.';
            else {
                try {
                    require_once __DIR__.'/../core/EmailService.php';
                    $settings=settings();
                    if(!empty($row['email'])&&filter_var($row['email'],FILTER_VALIDATE_EMAIL)) {
                        (new EmailService())->sendWithFallback([2,1],$row['email'],'Administrator access reset',EmailTemplates::adminReset($row['full_name']?:$row['username'],$settings));
                    }
                } catch(Throwable $e) {
                }
                $pdo->exec('DELETE FROM admin_users');
                $_SESSION=[];
                session_destroy();
                header('Location: setup.php?reset=1');
                exit;
            }
        }
    }
}
$pageTitle='Profile & Security';
$active='profile';
require __DIR__.'/_header.php';
?>

<div class="page-heading">
<div>
<p class="eyebrow">PROFILE & SECURITY</p>
<h1>Your administrator account</h1>
<p class="muted">Update your identity, login credentials and client handoff settings.</p>
</div>
</div><?php
if($error):
?>
<div class="alert error"><?=h($error)?>
</div><?php
endif;
?>

<div class="content-grid">
<section class="panel animate-in">
<div class="panel-heading">
<div class="panel-icon"><?=icon('user')?>
</div>
<div>
<h2>Profile information</h2>
<p>This information appears in the admin header.</p>
</div>
</div>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<input type="hidden" name="action" value="profile">
<div class="two-col">
<label>Full name<input name="full_name" value="<?=h($row['full_name']??'')?>
">
</label>
<label>Username<input name="username" required value="<?=h($row['username'])?>
">
</label>
</div>
<label>Email<input type="email" name="email" value="<?=h($row['email']??'')?>
">
</label>
<div class="upload-zone" data-upload-zone tabindex="0">
<div class="upload-icon"><?=icon('image')?>
</div>
<strong>Profile picture</strong>
<small data-upload-name>Drop, paste or choose image</small>
<input type="file" name="avatar" accept="image/jpeg,image/png,image/webp">
<div class="upload-preview" data-upload-preview>
</div>
</div>
<label>Current password to save<input type="password" name="current_password" required autocomplete="current-password">
</label>
<div class="form-actions">
<button>Save profile</button>
</div>
</form>
</section>
<section class="panel animate-in">
<div class="panel-heading">
<div class="panel-icon"><?=icon('gear')?>
</div>
<div>
<h2>Change password</h2>
<p>Use a unique password with at least 10 characters.</p>
</div>
</div>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<input type="hidden" name="action" value="password">
<label>Current password<input type="password" name="current_password" required>
</label>
<label>New password<input type="password" name="new_password" required minlength="10">
</label>
<label>Repeat new password<input type="password" name="new_password2" required minlength="10">
</label>
<div class="form-actions">
<button>Update password</button>
</div>
</form>
</section>
<section class="panel animate-in wide" id="two-factor">
<div class="panel-heading">
<div class="panel-icon"><?=icon('shield')?>
</div>
<div>
<h2>Two-factor authentication</h2>
<p>Add an authenticator app verification code after your password.</p>
</div>
</div><?php
$twoFactor=(int)($row['two_factor_enabled']??0)===1;
$pendingSecret=(string)($_SESSION['escms_pending_totp_secret']??'');
?>
<div class="two-factor-status">
<span class="badge <?=$twoFactor?'success':'closed'?>
"><?=$twoFactor?'Enabled':'Not enabled'?>
</span>
<p class="muted"><?=$twoFactor?'Your account requires an authenticator code at sign-in.':'Recommended for Owner and Administrator accounts.'?>
</p>
</div><?php
if(!$twoFactor&&!$pendingSecret):
?>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<input type="hidden" name="action" value="prepare_2fa">
<label>Current password<input type="password" name="current_password" required autocomplete="current-password">
</label>
<div class="form-actions">
<button>Set up authenticator</button>
</div>
</form><?php
elseif(!$twoFactor):
?>
<?php
$issuer=rawurlencode(setting('admin_brand_name',"ES CMS Core Admin"));
$account=rawurlencode($row['email']?:$row['username']);
$uri='otpauth://totp/'.$issuer.':'.$account.'?secret='.rawurlencode($pendingSecret).'&issuer='.$issuer.'&digits=6&period=30';
?>
<div class="totp-setup">
<div>
<small>MANUAL SETUP KEY</small>
<code><?=h($pendingSecret)?>
</code>
<p>Add this key to Google Authenticator, Microsoft Authenticator, 1Password or another TOTP app.</p>
<a class="button secondary small" href="<?=h($uri)?>
">Open authenticator app</a>
</div>
</div>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<input type="hidden" name="action" value="enable_2fa">
<label>Current password<input type="password" name="current_password" required>
</label>
<label>6-digit code<input class="totp-input" name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
</label>
<div class="form-actions">
<button>Verify & enable 2FA</button>
</div>
</form><?php
else:
?>
<form method="post" data-swal-confirm="Disable two-factor authentication?" data-swal-text="Your account will return to password-only sign-in.">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<input type="hidden" name="action" value="disable_2fa">
<label>Current password<input type="password" name="current_password" required>
</label>
<div class="form-actions">
<button class="button danger-lite">Disable 2FA</button>
</div>
</form><?php
endif;
?>
</section>
<?php
if(role_is_owner()):
?>
<section class="panel danger-panel wide animate-in" id="handoff">
<div class="panel-heading">
<div class="panel-icon">↻</div>
<div>
<h2>Reset administrator ownership</h2>
<p>Use only when handing the website to a new owner.</p>
</div>
</div>
<p class="muted">This removes administrator accounts and signs you out. It does <strong>not</strong> delete website content, gallery images, services, settings, email configuration or estimate requests.</p>
<form method="post" data-confirm-text="RESET ADMIN" data-swal-confirm="Reset administrator ownership?" data-swal-text="All administrator accounts will be removed and you will be signed out. Website content will remain intact.">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>
">
<input type="hidden" name="action" value="reset_admin">
<div class="two-col">
<label>Current password<input type="password" name="current_password" required>
</label>
<label>Type RESET ADMIN<input name="confirmation" required autocomplete="off">
</label>
</div>
<div class="form-actions">
<button class="button danger">Reset administrator setup</button>
</div>
</form>
</section><?php
endif;
?>
</div>
<?php
require __DIR__.'/_footer.php';
