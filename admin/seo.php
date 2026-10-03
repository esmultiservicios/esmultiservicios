<?php
require __DIR__.'/bootstrap.php';
require_permission('seo.manage');
$set=settings();
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    try {
        foreach(['seo_title','seo_description','seo_robots'] as $k) save_setting($k,trim((string)($_POST[$k]??'')));
        if(!empty($_FILES['seo_social_image']['name'])) {
            $p=upload_image($_FILES['seo_social_image'],'seo','social',8);
            save_setting('seo_social_image',$p);
            media_add($p,'SEO social image');
        }
        if(isset($_POST['remove_social'])) save_setting('seo_social_image','');
        log_activity('seo_update','Updated SEO settings');
        flash('success','SEO settings saved.');
        header('Location: seo.php');
        exit;
    } catch(Throwable $e) {
        $error=$e->getMessage();
    }
}
$set=settings();
$seoTitle=trim((string)($set['seo_title']??''));
$seoDescription=trim((string)($set['seo_description']??''));
$seoRobots=(string)($set['seo_robots']??'index,follow');
$socialImage=trim((string)($set['seo_social_image']??''));
$titleLen=mb_strlen($seoTitle);
$descriptionLen=mb_strlen($seoDescription);
$titleOk=$titleLen>=30 && $titleLen<=60;
$descriptionOk=$descriptionLen>=120 && $descriptionLen<=160;
$robotsIndex=$seoRobots==='index,follow';
$sitemapExists=is_file(ROOT_DIR.'/sitemap.xml');
$robotsFileExists=is_file(ROOT_DIR.'/robots.txt');
$pageTitle='SEO Manager';
$active='seo';
require __DIR__.'/_header.php';
?>
<div class="page-heading seo-page-heading">
    <div>
        <p class="eyebrow">SEO MANAGER</p>
        <h1>Search visibility & social sharing</h1>
        <p class="muted">Control what Google can index, how your result is presented and which image appears when the website is shared.</p>
    </div>
    <a class="button secondary" href="../sitemap.xml" target="_blank" rel="noopener">View sitemap</a>
</div>

<?php if($error): ?><div class="alert error"><?=h($error)?></div><?php endif; ?>

<div class="seo-health-grid">
    <article class="seo-health-card <?= $titleOk ? 'is-ready' : 'needs-attention' ?>">
        <span class="seo-health-icon"><?=icon($titleOk?'approval':'edit')?></span>
        <div><small>SEARCH TITLE</small><strong><?= $titleOk ? 'Good length' : 'Review length' ?></strong><p><?= $titleLen ?> / 60 characters recommended.</p></div>
    </article>
    <article class="seo-health-card <?= $descriptionOk ? 'is-ready' : 'needs-attention' ?>">
        <span class="seo-health-icon"><?=icon($descriptionOk?'approval':'edit')?></span>
        <div><small>META DESCRIPTION</small><strong><?= $descriptionOk ? 'Good length' : 'Review length' ?></strong><p><?= $descriptionLen ?> / 160 characters recommended.</p></div>
    </article>
    <article class="seo-health-card <?= $robotsIndex ? 'is-ready' : 'is-warning' ?>">
        <span class="seo-health-icon"><?=icon('eye')?></span>
        <div><small>INDEXING</small><strong><?= $robotsIndex ? 'Public & indexable' : 'Hidden from search' ?></strong><p><?= $robotsIndex ? 'Search engines may index this website.' : 'Pages request noindex and nofollow.' ?></p></div>
    </article>
    <article class="seo-health-card <?= ($sitemapExists && $robotsFileExists) ? 'is-ready' : 'needs-attention' ?>">
        <span class="seo-health-icon"><?=icon('gear')?></span>
        <div><small>TECHNICAL FILES</small><strong><?= ($sitemapExists && $robotsFileExists) ? 'Core files available' : 'File review needed' ?></strong><p>sitemap.xml · robots.txt</p></div>
    </article>
</div>

