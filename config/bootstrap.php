<?php
declare(strict_types=1);
const ROOT_DIR = __DIR__ . '/..';
const UPLOAD_DIR = ROOT_DIR . '/uploads';


function versioned_asset(string $url, string $relativePath): string
{
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    $fullPath = ROOT_DIR . '/' . $relativePath;
    if (!is_file($fullPath)) return $url;

    static $versions = [];
    if (!isset($versions[$fullPath])) {
        $hash = @sha1_file($fullPath);
        $versions[$fullPath] = $hash !== false
            ? substr($hash, 0, 12)
            : (string) (@filemtime($fullPath) ?: 1);
    }

    $separator = str_contains($url, '?') ? '&' : '?';
    return $url . $separator . 'v=' . rawurlencode($versions[$fullPath]);
}
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
function config_ready(): bool {
    return is_file(__DIR__ . '/database.php');
}
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $configFile = __DIR__ . '/database.php';
    if (!is_file($configFile)) throw new RuntimeException('Database is not configured. Open /install/ to start the setup wizard.');
    $cfg = require $configFile;
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['dbname'], $cfg['charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, ]);
    return $pdo;
}
function site_content(): array {
    $rows=db()->query('SELECT content_key,content_value FROM site_content')->fetchAll();
    $o=[];
    foreach($rows as $r)$o[$r['content_key']]=$r['content_value'];
    return $o;
}
function settings(): array {
    $rows=db()->query('SELECT setting_key,setting_value FROM settings')->fetchAll();
    $o=[];
    foreach($rows as $r)$o[$r['setting_key']]=$r['setting_value'];
    return $o;
}
function setting(string $key, string $default=''): string {
    static $cache=null;
    if($cache===null)$cache=settings();
    return isset($cache[$key])?(string)$cache[$key]:$default;
}
function save_setting(string $key, string $value): void {
    $st=db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $st->execute([$key,$value]);
}

function php_requirement_enable_hint(string $extension): string {
    $phpSeries = PHP_MAJOR_VERSION . PHP_MINOR_VERSION;
    return 'WHM → Software → EasyApache 4 → Customize → PHP Extensions → search ea-php' . $phpSeries . '-php-' . $extension . ' → Review → Provision.';
}
function fileinfo_requirement_message(): string {
    return 'Secure file validation is unavailable because PHP Fileinfo is not enabled. Enable it in WHM → Software → EasyApache 4 → Customize → PHP Extensions → search ea-php' . PHP_MAJOR_VERSION . PHP_MINOR_VERSION . '-php-fileinfo → Review → Provision, then try again.';
}
function detect_mime_type(string $path, bool $required = true): string {
    if (!extension_loaded('fileinfo') || !class_exists('finfo')) {
        if ($required) {
            throw new RuntimeException(fileinfo_requirement_message());
        }
        return '';
    }
    if ($path === '' || !is_file($path)) {
        if ($required) {
            throw new RuntimeException('The uploaded file could not be inspected. Please try again.');
        }
        return '';
    }
    try {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);
    } catch (Throwable $e) {
        if ($required) {
            throw new RuntimeException('The server could not validate the uploaded file type. Please contact the website administrator.');
        }
        return '';
    }
    if (!is_string($mime) || trim($mime) === '') {
        if ($required) {
            throw new RuntimeException('The server could not validate the uploaded file type. Please try another file.');
        }
        return '';
    }
    return strtolower(trim($mime));
}

