<?php
require __DIR__ . '/bootstrap.php';
require_permission('seo.manage');

function seo_public_base_url(): string
{
    $configured = rtrim(site_url(), '/');
    if ($configured !== '') {
        return $configured;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
    $host = preg_replace('/[^A-Za-z0-9.\-:]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return ($https ? 'https://' : 'http://') . $host;
}

function seo_write_robots_file(string $baseUrl): void
{
    $content = "User-agent: *\n";
    $content .= "Allow: /\n";
    $content .= "Disallow: /admin/\n";
    $content .= "Disallow: /install/\n";
    $content .= "Disallow: /config/\n\n";
    $content .= 'Sitemap: ' . $baseUrl . "/sitemap.xml\n";

    if (@file_put_contents(ROOT_DIR . '/robots.txt', $content, LOCK_EX) === false) {
        throw new RuntimeException('No se pudo regenerar robots.txt. Verifica permisos de escritura en la raíz del sitio.');
    }
}

function seo_write_sitemap_file(string $baseUrl): void
{
    $root = htmlspecialchars($baseUrl . '/', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $english = htmlspecialchars($baseUrl . '/?lang=en', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $today = date('Y-m-d');

    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
  <url>
    <loc>{$root}</loc>
    <lastmod>{$today}</lastmod>
    <xhtml:link rel="alternate" hreflang="es" href="{$root}" />
    <xhtml:link rel="alternate" hreflang="en" href="{$english}" />
    <xhtml:link rel="alternate" hreflang="x-default" href="{$root}" />
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc>{$english}</loc>
    <lastmod>{$today}</lastmod>
    <xhtml:link rel="alternate" hreflang="es" href="{$root}" />
    <xhtml:link rel="alternate" hreflang="en" href="{$english}" />
    <xhtml:link rel="alternate" hreflang="x-default" href="{$root}" />
    <changefreq>weekly</changefreq>
    <priority>0.9</priority>
  </url>
</urlset>
XML;

    if (@file_put_contents(ROOT_DIR . '/sitemap.xml', $xml . "\n", LOCK_EX) === false) {
        throw new RuntimeException('No se pudo regenerar sitemap.xml. Verifica permisos de escritura en la raíz del sitio.');
    }
}

$set = settings();
$error = '';
$baseUrl = seo_public_base_url();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $action = (string)($_POST['seo_action'] ?? 'save');

        if ($action === 'regenerate_robots') {
            seo_write_robots_file($baseUrl);
            log_activity('seo_robots_regenerate', 'Regenerated robots.txt');
            flash('success', 'robots.txt regenerado correctamente.');
        } elseif ($action === 'regenerate_sitemap') {
            seo_write_sitemap_file($baseUrl);
            log_activity('seo_sitemap_regenerate', 'Regenerated sitemap.xml');
            flash('success', 'sitemap.xml regenerado correctamente.');
        } else {
            foreach (['seo_title', 'seo_description', 'seo_robots', 'seo_google_verification'] as $key) {
                save_setting($key, trim((string)($_POST[$key] ?? '')));
            }

            if (!empty($_FILES['seo_social_image']['name'])) {
                $path = upload_image($_FILES['seo_social_image'], 'seo', 'social', 8);
                save_setting('seo_social_image', $path);
                media_add($path, 'SEO social image');
            }

            if (isset($_POST['remove_social'])) {
                save_setting('seo_social_image', '');
            }

            log_activity('seo_update', 'Updated SEO settings');
            flash('success', 'Configuración SEO guardada correctamente.');
        }

        header('Location: seo.php');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$set = settings();
$seoTitle = trim((string)($set['seo_title'] ?? ''));
$seoDescription = trim((string)($set['seo_description'] ?? ''));
$seoRobots = trim((string)($set['seo_robots'] ?? 'index,follow')) ?: 'index,follow';
$googleVerification = trim((string)($set['seo_google_verification'] ?? ''));
$socialImage = trim((string)($set['seo_social_image'] ?? ''));
$siteLogo = trim((string)($set['site_logo_path'] ?? '')) ?: 'assets/brand/es-multiservicios-official.png';

$titleLen = mb_strlen($seoTitle);
$descriptionLen = mb_strlen($seoDescription);
$titleOk = $titleLen >= 50 && $titleLen <= 60;
$descriptionOk = $descriptionLen >= 120 && $descriptionLen <= 160;
$robotsIndex = $seoRobots === 'index,follow';
$socialOk = $socialImage !== '';
$verificationOk = $googleVerification !== '';
$readyCount = (int)$titleOk + (int)$descriptionOk + (int)$robotsIndex + (int)$socialOk + (int)$verificationOk;
$seoPercent = (int)round(($readyCount / 5) * 100);

$robotsPath = ROOT_DIR . '/robots.txt';
$sitemapPath = ROOT_DIR . '/sitemap.xml';
$robotsFileExists = is_file($robotsPath);
$sitemapExists = is_file($sitemapPath);
$robotsModified = $robotsFileExists ? date('d/m/Y H:i', (int)filemtime($robotsPath)) : 'No disponible';
$sitemapModified = $sitemapExists ? date('d/m/Y H:i', (int)filemtime($sitemapPath)) : 'No disponible';

$previewImage = $socialImage !== '' ? $socialImage : $siteLogo;
$previewTitle = $seoTitle !== '' ? $seoTitle : 'ES MULTISERVICIOS | Soluciones Digitales';
$previewDescription = $seoDescription !== '' ? $seoDescription : 'Sistemas web, facturación, soluciones para clínicas, sitios web y desarrollo a la medida.';

$pageTitle = 'SEO Manager';
$active = 'seo';
require __DIR__ . '/_header.php';
?>
<div class="page-heading seo-premium-heading">
    <div>
        <p class="eyebrow">SEO MANAGER</p>
        <h1>Visibilidad en buscadores</h1>
        <p class="muted">Controla cómo aparece ES MULTISERVICIOS en Google y cuando compartes el sitio en redes sociales.</p>
    </div>
    <div class="seo-score-card" aria-label="Estado SEO <?=$seoPercent?> por ciento">
        <div class="seo-score-ring" style="--seo-score:<?=$seoPercent?>"><span><?=$seoPercent?>%</span></div>
        <div><strong>Estado SEO</strong><small><?=$readyCount?> de 5 controles completos</small></div>
    </div>
</div>

<?php if ($error): ?><div class="alert error"><?=h($error)?></div><?php endif; ?>

<div class="seo-premium-grid">
    <section class="panel seo-main-card">
        <div class="seo-card-heading">
            <span class="seo-card-icon"><?=icon('search')?></span>
            <div>
                <h2>Configuración principal</h2>
                <p>Define cómo se presenta ES MULTISERVICIOS en buscadores y redes sociales.</p>
            </div>
        </div>

        <form method="post" action="seo.php" enctype="multipart/form-data" class="seo-premium-form">
            <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
            <input type="hidden" name="seo_action" value="save">

            <label>Título para navegador y Google
                <input name="seo_title" maxlength="70" value="<?=h($seoTitle)?>" data-seo-title>
                <small>Recomendado: 50–60 caracteres.</small>
            </label>

            <label>Meta descripción
                <textarea name="seo_description" maxlength="180" data-seo-description><?=h($seoDescription)?></textarea>
                <small>Resume qué ofrece ES MULTISERVICIOS y qué problema resuelve en 120–160 caracteres.</small>
            </label>

            <div class="seo-two-fields">
                <label>Google Site Verification
                    <input name="seo_google_verification" maxlength="255" value="<?=h($googleVerification)?>" placeholder="Código de Google Search Console">
                    <small>Pega solo el valor del atributo <code>content</code>.</small>
                </label>

                <label>Indexación
                    <select name="seo_robots">
                        <option value="index,follow" <?=$seoRobots === 'index,follow' ? 'selected' : ''?>>Indexar y seguir enlaces</option>
                        <option value="noindex,nofollow" <?=$seoRobots === 'noindex,nofollow' ? 'selected' : ''?>>No indexar ni seguir enlaces</option>
                    </select>
                    <small>Para producción usa “Indexar y seguir enlaces”.</small>
                </label>
            </div>

            <div class="seo-share-box">
                <h3>Imagen para compartir</h3>
                <p>Puede aparecer al compartir ES MULTISERVICIOS en WhatsApp, Facebook u otras plataformas.</p>
                <div class="upload-zone seo-premium-upload" data-upload-zone tabindex="0">
                    <div class="upload-icon"><?=icon('image')?></div>
                    <strong>Adjuntar imagen social</strong>
                    <small data-upload-name><?= $socialImage !== '' ? h(basename($socialImage)) : 'No attachments selected.' ?></small>
                    <span class="upload-zone-action">Elegir imagen</span>
                    <input type="file" name="seo_social_image" accept="image/jpeg,image/png,image/webp" data-empty-label="No attachments selected.">
                    <div class="upload-preview" data-upload-preview></div>
                </div>
                <?php if ($socialImage !== ''): ?>
                    <div class="seo-current-social">
                        <img src="../<?=h($socialImage)?>" alt="Imagen social actual">
                        <div><strong>Imagen social actual</strong><small><?=h($socialImage)?></small></div>
                        <label class="premium-check"><input type="checkbox" name="remove_social" value="1"><span>Eliminar al guardar</span></label>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-actions"><button type="submit">Guardar configuración SEO</button></div>
        </form>
    </section>

    <aside class="seo-right-stack">
        <section class="panel seo-google-card">
            <div class="seo-card-topline">
                <div><p class="eyebrow">VISTA PREVIA EN GOOGLE</p><small>Resultado aproximado en escritorio.</small></div>
                <span class="seo-device-badge">Google · Desktop</span>
            </div>
            <div class="seo-browser-preview">
                <div class="seo-browser-bar"><i></i><i></i><i></i><span>google.com/search?q=ES+MULTISERVICIOS</span></div>
                <div class="seo-google-result">
                    <div class="seo-google-site-row"><span class="seo-google-favicon">E</span><div><strong>ES MULTISERVICIOS</strong><small><?=h($baseUrl)?>/</small></div><b>⋮</b></div>
                    <h3 data-seo-preview-title><?=h($previewTitle)?></h3>
                    <p data-seo-preview-description><?=h($previewDescription)?></p>
                </div>
            </div>
            <div class="seo-preview-counters">
                <div><span>TÍTULO</span><strong data-seo-title-count><?=$titleLen?>/70</strong><small>Ideal: 50–60 caracteres</small></div>
                <div><span>DESCRIPCIÓN</span><strong data-seo-description-count><?=$descriptionLen?>/180</strong><small>Ideal: 120–160 caracteres</small></div>
            </div>
        </section>

        <section class="panel seo-review-card">
            <p class="eyebrow">SALUD SEO</p>
            <h2>Lista de revisión</h2>
            <p class="muted">Verifica los puntos básicos antes de publicar cambios.</p>
            <div class="seo-review-list">
                <div class="seo-review-row <?=$titleOk ? 'ok' : 'pending'?>"><span><?=icon($titleOk ? 'gear' : 'edit')?></span><strong>Título SEO definido</strong><em><?=$titleOk ? 'Correcto' : 'Pendiente'?></em></div>
                <div class="seo-review-row <?=$descriptionOk ? 'ok' : 'pending'?>"><span><?=icon($descriptionOk ? 'gear' : 'edit')?></span><strong>Descripción optimizada</strong><em><?=$descriptionOk ? 'Correcto' : 'Pendiente'?></em></div>
                <div class="seo-review-row <?=$robotsIndex ? 'ok' : 'pending'?>"><span><?=icon($robotsIndex ? 'gear' : 'eye')?></span><strong>Indexación habilitada</strong><em><?=$robotsIndex ? 'Correcto' : 'Pendiente'?></em></div>
                <div class="seo-review-row <?=$socialOk ? 'ok' : 'pending'?>"><span><?=icon($socialOk ? 'gear' : 'image')?></span><strong>Imagen social configurada</strong><em><?=$socialOk ? 'Correcto' : 'Pendiente'?></em></div>
                <div class="seo-review-row <?=$verificationOk ? 'ok' : 'pending'?>"><span><?=icon($verificationOk ? 'gear' : 'search')?></span><strong>Google Search Console</strong><em><?=$verificationOk ? 'Correcto' : 'Pendiente'?></em></div>
            </div>
        </section>
    </aside>
</div>

<div class="seo-bottom-grid">
    <section class="panel seo-social-preview-card">
        <p class="eyebrow">SOCIAL</p>
        <h2>Vista previa al compartir</h2>
        <p class="muted">Así puede verse el enlace cuando alguien comparte ES MULTISERVICIOS.</p>
        <div class="seo-share-preview">
            <div class="seo-share-image"><img src="../<?=h($previewImage)?>" alt="Vista previa social"></div>
            <div class="seo-share-copy">
                <small><?=h(strtoupper((string)parse_url($baseUrl, PHP_URL_HOST)))?></small>
                <strong data-seo-social-title><?=h($previewTitle)?></strong>
                <p data-seo-social-description><?=h($previewDescription)?></p>
            </div>
        </div>
    </section>

    <section class="panel seo-technical-card">
        <p class="eyebrow">SEO TÉCNICO</p>
        <h2>Robots y Sitemap</h2>
        <p class="muted">Archivos públicos para rastreo e indexación.</p>
        <div class="seo-technical-list">
            <div class="seo-technical-row <?=$robotsFileExists ? 'ok' : 'bad'?>">
                <span class="seo-technical-icon"><?=icon('shield')?></span>
                <div><small><?=$robotsFileExists ? 'LISTO' : 'FALTA'?></small><strong>robots.txt</strong><p>Permite rastreo público, bloquea administración/instalador y anuncia el sitemap.</p><a href="../robots.txt" target="_blank" rel="noopener"><?=h($baseUrl)?>/robots.txt · <?=$robotsModified?></a></div>
                <form method="post" action="seo.php"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="seo_action" value="regenerate_robots"><button class="secondary" type="submit">Regenerar</button></form>
            </div>
            <div class="seo-technical-row <?=$sitemapExists ? 'ok' : 'bad'?>">
                <span class="seo-technical-icon"><?=icon('share')?></span>
                <div><small><?=$sitemapExists ? 'LISTO' : 'FALTA'?></small><strong>sitemap.xml</strong><p>Publica la URL principal del sitio para facilitar su descubrimiento e indexación.</p><a href="../sitemap.xml" target="_blank" rel="noopener"><?=h($baseUrl)?>/sitemap.xml · <?=$sitemapModified?></a></div>
                <form method="post" action="seo.php"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="seo_action" value="regenerate_sitemap"><button class="secondary" type="submit">Regenerar</button></form>
            </div>
        </div>
        <div class="seo-technical-note"><?=icon('gear')?> <span><strong>Indexación:</strong> mantén “Indexar y seguir enlaces” para producción. Luego puedes registrar el sitemap en Google Search Console.</span></div>
    </section>
</div>

<script>
(() => {
    const title = document.querySelector('[data-seo-title]');
    const description = document.querySelector('[data-seo-description]');
    const titleCount = document.querySelector('[data-seo-title-count]');
    const descriptionCount = document.querySelector('[data-seo-description-count]');
    const previewTitle = document.querySelector('[data-seo-preview-title]');
    const previewDescription = document.querySelector('[data-seo-preview-description]');
    const socialTitle = document.querySelector('[data-seo-social-title]');
    const socialDescription = document.querySelector('[data-seo-social-description]');

    const sync = () => {
        const titleValue = title?.value.trim() || 'ES MULTISERVICIOS | Soluciones Digitales';
        const descriptionValue = description?.value.trim() || 'Sistemas web, facturación, soluciones para clínicas, sitios web y desarrollo a la medida.';
        if (titleCount) titleCount.textContent = (title?.value.length || 0) + '/70';
        if (descriptionCount) descriptionCount.textContent = (description?.value.length || 0) + '/180';
        if (previewTitle) previewTitle.textContent = titleValue;
        if (previewDescription) previewDescription.textContent = descriptionValue;
        if (socialTitle) socialTitle.textContent = titleValue;
        if (socialDescription) socialDescription.textContent = descriptionValue;
    };

    title?.addEventListener('input', sync);
    description?.addEventListener('input', sync);
    sync();
})();
</script>
<?php require __DIR__ . '/_footer.php';