<div class="seo-workspace">
    <section class="panel seo-editor-panel">
        <div class="seo-section-title">
            <span><?=icon('search')?></span>
            <div><p class="eyebrow">SEARCH RESULT</p><h2>Google appearance</h2><p class="muted">Write a concise title and description. The counters help keep the content inside common search-result ranges.</p></div>
        </div>
        <form method="post" enctype="multipart/form-data" class="seo-form">
            <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
            <label>Browser / search title
                <input name="seo_title" maxlength="70" value="<?=h($seoTitle)?>" data-seo-title>
                <small class="field-help"><span>Recommended: 30–60 characters.</span><b data-seo-title-count><?=$titleLen?> / 60</b></small>
            </label>
            <label>Meta description
                <textarea name="seo_description" maxlength="180" data-seo-description><?=h($seoDescription)?></textarea>
                <small class="field-help"><span>Recommended: 120–160 characters. Explain clearly what the company offers.</span><b data-seo-description-count><?=$descriptionLen?> / 160</b></small>
            </label>

            <div class="seo-indexing-card">
                <div class="seo-section-title compact">
                    <span><?=icon('eye')?></span>
                    <div><p class="eyebrow">ROBOTS DIRECTIVE</p><h3>Search-engine access</h3><p class="muted">Choose whether public pages should be discoverable in search engines.</p></div>
                </div>
                <label>Robots
                    <select name="seo_robots">
                        <option value="index,follow" <?=$seoRobots==='index,follow'?'selected':''?>>Index & follow — public website</option>
                        <option value="noindex,nofollow" <?=$seoRobots==='noindex,nofollow'?'selected':''?>>Noindex & nofollow — hide from search engines</option>
                    </select>
                    <small class="field-help"><span><b>Index & follow:</b> normal production setting. <b>Noindex & nofollow:</b> use only when you intentionally want search engines to ignore the website.</span></small>
                </label>
            </div>

            <div class="seo-social-card">
                <div class="seo-section-title compact">
                    <span><?=icon('share')?></span>
                    <div><p class="eyebrow">SOCIAL PREVIEW</p><h3>Sharing image</h3><p class="muted">Used by social networks and messaging apps when the website URL is shared. Recommended landscape image: 1200 × 630 px.</p></div>
                </div>
                <div class="upload-zone seo-social-upload" data-upload-zone tabindex="0">
                    <div class="upload-icon"><?=icon('image')?></div>
                    <strong>Social sharing image</strong>
                    <small data-upload-name>Drop, paste or choose JPG, PNG or WEBP</small>
                    <span class="upload-zone-action">Choose image</span>
                    <input type="file" name="seo_social_image" accept="image/jpeg,image/png,image/webp" data-empty-label="Drop, paste or choose JPG, PNG or WEBP">
                    <div class="upload-preview" data-upload-preview></div>
                </div>
                <?php if($socialImage): ?>
                    <div class="seo-current-image">
                        <img src="../<?=h($socialImage)?>" alt="Current social sharing image">
                        <div><strong>Current social image</strong><small><?=h($socialImage)?></small></div>
                        <label class="compact-check"><input type="checkbox" name="remove_social"> Remove when saving</label>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-actions"><button type="submit">Save SEO settings</button></div>
        </form>
    </section>

    <aside class="seo-preview-column">
        <section class="search-preview premium-search-preview">
            <span>Google-style preview</span>
            <div class="search-domain">esmultiservicios.com</div>
            <h3 data-seo-preview-title><?=h($seoTitle?:'ES MULTISERVICIOS | Digital solutions')?></h3>
            <p data-seo-preview-description><?=h($seoDescription?:'Professional digital solutions tailored to your business.')?></p>
        </section>
        <section class="panel seo-files-panel">
            <div class="seo-section-title compact">
                <span><?=icon('gear')?></span>
                <div><p class="eyebrow">TECHNICAL SEO</p><h3>Required public files</h3><p class="muted">These files help crawlers understand and discover the site.</p></div>
            </div>
            <div class="seo-file-list">
                <div class="seo-file-row"><span class="seo-file-state <?=$sitemapExists?'ok':'bad'?>"></span><div><strong>sitemap.xml</strong><small><?=$sitemapExists?'Available at the website root.':'Missing from the website root.'?></small></div><a href="../sitemap.xml" target="_blank" rel="noopener">Open</a></div>
                <div class="seo-file-row"><span class="seo-file-state <?=$robotsFileExists?'ok':'bad'?>"></span><div><strong>robots.txt</strong><small><?=$robotsFileExists?'Available and points crawlers to the sitemap.':'Missing from the website root.'?></small></div><a href="../robots.txt" target="_blank" rel="noopener">Open</a></div>
            </div>
            <div class="seo-note"><strong>Important</strong><p>The robots selector above controls the page-level indexing directive. robots.txt controls crawler access and sitemap discovery; they serve different purposes.</p></div>
        </section>
    </aside>
</div>
<script>
(() => {
    const title=document.querySelector('[data-seo-title]');
    const description=document.querySelector('[data-seo-description]');
    const titleCount=document.querySelector('[data-seo-title-count]');
    const descriptionCount=document.querySelector('[data-seo-description-count]');
    const previewTitle=document.querySelector('[data-seo-preview-title]');
    const previewDescription=document.querySelector('[data-seo-preview-description]');
    const sync=()=>{
        if(titleCount) titleCount.textContent=(title?.value.length||0)+' / 60';
        if(descriptionCount) descriptionCount.textContent=(description?.value.length||0)+' / 160';
        if(previewTitle) previewTitle.textContent=title?.value.trim()||'ES MULTISERVICIOS | Digital solutions';
        if(previewDescription) previewDescription.textContent=description?.value.trim()||'Professional digital solutions tailored to your business.';
    };
    title?.addEventListener('input',sync);
    description?.addEventListener('input',sync);
    sync();
})();
</script>
<?php require __DIR__.'/_footer.php';
