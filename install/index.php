<?php
declare(strict_types=1);
session_start();

const INSTALLER_VERSION = '5.25.0';

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

function install_clear_stale_admin_auth(bool $destroySession = false): void
{
    // The installer and the admin area share the same PHP session cookie.
    // If install.lock/database are removed while an administrator is still
    // signed in, that old session must never authenticate the freshly
    // installed CMS automatically.
    foreach ([
        'escms_admin_id', 'escms_admin_user',
        'escms_2fa_pending_id', 'escms_2fa_pending_user', 'escms_2fa_remember',
        'csrf', 'flash'
    ] as $key) {
        unset($_SESSION[$key]);
    }

    $rememberName = 'escms_admin_remember';
    setcookie($rememberName, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[$rememberName]);

    if ($destroySession && session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                (bool)$params['secure'], (bool)$params['httponly']
            );
        }
        session_destroy();
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

function install_detect_reinstall_mode(?array $existingConfig, string $schemaFile): bool
{
    // A config file by itself does NOT mean this is a reinstall. A real
    // reinstall is only detected when the configured database still exists
    // and contains at least one table that belongs to this project's schema.
    if (!is_array($existingConfig)) return false;
    if (trim((string)($existingConfig['host'] ?? '')) === '' ||
        trim((string)($existingConfig['dbname'] ?? '')) === '' ||
        trim((string)($existingConfig['username'] ?? '')) === '') {
        return false;
    }

    try {
        $server = install_connect($existingConfig, true);
        $check = $server->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1');
        $check->execute([(string)$existingConfig['dbname']]);
        $databaseExists = (bool)$check->fetchColumn();
        $check->closeCursor();
        if (!$databaseExists) return false;

        $sql = is_file($schemaFile) ? (string)file_get_contents($schemaFile) : '';
        if ($sql === '') return false;
        $projectTables = install_project_tables($sql);
        if (!$projectTables) return false;

        $pdo = install_connect($existingConfig);
        $placeholders = implode(',', array_fill(0, count($projectTables), '?'));
        $query = 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (' . $placeholders . ')';
        $stmt = $pdo->prepare($query);
        $stmt->execute(array_merge([(string)$existingConfig['dbname']], $projectTables));
        $matchedTables = (int)$stmt->fetchColumn();
        $stmt->closeCursor();
        return $matchedTables > 0;
    } catch (Throwable $e) {
        // If the previous database cannot be positively identified, start as
        // a clean installation instead of presenting destructive reinstall UI.
        return false;
    }
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
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ]);
}

