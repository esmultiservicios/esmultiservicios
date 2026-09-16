<?php
require __DIR__.'/bootstrap.php';
require_permission('settings.manage');
$pdo=db();
$set=settings();
$error='';

// Website analytics are stored as UTC instants and displayed in Honduras local time.
$analyticsTimezone=new DateTimeZone('America/Tegucigalpa');
$analyticsToday=(new DateTimeImmutable('now',$analyticsTimezone))->format('Y-m-d');
$analyticsLastDisplay='No visits yet';
if(trim((string)($set['analytics_last_visit_at']??''))!=='') {
    try {
        $analyticsLastUtc=new DateTimeImmutable((string)$set['analytics_last_visit_at'],new DateTimeZone('UTC'));
        $analyticsLastDisplay=$analyticsLastUtc->setTimezone($analyticsTimezone)->format('Y-m-d h:i:s A');
    } catch(Throwable $e) {
        $analyticsLastDisplay=(string)$set['analytics_last_visit_at'];
    }
}
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $action=$_POST['action']??'';
    try {
        if($action==='identity') {
            foreach(['admin_brand_name','company_name','phone','phone_digits','email','youtube','facebook','tiktok','website','business_hours','contact_map_query','developer_credit_text'] as $k)save_setting($k,trim((string)($_POST[$k]??'')));
            if(!empty($_FILES['admin_logo']['name']))save_setting('admin_logo_path',upload_image($_FILES['admin_logo'],'branding','admin-logo',5));
            if(!empty($_FILES['favicon']['name']))save_setting('favicon_path',upload_image($_FILES['favicon'],'branding','favicon',3));
            if(isset($_POST['remove_favicon']))save_setting('favicon_path','');
            save_setting('developer_credit_enabled',isset($_POST['developer_credit_enabled'])?'1':'0');
            flash('success','Brand and contact settings saved.');
        } elseif($action==='maintenance') {
            save_setting('maintenance_mode',isset($_POST['maintenance_mode'])?'1':'0');
            save_setting('maintenance_title',trim((string)($_POST['maintenance_title']??'')));
            save_setting('maintenance_text',trim((string)($_POST['maintenance_text']??'')));
            if(!empty($_FILES['maintenance_image']['name']))save_setting('maintenance_image_path',upload_image($_FILES['maintenance_image'],'maintenance','maintenance',8));
            if(isset($_POST['remove_maintenance_image']))save_setting('maintenance_image_path','');
            flash('success','Website status updated.');
        } elseif($action==='whatsapp') {
            save_setting('whatsapp_enabled',isset($_POST['whatsapp_enabled'])?'1':'0');
            save_setting('whatsapp_message',trim((string)($_POST['whatsapp_message']??'')));
            save_setting('whatsapp_position',in_array($_POST['whatsapp_position']??'right',['left','right'],true)?$_POST['whatsapp_position']:'right');
            flash('success','WhatsApp widget updated.');
        } elseif($action==='visitor_analytics') {
            save_setting('analytics_tracking_enabled', isset($_POST['analytics_tracking_enabled']) ? '1' : '0');
            flash('success','Website visit tracking settings updated.');
        } elseif($action==='contact_requirements') {
            $required = [
                'name' => isset($_POST['contact_required_name']),
                'email' => true,
                'phone' => isset($_POST['contact_required_phone']),
                'service' => isset($_POST['contact_required_service']),
                'message' => isset($_POST['contact_required_message']),
                'referral' => isset($_POST['contact_required_referral']),
            ];
            if(!$required['service'] && !$required['message']) {
                throw new RuntimeException('Keep at least Request type or Message required so every inquiry explains what the visitor needs.');
            }

            $minChars = (int)($_POST['contact_message_min_chars'] ?? 30);
            $minWords = (int)($_POST['contact_message_min_words'] ?? 5);
            if($minChars < 20 || $minChars > 300) {
                throw new RuntimeException('Message minimum characters must be between 20 and 300.');
            }
            if($minWords < 3 || $minWords > 30) {
                throw new RuntimeException('Message minimum words must be between 3 and 30.');
            }

            foreach($required as $field=>$enabled) {
                save_setting('contact_required_'.$field,$enabled?'1':'0');
            }
            save_setting('contact_message_min_chars',(string)$minChars);
            save_setting('contact_message_min_words',(string)$minWords);

            $defaultReferralEs = "Búsqueda en Google u otro buscador\nFacebook\nTikTok\nInstagram\nWhatsApp\nRecomendación de una persona o empresa\nYa conocía ES MULTISERVICIOS\nOtro";
            $defaultReferralEn = "Google or another search engine\nFacebook\nTikTok\nInstagram\nWhatsApp\nRecommendation from a person or company\nI already knew ES MULTISERVICIOS\nOther";
            $referralEs = trim((string)($_POST['contact_referral_options_es'] ?? $defaultReferralEs));
            $referralEn = trim((string)($_POST['contact_referral_options_en'] ?? $defaultReferralEn));
            $cleanOptions = static function(string $raw): string {
                $items = array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/u', $raw) ?: []), static fn($v) => $v !== '')));
                return implode("\n", array_slice($items, 0, 20));
            };
            $referralEs = $cleanOptions($referralEs);
            $referralEn = $cleanOptions($referralEn);
            if ($referralEs === '' || $referralEn === '') throw new RuntimeException('Referral source options cannot be empty.');
            save_setting('contact_referral_options_es',$referralEs);
            save_setting('contact_referral_options_en',$referralEn);
            flash('success','Public inquiry requirements updated. Email remains mandatory, referral settings were saved and message quality rules remain active.');
        }
        header('Location: settings.php'.($action==='maintenance'?'#site-status':($action==='visitor_analytics'?'#visitor-analytics':($action==='contact_requirements'?'#contact-requirements':''))));
        exit;
    } catch(Throwable $e) {
        $error=$e->getMessage();
    }
}
$set=settings();
$pageTitle='Settings';
$active='settings';
require __DIR__.'/_header.php';
?>

