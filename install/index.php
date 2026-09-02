<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$lock=$root.'/config/install.lock';
$dbFile=$root.'/config/database.php';
$error='';
$done=false;
if(is_file($lock)&&is_file($dbFile)) {
    header('Location: ../admin/');
    exit;
}
if($_SERVER['REQUEST_METHOD']==='POST') {
    $host=trim((string)($_POST['host']??'localhost'));
    $dbname=trim((string)($_POST['dbname']??''));
    $user=trim((string)($_POST['username']??''));
    $pass=(string)($_POST['password']??'');
    $create=isset($_POST['create_database']);
    try {
        if($dbname===''||$user==='')throw new RuntimeException('Database name and user are required.');
        if(!preg_match('/^[A-Za-z0-9_\-]+$/',$dbname))throw new RuntimeException('Database name may contain only letters, numbers, underscore and hyphen.');
        if($create) {
            $server=new PDO('mysql:host='.$host.';charset=utf8mb4',$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            $server->exec('CREATE DATABASE IF NOT EXISTS `'.str_replace('`','',$dbname).'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        }
        $pdo=new PDO('mysql:host='.$host.';dbname='.$dbname.';charset=utf8mb4',$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $sql=(string)file_get_contents($root.'/database.sql');
        $sql=preg_replace('/^\s*--.*$/m','',$sql);
        $statements=preg_split('/;\s*(?:\r?\n|$)/',$sql);
        foreach($statements as $stmt) {
            $stmt=trim($stmt);
            if($stmt===''||str_starts_with($stmt,'--'))continue;
            $pdo->exec($stmt);
        }
        $cfg="
<?php
\nreturn ".var_export(['host'=>$host,'dbname'=>$dbname,'username'=>$user,'password'=>$pass,'charset'=>'utf8mb4'],true).";
\n";if(file_put_contents($dbFile,$cfg,LOCK_EX)===false)throw new RuntimeException('Could not write config/database.php.');@chmod($dbFile,0600);$key=base64_encode(random_bytes(32));file_put_contents($root.'/config/app.key',$key,LOCK_EX);@chmod($root.'/config/app.key',0600);file_put_contents($lock,date('c'));$done=true;
 }catch(Throwable $e){$msg=$e->getMessage();if($create&&stripos($msg,'denied')!==false)$msg.=' Your hosting account may not allow CREATE DATABASE from PHP. Create the database in cPanel, then run the wizard again with “Create database” turned off.';$error=$msg;}
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ES CMS Core Setup</title>
<style>
* {
  box-sizing:border-box
}
body {
  margin:0;
  background:#f3f5f3;
  font-family:Inter,Arial,sans-serif;
  color:#20302f
}
.wrap {
  min-height:100vh;
  display:grid;
  place-items:center;
  padding:24px
}
.card {
  width:min(780px,100%);
  background:#fff;
  border:1px solid #dfe5e1;
  border-radius:26px;
  padding:clamp(24px,5vw,44px);
  box-shadow:0 24px 70px rgba(15,60,57,.12)
}
.steps {
  display:flex;
  gap:8px;
  margin-bottom:26px
}
.step {
  height:5px;
  flex:1;
  border-radius:10px;
  background:#dfe5e1
}
.step.on {
  background:#0f7777
}
.brand {
  font-size:12px;
  letter-spacing:.14em;
  color:#0f7777;
  font-weight:800
}
h1 {
  font-size:clamp(28px,5vw,42px);
  margin:8px 0 10px
}
.muted {
  color:#667170;
  line-height:1.6
}
.grid {
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:16px;
  margin-top:22px
}
label {
  display:grid;
  gap:7px;
  font-size:13px;
  font-weight:700
}
input {
  width:100%;
  min-height:48px;
  border:1px solid #cfd9d4;
  border-radius:12px;
  padding:12px;
  font:inherit
}
button,a.btn {
  margin-top:20px;
  width:100%;
  min-height:50px;
  border:0;
  border-radius:12px;
  background:#0f7777;
  color:#fff;
  font-weight:800;
  font-size:15px;
  display:flex;
  align-items:center;
  justify-content:center;
  text-decoration:none
}
.note {
  background:#f8faf9;
  border-left:4px solid #f2d45c;
  padding:14px;
  border-radius:10px;
  margin-top:16px
}
.error {
  background:#fff0f0;
  color:#8e292d;
  padding:13px;
  border-radius:10px;
  margin-top:16px
}
.switch {
  display:flex;
  align-items:flex-start;
  gap:12px;
  margin-top:18px;
  padding:15px;
  border:1px solid #d9e4df;
  border-radius:14px;
  background:#f8fbfa
}
.switch input {
  width:20px;
  min-height:20px;
  margin-top:2px
}
.switch span {
  display:grid;
  gap:3px
}
.switch small {
  color:#667170;
  font-weight:500
}
@media(max-width:620px) {
  .grid {
    grid-template-columns:1fr
  }
  .wrap {
    padding:14px
  }
}
</style>
</head>
<body>
<div class="wrap">
<main class="card">
<div class="steps">
<span class="step on">
</span>
<span class="step <?=$done?'on':''?>">
</span>
<span class="step <?=$done?'on':''?>">
</span>
</div>
<div class="brand">ES CMS CORE · FIRST-TIME SETUP</div><?php
if($done):
?>

<h1>Database ready.</h1>
<p class="muted">The CMS schema, default content, encryption key and database configuration were created successfully.</p>
<div class="note">Next step: create the first administrator account.</div>
<a class="btn" href="../admin/setup.php">Create administrator</a><?php
else:
?>

<h1>Connect the CMS to MySQL.</h1>
<p class="muted">This assistant can connect to an existing database or try to create the database automatically when your MySQL account has permission.</p><?php
if($error):
?>

<div class="error"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?>

</div><?php
endif;
?>

<form method="post">
<div class="grid">
<label>Database host<input name="host" value="<?=htmlspecialchars($_POST['host']??'localhost',ENT_QUOTES)?>" required>
</label>
<label>Database name<input name="dbname" value="<?=htmlspecialchars($_POST['dbname']??'',ENT_QUOTES)?>" required>
</label>
<label>Database user<input name="username" value="<?=htmlspecialchars($_POST['username']??'',ENT_QUOTES)?>" required>
</label>
<label>Database password<input type="password" name="password" autocomplete="new-password">
</label>
</div>
<label class="switch">
<input type="checkbox" name="create_database" <?=isset($_POST['create_database'])?'checked':''?>

>
<span>
<strong>Try to create the database automatically</strong>
<small>Enable only if this MySQL user has CREATE DATABASE permission. On most cPanel hosting plans you create the database first in cPanel and leave this off.</small>
</span>
</label>
<div class="note">
<strong>The wizard always creates:</strong> all CMS tables, default content, config/database.php, encryption key, and the installation lock.</div>
<button>Configure database & continue</button>
</form><?php
endif;
?>

</main>
</div>
</body>
</html>
