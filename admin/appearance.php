<?php
require __DIR__ . '/bootstrap.php';
require_permission('settings.manage');

$set = settings();
$error = '';

$fonts = [
    'Manrope' => 'Manrope',
    'DM Sans' => 'DM Sans',
    'Montserrat' => 'Montserrat',
    'Poppins' => 'Poppins',
    'Inter' => 'Inter',
    'Roboto' => 'Roboto',
    'Open Sans' => 'Open Sans',
    'Lato' => 'Lato',
    'Nunito' => 'Nunito',
    'Merriweather' => 'Merriweather',
    'Playfair Display' => 'Playfair Display',
    'System' => 'System',
];

$themePresets = [
    'core' => [
        'name' => 'Core Default',
        'description' => 'Balanced, professional and ready for your own brand.',
        'colors' => [
            'color_primary' => '#0f7777',
            'color_primary_dark' => '#0b5f60',
            'color_secondary' => '#f2d45c',
            'color_background' => '#f7f6f1',
            'color_surface' => '#ffffff',
            'color_text' => '#1c2a2a',
            'color_muted' => '#667170',
        ],
        'typography' => [
            'font_heading_family' => 'Manrope',
            'font_body_family' => 'DM Sans',
            'font_h1_desktop' => '40',
            'font_h1_tablet' => '34',
            'font_h1_mobile' => '30',
            'font_h2_desktop' => '32',
            'font_h2_mobile' => '28',
            'font_h3' => '22',
            'font_body_size' => '16',
            'font_small' => '14',
            'font_nav' => '15',
            'font_button' => '15',
        ],
    ],
    'modern' => [
        'name' => 'Modern',
        'description' => 'Crisp typography with a strong teal accent.',
        'colors' => [
            'color_primary' => '#126b68',
            'color_primary_dark' => '#0c4f4d',
            'color_secondary' => '#f0c94b',
            'color_background' => '#f6f8f7',
            'color_surface' => '#ffffff',
            'color_text' => '#17302f',
            'color_muted' => '#657572',
        ],
        'typography' => [
            'font_heading_family' => 'Poppins',
            'font_body_family' => 'Inter',
            'font_h1_desktop' => '44',
            'font_h1_tablet' => '36',
            'font_h1_mobile' => '31',
            'font_h2_desktop' => '34',
            'font_h2_mobile' => '28',
            'font_h3' => '22',
            'font_body_size' => '16',
            'font_small' => '14',
            'font_nav' => '15',
            'font_button' => '15',
        ],
    ],
    'corporate' => [
        'name' => 'Corporate',
        'description' => 'Structured, polished and easy to read.',
        'colors' => [
            'color_primary' => '#245f63',
            'color_primary_dark' => '#174549',
            'color_secondary' => '#d9b84a',
            'color_background' => '#f5f6f5',
            'color_surface' => '#ffffff',
            'color_text' => '#233033',
            'color_muted' => '#667477',
        ],
        'typography' => [
            'font_heading_family' => 'Montserrat',
            'font_body_family' => 'Open Sans',
            'font_h1_desktop' => '42',
            'font_h1_tablet' => '35',
            'font_h1_mobile' => '30',
            'font_h2_desktop' => '32',
            'font_h2_mobile' => '27',
            'font_h3' => '21',
            'font_body_size' => '16',
            'font_small' => '14',
            'font_nav' => '15',
            'font_button' => '15',
        ],
    ],
    'minimal' => [
        'name' => 'Minimal',
        'description' => 'Clean spacing and quieter visual hierarchy.',
        'colors' => [
            'color_primary' => '#2f625f',
            'color_primary_dark' => '#234947',
            'color_secondary' => '#d8bf62',
            'color_background' => '#faf9f6',
            'color_surface' => '#ffffff',
            'color_text' => '#252d2c',
            'color_muted' => '#6b7472',
        ],
        'typography' => [
            'font_heading_family' => 'Inter',
            'font_body_family' => 'Inter',
            'font_h1_desktop' => '38',
            'font_h1_tablet' => '32',
            'font_h1_mobile' => '28',
            'font_h2_desktop' => '30',
            'font_h2_mobile' => '26',
            'font_h3' => '20',
            'font_body_size' => '16',
            'font_small' => '14',
            'font_nav' => '14',
            'font_button' => '14',
        ],
    ],
];

