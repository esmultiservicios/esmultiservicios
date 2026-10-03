<?php
declare(strict_types=1);

session_start();
require __DIR__ . '/config/bootstrap.php';

if (!installation_locked()) {
    header('Location: install/');
    exit;
}
if (!config_ready()) {
    http_response_code(500);
    exit('Installation lock exists but config/config.php is missing. Remove config/install.lock to run the installer again.');
}

$settings = settings();

/**
 * Detailed privacy-friendly analytics.
 * Stores an anonymous random visitor key and page path only; never stores IP addresses.
 */
function record_detailed_public_visit(array $settings): void
{
    if (($settings['analytics_tracking_enabled'] ?? '1') !== '1') return;
    if (!empty($_SESSION['escms_admin_id'])) return;
    if (isset($_GET['preview']) && (string)$_GET['preview'] === '1') return;
    $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua !== '' && preg_match('/bot|crawler|spider|slurp|bingpreview|facebookexternalhit|preview|monitor|uptime/i', $ua)) return;

    try {
        $timezoneName = trim((string)($settings['site_timezone'] ?? 'America/Tegucigalpa')) ?: 'America/Tegucigalpa';
        try { $timezone = new DateTimeZone($timezoneName); }
        catch (Throwable $e) { $timezone = new DateTimeZone('America/Tegucigalpa'); }
        $localNow = new DateTimeImmutable('now', $timezone);

        $cookieName = 'esms_visitor_key';
        $visitorRaw = (string)($_COOKIE[$cookieName] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/', $visitorRaw)) {
            $visitorRaw = bin2hex(random_bytes(16));
            if (!headers_sent()) {
                setcookie($cookieName, $visitorRaw, [
                    'expires' => time() + 60 * 60 * 24 * 180,
                    'path' => '/',
                    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
        }
        $visitorKey = hash('sha256', $visitorRaw);
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string)(parse_url($uri, PHP_URL_PATH) ?: '/');
        $path = mb_substr($path, 0, 500);
        ensure_site_visits_table();
        $st = db()->prepare('INSERT INTO site_visits(visitor_key,path,visited_at,visit_date) VALUES(?,?,?,?)');
        $st->execute([$visitorKey, $path, gmdate('Y-m-d H:i:s'), $localNow->format('Y-m-d')]);
    } catch (Throwable $e) {
        // Upgrade-safe: existing installations may not have the table until database-update.sql runs.
        error_log('Public analytics detail: ' . $e->getMessage());
    }
}

record_detailed_public_visit($settings);

/**
 * Lightweight first-party visit counter.
 * Counts at most once per browser per calendar day and ignores common crawlers.
 * No IP address, fingerprint or personal identifier is stored.
 */
function record_public_visit(array $settings): void
{
    if (($settings['analytics_tracking_enabled'] ?? '1') !== '1') return;
    if (!empty($_SESSION['escms_admin_id'])) return;
    if (isset($_GET['preview']) && (string)$_GET['preview'] === '1') return;

    $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua !== '' && preg_match('/bot|crawler|spider|slurp|bingpreview|facebookexternalhit|preview|monitor|uptime/i', $ua)) return;

    $siteTimezone = new DateTimeZone('America/Tegucigalpa');
    $siteNow = new DateTimeImmutable('now', $siteTimezone);
    $today = $siteNow->format('Y-m-d');
    $cookieName = 'esms_visit_day';
    if ((string)($_COOKIE[$cookieName] ?? '') === $today) return;

    try {
        $pdo = db();
        $pdo->beginTransaction();
        $keys = ['analytics_total_visits','analytics_today_date','analytics_today_visits'];
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $st = $pdo->prepare("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ($placeholders) FOR UPDATE");
        $st->execute($keys);
        $current = [];
        foreach ($st->fetchAll() as $row) $current[(string)$row['setting_key']] = (string)$row['setting_value'];

        $total = max(0, (int)($current['analytics_total_visits'] ?? 0)) + 1;
        $todayVisits = (($current['analytics_today_date'] ?? '') === $today)
            ? max(0, (int)($current['analytics_today_visits'] ?? 0)) + 1
            : 1;

        $up = $pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
        foreach ([
            'analytics_total_visits' => (string)$total,
            'analytics_today_date' => $today,
            'analytics_today_visits' => (string)$todayVisits,
            // Store the instant in UTC; convert only when it is displayed.
            'analytics_last_visit_at' => gmdate('Y-m-d H:i:s'),
        ] as $key => $value) $up->execute([$key, $value]);
        $pdo->commit();

        if (!headers_sent()) {
            setcookie($cookieName, $today, [
                'expires' => $siteNow->modify('tomorrow')->setTime(0, 0)->getTimestamp(),
                'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        error_log('ES MULTISERVICIOS public analytics: ' . $e->getMessage());
        // Analytics must never interrupt the public website.
    }
}

record_public_visit($settings);
$requestedLanguage = (string) ($_GET['lang'] ?? '');

if (in_array($requestedLanguage, ['es', 'en'], true)) {
    setcookie('esms_lang', $requestedLanguage, [
        'expires' => time() + 31536000,
        'path' => '/',
        'samesite' => 'Lax',
    ]);
    $lang = $requestedLanguage;
} else {
    $languageCookie = (string) ($_COOKIE['esms_lang'] ?? '');
    $browserLanguage = strtolower((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    $lang = in_array($languageCookie, ['es', 'en'], true)
        ? $languageCookie
        : (str_starts_with($browserLanguage, 'en') ? 'en' : 'es');
}

$copy = landing_content($lang);
$products = marketing_products();
$projects = marketing_projects();
$testimonials = marketing_testimonials();


try {
    $serviceAreasPublic = db()->query(
        'SELECT area_name FROM service_areas WHERE active=1 ORDER BY sort_order,id'
    )->fetchAll();
} catch (Throwable $ignored) {
    $serviceAreasPublic = [];
}

$serviceMapEnabled = (string)($settings['service_map_enabled'] ?? '1') === '1';
$serviceMapQuery = trim((string)($settings['service_map_query'] ?? ''));
$legacyServiceMapLabel = trim((string)($settings['service_map_label'] ?? ''));
$serviceMapLabelEs = trim((string)($settings['service_map_label_es'] ?? ($legacyServiceMapLabel !== '' ? $legacyServiceMapLabel : 'Área de cobertura')));
$serviceMapLabelEn = trim((string)($settings['service_map_label_en'] ?? 'Service coverage'));
$serviceMapLabel = $lang === 'es' ? $serviceMapLabelEs : $serviceMapLabelEn;
if ($serviceMapLabel === '') {
    $serviceMapLabel = $lang === 'es' ? 'Área de cobertura' : 'Service coverage';
}
if ($serviceMapQuery === '' && !empty($serviceAreasPublic)) {
    $serviceMapQuery = trim((string)($serviceAreasPublic[0]['area_name'] ?? ''));
}
$showPublicServiceMap = $serviceMapEnabled && $serviceMapQuery !== '';

try {
    $videos = db()->query(
        'SELECT * FROM videos WHERE active=1 ORDER BY sort_order,id'
    )->fetchAll();
} catch (Throwable $ignored) {
    $videos = [];
}

try {
    $aboutArtworks = db()->query(
        'SELECT * FROM about_artworks WHERE active=1 ORDER BY sort_order,id'
    )->fetchAll();
} catch (Throwable $ignored) {
    $aboutArtworks = [];
}

$text = static function (string $key, string $fallback = '') use ($copy): string {
    return (string) ($copy[$key] ?? $fallback);
};

$localized = static function (array $row, string $base) use ($lang): string {
    return (string) ($row[$base . '_' . $lang] ?? $row[$base . '_es'] ?? '');
};

$lines = static function (string $value): array {
    $items = preg_split('/\R+/', $value) ?: [];
    return array_values(array_filter(array_map('trim', $items)));
};

$videoEmbed = static function (array $video): string {
    $type = (string) ($video['video_type'] ?? '');
    $url = trim((string) ($video['video_url'] ?? ''));

    if (
        $type === 'youtube'
        && preg_match(
            '~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{6,})~',
            $url,
            $match
        )
    ) {
        return 'https://www.youtube.com/embed/' . rawurlencode($match[1]) . '?rel=0';
    }

    if (
        $type === 'vimeo'
        && preg_match('~vimeo\.com/(?:video/)?([0-9]+)~', $url, $match)
    ) {
        return 'https://player.vimeo.com/video/' . rawurlencode($match[1]);
    }

    return '';
};

$digits = preg_replace(
    '/\D+/',
    '',
    (string) ($settings['phone_digits'] ?? '50489136844')
);
$whatsAppBase = 'https://wa.me/' . $digits . '?text=';
$whatsApp = static function (string $message) use ($whatsAppBase): string {
    return $whatsAppBase . rawurlencode($message);
};

$companyName = (string) ($settings['company_name'] ?? 'ES MULTISERVICIOS');
$publicCorporateLogo = trim((string)($settings['site_logo_path'] ?? '')) ?: 'assets/brand/es-multiservicios-official.png';


$socialPlatforms = [
    'instagram' => 'Instagram',
    'facebook' => 'Facebook',
    'tiktok' => 'TikTok',
    'youtube' => 'YouTube',
    'linkedin' => 'LinkedIn',
];
$socialRows = [];
$socialRaw = trim((string)($settings['social_networks_json'] ?? ''));
if ($socialRaw !== '') {
    $decodedSocial = json_decode($socialRaw, true);
    if (is_array($decodedSocial)) {
        foreach ($decodedSocial as $row) {
            if (!is_array($row)) continue;
            $platform = strtolower(trim((string)($row['platform'] ?? '')));
            $url = trim((string)($row['url'] ?? ''));
            if (!isset($socialPlatforms[$platform]) || empty($row['enabled']) || !filter_var($url, FILTER_VALIDATE_URL)) continue;
            $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
            if (!in_array($scheme, ['http','https'], true)) continue;
            $socialRows[] = [
                'platform' => $platform,
                'label' => $socialPlatforms[$platform],
                'url' => $url,
                'sort_order' => (int)($row['sort_order'] ?? 0),
            ];
        }
    }
}
usort($socialRows, static fn(array $a,array $b): int => $a['sort_order'] <=> $b['sort_order']);
$socialSizeValue = (string)($settings['social_size'] ?? 'medium');
$socialStyleValue = (string)($settings['social_style'] ?? 'icon');
$socialLocationValue = (string)($settings['social_location'] ?? 'footer_floating_right');
$socialSize = in_array($socialSizeValue, ['small','medium','large'], true) ? $socialSizeValue : 'medium';
$socialStyle = in_array($socialStyleValue, ['icon','icon_name'], true) ? $socialStyleValue : 'icon';
$socialLocation = in_array($socialLocationValue, ['footer','hero','floating_left','floating_right','footer_floating_left','footer_floating_right'], true) ? $socialLocationValue : 'footer_floating_right';
$socialShowDesktop = ($settings['social_show_desktop'] ?? '1') === '1';
$socialShowMobile = ($settings['social_show_mobile'] ?? '1') === '1';
$socialVisibilityClass = (!$socialShowDesktop ? ' social-hide-desktop' : '') . (!$socialShowMobile ? ' social-hide-mobile' : '');
$socialRender = static function(string $placement) use ($socialRows,$socialSize,$socialStyle,$socialLocation,$socialVisibilityClass): string {
    if (!$socialRows) return '';
    $footerAllowed = in_array($socialLocation, ['footer','footer_floating_left','footer_floating_right'], true);
    $heroAllowed = $socialLocation === 'hero';
    $leftAllowed = in_array($socialLocation, ['floating_left','footer_floating_left'], true);
    $rightAllowed = in_array($socialLocation, ['floating_right','footer_floating_right'], true);
    if (($placement === 'footer' && !$footerAllowed) || ($placement === 'hero' && !$heroAllowed) || ($placement === 'left' && !$leftAllowed) || ($placement === 'right' && !$rightAllowed)) return '';
    $floating = in_array($placement, ['left','right'], true);
    $classes = $floating
        ? 'social-network-dock social-network-dock--'.$placement
        : 'social-network-links social-network-links--'.$placement;
    $classes .= ' social-size-'.$socialSize.' social-style-'.$socialStyle.$socialVisibilityClass;
    $sideAttr = $floating ? ' data-floating-social-side="'.h($placement).'"' : '';
    $html = '<div class="'.h($classes).'"'.$sideAttr.' aria-label="Social networks">';
    foreach ($socialRows as $row) {
        $platform = h($row['platform']);
        $label = h($row['label']);
        $html .= '<a class="social-network-link social-'.$platform.'" href="'.h($row['url']).'" target="_blank" rel="noopener noreferrer" aria-label="'.$label.'">';
        $html .= '<span class="social-network-icon" aria-hidden="true"></span>';
        if ($socialStyle === 'icon_name') $html .= '<span class="social-network-name">'.$label.'</span>';
        $html .= '</a>';
    }
    return $html.'</div>';
};
$maintenance = ($settings['maintenance_mode'] ?? '0') === '1';
$adminPreview = !empty($_SESSION['escms_admin_id'])
    && ($_GET['preview'] ?? '') === '1';
$previewQuery = $adminPreview ? '&preview=1' : '';

if ($maintenance && !$adminPreview) {
    $maintenanceTitle = (string) (
        $settings['maintenance_title']
        ?? ($lang === 'es'
            ? 'Estamos mejorando nuestro sitio.'
            : 'We are improving our website.')
    );
    $maintenanceText = (string) ($settings['maintenance_text'] ?? '');
    ?>
    <!doctype html>
    <html lang="<?= h($lang) ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title><?= h($maintenanceTitle) ?></title>
        <?php $maintenanceFavicon = trim((string) ($settings['favicon_path'] ?? '')) ?: 'assets/brand/favicon.png'; ?>
        <link rel="icon" type="image/png" href="<?= h($maintenanceFavicon) ?>">
        <link rel="shortcut icon" href="<?= h($maintenanceFavicon) ?>">
        <link rel="stylesheet" href="<?=h(versioned_asset('assets/vendor/select2/select2.local.css', 'assets/vendor/select2/select2.local.css'))?>">
    <link rel="stylesheet" href="<?=h(versioned_asset('assets/vendor/sweetalert2/sweetalert2.min.css', 'assets/vendor/sweetalert2/sweetalert2.min.css'))?>">
    <link rel="stylesheet" href="<?=h(versioned_asset('assets/vendor/show-notify/showNotify.css', 'assets/vendor/show-notify/showNotify.css'))?>">
    <link rel="stylesheet" href="<?=h(versioned_asset('assets/es-site.css', 'assets/es-site.css'))?>">
    <link rel="stylesheet" href="<?=h(versioned_asset('assets/action-icons.css', 'assets/action-icons.css'))?>">
</head>
    <body class="maintenance-page">
        <main class="maintenance-card">
            <img src="assets/brand/es-mark.png" alt="ES MULTISERVICIOS">
            <h1><?= h($maintenanceTitle) ?></h1>
            <p><?= h($maintenanceText) ?></p>
            <a
                class="btn btn-primary"
                href="<?= h($whatsApp(
                    $lang === 'es'
                        ? 'Hola, necesito información de ES MULTISERVICIOS.'
                        : 'Hello, I need information about ES MULTISERVICIOS.'
                )) ?>"
            >WhatsApp</a>
        </main>
    <?php if (!empty($turnstileConfigured)): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
<?php endif; ?>

<script>
document.addEventListener('click', function (event) {
    const trigger = event.target.closest('[data-solution-contact]');
    if (!trigger) return;
    const wanted = (trigger.getAttribute('data-solution-contact') || '').trim();
    if (!wanted) return;
    window.setTimeout(function () {
        const select = document.querySelector('#contact select[name="service"], #contact [name="service"]');
        if (!select) return;
        const option = Array.from(select.options || []).find(function (item) { return item.value === wanted || item.textContent.trim() === wanted; });
        if (option) {
            select.value = option.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            if (window.jQuery && window.jQuery(select).data('select2')) window.jQuery(select).trigger('change.select2');
        }
    }, 250);
});
</script>
</body>
    </html>
    <?php
    exit;
}

$navigation = [
    'home' => $lang === 'es' ? 'Inicio' : 'Home',
    'solutions' => $lang === 'es' ? 'Soluciones' : 'Solutions',
    'services' => $lang === 'es' ? 'Servicios' : 'Services',
    'projects' => $lang === 'es' ? 'Proyectos' : 'Projects',
    'affiliate' => $lang === 'es' ? 'Afiliados' : 'Affiliates',
    'contact' => $lang === 'es' ? 'Contacto' : 'Contact',
];

$servicesPublic = $lang === 'es'
    ? [
        ['Sitios web', 'Sitios corporativos, landing pages y CMS administrables.'],
        ['Software a la medida', 'Sistemas diseñados alrededor de tus procesos.'],
        ['Automatización', 'Menos trabajo repetitivo y procesos más ordenados.'],
        ['Integraciones', 'Conectamos plataformas, APIs y servicios.'],
        ['Implementación', 'Configuración, migración y acompañamiento.'],
        ['Soporte', 'Atención para ayudarte a seguir operando.'],
    ]
    : [
        ['Websites', 'Corporate sites, landing pages and manageable CMS platforms.'],
        ['Custom software', 'Systems designed around your processes.'],
        ['Automation', 'Less repetitive work and more organized processes.'],
        ['Integrations', 'We connect platforms, APIs and services.'],
        ['Implementation', 'Configuration, migration and onboarding.'],
        ['Support', 'Assistance to help you keep operating.'],
    ];

$whyItems = $lang === 'es'
    ? [
        'Productos propios desarrollados por nuestro equipo',
        'Soluciones adaptadas a cada negocio',
        'Diseño responsive y acceso web',
        'Seguridad y control de acceso',
        'Acompañamiento, implementación y soporte',
        'Capacidad para desarrollar a la medida',
    ]
    : [
        'Our own products built by our team',
        'Solutions adapted to each business',
        'Responsive design and web access',
        'Security and access control',
        'Implementation, onboarding and support',
        'Custom development capabilities',
    ];


function marketing_svg_icon(string $name): string
{
    $icons = [
        'web' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 4v5"/></svg>',
        'code' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 9-3 3 3 3M16 9l3 3-3 3M14 5l-4 14"/></svg>',
        'automation' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/><circle cx="12" cy="12" r="4"/></svg>',
        'integration' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 12h8M12 8v8"/><path d="M7 3h3v4H7a4 4 0 0 0-4 4v2M17 21h-3v-4h3a4 4 0 0 0 4-4v-2"/></svg>',
        'launch' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>',
        'support' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 13v-2a8 8 0 0 1 16 0v2"/><path d="M4 13h3v6H5a2 2 0 0 1-2-2v-2a2 2 0 0 1 1-2ZM20 13h-3v6h2a2 2 0 0 0 2-2v-2a2 2 0 0 0-1-2ZM17 19c-1 2-3 3-5 3"/></svg>',
        'product' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v13H4zM8 7V4h8v3"/><path d="M9 12h6"/></svg>',
        'adapt' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12a8 8 0 0 1 13.6-5.7L20 9M20 4v5h-5M20 12a8 8 0 0 1-13.6 5.7L4 15M4 20v-5h5"/></svg>',
        'responsive' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="14" height="11" rx="2"/><rect x="16" y="9" width="5" height="11" rx="1"/><path d="M8 20h4M10 15v5"/></svg>',
        'security' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 5 3 8 7 10 4-2 7-5 7-10V6z"/><path d="m9 12 2 2 4-4"/></svg>',
        'onboarding' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/><circle cx="5" cy="12" r="2"/></svg>',
        'custom' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 6 4 4M4 20l4-1 10-10-3-3L5 16z"/><path d="M13 5l2-2 4 4-2 2"/></svg>',
    ];
    return $icons[$name] ?? $icons['product'];
}

$serviceIconKeys = ['web','code','automation','integration','launch','support'];
$whyIconKeys = ['product','adapt','responsive','security','onboarding','custom'];
?>
<!doctype html>
<html lang="<?= h($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= h($settings['seo_title'] ?? 'ES MULTISERVICIOS') ?></title>
    <meta
        name="description"
        content="<?= h($settings['seo_description'] ?? '') ?>"
    >
    <meta name="robots" content="<?= h(trim((string)($settings['seo_robots'] ?? 'index,follow')) ?: 'index,follow') ?>">
    <?php if (!empty($settings['seo_google_verification'])): ?>
        <meta name="google-site-verification" content="<?= h($settings['seo_google_verification']) ?>">
    <?php endif; ?>
    <meta property="og:title" content="<?= h($settings['seo_title'] ?? 'ES MULTISERVICIOS') ?>">
    <meta property="og:description" content="<?= h($settings['seo_description'] ?? '') ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= h(site_url() !== '' ? rtrim(site_url(), '/') . '/' : '') ?>">
    <meta name="theme-color" content="#0B2E59">
    <?php if (!empty($settings['seo_social_image'])): ?>
        <meta property="og:image" content="<?= h($settings['seo_social_image']) ?>">
    <?php endif; ?>
    <?php $publicFavicon = trim((string) ($settings['favicon_path'] ?? '')) ?: 'assets/brand/favicon.png'; ?>
    <link rel="icon" type="image/png" href="<?= h($publicFavicon) ?>">
    <link rel="shortcut icon" href="<?= h($publicFavicon) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="<?=h(versioned_asset('assets/vendor/select2/select2.local.css', 'assets/vendor/select2/select2.local.css'))?>">
    <link rel="stylesheet" href="<?=h(versioned_asset('assets/vendor/sweetalert2/sweetalert2.min.css', 'assets/vendor/sweetalert2/sweetalert2.min.css'))?>">
    <link rel="stylesheet" href="<?=h(versioned_asset('assets/vendor/show-notify/showNotify.css', 'assets/vendor/show-notify/showNotify.css'))?>">
    <link rel="stylesheet" href="<?=h(versioned_asset('assets/es-site.css', 'assets/es-site.css'))?>">
</head>
<body>
<header class="site-header" data-header>
    <div class="shell nav-shell">
        <a class="master-brand" href="#home" aria-label="ES MULTISERVICIOS">
            <img src="assets/brand/es-mark.png" alt="">
            <span>
                <strong>ES MULTISERVICIOS</strong>
                <small><?= $lang === 'es' ? 'Más que servicios, construimos soluciones' : 'More than services, we build solutions' ?></small>
            </span>
        </a>

        <button
            class="nav-toggle"
            type="button"
            aria-expanded="false"
            aria-label="Menu"
            data-nav-toggle
        >
            <span></span>
            <span></span>
            <span></span>
        </button>

        <nav class="site-nav" data-nav>
            <?php foreach ($navigation as $id => $label): ?>
                <a href="#<?= h($id) ?>" data-nav-link data-section="<?= h($id) ?>"><?= h($label) ?></a>
            <?php endforeach; ?>
            <span class="nav-indicator" data-nav-indicator aria-hidden="true"></span>
        </nav>

        <div class="header-actions">
            <div class="lang-switch" aria-label="Language">
                <a
                    class="<?= $lang === 'es' ? 'active' : '' ?>"
                    href="?lang=es<?= h($previewQuery) ?>"
                >ES</a>
                <a
                    class="<?= $lang === 'en' ? 'active' : '' ?>"
                    href="?lang=en<?= h($previewQuery) ?>"
                >EN</a>
            </div>
            <a
                class="btn btn-compact btn-whatsapp desktop-cta"
                href="<?= h($whatsApp(
                    $lang === 'es'
                        ? 'Hola, quiero información sobre ES MULTISERVICIOS.'
                        : 'Hello, I would like information about ES MULTISERVICIOS.'
                )) ?>"
            >WhatsApp</a>
        </div>
    </div>
</header>

<main>
    <section class="hero" id="home">
        <div class="shell hero-grid">
            <div class="hero-copy reveal">
                <span class="eyebrow"><?= h($text('hero_kicker')) ?></span>
                <h1><?= h($text('hero_title')) ?></h1>
                <p class="lead"><?= h($text('hero_text')) ?></p>

                <div class="hero-actions">
                    <a
                        class="btn btn-primary"
                        href="#contact"
                    ><?= h($text('hero_primary')) ?></a>
                    <a
                        class="btn btn-whatsapp"
                        href="<?= h($whatsApp(
                            $lang === 'es'
                                ? 'Hola, quiero conocer más sobre ES MULTISERVICIOS.'
                                : 'Hello, I would like to learn more about ES MULTISERVICIOS.'
                        )) ?>"
                    ><?= h($text('hero_secondary')) ?></a>
                </div>

                <div class="trust-row">
                    <div>
                        <strong>100% Web</strong>
                        <span><?= $lang === 'es' ? 'Acceso desde navegador' : 'Browser access' ?></span>
                    </div>
                    <div>
                        <strong>Responsive</strong>
                        <span><?= $lang === 'es' ? 'Computadora, tablet y teléfono' : 'Desktop, tablet and phone' ?></span>
                    </div>
                    <div>
                        <strong><?= $lang === 'es' ? 'A tu medida' : 'Built around you' ?></strong>
                        <span><?= $lang === 'es' ? 'Tecnología adaptable' : 'Adaptable technology' ?></span>
                    </div>
                </div>
            </div>

            <div class="hero-media reveal corporate-solution-hero" aria-label="ES MULTISERVICIOS solutions">
                <div class="corporate-brand-panel">
                    <img class="corporate-brand-logo" src="<?= h($publicCorporateLogo) ?>" alt="<?= h($companyName) ?>">
                    <span><?= $lang === 'es' ? 'Tecnología creada para resolver necesidades reales' : 'Technology built to solve real needs' ?></span>
                </div>
                <div class="hero-solution-stack">
                    <?php foreach (array_slice($products, 0, 3) as $heroIndex => $heroProduct): ?>
                        <?php
                        $heroUrl = trim((string) ($heroProduct['cta_url'] ?? ''));
                        $heroChipClass = 'hero-solution-chip hero-solution-chip-' . ((int) $heroIndex + 1)
                            . ' solution-key-' . h($heroProduct['product_key'] ?? 'solution');
                        ?>
                        <?php if ($heroUrl !== ''): ?>
                            <a
                                class="<?= $heroChipClass ?> hero-solution-chip-link"
                                href="<?= h($heroUrl) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label="<?= h(($lang === 'es' ? 'Visitar ' : 'Visit ') . (string) $heroProduct['name']) ?>"
                                style="--product-accent:<?= h($heroProduct['accent_color']) ?>"
                            >
                                <div class="hero-solution-logo">
                                    <?php if (trim((string) ($heroProduct['logo_path'] ?? '')) !== ''): ?>
                                        <img src="<?= h($heroProduct['logo_path']) ?>" alt="<?= h($heroProduct['name']) ?>">
                                    <?php else: ?>
                                        <b><?= h(strtoupper(substr((string) $heroProduct['name'], 0, 2))) ?></b>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <strong><?= h($heroProduct['name']) ?></strong>
                                    <small><?= $lang === 'es' ? 'Solución corporativa' : 'Corporate solution' ?></small>
                                </div>
                            </a>
                        <?php else: ?>
                            <div
                                class="<?= $heroChipClass ?>"
                                style="--product-accent:<?= h($heroProduct['accent_color']) ?>"
                            >
                                <div class="hero-solution-logo">
                                    <?php if (trim((string) ($heroProduct['logo_path'] ?? '')) !== ''): ?>
                                        <img src="<?= h($heroProduct['logo_path']) ?>" alt="<?= h($heroProduct['name']) ?>">
                                    <?php else: ?>
                                        <b><?= h(strtoupper(substr((string) $heroProduct['name'], 0, 2))) ?></b>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <strong><?= h($heroProduct['name']) ?></strong>
                                    <small><?= $lang === 'es' ? 'Solución corporativa' : 'Corporate solution' ?></small>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
    <?= $socialRender('hero') ?>

    <section class="section section-soft solutions-premium-section" id="solutions">
        <div class="shell">
            <div class="section-heading centered reveal">
                <span class="eyebrow"><?= $lang === 'es' ? 'SOLUCIONES ES MULTISERVICIOS' : 'ES MULTISERVICIOS SOLUTIONS' ?></span>
                <h2><?= h($text('solutions_title')) ?></h2>
                <p><?= h($text('solutions_text')) ?></p>
            </div>

            <div class="solution-directory-grid solution-count-<?= min(count($products), 3) ?>">
                <?php foreach ($products as $index => $product): ?>
                    <?php
                        $features = $lines($localized($product, 'features'));
                        $productKey = strtolower(trim((string)($product['product_key'] ?? '')));
                        $fitCopy = match ($productKey) {
                            'izzy' => $lang === 'es'
                                ? 'Ideal para comercios, restaurantes y empresas que necesitan facturación, inventario y control operativo en una sola plataforma.'
                                : 'Ideal for retailers, restaurants and companies that need billing, inventory and operational control in one platform.',
                            'cami' => $lang === 'es'
                                ? 'Ideal para clínicas, consultorios y centros médicos que necesitan organizar pacientes, expedientes y procesos clínicos.'
                                : 'Ideal for clinics, practices and medical centers that need to organize patients, records and clinical workflows.',
                            'zynko' => $lang === 'es'
                                ? 'Ideal para empresas que atienden clientes por varios canales y necesitan centralizar conversaciones, agentes y automatización.'
                                : 'Ideal for companies serving customers across multiple channels that need centralized conversations, agents and automation.',
                            default => $lang === 'es'
                                ? 'Una solución adaptable para organizaciones que buscan digitalizar y simplificar sus procesos.'
                                : 'An adaptable solution for organizations looking to digitize and simplify their processes.',
                        };
                    ?>
                    <article class="solution-directory-card reveal solution-key-<?= h($product['product_key'] ?? 'solution') ?>" style="--product-accent:<?= h($product['accent_color']) ?>">
                        <div class="solution-directory-top">
                            <div class="solution-brand" aria-label="<?= h($product['name']) ?>">
                                <?php if (trim((string)($product['logo_path'] ?? '')) !== ''): ?>
                                    <img src="<?= h($product['logo_path']) ?>" alt="<?= h($product['name']) ?>">
                                <?php else: ?>
                                    <div class="solution-monogram" aria-hidden="true"><?= h(strtoupper(substr((string)$product['name'], 0, 2))) ?></div>
                                <?php endif; ?>
                            </div>
                            <span class="solution-index"><?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                        </div>
                        <div class="solution-directory-copy">
                            <span class="solution-owned"><?= $lang === 'es' ? 'Solución desarrollada por ES MULTISERVICIOS' : 'Solution developed by ES MULTISERVICIOS' ?></span>
                            <h3><?= h($product['name']) ?></h3>
                            <?php $solutionTagline = $localized($product, 'tagline'); ?>
                            <?php if (trim($solutionTagline) !== ''): ?><div class="solution-tagline"><?= h($solutionTagline) ?></div><?php endif; ?>
                            <p><?= h($localized($product, 'description')) ?></p>
                            <div class="solution-fit-note">
                                <span><?= $lang === 'es' ? 'Ideal para' : 'Best for' ?></span>
                                <strong><?= h($fitCopy) ?></strong>
                            </div>
                            <?php if ($features): ?>
                                <div class="solution-feature-list">
                                    <?php foreach (array_slice($features, 0, 4) as $feature): ?><span><?= h($feature) ?></span><?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="solution-card-actions">
                            <a class="solution-action solution-action-info" href="#contact" data-solution-contact="<?= h($product['name']) ?>">
                                <span><?= $lang === 'es' ? 'Solicitar información' : 'Request information' ?></span><b aria-hidden="true">→</b>
                            </a>
                            <?php if (trim((string)$product['cta_url']) !== ''): ?>
                                <a class="solution-action solution-action-site" href="<?= h($product['cta_url']) ?>" target="_blank" rel="noopener noreferrer">
                                    <?php $siteLabel = trim($localized($product, 'cta_label')); ?>
                                    <span><?= h($siteLabel !== '' ? $siteLabel : ($lang === 'es' ? 'Visitar sitio' : 'Visit website')) ?></span><b aria-hidden="true">↗</b>
                                </a>
                            <?php else: ?>
                                <span class="solution-action solution-action-pending" aria-disabled="true">
                                    <span><?= $lang === 'es' ? 'Sitio próximamente' : 'Website coming soon' ?></span><b aria-hidden="true">…</b>
                                </span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="solutions-custom-cta reveal">
                <div>
                    <span class="eyebrow"><?= $lang === 'es' ? 'SOLUCIONES A LA MEDIDA' : 'CUSTOM SOLUTIONS' ?></span>
                    <h3><?= $lang === 'es' ? '¿Tu empresa necesita algo diferente?' : 'Does your company need something different?' ?></h3>
                    <p><?= $lang === 'es' ? 'Diseñamos software, sitios web, automatizaciones e integraciones alineadas a tus procesos reales.' : 'We design software, websites, automations and integrations around your real business processes.' ?></p>
                </div>
                <a class="btn btn-primary" href="#contact"><?= $lang === 'es' ? 'Hablemos de tu proyecto' : 'Tell us about your project' ?></a>
            </div>
        </div>
    </section>

    <section class="section section-dark" id="services">
        <div class="shell">
            <div class="section-heading reveal">
                <span class="eyebrow light"><?= $lang === 'es' ? 'SERVICIOS' : 'SERVICES' ?></span>
                <h2><?= h($text('services_title')) ?></h2>
                <p><?= h($text('services_text')) ?></p>
            </div>

            <div class="service-grid">
                <?php foreach ($servicesPublic as $index => $service): ?>
                    <article class="service-card reveal">
                        <div class="service-card-top">
                            <span class="service-icon"><?= marketing_svg_icon($serviceIconKeys[$index] ?? 'product') ?></span>
                            <span class="service-number"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                        </div>
                        <h3><?= h($service[0]) ?></h3>
                        <p><?= h($service[1]) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php if ($videos): ?>
        <section class="section video-showcase-section" id="videos">
            <div class="shell">
                <div class="section-heading reveal">
                    <span class="eyebrow">
                        <?= $lang === 'es' ? 'DEMOSTRACIONES Y CONTENIDO' : 'DEMOS & CONTENT' ?>
                    </span>
                    <h2>
                        <?= $lang === 'es'
                            ? 'Mira nuestras soluciones y proyectos en acción'
                            : 'See our solutions and projects in action' ?>
                    </h2>
                    <p>
                        <?= $lang === 'es'
                            ? 'Videos publicados desde el administrador y presentados en una cuadrícula uniforme y responsive.'
                            : 'Videos published from the administrator and presented in a clean, uniform and responsive grid.' ?>
                    </p>
                </div>

                <div class="video-showcase-grid">
                    <?php foreach ($videos as $video): ?>
                        <?php $embed = $videoEmbed($video); ?>
                        <article class="public-video-card reveal">
                            <div class="public-video-frame">
                                <?php if (($video['video_type'] ?? '') === 'upload' && !empty($video['file_path'])): ?>
                                    <video
                                        controls
                                        preload="metadata"
                                        playsinline
                                        <?php if (!empty($video['poster_path'])): ?>
                                            poster="<?= h($video['poster_path']) ?>"
                                        <?php endif; ?>
                                    >
                                        <source src="<?= h($video['file_path']) ?>">
                                    </video>
                                <?php elseif ($embed !== ''): ?>
                                    <iframe
                                        src="<?= h($embed) ?>"
                                        title="<?= h((string) $video['title']) ?>"
                                        loading="lazy"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                        allowfullscreen
                                    ></iframe>
                                <?php endif; ?>
                            </div>

                            <div class="public-video-copy">
                                <h3><?= h((string) $video['title']) ?></h3>
                                <?php if (trim((string) ($video['description'] ?? '')) !== ''): ?>
                                    <p><?= h((string) $video['description']) ?></p>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="section section-soft" id="projects">
        <div class="shell">
            <div class="section-heading centered project-section-heading reveal">
                <span class="eyebrow"><?= $lang === 'es' ? 'PROYECTOS A LA MEDIDA' : 'CUSTOM PROJECTS' ?></span>
                <h2><?= h($text('projects_title')) ?></h2>
                <p><?= $lang === 'es'
                    ? 'Además de nuestras soluciones propias, en ES MULTISERVICIOS diseñamos y construimos plataformas, sitios web y sistemas adaptados a la operación real de cada cliente.'
                    : 'Beyond our own products, ES MULTISERVICIOS designs and builds platforms, websites and systems around each client’s real operation.' ?></p>
            </div>

            <div class="project-capability-strip reveal" aria-label="<?= $lang === 'es' ? 'Capacidades de desarrollo' : 'Development capabilities' ?>">
                <span><b>01</b><?= $lang === 'es' ? 'Diseño y desarrollo a la medida' : 'Custom design and development' ?></span>
                <span><b>02</b><?= $lang === 'es' ? 'CMS y administración propia' : 'Custom CMS and administration' ?></span>
                <span><b>03</b><?= $lang === 'es' ? 'Responsive, soporte y evolución' : 'Responsive, support and evolution' ?></span>
            </div>

            <div class="projects-grid projects-showcase-grid">
                <?php foreach ($projects as $loopIndex => $project): ?>
                    <?php
                        $projectImage = trim((string)($project['image_path'] ?? ''));
                        $projectImageAvailable = false;
                        if ($projectImage !== '') {
                            if (preg_match('~^https?://~i', $projectImage)) {
                                $projectImageAvailable = true;
                            } else {
                                $projectImageAvailable = is_file(__DIR__ . '/' . ltrim($projectImage, '/'));
                            }
                        }
                    ?>
                    <article class="project-card project-showcase-card reveal">
                        <div class="project-visual-panel">
                            <div class="project-visual-meta">
                                <span><?= $lang === 'es' ? 'Proyecto ES MULTISERVICIOS' : 'ES MULTISERVICIOS project' ?></span>
                                <b><?= str_pad((string)($loopIndex + 1), 2, '0', STR_PAD_LEFT) ?></b>
                            </div>
                            <?php if ($projectImageAvailable): ?>
                                <img
                                    src="<?= h($projectImage) ?>"
                                    alt="<?= h($project['title']) ?>"
                                    loading="lazy"
                                >
                            <?php else: ?>
                                <div class="project-placeholder"><span><?php
                                    $projectWords = preg_split('/\s+/u', trim((string)$project['title'])) ?: [];
                                    $projectInitials = '';
                                    foreach (array_slice($projectWords, 0, 2) as $word) {
                                        $projectInitials .= function_exists('mb_substr') ? mb_substr($word, 0, 1, 'UTF-8') : substr($word, 0, 1);
                                    }
                                    echo h(strtoupper($projectInitials !== '' ? $projectInitials : 'PR'));
                                ?></span></div>
                            <?php endif; ?>
                            <div class="project-visual-caption">
                                <span><?= $lang === 'es' ? 'Hecho a la medida' : 'Built to fit' ?></span>
                                <strong><?= $lang === 'es' ? 'Diseñado, desarrollado y acompañado por nuestro equipo.' : 'Designed, developed and supported by our team.' ?></strong>
                            </div>
                        </div>

                        <div class="project-content-panel">
                            <div class="project-kicker-row">
                                <small><?= h($localized($project, 'category')) ?></small>
                                <span class="project-live-dot"><?= $lang === 'es' ? 'Caso real' : 'Real project' ?></span>
                            </div>
                            <h3><?= h($project['title']) ?></h3>
                            <p><?= h($localized($project, 'description')) ?></p>
                            <div class="project-value-note">
                                <span><?= $lang === 'es' ? 'Qué demuestra' : 'What it shows' ?></span>
                                <strong><?= $lang === 'es'
                                    ? 'Podemos adaptar diseño, contenido, administración y funcionalidades a las necesidades específicas de cada negocio.'
                                    : 'We can adapt design, content, administration and functionality to the specific needs of each business.' ?></strong>
                            </div>
                            <div class="project-card-actions">
                                <a class="project-action project-action-info" href="#contact">
                                    <?= $lang === 'es' ? 'Consultar proyecto' : 'Ask about project' ?> <span aria-hidden="true">→</span>
                                </a>
                                <?php if (trim((string)($project['project_url'] ?? '')) !== ''): ?>
                                    <a
                                        class="project-action project-action-site"
                                        href="<?= h($project['project_url']) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    ><?= $lang === 'es' ? 'Visitar sitio' : 'Visit website' ?> <span aria-hidden="true">↗</span></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>


    <section class="section affiliate-section" id="affiliate">
        <div class="shell">
            <div class="section-heading affiliate-heading reveal">
                <span class="eyebrow"><?= $lang === 'es' ? 'PROGRAMA DE AFILIADOS' : 'AFFILIATE PROGRAM' ?></span>
                <h2><?= h($text('affiliate_title')) ?></h2>
                <p><?= h($text('affiliate_text')) ?></p>
            </div>

            <div class="affiliate-premium-grid">
                <article class="affiliate-benefit-card reveal">
                    <span class="affiliate-benefit-number">01</span>
                    <div>
                        <strong><?= h($text('affiliate_single_title')) ?></strong>
                        <p><?= h($text('affiliate_single_text')) ?></p>
                    </div>
                </article>

                <article class="affiliate-benefit-card affiliate-benefit-featured reveal">
                    <span class="affiliate-benefit-number">03+</span>
                    <div>
                        <strong><?= h($text('affiliate_team_title')) ?></strong>
                        <p><?= h($text('affiliate_team_text')) ?></p>
                    </div>
                </article>
            </div>

            <div class="affiliate-process-card reveal">
                <div class="affiliate-process-copy">
                    <span class="affiliate-mini-label"><?= $lang === 'es' ? 'ASÍ FUNCIONA' : 'HOW IT WORKS' ?></span>
                    <h3><?= $lang === 'es' ? 'Tú conectas al cliente. Nosotros nos encargamos del resto.' : 'You connect the client. We take care of the rest.' ?></h3>
                    <p><?= h($text('affiliate_support_text')) ?></p>
                    <small><?= h($text('affiliate_disclaimer')) ?></small>
                </div>

                <div class="affiliate-steps">
                    <span><b>1</b><strong><?= $lang === 'es' ? 'Recomienda' : 'Recommend' ?></strong><small><?= $lang === 'es' ? 'Presenta nuestras soluciones.' : 'Introduce our solutions.' ?></small></span>
                    <span><b>2</b><strong><?= $lang === 'es' ? 'Conecta' : 'Connect' ?></strong><small><?= $lang === 'es' ? 'Nos compartes el prospecto.' : 'Share the lead with us.' ?></small></span>
                    <span><b>3</b><strong><?= $lang === 'es' ? 'Gana' : 'Earn' ?></strong><small><?= $lang === 'es' ? 'Recibe tu beneficio según las condiciones.' : 'Earn according to the program terms.' ?></small></span>
                </div>

                <a
                    class="btn btn-whatsapp affiliate-cta"
                    href="<?= h($whatsApp(
                        $lang === 'es'
                            ? 'Hola, quiero información para formar parte del programa de afiliados de ES MULTISERVICIOS.'
                            : 'Hello, I would like information about joining the ES MULTISERVICIOS affiliate program.'
                    )) ?>"
                ><?= h($text('affiliate_cta')) ?></a>
            </div>
        </div>
    </section>

    <?php if ($aboutArtworks): ?>
        <section class="section company-artwork-section" id="company-artwork">
            <div class="shell">
                <div class="section-heading centered reveal">
                    <span class="eyebrow"><?= $lang === 'es' ? 'NUESTRA IDENTIDAD' : 'OUR IDENTITY' ?></span>
                    <h2><?= $lang === 'es' ? 'Material corporativo aprobado' : 'Approved corporate material' ?></h2>
                </div>

                <div class="company-artwork-grid <?= count($aboutArtworks) === 1 ? 'single-artwork' : '' ?>">
                    <?php foreach ($aboutArtworks as $artwork): ?>
                        <article class="company-artwork-card reveal">
                            <img
                                src="<?= h($artwork['image_path']) ?>"
                                alt="<?= h(
                                    $artwork['title']
                                    ?: ($lang === 'es' ? 'Arte corporativo' : 'Corporate artwork')
                                ) ?>"
                            >
                            <?php if (trim((string) ($artwork['title'] ?? '')) !== ''): ?>
                                <strong><?= h((string) $artwork['title']) ?></strong>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="section why-section">
        <div class="shell why-grid">
            <div class="section-heading reveal">
                <span class="eyebrow"><?= $lang === 'es' ? 'NUESTRO ENFOQUE' : 'OUR APPROACH' ?></span>
                <h2><?= h($text('why_title')) ?></h2>
                <p>
                    <?= $lang === 'es'
                        ? 'La tecnología debe simplificar la vida de las personas. Por eso construimos soluciones claras, adaptables y acompañadas por soporte real.'
                        : 'Technology should simplify people’s lives. That is why we build clear, adaptable solutions backed by real support.' ?>
                </p>
            </div>

            <div class="why-list reveal">
                <?php foreach ($whyItems as $index => $item): ?>
                    <span class="why-item">
                        <i class="why-icon"><?= marketing_svg_icon($whyIconKeys[$index] ?? 'product') ?></i>
                        <b><?= h($item) ?></b>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php if (!empty($testimonials)): ?>
        <section class="section testimonials-section" id="testimonials">
            <div class="shell">
                <div class="section-heading centered reveal">
                    <span class="eyebrow"><?= $lang === 'es' ? 'OPINIONES DE CLIENTES' : 'CLIENT REVIEWS' ?></span>
                    <h2><?= $lang === 'es' ? 'Experiencias alrededor de nuestras soluciones' : 'Experiences around our solutions' ?></h2>
                    <p><?= $lang === 'es' ? 'Conoce cómo nuestras soluciones pueden acompañar distintos tipos de operación, desde facturación y gestión clínica hasta atención omnicanal y desarrollos a la medida.' : 'See how our solutions can support different operations, from billing and clinical management to omnichannel service and custom development.' ?></p>
                </div>
                <div class="testimonials-grid">
                    <?php foreach ($testimonials as $testimonial): ?>
                        <?php
                        $testimonialSolution = trim((string)($testimonial['solution_name'] ?? ''));
                        $testimonialLogo = '';
                        $testimonialAccent = '#16b89a';
                        foreach ($products as $testimonialProduct) {
                            $candidateName = trim((string)($testimonialProduct['name'] ?? ''));
                            $candidateKey = trim((string)($testimonialProduct['product_key'] ?? ''));
                            if (strcasecmp($testimonialSolution, $candidateName) === 0 || strcasecmp($testimonialSolution, $candidateKey) === 0) {
                                $testimonialLogo = trim((string)($testimonialProduct['logo_path'] ?? ''));
                                $testimonialAccent = trim((string)($testimonialProduct['accent_color'] ?? '')) ?: '#16b89a';
                                break;
                            }
                        }
                        if ($testimonialLogo === '' && in_array(strtolower($testimonialSolution), ['soluciones a la medida','custom solutions','desarrollo a la medida','custom development'], true)) {
                            $testimonialLogo = 'assets/brand/custom-solutions.svg';
                            $testimonialAccent = '#ff8a00';
                        }
                        if ($testimonialLogo === '') {
                            $testimonialLogo = $publicCorporateLogo;
                        }
                        ?>
                        <article class="testimonial-card reveal" style="--testimonial-accent:<?= h($testimonialAccent) ?>">
                            <div class="testimonial-card-head">
                                <div class="testimonial-solution-logo">
                                    <img src="<?= h($testimonialLogo) ?>" alt="<?= h($testimonialSolution !== '' ? $testimonialSolution : $companyName) ?>">
                                </div>
                                <?php if ($testimonialSolution !== ''): ?>
                                    <span class="testimonial-solution-name"><?= h($testimonialSolution) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="testimonial-quote" aria-hidden="true">“</div>
                            <p><?= h($localized($testimonial, 'quote')) ?></p>
                            <footer>
                                <strong><?= h((string)$testimonial['client_name']) ?></strong>
                                <?php $meta = trim($localized($testimonial, 'client_role')); ?>
                                <?php if ($meta !== ''): ?><span><?= h($meta) ?></span><?php endif; ?>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($showPublicServiceMap): ?>
        <?php $serviceMapGoogleUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($serviceMapQuery); ?>
        <section class="section coverage-map-section" aria-labelledby="coverage-map-title">
            <div class="shell">
                <div class="coverage-map-card reveal">
                    <div class="coverage-map-header">
                        <div class="coverage-map-copy">
                            <span class="eyebrow"><?= $lang === 'es' ? 'COBERTURA' : 'COVERAGE' ?></span>
                            <h2 id="coverage-map-title"><?= h($serviceMapLabel) ?></h2>
                            <p>
                                <?= $lang === 'es'
                                    ? 'Explora nuestra ubicación de cobertura de referencia. Contáctanos para confirmar disponibilidad en tu zona.'
                                    : 'Explore our reference coverage location. Contact us to confirm availability in your area.' ?>
                            </p>
                        </div>

                        <a
                            class="coverage-map-open"
                            href="<?= h($serviceMapGoogleUrl) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <span><?= $lang === 'es' ? 'Abrir en Google Maps' : 'Open in Google Maps' ?></span>
                            <span aria-hidden="true">↗</span>
                        </a>
                    </div>

                    <?php if (!empty($serviceAreasPublic)): ?>
                        <div class="coverage-area-chips" aria-label="<?= $lang === 'es' ? 'Áreas de servicio' : 'Service areas' ?>">
                            <?php foreach ($serviceAreasPublic as $area): ?>
                                <?php $areaName = trim((string)($area['area_name'] ?? '')); ?>
                                <?php if ($areaName !== ''): ?>
                                    <span><?= h($areaName) ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="coverage-map-frame">
                        <iframe
                            title="<?= h($serviceMapLabel) ?>"
                            src="https://www.google.com/maps?q=<?= rawurlencode($serviceMapQuery) ?>&output=embed&hl=<?= $lang === 'es' ? 'es' : 'en' ?>"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            allowfullscreen
                        ></iframe>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="section contact-section" id="contact">
        <div class="shell">
            <div class="contact-intro reveal">
                <span class="eyebrow light"><?= $lang === 'es' ? 'CONTACTO' : 'CONTACT' ?></span>
                <h2><?= h($text('contact_title')) ?></h2>
                <p><?= h($text('contact_text')) ?></p>
            </div>

            <div class="contact-experience-grid">
                <div class="contact-form-card reveal">
                    <div class="contact-card-heading">
                        <span class="contact-icon"><?= marketing_svg_icon('product') ?></span>
                        <div>
                            <strong><?= $lang === 'es' ? 'Cuéntanos qué necesitas' : 'Tell us what you need' ?></strong>
                            <p><?= $lang === 'es' ? 'Déjanos tus datos y una breve descripción. La solicitud llegará al administrador del sitio.' : 'Leave your details and a short description. The request will reach the website administrator.' ?></p>
                        </div>
                    </div>

                    <?php
                    $contactRequired = [
                        'name' => ($settings['contact_required_name'] ?? '1') === '1',
                        'email' => true,
                        'phone' => ($settings['contact_required_phone'] ?? '1') === '1',
                        'service' => ($settings['contact_required_service'] ?? '1') === '1',
                        'message' => ($settings['contact_required_message'] ?? '1') === '1',
                    ];
                    $requiredMark = '<span class="field-required" aria-hidden="true">*</span>';
                    $messageMinChars = max(20, min(300, (int)($settings['contact_message_min_chars'] ?? 30)));
                    $messageMinWords = max(3, min(30, (int)($settings['contact_message_min_words'] ?? 5)));
                    $contactRequired['referral'] = ($settings['contact_required_referral'] ?? '1') === '1';
                    $defaultReferralEs = "Búsqueda en Google u otro buscador\nFacebook\nTikTok\nInstagram\nWhatsApp\nRecomendación de una persona o empresa\nYa conocía ES MULTISERVICIOS\nOtro";
                    $defaultReferralEn = "Google or another search engine\nFacebook\nTikTok\nInstagram\nWhatsApp\nRecommendation from a person or company\nI already knew ES MULTISERVICIOS\nOther";
                    $referralRaw = (string)($settings[$lang === 'es' ? 'contact_referral_options_es' : 'contact_referral_options_en'] ?? ($lang === 'es' ? $defaultReferralEs : $defaultReferralEn));
                    $referralOptions = array_values(array_filter(array_map('trim', preg_split('/\R/u', $referralRaw) ?: []), static fn($v) => $v !== ''));
                    if (!$referralOptions) $referralOptions = array_values(array_filter(array_map('trim', preg_split('/\R/u', $lang === 'es' ? $defaultReferralEs : $defaultReferralEn) ?: [])));
                    $turnstileEnabled = ($settings['contact_turnstile_enabled'] ?? '0') === '1';
                    $turnstileSiteKey = trim((string)($settings['contact_turnstile_site_key'] ?? ''));
                    $turnstileConfigured = $turnstileEnabled && $turnstileSiteKey !== '' && trim((string)($settings['contact_turnstile_secret'] ?? '')) !== '';
                    ?>
                    <form class="contact-form" data-contact-form action="estimate-submit.php" method="post">
                        <input type="hidden" name="lang" value="<?= h($lang) ?>">
                        <input type="hidden" name="form_started_at" value="<?= time() ?>" data-form-started-at>
                        <div class="contact-antispam-field" aria-hidden="true">
                            <label>Leave this field empty
                                <input type="text" name="website_url_confirm" value="" tabindex="-1" autocomplete="off" inputmode="none">
                            </label>
                        </div>
                        <div class="contact-required-note" role="note">
                            <strong><span class="field-required" aria-hidden="true">*</span> <?= $lang === 'es' ? 'Campos requeridos' : 'Required fields' ?></strong>
                            <span><?= $lang === 'es' ? 'Los campos con asterisco son obligatorios. El correo siempre es requerido para poder responderte.' : 'Fields marked with an asterisk are required. Email is always required so we can reply.' ?></span>
                        </div>
                        <div class="contact-form-grid">
                            <label>
                                <span><?= $lang === 'es' ? 'Nombre' : 'Name' ?><?= $contactRequired['name'] ? $requiredMark : '' ?></span>
                                <input name="name" autocomplete="name" maxlength="120" <?= $contactRequired['name'] ? 'required' : '' ?>>
                                <?php if ($contactRequired['name']): ?><small class="field-requirement-hint"><?= $lang === 'es' ? 'Tu nombre completo.' : 'Your full name.' ?></small><?php endif; ?>
                            </label>
                            <label>
                                <span><?= $lang === 'es' ? 'Correo' : 'Email' ?><?= $contactRequired['email'] ? $requiredMark : '' ?></span>
                                <input type="email" name="email" autocomplete="email" maxlength="190" required aria-describedby="contact-email-help">
                                <small id="contact-email-help" class="field-requirement-hint"><?= $lang === 'es' ? 'Usaremos este correo para responderte.' : 'We will use this email to reply.' ?></small>
                            </label>
                            <label>
                                <span><?= $lang === 'es' ? 'Teléfono' : 'Phone' ?><?= $contactRequired['phone'] ? $requiredMark : '' ?></span>
                                <input name="phone" autocomplete="tel" maxlength="50" <?= $contactRequired['phone'] ? 'required' : '' ?>>
                                <?php if ($contactRequired['phone']): ?><small class="field-requirement-hint"><?= $lang === 'es' ? 'Número donde podamos contactarte.' : 'Best number to reach you.' ?></small><?php endif; ?>
                            </label>
                            <label>
                                <span><?= $lang === 'es' ? '¿Qué necesitas?' : 'What do you need?' ?><?= $contactRequired['service'] ? $requiredMark : '' ?></span>
                                <select class="public-select2" name="service" data-select2-placeholder="<?= h($lang === 'es' ? 'Selecciona una opción' : 'Select an option') ?>" <?= $contactRequired['service'] ? 'required' : '' ?>>
                                    <option value="" selected><?= $lang === 'es' ? 'Selecciona una opción' : 'Select an option' ?></option>
                                    <option value="<?= $lang === 'es' ? 'Información general' : 'General information' ?>"><?= $lang === 'es' ? 'Información general' : 'General information' ?></option>
                                    <?php foreach ($products as $solutionOption): ?>
                                        <option value="<?= h((string)$solutionOption['name']) ?>"><?= h((string)$solutionOption['name']) ?></option>
                                    <?php endforeach; ?>
                                    <option value="<?= $lang === 'es' ? 'Sitio web' : 'Website' ?>"><?= $lang === 'es' ? 'Sitio web' : 'Website' ?></option>
                                    <option value="<?= $lang === 'es' ? 'Software a la medida' : 'Custom software' ?>"><?= $lang === 'es' ? 'Software a la medida' : 'Custom software' ?></option>
                                    <option value="<?= $lang === 'es' ? 'Soporte' : 'Support' ?>"><?= $lang === 'es' ? 'Soporte' : 'Support' ?></option>
                                </select>
                                <?php if ($contactRequired['service']): ?><small class="field-requirement-hint"><?= $lang === 'es' ? 'Selecciona una opción.' : 'Select one option.' ?></small><?php endif; ?>
                            </label>
                            <label>
                                <span><?= $lang === 'es' ? '¿Cómo nos conociste?' : 'How did you hear about us?' ?><?= $contactRequired['referral'] ? $requiredMark : '' ?></span>
                                <select class="public-select2" name="referral_source" data-referral-source data-select2-placeholder="<?= h($lang === 'es' ? 'Selecciona una opción' : 'Select an option') ?>" <?= $contactRequired['referral'] ? 'required' : '' ?>>
                                    <option value="" selected><?= $lang === 'es' ? 'Selecciona una opción' : 'Select an option' ?></option>
                                    <?php foreach ($referralOptions as $referralOption): ?>
                                        <option value="<?= h($referralOption) ?>" data-is-other="<?= preg_match('/^(otro|other)$/iu', $referralOption) ? '1' : '0' ?>"><?= h($referralOption) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($contactRequired['referral']): ?><small class="field-requirement-hint"><?= $lang === 'es' ? 'Esto nos ayuda a saber cómo nos encontraste.' : 'This helps us know how you found us.' ?></small><?php endif; ?>
                            </label>
                            <label class="referral-other-field" data-referral-other hidden>
                                <span><?= $lang === 'es' ? 'Cuéntanos dónde nos encontraste' : 'Tell us where you found us' ?><span class="field-required" aria-hidden="true">*</span></span>
                                <input name="referral_details" data-referral-details maxlength="180" placeholder="<?= $lang === 'es' ? 'Ejemplo: un amigo, una empresa, un evento o un sitio específico' : 'Example: a friend, a company, an event or a specific website' ?>">
                                <small class="field-requirement-hint"><?= $lang === 'es' ? 'Completa este campo si elegiste “Otro”.' : 'Complete this field if you selected “Other”.' ?></small>
                            </label>
                        </div>
                        <label>
                            <span><?= $lang === 'es' ? 'Mensaje' : 'Message' ?><?= $contactRequired['message'] ? $requiredMark : '' ?></span>
                            <textarea name="message" rows="5" maxlength="5000" <?= $contactRequired['message'] ? 'required' : '' ?> data-meaningful-message data-min-meaningful-chars="<?= $messageMinChars ?>" data-min-meaningful-words="<?= $messageMinWords ?>" aria-describedby="contact-message-help" placeholder="<?= $lang === 'es' ? 'Ejemplo: Me interesa una de sus soluciones y necesito información, una demostración o una propuesta para mi empresa.' : 'Example: I am interested in one of your solutions and need information, a demo or a proposal for my company.' ?>"></textarea>
                            <small id="contact-message-help" class="field-requirement-hint message-help <?= $contactRequired['message'] ? '' : 'optional-hint' ?>"><?= $contactRequired['message'] ? ($lang === 'es' ? 'Describe lo que necesitas con al menos ' . $messageMinChars . ' caracteres útiles y ' . $messageMinWords . ' palabras. Un “Hola” o solo signos no cuentan como detalle suficiente.' : 'Describe what you need using at least ' . $messageMinChars . ' meaningful characters and ' . $messageMinWords . ' words. A simple “Hello” or only punctuation is not enough.') : ($lang === 'es' ? 'Si escribes un mensaje, incluye detalles reales de lo que necesitas.' : 'If you enter a message, include real details about what you need.') ?></small>
                        </label>
                        <?php if ($turnstileConfigured): ?>
                            <div class="contact-turnstile-wrap" data-turnstile-wrap>
                                <div class="contact-turnstile-widget"
                                     data-turnstile-container
                                     data-sitekey="<?= h($turnstileSiteKey) ?>"
                                     data-language="<?= $lang === 'es' ? 'es' : 'en' ?>"></div>
                                <small><?= $lang === 'es' ? 'Protección automática contra bots. Normalmente no tendrás que hacer nada.' : 'Automatic bot protection. You normally will not need to do anything.' ?></small>
                            </div>
                        <?php endif; ?>
                        <div class="contact-form-actions">
                            <button class="btn btn-primary" type="submit">
                                <?= $lang === 'es' ? 'Enviar consulta' : 'Send inquiry' ?> →
                            </button>
                            <span class="contact-form-status" data-contact-status aria-live="polite"></span>
                        </div>
                    </form>
                </div>

                <aside class="contact-info-card reveal">
                    <div>
                        <span class="eyebrow"><?= $lang === 'es' ? 'CONTACTO DIRECTO' : 'DIRECT CONTACT' ?></span>
                        <h3><?= $lang === 'es' ? 'También puedes hablar con nosotros ahora' : 'You can also talk with us now' ?></h3>
                        <p><?= $lang === 'es' ? 'Elige el canal que te resulte más cómodo para ventas, demostraciones o soporte.' : 'Choose the channel that works best for sales, demos or support.' ?></p>
                    </div>
                    <div class="contact-direct-actions">
                        <a class="btn btn-whatsapp" href="<?= h($whatsApp($lang === 'es' ? 'Hola, quiero información sobre sus soluciones.' : 'Hello, I would like information about your solutions.')) ?>">WhatsApp</a>
                        <a class="btn btn-primary" href="<?= h($whatsApp($lang === 'es' ? 'Hola, quiero solicitar una demostración.' : 'Hello, I would like to request a demo.')) ?>"><?= h($text('hero_primary')) ?></a>
                        <a class="btn btn-support" href="<?= h($whatsApp($lang === 'es' ? 'Hola, ya soy cliente y necesito soporte.' : 'Hello, I am already a customer and I need support.')) ?>"><?= h($text('support_cta')) ?> →</a>
                    </div>
                    <?php if (trim((string) ($settings['contact_map_query'] ?? '')) !== ''): ?>
                        <div class="contact-map-wrap">
                            <iframe
                                title="<?= $lang === 'es' ? 'Ubicación' : 'Location' ?>"
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                src="https://www.google.com/maps?q=<?= rawurlencode((string) $settings['contact_map_query']) ?>&output=embed"
                            ></iframe>
                        </div>
                    <?php endif; ?>
                    <div class="contact-meta">
                        <?php if (!empty($settings['phone'])): ?><span><b><?= $lang === 'es' ? 'Teléfono' : 'Phone' ?></b><?= h((string) $settings['phone']) ?></span><?php endif; ?>
                        <?php if (!empty($settings['email'])): ?><span><b>Email</b><?= h((string) $settings['email']) ?></span><?php endif; ?>
                        <?php if (!empty($settings['business_hours'])): ?><span><b><?= $lang === 'es' ? 'Horario' : 'Hours' ?></b><?= nl2br(h((string) $settings['business_hours'])) ?></span><?php endif; ?>
                    </div>
                    <div class="contact-info-points" aria-label="<?= $lang === 'es' ? 'Ventajas de contacto' : 'Contact advantages' ?>">
                        <span><?= $lang === 'es' ? 'Respuesta rápida en horario laboral.' : 'Fast response during business hours.' ?></span>
                        <span><?= $lang === 'es' ? 'Atención directa para ventas, demos y soporte.' : 'Direct assistance for sales, demos and support.' ?></span>
                        <span><?= $lang === 'es' ? 'Acompañamiento real por nuestro equipo.' : 'Real follow-up from our team.' ?></span>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="shell footer-grid">
        <div class="footer-brand">
            <img src="<?= h($publicCorporateLogo) ?>" alt="<?= h($companyName) ?>">
            <span class="footer-kicker">TECHNOLOGY · SOFTWARE · WEB</span>
            <p>
                <?= $lang === 'es'
                    ? 'Más que servicios, construimos soluciones.'
                    : 'More than services, we build solutions.' ?>
            </p>
            <?= $socialRender('footer') ?>
        </div>

        <div>
            <strong><?= $lang === 'es' ? 'Soluciones' : 'Solutions' ?></strong>
            <?php foreach (array_slice($products, 0, 4) as $footerSolution): ?>
                <a href="<?= h(trim((string)$footerSolution['cta_url']) !== '' ? $footerSolution['cta_url'] : '#solutions') ?>" <?= trim((string)$footerSolution['cta_url']) !== '' ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><?= h((string)$footerSolution['name']) ?></a>
            <?php endforeach; ?>
            <a href="#services"><?= $lang === 'es' ? 'Servicios' : 'Services' ?></a>
        </div>

        <div>
            <strong><?= $lang === 'es' ? 'Empresa' : 'Company' ?></strong>
            <a href="#projects"><?= $lang === 'es' ? 'Proyectos' : 'Projects' ?></a>
            <a href="#affiliate"><?= $lang === 'es' ? 'Afiliados' : 'Affiliates' ?></a>
            <a href="#contact"><?= $lang === 'es' ? 'Contacto' : 'Contact' ?></a>
        </div>

        <div>
            <strong><?= $lang === 'es' ? 'Soporte' : 'Support' ?></strong>
            <a
                href="<?= h($whatsApp(
                    $lang === 'es'
                        ? 'Hola, necesito soporte.'
                        : 'Hello, I need support.'
                )) ?>"
            >WhatsApp</a>
            <span><?= h($settings['phone'] ?? '+504 8913-6844') ?></span>
            <a href="#contact"><?= $lang === 'es' ? 'Enviar consulta' : 'Send inquiry' ?></a>
            <span>esmultiservicios.com</span>
        </div>
    </div>

    <div class="shell footer-bottom">
        <span>© <?= date('Y') ?> <?= h($companyName) ?></span>
        <span><?= $lang === 'es' ? 'Todos los derechos reservados.' : 'All rights reserved.' ?></span>
    </div>
