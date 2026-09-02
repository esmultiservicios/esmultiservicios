<?php
require __DIR__.'/bootstrap.php';
if (admin_count()===0) { header('Location: setup.php'); exit; }
header('Location: '.(is_logged_in()?'dashboard.php':'login.php'));