function upload_image(array $file, string $subdir, string $prefix, int $maxMb = 8): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Image upload failed.');
    if (($file['size'] ?? 0) > $maxMb * 1024 * 1024) throw new RuntimeException("Image exceeds {$maxMb} MB.");
    $mime = detect_mime_type((string)($file['tmp_name'] ?? ''), true);
    $allowed=['image/jpeg'=>'jpg',
    'image/png'=>'png',
    'image/webp'=>'webp'];
    if (!isset($allowed[$mime])) throw new RuntimeException('Only JPG, PNG and WEBP images are allowed.');
    $dir=UPLOAD_DIR.'/'.trim($subdir,'/');
    if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Could not create upload directory.');
    $name=preg_replace('/[^a-z0-9_-]+/i','-',$prefix).'-'.date('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.$allowed[$mime];
    $target=$dir.'/'.$name;
    if(!move_uploaded_file($file['tmp_name'],$target))throw new RuntimeException('Could not save uploaded image.');
    return 'uploads/'.trim($subdir,'/').'/'.$name;
}
function upload_media_file(array $file, string $subdir='media', string $prefix='media', int $maxMb = 60): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Media upload failed.');
    if (($file['size'] ?? 0) > $maxMb * 1024 * 1024) throw new RuntimeException("Media file exceeds {$maxMb} MB.");
    $mime = detect_mime_type((string)($file['tmp_name'] ?? ''), true);
    $allowed=[ 'image/jpeg'=>'jpg',
    'image/png'=>'png',
    'image/webp'=>'webp',
    'video/mp4'=>'mp4',
    'video/webm'=>'webm',
    'application/pdf'=>'pdf' ];
    if(!isset($allowed[$mime])) throw new RuntimeException('Allowed media: JPG, PNG, WEBP, MP4, WEBM and PDF.');
    $dir=UPLOAD_DIR.'/'.trim($subdir,'/');
    if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Could not create upload directory.');
    $name=preg_replace('/[^a-z0-9_-]+/i','-',$prefix).'-'.date('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.$allowed[$mime];
    $target=$dir.'/'.$name;
    if(!move_uploaded_file($file['tmp_name'],$target))throw new RuntimeException('Could not save uploaded media.');
    return 'uploads/'.trim($subdir,'/').'/'.$name;
}
function normalized_files(string $field): array {
    if(empty($_FILES[$field])) return [];
    $src=$_FILES[$field];
    $out=[];
    if(!is_array($src['name'])) return [$src];
    foreach($src['name'] as $i=>$name) {
        if(($src['error'][$i]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)continue;
        $out[]=['name'=>$name,
        'type'=>$src['type'][$i]??'',
        'tmp_name'=>$src['tmp_name'][$i]??'',
        'error'=>$src['error'][$i]??UPLOAD_ERR_NO_FILE,
        'size'=>$src['size'][$i]??0];
    }
    return $out;
}
function app_key(): string {
    $file=__DIR__.'/app.key';
    if(!is_file($file)) {
        $key=random_bytes(32);
        if(@file_put_contents($file,base64_encode($key),LOCK_EX)===false) throw new RuntimeException('Unable to create config/app.key. Check write permissions.');
        @chmod($file,0600);
        return $key;
    }
    $raw=base64_decode(trim((string)file_get_contents($file)),true);
    if($raw===false||strlen($raw)<32) throw new RuntimeException('Invalid application encryption key.');
    return substr($raw,0,32);
}
function secret_encrypt(?string $value): string {
    $value=trim((string)$value);
    if($value==='')return '';
    $iv=random_bytes(12);
    $tag='';
    $cipher=openssl_encrypt($value,'aes-256-gcm',app_key(),OPENSSL_RAW_DATA,$iv,$tag);
    if($cipher===false)throw new RuntimeException('Unable to encrypt secret.');
    return 'v1:'.base64_encode($iv.$tag.$cipher);
}
function secret_decrypt(?string $value): string {
    $value=(string)$value;
    if($value===''||!str_starts_with($value,'v1:'))return $value;
    $raw=base64_decode(substr($value,3),true);
    if($raw===false||strlen($raw)<29)return '';
    $iv=substr($raw,0,12);
    $tag=substr($raw,12,16);
    $cipher=substr($raw,28);
    $plain=openssl_decrypt($cipher,'aes-256-gcm',app_key(),OPENSSL_RAW_DATA,$iv,$tag);
    return $plain===false?'':$plain;
}
function gallery_fallback(int $index): string {
    $images=[ 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=1100&q=85',
    'https://images.unsplash.com/photo-1562259949-e8e7689d7828?auto=format&fit=crop&w=900&q=85',
    'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=900&q=85',
    'https://images.unsplash.com/photo-1581858726788-75bc0f6a952d?auto=format&fit=crop&w=900&q=85',
    'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=900&q=85',
    'https://images.unsplash.com/photo-1590725121839-892b458a74fe?auto=format&fit=crop&w=900&q=85' ];
    return $images[$index % count($images)];
}
function draft_content(): array {
    $rows=db()->query('SELECT content_key,content_value FROM content_drafts')->fetchAll();
    $o=[];
    foreach($rows as $r)$o[$r['content_key']]=$r['content_value'];
    return $o;
}
function site_sections(): array {
    try {
        $rows=db()->query('SELECT section_key,label,sort_order,active FROM site_sections ORDER BY sort_order,section_key')->fetchAll();
    } catch(Throwable $e) {
        return [];
    }
    $o=[];
    foreach($rows as $r)$o[$r['section_key']]=$r;
    return $o;
}
function section_enabled(string $key): bool {
    static $s=null;
    if($s===null)$s=site_sections();
    return !isset($s[$key])||(int)$s[$key]['active']===1;
}
function section_order(string $key,int $default=999): int {
    static $s=null;
    if($s===null)$s=site_sections();
    return isset($s[$key])?(int)$s[$key]['sort_order']:$default;
}
function log_activity(string $type,string $description,array $metadata=[]): void {
    try {
        $adminId=!empty($_SESSION['escms_admin_id'])?(int)$_SESSION['escms_admin_id']:null;
        $st=db()->prepare('INSERT INTO activity_log(admin_id,action_type,description,metadata_json) VALUES(?,?,?,?)');
        $st->execute([$adminId,$type,$description,$metadata?json_encode($metadata,JSON_UNESCAPED_UNICODE):null]);
    } catch(Throwable $e) {
    }
}
function admin_notify(string $type,string $title,string $message,string $url=''): void {
    try {
        $st=db()->prepare('INSERT INTO admin_notifications(notification_type,title,message,action_url) VALUES(?,?,?,?)');
        $st->execute([$type,$title,$message,$url?:null]);
    } catch(Throwable $e) {
    }
}
function media_add(string $path,string $title=''): void {
    try {
        $full=ROOT_DIR.'/'.$path;
        $mime = is_file($full) ? detect_mime_type($full, false) : '';
        $size=is_file($full)?filesize($full):0;
        $adminId=!empty($_SESSION['escms_admin_id'])?(int)$_SESSION['escms_admin_id']:null;
        $st=db()->prepare('INSERT INTO media_library(title,file_path,mime_type,file_size,uploaded_by) VALUES(?,?,?,?,?)');
        $st->execute([$title,$path,$mime,(int)$size,$adminId]);
    } catch(Throwable $e) {
    }
}


function landing_content(string $lang='es'): array {
    $lang=in_array($lang,['es','en'],true)?$lang:'es';
    try {
        $st=db()->prepare('SELECT content_key,content_value FROM landing_content WHERE lang=?');
        $st->execute([$lang]);
        $out=[];
        foreach($st->fetchAll() as $row)$out[$row['content_key']]=$row['content_value'];
        return $out;
    } catch(Throwable $e) { return []; }
}
function marketing_products(): array {
    try { return db()->query('SELECT * FROM marketing_products WHERE active=1 ORDER BY sort_order,id')->fetchAll(); }
    catch(Throwable $e) { return []; }
}
function marketing_plans(): array {
    try { return db()->query('SELECT * FROM marketing_plans WHERE active=1 ORDER BY product_key,sort_order,id')->fetchAll(); }
    catch(Throwable $e) { return []; }
}
function marketing_projects(): array {
    try { return db()->query('SELECT * FROM marketing_projects WHERE active=1 ORDER BY sort_order,id')->fetchAll(); }
    catch(Throwable $e) { return []; }
}
