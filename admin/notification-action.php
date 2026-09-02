<?php
require __DIR__.'/bootstrap.php';
require_login();
$id=(int)($_GET['id']??0);
if($id) {
    mark_notification_read($id);
    $st=db()->prepare('SELECT action_url FROM admin_notifications WHERE id=?');
    $st->execute([$id]);
    $url=(string)$st->fetchColumn();
} else {
    $url='notifications.php';
}
header('Location: '.($url?:'notifications.php'));
exit;