function appearance_num(string $key, float $default, float $min, float $max): string
{
    $value = (float) ($_POST[$key] ?? $default);
    $value = max($min, min($max, $value));

    return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
}

function appearance_hex(string $key, string $default): string
{
    $value = trim((string) ($_POST[$key] ?? $default));

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
        return $default;
    }

    return strtolower($value);
}

function opt(string $current, string $value): string
{
    return $current === $value ? 'selected' : '';
}

function checked_setting(array $settings, string $key, string $default = '0'): string
{
    return ($settings[$key] ?? $default) === '1' ? 'checked' : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'typography') {
            foreach (['font_heading_family', 'font_body_family'] as $key) {
                $value = (string) ($_POST[$key] ?? '');

                if (!isset($fonts[$value])) {
                    $value = $key === 'font_heading_family' ? 'Manrope' : 'DM Sans';
                }

                save_setting($key, $value);
            }

            $rules = [
                'font_h1_desktop' => [40, 24, 80],
                'font_h1_tablet' => [34, 22, 70],
                'font_h1_mobile' => [30, 22, 58],
                'font_h2_desktop' => [32, 22, 64],
                'font_h2_mobile' => [28, 20, 52],
                'font_h3' => [22, 18, 42],
                'font_body_size' => [16, 14, 24],
                'font_small' => [14, 14, 20],
                'font_nav' => [15, 14, 20],
                'font_button' => [15, 14, 20],
                'line_height_body' => [1.7, 1.3, 2.2],
                'heading_weight' => [800, 400, 900],
                'body_weight' => [400, 300, 700],
            ];

            foreach ($rules as $key => $rule) {
                save_setting($key, appearance_num($key, $rule[0], $rule[1], $rule[2]));
            }

            log_activity('appearance_typography', 'Updated public website typography');
            flash('success', 'Typography saved.');
        } elseif ($action === 'theme') {
            $defaults = $themePresets['core']['colors'];

            foreach ($defaults as $key => $default) {
                save_setting($key, appearance_hex($key, $default));
            }

            save_setting('theme_radius', appearance_num('theme_radius', 24, 8, 36));
            save_setting('theme_shadow_strength', appearance_num('theme_shadow_strength', 10, 0, 24));
            save_setting('design_preset', 'custom');

            log_activity('appearance_theme', 'Updated website colors and theme settings');
            flash('success', 'Theme colors saved.');
        } elseif ($action === 'header') {
            save_setting('banner_enabled', isset($_POST['banner_enabled']) ? '1' : '0');
            save_setting(
                'banner_type',
                in_array($_POST['banner_type'] ?? 'image', ['image', 'video_upload', 'video_embed'], true)
                    ? (string) $_POST['banner_type']
                    : 'image'
            );
            save_setting('banner_embed_url', trim((string) ($_POST['banner_embed_url'] ?? '')));
            save_setting('banner_alt', trim((string) ($_POST['banner_alt'] ?? '')));
            save_setting(
                'banner_display',
                in_array($_POST['banner_display'] ?? 'full', ['full', 'contained'], true)
                    ? (string) $_POST['banner_display']
                    : 'full'
            );
            save_setting(
                'banner_height',
                in_array($_POST['banner_height'] ?? 'auto', ['auto', 'compact', 'medium', 'large'], true)
                    ? (string) $_POST['banner_height']
                    : 'auto'
            );
            save_setting(
                'nav_position',
                in_array($_POST['nav_position'] ?? 'below_banner', ['above_banner', 'below_banner'], true)
                    ? (string) $_POST['nav_position']
                    : 'below_banner'
            );
            save_setting(
                'nav_behavior',
                in_array($_POST['nav_behavior'] ?? 'sticky_after', ['normal', 'fixed', 'sticky_after'], true)
                    ? (string) $_POST['nav_behavior']
                    : 'sticky_after'
            );
            save_setting('nav_logo_enabled', isset($_POST['nav_logo_enabled']) ? '1' : '0');
            save_setting(
                'nav_alignment',
                in_array($_POST['nav_alignment'] ?? 'center', ['left', 'center', 'right'], true)
                    ? (string) $_POST['nav_alignment']
                    : 'center'
            );

            if (!empty($_FILES['banner_image']['name'])) {
                $path = upload_image($_FILES['banner_image'], 'appearance', 'banner', 15);
                save_setting('banner_image_path', $path);
                media_add($path, 'Website banner');
            }

            if (!empty($_FILES['banner_video']['name'])) {
                $path = upload_media_file($_FILES['banner_video'], 'appearance', 'banner-video', 60);
                $mime = detect_mime_type(ROOT_DIR . '/' . $path, true);

                if (!str_starts_with($mime, 'video/')) {
                    throw new RuntimeException('Banner video must be MP4 or WEBM.');
                }

                save_setting('banner_video_path', $path);
                media_add($path, 'Website banner video');
            }

            if (isset($_POST['remove_banner_image'])) {
                save_setting('banner_image_path', '');
            }

            if (isset($_POST['remove_banner_video'])) {
                save_setting('banner_video_path', '');
            }

            log_activity('appearance_header', 'Updated public banner and navigation');
            flash('success', 'Banner & navigation saved.');
        } elseif ($action === 'preset') {
            $preset = (string) ($_POST['preset'] ?? 'core');

            if (!isset($themePresets[$preset])) {
                $preset = 'core';
            }

            foreach ($themePresets[$preset]['typography'] as $key => $value) {
                save_setting($key, $value);
            }

            foreach ($themePresets[$preset]['colors'] as $key => $value) {
                save_setting($key, $value);
            }

            save_setting('design_preset', $preset);
            save_setting('theme_radius', '24');
            save_setting('theme_shadow_strength', '10');

            log_activity('appearance_preset', 'Applied design preset', ['preset' => $preset]);
            flash('success', 'Design preset applied.');
        } elseif ($action === 'reset') {
            $defaults = array_merge(
                $themePresets['core']['typography'],
                $themePresets['core']['colors'],
                [
                    'line_height_body' => '1.7',
                    'heading_weight' => '800',
                    'body_weight' => '400',
                    'theme_radius' => '24',
                    'theme_shadow_strength' => '10',
                    'banner_enabled' => '1',
                    'banner_type' => 'image',
                    'banner_image_path' => '',
                    'banner_embed_url' => '',
                    'banner_display' => 'full',
                    'banner_height' => 'auto',
                    'nav_position' => 'below_banner',
                    'nav_behavior' => 'sticky_after',
                    'nav_logo_enabled' => '0',
                    'nav_alignment' => 'center',
                    'design_preset' => 'core',
                ]
            );

            foreach ($defaults as $key => $value) {
                save_setting($key, (string) $value);
            }

            log_activity('appearance_reset', 'Reset appearance configuration');
            flash('success', "Appearance reset to the core defaults.");
        }

        header('Location: appearance.php');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$set = settings();
