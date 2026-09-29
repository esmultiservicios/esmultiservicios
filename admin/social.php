<?php
require __DIR__.'/bootstrap.php';
require_permission('social.manage');

$platforms = [
    'instagram' => 'Instagram',
    'facebook' => 'Facebook',
    'tiktok' => 'TikTok',
    'youtube' => 'YouTube',
    'linkedin' => 'LinkedIn',
];
$allowedSizes = ['small','medium','large'];
$allowedStyles = ['icon','icon_name'];
$allowedLocations = ['footer','hero','floating_left','floating_right','footer_floating_left','footer_floating_right'];
$set = settings();
$error = '';

function social_default_rows(array $set): array {
    $legacy = static function(string $key) use ($set): string {
        $value = trim((string)($set[$key] ?? ''));
        return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
    };
    return [
        ['enabled'=>false,'platform'=>'instagram','url'=>'','sort_order'=>1],
        ['enabled'=>($legacy('facebook')!=='' || !array_key_exists('social_networks_json', $set)),'platform'=>'facebook','url'=>$legacy('facebook')!=='' ? $legacy('facebook') : 'https://web.facebook.com/esmultiserv','sort_order'=>2],
        ['enabled'=>$legacy('tiktok')!=='','platform'=>'tiktok','url'=>$legacy('tiktok'),'sort_order'=>3],
        ['enabled'=>$legacy('youtube')!=='','platform'=>'youtube','url'=>$legacy('youtube'),'sort_order'=>4],
        ['enabled'=>false,'platform'=>'linkedin','url'=>'','sort_order'=>5],
    ];
}

function social_rows_from_settings(array $set): array {
    $raw = trim((string)($set['social_networks_json'] ?? ''));
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $rows = [];
            foreach ($decoded as $row) {
                if (!is_array($row)) continue;
                $rows[] = [
                    'enabled' => !empty($row['enabled']),
                    'platform' => (string)($row['platform'] ?? ''),
                    'url' => trim((string)($row['url'] ?? '')),
                    'sort_order' => max(0, min(999, (int)($row['sort_order'] ?? 0))),
                ];
            }
            if ($rows) return array_slice($rows, 0, 10);
        }
    }
    return social_default_rows($set);
}

