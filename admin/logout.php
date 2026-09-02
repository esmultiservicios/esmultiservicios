<?php
require __DIR__.'/bootstrap.php';
try{if(is_logged_in()){db()->prepare('UPDATE admin_sessions SET revoked_at=NOW() WHERE session_hash=?')->execute([session_fingerprint()]);log_activity('logout','Administrator signed out');}}catch(Throwable $e){}
clear_remember_cookie();
$_SESSION=[];
if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);}
session_destroy();
header('Location: login.php');exit;