$pageTitle = 'Appearance';
$active = 'appearance';
require __DIR__ . '/_header.php';

$bannerImage = trim((string) ($set['banner_image_path'] ?? ''));
$currentBannerImage = $bannerImage;
$currentBannerVideo = trim((string) ($set['banner_video_path'] ?? ''));
$currentEmbed = trim((string) ($set['banner_embed_url'] ?? ''));
$currentPreset = (string) ($set['design_preset'] ?? 'core');

$previewColors = [
    'primary' => $set['color_primary'] ?? '#0f7777',
    'secondary' => $set['color_secondary'] ?? '#f2d45c',
    'background' => $set['color_background'] ?? '#f7f6f1',
    'surface' => $set['color_surface'] ?? '#ffffff',
    'text' => $set['color_text'] ?? '#1c2a2a',
    'muted' => $set['color_muted'] ?? '#667170',
];
?>

<div class="page-heading">
    <div>
        <p class="eyebrow">APPEARANCE</p>
        <h1>Design the website without touching code</h1>
        <p class="muted">
            Change colors, typography, banner and navigation from one clean workspace.
            Your content, forms, services and data remain untouched.
        </p>
    </div>
    <div class="heading-actions">
        <a class="button secondary" href="../?preview=1" target="_blank" rel="noopener">Preview website</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert error"><?= h($error) ?></div>
<?php endif; ?>

<nav class="appearance-jump" aria-label="Appearance sections">
    <a href="#themes">Themes & colors</a>
    <a href="#typography">Typography</a>
    <a href="#header-banner">Header & banner</a>
    <a href="#navigation">Navigation</a>
</nav>

