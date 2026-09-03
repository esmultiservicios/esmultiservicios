<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$lock = $root . '/config/install.lock';
$dbFile = $root . '/config/database.php';
$error = '';
$done = false;

if (is_file($lock) && is_file($dbFile)) {
    header('Location: ../admin/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim((string) ($_POST['host'] ?? 'localhost'));
    $dbname = trim((string) ($_POST['dbname'] ?? ''));
    $user = trim((string) ($_POST['username'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');
    $create = isset($_POST['create_database']);

    try {
        if ($dbname === '' || $user === '') {
            throw new RuntimeException('Database name and user are required.');
        }

        if (!preg_match('/^[A-Za-z0-9_\-]+$/', $dbname)) {
            throw new RuntimeException('Database name may contain only letters, numbers, underscore and hyphen.');
        }

        if ($create) {
            $server = new PDO(
                'mysql:host=' . $host . ';charset=utf8mb4',
                $user,
                $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $server->exec(
                'CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $dbname) .
                '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );
        }

        $pdo = new PDO(
            'mysql:host=' . $host . ';dbname=' . $dbname . ';charset=utf8mb4',
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $sql = (string) file_get_contents($root . '/database.sql');
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);

        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || str_starts_with($stmt, '--')) {
                continue;
            }
            $pdo->exec($stmt);
        }

        $cfg = "<?php\n\nreturn " . var_export([
            'host' => $host,
            'dbname' => $dbname,
            'username' => $user,
            'password' => $pass,
            'charset' => 'utf8mb4',
        ], true) . ";\n";

        if (file_put_contents($dbFile, $cfg, LOCK_EX) === false) {
            throw new RuntimeException('Could not write config/database.php.');
        }
        @chmod($dbFile, 0600);

        $key = base64_encode(random_bytes(32));
        if (file_put_contents($root . '/config/app.key', $key, LOCK_EX) === false) {
            throw new RuntimeException('Could not write config/app.key.');
        }
        @chmod($root . '/config/app.key', 0600);

        if (file_put_contents($lock, date('c'), LOCK_EX) === false) {
            throw new RuntimeException('Could not write config/install.lock.');
        }

        $done = true;
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if ($create && stripos($msg, 'denied') !== false) {
            $msg .= ' Your hosting account may not allow CREATE DATABASE from PHP. Create the database in cPanel, then run the wizard again with “Create database” turned off.';
        }
        $error = $msg;
    }
}

$currentStep = $done ? 2 : 1;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ES MULTISERVICIOS CMS Setup</title>
<link rel="icon" type="image/png" href="../assets/brand/favicon.png">
<link rel="shortcut icon" href="../assets/brand/favicon.png">
<style>
:root {
  --primary:#0c78aa;
  --primary-dark:#0b2e59;
  --accent:#f28c28;
  --ink:#172b44;
  --muted:#65788d;
  --surface:#ffffff;
  --page:#f4f7fb;
  --line:#dce5ef;
  --soft:#f7fbfd;
}
* {
  box-sizing:border-box;
}
html {
  -webkit-text-size-adjust:100%;
}
body {
  margin:0;
  background:var(--page);
  font-family:Inter,Arial,sans-serif;
  color:var(--ink);
}
button,
a,
input,
label {
  -webkit-tap-highlight-color:transparent;
}
button,
a.btn,
.switch input {
  cursor:pointer;
}
.wrap {
  min-height:100vh;
  display:grid;
  place-items:center;
  padding:clamp(14px,3vw,32px);
}
.card {
  width:min(860px,100%);
  background:var(--surface);
  border:1px solid var(--line);
  border-radius:26px;
  padding:clamp(22px,5vw,46px);
  box-shadow:0 24px 70px rgba(15,60,57,.12);
}
.wizard-head {
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:20px;
  margin-bottom:24px;
}
.brand {
  font-size:12px;
  letter-spacing:.14em;
  color:var(--primary);
  font-weight:800;
}
.step-count {
  flex:0 0 auto;
  padding:8px 12px;
  border:1px solid #cfe1dc;
  border-radius:999px;
  background:#eef8f5;
  color:var(--primary-dark);
  font-size:13px;
  font-weight:800;
  white-space:nowrap;
}
.stepper {
  display:grid;
  grid-template-columns:repeat(3,minmax(0,1fr));
  gap:10px;
  margin-bottom:30px;
}
.step-item {
  position:relative;
  min-width:0;
  display:flex;
  align-items:center;
  gap:10px;
  padding:12px;
  border:1px solid var(--line);
  border-radius:15px;
  background:#fafcfb;
}
.step-item.active {
  border-color:#9fd0c7;
  background:#edf8f5;
}
.step-item.complete {
  border-color:#b9ddd5;
  background:#f4fbf9;
}
.step-number {
  width:32px;
  height:32px;
  flex:0 0 32px;
  display:grid;
  place-items:center;
  border-radius:50%;
  background:#e5ebe8;
  color:#62706e;
  font-size:13px;
  font-weight:900;
}
.step-item.active .step-number,
.step-item.complete .step-number {
  background:var(--primary);
  color:#fff;
}
.step-copy {
  min-width:0;
  display:grid;
  gap:2px;
}
.step-copy strong {
  font-size:13px;
  line-height:1.25;
}
.step-copy small {
  color:var(--muted);
  font-size:11px;
  line-height:1.3;
}
h1 {
  font-size:clamp(28px,5vw,42px);
  line-height:1.08;
  margin:8px 0 10px;
}
.muted {
  color:var(--muted);
  line-height:1.65;
  margin-bottom:0;
}
.grid {
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:16px;
  margin-top:24px;
}
label {
  display:grid;
  gap:7px;
  font-size:13px;
  font-weight:700;
}
input {
  width:100%;
  min-height:50px;
  border:1px solid #cfd9d4;
  border-radius:12px;
  padding:12px 14px;
  background:#fff;
  color:var(--ink);
  font:inherit;
  outline:none;
  transition:border-color .18s ease,box-shadow .18s ease;
}
input:focus {
  border-color:var(--primary);
  box-shadow:0 0 0 4px rgba(15,119,119,.10);
}
button,
a.btn {
  margin-top:20px;
  width:100%;
  min-height:52px;
  border:0;
  border-radius:12px;
  background:var(--primary);
  color:#fff;
  font-weight:800;
  font-size:15px;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:10px;
  text-decoration:none;
  box-shadow:0 10px 24px rgba(15,119,119,.16);
  transition:transform .18s ease,background .18s ease,box-shadow .18s ease;
}
button:hover,
a.btn:hover {
  background:var(--primary-dark);
  transform:translateY(-1px);
  box-shadow:0 13px 28px rgba(15,119,119,.20);
}
button:focus-visible,
a.btn:focus-visible {
  outline:3px solid rgba(242,212,92,.75);
  outline-offset:3px;
}
.btn-icon {
  display:inline-grid;
  place-items:center;
  width:25px;
  height:25px;
  border-radius:50%;
  background:rgba(255,255,255,.14);
  font-size:18px;
  line-height:1;
}
.note {
  background:#f8faf9;
  border-left:4px solid var(--accent);
  padding:15px 16px;
  border-radius:10px;
  margin-top:18px;
  color:#435150;
  line-height:1.55;
}
.error {
  background:#fff0f0;
  color:#8e292d;
  padding:13px 15px;
  border:1px solid #f4c9ca;
  border-radius:10px;
  margin-top:18px;
  line-height:1.5;
}
.switch {
  display:flex;
  align-items:flex-start;
  gap:12px;
  margin-top:18px;
  padding:15px;
  border:1px solid #d9e4df;
  border-radius:14px;
  background:var(--soft);
}
.switch input {
  width:20px;
  min-height:20px;
  margin:2px 0 0;
  flex:0 0 20px;
}
.switch span {
  display:grid;
  gap:4px;
}
.switch small {
  color:var(--muted);
  font-weight:500;
  line-height:1.45;
}
.success-panel {
  margin-top:24px;
  display:grid;
  grid-template-columns:auto 1fr;
  gap:14px;
  align-items:start;
  padding:18px;
  border:1px solid #b9ddd5;
  border-radius:16px;
  background:#f4fbf9;
}
.success-icon {
  width:42px;
  height:42px;
  display:grid;
  place-items:center;
  border-radius:50%;
  background:var(--primary);
  color:#fff;
  font-size:21px;
  font-weight:900;
}
.success-panel strong {
  display:block;
  margin-bottom:4px;
}
.success-panel p {
  margin:0;
  color:var(--muted);
  line-height:1.55;
}
@media (max-width:700px) {
  .wizard-head {
    display:grid;
    gap:12px;
  }
  .step-count {
    width:max-content;
  }
  .stepper {
    grid-template-columns:1fr;
    gap:7px;
  }
  .step-item {
    padding:10px 12px;
  }
  .grid {
    grid-template-columns:1fr;
  }
}
@media (max-width:420px) {
  .wrap {
    padding:10px;
  }
  .card {
    padding:20px 16px;
    border-radius:20px;
  }
  h1 {
    font-size:28px;
  }
  button,
  a.btn {
    min-height:54px;
  }
}
@media (prefers-reduced-motion:reduce) {
  *,*::before,*::after {
    scroll-behavior:auto!important;
    transition:none!important;
  }
}
</style>
</head>
<body>
<div class="wrap">
<main class="card">
  <div class="wizard-head">
    <div>
      <div class="brand">CMS CORE · FIRST-TIME SETUP</div>
    </div>
    <div class="step-count">Step <?= $currentStep ?> of 3</div>
  </div>

  <div class="stepper" aria-label="Installation progress">
    <div class="step-item <?= $currentStep >= 1 ? ($currentStep > 1 ? 'complete' : 'active') : '' ?>">
      <span class="step-number"><?= $currentStep > 1 ? '✓' : '1' ?></span>
      <span class="step-copy"><strong>Database</strong><small>Connect MySQL</small></span>
    </div>
    <div class="step-item <?= $currentStep >= 2 ? ($currentStep > 2 ? 'complete' : 'active') : '' ?>">
      <span class="step-number"><?= $currentStep > 2 ? '✓' : '2' ?></span>
      <span class="step-copy"><strong>CMS ready</strong><small>Create configuration</small></span>
    </div>
    <div class="step-item">
      <span class="step-number">3</span>
      <span class="step-copy"><strong>Administrator</strong><small>Create Owner account</small></span>
    </div>
  </div>

<?php if ($done): ?>
  <h1>Database ready.</h1>
  <p class="muted">The complete CMS database, default content, encryption key and protected database configuration were created successfully.</p>

  <div class="success-panel">
    <span class="success-icon">✓</span>
    <div>
      <strong>Step 2 completed</strong>
      <p>Only one step remains: create the first Owner account that will administer the website.</p>
    </div>
  </div>

  <a class="btn" href="../admin/setup.php">
    <span>Create administrator</span>
    <span class="btn-icon" aria-hidden="true">→</span>
  </a>
<?php else: ?>
  <h1>Connect the CMS to MySQL.</h1>
  <p class="muted">This 3-step assistant prepares the complete CMS. Start by connecting an existing database or, when your MySQL account permits it, let the installer create the database.</p>

  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="post">
    <div class="grid">
      <label>
        Database host
        <input name="host" value="<?= htmlspecialchars($_POST['host'] ?? 'localhost', ENT_QUOTES) ?>" required>
      </label>
      <label>
        Database name
        <input name="dbname" value="<?= htmlspecialchars($_POST['dbname'] ?? '', ENT_QUOTES) ?>" required>
      </label>
      <label>
        Database user
        <input name="username" value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES) ?>" required>
      </label>
      <label>
        Database password
        <input type="password" name="password" autocomplete="new-password">
      </label>
    </div>

    <label class="switch">
      <input type="checkbox" name="create_database" <?= isset($_POST['create_database']) ? 'checked' : '' ?>>
      <span>
        <strong>Try to create the database automatically</strong>
        <small>Enable only if this MySQL user has CREATE DATABASE permission. On most cPanel hosting plans, create the database first in cPanel and leave this option off.</small>
      </span>
    </label>

    <div class="note">
      <strong>The assistant automatically creates:</strong> all current CMS tables and data from <code>database.sql</code>, <code>config/database.php</code>, the encryption key and the installation lock.
    </div>

    <button type="submit">
      <span>Configure database & continue</span>
      <span class="btn-icon" aria-hidden="true">→</span>
    </button>
  </form>
<?php endif; ?>
</main>
</div>
</body>
</html>