$rows = social_rows_from_settings($set);
while (count($rows) < 5) {
    $fallback = array_keys($platforms)[count($rows) % count($platforms)];
    $rows[] = ['enabled'=>false,'platform'=>$fallback,'url'=>'','sort_order'=>count($rows)+1];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $postedPlatforms = $_POST['platform'] ?? [];
        $postedUrls = $_POST['url'] ?? [];
        $postedOrders = $_POST['sort_order'] ?? [];
        $postedEnabled = $_POST['enabled'] ?? [];
        $newRows = [];
        $seen = [];
        for ($i=0; $i<5; $i++) {
            $platform = strtolower(trim((string)($postedPlatforms[$i] ?? '')));
            if (!isset($platforms[$platform])) throw new RuntimeException('Select a valid platform for row '.($i+1).'.');
            if (isset($seen[$platform])) throw new RuntimeException('Each platform can be configured only once.');
            $seen[$platform] = true;
            $url = trim((string)($postedUrls[$i] ?? ''));
            $enabled = isset($postedEnabled[$i]);
            if ($url !== '') {
                if (!filter_var($url, FILTER_VALIDATE_URL)) throw new RuntimeException('Enter a valid absolute URL for '.$platforms[$platform].'.');
                $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
                if (!in_array($scheme, ['http','https'], true)) throw new RuntimeException('Social URLs must use http or https.');
            }
            if ($enabled && $url === '') throw new RuntimeException($platforms[$platform].' is enabled but has no URL.');
            $order = max(0, min(999, (int)($postedOrders[$i] ?? ($i+1))));
            $newRows[] = ['enabled'=>$enabled,'platform'=>$platform,'url'=>$url,'sort_order'=>$order];
        }

        $size = (string)($_POST['social_size'] ?? 'medium');
        $style = (string)($_POST['social_style'] ?? 'icon');
        $location = (string)($_POST['social_location'] ?? 'footer_floating_right');
        if (!in_array($size, $allowedSizes, true)) throw new RuntimeException('Select a valid icon size.');
        if (!in_array($style, $allowedStyles, true)) throw new RuntimeException('Select a valid social style.');
        if (!in_array($location, $allowedLocations, true)) throw new RuntimeException('Select a valid social location.');

        usort($newRows, static fn(array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);
        save_setting('social_networks_json', json_encode($newRows, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        save_setting('social_size', $size);
        save_setting('social_style', $style);
        save_setting('social_location', $location);
        save_setting('social_show_desktop', isset($_POST['social_show_desktop']) ? '1' : '0');
        save_setting('social_show_mobile', isset($_POST['social_show_mobile']) ? '1' : '0');
        flash('success','Social networks updated. Public placement and visibility are now active.');
        header('Location: social.php');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $rows = [];
        for ($i=0; $i<5; $i++) {
            $rows[] = [
                'enabled'=>isset(($_POST['enabled'] ?? [])[$i]),
                'platform'=>(string)(($_POST['platform'] ?? [])[$i] ?? array_keys($platforms)[$i]),
                'url'=>(string)(($_POST['url'] ?? [])[$i] ?? ''),
                'sort_order'=>(int)(($_POST['sort_order'] ?? [])[$i] ?? ($i+1)),
            ];
        }
    }
}

$set = settings();
$pageTitle='Social networks';
$active='social';
require __DIR__.'/_header.php';
$size = (string)($set['social_size'] ?? 'medium');
$style = (string)($set['social_style'] ?? 'icon');
$location = (string)($set['social_location'] ?? 'footer_floating_right');
?>
<div class="page-heading social-page-heading">
    <div>
        <p class="eyebrow">PUBLIC PRESENCE</p>
        <h1>Social networks</h1>
        <p class="muted">Control which social profiles appear publicly, their order, visual style and placement without editing frontend code.</p>
    </div>
    <div class="heading-actions">
        <a class="button secondary" href="../?preview=1" target="_blank" rel="noopener"><?=icon('eye')?> Preview site</a>
    </div>
</div>
<?php if($error): ?><div class="alert error"><?=h($error)?></div><?php endif; ?>

<form method="post" class="social-admin-form" data-unsaved-form>
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<section class="panel wide animate-in social-config-panel">
    <div class="panel-heading">
        <div class="panel-icon"><?=icon('share')?></div>
        <div><h2>Social profiles</h2><p>Five managed social slots. Enable only profiles that are ready to publish.</p></div>
    </div>

    <div class="social-table-head" aria-hidden="true">
        <span>#</span><span>Active</span><span>Platform</span><span>Profile URL</span><span>Order</span>
    </div>
    <div class="social-admin-rows">
    <?php foreach(array_slice($rows,0,5) as $i=>$row): $platform=(string)($row['platform']??array_keys($platforms)[$i]); ?>
        <div class="social-admin-row">
            <div class="social-row-number" aria-label="Row <?=($i+1)?>"><?=str_pad((string)($i+1),2,'0',STR_PAD_LEFT)?></div>
            <label class="social-enabled-control">
                <span class="social-mobile-label">Active</span>
                <input type="checkbox" name="enabled[<?=$i?>]" value="1" <?=!empty($row['enabled'])?'checked':''?>>
                <span class="social-check-ui" aria-hidden="true"></span>
            </label>
            <label><span class="social-mobile-label">Platform</span><select name="platform[<?=$i?>]" required>
                <?php foreach($platforms as $key=>$label): ?><option value="<?=h($key)?>" <?=$platform===$key?'selected':''?>><?=h($label)?></option><?php endforeach; ?>
            </select></label>
            <label><span class="social-mobile-label">Profile URL</span><input type="url" name="url[<?=$i?>]" value="<?=h((string)($row['url']??''))?>" placeholder="https://..." inputmode="url"></label>
            <label><span class="social-mobile-label">Order</span><input type="number" name="sort_order[<?=$i?>]" value="<?=h((string)($row['sort_order']??($i+1)))?>" min="0" max="999"></label>
        </div>
    <?php endforeach; ?>
    </div>
</section>

<section class="panel wide animate-in social-global-panel">
    <div class="panel-heading">
        <div class="panel-icon"><?=icon('gear')?></div>
        <div><h2>Public display</h2><p>Choose where enabled profiles appear. For this site, <strong>Footer + floating right</strong> keeps the links visible in both places without editing frontend code.</p></div>
    </div>
    <div class="three-col social-global-grid">
        <label>Size<select name="social_size">
            <option value="small" <?=$size==='small'?'selected':''?>>Small</option>
            <option value="medium" <?=$size==='medium'?'selected':''?>>Medium</option>
            <option value="large" <?=$size==='large'?'selected':''?>>Large</option>
        </select></label>
        <label>Style<select name="social_style">
            <option value="icon" <?=$style==='icon'?'selected':''?>>Icon only</option>
            <option value="icon_name" <?=$style==='icon_name'?'selected':''?>>Icon + name</option>
        </select></label>
        <label>Location<select name="social_location">
            <option value="footer" <?=$location==='footer'?'selected':''?>>Footer</option>
            <option value="hero" <?=$location==='hero'?'selected':''?>>Below Hero</option>
            <option value="floating_left" <?=$location==='floating_left'?'selected':''?>>Floating left</option>
            <option value="floating_right" <?=$location==='floating_right'?'selected':''?>>Floating right</option>
            <option value="footer_floating_left" <?=$location==='footer_floating_left'?'selected':''?>>Footer + floating left</option>
            <option value="footer_floating_right" <?=$location==='footer_floating_right'?'selected':''?>>Footer + floating right</option>
        </select></label>
    </div>
    <div class="social-visibility-grid">
        <label class="premium-switch"><input type="checkbox" name="social_show_desktop" value="1" <?=($set['social_show_desktop']??'1')==='1'?'checked':''?>><span class="switch-ui"></span><span><b>Desktop & tablet</b><small>Show social links on larger screens.</small></span></label>
        <label class="premium-switch"><input type="checkbox" name="social_show_mobile" value="1" <?=($set['social_show_mobile']??'1')==='1'?'checked':''?>><span class="switch-ui"></span><span><b>Mobile</b><small>Show the same configured social profiles on phones.</small></span></label>
    </div>
    <div class="social-preview-card">
        <div><span class="eyebrow">PREVIEW</span><strong>Local SVG icons</strong><small>No CDN or external icon library is used.</small></div>
        <div class="social-preview-icons">
            <?php foreach($platforms as $key=>$label): ?><span title="<?=h($label)?>"><img src="../assets/social/<?=h($key)?>.svg" alt=""></span><?php endforeach; ?>
        </div>
    </div>
    <div class="form-actions social-form-actions"><button type="submit"><?=icon('share')?> Save social networks</button></div>
</section>
</form>
<?php require __DIR__.'/_footer.php';
