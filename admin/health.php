<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require_permission('health.view');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$pdo = db();
$s = settings();

$checks = [];
$checks[] = ['Logo configured', !empty($s['admin_logo_path']), 'Upload the admin logo in Settings.', 'settings.php'];
$checks[] = ['Favicon configured', !empty($s['favicon_path']), 'Upload a browser tab icon.', 'settings.php'];
$checks[] = ['WhatsApp enabled', ($s['whatsapp_enabled'] ?? '0') === '1', 'Enable floating WhatsApp.', 'settings.php'];
$checks[] = ['Public email configured', filter_var($s['email'] ?? '', FILTER_VALIDATE_EMAIL) !== false, 'Add a valid public email.', 'settings.php'];
$emailOk = (int)$pdo->query('SELECT COUNT(*) FROM correo WHERE estado=1')->fetchColumn() > 0;
$checks[] = ['Email delivery configured', $emailOk, 'Configure and test SMTP or Microsoft Graph.', 'email.php'];
$areas = (int)$pdo->query('SELECT COUNT(*) FROM service_areas WHERE active=1')->fetchColumn();
$checks[] = ['Service areas published', $areas > 0, 'Add at least one service area.', 'areas.php'];
$missing = (int)$pdo->query("SELECT COUNT(*) FROM gallery WHERE active=1 AND (image_path IS NULL OR image_path='')")->fetchColumn();
$checks[] = ['Gallery uses real images', $missing === 0, $missing.' gallery item(s) still use fallback images.', 'gallery.php'];
$seoOk = strlen(trim($s['seo_title'] ?? '')) > 10 && strlen(trim($s['seo_description'] ?? '')) > 40;
$checks[] = ['SEO basics configured', $seoOk, 'Review title and meta description.', 'seo.php'];
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$checks[] = ['HTTPS detected', $https, 'Production should run over HTTPS.', '#'];

$passed = count(array_filter($checks, static fn(array $c): bool => (bool)$c[1]));
$score = (int)round($passed / max(count($checks), 1) * 100);

$phpSeries = PHP_MAJOR_VERSION . PHP_MINOR_VERSION;
$eaHint = static fn(string $package): string => 'WHM → Software → EasyApache 4 → Customize → PHP Extensions → search ea-php'.$phpSeries.'-php-'.$package.' → Review → Provision.';
$coreHint = 'WHM → Software → EasyApache 4 → Customize → PHP Versions. Select PHP 8.0 or newer for this domain, then Review → Provision.';

$requirements = [
    [
        'name' => 'PHP 8+',
        'available' => version_compare(PHP_VERSION, '8.0.0', '>='),
        'purpose' => 'Runs the application and provides the language features used by this CMS.',
        'enable' => $coreHint,
        'detail' => 'Detected: PHP '.PHP_VERSION,
    ],
    [
        'name' => 'PDO',
        'available' => extension_loaded('pdo') && class_exists('PDO'),
        'purpose' => 'Provides the secure database abstraction layer used by the CMS.',
        'enable' => $eaHint('pdo'),
        'detail' => 'Required for all database access.',
    ],
    [
        'name' => 'PDO MySQL',
        'available' => extension_loaded('pdo_mysql'),
        'purpose' => 'Connects PDO to the MySQL/MariaDB database used by the website.',
        'enable' => $eaHint('mysqlnd'),
        'detail' => 'EasyApache normally provides PDO MySQL through mysqlnd.',
    ],
    [
        'name' => 'OpenSSL',
        'available' => extension_loaded('openssl') && function_exists('openssl_encrypt'),
        'purpose' => 'Encrypts saved SMTP and Microsoft Graph secrets and supports secure connections.',
        'enable' => $eaHint('openssl'),
        'detail' => 'Used by encrypted email credentials.',
    ],
    [
        'name' => 'cURL',
        'available' => extension_loaded('curl') && function_exists('curl_init'),
        'purpose' => 'Performs HTTPS requests to Microsoft Graph and external services.',
        'enable' => $eaHint('curl'),
        'detail' => 'Required when Microsoft Graph email is used.',
    ],
    [
        'name' => 'Fileinfo',
        'available' => extension_loaded('fileinfo') && class_exists('finfo'),
        'purpose' => 'Validates the real MIME type of uploaded images and media instead of trusting the browser.',
        'enable' => $eaHint('fileinfo'),
        'detail' => 'Uploads remain blocked safely when Fileinfo is missing.',
    ],
    [
        'name' => 'ZIP / ZipArchive',
        'available' => extension_loaded('zip') && class_exists('ZipArchive'),
        'purpose' => 'Creates and restores complete CMS backup ZIP files.',
        'enable' => $eaHint('zip'),
        'detail' => 'Required by the Backups module.',
    ],
    [
        'name' => 'JSON',
        'available' => extension_loaded('json') && function_exists('json_encode') && function_exists('json_decode'),
        'purpose' => 'Encodes API payloads, activity metadata and application data.',
        'enable' => $eaHint('json'),
        'detail' => 'Bundled with modern PHP builds but verified here.',
    ],
    [
        'name' => 'Session',
        'available' => extension_loaded('session') && function_exists('session_start'),
        'purpose' => 'Maintains authenticated administrator sessions and CSRF-related state.',
        'enable' => $eaHint('session'),
        'detail' => 'Required for Admin authentication.',
    ],
    [
        'name' => 'Filter',
        'available' => extension_loaded('filter') && function_exists('filter_var'),
        'purpose' => 'Validates email addresses and other user-provided values.',
        'enable' => $eaHint('filter'),
        'detail' => 'Used by forms, users and email configuration.',
    ],
    [
        'name' => 'Hash',
        'available' => extension_loaded('hash') && function_exists('hash') && function_exists('hash_equals'),
        'purpose' => 'Creates secure hashes for sessions, password-reset tokens and integrity checks.',
        'enable' => $eaHint('hash'),
        'detail' => 'Used by authentication and security features.',
    ],
];

