<?php
require __DIR__.'/bootstrap.php';
if (admin_count()===0) { header('Location: /admin/setup.php'); exit; }
header('Location: '.(is_logged_in()?'/admin/dashboard.php':'/admin/login.php'));
