<?php
declare(strict_types=1);
session_start();

const INSTALLER_VERSION = '2.0.0';

$root = dirname(__DIR__);
$configDir = $root . '/config';
$configFile = $configDir . '/config.php';
$legacyConfigFile = $configDir . '/database.php';
$lockFile = $configDir . '/install.lock';
$keyFile = $configDir . '/app.key';
$schemaFile = is_file($root . '/schema.sql') ? $root . '/schema.sql' : $root . '/database.sql';
$error = '';
$notice = '';

if (is_file($lockFile)) {
    header('Location: ../admin/login.php');
    exit;
}

if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}

function ih(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function install_verify_csrf(): void
{
    $posted = (string)($_POST['csrf'] ?? '');
    $stored = (string)($_SESSION['install_csrf'] ?? '');
    if ($stored === '' || !hash_equals($stored, $posted)) {
        throw new RuntimeException('La sesión del instalador expiró. Recarga la página e inténtalo nuevamente.');
    }
}

function install_detect_site_url(): string
{
    $forwarded = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || $forwarded === 'https';
    $scheme = $https ? 'https' : 'http';
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/install/index.php'));
    $basePath = preg_replace('~/install(?:/index\.php)?$~i', '', $script) ?? '';
    $basePath = rtrim($basePath, '/');

    // cPanel can keep the Git checkout in a folder named like the domain while
    // the public domain itself points at /public_html. Never persist that
    // physical checkout folder as part of the public URL.
    if ($basePath !== '') {
        $segments = array_values(array_filter(explode('/', trim($basePath, '/')), static fn($v) => $v !== ''));
        $hostOnly = strtolower(preg_replace('/:\d+$/', '', $host) ?? $host);
        $plainHost = preg_replace('/^www\./i', '', $hostOnly);
        while ($segments && strcasecmp((string)$segments[0], (string)$plainHost) === 0) array_shift($segments);
        $basePath = $segments ? '/' . implode('/', $segments) : '';
    }
    return $scheme . '://' . $host . $basePath;
}

function install_normalize_db_config(array $raw): array
{
    $db = isset($raw['database']) && is_array($raw['database']) ? $raw['database'] : $raw;
    return [
        'host' => (string)($db['host'] ?? 'localhost'),
        'port' => (int)($db['port'] ?? 3306),
        'dbname' => (string)($db['dbname'] ?? ''),
        'username' => (string)($db['username'] ?? ''),
        'password' => (string)($db['password'] ?? ''),
        'charset' => (string)($db['charset'] ?? 'utf8mb4'),
        'site_url' => (string)($raw['site_url'] ?? ''),
    ];
}

function install_existing_config(string $configFile, string $legacyFile): ?array
{
    foreach ([$configFile, $legacyFile] as $file) {
        if (!is_file($file)) continue;
        $raw = require $file;
        if (!is_array($raw)) {
            throw new RuntimeException('El archivo de configuración existente no devuelve una configuración válida.');
        }
        $normalized = install_normalize_db_config($raw);
        $normalized['_source'] = $file;
        return $normalized;
    }
    return null;
}

function install_connect(array $db, bool $withoutDatabase = false): PDO
{
    $host = trim((string)$db['host']);
    $port = (int)($db['port'] ?? 3306);
    $dbname = trim((string)$db['dbname']);
    $charset = trim((string)($db['charset'] ?? 'utf8mb4')) ?: 'utf8mb4';
    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';charset=' . $charset;
    if (!$withoutDatabase) {
        $dsn .= ';dbname=' . $dbname;
    }
    return new PDO($dsn, (string)$db['username'], (string)$db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function install_validate_db_input(?array $passwordSource, bool $reinstallMode): array
{
    $host = trim((string)($_POST['host'] ?? 'localhost'));
    $port = (int)($_POST['port'] ?? 3306);
    $dbname = trim((string)($_POST['dbname'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $postedPassword = (string)($_POST['password'] ?? '');
    $password = $postedPassword !== '' ? $postedPassword : (string)($passwordSource['password'] ?? '');

    if ($host === '' || $dbname === '' || $username === '') {
        throw new RuntimeException('Servidor, base de datos y usuario son obligatorios.');
    }
    if ($port < 1 || $port > 65535) {
        throw new RuntimeException('Ingresa un puerto MySQL válido.');
    }
    if (!preg_match('/^[A-Za-z0-9_\-]+$/', $dbname)) {
        throw new RuntimeException('El nombre de la base de datos solo puede contener letras, números, guion y guion bajo.');
    }

    return [
        'host' => $host,
        'port' => $port,
        'dbname' => $dbname,
        'username' => $username,
        'password' => $password,
        'charset' => 'utf8mb4',
        'create_database' => isset($_POST['create_database']) && !$reinstallMode,
    ];
}

function install_project_tables(string $sql): array
{
    preg_match_all('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?([A-Za-z0-9_]+)`?/i', $sql, $matches);
    $tables = array_values(array_unique($matches[1] ?? []));
    if (!$tables) {
        throw new RuntimeException('No se pudieron identificar las tablas propias del proyecto en el esquema.');
    }
    return $tables;
}

function install_split_sql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    $quote = null;
    $lineComment = false;
    $blockComment = false;

    for ($i = 0; $i < $length; $i++) {
        $ch = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($lineComment) {
            if ($ch === "\n") {
                $lineComment = false;
                $buffer .= $ch;
            }
            continue;
        }
        if ($blockComment) {
            if ($ch === '*' && $next === '/') {
                $blockComment = false;
                $i++;
            }
            continue;
        }
        if ($quote === null) {
            if ($ch === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2]))) {
                $lineComment = true;
                $i++;
                continue;
            }
            if ($ch === '#') {
                $lineComment = true;
                continue;
            }
            if ($ch === '/' && $next === '*') {
                $blockComment = true;
                $i++;
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $quote = $ch;
                $buffer .= $ch;
                continue;
            }
            if ($ch === ';') {
                $statement = trim($buffer);
                if ($statement !== '') $statements[] = $statement;
                $buffer = '';
                continue;
            }
            $buffer .= $ch;
            continue;
        }

        $buffer .= $ch;
        if ($ch === '\\' && $quote !== '`' && $i + 1 < $length) {
            $buffer .= $sql[++$i];
            continue;
        }
        if ($ch === $quote) {
            if ($i + 1 < $length && $sql[$i + 1] === $quote && $quote !== '`') {
                $buffer .= $sql[++$i];
                continue;
            }
            $quote = null;
        }
    }

    $tail = trim($buffer);
    if ($tail !== '') $statements[] = $tail;
    return $statements;
}

function install_apply_schema(PDO $pdo, string $schemaFile, bool $cleanProjectTables): void
{
    $sql = @file_get_contents($schemaFile);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('No se pudo leer el esquema de base de datos del proyecto.');
    }

    $tables = $cleanProjectTables ? install_project_tables($sql) : [];
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    try {
        if ($cleanProjectTables) {
            foreach (array_reverse($tables) as $table) {
                $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`');
            }
        }
        foreach (install_split_sql($sql) as $statement) {
            $pdo->exec($statement);
        }
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
}

function install_app_key_raw(string $keyFile): array
{
    if (is_file($keyFile)) {
        $raw = base64_decode(trim((string)file_get_contents($keyFile)), true);
        if ($raw === false || strlen($raw) < 32) {
            throw new RuntimeException('config/app.key existe pero no es válido.');
        }
        return [substr($raw, 0, 32), false];
    }
    return [random_bytes(32), true];
}

function install_encrypt(string $plain, string $key): string
{
    $plain = trim($plain);
    if ($plain === '') return '';
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        throw new RuntimeException('No se pudo cifrar una credencial del sistema.');
    }
    return 'v1:' . base64_encode($iv . $tag . $cipher);
}

function install_write_atomic(string $path, string $content, int $mode = 0600): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('No se pudo crear el directorio de configuración.');
    }
    $temp = $path . '.tmp.' . bin2hex(random_bytes(5));
    if (file_put_contents($temp, $content, LOCK_EX) === false) {
        throw new RuntimeException('No se pudo escribir el archivo temporal de configuración.');
    }
    @chmod($temp, $mode);
    if (!@rename($temp, $path)) {
        @unlink($temp);
        throw new RuntimeException('No se pudo activar el archivo de configuración. Revisa permisos de escritura.');
    }
}

function install_config_php(array $db, string $siteUrl): string
{
    $payload = [
        'site_url' => rtrim($siteUrl, '/'),
        'database' => [
            'host' => $db['host'],
            'port' => (int)$db['port'],
            'dbname' => $db['dbname'],
            'username' => $db['username'],
            'password' => $db['password'],
            'charset' => 'utf8mb4',
        ],
    ];
    return "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($payload, true) . ";\n";
}

function install_email_payload_from_post(?array $previous = null, bool $requirePurposes = true): array
{
    $choice = strtoupper(trim((string)($_POST['email_choice'] ?? 'LATER')));
    if (!in_array($choice, ['LATER', 'SMTP', 'GRAPH'], true)) $choice = 'LATER';
    if ($choice === 'LATER') return ['mode' => 'LATER'];

    $sender = trim((string)($_POST['sender_email'] ?? ''));
    if (!filter_var($sender, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Ingresa un correo remitente válido.');
    }

    $purposes = array_values(array_unique(array_map('intval', (array)($_POST['purposes'] ?? []))));
    $purposes = array_values(array_filter($purposes, static fn(int $id): bool => in_array($id, [1, 2, 3, 4], true)));
    if ($requirePurposes && !$purposes) throw new RuntimeException('Selecciona al menos un uso para la configuración de correo.');
    if (!$purposes) $purposes = $previous['purposes'] ?? [1, 2, 3, 4];

    if ($choice === 'SMTP') {
        $server = trim((string)($_POST['smtp_server'] ?? ''));
        $password = (string)($_POST['smtp_password'] ?? '');
        if ($password === '' && ($previous['mode'] ?? '') === 'SMTP') $password = (string)($previous['password_plain'] ?? '');
        $port = (int)($_POST['smtp_port'] ?? 587);
        $secure = strtolower(trim((string)($_POST['smtp_secure'] ?? 'tls')));
        if ($server === '' || $password === '') throw new RuntimeException('Servidor y contraseña SMTP son obligatorios.');
        if ($port < 1 || $port > 65535) throw new RuntimeException('Ingresa un puerto SMTP válido.');
        if (!in_array($secure, ['tls', 'ssl'], true)) throw new RuntimeException('Selecciona TLS o SSL para SMTP.');
        return [
            'mode' => 'SMTP', 'metodo_envio' => 'SMTP', 'server' => $server, 'correo' => $sender,
            'password_plain' => $password, 'port' => $port, 'smtp_secure' => $secure,
            'tenant_id' => null, 'client_id' => null, 'client_secret_plain' => '', 'graph_user' => null,
            'save_to_sent_items' => 0, 'purposes' => $purposes,
        ];
    }

    $tenant = trim((string)($_POST['tenant_id'] ?? ''));
    $client = trim((string)($_POST['client_id'] ?? ''));
    $secret = (string)($_POST['client_secret'] ?? '');
    if ($secret === '' && ($previous['mode'] ?? '') === 'GRAPH') $secret = (string)($previous['client_secret_plain'] ?? '');
    $graphUser = trim((string)($_POST['graph_user'] ?? '')) ?: $sender;
    if ($tenant === '' || $client === '' || $secret === '') {
        throw new RuntimeException('Tenant ID, Client ID y Client Secret son obligatorios para Microsoft Graph.');
    }
    if (!filter_var($graphUser, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Ingresa un buzón válido de Microsoft 365.');
    }
    return [
        'mode' => 'GRAPH', 'metodo_envio' => 'GRAPH', 'server' => 'graph.microsoft.com', 'correo' => $sender,
        'password_plain' => '', 'port' => 0, 'smtp_secure' => 'tls', 'tenant_id' => $tenant,
        'client_id' => $client, 'client_secret_plain' => $secret, 'graph_user' => $graphUser,
        'save_to_sent_items' => isset($_POST['save_to_sent_items']) ? 1 : 0, 'purposes' => $purposes,
    ];
}

function install_save_email(PDO $pdo, array $email, string $key): void
{
    if (($email['mode'] ?? 'LATER') === 'LATER') return;
    foreach ($email['purposes'] as $type) {
        $pdo->prepare('UPDATE correo SET estado=2 WHERE correo_tipo_id=?')->execute([$type]);
        $st = $pdo->prepare('INSERT INTO correo(correo_tipo_id,metodo_envio,server,correo,password,port,smtp_secure,tenant_id,client_id,client_secret,graph_user,save_to_sent_items,estado,fecha_registro) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,1,NOW())');
        $st->execute([
            $type, $email['metodo_envio'], $email['server'], $email['correo'],
            install_encrypt((string)$email['password_plain'], $key), (int)$email['port'], $email['smtp_secure'],
            $email['tenant_id'], $email['client_id'], install_encrypt((string)$email['client_secret_plain'], $key),
            $email['graph_user'], (int)$email['save_to_sent_items'],
        ]);
    }
}

function install_test_email(string $root, array $payload, string $to): array
{
    if (($payload['mode'] ?? 'LATER') === 'LATER') {
        throw new RuntimeException('Selecciona SMTP o Microsoft Graph para realizar una prueba.');
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Ingresa un correo destino válido para la prueba.');

    require_once $root . '/config/bootstrap.php';
    require_once $root . '/core/EmailService.php';
    $cfg = [
        'metodo_envio' => $payload['metodo_envio'], 'server' => $payload['server'], 'correo' => $payload['correo'],
        'password' => $payload['password_plain'], 'port' => $payload['port'], 'smtp_secure' => $payload['smtp_secure'],
        'tenant_id' => $payload['tenant_id'], 'client_id' => $payload['client_id'], 'client_secret' => $payload['client_secret_plain'],
        'graph_user' => $payload['graph_user'], 'save_to_sent_items' => $payload['save_to_sent_items'],
        'destinatario' => null, 'copia' => null,
    ];
    $service = new EmailService();
    return $service->send($cfg, $to, 'Prueba de correo del instalador', EmailTemplates::test($payload['metodo_envio'], ['company_name' => 'ES MULTISERVICIOS', 'site_url' => install_detect_site_url()]));
}

$detectedUrl = install_detect_site_url();
try {
    $existingConfig = install_existing_config($configFile, $legacyConfigFile);
} catch (Throwable $e) {
    $existingConfig = null;
    $error = $e->getMessage();
}
$reinstallMode = $existingConfig !== null;
$_SESSION['install_mode'] = $reinstallMode ? 'reinstall' : 'fresh';

$steps = ['database' => 1, 'admin' => 2, 'email' => 3, 'confirm' => 4];
$requestedStep = strtolower(trim((string)($_GET['step'] ?? 'database')));
if (!isset($steps[$requestedStep])) $requestedStep = 'database';

if ($requestedStep === 'admin' && empty($_SESSION['install_db'])) $requestedStep = 'database';
if ($requestedStep === 'email' && (empty($_SESSION['install_db']) || empty($_SESSION['install_admin']))) $requestedStep = empty($_SESSION['install_db']) ? 'database' : 'admin';
if ($requestedStep === 'confirm' && (empty($_SESSION['install_db']) || empty($_SESSION['install_admin']) || !isset($_SESSION['install_email']))) {
    $requestedStep = empty($_SESSION['install_db']) ? 'database' : (empty($_SESSION['install_admin']) ? 'admin' : 'email');
}
$currentStep = $steps[$requestedStep];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    try {
        install_verify_csrf();

        if ($action === 'database') {
            $passwordSource = is_array($_SESSION['install_db'] ?? null) ? $_SESSION['install_db'] : $existingConfig;
            $db = install_validate_db_input($passwordSource, $reinstallMode);
            if ($db['create_database']) {
                $server = install_connect($db, true);
                $server->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $db['dbname']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            }
            $pdo = install_connect($db);
            $pdo->query('SELECT 1')->fetchColumn();
            $_SESSION['install_db'] = $db;
            $_SESSION['install_site_url'] = $detectedUrl;
            header('Location: ?step=admin');
            exit;
        }

        if ($action === 'admin') {
            $fullName = trim((string)($_POST['full_name'] ?? ''));
            $email = trim((string)($_POST['admin_email'] ?? ''));
            $username = trim((string)($_POST['admin_username'] ?? ''));
            $password = (string)($_POST['admin_password'] ?? '');
            $password2 = (string)($_POST['admin_password2'] ?? '');
            $previousAdmin = is_array($_SESSION['install_admin'] ?? null) ? $_SESSION['install_admin'] : null;
            if (strlen($username) < 4) throw new RuntimeException('El usuario administrador debe tener al menos 4 caracteres.');
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Ingresa un correo válido para el administrador.');
            if ($password === '' && $previousAdmin) {
                $passwordHash = (string)$previousAdmin['password_hash'];
            } else {
                if (strlen($password) < 10) throw new RuntimeException('La contraseña del administrador debe tener al menos 10 caracteres.');
                if ($password !== $password2) throw new RuntimeException('Las contraseñas del administrador no coinciden.');
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            }
            $_SESSION['install_admin'] = [
                'full_name' => $fullName, 'email' => $email, 'username' => $username,
                'password_hash' => $passwordHash,
            ];
            header('Location: ?step=email');
            exit;
        }

        if ($action === 'test_email') {
            $payload = install_email_payload_from_post(is_array($_SESSION['install_email'] ?? null) ? $_SESSION['install_email'] : null, false);
            $result = install_test_email($root, $payload, trim((string)($_POST['test_to'] ?? '')));
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => (bool)($result['success'] ?? false),
                'message' => (string)($result['message'] ?? ((bool)($result['success'] ?? false) ? 'Prueba enviada correctamente.' : 'No se pudo completar la prueba.')),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'email') {
            $_SESSION['install_email'] = install_email_payload_from_post(is_array($_SESSION['install_email'] ?? null) ? $_SESSION['install_email'] : null, true);
            header('Location: ?step=confirm');
            exit;
        }

        if ($action === 'finalize') {
            $db = $_SESSION['install_db'] ?? null;
            $admin = $_SESSION['install_admin'] ?? null;
            $email = $_SESSION['install_email'] ?? null;
            if (!is_array($db) || !is_array($admin) || !is_array($email)) {
                throw new RuntimeException('La sesión del asistente está incompleta. Revisa los pasos anteriores.');
            }

            $siteUrl = (string)($_SESSION['install_site_url'] ?? $detectedUrl);
            $cleanProjectTables = ($_SESSION['install_mode'] ?? 'fresh') === 'reinstall' || !empty($_SESSION['install_fresh_retry']);
            $oldConfigExists = is_file($configFile);
            $oldConfigRaw = $oldConfigExists ? (string)file_get_contents($configFile) : null;
            $newKeyCreated = false;
            $configWritten = false;

            try {
                $pdo = install_connect($db);
                install_apply_schema($pdo, $schemaFile, $cleanProjectTables);

                $roleId = (int)$pdo->query("SELECT id FROM admin_roles WHERE role_key='owner' LIMIT 1")->fetchColumn();
                if ($roleId < 1) throw new RuntimeException('No se encontró el rol Owner después de cargar el esquema.');
                $st = $pdo->prepare('INSERT INTO admin_users(username,full_name,email,password_hash,role_id,active) VALUES(?,?,?,?,?,1)');
                $st->execute([$admin['username'], $admin['full_name'], $admin['email'] !== '' ? $admin['email'] : null, $admin['password_hash'], $roleId]);

                [$appKey, $newKeyCreated] = install_app_key_raw($keyFile);
                install_save_email($pdo, $email, $appKey);

                if ($newKeyCreated) {
                    install_write_atomic($keyFile, base64_encode($appKey) . "\n", 0600);
                }

                install_write_atomic($configFile, install_config_php($db, $siteUrl), 0600);
                $configWritten = true;

                $lockPayload = json_encode([
                    'installed_at' => date(DATE_ATOM),
                    'site_url' => rtrim($siteUrl, '/'),
                    'installer_version' => INSTALLER_VERSION,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if ($lockPayload === false) throw new RuntimeException('No se pudo generar install.lock.');
                install_write_atomic($lockFile, $lockPayload . "\n", 0600); // ALWAYS LAST
            } catch (Throwable $finalError) {
                $_SESSION['install_fresh_retry'] = true;
                if ($configWritten) {
                    if ($oldConfigExists && $oldConfigRaw !== null) {
                        @file_put_contents($configFile, $oldConfigRaw, LOCK_EX);
                        @chmod($configFile, 0600);
                    } else {
                        @unlink($configFile);
                    }
                }
                if ($newKeyCreated) @unlink($keyFile);
                @unlink($lockFile);
                throw $finalError;
            }

            foreach (['install_db','install_admin','install_email','install_site_url','install_mode','install_fresh_retry'] as $key) unset($_SESSION[$key]);
            $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
            header('Location: ../admin/login.php?installed=1');
            exit;
        }
    } catch (Throwable $e) {
        if ($action === 'test_email') {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $error = $e->getMessage();
    }
}

$dbDefaults = $_SESSION['install_db'] ?? [
    'host' => $existingConfig['host'] ?? 'localhost',
    'port' => $existingConfig['port'] ?? 3306,
    'dbname' => $existingConfig['dbname'] ?? '',
    'username' => $existingConfig['username'] ?? '',
    'password' => $existingConfig['password'] ?? '',
    'create_database' => false,
];
$adminDefaults = $_SESSION['install_admin'] ?? ['full_name' => '', 'email' => '', 'username' => ''];
$emailDefaults = $_SESSION['install_email'] ?? ['mode' => 'LATER'];
$stepNames = [1 => 'Base de datos', 2 => 'Administrador', 3 => 'Correo', 4 => 'Confirmación'];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Instalación · ES MULTISERVICIOS CMS</title>
<link rel="icon" type="image/png" href="../assets/brand/favicon.png">
<style>
:root{--navy:#0b2e59;--navy2:#123d6d;--blue:#0c78aa;--blue2:#075f8c;--orange:#f28c28;--ink:#172b44;--muted:#667a90;--page:#f3f7fb;--surface:#fff;--line:#dce6ef;--soft:#f7fafc;--softblue:#eef7fb;--ok:#13795b;--danger:#b42318;--shadow:0 28px 80px rgba(16,42,67,.14);--r:26px}
*{box-sizing:border-box}html{-webkit-text-size-adjust:100%}body{margin:0;background:var(--page);color:var(--ink);font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Arial,sans-serif}button,input,select,a{font:inherit}button,a{touch-action:manipulation}.page{min-height:100dvh;padding:clamp(12px,3vw,34px);display:grid;place-items:center}.wizard{width:min(1180px,100%);display:grid;grid-template-columns:minmax(250px,310px) minmax(0,1fr);background:var(--surface);border:1px solid var(--line);border-radius:30px;overflow:hidden;box-shadow:var(--shadow)}.aside{min-width:0;background:var(--navy);color:#fff;padding:clamp(24px,4vw,38px);display:flex;flex-direction:column;gap:22px}.brand{display:flex;align-items:center;gap:12px}.brand-mark{width:46px;height:46px;border-radius:15px;background:#fff;color:var(--navy);display:grid;place-items:center;font-weight:900}.brand strong{display:block;font-size:14px}.brand small{display:block;color:#c7d9ea;margin-top:3px}.aside h1{font-size:clamp(28px,3vw,36px);line-height:1.08;margin:6px 0 0}.aside p{margin:0;color:#c9d8e7;line-height:1.65;font-size:14px}.aside-steps{display:grid;gap:10px;margin-top:2px}.aside-step{display:grid;grid-template-columns:38px minmax(0,1fr);gap:11px;align-items:center;min-width:0;padding:8px 9px;border-radius:13px;color:#b9cadd;border:1px solid transparent}.aside-step-num{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.04);color:#d7e4ef;font-size:12px;font-weight:900}.aside-step strong{display:block;font-size:13px;line-height:1.25;color:inherit}.aside-step small{display:block;margin-top:3px;color:#8fa8bf;font-size:11px;line-height:1.3}.aside-step.done{color:#dff5eb}.aside-step.done .aside-step-num{background:#1d684f;border-color:#1d684f;color:#fff}.aside-step.active{background:rgba(255,255,255,.08);color:#fff;border-color:rgba(255,255,255,.12)}.aside-step.active .aside-step-num{background:#d92f24;border-color:#d92f24;color:#fff}.aside-step.active small{color:#d7e4ef}.mode-card{margin-top:auto;border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.07);border-radius:18px;padding:15px}.mode-card strong{display:block;font-size:13px}.mode-card span{display:block;color:#c9d8e7;font-size:12px;line-height:1.55;margin-top:5px}.content{min-width:0;padding:clamp(22px,5vw,52px)}.top{display:flex;align-items:flex-start;justify-content:space-between;gap:18px}.eyebrow{font-size:11px;letter-spacing:.15em;color:var(--blue);font-weight:900}.content h2{font-size:clamp(29px,5vw,43px);line-height:1.08;color:var(--navy);margin:8px 0 10px}.muted{color:var(--muted);line-height:1.65;margin:0}.counter{flex:0 0 auto;border:1px solid #cfe2ec;background:var(--softblue);border-radius:999px;padding:8px 12px;color:var(--navy);font-weight:900;font-size:12px;white-space:nowrap}.progress{height:7px;background:#e9eff5;border-radius:999px;overflow:hidden;margin:24px 0 18px}.progress span{display:block;height:100%;width:calc(var(--step) * 25%);background:var(--blue);border-radius:inherit;transition:width .2s ease}.steps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px;margin-bottom:28px}.step{min-width:0;border:1px solid var(--line);border-radius:15px;padding:11px;display:flex;align-items:center;gap:9px;background:#fbfdff}.step-num{width:31px;height:31px;flex:0 0 31px;border-radius:50%;display:grid;place-items:center;background:#e8eef4;color:#708196;font-size:12px;font-weight:900}.step strong{font-size:12px;line-height:1.2}.step small{display:block;font-size:10px;color:var(--muted);margin-top:2px}.step.active{border-color:#8fc8df;background:#f1f9fc}.step.active .step-num{background:var(--blue);color:#fff}.step.done{border-color:#b7ddcf;background:#f3faf7}.step.done .step-num{background:var(--ok);color:#fff}.alert{border-radius:15px;padding:13px 15px;margin:0 0 18px;font-size:13px;line-height:1.55}.alert.error{border:1px solid #f2b8b5;background:#fff4f3;color:#8d1c16}.intro{display:flex;gap:12px;align-items:flex-start;border:1px solid #d9e9f2;background:var(--softblue);border-radius:17px;padding:14px 16px;margin-bottom:22px}.intro-icon{width:34px;height:34px;flex:0 0 34px;border-radius:11px;background:var(--blue);color:#fff;display:grid;place-items:center;font-weight:900}.intro strong{font-size:13px;color:var(--navy)}.intro span{display:block;color:var(--muted);font-size:12px;line-height:1.5;margin-top:3px}.badge{display:inline-flex;align-items:center;gap:7px;padding:7px 10px;border-radius:999px;font-size:11px;font-weight:900;background:#fff3e7;color:#8d4a00;border:1px solid #f5cfaa;margin-bottom:16px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.full{grid-column:1/-1}.field{min-width:0;display:grid;gap:8px;color:var(--navy);font-size:13px;font-weight:800}.field input,.field select{width:100%;min-width:0;min-height:50px;border:1px solid #cfd9e4;border-radius:13px;background:#fff;padding:11px 13px;color:var(--ink);outline:none}.field input:focus,.field select:focus{border-color:#58aaca;box-shadow:0 0 0 4px rgba(12,120,170,.1)}.helper{font-size:11px;color:var(--muted);font-weight:500;line-height:1.45}.check{display:flex;gap:10px;align-items:flex-start;border:1px solid var(--line);background:var(--soft);border-radius:14px;padding:13px;color:var(--ink)}.check input{margin-top:3px}.safe{margin-top:18px;border-left:4px solid var(--blue);background:#f7fbfd;border-radius:12px;padding:13px 15px;font-size:12px;color:var(--muted);line-height:1.55}.safe strong{color:var(--navy)}.actions{display:flex;justify-content:space-between;gap:12px;margin-top:24px;flex-wrap:wrap}.btn{min-height:48px;border-radius:13px;padding:11px 17px;border:1px solid transparent;text-decoration:none;font-weight:900;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}.btn.primary{background:var(--navy);color:#fff}.btn.primary:hover{background:var(--navy2)}.btn.secondary{background:#fff;color:var(--navy);border-color:#bed3df}.btn.ghost{background:var(--soft);color:var(--navy);border-color:var(--line)}.btn.orange{background:var(--orange);color:#172b44}.choice-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:20px}.choice{position:relative;min-width:0;border:1px solid var(--line);background:#fff;border-radius:18px;padding:16px;display:grid;gap:6px;cursor:pointer}.choice input{position:absolute;opacity:0;pointer-events:none}.choice strong{color:var(--navy);font-size:15px}.choice span{color:var(--muted);font-size:12px;line-height:1.45}.choice.active{border-color:#6eb5d1;background:#f1f9fc;box-shadow:0 0 0 3px rgba(12,120,170,.08)}.choice-symbol{width:36px;height:36px;border-radius:11px;background:var(--navy);color:#fff;display:grid;place-items:center;font-weight:900;margin-bottom:3px}.email-panel,.box{border:1px solid var(--line);border-radius:18px;padding:18px;margin-top:16px;background:#fff}.email-panel[hidden]{display:none!important}.panel-head{display:flex;align-items:flex-start;gap:11px;margin-bottom:15px}.panel-icon{width:35px;height:35px;flex:0 0 35px;border-radius:11px;background:var(--blue);color:#fff;display:grid;place-items:center;font-weight:900}.panel-head h3,.box h3{margin:0;color:var(--navy);font-size:16px}.purpose-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:12px}.purpose{display:flex;gap:9px;align-items:flex-start;border:1px solid var(--line);border-radius:13px;padding:11px}.purpose strong{display:block;font-size:12px;color:var(--navy)}.purpose small{display:block;color:var(--muted);margin-top:2px;line-height:1.35}.test-row{display:grid;grid-template-columns:minmax(0,1fr) minmax(190px,auto);gap:14px;align-items:end;margin-top:16px}.test-row .btn{min-height:46px;align-self:end;white-space:nowrap}.test-result{min-height:18px;margin-top:10px;font-size:12px;font-weight:800}.test-result.ok{color:var(--ok)}.test-result.bad{color:var(--danger)}.review{display:grid;gap:12px}.review-card{border:1px solid var(--line);border-radius:17px;padding:16px;background:#fff;min-width:0}.review-card .review-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:11px}.review-card h3{margin:0;color:var(--navy);font-size:15px}.review-card a{color:var(--blue);font-size:12px;font-weight:900;text-decoration:none}.review-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.review-item{min-width:0;border-radius:12px;background:var(--soft);padding:10px 12px}.review-item span{display:block;color:var(--muted);font-size:10px;text-transform:uppercase;letter-spacing:.07em;font-weight:800}.review-item strong{display:block;margin-top:4px;color:var(--ink);font-size:12px;overflow-wrap:anywhere}.danger-note{border:1px solid #f2d19b;background:#fff8ec;color:#76420a;border-radius:15px;padding:14px;font-size:12px;line-height:1.55;margin-top:14px}.danger-note strong{color:#623400}.site-url{margin-top:16px;border:1px solid var(--line);border-radius:14px;padding:12px 14px;background:var(--soft);font-size:12px}.site-url span{display:block;color:var(--muted);font-size:10px;text-transform:uppercase;font-weight:900;letter-spacing:.07em}.site-url strong{display:block;margin-top:4px;overflow-wrap:anywhere;color:var(--navy)}
@media(max-width:960px){.wizard{grid-template-columns:1fr}.aside{padding:22px}.mode-card{margin-top:0}.aside h1{font-size:27px}.aside-steps{grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}.aside-step{grid-template-columns:32px minmax(0,1fr);padding:7px}.aside-step-num{width:30px;height:30px}.aside-step small{display:none}.content{padding:26px}.choice-grid{grid-template-columns:1fr}.steps{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:680px){.page{padding:9px}.wizard{border-radius:21px}.aside{padding:18px;gap:13px}.aside>p{display:none}.aside-steps{grid-template-columns:repeat(2,minmax(0,1fr))}.aside-step{grid-template-columns:30px minmax(0,1fr)}.aside-step strong{font-size:12px}.content{padding:18px}.top{display:grid}.counter{justify-self:start}.steps{grid-template-columns:1fr}.step:not(.active):not(.done){display:none}.grid,.purpose-grid,.review-grid,.test-row{grid-template-columns:1fr}.actions{flex-direction:column-reverse}.actions .btn,.test-row .btn{width:100%}.choice-grid{gap:10px}.email-panel,.box{padding:15px}.full{grid-column:auto}}
@media(max-width:380px){.content{padding:15px}.aside{padding:15px}.btn{width:100%}.review-card{padding:13px}.field input,.field select{min-height:48px}}

/* Final wizard legibility + step-panel behavior. */
.wizard{max-height:calc(100dvh - 28px);overflow:hidden}
.content{display:flex;flex-direction:column;min-height:0;max-height:calc(100dvh - 28px)}
.step-scroll{min-height:0;overflow-y:auto;overflow-x:hidden;padding-right:4px;scrollbar-gutter:stable}
.field{font-size:14px}.field input,.field select{min-height:46px;font-size:15px}.helper{font-size:12px}.muted{font-size:14px}.step strong{font-size:13px}.step small{font-size:11px}.btn{font-size:14px}
.choice-grid{align-items:stretch}
.choice{grid-template-columns:22px 42px minmax(0,1fr);grid-template-rows:auto auto;column-gap:10px;row-gap:3px;align-items:center;padding:16px;min-height:88px}
.choice input{position:static;opacity:1;pointer-events:auto;width:16px;height:16px;margin:0;grid-row:1/3;accent-color:var(--blue)}
.choice-symbol{grid-row:1/3;margin:0;background:#fff3ea;color:#cf3425;border:1px solid #f2e1d7}
.choice strong{grid-column:3;font-size:14px;align-self:end}.choice span{grid-column:3;font-size:11px;align-self:start}
.choice.active{border-color:#e2a33a;background:#fffaf0;box-shadow:0 0 0 3px rgba(226,163,58,.08)}
.actions{position:sticky;bottom:0;z-index:8;background:linear-gradient(180deg,rgba(255,255,255,0),#fff 18%);padding-top:18px;padding-bottom:2px}
@media(max-height:760px) and (min-width:961px){.aside{display:none}.wizard{grid-template-columns:1fr}.content{padding-top:24px;padding-bottom:18px}.progress{margin:16px 0 14px}.steps{margin-bottom:18px}}
@media(max-width:960px){.wizard,.content{max-height:none}.step-scroll{overflow:visible;padding-right:0}.actions{position:static;background:none;padding-top:0}}
@media(max-width:680px){body{font-size:15px}.field{font-size:13px}.field input,.field select{font-size:15px;min-height:46px}.helper{font-size:12px}.choice{grid-template-columns:22px 40px minmax(0,1fr);padding:14px}.choice strong{font-size:14px}.choice span{font-size:11px}}

</style>
</head>
<body>
<div class="page">
<div class="wizard">
<aside class="aside">
  <div class="brand"><div class="brand-mark">ES</div><div><strong>Setup Assistant</strong><small>ES MULTISERVICIOS CMS</small></div></div>
  <h1><?= $reinstallMode ? 'Reinstalación limpia y controlada.' : 'Instalación guiada, paso a paso.' ?></h1>
  <p><?= $reinstallMode ? 'Se reutiliza la conexión existente y únicamente se recrean las tablas propias de este proyecto. La base de datos completa nunca se elimina.' : 'Configura la base de datos, crea el administrador, decide cómo enviar correos y confirma antes de activar el sistema.' ?></p>
  <nav class="aside-steps" aria-label="Progreso de instalación">
    <?php foreach ($stepNames as $n => $label): ?>
      <div class="aside-step <?= $n === $currentStep ? 'active' : ($n < $currentStep ? 'done' : '') ?>" <?= $n === $currentStep ? 'aria-current="step"' : '' ?>>
        <div class="aside-step-num"><?= $n < $currentStep ? '✓' : $n ?></div>
        <div><strong><?= ih($label) ?></strong><small><?= ['Conexión MySQL','Cuenta principal','SMTP o Graph','Revisión final'][$n-1] ?></small></div>
      </div>
    <?php endforeach; ?>
  </nav>
  <div class="mode-card"><strong><?= $reinstallMode ? 'Modo: Reinstalación limpia' : 'Modo: Instalación inicial' ?></strong><span><?= $reinstallMode ? 'Detectamos una configuración previa sin install.lock. Tus datos de conexión se precargan automáticamente.' : 'install.lock se creará únicamente cuando los cuatro pasos finalicen correctamente.' ?></span></div>
</aside>
<main class="content">
  <div class="top"><div><div class="eyebrow">ES MULTISERVICIOS · CMS INSTALLER</div><h2><?= ih($stepNames[$currentStep]) ?></h2><p class="muted">Completa este paso para continuar. Solo se muestra una etapa a la vez.</p></div><div class="counter">PASO <?= $currentStep ?> DE 4</div></div>
  <div class="progress" style="--step:<?= $currentStep ?>"><span></span></div>
  <div class="step-scroll">

  <?php if ($error !== ''): ?><div class="alert error"><?= ih($error) ?></div><?php endif; ?>
  <?php if ($reinstallMode): ?><div class="badge">↻ REINSTALACIÓN LIMPIA</div><?php endif; ?>

  <?php if ($currentStep === 1): ?>
    <div class="intro"><div class="intro-icon">1</div><div><strong>Conecta la base de datos</strong><span><?= $reinstallMode ? 'Los datos existentes fueron precargados. Si dejas la contraseña vacía, se reutilizará la guardada en la configuración anterior.' : 'El asistente validará la conexión antes de permitirte continuar.' ?></span></div></div>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= ih((string)$_SESSION['install_csrf']) ?>"><input type="hidden" name="action" value="database">
      <div class="grid">
        <label class="field">Servidor MySQL<input name="host" required value="<?= ih((string)$dbDefaults['host']) ?>"></label>
        <label class="field">Puerto<input type="number" min="1" max="65535" name="port" required value="<?= (int)$dbDefaults['port'] ?>"></label>
        <label class="field">Base de datos<input name="dbname" required value="<?= ih((string)$dbDefaults['dbname']) ?>"></label>
        <label class="field">Usuario<input name="username" required value="<?= ih((string)$dbDefaults['username']) ?>"></label>
        <label class="field full">Contraseña MySQL<input type="password" name="password" autocomplete="new-password" placeholder="<?= $reinstallMode ? 'Déjala vacía para reutilizar la contraseña almacenada' : 'Contraseña de la base de datos' ?>"><span class="helper"><?= $reinstallMode ? 'Por seguridad nunca mostramos la contraseña existente.' : 'Se guardará únicamente dentro de config/config.php, protegido por el servidor.' ?></span></label>
        <?php if (!$reinstallMode): ?><label class="check full"><input type="checkbox" name="create_database" value="1" <?= !empty($dbDefaults['create_database']) ? 'checked' : '' ?>><span><strong>Crear la base de datos si no existe</strong><br><span class="helper">Déjalo apagado si tu hosting obliga a crearla desde cPanel.</span></span></label><?php endif; ?>
      </div>
      <div class="site-url"><span>URL detectada automáticamente</span><strong><?= ih($detectedUrl) ?></strong></div>
      <?php if ($reinstallMode): ?><div class="danger-note"><strong>No se usará DROP DATABASE.</strong> Al confirmar la reinstalación solo se eliminarán y recrearán las tablas identificadas en el esquema propio de este proyecto, con FOREIGN_KEY_CHECKS controlado.</div><?php endif; ?>
      <div class="actions"><span></span><button class="btn primary" type="submit">Siguiente →</button></div>
    </form>

  <?php elseif ($currentStep === 2): ?>
    <div class="intro"><div class="intro-icon">2</div><div><strong>Crea el administrador principal</strong><span>Esta cuenta será Owner y tendrá control completo del CMS después de finalizar la instalación.</span></div></div>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= ih((string)$_SESSION['install_csrf']) ?>"><input type="hidden" name="action" value="admin">
      <div class="grid">
        <label class="field">Nombre completo<input name="full_name" autocomplete="name" value="<?= ih((string)($adminDefaults['full_name'] ?? '')) ?>"></label>
        <label class="field">Correo<input type="email" name="admin_email" autocomplete="email" value="<?= ih((string)($adminDefaults['email'] ?? '')) ?>"></label>
        <label class="field full">Usuario<input name="admin_username" required minlength="4" autocomplete="username" value="<?= ih((string)($adminDefaults['username'] ?? '')) ?>"></label>
        <label class="field">Contraseña<input type="password" name="admin_password" <?= !empty($_SESSION['install_admin']['password_hash']) ? '' : 'required' ?> minlength="10" autocomplete="new-password"><span class="helper"><?= !empty($_SESSION['install_admin']['password_hash']) ? 'Déjala vacía para conservar la contraseña ya definida en este asistente.' : 'Mínimo 10 caracteres.' ?></span></label>
        <label class="field">Repetir contraseña<input type="password" name="admin_password2" <?= !empty($_SESSION['install_admin']['password_hash']) ? '' : 'required' ?> minlength="10" autocomplete="new-password"></label>
      </div>
      <div class="safe"><strong>La contraseña no se guarda en texto plano.</strong> El asistente conserva únicamente su hash para crear la cuenta durante la confirmación final.</div>
      <div class="actions"><a class="btn ghost" href="?step=database">← Atrás</a><button class="btn primary" type="submit">Siguiente →</button></div>
    </form>

  <?php elseif ($currentStep === 3): ?>
    <div class="intro"><div class="intro-icon">3</div><div><strong>Configura el correo del sistema</strong><span>Elige SMTP, Microsoft Graph o Configurar después. La opción de correo no bloquea la instalación si decides dejarla pendiente.</span></div></div>
    <form method="post" id="emailForm" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= ih((string)$_SESSION['install_csrf']) ?>"><input type="hidden" name="action" id="emailAction" value="email">
      <div class="choice-grid">
        <label class="choice" data-choice="LATER"><input type="radio" name="email_choice" value="LATER" <?= ($emailDefaults['mode'] ?? 'LATER') === 'LATER' ? 'checked' : '' ?>><div class="choice-symbol">→</div><strong>Configurar después</strong><span>Finaliza la instalación y configura el correo desde Administración → Correo.</span></label>
        <label class="choice" data-choice="SMTP"><input type="radio" name="email_choice" value="SMTP" <?= ($emailDefaults['mode'] ?? '') === 'SMTP' ? 'checked' : '' ?>><div class="choice-symbol">✉</div><strong>SMTP</strong><span>Hosting, Gmail, Microsoft 365 SMTP u otro proveedor compatible.</span></label>
        <label class="choice" data-choice="GRAPH"><input type="radio" name="email_choice" value="GRAPH" <?= ($emailDefaults['mode'] ?? '') === 'GRAPH' ? 'checked' : '' ?>><div class="choice-symbol">G</div><strong>Microsoft Graph</strong><span>OAuth2 mediante Tenant ID, Client ID y Client Secret.</span></label>
      </div>
      <div id="emailConfigArea">
        <div class="grid"><label class="field full">Correo remitente<input type="email" name="sender_email" value="<?= ih((string)($emailDefaults['correo'] ?? '')) ?>" placeholder="notificaciones@tudominio.com" data-email-required></label></div>
        <section class="email-panel" id="smtpPanel"><div class="panel-head"><div class="panel-icon">S</div><div><h3>Configuración SMTP</h3><p class="muted">Solo se muestran los campos de SMTP.</p></div></div><div class="grid">
          <label class="field full">Servidor SMTP<input name="smtp_server" value="<?= ih((string)($emailDefaults['server'] ?? '')) ?>" placeholder="smtp.tudominio.com" data-smtp-required></label>
          <label class="field">Puerto<input type="number" min="1" max="65535" name="smtp_port" value="<?= (int)($emailDefaults['port'] ?? 587) ?>" data-smtp-required></label>
          <label class="field">Seguridad<select name="smtp_secure" data-smtp-required><option value="tls" <?= ($emailDefaults['smtp_secure'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS / STARTTLS</option><option value="ssl" <?= ($emailDefaults['smtp_secure'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option></select></label>
          <label class="field full">Contraseña SMTP<input type="password" name="smtp_password" autocomplete="new-password" data-smtp-required data-has-stored="<?= ($emailDefaults['mode'] ?? '') === 'SMTP' && !empty($emailDefaults['password_plain']) ? '1' : '0' ?>"><span class="helper"><?= ($emailDefaults['mode'] ?? '') === 'SMTP' ? 'Déjala vacía para conservar la contraseña capturada anteriormente en este asistente.' : 'Se cifra antes de almacenarse.' ?></span></label>
        </div></section>
        <section class="email-panel" id="graphPanel" hidden><div class="panel-head"><div class="panel-icon">G</div><div><h3>Microsoft Graph</h3><p class="muted">Solo se muestran las credenciales requeridas para Graph.</p></div></div><div class="grid">
          <label class="field">Tenant ID<input name="tenant_id" value="<?= ih((string)($emailDefaults['tenant_id'] ?? '')) ?>" data-graph-required></label>
          <label class="field">Client ID<input name="client_id" value="<?= ih((string)($emailDefaults['client_id'] ?? '')) ?>" data-graph-required></label>
          <label class="field full">Client Secret<input type="password" name="client_secret" autocomplete="new-password" data-graph-required data-has-stored="<?= ($emailDefaults['mode'] ?? '') === 'GRAPH' && !empty($emailDefaults['client_secret_plain']) ? '1' : '0' ?>"><span class="helper"><?= ($emailDefaults['mode'] ?? '') === 'GRAPH' ? 'Déjalo vacío para conservar el secreto capturado anteriormente en este asistente.' : 'Se cifra antes de almacenarse.' ?></span></label>
          <label class="field full">Buzón Microsoft 365<input type="email" name="graph_user" value="<?= ih((string)($emailDefaults['graph_user'] ?? '')) ?>" placeholder="correo@tudominio.com"><span class="helper">Si lo dejas vacío se utilizará el correo remitente.</span></label>
          <label class="check full"><input type="checkbox" name="save_to_sent_items" value="1" <?= !isset($emailDefaults['save_to_sent_items']) || !empty($emailDefaults['save_to_sent_items']) ? 'checked' : '' ?>><span><strong>Guardar en Sent Items</strong><br><span class="helper">Recomendado para trazabilidad.</span></span></label>
        </div></section>
        <section class="box"><h3>Usar esta conexión para</h3><p class="muted">Puedes separar estas configuraciones más adelante desde Administración → Correo.</p><div class="purpose-grid">
          <?php $selectedPurposes = $emailDefaults['purposes'] ?? [1,2,3,4]; foreach ([1=>'Website Alerts',2=>'Admin Security',3=>'Estimate Requests',4=>'Auto Replies'] as $id=>$name): ?><label class="purpose"><input type="checkbox" name="purposes[]" value="<?= $id ?>" <?= in_array($id,$selectedPurposes,true) ? 'checked' : '' ?>><span><strong><?= ih($name) ?></strong><small>Configuración de envío para este propósito.</small></span></label><?php endforeach; ?>
        </div></section>
        <section class="box"><div class="panel-head"><div class="panel-icon">✓</div><div><h3>Probar configuración</h3><p class="muted">Envía un correo de prueba con la plantilla corporativa antes de guardar. Los valores actuales todavía no se almacenan.</p></div></div><div class="test-row"><label class="field">Correo destino<input type="email" id="testTo" name="test_to" placeholder="tu@correo.com"></label><button class="btn secondary" type="button" id="testBtn">Enviar prueba</button></div><div class="test-result" id="testResult" role="status" aria-live="polite"></div></section>
      </div>
      <div class="actions"><a class="btn ghost" href="?step=admin">← Atrás</a><button class="btn primary" type="submit" id="emailNext">Guardar y continuar →</button></div>
    </form>

  <?php else: ?>
    <?php $dbReview = $_SESSION['install_db']; $adminReview = $_SESSION['install_admin']; $emailReview = $_SESSION['install_email']; ?>
    <div class="intro"><div class="intro-icon">4</div><div><strong>Revisa antes de activar</strong><span>install.lock todavía NO existe. Se creará únicamente después de completar correctamente base de datos, administrador, correo y configuración.</span></div></div>
    <div class="review">
      <section class="review-card"><div class="review-head"><h3>Base de datos</h3><a href="?step=database">Editar</a></div><div class="review-grid"><div class="review-item"><span>Servidor</span><strong><?= ih($dbReview['host']) ?>:<?= (int)$dbReview['port'] ?></strong></div><div class="review-item"><span>Base</span><strong><?= ih($dbReview['dbname']) ?></strong></div><div class="review-item"><span>Usuario</span><strong><?= ih($dbReview['username']) ?></strong></div><div class="review-item"><span>Modo</span><strong><?= $reinstallMode ? 'Reinstalación limpia' : 'Instalación inicial' ?></strong></div></div></section>
      <section class="review-card"><div class="review-head"><h3>Administrador</h3><a href="?step=admin">Editar</a></div><div class="review-grid"><div class="review-item"><span>Nombre</span><strong><?= ih((string)$adminReview['full_name']) ?: '—' ?></strong></div><div class="review-item"><span>Usuario</span><strong><?= ih((string)$adminReview['username']) ?></strong></div><div class="review-item"><span>Correo</span><strong><?= ih((string)$adminReview['email']) ?: '—' ?></strong></div><div class="review-item"><span>Rol</span><strong>Owner</strong></div></div></section>
      <section class="review-card"><div class="review-head"><h3>Correo</h3><a href="?step=email">Editar</a></div><div class="review-grid"><div class="review-item"><span>Método</span><strong><?= ($emailReview['mode'] ?? 'LATER') === 'LATER' ? 'Configurar después' : ih((string)$emailReview['mode']) ?></strong></div><div class="review-item"><span>Remitente</span><strong><?= ($emailReview['mode'] ?? 'LATER') === 'LATER' ? 'Pendiente' : ih((string)$emailReview['correo']) ?></strong></div></div></section>
      <section class="review-card"><div class="review-head"><h3>Sitio</h3></div><div class="review-grid"><div class="review-item full"><span>URL detectada</span><strong><?= ih((string)($_SESSION['install_site_url'] ?? $detectedUrl)) ?></strong></div></div></section>
    </div>
    <?php if ($reinstallMode): ?><div class="danger-note"><strong>Reinstalación limpia:</strong> al finalizar se desactivarán temporalmente las claves foráneas, se eliminarán únicamente las tablas propias detectadas en el esquema del proyecto y se recrearán. No se ejecuta DROP DATABASE y no se tocan tablas ajenas.</div><?php endif; ?>
    <form method="post"><input type="hidden" name="csrf" value="<?= ih((string)$_SESSION['install_csrf']) ?>"><input type="hidden" name="action" value="finalize"><div class="actions"><a class="btn ghost" href="?step=email">← Atrás</a><button class="btn orange" type="submit">Finalizar instalación</button></div></form>
  <?php endif; ?>
  </div>
</main>
</div>
</div>
<?php if ($currentStep === 3): ?>
<script>
(() => {
  const form = document.getElementById('emailForm');
  const choices = [...document.querySelectorAll('[data-choice]')];
  const configArea = document.getElementById('emailConfigArea');
  const smtp = document.getElementById('smtpPanel');
  const graph = document.getElementById('graphPanel');
  const testBtn = document.getElementById('testBtn');
  const result = document.getElementById('testResult');
  const selected = () => form.querySelector('input[name="email_choice"]:checked')?.value || 'LATER';
  const sync = () => {
    const mode = selected();
    choices.forEach(c => c.classList.toggle('active', c.dataset.choice === mode));
    configArea.hidden = mode === 'LATER';
    smtp.hidden = mode !== 'SMTP';
    graph.hidden = mode !== 'GRAPH';
    form.querySelectorAll('[data-email-required]').forEach(el => el.required = mode !== 'LATER');
    form.querySelectorAll('[data-smtp-required]').forEach(el => el.required = mode === 'SMTP' && el.dataset.hasStored !== '1');
    form.querySelectorAll('[data-graph-required]').forEach(el => el.required = mode === 'GRAPH' && el.dataset.hasStored !== '1');
    result.textContent = ''; result.className = 'test-result';
  };
  choices.forEach(card => card.addEventListener('click', () => { const r = card.querySelector('input[type="radio"]'); if (r) r.checked = true; sync(); }));
  testBtn.addEventListener('click', async () => {
    if (selected() === 'LATER') return;
    sync();
    if (!form.reportValidity()) return;
    const to = document.getElementById('testTo');
    if (!to.value || !to.checkValidity()) { to.reportValidity(); return; }
    const data = new FormData(form); data.set('action','test_email');
    testBtn.disabled = true; testBtn.textContent = 'Enviando…';
    try {
      const res = await fetch(window.location.href,{method:'POST',body:data,headers:{'X-Requested-With':'XMLHttpRequest'}});
      const payload = await res.json();
      result.textContent = payload.message || (payload.success ? 'Prueba enviada correctamente.' : 'La prueba falló.');
      result.className = 'test-result ' + (payload.success ? 'ok' : 'bad');
    } catch(e) {
      result.textContent = 'No se pudo completar la prueba. Revisa la conexión e inténtalo nuevamente.';
      result.className = 'test-result bad';
    } finally { testBtn.disabled = false; testBtn.textContent = 'Enviar prueba'; }
  });
  sync();
})();
</script>
<?php endif; ?>
</body>
</html>