</footer>

<?= $socialRender('left') ?>
<?= $socialRender('right') ?>

<?php
    $waPosition = in_array(($settings['whatsapp_position'] ?? 'left'), ['left','right'], true)
        ? (string)$settings['whatsapp_position'] : 'left';
    $widgetGap = max(8, min(40, (int)($settings['floating_widget_gap'] ?? 12)));
    $waShowDesktop = ($settings['whatsapp_show_desktop'] ?? '1') === '1';
    $waShowMobile = ($settings['whatsapp_show_mobile'] ?? '1') === '1';
    $managedFloating = ['left' => [], 'right' => []];

    if (($settings['whatsapp_enabled'] ?? '1') === '1') {
        $visibility = (!$waShowDesktop ? ' floating-hide-desktop' : '') . (!$waShowMobile ? ' floating-hide-mobile' : '');
        $message = trim((string)($settings['whatsapp_message'] ?? ''));
        if ($message === '') {
            $message = $lang === 'es'
                ? 'Hola, quiero información sobre sus soluciones.'
                : 'Hello, I would like information about your solutions.';
        }
        $managedFloating[$waPosition][] = [
            'order' => (int)($settings['whatsapp_order'] ?? 10),
            'html' => '<a class="floating-wa managed-floating-control'.$visibility.'" href="'.h($whatsApp($message)).'" aria-label="WhatsApp"><img src="assets/icons/whatsapp.svg" alt="" aria-hidden="true"></a>',
        ];
    }

    $externalWidgets = [];
    $externalRaw = trim((string)($settings['floating_widgets_json'] ?? ''));
    if ($externalRaw !== '') {
        $decoded = json_decode($externalRaw, true);
        if (is_array($decoded)) $externalWidgets = $decoded;
    }
    if (!$externalWidgets) {
        // Backward compatibility with packages that only had one external/NIVO widget.
        $externalWidgets[] = [
            'name' => $settings['floating_external_name'] ?? 'NIVO Web Chat',
            'enabled' => (($settings['floating_external_enabled'] ?? '0') === '1') ? 1 : 0,
            'kind' => trim((string)($settings['floating_external_snippet'] ?? '')) !== '' ? 'embed' : 'url',
            'position' => $settings['floating_external_position'] ?? 'right',
            'order' => (int)($settings['floating_external_order'] ?? 20),
            'show_desktop' => (($settings['floating_external_show_desktop'] ?? '1') === '1') ? 1 : 0,
            'show_mobile' => (($settings['floating_external_show_mobile'] ?? '1') === '1') ? 1 : 0,
            'url' => $settings['floating_external_url'] ?? '',
            'snippet' => $settings['floating_external_snippet'] ?? '',
        ];
    }

    foreach ($externalWidgets as $widget) {
        if (!is_array($widget) || empty($widget['enabled'])) continue;
        $name = trim((string)($widget['name'] ?? 'External widget')) ?: 'External widget';
        $position = in_array(($widget['position'] ?? 'right'), ['left','right'], true) ? (string)$widget['position'] : 'right';
        $kind = ($widget['kind'] ?? 'embed') === 'url' ? 'url' : 'embed';
        $snippet = trim((string)($widget['snippet'] ?? ''));
        $url = trim((string)($widget['url'] ?? ''));
        $showDesktop = !array_key_exists('show_desktop', $widget) || !empty($widget['show_desktop']);
        $showMobile = !array_key_exists('show_mobile', $widget) || !empty($widget['show_mobile']);
        $visibility = (!$showDesktop ? ' floating-hide-desktop' : '') . (!$showMobile ? ' floating-hide-mobile' : '');
        $content = '';
        if ($kind === 'embed' && $snippet !== '') {
            // Keep third-party installation code inert during PHP rendering and let the
            // public widget loader recreate its <script> nodes. Scripts inserted through
            // innerHTML/template cloning do not execute reliably across all browsers,
            // while explicit script recreation does. This also gives us load/error
            // diagnostics without exposing anything to visitors.
            $content = '<template class="external-widget-template" data-external-widget-template>'.
                $snippet.
                '</template><span class="external-widget-runtime-status" data-external-widget-status hidden></span>';
        } elseif ($kind === 'url' && $url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
            $content = '<iframe class="nivo-widget-frame" src="'.h($url).'" title="'.h($name).'" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>';
        }
        if ($content === '') continue;
        $managedFloating[$position][] = [
            'order' => max(0, (int)($widget['order'] ?? 20)),
            'html' => '<div class="external-floating-widget managed-floating-control'.$visibility.'" aria-label="'.h($name).'" data-external-widget="1" data-widget-name="'.h($name).'" data-widget-position="'.h($position).'">'.$content.'</div>',
        ];
    }

    foreach (['left', 'right'] as $floatingSide):
        if (!$managedFloating[$floatingSide]) continue;
        // Lower order stays closer to the bottom edge. Because the stack grows upward,
        // larger values render first and lower values render last.
        usort($managedFloating[$floatingSide], static fn(array $a, array $b): int => $b['order'] <=> $a['order']);