$missingRequirements = array_values(array_filter($requirements, static fn(array $r): bool => !$r['available']));
$requirementsPassed = count($requirements) - count($missingRequirements);

$pageTitle = 'Website Health';
$active = 'health';
require __DIR__.'/_header.php';
?>
<div class="page-heading health-page-heading">
    <div>
        <p class="eyebrow">WEBSITE HEALTH</p>
        <h1>Site readiness check</h1>
        <p class="muted">ES MULTISERVICIOS server, configuration and website readiness in one place.</p>
    </div>
    <form method="get" action="health.php" class="health-recheck-form">
        <input type="hidden" name="recheck" value="1">
        <button class="button health-recheck-button" type="submit">↻ Recheck server</button>
    </form>
</div>

<?php if ($missingRequirements): ?>
<div class="health-global-alert" role="alert">
    <div class="health-global-alert-icon" aria-hidden="true">!</div>
    <div>
        <strong>Server requirements need attention</strong>
        <p><?= h(count($missingRequirements)) ?> required PHP component<?= count($missingRequirements) === 1 ? '' : 's' ?> <?= count($missingRequirements) === 1 ? 'is' : 'are' ?> missing. Enable the item<?= count($missingRequirements) === 1 ? '' : 's' ?> below and press <b>Recheck server</b>. No browser hard refresh is required.</p>
    </div>
</div>
<?php else: ?>
<div class="health-global-ready" role="status">
    <span aria-hidden="true">✓</span>
    <div><strong>PHP server requirements are ready</strong><p>All <?= count($requirements) ?> required runtime components are currently available.</p></div>
</div>
<?php endif; ?>

<section class="panel health-requirements-panel">
    <div class="panel-head health-requirements-head">
        <div>
            <span class="eyebrow">SERVER REQUIREMENTS</span>
            <h2>PHP environment</h2>
            <p>The status is read directly from the PHP runtime serving this administrator.</p>
        </div>
        <div class="health-requirements-summary"><strong><?= $requirementsPassed ?>/<?= count($requirements) ?></strong><span>Available</span></div>
    </div>

    <div class="health-requirements-list">
        <?php foreach ($requirements as $requirement): ?>
        <article class="health-requirement <?= $requirement['available'] ? 'available' : 'missing' ?>">
            <div class="health-requirement-main">
                <div class="health-requirement-title-row">
                    <h3><?= h($requirement['name']) ?></h3>
                    <span class="health-status <?= $requirement['available'] ? 'available' : 'missing' ?>"><?= $requirement['available'] ? 'Available' : 'Missing' ?></span>
                </div>
                <p><?= h($requirement['purpose']) ?></p>
                <small><?= h($requirement['detail']) ?></small>
            </div>
            <div class="health-enable-instructions">
                <strong><?= $requirement['available'] ? 'WHM reference' : 'Enable in WHM' ?></strong>
                <p><?= h($requirement['enable']) ?></p>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</section>

<div class="health-section-heading">
    <div>
        <span class="eyebrow">WEBSITE CHECKS</span>
        <h2>Configuration readiness</h2>
    </div>
</div>
<div class="health-hero">
    <div class="health-score"><strong><?= $score ?>%</strong><span><?= $passed ?> of <?= count($checks) ?> checks passed</span></div>
    <div class="health-meter"><i style="width:<?= $score ?>%"></i></div>
</div>
<div class="health-grid">
<?php foreach ($checks as $c): ?>
    <article class="health-card <?= $c[1] ? 'ok' : 'warn' ?>">
        <span><?= $c[1] ? '✓' : '!' ?></span>
        <div><strong><?= h($c[0]) ?></strong><p><?= h($c[2]) ?></p></div>
        <?php if ($c[3] !== '#'): ?><a href="<?= h($c[3]) ?>">Fix →</a><?php endif; ?>
    </article>
<?php endforeach; ?>
</div>
<?php require __DIR__.'/_footer.php'; ?>