function install_validate_db_input(?array $passwordSource, bool $reinstallMode): array
{
    $host = trim((string)($_POST['host'] ?? 'localhost'));
    $port = (int)($_POST['port'] ?? 3306);
    $prefixRaw = trim((string)($_POST['db_prefix'] ?? ''));
    $baseName = trim((string)($_POST['db_name'] ?? ($_POST['dbname'] ?? '')));
    $username = trim((string)($_POST['username'] ?? ''));
    $postedPassword = (string)($_POST['password'] ?? '');
    $password = $postedPassword !== '' ? $postedPassword : (string)($passwordSource['password'] ?? '');

    if ($host === '' || $baseName === '' || $username === '') {
        throw new RuntimeException('Servidor, base de datos y usuario son obligatorios.');
    }
    if ($port < 1 || $port > 65535) {
        throw new RuntimeException('Ingresa un puerto MySQL válido.');
    }
    if ($prefixRaw !== '' && !preg_match('/^[A-Za-z0-9_\-]+$/', $prefixRaw)) {
        throw new RuntimeException('El prefijo solo puede contener letras, números, guion y guion bajo.');
    }
    if (!preg_match('/^[A-Za-z0-9_\-]+$/', $baseName)) {
        throw new RuntimeException('El nombre de la base de datos solo puede contener letras, números, guion y guion bajo.');
    }

    $prefix = trim($prefixRaw, '_');
    $prefix = $prefix !== '' ? $prefix . '_' : '';
    $dbname = $baseName;
    if ($prefix !== '' && !str_starts_with(strtolower($baseName), strtolower($prefix))) {
        $dbname = $prefix . $baseName;
    }
    if (strlen($dbname) > 64) {
        throw new RuntimeException('El nombre final de la base de datos no puede superar 64 caracteres.');
    }

    return [
        'host' => $host,
        'port' => $port,
        'db_prefix' => $prefix,
        'db_name' => $baseName,
        'dbname' => $dbname,
        'username' => $username,
        'password' => $password,
        'charset' => 'utf8mb4',
        'create_database' => true,
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

function install_exec_and_drain(PDO $pdo, string $sql): void
{
    $statement = $pdo->prepare($sql);
    $statement->execute();

    do {
        if ($statement->columnCount() > 0) {
            $statement->fetchAll(PDO::FETCH_NUM);
        }
    } while ($statement->nextRowset());

    $statement->closeCursor();
}

function install_apply_schema(PDO $pdo, string $schemaFile, bool $cleanProjectTables): void
{
    $sql = @file_get_contents($schemaFile);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('No se pudo leer el esquema de base de datos del proyecto.');
    }

    $tables = $cleanProjectTables ? install_project_tables($sql) : [];
    install_exec_and_drain($pdo, 'SET FOREIGN_KEY_CHECKS=0');
    try {
        if ($cleanProjectTables) {
            foreach (array_reverse($tables) as $table) {
                install_exec_and_drain($pdo, 'DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`');
            }
        }
        foreach (install_split_sql($sql) as $statement) {
            install_exec_and_drain($pdo, $statement);
        }
    } finally {
        install_exec_and_drain($pdo, 'SET FOREIGN_KEY_CHECKS=1');
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
    $internalDestination = trim((string)($_POST['internal_destination'] ?? ''));

    $purposes = array_values(array_unique(array_map('intval', (array)($_POST['purposes'] ?? []))));
    $purposes = array_values(array_filter($purposes, static fn(int $id): bool => in_array($id, [1, 2, 3, 4], true)));
    if ($requirePurposes && !$purposes) throw new RuntimeException('Selecciona al menos un uso para la configuración de correo.');
    if (!$purposes) $purposes = $previous['purposes'] ?? [1, 2, 3, 4];

    if ($choice === 'SMTP') {
        if (!filter_var($sender, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Ingresa un correo remitente válido para SMTP.');
        }
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
            'destinatario' => $internalDestination !== '' ? $internalDestination : $sender,
            'save_to_sent_items' => 0, 'purposes' => $purposes,
        ];
    }

    $tenant = trim((string)($_POST['tenant_id'] ?? ''));
    $client = trim((string)($_POST['client_id'] ?? ''));
    $secret = (string)($_POST['client_secret'] ?? '');
    if ($secret === '' && ($previous['mode'] ?? '') === 'GRAPH') $secret = (string)($previous['client_secret_plain'] ?? '');
    $graphUser = trim((string)($_POST['graph_user'] ?? ''));
    if ($tenant === '' || $client === '' || $secret === '') {
        throw new RuntimeException('Tenant ID, Client ID y Client Secret son obligatorios para Microsoft Graph.');
    }
    if (!filter_var($graphUser, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Ingresa un buzón válido de Microsoft 365.');
    }
    if ($internalDestination !== '' && !filter_var($internalDestination, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Ingresa un destino interno válido o déjalo vacío para usar el buzón de Microsoft 365.');
    }
    return [
        'mode' => 'GRAPH', 'metodo_envio' => 'GRAPH', 'server' => 'graph.microsoft.com', 'correo' => $graphUser,
        'password_plain' => '', 'port' => 0, 'smtp_secure' => 'tls', 'tenant_id' => $tenant,
        'client_id' => $client, 'client_secret_plain' => $secret, 'graph_user' => $graphUser,
        'destinatario' => $internalDestination !== '' ? $internalDestination : $graphUser,
        'save_to_sent_items' => isset($_POST['save_to_sent_items']) ? 1 : 0, 'purposes' => $purposes,
    ];
}

function install_save_email(PDO $pdo, array $email, string $key): void
{
    if (($email['mode'] ?? 'LATER') === 'LATER') return;
    foreach ($email['purposes'] as $type) {
        $disable = $pdo->prepare('UPDATE correo SET estado=2 WHERE correo_tipo_id=?');
        $disable->execute([$type]);
        $disable->closeCursor();
        $st = $pdo->prepare('INSERT INTO correo(correo_tipo_id,metodo_envio,server,correo,destinatario,password,port,smtp_secure,tenant_id,client_id,client_secret,graph_user,save_to_sent_items,estado,fecha_registro) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW())');
        $st->execute([
            $type, $email['metodo_envio'], $email['server'], $email['correo'], $email['destinatario'] ?? null,
            install_encrypt((string)$email['password_plain'], $key), (int)$email['port'], $email['smtp_secure'],
            $email['tenant_id'], $email['client_id'], install_encrypt((string)$email['client_secret_plain'], $key),
            $email['graph_user'], (int)$email['save_to_sent_items'],
        ]);
        $st->closeCursor();
    }
}

function install_test_email(string $root, array $payload, string $to): array
{
    if (($payload['mode'] ?? 'LATER') === 'LATER') {
        throw new RuntimeException('Selecciona SMTP o Microsoft Graph para realizar una prueba.');
    }
    if ($to === '') {
        $to = trim((string)($payload['destinatario'] ?? $payload['graph_user'] ?? $payload['correo'] ?? ''));
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Ingresa un correo destino válido para la prueba.');

    require_once $root . '/config/bootstrap.php';
    require_once $root . '/core/EmailService.php';
    $cfg = [
        'metodo_envio' => $payload['metodo_envio'], 'server' => $payload['server'], 'correo' => $payload['correo'],
        'password' => $payload['password_plain'], 'port' => $payload['port'], 'smtp_secure' => $payload['smtp_secure'],
        'tenant_id' => $payload['tenant_id'], 'client_id' => $payload['client_id'], 'client_secret' => $payload['client_secret_plain'],
        'graph_user' => $payload['graph_user'], 'save_to_sent_items' => $payload['save_to_sent_items'],
        'destinatario' => $payload['destinatario'] ?? null, 'copia' => null,
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
$reinstallMode = install_detect_reinstall_mode($existingConfig, $schemaFile);
$installMode = $reinstallMode ? 'reinstall' : 'fresh';

// Entering the installer always invalidates any previous administrator login
// state in this browser. This is especially important when install.lock or
// the database was deleted without first signing out of the old installation.
install_clear_stale_admin_auth(false);

// If the installation footprint changed (for example, install.lock and the
// database were deleted to start over), discard stale wizard data from the
// previous mode so the assistant truly starts clean.
if (isset($_SESSION['install_mode']) && $_SESSION['install_mode'] !== $installMode) {
    foreach (['install_db','install_admin','install_admin_draft','install_email','install_site_url','install_notice','install_fresh_retry'] as $key) {
        unset($_SESSION[$key]);
    }
}
$_SESSION['install_mode'] = $installMode;
$reinstallConfig = $reinstallMode ? $existingConfig : null;

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

        if ($action === 'reset_wizard') {
            foreach (['install_db','install_admin','install_admin_draft','install_email','install_site_url','install_notice','install_fresh_retry'] as $key) {
                unset($_SESSION[$key]);
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'database') {
            $passwordSource = is_array($_SESSION['install_db'] ?? null) ? $_SESSION['install_db'] : $reinstallConfig;
            $db = install_validate_db_input($passwordSource, $reinstallMode);

            // Validate MySQL credentials first without requiring the target database to exist.
            $server = install_connect($db, true);
            $ping = $server->query('SELECT 1');
            $ping->fetchColumn();
            $ping->closeCursor();
            $check = $server->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1');
            $check->execute([$db['dbname']]);
            $databaseExists = (bool)$check->fetchColumn();
            $check->closeCursor();

            if ($databaseExists) {
                $pdo = install_connect($db);
                $ping = $pdo->query('SELECT 1');
                $ping->fetchColumn();
                $ping->closeCursor();
                $_SESSION['install_notice'] = ['type' => 'success', 'message' => 'Conexión MySQL validada correctamente. La base de datos ya existe y será utilizada.'];
            } else {
                $_SESSION['install_notice'] = ['type' => 'info', 'message' => 'Conexión MySQL correcta. La base de datos todavía no existe; el asistente intentará crearla automáticamente al finalizar.'];
            }

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
            // Keep the visible fields available when validation fails. Passwords are not stored server-side.
            $_SESSION['install_admin_draft'] = [
                'full_name' => $fullName,
                'email' => $email,
                'username' => $username,
            ];
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
            unset($_SESSION['install_admin_draft']);
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
                $server = install_connect($db, true);
                $check = $server->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1');
                $check->execute([$db['dbname']]);
                $databaseExists = (bool)$check->fetchColumn();
                $check->closeCursor();
                if (!$databaseExists) {
                    try {
                        $server->exec('CREATE DATABASE `' . str_replace('`', '``', $db['dbname']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                    } catch (Throwable $createError) {
                        throw new RuntimeException('La conexión MySQL es correcta, pero el usuario no tiene permiso para crear la base de datos automáticamente. Créala desde tu hosting con el nombre “' . $db['dbname'] . '” y vuelve a finalizar la instalación.');
                    }
                }
                $pdo = install_connect($db);
                install_apply_schema($pdo, $schemaFile, $cleanProjectTables);

                $roleQuery = $pdo->query("SELECT id FROM admin_roles WHERE role_key='owner' LIMIT 1");
                $roleId = (int)$roleQuery->fetchColumn();
                $roleQuery->closeCursor();
                if ($roleId < 1) throw new RuntimeException('No se encontró el rol Owner después de cargar el esquema.');
                $st = $pdo->prepare('INSERT INTO admin_users(username,full_name,email,password_hash,role_id,active) VALUES(?,?,?,?,?,1)');
                $st->execute([$admin['username'], $admin['full_name'], $admin['email'] !== '' ? $admin['email'] : null, $admin['password_hash'], $roleId]);
                $st->closeCursor();

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

            foreach (['install_db','install_admin','install_admin_draft','install_email','install_site_url','install_mode','install_fresh_retry'] as $key) unset($_SESSION[$key]);

            // A completed installation must always land on a clean login.
            // Destroy the installer/admin session and expire the remember-me
            // cookie so a session from the previous installation cannot send
            // the browser straight to /admin/dashboard.php.
            install_clear_stale_admin_auth(true);
            header('Location: ../admin/login.php');
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

$dbSuggestionHost = strtolower((string)(parse_url($detectedUrl, PHP_URL_HOST) ?: 'esmultiservicios'));
$dbSuggestionStem = preg_replace('/[^a-z0-9]+/i', '_', preg_replace('/^www\./i', '', explode('.', $dbSuggestionHost)[0] ?? 'esmultiservicios')) ?: 'esmultiservicios';
$dbSuggestion = trim($dbSuggestionStem, '_') . '_cms';

$dbDefaults = $_SESSION['install_db'] ?? [
    'host' => $reinstallConfig['host'] ?? 'localhost',
    'port' => $reinstallConfig['port'] ?? 3306,
    'db_prefix' => '',
    'db_name' => $reinstallConfig['dbname'] ?? '',
    'dbname' => $reinstallConfig['dbname'] ?? '',
    'username' => $reinstallConfig['username'] ?? '',
    'password' => $reinstallConfig['password'] ?? '',
    'create_database' => true,
];
$adminDefaults = $_SESSION['install_admin_draft'] ?? ($_SESSION['install_admin'] ?? ['full_name' => '', 'email' => '', 'username' => '']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'admin') {
    $adminDefaults = [
        'full_name' => trim((string)($_POST['full_name'] ?? '')),
        'email' => trim((string)($_POST['admin_email'] ?? '')),
        'username' => trim((string)($_POST['admin_username'] ?? '')),
    ];
}
$adminPasswordValue = ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'admin') ? (string)($_POST['admin_password'] ?? '') : '';
$adminPassword2Value = ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'admin') ? (string)($_POST['admin_password2'] ?? '') : '';
$emailDefaults = $_SESSION['install_email'] ?? ['mode' => 'LATER'];
$installNotice = is_array($_SESSION['install_notice'] ?? null) ? $_SESSION['install_notice'] : null;
unset($_SESSION['install_notice']);
$stepNames = [1 => 'Base de datos', 2 => 'Administrador', 3 => 'Correo', 4 => 'Confirmación'];
$wizardHasServerData = !empty($_SESSION['install_db']) || !empty($_SESSION['install_admin']) || !empty($_SESSION['install_admin_draft']) || (isset($_SESSION['install_email']) && is_array($_SESSION['install_email']));
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Instalación · ES MULTISERVICIOS CMS</title>
<link rel="icon" type="image/png" href="../assets/brand/favicon.png">
<link rel="stylesheet" href="../assets/vendor/sweetalert2/sweetalert2.min.css">
<link rel="stylesheet" href="../assets/vendor/show-notify/showNotify.css">
<link rel="stylesheet" href="../assets/vendor/select2/select2.local.css">
<link rel="stylesheet" href="../assets/action-icons.css">
<style>
:root{--navy:#0b2e59;--navy2:#123d6d;--blue:#0c78aa;--blue2:#075f8c;--orange:#f28c28;--ink:#172b44;--muted:#667a90;--page:#f3f7fb;--surface:#fff;--line:#dce6ef;--soft:#f7fafc;--softblue:#eef7fb;--ok:#13795b;--danger:#b42318;--shadow:0 28px 80px rgba(16,42,67,.14);--r:26px}
*{box-sizing:border-box}html{-webkit-text-size-adjust:100%}body{margin:0;background:var(--page);color:var(--ink);font-family:"Segoe UI Variable Text","Segoe UI Variable","Segoe UI",Tahoma,Arial,sans-serif;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;text-rendering:optimizeLegibility;font-kerning:normal}button,input,select,a{font:inherit}button,a{touch-action:manipulation}.page{min-height:100dvh;padding:clamp(12px,3vw,34px);display:grid;place-items:center}.wizard{width:min(1180px,100%);display:grid;grid-template-columns:minmax(250px,310px) minmax(0,1fr);background:var(--surface);border:1px solid var(--line);border-radius:30px;overflow:hidden;box-shadow:var(--shadow)}.aside{min-width:0;background:var(--navy);color:#fff;padding:clamp(24px,4vw,38px);display:flex;flex-direction:column;gap:22px}.brand{display:flex;align-items:center;gap:12px}.brand-mark{width:46px;height:46px;border-radius:15px;background:#fff;color:var(--navy);display:grid;place-items:center;font-weight:900}.brand strong{display:block;font-size:14px}.brand small{display:block;color:#c7d9ea;margin-top:3px}.aside h1{font-size:clamp(28px,3vw,36px);line-height:1.08;margin:6px 0 0}.aside p{margin:0;color:#c9d8e7;line-height:1.65;font-size:14px}.aside-steps{display:grid;gap:10px;margin-top:2px}.aside-step{display:grid;grid-template-columns:38px minmax(0,1fr);gap:11px;align-items:center;min-width:0;padding:8px 9px;border-radius:13px;color:#b9cadd;border:1px solid transparent}.aside-step-num{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.04);color:#d7e4ef;font-size:12px;font-weight:900}.aside-step strong{display:block;font-size:13px;line-height:1.25;color:inherit}.aside-step small{display:block;margin-top:3px;color:#8fa8bf;font-size:11px;line-height:1.3}.aside-step.done{color:#dff5eb}.aside-step.done .aside-step-num{background:#1d684f;border-color:#1d684f;color:#fff}.aside-step.active{background:rgba(255,255,255,.08);color:#fff;border-color:rgba(255,255,255,.12)}.aside-step.active .aside-step-num{background:#d92f24;border-color:#d92f24;color:#fff}.aside-step.active small{color:#d7e4ef}.mode-card{margin-top:auto;border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.07);border-radius:18px;padding:15px}.mode-card strong{display:block;font-size:13px}.mode-card span{display:block;color:#c9d8e7;font-size:12px;line-height:1.55;margin-top:5px}.content{min-width:0;padding:clamp(22px,5vw,52px)}.top{display:flex;align-items:flex-start;justify-content:space-between;gap:18px}.eyebrow{font-size:11px;letter-spacing:.14em;color:var(--blue);font-weight:800}.content h2{font-size:clamp(29px,5vw,43px);line-height:1.08;color:var(--navy);margin:8px 0 10px}.muted{color:var(--muted);line-height:1.65;margin:0}.counter{flex:0 0 auto;border:1px solid #cfe2ec;background:var(--softblue);border-radius:999px;padding:8px 12px;color:var(--navy);font-weight:900;font-size:12px;white-space:nowrap}.progress{height:7px;background:#e9eff5;border-radius:999px;overflow:hidden;margin:24px 0 18px}.progress span{display:block;height:100%;width:calc(var(--step) * 25%);background:var(--blue);border-radius:inherit;transition:width .2s ease}.steps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px;margin-bottom:28px}.step{min-width:0;border:1px solid var(--line);border-radius:15px;padding:11px;display:flex;align-items:center;gap:9px;background:#fbfdff}.step-num{width:31px;height:31px;flex:0 0 31px;border-radius:50%;display:grid;place-items:center;background:#e8eef4;color:#708196;font-size:12px;font-weight:900}.step strong{font-size:12px;line-height:1.2}.step small{display:block;font-size:10px;color:var(--muted);margin-top:2px}.step.active{border-color:#8fc8df;background:#f1f9fc}.step.active .step-num{background:var(--blue);color:#fff}.step.done{border-color:#b7ddcf;background:#f3faf7}.step.done .step-num{background:var(--ok);color:#fff}.alert{border-radius:15px;padding:13px 15px;margin:0 0 18px;font-size:13px;line-height:1.55}.alert.error{border:1px solid #f2b8b5;background:#fff4f3;color:#8d1c16}.intro{display:flex;gap:12px;align-items:flex-start;border:1px solid #d9e9f2;background:var(--softblue);border-radius:17px;padding:14px 16px;margin-bottom:22px}.intro-icon{width:34px;height:34px;flex:0 0 34px;border-radius:11px;background:var(--blue);color:#fff;display:grid;place-items:center;font-weight:900}.intro strong{font-size:13px;color:var(--navy)}.intro span{display:block;color:var(--muted);font-size:12px;line-height:1.5;margin-top:3px}.badge{display:inline-flex;align-items:center;gap:7px;padding:7px 10px;border-radius:999px;font-size:11px;font-weight:900;background:#fff3e7;color:#8d4a00;border:1px solid #f5cfaa;margin-bottom:16px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.full{grid-column:1/-1}.field{min-width:0;display:grid;gap:8px;color:var(--navy);font-size:13px;font-weight:700}.field input,.field select{width:100%;min-width:0;min-height:50px;border:1px solid #cfd9e4;border-radius:13px;background:#fff;padding:11px 13px;color:var(--ink);outline:none}.field input:focus,.field select:focus{border-color:#58aaca;box-shadow:0 0 0 4px rgba(12,120,170,.1)}.helper{font-size:11px;color:var(--muted);font-weight:500;line-height:1.45}.check{display:flex;gap:10px;align-items:flex-start;border:1px solid var(--line);background:var(--soft);border-radius:14px;padding:13px;color:var(--ink)}.check input{margin-top:3px}.safe{margin-top:18px;border-left:4px solid var(--blue);background:#f7fbfd;border-radius:12px;padding:13px 15px;font-size:12px;color:var(--muted);line-height:1.55}.safe strong{color:var(--navy)}.actions{display:flex;justify-content:space-between;gap:12px;margin-top:24px;flex-wrap:wrap}.btn{min-height:48px;border-radius:13px;padding:11px 17px;border:1px solid transparent;text-decoration:none;font-weight:700;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}.btn.primary{background:var(--navy);color:#fff}.btn.primary:hover{background:var(--navy2)}.btn.secondary{background:#fff;color:var(--navy);border-color:#bed3df}.btn.ghost{background:var(--soft);color:var(--navy);border-color:var(--line)}.btn.orange{background:var(--orange);color:#172b44}.choice-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:20px}.choice{position:relative;min-width:0;border:1px solid var(--line);background:#fff;border-radius:18px;padding:16px;display:grid;gap:6px;cursor:pointer}.choice input{position:absolute;opacity:0;pointer-events:none}.choice strong{color:var(--navy);font-size:15px}.choice span{color:var(--muted);font-size:12px;line-height:1.45}.choice.active{border-color:#6eb5d1;background:#f1f9fc;box-shadow:0 0 0 3px rgba(12,120,170,.08)}.choice-symbol{width:36px;height:36px;border-radius:11px;background:var(--navy);color:#fff;display:grid;place-items:center;font-weight:900;margin-bottom:3px}.email-panel,.box{border:1px solid var(--line);border-radius:18px;padding:18px;margin-top:16px;background:#fff}.email-panel[hidden]{display:none!important}.panel-head{display:flex;align-items:flex-start;gap:11px;margin-bottom:15px}.panel-icon{width:35px;height:35px;flex:0 0 35px;border-radius:11px;background:var(--blue);color:#fff;display:grid;place-items:center;font-weight:900}.panel-head h3,.box h3{margin:0;color:var(--navy);font-size:16px}.purpose-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:12px}.purpose{display:flex;gap:9px;align-items:flex-start;border:1px solid var(--line);border-radius:13px;padding:11px}.purpose strong{display:block;font-size:12px;color:var(--navy)}.purpose small{display:block;color:var(--muted);margin-top:2px;line-height:1.35}.test-row{display:grid;grid-template-columns:minmax(0,1fr) minmax(190px,auto);gap:14px;align-items:end;margin-top:16px}.test-row .btn{min-height:46px;align-self:end;white-space:nowrap}.test-result{min-height:18px;margin-top:10px;font-size:12px;font-weight:800}.test-result.ok{color:var(--ok)}.test-result.bad{color:var(--danger)}
#emailSenderRow[hidden]{display:none!important}
.test-row{align-items:start!important}
.test-action-field{grid-column:span 4;display:grid;grid-template-rows:auto 48px auto;gap:7px;min-width:0}
.test-action-field .field-label-spacer{visibility:hidden;font-size:13px;font-weight:800;line-height:1.2;min-height:16px}
.test-action-field .btn{width:100%;min-height:48px;height:48px;margin:0;align-self:stretch}
.test-action-field .helper-spacer{visibility:hidden;font-size:11px;line-height:1.45;min-height:16px}
@media(max-width:640px){.test-action-field{grid-column:1/-1;grid-template-rows:auto 48px}.test-action-field .field-label-spacer,.test-action-field .helper-spacer{display:none}.test-action-field .btn{width:100%}}
.review{display:grid;gap:12px}.review-card{border:1px solid var(--line);border-radius:17px;padding:16px;background:#fff;min-width:0}.review-card .review-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:11px}.review-card h3{margin:0;color:var(--navy);font-size:15px}.review-card a{color:var(--blue);font-size:12px;font-weight:900;text-decoration:none}.review-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.review-item{min-width:0;border-radius:12px;background:var(--soft);padding:10px 12px}.review-item span{display:block;color:var(--muted);font-size:10px;text-transform:uppercase;letter-spacing:.07em;font-weight:800}.review-item strong{display:block;margin-top:4px;color:var(--ink);font-size:12px;overflow-wrap:anywhere}.danger-note{border:1px solid #f2d19b;background:#fff8ec;color:#76420a;border-radius:15px;padding:14px;font-size:12px;line-height:1.55;margin-top:14px}.danger-note strong{color:#623400}.site-url{margin-top:16px;border:1px solid var(--line);border-radius:14px;padding:12px 14px;background:var(--soft);font-size:12px}.site-url span{display:block;color:var(--muted);font-size:10px;text-transform:uppercase;font-weight:900;letter-spacing:.07em}.site-url strong{display:block;margin-top:4px;overflow-wrap:anywhere;color:var(--navy)}
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


/* ==========================================================
   INSTALLER v2.1 — UNIFIED PREMIUM LAYOUT
   Visual language aligned with the ES MULTISERVICIOS / IZZY
   installer family: clean header, compact step rail, spacious form.
   ========================================================== */
body{background:#eef3f7}
.page{min-height:100dvh;display:block;padding:22px clamp(14px,3vw,34px) 34px}
.installer-topbar{width:min(1180px,100%);margin:0 auto 18px;min-height:96px;padding:18px 22px;display:flex;align-items:center;justify-content:space-between;gap:20px;background:#fff;border:1px solid #dbe5ee;border-top:4px solid var(--blue);border-radius:22px;box-shadow:0 14px 36px rgba(16,42,67,.08)}
.installer-brand{display:flex;align-items:center;gap:18px;min-width:0}.installer-brand img{width:118px;height:62px;object-fit:contain;object-position:left center;display:block}.installer-brand div{display:grid;gap:2px;min-width:0}.installer-brand span{font-size:11px;letter-spacing:.14em;color:var(--blue);font-weight:900}.installer-brand strong{font-size:22px;line-height:1.1;color:var(--navy)}.installer-brand small{color:var(--muted);font-size:13px}.install-mode-pill{flex:0 0 auto;padding:12px 18px;border-radius:13px;background:var(--navy);color:#fff;font-weight:900;font-size:13px;box-shadow:0 8px 18px rgba(11,46,89,.14)}
.wizard{width:min(1180px,100%);margin:0 auto;display:grid;grid-template-columns:232px minmax(0,1fr);background:transparent;border:0;border-radius:0;overflow:visible;box-shadow:none;gap:16px;align-items:stretch}
.aside{min-width:0;background:#fff;color:var(--ink);padding:22px 16px;border:1px solid var(--line);border-radius:22px;box-shadow:0 14px 34px rgba(16,42,67,.07);display:flex;flex-direction:column;gap:18px}
.aside-progress{display:grid;gap:4px;padding:0 2px 14px;border-bottom:1px solid var(--line)}.aside-progress strong{font-size:14px;color:var(--navy)}.aside-progress span{font-size:11px;color:var(--muted)}.aside-progress-bar{height:6px;margin-top:10px;border-radius:999px;background:#e7eef4;overflow:hidden}.aside-progress-bar i{display:block;height:100%;background:var(--blue);border-radius:999px}
.aside-steps{display:grid;grid-template-columns:1fr;gap:8px;margin:0}.aside-step{display:grid;grid-template-columns:34px minmax(0,1fr);gap:10px;align-items:center;padding:10px;border:1px solid transparent;border-radius:13px;color:#536a80;background:transparent}.aside-step-num{width:30px;height:30px;border-radius:9px;background:#edf2f6;border:0;color:#6c8297}.aside-step strong{font-size:12px;color:var(--ink)}.aside-step small{font-size:10px;color:#768ca0}.aside-step.active{background:#eaf7f9;border-color:#cce9ed;color:var(--navy)}.aside-step.active .aside-step-num{background:var(--blue);color:#fff;border:0}.aside-step.active strong{color:var(--navy)}.aside-step.active small{color:#52768b}.aside-step.done{background:#f4faf7;border-color:#d6eadf}.aside-step.done .aside-step-num{background:var(--ok);color:#fff}.installer-version{margin-top:auto;padding-top:14px;border-top:1px solid var(--line);font-size:10px;line-height:1.5;color:#7890a4}
.content{min-width:0;background:#fff;border:1px solid var(--line);border-radius:22px;padding:28px 28px 24px;box-shadow:0 14px 34px rgba(16,42,67,.07)}
.top{align-items:center}.eyebrow{font-size:12px;letter-spacing:.13em}.content h2{font-size:clamp(32px,4vw,42px);margin:7px 0 6px}.counter{padding:9px 13px;background:#f0f7fb;border-color:#cfe2ec;text-transform:uppercase;font-size:11px}.progress{margin:22px 0 22px;height:5px}.steps{display:none}.step-scroll{max-height:none;overflow:visible;padding-right:0}
.intro{border-color:#d7e8ef;background:#f3f9fb;border-radius:16px;padding:14px 16px;margin-bottom:20px}.intro-icon{background:var(--blue);border-radius:10px}.grid{gap:16px}.field{gap:7px}.field input,.field select{min-height:48px;border-radius:12px;border-color:#cbd8e4;background:#fff}.field input:focus,.field select:focus{border-color:#2f96bd;box-shadow:0 0 0 3px rgba(12,120,170,.1)}
.check{padding:13px 14px;border-radius:13px;background:#f8fbfd;align-items:center}.check input{width:18px;height:18px;margin:0;accent-color:var(--blue)}
.site-url-premium{position:relative;margin-top:18px;padding:15px 16px 15px 58px;background:#f4f9fc;border-color:#d3e5ef;border-radius:15px}.site-url-premium:before{content:'↗';position:absolute;left:14px;top:14px;width:34px;height:34px;display:grid;place-items:center;border-radius:10px;background:var(--blue);color:#fff;font-weight:900}.site-url-premium span{color:var(--blue);font-size:10px}.site-url-premium strong{font-size:14px;margin-top:3px}.site-url-premium small{display:block;margin-top:3px;color:var(--muted);font-size:11px;line-height:1.45}
.actions{margin-top:20px;padding-top:18px;border-top:1px solid #e9eff4}.btn{min-height:46px;border-radius:12px;padding:10px 17px}.btn.primary{background:var(--navy)}.btn.secondary{background:#edf5f8;color:var(--navy);border-color:#ccdee8}.btn.ghost{background:#edf2f6;color:var(--navy);border-color:#d9e3eb}.btn.orange{background:var(--orange);color:#10263f}
.email-panel,.box,.review-card{border-radius:16px;background:#fff}.choice{border-radius:16px;background:#fbfdff}.choice.active{background:#eef8fb}.safe{border-left:0;border:1px solid #d3e5ef;border-radius:13px;background:#f6fafc}
@media(max-width:900px){.installer-topbar{min-height:82px}.installer-brand img{width:92px;height:52px}.installer-brand strong{font-size:18px}.installer-brand small{display:none}.wizard{grid-template-columns:1fr}.aside{padding:16px}.aside-progress{border-bottom:0;padding-bottom:4px}.aside-steps{grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}.aside-step{display:flex;justify-content:center;padding:8px}.aside-step>div:last-child{display:none}.installer-version{display:none}.content{padding:22px}}
@media(max-width:640px){.page{padding:10px 10px 22px}.installer-topbar{margin-bottom:10px;padding:13px 14px;border-radius:18px;gap:10px}.installer-brand{gap:10px}.installer-brand img{width:62px;height:44px}.installer-brand span{font-size:9px}.installer-brand strong{font-size:15px}.install-mode-pill{padding:9px 11px;font-size:10px}.wizard{gap:10px}.aside{border-radius:18px}.aside-progress strong{font-size:12px}.aside-steps{gap:5px}.aside-step{padding:6px}.aside-step-num{width:28px;height:28px}.content{border-radius:18px;padding:18px 14px}.top{align-items:flex-start}.counter{font-size:10px;padding:7px 9px}.content h2{font-size:30px}.muted{font-size:13px}.grid,.choice-grid,.purpose-grid,.review-grid,.test-row{grid-template-columns:1fr}.full{grid-column:auto}.field input,.field select{min-height:46px}.site-url-premium{padding:14px 13px 14px 54px}.actions{flex-direction:column-reverse}.actions .btn,.actions>a.btn,.actions>button.btn{width:100%}.actions>span:empty{display:none}.choice-grid{gap:10px}.test-row .btn{width:100%}}

/* ==========================================================
   INSTALLER v2.2 — COMPACT DESKTOP LAYOUT
   Keeps the full step visible on common 1080p screens without
   making controls too small. Mobile behavior remains stacked.
   ========================================================== */
@media (min-width:901px){
  .page{padding:14px clamp(14px,2.2vw,28px) 18px}
  .installer-topbar{min-height:78px;margin-bottom:12px;padding:12px 18px;border-radius:18px}
  .installer-brand{gap:14px}
  .installer-brand img{width:92px;height:50px}
  .installer-brand span{font-size:10px}
  .installer-brand strong{font-size:19px}
  .installer-brand small{font-size:12px}
  .install-mode-pill{padding:10px 14px;font-size:12px}
  .wizard{grid-template-columns:214px minmax(0,1fr);gap:12px}
  .aside{padding:16px 13px;border-radius:18px;gap:12px}
  .aside-progress{padding-bottom:10px}
  .aside-progress-bar{margin-top:7px;height:5px}
  .aside-steps{gap:5px}
  .aside-step{grid-template-columns:30px minmax(0,1fr);gap:8px;padding:7px 8px;border-radius:11px}
  .aside-step-num{width:27px;height:27px;border-radius:8px}
  .aside-step strong{font-size:11px}
  .aside-step small{font-size:9px;margin-top:1px}
  .installer-version{padding-top:9px;font-size:9px}
  .content{padding:20px 22px 16px;border-radius:18px}
  .content h2{font-size:36px;margin:4px 0 4px}
  .eyebrow{font-size:10px}
  .muted{font-size:12px;line-height:1.45}
  .counter{padding:7px 10px;font-size:10px}
  .progress{margin:14px 0 14px;height:4px}
  .intro{padding:10px 12px;margin-bottom:13px;border-radius:13px}
  .intro-icon{width:30px;height:30px;flex-basis:30px;border-radius:9px}
  .intro strong{font-size:12px}
  .intro span{font-size:11px}
  .grid{gap:11px 14px}
  .field{gap:5px;font-size:12px}
  .field input,.field select{min-height:42px;padding:8px 11px;font-size:14px;border-radius:10px}
  .helper{font-size:10px;line-height:1.35}
  .check{padding:10px 12px;border-radius:11px}
  .check input{width:16px;height:16px}
  .site-url-premium{margin-top:12px;padding:11px 13px 11px 50px;border-radius:12px}
  .site-url-premium:before{left:11px;top:10px;width:30px;height:30px;border-radius:9px}
  .site-url-premium span{font-size:9px}
  .site-url-premium strong{font-size:12px}
  .site-url-premium small{font-size:10px}
  .actions{margin-top:12px;padding-top:12px}
  .btn{min-height:40px;padding:8px 14px;font-size:12px;border-radius:10px}
  .email-panel,.box,.review-card{padding:14px;margin-top:12px}
  .choice-grid{gap:9px;margin-bottom:14px}
  .choice{min-height:72px;padding:11px}
}
@media (min-width:901px) and (max-height:820px){
  .page{padding-top:8px;padding-bottom:10px}
  .installer-topbar{min-height:68px;margin-bottom:8px;padding:9px 16px}
  .installer-brand img{width:80px;height:42px}
  .installer-brand strong{font-size:17px}
  .installer-brand small{font-size:11px}
  .wizard{gap:9px}
  .aside{padding:12px 11px;gap:9px}
  .content{padding:15px 18px 12px}
  .content h2{font-size:31px}
  .progress{margin:10px 0}
  .intro{padding:8px 10px;margin-bottom:10px}
  .field input,.field select{min-height:39px}
  .site-url-premium{margin-top:9px}
  .actions{margin-top:9px;padding-top:9px}
}


/* ==========================================================
   INSTALLER v2.3 — LEGIBILITY / COMFORT ZOOM
   Slightly increases visual scale on desktop without bringing
   back unnecessary vertical scroll. Keeps the v2.2 compactness.
   ========================================================== */
@media (min-width:901px){
  .installer-topbar{width:min(1240px,100%);min-height:82px;padding:13px 20px}
  .installer-brand img{width:100px;height:54px}
  .installer-brand span{font-size:11px}
  .installer-brand strong{font-size:21px}
  .installer-brand small{font-size:13px}
  .install-mode-pill{font-size:13px;padding:10px 15px}

  .wizard{width:min(1240px,100%);grid-template-columns:224px minmax(0,1fr);gap:13px}
  .aside{padding:17px 14px;gap:13px}
  .aside-progress strong{font-size:15px}
  .aside-progress span{font-size:11px}
  .aside-step{grid-template-columns:32px minmax(0,1fr);padding:8px 9px}
  .aside-step-num{width:29px;height:29px}
  .aside-step strong{font-size:12px}
  .aside-step small{font-size:10px}
  .installer-version{font-size:9.5px}

  .content{padding:21px 24px 16px}
  .eyebrow{font-size:11px}
  .content h2{font-size:39px;line-height:1.05}
  .muted{font-size:13px;line-height:1.5}
  .counter{font-size:10.5px;padding:7px 11px}
  .progress{margin:13px 0 13px}
  .intro{padding:11px 13px;margin-bottom:12px}
  .intro-icon{width:32px;height:32px;flex-basis:32px}
  .intro strong{font-size:13px}
  .intro span{font-size:11.5px}

  .grid{gap:10px 14px}
  .field{font-size:13px;gap:5px}
  .field input,.field select{min-height:44px;padding:9px 12px;font-size:15px}
  .helper{font-size:10.5px;line-height:1.4}
  .check{padding:10px 12px}
  .check strong,.check b{font-size:13px}
  .check small{font-size:10.5px}
  .site-url-premium{margin-top:10px;padding:11px 13px 11px 51px}
  .site-url-premium:before{width:31px;height:31px}
  .site-url-premium span{font-size:9.5px}
  .site-url-premium strong{font-size:13px}
  .site-url-premium small{font-size:10.5px}
  .actions{margin-top:10px;padding-top:10px}
  .btn{min-height:41px;padding:8px 15px;font-size:13px}
}
@media (min-width:901px) and (max-height:820px){
  .installer-topbar{min-height:72px;padding:10px 17px}
  .installer-brand img{width:88px;height:46px}
  .installer-brand strong{font-size:19px}
  .wizard{grid-template-columns:218px minmax(0,1fr)}
  .aside{padding:13px 12px;gap:10px}
  .content{padding:16px 20px 12px}
  .content h2{font-size:34px}
  .muted{font-size:12.5px}
  .field input,.field select{min-height:41px;font-size:14px}
  .actions{margin-top:8px;padding-top:8px}
}

/* ==========================================================
   INSTALLER v2.4 — READABLE DESKTOP SCALE
   IMPORTANT: this block intentionally comes last. Previous
   compact-height rules made the installer look too small on
   normal laptop/desktop viewports. Keep the interface readable
   and only compact aggressively on genuinely short screens.
   ========================================================== */
@media (min-width:901px){
  .page{padding:18px clamp(18px,2.3vw,34px) 24px}
  .installer-topbar{width:min(1360px,100%);min-height:92px;margin-bottom:16px;padding:15px 24px;border-radius:20px}
  .installer-brand{gap:17px}
  .installer-brand img{width:112px;height:60px}
  .installer-brand span{font-size:11.5px}
  .installer-brand strong{font-size:23px;line-height:1.08}
  .installer-brand small{font-size:13.5px}
  .install-mode-pill{padding:11px 17px;font-size:13.5px;border-radius:12px}

  .wizard{width:min(1360px,100%);grid-template-columns:250px minmax(0,1fr);gap:16px;max-height:none;overflow:visible}
  .aside{display:flex!important;padding:21px 17px;border-radius:20px;gap:16px}
  .aside-progress{padding-bottom:13px}
  .aside-progress strong{font-size:16px}
  .aside-progress span{font-size:12px}
  .aside-progress-bar{height:6px;margin-top:9px}
  .aside-steps{gap:8px}
  .aside-step{grid-template-columns:35px minmax(0,1fr);gap:10px;padding:9px 10px;border-radius:12px}
  .aside-step-num{width:32px;height:32px;border-radius:9px;font-size:12px}
  .aside-step strong{font-size:13px;line-height:1.2}
  .aside-step small{font-size:10.5px;line-height:1.3;margin-top:2px}
  .installer-version{padding-top:12px;font-size:10.5px;line-height:1.45}

  .content{max-height:none;padding:28px 32px 22px;border-radius:20px}
  .eyebrow{font-size:12px}
  .content h2{font-size:44px;line-height:1.04;margin:6px 0 6px}
  .muted{font-size:14px;line-height:1.5}
  .counter{padding:8px 12px;font-size:11.5px}
  .progress{height:5px;margin:16px 0 16px}
  .step-scroll{overflow:visible;padding-right:0}
  .intro{padding:13px 15px;margin-bottom:16px;border-radius:14px}
  .intro-icon{width:34px;height:34px;flex-basis:34px}
  .intro strong{font-size:14px}
  .intro span{font-size:12px;line-height:1.45}
  .grid{gap:14px 18px}
  .field{font-size:14px;gap:6px}
  .field input,.field select{min-height:48px;padding:10px 13px;font-size:16px;border-radius:11px}
  .helper{font-size:11.5px;line-height:1.4}
  .check{padding:12px 14px;border-radius:12px;font-size:14px}
  .check input{width:18px;height:18px}
  .site-url-premium{margin-top:14px;padding:13px 15px 13px 56px;border-radius:13px}
  .site-url-premium:before{left:13px;top:12px;width:33px;height:33px}
  .site-url-premium span{font-size:10.5px}
  .site-url-premium strong{font-size:14px}
  .site-url-premium small{font-size:11.5px}
  .actions{position:static;background:none;margin-top:14px;padding-top:14px;padding-bottom:0}
  .btn{min-height:44px;padding:9px 16px;font-size:14px;border-radius:11px}
}

/* Do not trigger the old ultra-compact layout on normal laptop heights. */
@media (min-width:901px) and (min-height:701px){
  .aside{display:flex!important}
  .wizard{grid-template-columns:250px minmax(0,1fr)!important}
}

/* Only genuinely short screens get a compact version, while preserving legibility. */
@media (min-width:901px) and (max-height:700px){
  .page{padding-top:8px;padding-bottom:10px}
  .installer-topbar{min-height:74px;margin-bottom:9px;padding:10px 18px}
  .installer-brand img{width:90px;height:46px}
  .installer-brand strong{font-size:20px}
  .installer-brand small{font-size:11.5px}
  .wizard{grid-template-columns:220px minmax(0,1fr);gap:10px}
  .aside{display:flex!important;padding:13px 12px;gap:10px}
  .aside-progress strong{font-size:14px}
  .aside-step{padding:6px 8px}
  .aside-step-num{width:28px;height:28px}
  .aside-step strong{font-size:11.5px}
  .aside-step small{font-size:9.5px}
  .content{padding:17px 20px 13px}
  .content h2{font-size:35px}
  .muted{font-size:12.5px}
  .progress{margin:10px 0}
  .intro{padding:9px 11px;margin-bottom:10px}
  .field{font-size:12.5px}
  .field input,.field select{min-height:41px;font-size:14.5px}
  .site-url-premium{margin-top:9px}
  .actions{margin-top:9px;padding-top:9px}
}

/* ==========================================================
   INSTALLER v2.5 — FLUID VIEWPORT / TRUE RESPONSIVE LAYOUT
   Keep the whole installer inside normal desktop heights when
   possible. On genuinely smaller screens, use document scroll
   instead of clipping or internal scroll areas.
   ========================================================== */
.page{
  min-height:100dvh;
  display:block;
  padding:clamp(8px,1.6vh,18px) clamp(10px,2vw,28px) clamp(14px,2vh,24px);
}
.installer-topbar,.wizard{margin-left:auto;margin-right:auto}

/* Fluid field/card grids: 3 columns on wide screens, 2 on medium, 1 on mobile. */
.grid,.purpose-grid,.review-grid{
  grid-template-columns:repeat(auto-fit,minmax(min(260px,100%),1fr));
}
.choice-grid{
  grid-template-columns:repeat(auto-fit,minmax(min(220px,100%),1fr));
}
.full{grid-column:1/-1}

@media (min-width:1400px){
  .grid{grid-template-columns:repeat(3,minmax(0,1fr))}
  .purpose-grid,.review-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
  .choice-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
}
@media (min-width:901px) and (max-width:1399px){
  .grid,.purpose-grid,.review-grid,.choice-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media (max-width:700px){
  .grid,.purpose-grid,.review-grid,.choice-grid,.test-row{grid-template-columns:1fr}
  .full{grid-column:auto}
}

/* Desktop/laptop: readable but height-aware. */
@media (min-width:901px){
  .installer-topbar{
    width:min(1320px,100%);
    min-height:clamp(68px,8.5vh,84px);
    margin-bottom:clamp(8px,1.2vh,14px);
    padding:clamp(9px,1.4vh,13px) 20px;
  }
  .installer-brand img{width:clamp(86px,6vw,104px);height:clamp(44px,5.5vh,56px)}
  .installer-brand strong{font-size:clamp(19px,1.35vw,22px)}
  .installer-brand small{font-size:clamp(11px,.82vw,13px)}
  .install-mode-pill{font-size:clamp(11px,.78vw,13px);padding:9px 14px}

  .wizard{
    width:min(1320px,100%);
    grid-template-columns:clamp(210px,16vw,240px) minmax(0,1fr)!important;
    gap:clamp(9px,1vw,14px);
    max-height:none!important;
    overflow:visible!important;
  }
  .aside{
    display:flex!important;
    padding:clamp(12px,1.7vh,18px) 14px;
    gap:clamp(9px,1.35vh,14px);
    min-height:0;
  }
  .aside-progress{padding-bottom:clamp(7px,1vh,11px)}
  .aside-progress strong{font-size:clamp(14px,.95vw,16px)}
  .aside-progress span{font-size:clamp(10px,.72vw,12px)}
  .aside-steps{gap:clamp(4px,.7vh,7px)}
  .aside-step{padding:clamp(6px,.75vh,8px) 8px}
  .aside-step-num{width:clamp(27px,2vw,31px);height:clamp(27px,2vw,31px)}
  .aside-step strong{font-size:clamp(11.5px,.78vw,13px)}
  .aside-step small{font-size:clamp(9px,.64vw,10.5px)}
  .installer-version{font-size:clamp(9px,.62vw,10.5px);padding-top:8px}

  .content{
    padding:clamp(15px,2vh,22px) clamp(18px,2vw,28px) clamp(12px,1.7vh,18px);
    max-height:none!important;
    overflow:visible!important;
  }
  .content h2{font-size:clamp(34px,3vw,42px);margin:4px 0 4px}
  .eyebrow{font-size:clamp(10px,.72vw,11.5px)}
  .muted{font-size:clamp(12px,.85vw,13.5px);line-height:1.42}
  .counter{font-size:clamp(10px,.72vw,11.5px);padding:7px 10px}
  .progress{height:4px;margin:clamp(9px,1.2vh,13px) 0}
  .intro{padding:clamp(9px,1.2vh,12px) 13px;margin-bottom:clamp(10px,1.3vh,14px)}
  .intro-icon{width:31px;height:31px;flex-basis:31px}
  .intro strong{font-size:clamp(12px,.82vw,13.5px)}
  .intro span{font-size:clamp(10.5px,.72vw,11.5px)}
  .grid{gap:clamp(8px,1vh,12px) 14px}
  .field{gap:4px;font-size:clamp(12px,.82vw,13.5px)}
  .field input,.field select{min-height:clamp(40px,4.7vh,46px);padding:8px 11px;font-size:clamp(14px,.95vw,15.5px)}
  .helper{font-size:clamp(10px,.68vw,11px);line-height:1.32}
  .check{padding:clamp(9px,1.1vh,11px) 12px}
  .check input{width:17px;height:17px}
  .site-url-premium{margin-top:clamp(9px,1.2vh,12px);padding:clamp(10px,1.2vh,12px) 13px clamp(10px,1.2vh,12px) 52px}
  .site-url-premium:before{width:31px;height:31px;top:10px}
  .site-url-premium span{font-size:9.5px}
  .site-url-premium strong{font-size:clamp(12px,.82vw,13.5px)}
  .site-url-premium small{font-size:clamp(9.5px,.68vw,10.5px)}
  .actions{margin-top:clamp(9px,1.2vh,12px);padding-top:clamp(9px,1.2vh,12px)}
  .btn{min-height:clamp(39px,4.4vh,43px);padding:8px 14px;font-size:clamp(12px,.82vw,13.5px)}
}

/* Short desktops: keep good reading size, but let the PAGE scroll if needed. */
@media (min-width:901px) and (max-height:760px){
  .page{padding-top:6px;padding-bottom:12px}
  .installer-topbar{min-height:64px;margin-bottom:7px;padding-top:8px;padding-bottom:8px}
  .installer-brand img{width:82px;height:40px}
  .installer-brand strong{font-size:18px}
  .installer-brand small{font-size:10.5px}
  .wizard{grid-template-columns:205px minmax(0,1fr)!important;gap:8px}
  .aside{padding:10px 10px;gap:7px}
  .aside-progress strong{font-size:13px}
  .aside-progress span{font-size:9.5px}
  .aside-step{padding:5px 7px}
  .aside-step-num{width:26px;height:26px}
  .aside-step strong{font-size:11px}
  .aside-step small{font-size:8.8px}
  .content{padding:12px 16px 10px}
  .content h2{font-size:31px}
  .muted{font-size:11.5px}
  .intro{padding:8px 10px;margin-bottom:8px}
  .field input,.field select{min-height:38px;font-size:13.5px}
  .check{padding:8px 10px}
  .site-url-premium{margin-top:8px;padding-top:9px;padding-bottom:9px}
  .actions{margin-top:8px;padding-top:8px}
  .btn{min-height:38px;font-size:12px}
}

/* Tablet: sidebar becomes a compact step strip; form is 2 columns when space allows. */
@media (min-width:701px) and (max-width:900px){
  .page{padding:10px 12px 18px}
  .installer-topbar{margin-bottom:9px}
  .wizard{grid-template-columns:1fr!important;gap:9px}
  .aside{padding:12px;border-radius:17px}
  .aside-progress{padding-bottom:5px}
  .aside-steps{grid-template-columns:repeat(4,minmax(0,1fr));gap:6px}
  .aside-step{display:flex;justify-content:center;padding:7px}
  .aside-step>div:last-child{display:none}
  .installer-version{display:none}
  .content{padding:18px 16px}
  .grid,.purpose-grid,.review-grid,.choice-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
  .full{grid-column:1/-1}
}

/* Phone: one column, no horizontal overflow, natural page scroll. */
@media (max-width:700px){
  html,body{overflow-x:hidden}
  .page{padding:8px 8px 18px}
  .installer-topbar{padding:11px 12px;margin-bottom:8px}
  .installer-brand img{width:58px;height:40px}
  .installer-brand strong{font-size:15px}
  .installer-brand span{font-size:8.5px}
  .install-mode-pill{font-size:9.5px;padding:8px 9px}
  .wizard{grid-template-columns:1fr!important;gap:8px}
  .aside{padding:11px;border-radius:16px}
  .aside-steps{grid-template-columns:repeat(4,minmax(0,1fr));gap:4px}
  .aside-step{display:flex;justify-content:center;padding:5px}
  .aside-step>div:last-child{display:none}
  .installer-version{display:none}
  .content{padding:15px 12px;border-radius:16px}
  .content h2{font-size:29px}
  .top{gap:8px}
  .counter{font-size:9.5px}
  .grid,.purpose-grid,.review-grid,.choice-grid,.test-row{grid-template-columns:1fr}
  .full{grid-column:auto}
  .field input,.field select{min-height:44px;font-size:15px}
  .actions{flex-direction:column-reverse}
  .actions .btn,.test-row .btn{width:100%}
}


/* Database naming assistant */
.optional-tag{display:inline-flex;align-items:center;margin-left:6px;padding:2px 6px;border-radius:999px;background:#eef5f8;color:#668096;font-size:.72em;font-weight:800;vertical-align:middle}
.db-suggestion{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.suggestion-button{border:0;background:transparent;color:var(--blue);padding:0;font:inherit;font-weight:900;cursor:pointer;text-decoration:underline;text-underline-offset:2px}
.suggestion-button:hover{color:var(--blue2)}
.db-final-preview{min-width:0;border:1px solid #cfe2ec;background:#f3f9fb;border-radius:13px;padding:10px 12px;display:flex;flex-direction:column;justify-content:center;gap:3px}
.db-final-preview span{font-size:10px;letter-spacing:.06em;text-transform:uppercase;color:#648095;font-weight:900}
.db-final-preview strong{font-size:15px;color:var(--navy);overflow-wrap:anywhere;line-height:1.2}
.db-final-preview small{font-size:10px;color:var(--muted);line-height:1.35}
@media(max-width:700px){.db-final-preview{padding:11px 12px}.db-final-preview strong{font-size:14px}}


/* ADMIN STEP — equal, aligned controls */
.admin-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;align-items:start}
.admin-grid .field{min-width:0;width:100%}
.admin-grid .field input,.admin-grid .field select{width:100%}
@media(max-width:1100px){.admin-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:680px){.admin-grid{grid-template-columns:1fr}}

/* v2.8 — consistent step height and controlled overflow */
@media (min-width:901px){
  .wizard{
    height:clamp(590px,calc(100dvh - 165px),650px)!important;
    min-height:590px;
    align-items:stretch!important;
    overflow:visible!important;
  }
  .aside,.content{height:100%;min-height:0}
  .content{display:flex!important;flex-direction:column;overflow:hidden!important}
  .top,.progress{flex:0 0 auto}
  .step-scroll{
    flex:1 1 auto;
    min-height:0;
    overflow-y:auto!important;
    overflow-x:hidden;
    padding-right:6px;
    scrollbar-gutter:stable;
    overscroll-behavior:contain;
  }
  .step-scroll::-webkit-scrollbar{width:9px}
  .step-scroll::-webkit-scrollbar-track{background:#eef4f8;border-radius:999px}
  .step-scroll::-webkit-scrollbar-thumb{background:#b6c8d6;border-radius:999px;border:2px solid #eef4f8}
  .step-scroll::-webkit-scrollbar-thumb:hover{background:#8ea9ba}
  .step-scroll>form{min-height:calc(100% - 2px);display:flex;flex-direction:column}
  .step-scroll>form>.actions{margin-top:auto}
}
@media (min-width:901px) and (max-height:720px){
  .page{align-items:start!important;padding-top:8px!important;padding-bottom:14px!important}
  .wizard{height:590px!important;min-height:590px}
}
@media (max-width:900px){
  .wizard{height:auto!important;min-height:0!important}
  .aside,.content{height:auto!important}
  .content{overflow:visible!important}
  .step-scroll{overflow:visible!important;max-height:none!important;padding-right:0!important}
}

.step-support{
  margin-top:14px;
  border:1px solid #d7e5ee;
  background:#f7fbfd;
  border-radius:14px;
  padding:12px 14px;
  display:grid;
  grid-template-columns:repeat(3,minmax(0,1fr));
  gap:10px;
}
.step-support-item{min-width:0;display:flex;align-items:flex-start;gap:9px}
.step-support-icon{width:29px;height:29px;flex:0 0 29px;border-radius:9px;display:grid;place-items:center;background:#e6f5f9;color:var(--blue);font-weight:900}
.step-support-item strong{display:block;color:var(--navy);font-size:11px;line-height:1.25}
.step-support-item span{display:block;color:var(--muted);font-size:9.8px;line-height:1.35;margin-top:2px}
@media(max-width:1100px){.step-support{grid-template-columns:1fr 1fr}.step-support-item:last-child{grid-column:1/-1}}
@media(max-width:700px){.step-support{grid-template-columns:1fr}.step-support-item:last-child{grid-column:auto}}


/* ==========================================================
   INSTALLER v2.9 — 95% VIEWPORT, FIXED ACTION FOOTER, 12-COL GRID
   The complete installer (top bar + wizard) occupies roughly 95dvh.
   Content scrolls inside the step area while Back/Next/Continue stay visible.
   ========================================================== */
html,body{height:100%;overflow:hidden}
.page{
  height:100dvh!important;
  min-height:0!important;
  padding:2.5dvh clamp(10px,2vw,28px)!important;
  display:flex!important;
  flex-direction:column;
  align-items:stretch!important;
  justify-content:flex-start!important;
  overflow:hidden!important;
}
.installer-topbar{
  flex:0 0 auto;
  width:min(1360px,100%)!important;
  margin:0 auto clamp(8px,1.15vh,12px)!important;
}
.wizard{
  flex:1 1 auto!important;
  width:min(1360px,100%)!important;
  height:auto!important;
  min-height:0!important;
  max-height:none!important;
  margin:0 auto!important;
  align-items:stretch!important;
  overflow:hidden!important;
}
.aside,.content{min-height:0!important;height:100%!important}
.content{
  display:flex!important;
  flex-direction:column!important;
  overflow:hidden!important;
}
.top,.progress{flex:0 0 auto}
.step-scroll{
  flex:1 1 auto!important;
  min-height:0!important;
  overflow-y:auto!important;
  overflow-x:hidden!important;
  padding-right:7px!important;
  scrollbar-gutter:stable;
  overscroll-behavior:contain;
}
.step-scroll::-webkit-scrollbar{width:9px}
.step-scroll::-webkit-scrollbar-track{background:#eef4f8;border-radius:999px}
.step-scroll::-webkit-scrollbar-thumb{background:#afc3d3;border-radius:999px;border:2px solid #eef4f8}
.step-scroll::-webkit-scrollbar-thumb:hover{background:#879fb1}
.step-scroll>form{
  min-height:100%;
  display:flex;
  flex-direction:column;
  min-width:0;
}
.actions{
  position:sticky!important;
  left:0;
  right:0;
  bottom:0!important;
  z-index:30;
  flex:0 0 auto;
  margin-top:auto!important;
  padding:12px 0 2px!important;
  background:linear-gradient(to bottom,rgba(255,255,255,.78),#fff 28%);
  border-top:1px solid #e2eaf1;
  box-shadow:0 -10px 22px rgba(16,42,67,.045);
}

/* Twelve-column system — no tables. */
.grid,.admin-grid,.choice-grid,.purpose-grid,.review-grid,.test-row{
  display:grid!important;
  grid-template-columns:repeat(12,minmax(0,1fr))!important;
  align-items:start;
}
.grid>* ,.admin-grid>*{grid-column:span 6;min-width:0}
.grid>.full,.admin-grid>.full,.full{grid-column:1/-1!important}
.choice-grid>*{grid-column:span 4;min-width:0}
.purpose-grid>*{grid-column:span 6;min-width:0}
.review-grid>*{grid-column:span 6;min-width:0}
.test-row>.field{grid-column:span 8;min-width:0}
.test-row>.btn{grid-column:span 4;min-width:0}

@media (min-width:1280px){
  .grid>* ,.admin-grid>*{grid-column:span 4}
  .grid>.full,.admin-grid>.full,.full{grid-column:1/-1!important}
  .purpose-grid>*{grid-column:span 3}
}

/* Tablet: compact progress header + fixed footer + internal scroll. */
@media (max-width:900px){
  .page{padding:1.8dvh 10px!important}
  .installer-topbar{margin-bottom:8px!important}
  .wizard{
    grid-template-columns:1fr!important;
    grid-template-rows:auto minmax(0,1fr)!important;
    gap:8px!important;
  }
  .aside{height:auto!important;max-height:none!important;flex:0 0 auto}
  .content{height:100%!important;min-height:0!important;overflow:hidden!important}
  .step-scroll{overflow-y:auto!important;padding-right:5px!important}
  .grid>* ,.admin-grid>*,.choice-grid>*,.purpose-grid>*,.review-grid>*{grid-column:span 6}
  .grid>.full,.admin-grid>.full,.full{grid-column:1/-1!important}
  .test-row>.field{grid-column:span 7}
  .test-row>.btn{grid-column:span 5}
  .actions{padding-top:10px!important}
}

/* Phone: single-column 12-grid, same 95% viewport behavior. */
@media (max-width:700px){
  .page{padding:1.4dvh 8px!important}
  .installer-topbar{margin-bottom:6px!important}
  .wizard{gap:6px!important}
  .aside{padding:9px!important}
  .content{padding:13px 11px!important}
  .grid>* ,.admin-grid>*,.choice-grid>*,.purpose-grid>*,.review-grid>*,.test-row>*{
    grid-column:1/-1!important;
  }
  .actions{
    flex-direction:row!important;
    gap:8px!important;
    padding:9px 0 1px!important;
  }
  .actions .btn,.actions>a.btn,.actions>button.btn{
    width:auto!important;
    flex:1 1 0;
    min-width:0;
  }
  .actions>span:empty{display:block!important;flex:1 1 0}
}

/* Extremely short devices: preserve layout; only the content area scrolls. */
@media (max-height:620px){
  .page{padding-top:6px!important;padding-bottom:6px!important}
  .installer-topbar{min-height:58px!important;padding-top:7px!important;padding-bottom:7px!important;margin-bottom:5px!important}
  .content{padding-top:10px!important;padding-bottom:8px!important}
  .aside{padding-top:8px!important;padding-bottom:8px!important}
}



/* ==========================================================
   INSTALLER v3.0 — STANDARDIZED PREMIUM LAYOUT
   Matches the shared CMS installer standard: balanced 95vh shell,
   exact 12-column alignment, fixed action footer, and scroll only
   when content actually overflows.
   ========================================================== */
:root{--installer-max:1420px}
html,body{height:100%;overflow:hidden}
.page{
  height:100dvh!important;
  padding:2.2dvh clamp(12px,2.2vw,34px)!important;
  display:flex!important;
  flex-direction:column!important;
  overflow:hidden!important;
}
.installer-topbar{
  width:min(var(--installer-max),100%)!important;
  min-height:82px!important;
  margin:0 auto 12px!important;
  padding:12px 24px!important;
  border-radius:20px!important;
}
.wizard{
  width:min(var(--installer-max),100%)!important;
  flex:1 1 auto!important;
  min-height:0!important;
  height:auto!important;
  margin:0 auto!important;
  grid-template-columns:250px minmax(0,1fr)!important;
  gap:16px!important;
  overflow:hidden!important;
}
.aside,.content{height:100%!important;min-height:0!important}
.aside{padding:20px 17px!important;border-radius:20px!important}
.content{
  padding:24px 28px 0!important;
  border-radius:20px!important;
  display:flex!important;
  flex-direction:column!important;
  overflow:hidden!important;
}
.top,.progress{flex:0 0 auto}
.progress{margin:14px 0 14px!important}
.step-scroll{
  flex:1 1 auto!important;
  min-height:0!important;
  overflow-y:auto!important;
  overflow-x:hidden!important;
  padding-right:0!important;
  scrollbar-gutter:auto!important;
  overscroll-behavior:contain;
}
/* Do not reserve or paint a scrollbar track when no overflow exists. */
.step-scroll::-webkit-scrollbar{width:9px}
.step-scroll::-webkit-scrollbar-track{background:transparent}
.step-scroll::-webkit-scrollbar-thumb{background:#b7c9d6;border-radius:999px;border:2px solid transparent;background-clip:padding-box}
.step-scroll::-webkit-scrollbar-thumb:hover{background:#8fa8ba;background-clip:padding-box}
.step-scroll>form{
  min-height:100%;
  display:flex!important;
  flex-direction:column!important;
  min-width:0;
}
.actions{
  position:sticky!important;
  bottom:0!important;
  z-index:40!important;
  flex:0 0 auto!important;
  margin-top:auto!important;
  padding:12px 0 14px!important;
  background:#fff!important;
  border-top:1px solid #e2eaf1!important;
  box-shadow:0 -8px 18px rgba(16,42,67,.035)!important;
}
/* Strict 12-column grid: every row finishes on the same line. */
.grid,.admin-grid,.choice-grid,.purpose-grid,.review-grid,.test-row{
  display:grid!important;
  grid-template-columns:repeat(12,minmax(0,1fr))!important;
  column-gap:16px!important;
  row-gap:12px!important;
  align-items:start!important;
}
.grid>* ,.admin-grid>*{grid-column:span 4!important;min-width:0!important}
.grid>.full,.admin-grid>.full,.full{grid-column:1/-1!important}
.choice-grid>*{grid-column:span 4!important;min-width:0!important}
.purpose-grid>*{grid-column:span 3!important;min-width:0!important}
.review-grid>*{grid-column:span 6!important;min-width:0!important}
.test-row>.field{grid-column:span 8!important;min-width:0!important}
.test-row>.btn{grid-column:span 4!important;min-width:0!important}
.field{min-width:0!important;width:100%!important}
.field-label{display:flex;align-items:center;gap:7px;min-height:20px;line-height:20px;font-weight:900;color:var(--ink)}
.field input,.field select{width:100%!important;box-sizing:border-box!important}
.optional-tag{margin-left:0!important;line-height:1!important}
.db-final-preview{height:100%!important;min-height:91px!important;box-sizing:border-box!important;align-self:stretch!important}
/* Purpose selector toolbar */
.purpose-heading{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:12px}
.purpose-heading h3{margin:0 0 4px}
.purpose-heading p{margin:0}
.select-all-purpose{display:inline-flex;align-items:center;gap:8px;flex:0 0 auto;cursor:pointer;font-weight:900;color:var(--navy);font-size:12px;user-select:none;position:relative;min-height:24px}
.select-all-purpose input{appearance:none;-webkit-appearance:none;width:22px;height:22px;flex:0 0 22px;margin:0;border-radius:7px;border:1px solid #bdd0dc;background:#f8fbfd;display:grid;place-items:center;cursor:pointer;outline:none;box-shadow:inset 0 0 0 1px rgba(255,255,255,.7)}
.select-all-purpose input:checked{background:var(--blue);border-color:var(--blue)}
.select-all-purpose input:checked:after{content:'✓';color:#fff;font-weight:1000;font-size:14px;line-height:1}
.select-all-purpose input:focus-visible{box-shadow:0 0 0 3px rgba(12,120,170,.16)}
.select-all-box{display:none!important}
.defer-help{margin:12px 0;border:1px solid #d3e6ee;background:#f5fafc;border-radius:14px;padding:13px 14px;display:flex;gap:11px;align-items:flex-start}
.defer-help[hidden]{display:none!important}
.defer-help-icon{width:30px;height:30px;flex:0 0 30px;border-radius:9px;display:grid;place-items:center;background:#e3f3f8;color:var(--blue);font-weight:1000}
.defer-help strong{display:block;color:var(--navy);font-size:12.5px}
.defer-help span{display:block;color:var(--muted);font-size:11px;line-height:1.45;margin-top:2px}
/* Keep the first step scrollbar-free on normal desktop/laptop heights. */
@media (min-width:901px) and (min-height:760px){
  .content{padding-top:20px!important}
  .content h2{font-size:42px!important;margin-top:4px!important;margin-bottom:4px!important}
  .muted{font-size:13px!important}
  .intro{margin-bottom:12px!important;padding:11px 13px!important}
  .grid{row-gap:10px!important}
  .field input,.field select{min-height:44px!important}
  .check{padding:10px 12px!important}
  .site-url-premium{margin-top:10px!important;padding-top:10px!important;padding-bottom:10px!important}
}
@media (max-width:1180px){
  .grid>* ,.admin-grid>*{grid-column:span 6!important}
  .grid>.full,.admin-grid>.full,.full{grid-column:1/-1!important}
  .choice-grid>*{grid-column:span 6!important}
  .purpose-grid>*{grid-column:span 6!important}
  .wizard{grid-template-columns:225px minmax(0,1fr)!important}
}
@media (max-width:900px){
  html,body{overflow:auto!important}
  .page{height:auto!important;min-height:100dvh!important;overflow:visible!important;padding:10px 12px 16px!important}
  .installer-topbar{min-height:74px!important;margin-bottom:9px!important}
  .wizard{height:auto!important;min-height:calc(100dvh - 110px)!important;grid-template-columns:1fr!important;grid-template-rows:auto minmax(0,1fr)!important;overflow:visible!important}
  .aside{height:auto!important}
  .content{height:min(78dvh,760px)!important;min-height:560px!important;overflow:hidden!important}
  .grid>* ,.admin-grid>*,.choice-grid>*,.purpose-grid>*,.review-grid>*{grid-column:span 6!important}
  .full,.grid>.full,.admin-grid>.full{grid-column:1/-1!important}
  .purpose-heading{align-items:flex-start;flex-direction:column;gap:10px}
}
@media (max-width:700px){
  .page{padding:8px!important}
  .content{height:76dvh!important;min-height:520px!important;padding:16px 12px 0!important}
  .grid>* ,.admin-grid>*,.choice-grid>*,.purpose-grid>*,.review-grid>*,.test-row>*{grid-column:1/-1!important}
  .actions{gap:8px!important;padding:10px 0 11px!important}
  .purpose-heading{margin-bottom:10px}
}
@media (max-height:700px) and (min-width:901px){
  .page{padding-top:8px!important;padding-bottom:8px!important}
  .installer-topbar{min-height:68px!important;margin-bottom:8px!important}
  .wizard{gap:10px!important}
  .content{padding-top:15px!important}
  .content h2{font-size:34px!important}
  .step-scroll{overflow-y:auto!important}
}


/* ==========================================================
   INSTALLER v3.1 — clean conditional scroll + fixed action footer
   ========================================================== */
.content{position:relative!important;display:flex!important;flex-direction:column!important;min-height:0!important;overflow:hidden!important}
.step-scroll{
  flex:1 1 auto!important;
  min-height:0!important;
  overflow-y:auto!important;
  overflow-x:hidden!important;
  padding-right:16px!important;
  scrollbar-gutter:auto!important;
}
.step-scroll::-webkit-scrollbar{width:8px}
.step-scroll::-webkit-scrollbar-track{background:transparent}
.step-scroll::-webkit-scrollbar-thumb{background:#b7c9d7;border-radius:999px}
.step-scroll::-webkit-scrollbar-thumb:hover{background:#8ea8ba}
.content>.actions{
  position:static!important;
  flex:0 0 auto!important;
  margin:0!important;
  padding:12px 0 0!important;
  border-top:1px solid #e3ebf2!important;
  background:#fff!important;
  box-shadow:none!important;
  z-index:50!important;
}
.step-scroll>form{min-height:0!important;display:block!important}
.step-scroll.step-1>form,.step-scroll.step-2>form,.step-scroll.step-3>form{min-height:0!important}
.step-scroll.step-4>form{min-height:0!important}
/* keep content away from scrollbar and preserve 12-column endings */
.grid,.admin-grid,.choice-grid,.purpose-grid,.review-grid,.test-row{width:100%!important}
.grid>* ,.admin-grid>*,.choice-grid>*,.purpose-grid>*,.review-grid>*,.test-row>*{min-width:0!important}
/* never use white action buttons */
.btn.ghost{background:#e7f1f7!important;color:var(--navy)!important;border-color:#bfd3df!important}
.btn.secondary{background:#dcecf3!important;color:var(--navy)!important;border-color:#b9d3df!important}
.btn.primary{background:var(--navy)!important;color:#fff!important}
.btn.orange{background:#ef7d16!important;color:#fff!important;border-color:#ef7d16!important;text-shadow:none!important;box-shadow:0 8px 18px rgba(239,125,22,.20)!important}
.btn:hover{filter:none!important;opacity:1!important;transform:translateY(-1px)}
.btn.orange:hover{background:#d96d0d!important;color:#fff!important}
.review-card .review-head{padding-right:2px!important}
.review-card .review-head a{display:inline-flex;align-items:center;gap:6px;color:var(--blue)!important;font-weight:900!important}
.review-card .review-head a:before{content:'✎';font-size:12px}
@media(max-width:900px){
  .content>.actions{padding:10px 0 0!important}
  .step-scroll{padding-right:10px!important}
}
@media(max-width:700px){
  .content>.actions{display:flex!important;flex-direction:column-reverse!important;gap:8px!important}
  .content>.actions .btn{width:100%!important}
  .step-scroll{padding-right:6px!important}
}

/* Installer v3.2 — breathing room for the fixed action footer.
   Keep navigation visible without visually sticking to the panel bottom. */
.content>.actions{
  padding:12px 0 16px!important;
  min-height:64px!important;
  align-items:center!important;
}
@media(max-width:900px){
  .content>.actions{padding:10px 0 14px!important;min-height:60px!important}
}
@media(max-width:700px){
  .content>.actions{padding:10px 0 14px!important;min-height:auto!important}
}


/* v3.3 interaction standard */
.btn{gap:8px!important}.btn-icon{display:inline-grid;place-items:center;min-width:18px;font-weight:900;line-height:1}
.btn.ghost,.btn.secondary{background:#e9f2f7!important;color:var(--navy)!important;border-color:#c9dce7!important}
.btn:hover{filter:none!important;opacity:1!important;transform:translateY(-1px);box-shadow:0 8px 20px rgba(11,46,89,.14)}
.swal2-popup{border-radius:20px!important;box-shadow:0 28px 70px rgba(11,46,89,.22)!important}.swal2-actions{display:flex!important;flex-direction:row!important;flex-wrap:nowrap!important;justify-content:center!important;align-items:center!important;gap:10px!important;width:100%!important;margin-top:1.15rem!important}.swal2-confirm,.swal2-cancel{border-radius:11px!important;font-weight:900!important;padding:10px 12px!important;display:inline-flex!important;width:auto!important;min-width:0!important;white-space:nowrap!important;align-items:center!important;justify-content:center!important;gap:7px!important}.swal2-confirm{background:#0b2e59!important;color:#fff!important}.swal2-cancel{background:#dfeef5!important;color:#0b2e59!important}.swal2-confirm:hover,.swal2-cancel:hover{filter:none!important;transform:translateY(-1px)}.swal2-confirm::before,.swal2-cancel::before{content:none!important;display:none!important}@media(max-width:520px){.swal2-actions{flex-wrap:wrap!important;gap:10px!important}.swal2-confirm,.swal2-cancel{flex:1 1 100%!important;width:100%!important;max-width:100%!important}}
/* Installer v4.8 — one consistent SweetAlert layout + local password visibility controls. */
.swal2-container{position:fixed!important;inset:0!important;width:100vw!important;height:100dvh!important;display:flex!important;align-items:center!important;justify-content:center!important;padding:20px!important;z-index:99999!important}
.swal2-popup{position:relative!important;margin:auto!important;transform:none!important;top:auto!important;left:auto!important;right:auto!important;bottom:auto!important}
.swal2-icon.question{background:#e8f5fb!important;color:#0b2e59!important}
.password-control{position:relative;display:block;width:100%;min-width:0}
.password-control>input{padding-right:52px!important}
/* Keep exactly one password visibility control: hide browser-native reveal/clear affordances. */
.password-control>input[type="password"]::-ms-reveal,.password-control>input[type="password"]::-ms-clear{display:none!important;width:0!important;height:0!important}
.password-control>input[type="password"]::-webkit-credentials-auto-fill-button,.password-control>input[type="password"]::-webkit-contacts-auto-fill-button{visibility:hidden!important;display:none!important;pointer-events:none!important;position:absolute!important;right:0!important}
.password-visibility-toggle{position:absolute;right:6px;top:50%;transform:translateY(-50%);width:38px;height:38px;border:1px solid #c9dce7;border-radius:10px;background:#e9f2f7;color:#0b2e59;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;padding:0;z-index:2;transition:transform .15s ease,box-shadow .15s ease,background .15s ease}
.password-visibility-toggle:hover{background:#d9eaf3;color:#0b2e59;opacity:1!important;filter:none!important;box-shadow:0 6px 16px rgba(11,46,89,.12)}
.password-visibility-toggle:focus-visible{outline:3px solid rgba(12,120,170,.18);outline-offset:2px}
.password-visibility-toggle svg{width:20px;height:20px;display:block;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}


.auto-db-note{grid-column:1/-1;display:flex;align-items:flex-start;gap:12px;padding:14px 16px;border:1px solid #cfe3ee;border-radius:16px;background:#f4fbff;color:#0b3456}.auto-db-note-icon{width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;flex:0 0 34px;border-radius:10px;background:#0d7fa8;color:#fff;font-weight:800}.auto-db-note strong{display:block;margin-bottom:3px}.auto-db-note .helper{display:block;margin:0}

/* ==========================================================
   INSTALLER v4.6 — email layout + global local Select2 + clear validation
   ========================================================== */
.email-grid-2{
  display:grid!important;
  grid-template-columns:repeat(12,minmax(0,1fr))!important;
  column-gap:16px!important;
  row-gap:12px!important;
  align-items:start!important;
}
.email-grid-2>.half{grid-column:span 6!important;min-width:0!important;width:100%!important}
.email-grid-2>.full{grid-column:1/-1!important;min-width:0!important;width:100%!important}
.email-grid-2>.field.full input{width:100%!important}
.field-label{display:block;font-size:13px!important;font-weight:700!important;line-height:1.3!important;color:var(--navy)!important}
.sent-items-check{display:flex!important;align-items:center!important;gap:10px!important;width:100%!important;min-height:54px;padding:12px 14px;border:1px solid var(--line);border-radius:14px;background:var(--soft);color:var(--ink);cursor:pointer}
.sent-items-check input{flex:0 0 auto;width:18px;height:18px;margin:0!important}
.sent-items-check>span{display:flex;align-items:center;gap:10px;min-width:0;flex-wrap:wrap}
.sent-items-check strong{font-size:13px;color:var(--navy);line-height:1.25}
.sent-items-check small{font-size:11px;color:var(--muted);font-weight:500;line-height:1.3}
.select2-container{width:100%!important;min-width:0!important}
.select2-container .select2-selection{min-height:44px!important;border:1px solid #cbd8e4!important;border-radius:10px!important;background:#fff!important;display:flex!important;align-items:center!important}
.select2-container .select2-selection__rendered{padding-left:11px!important;padding-right:36px!important;line-height:42px!important;color:var(--ink)!important;font-weight:700!important}
.select2-container .select2-selection__arrow{height:42px!important;right:8px!important}
.select2-container--focus .select2-selection,
.select2-container--open .select2-selection{border-color:#2f96bd!important;box-shadow:0 0 0 3px rgba(12,120,170,.1)!important}
.field.is-invalid input,.field.is-invalid select,
.field.is-invalid .select2-selection{border-color:#d92f24!important;box-shadow:0 0 0 3px rgba(217,47,36,.10)!important}
@media(max-width:700px){
  .email-grid-2>.half,.email-grid-2>.full{grid-column:1/-1!important}
}

/* v4.5: navigation actions must never look white */
.btn.ghost{background:#195578!important;color:#fff!important;border-color:#195578!important;box-shadow:0 7px 16px rgba(25,85,120,.16)!important}
.btn.ghost:hover,.btn.ghost:focus{background:#123f5c!important;color:#fff!important;border-color:#123f5c!important;opacity:1!important;filter:none!important}

/* INSTALLER TYPOGRAPHY v5.10 */
html,body{font-synthesis:none}
.content,.aside,.installer-topbar{letter-spacing:0}
.content h2,.installer-brand strong,.aside-progress strong,.intro strong,.panel-head h3,.box h3,.review-card h3{font-weight:700}
.field input,.field select,.select2-container .select2-selection__rendered{font-weight:600!important}
.helper,.muted,.intro span,.choice span,.purpose small,.installer-version{font-weight:400}
.btn,.counter,.aside-step-num,.badge,.select-all-purpose{font-weight:700!important}
</style>
</head>
<body>
<div class="page">
<header class="installer-topbar">
  <div class="installer-brand">
    <img src="../assets/brand/es-multiservicios-official.png" alt="ES MULTISERVICIOS">
    <div><span>ASISTENTE DE INSTALACIÓN</span><strong>ES MULTISERVICIOS</strong><small>Configuración guiada, clara y segura</small></div>
  </div>
  <div class="install-mode-pill"><?= $reinstallMode ? 'Reinstalación controlada' : 'Instalación nueva' ?></div>
</header>
<div class="wizard">
<aside class="aside">
  <div class="aside-progress"><strong>Paso <?= $currentStep ?> de 4</strong><span>Avance de instalación</span><div class="aside-progress-bar"><i style="width:<?= $currentStep * 25 ?>%"></i></div></div>
  <nav class="aside-steps" aria-label="Progreso de instalación">
    <?php foreach ($stepNames as $n => $label): ?>
      <div class="aside-step <?= $n === $currentStep ? 'active' : ($n < $currentStep ? 'done' : '') ?>" <?= $n === $currentStep ? 'aria-current="step"' : '' ?>>
        <div class="aside-step-num"><?= $n < $currentStep ? '✓' : $n ?></div>
        <div><strong><?= ih($label) ?></strong><small><?= ['Conexión MySQL','Cuenta principal','SMTP o Graph','Revisión final'][$n-1] ?></small></div>
      </div>
    <?php endforeach; ?>
  </nav>
  <div class="installer-version">Instalador <?= ih(INSTALLER_VERSION) ?> · El bloqueo se crea únicamente al terminar correctamente.</div>
</aside>
<main class="content">
  <div class="top"><div><div class="eyebrow"><?= $currentStep === 1 ? 'CONEXIÓN SEGURA' : 'CONFIGURACIÓN SEGURA' ?></div><h2><?= ih($stepNames[$currentStep]) ?></h2><p class="muted"><?= $currentStep === 1 ? 'Conecta MySQL o MariaDB. El asistente instalará el esquema completo del CMS.' : 'Completa este paso para continuar con la instalación.' ?></p></div><div class="counter">Paso <?= $currentStep ?> de 4</div></div>
  <div class="progress" style="--step:<?= $currentStep ?>"><span></span></div>
  <div class="step-scroll step-<?= $currentStep ?>">

  <?php if ($error !== ''): ?><div class="install-flash" data-install-flash="<?= ih($error) ?>" data-install-flash-type="error" hidden></div><?php endif; ?><?php if ($installNotice): ?><div class="install-flash" data-install-flash="<?= ih((string)($installNotice['message'] ?? '')) ?>" data-install-flash-type="<?= ih((string)($installNotice['type'] ?? 'info')) ?>" hidden></div><?php endif; ?>
  <?php if ($reinstallMode): ?><div class="badge">↻ REINSTALACIÓN LIMPIA</div><?php endif; ?>

  <?php if ($currentStep === 1): ?>
    <div class="intro"><div class="intro-icon">1</div><div><strong>Conecta la base de datos</strong><span><?= $reinstallMode ? 'Los datos existentes fueron precargados. Si dejas la contraseña vacía, se reutilizará la guardada en la configuración anterior.' : 'El asistente validará la conexión antes de permitirte continuar.' ?></span></div></div>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= ih((string)$_SESSION['install_csrf']) ?>"><input type="hidden" name="action" value="database">
      <div class="grid">
        <label class="field"><span class="field-label">Servidor MySQL</span><input name="host" required value="<?= ih((string)$dbDefaults['host']) ?>"></label>
        <label class="field"><span class="field-label">Puerto</span><input type="number" min="1" max="65535" name="port" required value="<?= (int)$dbDefaults['port'] ?>"></label>
        <label class="field"><span class="field-label">Usuario</span><input name="username" required value="<?= ih((string)$dbDefaults['username']) ?>"></label>
        <label class="field"><span class="field-label">Prefijo de hosting <span class="optional-tag">Opcional</span></span><input id="dbPrefix" name="db_prefix" value="<?= ih((string)($dbDefaults['db_prefix'] ?? '')) ?>" placeholder="ej. cuenta_" autocomplete="off"><span class="helper">Úsalo solo si tu hosting antepone un prefijo al nombre de la base.</span></label>
        <label class="field"><span class="field-label">Base de datos</span><input id="dbName" name="db_name" required value="<?= ih((string)($dbDefaults['db_name'] ?? $dbDefaults['dbname'] ?? '')) ?>" placeholder="Sugerencia: <?= ih($dbSuggestion) ?>" autocomplete="off"><span class="helper">Puedes usar la sugerencia mostrada o escribir el nombre que prefieras.</span></label>
        <div class="db-final-preview" aria-live="polite"><span>Nombre final de la base de datos</span><strong id="dbFinalName">—</strong><small>Se combina automáticamente: prefijo + base de datos. Si no usas prefijo, queda solo el nombre indicado.</small></div>
        <label class="field full"><span class="field-label">Contraseña MySQL</span><input type="password" name="password" autocomplete="new-password" placeholder="<?= $reinstallMode ? 'Déjala vacía para reutilizar la contraseña almacenada' : 'Contraseña de la base de datos' ?>"><span class="helper"><?= $reinstallMode ? 'Por seguridad nunca mostramos la contraseña existente.' : 'Se guardará únicamente dentro de config/config.php, protegido por el servidor.' ?></span></label>
        <div class="auto-db-note full"><span class="auto-db-note-icon" aria-hidden="true">⚙</span><div><strong>Creación automática de la base de datos</strong><span class="helper">No necesitas activar nada. Si la base ya existe, el asistente la usa. Si no existe, intentará crearla automáticamente al finalizar.</span></div></div>
      </div>
      <div class="site-url site-url-premium"><span>URL DEL SITIO DETECTADA AUTOMÁTICAMENTE</span><strong><?= ih($detectedUrl) ?></strong><small>No necesitas escribirla. El instalador la toma directamente del servidor y la guardará en la configuración.</small></div>
      <?php if ($reinstallMode): ?><div class="danger-note"><strong>No se usará DROP DATABASE.</strong> Al confirmar la reinstalación solo se eliminarán y recrearán las tablas identificadas en el esquema propio de este proyecto, con FOREIGN_KEY_CHECKS controlado.</div><?php endif; ?>
      <div class="actions"><span></span><button class="btn primary" type="submit"><span class="btn-icon" aria-hidden="true">→</span><span>Siguiente</span></button></div>
    </form>

  <?php elseif ($currentStep === 2): ?>
    <div class="intro"><div class="intro-icon">2</div><div><strong>Crea el administrador principal</strong><span>Esta cuenta será Owner y tendrá control completo del CMS después de finalizar la instalación.</span></div></div>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= ih((string)$_SESSION['install_csrf']) ?>"><input type="hidden" name="action" value="admin">
      <div class="grid admin-grid">
        <label class="field">Nombre completo<input name="full_name" autocomplete="name" value="<?= ih((string)($adminDefaults['full_name'] ?? '')) ?>"></label>
        <label class="field">Correo<input type="email" name="admin_email" autocomplete="email" value="<?= ih((string)($adminDefaults['email'] ?? '')) ?>"></label>
        <label class="field">Usuario<input name="admin_username" required minlength="4" autocomplete="username" value="<?= ih((string)($adminDefaults['username'] ?? '')) ?>"></label>
        <label class="field">Contraseña<input type="password" name="admin_password" <?= !empty($_SESSION['install_admin']['password_hash']) ? '' : 'required' ?> minlength="10" autocomplete="new-password" value="<?= ih($adminPasswordValue) ?>"><span class="helper"><?= !empty($_SESSION['install_admin']['password_hash']) ? 'Déjala vacía para conservar la contraseña ya definida en este asistente.' : 'Mínimo 10 caracteres.' ?></span></label>
        <label class="field">Repetir contraseña<input type="password" name="admin_password2" <?= !empty($_SESSION['install_admin']['password_hash']) ? '' : 'required' ?> minlength="10" autocomplete="new-password" value="<?= ih($adminPassword2Value) ?>"></label>
      </div>
      <div class="safe"><strong>La contraseña no se guarda en texto plano.</strong> El asistente conserva únicamente su hash para crear la cuenta durante la confirmación final.</div>
      <div class="step-support" aria-label="Resumen de seguridad del administrador">
        <div class="step-support-item"><div class="step-support-icon">O</div><div><strong>Rol Owner</strong><span>La cuenta inicial tendrá control completo del CMS.</span></div></div>
        <div class="step-support-item"><div class="step-support-icon">@</div><div><strong>Acceso por usuario o correo</strong><span>Podrás iniciar sesión con cualquiera de los dos datos.</span></div></div>
        <div class="step-support-item"><div class="step-support-icon">#</div><div><strong>Credencial protegida</strong><span>La contraseña se almacena únicamente como hash seguro.</span></div></div>
      </div>
      <div class="actions"><a class="btn ghost" href="?step=database" data-wizard-back><span class="btn-icon" aria-hidden="true">←</span><span>Atrás</span></a><button class="btn primary" type="submit"><span class="btn-icon" aria-hidden="true">→</span><span>Siguiente</span></button></div>
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
        <div class="grid" id="emailSenderRow"><label class="field full">Correo remitente SMTP<input type="email" name="sender_email" value="<?= ih((string)(($emailDefaults['mode'] ?? '') === 'SMTP' ? ($emailDefaults['correo'] ?? '') : '')) ?>" placeholder="notificaciones@tudominio.com" data-email-required><span class="helper">Se utiliza únicamente cuando eliges SMTP.</span></label></div>
        <section class="email-panel" id="smtpPanel"><div class="panel-head"><div class="panel-icon">S</div><div><h3>Configuración SMTP</h3><p class="muted">Solo se muestran los campos de SMTP.</p></div></div><div class="email-grid-2">
          <label class="field half">Servidor SMTP<input name="smtp_server" value="<?= ih((string)($emailDefaults['server'] ?? '')) ?>" placeholder="smtp.tudominio.com" data-smtp-required></label>
          <label class="field half">Contraseña SMTP<input type="password" name="smtp_password" autocomplete="new-password" data-smtp-required data-has-stored="<?= ($emailDefaults['mode'] ?? '') === 'SMTP' && !empty($emailDefaults['password_plain']) ? '1' : '0' ?>"><span class="helper"><?= ($emailDefaults['mode'] ?? '') === 'SMTP' ? 'Déjala vacía para conservar la contraseña capturada anteriormente en este asistente.' : 'Se cifra antes de almacenarse.' ?></span></label>
          <label class="field half"><span class="field-label">Puerto</span><input type="number" min="1" max="65535" name="smtp_port" value="<?= (int)($emailDefaults['port'] ?? 587) ?>" data-smtp-required></label>
          <label class="field half">Seguridad<select name="smtp_secure" data-smtp-required><option value="tls" <?= ($emailDefaults['smtp_secure'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS / STARTTLS</option><option value="ssl" <?= ($emailDefaults['smtp_secure'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option></select></label>
        </div></section>
        <section class="email-panel" id="graphPanel" hidden><div class="panel-head"><div class="panel-icon">G</div><div><h3>Microsoft Graph</h3><p class="muted">Solo se muestran las credenciales requeridas para Graph.</p></div></div><div class="email-grid-2">
          <label class="field half">Tenant ID<input name="tenant_id" value="<?= ih((string)($emailDefaults['tenant_id'] ?? '')) ?>" data-graph-required></label>
          <label class="field half">Client ID<input name="client_id" value="<?= ih((string)($emailDefaults['client_id'] ?? '')) ?>" data-graph-required></label>
          <label class="field full"><span class="field-label">Client Secret</span><input type="password" name="client_secret" autocomplete="new-password" data-graph-required data-has-stored="<?= ($emailDefaults['mode'] ?? '') === 'GRAPH' && !empty($emailDefaults['client_secret_plain']) ? '1' : '0' ?>"><span class="helper"><?= ($emailDefaults['mode'] ?? '') === 'GRAPH' ? 'Déjalo vacío para conservar el secreto capturado anteriormente en este asistente.' : 'Se cifra antes de almacenarse.' ?></span></label>
          <label class="field full"><span class="field-label">Buzón Microsoft 365 / remitente</span><input type="email" name="graph_user" value="<?= ih((string)($emailDefaults['graph_user'] ?? '')) ?>" placeholder="correo@tudominio.com" data-graph-required><span class="helper">Este buzón se usa para enviar con Microsoft Graph y será el destino interno por defecto si no indicas otro.</span></label>
          <label class="field full"><span class="field-label">Destino interno opcional</span><input type="email" name="internal_destination" value="<?= ih((string)($emailDefaults['destinatario'] ?? '')) ?>" placeholder="Opcional: si queda vacío se usará el buzón Microsoft 365"><span class="helper">Úsalo solo si quieres que las notificaciones internas lleguen a un correo diferente del buzón/remitente.</span></label>
          <label class="sent-items-check full"><input type="checkbox" name="save_to_sent_items" value="1" <?= !isset($emailDefaults['save_to_sent_items']) || !empty($emailDefaults['save_to_sent_items']) ? 'checked' : '' ?>><span><strong>Guardar una copia en Elementos enviados del buzón</strong><small>Recomendado para trazabilidad.</small></span></label>
        </div></section>
        <section class="defer-help" id="deferHelp" hidden>
          <div class="defer-help-icon">i</div>
          <div><strong>Puedes continuar sin configurar correo ahora</strong><span>Al finalizar, podrás configurar SMTP o Microsoft Graph desde Administración → Correo sin repetir la instalación.</span></div>
        </section>
        <section class="box purpose-box" id="emailPurposeBox"><div class="purpose-heading"><div><h3>Usar esta conexión para</h3><p class="muted">Puedes separar estas configuraciones más adelante desde Administración → Correo.</p></div><label class="select-all-purpose"><input type="checkbox" id="selectAllPurposes"><span>Seleccionar todo</span></label></div><div class="purpose-grid">
          <?php $selectedPurposes = $emailDefaults['purposes'] ?? [1,2,3,4]; foreach ([1=>'Website Alerts',2=>'Admin Security',3=>'Estimate Requests',4=>'Auto Replies'] as $id=>$name): ?><label class="purpose"><input type="checkbox" name="purposes[]" value="<?= $id ?>" <?= in_array($id,$selectedPurposes,true) ? 'checked' : '' ?>><span><strong><?= ih($name) ?></strong><small>Configuración de envío para este propósito.</small></span></label><?php endforeach; ?>
        </div></section>
        <section class="box" id="emailTestBox"><div class="panel-head"><div class="panel-icon">✓</div><div><h3>Probar configuración</h3><p class="muted">Envía un correo de prueba con la plantilla corporativa antes de guardar. Los valores actuales todavía no se almacenan.</p></div></div><div class="test-row"><label class="field"><span class="field-label">Correo destino de prueba (opcional)</span><input type="email" id="testTo" name="test_to" placeholder="Vacío = usar destino interno o buzón/remitente"><span class="helper">Si lo dejas vacío, se usará automáticamente el destino interno configurado; si tampoco existe, el buzón/remitente.</span></label><div class="test-action-field"><span class="field-label-spacer" aria-hidden="true">Acción</span><button class="btn secondary" type="button" id="testBtn" data-action="send"><span class="btn-label">Enviar prueba</span></button><span class="helper-spacer" aria-hidden="true">Alineación</span></div></div><div class="test-result" id="testResult" role="status" aria-live="polite"></div></section>
      </div>
      <div class="actions"><a class="btn ghost" href="?step=admin" data-wizard-back><span class="btn-icon" aria-hidden="true">←</span><span>Atrás</span></a><button class="btn primary" type="submit" id="emailNext"><span class="btn-icon" aria-hidden="true">✓</span><span>Guardar y continuar</span></button></div>
    </form>

  <?php else: ?>
    <?php $dbReview = $_SESSION['install_db']; $adminReview = $_SESSION['install_admin']; $emailReview = $_SESSION['install_email']; ?>
    <div class="intro"><div class="intro-icon">4</div><div><strong>Revisa antes de activar</strong><span>install.lock todavía NO existe. Se creará únicamente después de completar correctamente base de datos, administrador, correo y configuración.</span></div></div>
    <div class="review">
      <section class="review-card"><div class="review-head"><h3>Base de datos</h3><a href="?step=database">Editar</a></div><div class="review-grid"><div class="review-item"><span>Servidor</span><strong><?= ih($dbReview['host']) ?>:<?= (int)$dbReview['port'] ?></strong></div><div class="review-item"><span>Base final</span><strong><?= ih($dbReview['dbname']) ?></strong></div><div class="review-item"><span>Usuario</span><strong><?= ih($dbReview['username']) ?></strong></div><div class="review-item"><span>Modo</span><strong><?= $reinstallMode ? 'Reinstalación limpia' : 'Instalación inicial' ?></strong></div></div></section>
      <section class="review-card"><div class="review-head"><h3>Administrador</h3><a href="?step=admin">Editar</a></div><div class="review-grid"><div class="review-item"><span>Nombre</span><strong><?= ih((string)$adminReview['full_name']) ?: '—' ?></strong></div><div class="review-item"><span>Usuario</span><strong><?= ih((string)$adminReview['username']) ?></strong></div><div class="review-item"><span>Correo</span><strong><?= ih((string)$adminReview['email']) ?: '—' ?></strong></div><div class="review-item"><span>Rol</span><strong>Owner</strong></div></div></section>
      <section class="review-card"><div class="review-head"><h3>Correo</h3><a href="?step=email">Editar</a></div><div class="review-grid"><div class="review-item"><span>Método</span><strong><?= ($emailReview['mode'] ?? 'LATER') === 'LATER' ? 'Configurar después' : ih((string)$emailReview['mode']) ?></strong></div><div class="review-item"><span>Remitente</span><strong><?= ($emailReview['mode'] ?? 'LATER') === 'LATER' ? 'Pendiente' : ih((string)$emailReview['correo']) ?></strong></div></div></section>
      <section class="review-card"><div class="review-head"><h3>Sitio</h3></div><div class="review-grid"><div class="review-item full"><span>URL detectada</span><strong><?= ih((string)($_SESSION['install_site_url'] ?? $detectedUrl)) ?></strong></div></div></section>
    </div>
    <?php if ($reinstallMode): ?><div class="danger-note"><strong>Reinstalación limpia:</strong> al finalizar se desactivarán temporalmente las claves foráneas, se eliminarán únicamente las tablas propias detectadas en el esquema del proyecto y se recrearán. No se ejecuta DROP DATABASE y no se tocan tablas ajenas.</div><?php endif; ?>
    <form method="post"><input type="hidden" name="csrf" value="<?= ih((string)$_SESSION['install_csrf']) ?>"><input type="hidden" name="action" value="finalize"><div class="actions"><a class="btn ghost" href="?step=email" data-wizard-back><span class="btn-icon" aria-hidden="true">←</span><span>Atrás</span></a><button class="btn orange" type="submit" data-finalize-install><span class="btn-icon" aria-hidden="true">✓</span><span>Finalizar instalación</span></button></div></form>
  <?php endif; ?>
  </div>
</main>
</div>
</div>
<script src="../assets/vendor/jquery/jquery.min.js"></script>
<script src="../assets/vendor/select2/select2.local.js"></script>
<script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
<script src="../assets/vendor/show-notify/showNotify.js"></script>
<script src="../assets/action-icons.js"></script>
<script>
(() => {
  const initSelect2 = (root = document) => {
    if (!window.jQuery || !window.jQuery.fn?.select2) return;
    root.querySelectorAll('select:not([data-native-select])').forEach(el => {
      const $el = window.jQuery(el);
      if (!$el.hasClass('select2-hidden-accessible')) {
        $el.select2({width:'100%', minimumResultsForSearch: Infinity});
      }
    });
  };
  window.ESInitLocalSelect2 = initSelect2;
  initSelect2();

  document.querySelectorAll('[data-install-flash]').forEach(el => {
    const type = (el.dataset.installFlashType || 'info').toLowerCase();
    window.showNotify?.(el.dataset.installFlash || '', type === 'danger' ? 'error' : type);
  });
})();
</script>
<script>
(() => {
  const NS = 'http://www.w3.org/2000/svg';
  const eyePaths = {
    show:['M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z','M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6'],
    hide:['M3 3l18 18','M10.6 10.6a2 2 0 0 0 2.8 2.8','M9.9 4.2A11.7 11.7 0 0 1 12 4c6.5 0 10 8 10 8a15.5 15.5 0 0 1-3 4.1','M6.6 6.6C3.6 8.4 2 12 2 12s3.5 8 10 8a10.8 10.8 0 0 0 4.1-.8']
  };
  const makeEye = mode => {
    const svg=document.createElementNS(NS,'svg');
    svg.setAttribute('viewBox','0 0 24 24');
    svg.setAttribute('aria-hidden','true');
    eyePaths[mode].forEach(d=>{const path=document.createElementNS(NS,'path');path.setAttribute('d',d);svg.appendChild(path)});
    return svg;
  };
  const apply = input => {
    if (!(input instanceof HTMLInputElement) || input.dataset.visibilityReady === '1') return;
    const wrap=document.createElement('span');
    wrap.className='password-control';
    input.parentNode.insertBefore(wrap,input);
    wrap.appendChild(input);
    const btn=document.createElement('button');
    btn.type='button';
    btn.className='password-visibility-toggle';
    btn.setAttribute('data-no-action-icon','');
    btn.tabIndex = -1;
    btn.setAttribute('aria-label','Mostrar contraseña');
    btn.title='Mostrar contraseña';
    btn.appendChild(makeEye('show'));
    btn.addEventListener('click',()=>{
      const showing=input.type==='text';
      input.type=showing?'password':'text';
      btn.replaceChildren(makeEye(showing?'show':'hide'));
      const label=showing?'Mostrar contraseña':'Ocultar contraseña';
      btn.setAttribute('aria-label',label);
      btn.title=label;
      input.focus({preventScroll:true});
      try{input.setSelectionRange(input.value.length,input.value.length)}catch(_){ }
    });
    wrap.appendChild(btn);
    input.dataset.visibilityReady='1';
  };
  const init = root => root.querySelectorAll?.('input[type="password"]').forEach(apply);
  init(document);
  new MutationObserver(records=>records.forEach(r=>r.addedNodes.forEach(n=>{if(n.nodeType===1){if(n.matches?.('input[type="password"]'))apply(n);init(n)}}))).observe(document.documentElement,{childList:true,subtree:true});
})();
</script>
<?php if ($currentStep === 1): ?>
<script>
(() => {
  const prefix = document.getElementById('dbPrefix');
  const name = document.getElementById('dbName');
  const finalName = document.getElementById('dbFinalName');
  const normalizePrefix = value => {
    const clean = (value || '').trim().replace(/^_+|_+$/g, '');
    return clean ? clean + '_' : '';
  };
  const sync = () => {
    const p = normalizePrefix(prefix?.value || '');
    const n = (name?.value || '').trim();
    let combined = n;
    if (p && n && !n.toLowerCase().startsWith(p.toLowerCase())) combined = p + n;
    finalName.textContent = combined || '—';
  };
  prefix?.addEventListener('input', sync);
  name?.addEventListener('input', sync);
  prefix?.addEventListener('blur', () => { if (prefix.value.trim()) prefix.value = normalizePrefix(prefix.value); sync(); });
  sync();
})();
</script>
<?php endif; ?>
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
  const defer = document.getElementById('deferHelp');
  const senderRow = document.getElementById('emailSenderRow');
  const purposeBox = document.getElementById('emailPurposeBox');
  const testBox = document.getElementById('emailTestBox');
  const selected = () => form.querySelector('input[name="email_choice"]:checked')?.value || 'LATER';
  const sync = () => {
    const mode = selected();
    choices.forEach(c => c.classList.toggle('active', c.dataset.choice === mode));
    configArea.hidden = false;
    if (defer) defer.hidden = mode !== 'LATER';
    if (senderRow) senderRow.hidden = mode !== 'SMTP';
    smtp.hidden = mode !== 'SMTP';
    graph.hidden = mode !== 'GRAPH';
    if (purposeBox) purposeBox.hidden = mode === 'LATER';
    if (testBox) testBox.hidden = mode === 'LATER';
    form.querySelectorAll('[data-email-required]').forEach(el => el.required = mode === 'SMTP');
    form.querySelectorAll('[data-smtp-required]').forEach(el => el.required = mode === 'SMTP' && el.dataset.hasStored !== '1');
    form.querySelectorAll('[data-graph-required]').forEach(el => el.required = mode === 'GRAPH' && el.dataset.hasStored !== '1');
    result.textContent = ''; result.className = 'test-result';
    window.ESInitLocalSelect2?.(form);
  };
  choices.forEach(card => card.addEventListener('click', () => { const r = card.querySelector('input[type="radio"]'); if (r) { r.checked = true; r.dispatchEvent(new Event('change',{bubbles:true})); } }));
  form.querySelectorAll('input[name="email_choice"]').forEach(r => r.addEventListener('change', sync));
  window.ESInstallSyncEmail = sync;
  testBtn.addEventListener('click', async () => {
    if (selected() === 'LATER') return;
    sync();
    if (!form.reportValidity()) return;
    const to = document.getElementById('testTo');
    if (to.value && !to.checkValidity()) { to.reportValidity(); return; }
    const data = new FormData(form); data.set('action','test_email');
    testBtn.disabled = true; const testLabel = testBtn.querySelector('.btn-label'); if (testLabel) testLabel.textContent = 'Enviando…';
    try {
      const res = await fetch(window.location.href,{method:'POST',body:data,headers:{'X-Requested-With':'XMLHttpRequest'}});
      const payload = await res.json();
      const msg = payload.message || (payload.success ? 'Prueba enviada correctamente.' : 'La prueba falló.');
      result.textContent = ''; result.className = 'test-result';
      window.showNotify?.(msg, payload.success ? 'success' : 'error');
    } catch(e) {
      result.textContent = ''; result.className = 'test-result';
      window.showNotify?.('No se pudo completar la prueba. Revisa la conexión e inténtalo nuevamente.','error');
    } finally { testBtn.disabled = false; const testLabel = testBtn.querySelector('.btn-label'); if (testLabel) testLabel.textContent = 'Enviar prueba'; window.ESActionIcons?.decorate(testBtn); }
  });
  sync();
})();
</script>
<?php endif; ?>




<script>
(() => {
  const forms = [...document.querySelectorAll('main.content form[method="post"]')].filter(f => {
    const action = f.querySelector('input[name="action"]')?.value || '';
    return !['finalize','test_email','reset_wizard'].includes(action);
  });

  const friendlyName = el => {
    const label = el.closest('label')?.querySelector('.field-label')?.textContent?.trim()
      || el.closest('label')?.childNodes?.[0]?.textContent?.trim()
      || el.getAttribute('aria-label')
      || el.name
      || 'este campo';
    return label.replace(/\s+/g,' ').trim();
  };

  const messageFor = el => {
    const name = friendlyName(el);
    if (el.validity.valueMissing) return `Completa ${name}.`;
    if (el.validity.typeMismatch) return `Ingresa un valor válido en ${name}.`;
    if (el.validity.tooShort) return `${name} debe tener al menos ${el.minLength} caracteres.`;
    if (el.validity.rangeUnderflow || el.validity.rangeOverflow) return `Revisa el valor de ${name}.`;
    if (el.validity.patternMismatch) return `Revisa el formato de ${name}.`;
    return `Revisa ${name} antes de continuar.`;
  };

  const mark = (el, invalid) => {
    const field = el.closest('.field');
    field?.classList.toggle('is-invalid', invalid);
  };

  forms.forEach(form => {
    form.setAttribute('novalidate','novalidate');
    form.querySelectorAll('input,select,textarea').forEach(el => {
      const clear = () => mark(el, false);
      el.addEventListener('input', clear);
      el.addEventListener('change', clear);
    });
    form.addEventListener('submit', event => {
      const controls = [...form.querySelectorAll('input,select,textarea')].filter(el => !el.disabled && el.type !== 'hidden');
      const firstInvalid = controls.find(el => !el.checkValidity());
      if (!firstInvalid) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      mark(firstInvalid, true);
      window.showNotify?.(messageFor(firstInvalid), 'warning');
      firstInvalid.focus({preventScroll:true});
      firstInvalid.scrollIntoView({behavior:'smooth',block:'center'});
      if (firstInvalid.tagName === 'SELECT' && window.jQuery?.fn?.select2) {
        window.jQuery(firstInvalid).select2('open');
      }
    }, true);
  });
})();
</script>

<script>
(() => {
  const storageKey = 'esm_install_wizard_draft_v46';
  const form = document.querySelector('main.content form[method="post"]');
  const currentStep = new URLSearchParams(location.search).get('step') || 'database';
  if (!form) return;
  const action = form.querySelector('input[name="action"]')?.value || currentStep;
  if (action === 'finalize' || action === 'test_email') return;

  const serverHasWizardData = <?= $wizardHasServerData ? 'true' : 'false' ?>;
  let state = {};
  try { state = JSON.parse(sessionStorage.getItem(storageKey) || '{}') || {}; } catch (_) { state = {}; }

  const shouldTrack = el => el.name && !['csrf','action'].includes(el.name) && !el.disabled && el.type !== 'file';
  const hasMeaningfulData = data => Object.entries(data || {}).some(([key,val]) => {
    if (key === 'host' && val === 'localhost') return false;
    if (key === 'port' && String(val) === '3306') return false;
    if (key === 'email_choice' && val === 'LATER') return false;
    if (key === 'smtp_port' && String(val) === '587') return false;
    if (key === 'smtp_secure' && String(val).toLowerCase() === 'tls') return false;
    if (key === 'save_to_sent_items' && val === true) return false;
    if (Array.isArray(val)) return val.length > 0;
    if (typeof val === 'boolean') return val;
    return String(val ?? '').trim() !== '';
  });
  const anyClientDraft = () => Object.values(state || {}).some(hasMeaningfulData);

  const restore = () => {
    const saved = state[action];
    if (!saved) return false;
    [...form.elements].forEach(el => {
      if (!shouldTrack(el) || !(el.name in saved)) return;
      const val = saved[el.name];
      if (el.type === 'checkbox' || el.type === 'radio') {
        if (Array.isArray(val)) el.checked = val.includes(el.value);
        else if (el.type === 'radio') el.checked = val === el.value;
        else el.checked = Boolean(val);
      } else {
        el.value = val ?? '';
      }
    });
    return true;
  };

  const refreshDynamicUI = () => {
    window.ESInstallSyncEmail?.();
    form.dispatchEvent(new Event('change',{bubbles:true}));
    form.dispatchEvent(new Event('input',{bubbles:true}));
  };

  const capture = () => {
    const data = {};
    [...form.elements].forEach(el => {
      if (!shouldTrack(el)) return;
      if (el.type === 'checkbox') {
        if (el.name.endsWith('[]')) {
          if (!Array.isArray(data[el.name])) data[el.name] = [];
          if (el.checked) data[el.name].push(el.value);
        } else {
          data[el.name] = el.checked;
        }
      } else if (el.type === 'radio') {
        if (el.checked) data[el.name] = el.value;
      } else {
        data[el.name] = el.value;
      }
    });
    state[action] = data;
    sessionStorage.setItem(storageKey, JSON.stringify(state));
    return data;
  };

  const clearEntireWizard = async () => {
    const body = new FormData();
    body.set('csrf', form.querySelector('input[name="csrf"]')?.value || '');
    body.set('action', 'reset_wizard');
    const response = await fetch(location.pathname + location.search, {
      method:'POST', body, headers:{'X-Requested-With':'XMLHttpRequest'}
    });
    if (!response.ok) throw new Error('reset failed');
    const payload = await response.json();
    if (!payload?.success) throw new Error('reset failed');
    sessionStorage.removeItem(storageKey);
  };

  const nav = performance.getEntriesByType?.('navigation')?.[0];
  const isReload = nav?.type === 'reload' || (typeof performance.navigation !== 'undefined' && performance.navigation.type === 1);

  const boot = async () => {
    if (isReload && (serverHasWizardData || anyClientDraft())) {
      const result = await Swal.fire({
        icon:'warning',
        title:'Hay datos del asistente',
        text:'Puedes conservar lo escrito o limpiar el asistente y volver a sus valores por defecto.',
        showCancelButton:true,
        confirmButtonText:'Conservar',
        cancelButtonText:'Limpiar datos',
        customClass:{confirmButton:'swal-btn-back',cancelButton:'swal-btn-trash'},
        allowOutsideClick:false,
        allowEscapeKey:true
      });
      if (result.isConfirmed) {
        if (restore()) refreshDynamicUI();
        else window.ESInstallSyncEmail?.();
      } else {
        try {
          await clearEntireWizard();
          location.replace('?step=database&fresh=1');
          return;
        } catch (_) {
          window.showNotify?.('No se pudieron limpiar los datos del asistente. Inténtalo nuevamente.','error');
        }
      }
    } else {
      if (restore()) refreshDynamicUI();
      else window.ESInstallSyncEmail?.();
    }

    form.addEventListener('input', capture);
    form.addEventListener('change', capture);
    form.addEventListener('submit', capture);
    document.querySelectorAll('[data-wizard-back]').forEach(link => link.addEventListener('click', capture));
  };

  boot();
})();
</script>

<script>
(function(){
  const all = document.getElementById('selectAllPurposes');
  const boxes = Array.from(document.querySelectorAll('input[name="purposes[]"]'));
  function syncAll(){
    if(!all || !boxes.length) return;
    all.checked = boxes.every(b => b.checked);
    all.indeterminate = !all.checked && boxes.some(b => b.checked);
  }
  if(all && boxes.length){
    all.addEventListener('change',()=>{ boxes.forEach(b=>b.checked=all.checked); syncAll(); });
    boxes.forEach(b=>b.addEventListener('change',syncAll));
    syncAll();
  }
})();
</script>


<script>
(() => {
  const finalBtn = document.querySelector('[data-finalize-install]');
  if (!finalBtn) return;
  const finalForm = finalBtn.closest('form');
  finalForm?.addEventListener('submit', async (event) => {
    if (finalForm.dataset.confirmed === '1') return;
    event.preventDefault();
    const result = await Swal.fire({
      icon:'question',
      title:'¿Finalizar la instalación?',
      text:'Se creará la base de datos si corresponde, se instalará el esquema y se activará el sistema.',
      showCancelButton:true,
      confirmButtonText:'Finalizar instalación',
      cancelButtonText:'Cancelar',
      customClass:{confirmButton:'swal-btn-check',cancelButton:'swal-btn-close'},
      allowOutsideClick:false,
      allowEscapeKey:true,
      reverseButtons:true
    });
    if (result.isConfirmed) { finalForm.dataset.confirmed='1'; finalForm.submit(); }
  });
})();
</script>
<script>
(() => {
  const stepScroll = document.querySelector('.step-scroll');
  const content = document.querySelector('main.content');
  if (!stepScroll || !content) return;
  const actions = [...stepScroll.querySelectorAll('.actions')].pop();
  if (!actions) return;
  const form = actions.closest('form');
  if (form) {
    if (!form.id) form.id = 'installerStepForm';
    actions.querySelectorAll('button[type="submit"]').forEach(btn => btn.setAttribute('form', form.id));
  }
  content.appendChild(actions);
})();
</script>

</body>
</html>