<div class="appearance-layout">
    <div class="appearance-main">
        <section class="panel animate-in" id="themes">
            <div class="panel-heading">
                <div class="panel-icon">◐</div>
                <div>
                    <h2>Themes & colors</h2>
                    <p>Start with a preset or customize the brand palette. Changes never delete content.</p>
                </div>
            </div>

            <form method="post" class="appearance-form" data-unsaved-form>
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="preset">

                <div class="preset-grid">
                    <?php foreach ($themePresets as $key => $preset): ?>
                        <label class="theme-preset-card">
                            <input
                                type="radio"
                                name="preset"
                                value="<?= h($key) ?>"
                                <?= $currentPreset === $key ? 'checked' : '' ?>
                            >
                            <span>
                                <span class="theme-swatch-row" aria-hidden="true">
                                    <i style="background:<?= h($preset['colors']['color_primary']) ?>"></i>
                                    <i style="background:<?= h($preset['colors']['color_secondary']) ?>"></i>
                                    <i style="background:<?= h($preset['colors']['color_background']) ?>"></i>
                                </span>
                                <b><?= h($preset['name']) ?></b>
                                <small><?= h($preset['description']) ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="form-actions">
                    <button type="submit">Apply selected preset</button>
                </div>
            </form>

            <hr class="panel-divider">

            <form method="post" class="appearance-form" data-unsaved-form>
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="theme">

                <div class="section-heading compact">
                    <div>
                        <h3>Custom brand colors</h3>
                        <p class="muted">Use the color picker or type a HEX value such as #0f7777.</p>
                    </div>
                </div>

                <div class="color-grid">
                    <?php
                    $colorFields = [
                        'color_primary' => ['Primary', '#0f7777'],
                        'color_primary_dark' => ['Primary dark', '#0b5f60'],
                        'color_secondary' => ['Accent', '#f2d45c'],
                        'color_background' => ['Page background', '#f7f6f1'],
                        'color_surface' => ['Cards / surfaces', '#ffffff'],
                        'color_text' => ['Main text', '#1c2a2a'],
                        'color_muted' => ['Secondary text', '#667170'],
                    ];
                    ?>

                    <?php foreach ($colorFields as $key => [$label, $default]): ?>
                        <?php $value = $set[$key] ?? $default; ?>
                        <label class="color-control">
                            <span><?= h($label) ?></span>
                            <span class="color-input-wrap">
                                <input
                                    type="color"
                                    value="<?= h($value) ?>"
                                    data-color-picker
                                    aria-label="<?= h($label) ?> color picker"
                                >
                                <input
                                    type="text"
                                    name="<?= h($key) ?>"
                                    value="<?= h($value) ?>"
                                    pattern="#[0-9A-Fa-f]{6}"
                                    maxlength="7"
                                    data-color-text
                                >
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="two-col compact-fields">
                    <label>
                        Corner roundness
                        <input
                            type="number"
                            name="theme_radius"
                            min="8"
                            max="36"
                            value="<?= h($set['theme_radius'] ?? '24') ?>"
                        >
                        <small>8–36 px</small>
                    </label>
                    <label>
                        Shadow strength
                        <input
                            type="number"
                            name="theme_shadow_strength"
                            min="0"
                            max="24"
                            value="<?= h($set['theme_shadow_strength'] ?? '10') ?>"
                        >
                        <small>0 = flat · 24 = stronger depth</small>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit">Save theme colors</button>
                </div>
            </form>
        </section>

        <section class="panel animate-in" id="typography">
            <div class="panel-heading">
                <div class="panel-icon">Aa</div>
                <div>
                    <h2>Typography</h2>
                    <p>Control titles, paragraphs, buttons and menu text with safe readable limits.</p>
                </div>
            </div>

            <form method="post" class="appearance-form" data-appearance-form data-unsaved-form>
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="typography">

                <div class="two-col">
                    <label>
                        Heading font
                        <select name="font_heading_family">
                            <?php foreach ($fonts as $key => $label): ?>
                                <option
                                    value="<?= h($key) ?>"
                                    <?= opt($set['font_heading_family'] ?? 'Manrope', $key) ?>
                                >
                                    <?= h($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        Body font
                        <select name="font_body_family">
                            <?php foreach ($fonts as $key => $label): ?>
                                <option
                                    value="<?= h($key) ?>"
                                    <?= opt($set['font_body_family'] ?? 'DM Sans', $key) ?>
                                >
                                    <?= h($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="appearance-size-grid">
                    <label>
                        Main title · Desktop
                        <input type="number" name="font_h1_desktop" min="24" max="80" value="<?= h($set['font_h1_desktop'] ?? '40') ?>">
                        <small>Recommended 36–44 px</small>
                    </label>
                    <label>
                        Main title · Tablet
                        <input type="number" name="font_h1_tablet" min="22" max="70" value="<?= h($set['font_h1_tablet'] ?? '34') ?>">
                        <small>Recommended 30–38 px</small>
                    </label>
                    <label>
                        Main title · Mobile
                        <input type="number" name="font_h1_mobile" min="22" max="58" value="<?= h($set['font_h1_mobile'] ?? '30') ?>">
                        <small>Recommended 26–32 px</small>
                    </label>
                    <label>
                        Section titles
                        <input type="number" name="font_h2_desktop" min="22" max="64" value="<?= h($set['font_h2_desktop'] ?? '32') ?>">
                        <small>Desktop px</small>
                    </label>
                    <label>
                        Section titles · Mobile
                        <input type="number" name="font_h2_mobile" min="20" max="52" value="<?= h($set['font_h2_mobile'] ?? '28') ?>">
                        <small>Mobile px</small>
                    </label>
                    <label>
                        Subtitles / H3
                        <input type="number" name="font_h3" min="18" max="42" value="<?= h($set['font_h3'] ?? '22') ?>">
                        <small>18–42 px</small>
                    </label>
                    <label>
                        Paragraphs
                        <input type="number" name="font_body_size" min="14" max="24" value="<?= h($set['font_body_size'] ?? '16') ?>">
                        <small>Default 16 px</small>
                    </label>
                    <label>
                        Small text
                        <input type="number" name="font_small" min="14" max="20" value="<?= h($set['font_small'] ?? '14') ?>">
                        <small>Minimum 14 px for readability</small>
                    </label>
                    <label>
                        Navigation
                        <input type="number" name="font_nav" min="14" max="20" value="<?= h($set['font_nav'] ?? '15') ?>">
                        <small>14–20 px</small>
                    </label>
                    <label>
                        Buttons
                        <input type="number" name="font_button" min="14" max="20" value="<?= h($set['font_button'] ?? '15') ?>">
                        <small>14–20 px</small>
                    </label>
                    <label>
                        Heading weight
                        <input type="number" name="heading_weight" step="100" min="400" max="900" value="<?= h($set['heading_weight'] ?? '800') ?>">
                        <small>400–900</small>
                    </label>
                    <label>
                        Body weight
                        <input type="number" name="body_weight" step="100" min="300" max="700" value="<?= h($set['body_weight'] ?? '400') ?>">
                        <small>300–700</small>
                    </label>
                </div>

                <label>
                    Paragraph line height
                    <input type="number" name="line_height_body" step="0.05" min="1.3" max="2.2" value="<?= h($set['line_height_body'] ?? '1.7') ?>">
                </label>

                <div class="form-actions">
                    <button type="submit">Save typography</button>
                </div>
            </form>
        </section>

        <section class="panel animate-in" id="header-banner">
            <div class="panel-heading">
                <div class="panel-icon">▣</div>
                <div>
                    <h2>Header banner</h2>
                    <p>Use an image, an uploaded video, or a YouTube/Vimeo video. Current media is shown below.</p>
                </div>
            </div>

            <form method="post" enctype="multipart/form-data" class="appearance-form" data-unsaved-form>
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="header">

                <label class="premium-switch">
                    <input type="checkbox" name="banner_enabled" value="1" <?= checked_setting($set, 'banner_enabled', '1') ?>>
                    <span class="switch-ui"></span>
                    <span>
                        <b>Show website banner</b>
                        <small>Turn the banner off without deleting the uploaded file.</small>
                    </span>
                </label>

                <div class="current-media-card">
                    <div class="current-media-head">
                        <div>
                            <strong>Current banner image</strong>
                            <small><?= $bannerImage !== '' ? 'Uploaded image' : 'Default banner image' ?></small>
                        </div>
                        <a class="button secondary small" href="../<?= h($currentBannerImage) ?>" target="_blank" rel="noopener">Open</a>
                    </div>
                    <button
                        type="button"
                        class="current-media-preview"
                        data-preview-src="../<?= h($currentBannerImage) ?>"
                        data-preview-caption="Current website banner"
                    >
                        <img src="../<?= h($currentBannerImage) ?>" alt="Current website banner preview">
                        <span>Click to preview</span>
                    </button>
                </div>

                <?php if ($currentBannerVideo !== ''): ?>
                    <div class="current-media-card">
                        <div class="current-media-head">
                            <div>
                                <strong>Current uploaded video</strong>
                                <small><?= h($currentBannerVideo) ?></small>
                            </div>
                        </div>
                        <video class="current-video-preview" controls preload="metadata" src="../<?= h($currentBannerVideo) ?>"></video>
                    </div>
                <?php endif; ?>

                <?php if ($currentEmbed !== ''): ?>
                    <div class="current-media-card compact-current-media">
                        <div>
                            <strong>Current embedded video</strong>
                            <small><?= h($currentEmbed) ?></small>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="three-col">
                    <label>
                        Banner type
                        <select name="banner_type">
                            <option value="image" <?= opt($set['banner_type'] ?? 'image', 'image') ?>>Image</option>
                            <option value="video_upload" <?= opt($set['banner_type'] ?? 'image', 'video_upload') ?>>Uploaded video</option>
                            <option value="video_embed" <?= opt($set['banner_type'] ?? 'image', 'video_embed') ?>>YouTube / Vimeo</option>
                        </select>
                    </label>
                    <label>
                        Display width
                        <select name="banner_display">
                            <option value="full" <?= opt($set['banner_display'] ?? 'full', 'full') ?>>Full width</option>
                            <option value="contained" <?= opt($set['banner_display'] ?? 'full', 'contained') ?>>Contained</option>
                        </select>
                    </label>
                    <label>
                        Banner height
                        <select name="banner_height">
                            <option value="auto" <?= opt($set['banner_height'] ?? 'auto', 'auto') ?>>Use image ratio</option>
                            <option value="compact" <?= opt($set['banner_height'] ?? 'auto', 'compact') ?>>Compact</option>
                            <option value="medium" <?= opt($set['banner_height'] ?? 'auto', 'medium') ?>>Medium</option>
                            <option value="large" <?= opt($set['banner_height'] ?? 'auto', 'large') ?>>Large</option>
                        </select>
                    </label>
                </div>

                <div class="two-col media-upload-grid">
                    <div>
                        <span class="field-title">Replace banner image</span>
                        <div class="upload-zone" data-upload-zone tabindex="0">
                            <div class="upload-icon">＋</div>
                            <strong>Banner image</strong>
                            <small data-upload-name>Drag & drop, paste, or choose JPG / PNG / WEBP</small>
                            <input type="file" name="banner_image" accept="image/jpeg,image/png,image/webp">
                            <div class="upload-preview" data-upload-preview></div>
                        </div>
                        <?php if ($bannerImage !== ''): ?>
                            <label class="premium-check compact-check">
                                <input type="checkbox" name="remove_banner_image" value="1">
                                <span class="check-ui"></span>
                                <span>Remove uploaded banner image</span>
                            </label>
                        <?php endif; ?>
                    </div>

                    <div>
                        <span class="field-title">Replace banner video</span>
                        <div class="upload-zone" data-upload-zone tabindex="0">
                            <div class="upload-icon">▶</div>
                            <strong>Banner video</strong>
                            <small data-upload-name>Choose MP4 or WEBM · up to 60 MB</small>
                            <input type="file" name="banner_video" accept="video/mp4,video/webm">
                            <div class="upload-preview" data-upload-preview></div>
                        </div>
                        <?php if ($currentBannerVideo !== ''): ?>
                            <label class="premium-check compact-check">
                                <input type="checkbox" name="remove_banner_video" value="1">
                                <span class="check-ui"></span>
                                <span>Remove uploaded banner video</span>
                            </label>
                        <?php endif; ?>
                    </div>
                </div>

                <label>
                    YouTube or Vimeo URL
                    <input
                        type="url"
                        name="banner_embed_url"
                        value="<?= h($set['banner_embed_url'] ?? '') ?>"
                        placeholder="https://www.youtube.com/watch?v=..."
                    >
                </label>

                <label>
                    Banner alternative text
                    <input
                        name="banner_alt"
                        value="<?= h($set['banner_alt'] ?? "Website banner") ?>"
                        placeholder="Describe the banner for accessibility"
                    >
                </label>

                <div class="nav-config-card" id="navigation">
                    <div class="section-heading compact">
                        <div>
                            <h3>Navigation</h3>
                            <p class="muted">Choose where the menu appears and how it behaves while scrolling.</p>
                        </div>
                    </div>

                    <div class="three-col">
                        <label>
                            Menu position
                            <select name="nav_position">
                                <option value="above_banner" <?= opt($set['nav_position'] ?? 'below_banner', 'above_banner') ?>>Above banner</option>
                                <option value="below_banner" <?= opt($set['nav_position'] ?? 'below_banner', 'below_banner') ?>>Below banner</option>
                            </select>
                        </label>
                        <label>
                            Scroll behavior
                            <select name="nav_behavior">
                                <option value="normal" <?= opt($set['nav_behavior'] ?? 'sticky_after', 'normal') ?>>Normal</option>
                                <option value="fixed" <?= opt($set['nav_behavior'] ?? 'sticky_after', 'fixed') ?>>Always fixed</option>
                                <option value="sticky_after" <?= opt($set['nav_behavior'] ?? 'sticky_after', 'sticky_after') ?>>Sticky after reaching menu</option>
                            </select>
                        </label>
                        <label>
                            Menu alignment
                            <select name="nav_alignment">
                                <option value="left" <?= opt($set['nav_alignment'] ?? 'center', 'left') ?>>Left</option>
                                <option value="center" <?= opt($set['nav_alignment'] ?? 'center', 'center') ?>>Center</option>
                                <option value="right" <?= opt($set['nav_alignment'] ?? 'center', 'right') ?>>Right</option>
                            </select>
                        </label>
                    </div>

                    <label class="premium-switch">
                        <input type="checkbox" name="nav_logo_enabled" value="1" <?= checked_setting($set, 'nav_logo_enabled') ?>>
                        <span class="switch-ui"></span>
                        <span>
                            <b>Show logo inside navigation</b>
                            <small>Useful when the banner does not already contain the brand logo.</small>
                        </span>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit">Save banner & navigation</button>
                </div>
            </form>
        </section>

        <section class="panel appearance-reset-card animate-in">
            <div>
                <h2>Restore safe defaults</h2>
                <p class="muted">Resets appearance settings only. Website content and uploaded media are not deleted.</p>
            </div>
            <form method="post" data-swal-confirm="Reset appearance settings?" data-swal-text="Content and uploaded files will stay intact.">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="reset">
                <button class="button danger-lite" type="submit">Reset appearance</button>
            </form>
        </section>
    </div>

    <aside class="appearance-preview">
        <section class="panel preview-side-panel">
            <div class="panel-heading compact-heading">
                <div class="panel-icon">◎</div>
                <div>
                    <h2>Quick preview</h2>
                    <p>Shows the current saved visual style.</p>
                </div>
            </div>

            <div
                class="mini-browser"
                style="
                    --mini-primary:<?= h($previewColors['primary']) ?>;
                    --mini-accent:<?= h($previewColors['secondary']) ?>;
                    --mini-bg:<?= h($previewColors['background']) ?>;
                    --mini-surface:<?= h($previewColors['surface']) ?>;
                    --mini-text:<?= h($previewColors['text']) ?>;
                    --mini-muted:<?= h($previewColors['muted']) ?>;
                "
            >
                <div class="mini-banner">
                    <img src="../<?= h($currentBannerImage) ?>" alt="Banner preview">
                </div>
                <div class="mini-nav">HOME · SERVICES · GALLERY · ABOUT · CONTACT</div>
                <div class="mini-content">
                    <span>YOUR BUSINESS</span>
                    <h2>More Than Repairs and Maintenance.</h2>
                    <p>Your home deserves clear, reliable and professional service.</p>
                    <button type="button">Request Estimate</button>
                </div>
            </div>

            <div class="preview-actions">
                <a class="button secondary" href="../?preview=1" target="_blank" rel="noopener">Open full preview</a>
                <a class="button ghost" href="media.php">Open Media Library</a>
            </div>

            <div class="appearance-help">
                <strong>Easy rule</strong>
                <p>Change one group at a time, preview it, and publish only when you are happy with the result.</p>
            </div>
        </section>
    </aside>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