<div class="page-heading">
<div>
<p class="eyebrow">SETTINGS</p>
<h1>Brand, contact & website status</h1>
<p class="muted">Control the admin identity and important public website behavior from one place.</p>
</div>
</div><?php
if($error):
?>
<div class="alert error"><?=h($error)?>
</div><?php
endif;
?>

<div class="content-grid">
<section class="panel animate-in">
<div class="panel-heading">
<div class="panel-icon"><?=icon('image')?>
</div>
<div>
<h2>Admin branding</h2>
<p>Change the name and logo displayed throughout this administrator.</p>
</div>
</div>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="identity">
<label>Admin name<input name="admin_brand_name" value="<?=h($set['admin_brand_name']??"ES CMS Core Admin")?>">
</label>
<label>Company / website name<input name="company_name" value="<?=h($set['company_name']??'Your Company')?>">
</label>
<div class="branding-upload-grid">
<div class="upload-zone" data-upload-zone tabindex="0">
<div class="upload-icon"><?=icon('image')?>
</div>
<strong>Admin logo</strong>
<small data-upload-name>Drag & drop, paste, or choose image</small>
<input type="file" name="admin_logo" accept="image/jpeg,image/png,image/webp">
<div class="upload-preview" data-upload-preview>
</div>
</div>
<div class="upload-zone favicon-zone" data-upload-zone tabindex="0">
<div class="upload-icon"><?=icon('image')?>
</div>
<strong>Browser tab icon (favicon)</strong>
<small data-upload-name>Use a square PNG, JPG or WebP · drag, paste, or choose</small>
<input type="file" name="favicon" accept="image/jpeg,image/png,image/webp">
<div class="upload-preview" data-upload-preview>
</div>
</div>
</div><?php
if(!empty($set['favicon_path'])):
?>
<div class="saved-favicon">
<img src="../<?=h($set['favicon_path'])?>" alt="Current favicon">
<div>
<strong>Current browser tab icon</strong>
<small><?=h($set['favicon_path'])?>
</small>
</div>
<label class="premium-check">
<input type="checkbox" name="remove_favicon" value="1">
<span>Remove favicon</span>
</label>
</div><?php
endif;
?>
<div class="two-col">
<label>Phone<input name="phone" value="<?=h($set['phone']??'')?>">
</label>
<label>Phone digits<input name="phone_digits" value="<?=h($set['phone_digits']??'')?>">
</label>
</div>
<label>Public email<input type="email" name="email" value="<?=h($set['email']??'')?>">
</label>
<div class="two-col">
<label>YouTube<input name="youtube" value="<?=h($set['youtube']??'')?>">
</label>
<label>Facebook<input name="facebook" value="<?=h($set['facebook']??'')?>">
</label>
</div>
<div class="two-col">
<label>TikTok<input name="tiktok" value="<?=h($set['tiktok']??'')?>">
</label>
<label>Website<input name="website" value="<?=h($set['website']??'')?>">
</label>
</div>
<label>Business hours<textarea name="business_hours"><?=h($set['business_hours']??'')?>
</textarea>
</label>
<label>Contact map location / search
<input name="contact_map_query" value="<?=h($set['contact_map_query']??'')?>" placeholder="Example: Company name, city, country">
<small class="field-help">Optional. When filled, the public Contact section shows an embedded map for this search/location.</small>
</label>
<label class="premium-switch">
<input type="checkbox" name="developer_credit_enabled" <?=($set['developer_credit_enabled']??'0')==='1'?'checked':''?>
>
<span class="switch-ui">
</span>
<span>
<b>Developer credit in footer</b>
<small>Optional. Enable only if the client agrees to show a discreet ES MULTISERVICIOS credit.</small>
</span>
</label>
<label>Developer credit text<input name="developer_credit_text" value="<?=h($set['developer_credit_text']??'Website by ES MULTISERVICIOS')?>">
</label>
<div class="form-actions">
<button>Save identity & contact</button>
</div>
</form>
</section>
<section class="panel animate-in" id="site-status">
<div class="panel-heading">
<div class="panel-icon"><?=icon('eye')?>
</div>
<div>
<h2>Website status</h2>
<p>Temporarily replace the public site with a polished maintenance screen while you make changes.</p>
</div>
</div>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="maintenance">
<label class="status-switch premium-switch">
<input type="checkbox" name="maintenance_mode" <?=($set['maintenance_mode']??'0')==='1'?'checked':''?>
>
<span class="switch-ui" aria-hidden="true">
</span>
<span>
<b>Maintenance mode</b>
<small>Visitors will see the maintenance screen when enabled.</small>
</span>
</label>
<label>Maintenance title<input name="maintenance_title" value="<?=h($set['maintenance_title']??'')?>">
</label>
<label>Maintenance message<textarea name="maintenance_text"><?=h($set['maintenance_text']??'')?>
</textarea>
</label>
<div class="upload-zone" data-upload-zone tabindex="0">
<div class="upload-icon"><?=icon('image')?>
</div>
<strong>Maintenance image</strong>
<small data-upload-name>Drag & drop, paste, or choose an optional image</small>
<input type="file" name="maintenance_image" accept="image/jpeg,image/png,image/webp">
<div class="upload-preview" data-upload-preview>
</div>
</div><?php
if(!empty($set['maintenance_image_path'])):
?>
<div class="saved-image-row">
<img src="../<?=h($set['maintenance_image_path'])?>" alt="Maintenance preview">
<div>
<strong>Current maintenance image</strong>
<small><?=h($set['maintenance_image_path'])?>
</small>
</div>
<label class="compact-check">
<input type="checkbox" name="remove_maintenance_image" value="1"> Remove image</label>
</div><?php
endif;
?>
<p class="secret-hint">When maintenance is active, opening the normal website shows the maintenance screen even if you are logged in. Use the preview button below to inspect the real site privately.</p>
<div class="form-actions">
<button>Save website status</button>
<a class="button secondary" href="../?preview=1" target="_blank" rel="noopener">Preview real site</a>
<a class="button ghost" href="../" target="_blank" rel="noopener">View public status</a>
</div>
</form>
</section>
<section class="panel wide animate-in" id="visitor-analytics">
<div class="panel-heading">
<div class="panel-icon">↗</div>
<div>
<h2>Private website visits</h2>
<p>See an anonymous estimate of how many browsers are reaching the public website. These counters are visible only in the administrator.</p>
</div>
</div>
<div class="three-col">
<div class="list-card"><small>ESTIMATED VISITS</small><strong><?=number_format((int)($set['analytics_total_visits']??0))?></strong><p>Counted once per browser per day.</p></div>
<div class="list-card"><small>TODAY</small><strong><?=h(($set['analytics_today_date']??'')===$analyticsToday ? number_format((int)($set['analytics_today_visits']??0)) : '0')?></strong><p>Anonymous visits recorded today in Honduras.</p></div>
<div class="list-card"><small>LAST VISIT</small><strong><?=h($analyticsLastDisplay)?></strong><p>Honduras local time (America/Tegucigalpa) of the latest counted visit.</p></div>
</div>
<form method="post" style="margin-top:18px">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="visitor_analytics">
<label class="premium-switch">
<input type="checkbox" name="analytics_tracking_enabled" <?=($set['analytics_tracking_enabled']??'1')==='1'?'checked':''?>>
<span class="switch-ui" aria-hidden="true"></span>
<span><b>Enable anonymous visit counter</b><small>Uses a first-party daily cookie. It does not store IP addresses or visitor identity and ignores common crawler user agents.</small></span>
</label>
<p class="secret-hint">This is a lightweight internal counter, not person identification. One person using multiple devices or clearing cookies can be counted more than once, while repeat page loads on the same browser during the same day are not counted again.</p>
<div class="form-actions"><button>Save visit tracking</button></div>
</form>
</section>
<section class="panel wide animate-in" id="contact-requirements">
<div class="panel-heading">
<div class="panel-icon"><?=icon('edit')?>
</div>
<div>
<h2>Public inquiry requirements</h2>
<p>Choose which fields visitors must complete before a website inquiry can be sent.</p>
</div>
</div>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="contact_requirements">
<div class="two-col">
<label class="premium-switch">
<input type="checkbox" name="contact_required_name" <?=($set['contact_required_name']??'1')==='1'?'checked':''?>>
<span class="switch-ui" aria-hidden="true"></span>
<span><b>Name required</b><small>Visitors must provide their name before sending the inquiry.</small></span>
</label>
<label class="premium-switch requirement-locked" aria-label="Email is always required">
<input type="checkbox" checked disabled>
<span class="switch-ui" aria-hidden="true"></span>
<span><b>Email always required</b><small>Locked for safety. Every inquiry must include a valid email so you always have a direct reply channel.</small></span>
</label>
<label class="premium-switch">
<input type="checkbox" name="contact_required_phone" <?=($set['contact_required_phone']??'1')==='1'?'checked':''?>>
<span class="switch-ui" aria-hidden="true"></span>
<span><b>Phone required</b><small>Require a phone number for calls or WhatsApp follow-up.</small></span>
</label>
<label class="premium-switch">
<input type="checkbox" name="contact_required_service" <?=($set['contact_required_service']??'1')==='1'?'checked':''?>>
<span class="switch-ui" aria-hidden="true"></span>
<span><b>Request type required</b><small>Visitors must choose IZZY, CAMI, Website, Custom software, Support or another listed option.</small></span>
</label>
<label class="premium-switch">
<input type="checkbox" name="contact_required_message" <?=($set['contact_required_message']??'1')==='1'?'checked':''?>>
<span class="switch-ui" aria-hidden="true"></span>
<span><b>Message required</b><small>Require a description so the inquiry includes useful context.</small></span>
</label>
<label class="premium-switch">
<input type="checkbox" name="contact_required_referral" <?=($set['contact_required_referral']??'1')==='1'?'checked':''?>>
<span class="switch-ui" aria-hidden="true"></span>
<span><b>How did you hear about us? required</b><small>Ask every visitor which channel or recommendation led them to the website.</small></span>
</label>
</div>
<div class="two-col requirement-quality-grid">
<label>Minimum meaningful characters in Message
<input type="number" name="contact_message_min_chars" min="20" max="300" step="1" value="<?=h((string)($set['contact_message_min_chars']??'30'))?>">
<small>Counts letters and numbers, not spaces or punctuation. Default: 30.</small>
</label>
<label>Minimum meaningful words in Message
<input type="number" name="contact_message_min_words" min="3" max="30" step="1" value="<?=h((string)($set['contact_message_min_words']??'5'))?>">
<small>Words must contain at least 2 letters or numbers. Default: 5.</small>
</label>
</div>
<?php
$defaultReferralEs = "Búsqueda en Google u otro buscador\nFacebook\nTikTok\nInstagram\nWhatsApp\nRecomendación de una persona o empresa\nYa conocía ES MULTISERVICIOS\nOtro";
$defaultReferralEn = "Google or another search engine\nFacebook\nTikTok\nInstagram\nWhatsApp\nRecommendation from a person or company\nI already knew ES MULTISERVICIOS\nOther";
?>
<div class="two-col requirement-quality-grid">
<label>Referral options (ES)
<textarea name="contact_referral_options_es" rows="8"><?=h($set['contact_referral_options_es']??$defaultReferralEs)?></textarea>
<small>One option per line. Keep “Otro” if you want visitors to specify a custom source.</small>
</label>
<label>Referral options (EN)
<textarea name="contact_referral_options_en" rows="8"><?=h($set['contact_referral_options_en']??$defaultReferralEn)?></textarea>
<small>One option per line. Keep “Other” if you want visitors to specify a custom source.</small>
</label>
</div>
<p class="secret-hint">Safety rules: Email is always required. Phone, Name, Request type, Message and How did you hear about us? remain configurable except Email. At least Request type or Message must stay required. When a message is entered, punctuation-only, spaces, “Hi/Hello” alone, or other too-short text is rejected according to the quality limits above.</p>
<div class="form-actions">
<button>Save inquiry requirements</button>
</div>
</form>
</section>
<section class="panel wide animate-in">
<div class="panel-heading">
<div class="panel-icon">💬</div>
<div>
<h2>Floating WhatsApp contact</h2>
<p>A discreet floating contact button that respects the page content on desktop and mobile.</p>
</div>
</div>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="action" value="whatsapp">
<label class="premium-switch">
<input type="checkbox" name="whatsapp_enabled" <?=($set['whatsapp_enabled']??'1')==='1'?'checked':''?>
>
<span class="switch-ui" aria-hidden="true">
</span>
<span>
<b>Floating WhatsApp</b>
<small>Show a compact WhatsApp contact button on the public website.</small>
</span>
</label>
<div class="two-col">
<label>Default message<textarea name="whatsapp_message"><?=h($set['whatsapp_message']??'')?>
</textarea>
</label>
<label>Position<select name="whatsapp_position">
<option value="right" <?=($set['whatsapp_position']??'right')==='right'?'selected':''?>
>Bottom right</option>
<option value="left" <?=($set['whatsapp_position']??'right')==='left'?'selected':''?>
>Bottom left</option>
</select>
</label>
</div>
<div class="form-actions">
<button>Save WhatsApp widget</button>
</div>
</form>
</section>
</div><?php
require __DIR__.'/_footer.php';