?>
    <div class="floating-widget-stack floating-widget-stack-<?= h($floatingSide) ?>"
         data-floating-stack="<?= h($floatingSide) ?>"
         style="--floating-widget-gap: <?= (int)$widgetGap ?>px">
        <?php foreach ($managedFloating[$floatingSide] as $managedWidget): ?>
            <div class="floating-widget-item"><?= $managedWidget['html'] /* trusted configured widget markup */ ?></div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

<script src="<?=h(versioned_asset('assets/vendor/jquery/jquery.min.js', 'assets/vendor/jquery/jquery.min.js'))?>"></script>
<script src="<?=h(versioned_asset('assets/vendor/select2/select2.local.js', 'assets/vendor/select2/select2.local.js'))?>"></script>
<script src="<?=h(versioned_asset('assets/vendor/sweetalert2/sweetalert2.all.min.js', 'assets/vendor/sweetalert2/sweetalert2.all.min.js'))?>"></script>
<script src="<?=h(versioned_asset('assets/vendor/show-notify/showNotify.js', 'assets/vendor/show-notify/showNotify.js'))?>"></script>
<script src="<?=h(versioned_asset('assets/action-icons.js', 'assets/action-icons.js'))?>"></script>
<script src="<?=h(versioned_asset('assets/es-site.js', 'assets/es-site.js'))?>"></script>
</body>
</html>
