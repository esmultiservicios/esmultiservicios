<?php
require __DIR__.'/bootstrap.php';
require_once dirname(__DIR__).'/core/EmailService.php';
require_permission('estimates.view');
$pdo=db();
$me=current_admin();
$canAll=user_can('estimates.manage_all');
$canAssigned=user_can('estimates.manage_assigned')||$canAll;
$error='';

function estimate_reply_files(): array {
    $raw=$_FILES['reply_files']??null;
    if(!$raw||!isset($raw['name']))return [];
    if(!is_array($raw['name']))return [$raw];
    $out=[];
    foreach($raw['name'] as $i=>$name){
        $out[]=[
            'name'=>$name,
            'type'=>$raw['type'][$i]??'',
            'tmp_name'=>$raw['tmp_name'][$i]??'',
            'error'=>$raw['error'][$i]??UPLOAD_ERR_NO_FILE,
            'size'=>$raw['size'][$i]??0,
        ];
    }
    return $out;
}

function estimate_store_reply_files(array $files,int $estimateId): array {
    if(!$files)return [];
    if(count($files)>5)throw new RuntimeException('Attach no more than 5 files per response.');
    $allowed=[
        'application/pdf'=>'pdf',
        'application/msword'=>'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx',
        'application/vnd.ms-excel'=>'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx',
        'image/jpeg'=>'jpg',
        'image/png'=>'png',
        'image/webp'=>'webp',
        'text/plain'=>'txt',
    ];
    $total=0;
    $stored=[];
    $root=defined('ROOT_DIR')?ROOT_DIR:dirname(__DIR__);
    $relativeDir='uploads/estimate-replies/'.$estimateId;
    $absoluteDir=$root.'/'.$relativeDir;
    if(!is_dir($absoluteDir)&&!mkdir($absoluteDir,0755,true)&&!is_dir($absoluteDir))throw new RuntimeException('Unable to prepare the attachment folder.');
    $finfo=function_exists('finfo_open')?finfo_open(FILEINFO_MIME_TYPE):null;
    try{
        foreach($files as $file){
            $err=(int)($file['error']??UPLOAD_ERR_NO_FILE);
            if($err===UPLOAD_ERR_NO_FILE)continue;
            if($err!==UPLOAD_ERR_OK)throw new RuntimeException('One of the attachments could not be uploaded.');
            $size=(int)($file['size']??0);
            if($size<1)continue;
            $total+=$size;
            if($total>2621440)throw new RuntimeException('Attachments must be 2.5 MB or less in total so they can be delivered reliably by email.');
            $tmp=(string)($file['tmp_name']??'');
            if($tmp===''||!is_uploaded_file($tmp))throw new RuntimeException('Invalid attachment upload.');
            $mime=$finfo?((string)finfo_file($finfo,$tmp)):(string)($file['type']??'');
            if(!isset($allowed[$mime]))throw new RuntimeException('Allowed attachments: PDF, Word, Excel, TXT, JPG, PNG and WEBP.');
            $original=trim((string)($file['name']??'attachment'))?:'attachment';
            $ext=$allowed[$mime];
            $filename='reply-'.bin2hex(random_bytes(10)).'.'.$ext;
            $relative=$relativeDir.'/'.$filename;
            $absolute=$root.'/'.$relative;
            if(!move_uploaded_file($tmp,$absolute))throw new RuntimeException('Unable to save one of the attachments.');
            $stored[]=['path'=>$absolute,'relative_path'=>$relative,'name'=>$original,'mime'=>$mime,'size'=>$size];
        }
    }finally{
        if($finfo)finfo_close($finfo);
    }
    return $stored;
}

function estimate_cleanup_reply_files(array $stored): void {
    foreach($stored as $file){
        $path=(string)($file['path']??'');
        if($path!==''&&is_file($path))@unlink($path);
    }
}
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    if(!$canAssigned) {
        http_response_code(403);
        exit('Access denied.');
    }
    $id=(int)($_POST['id']??0);
    try {
        $st=$pdo->prepare('SELECT * FROM estimate_requests WHERE id=?');
        $st->execute([$id]);
        $cur=$st->fetch();
        if(!$cur)throw new RuntimeException('Request not found.');
        if(!$canAll&&(int)($cur['assigned_to']??0)!==(int)$me['id'])throw new RuntimeException('This request is not assigned to you.');
        $action=(string)($_POST['action']??'update');
        if($action==='update') {
            $status=(string)($_POST['status']??'new');
            $priority=(string)($_POST['priority']??'normal');
            $follow=trim((string)($_POST['follow_up_date']??''))?:null;
            $assigned=$canAll?((int)($_POST['assigned_to']??0)?:null):((int)$me['id']);
            if(!in_array($status,['new','contacted','in_progress','won','lost','closed'],true))throw new RuntimeException('Invalid status.');
            if(!in_array($priority,['low','normal','high','urgent'],true))$priority='normal';
            $pdo->prepare('UPDATE estimate_requests SET status=?,priority=?,follow_up_date=?,assigned_to=? WHERE id=?')->execute([$status,$priority,$follow,$assigned,$id]);
            log_activity('estimate_update','Updated estimate request workflow',['estimate_id'=>$id]);
            flash('success','Request updated.');
        } elseif($action==='note') {
            $note=trim((string)($_POST['note']??''));
            if($note==='')throw new RuntimeException('Write a note first.');
            $pdo->prepare('INSERT INTO estimate_notes(estimate_id,admin_id,note) VALUES(?,?,?)')->execute([$id,(int)$me['id'],$note]);
            log_activity('estimate_note','Added internal estimate note',['estimate_id'=>$id]);
            flash('success','Internal note added.');
        } elseif($action==='reply') {
            $to=trim((string)($cur['email']??''));
            $subject=trim((string)($_POST['reply_subject']??''));
            $messageHtml=EmailTemplates::sanitizeEditorHtml((string)($_POST['reply_message_html']??''));
            $messagePlain=trim(html_entity_decode(strip_tags(str_replace(['<br>','<br/>','<br />'],"\n",$messageHtml)),ENT_QUOTES|ENT_HTML5,'UTF-8'));
            if(!filter_var($to,FILTER_VALIDATE_EMAIL))throw new RuntimeException('This request does not have a valid email address.');
            if($subject==='')throw new RuntimeException('Write an email subject.');
            if($messagePlain==='')throw new RuntimeException('Write the response before sending.');
            if(strlen($subject)>240)throw new RuntimeException('The subject is too long.');
            if(strlen($messageHtml)>30000)throw new RuntimeException('The response is too long.');
            $storedReplyFiles=[];
            try{
                $storedReplyFiles=estimate_store_reply_files(estimate_reply_files(),$id);
                $set=settings();
                $mailer=new EmailService();
                $cfg=$mailer->configByType(4) ?: $mailer->configByType(1);
                if(!$cfg)throw new RuntimeException('No active Auto Replies or Website Alerts sender is configured.');
                $replyTo=$mailer->resolveDestination($cfg);
                $payload=$cur;
                $payload['reply_subject']=$subject;
                $payload['reply_message_html']=$messageHtml;
                $payload['admin_name']=trim((string)($me['full_name']??$me['username']??'Administrator'));
                $emailAttachments=array_map(static fn(array $file):array=>['path'=>$file['path'],'name'=>$file['name'],'mime'=>$file['mime']],$storedReplyFiles);
                $result=$mailer->send(
                    $cfg,
                    $to,
                    $subject,
                    EmailTemplates::estimateManualReply($payload,$set),
                    ['reply_to'=>filter_var($replyTo,FILTER_VALIDATE_EMAIL)?$replyTo:'','attachments'=>$emailAttachments]
                );
                if(!($result['success']??false))throw new RuntimeException('The email could not be sent. Review Email Configuration and try again.');
                $pdo->beginTransaction();
                try{
                    $pdo->prepare('INSERT INTO estimate_replies(estimate_id,admin_id,recipient_email,subject,message) VALUES(?,?,?,?,?)')->execute([$id,(int)$me['id'],$to,$subject,$messageHtml]);
                    $replyId=(int)$pdo->lastInsertId();
                    if($storedReplyFiles){
                        $insReplyFile=$pdo->prepare('INSERT INTO estimate_reply_attachments(reply_id,estimate_id,file_path,original_name,mime_type,file_size) VALUES(?,?,?,?,?,?)');
                        foreach($storedReplyFiles as $file){
                            $insReplyFile->execute([$replyId,$id,$file['relative_path'],$file['name'],$file['mime'],$file['size']]);
                        }
                    }
                    if(($cur['status']??'new')==='new')$pdo->prepare("UPDATE estimate_requests SET status='contacted' WHERE id=?")->execute([$id]);
                    $pdo->commit();
                }catch(Throwable $dbError){
                    if($pdo->inTransaction())$pdo->rollBack();
                    throw $dbError;
                }
            }catch(Throwable $replyError){
                estimate_cleanup_reply_files($storedReplyFiles);
                throw $replyError;
            }
            log_activity('estimate_reply','Sent rich response from estimate request',['estimate_id'=>$id,'recipient'=>$to,'attachments'=>count($storedReplyFiles)]);
            flash('success','Response sent successfully and added to the request history.');
        } elseif($action==='mark_spam') {
            $pdo->prepare("INSERT INTO estimate_request_flags(estimate_id,is_spam,archived_at,updated_by) VALUES(?,1,NOW(),?) ON DUPLICATE KEY UPDATE is_spam=1,archived_at=COALESCE(archived_at,NOW()),updated_by=VALUES(updated_by)")->execute([$id,(int)$me['id']]);
            $pdo->prepare("UPDATE estimate_requests SET status='closed' WHERE id=?")->execute([$id]);
            log_activity('estimate_mark_spam','Marked estimate request as spam',['estimate_id'=>$id]);
            flash('success','Request marked as spam and archived.');
        } elseif($action==='not_spam') {
            $pdo->prepare("INSERT INTO estimate_request_flags(estimate_id,is_spam,archived_at,updated_by) VALUES(?,0,NULL,?) ON DUPLICATE KEY UPDATE is_spam=0,archived_at=NULL,updated_by=VALUES(updated_by)")->execute([$id,(int)$me['id']]);
            log_activity('estimate_restore_spam','Restored estimate request from spam',['estimate_id'=>$id]);
            flash('success','Request restored from spam.');
        } elseif($action==='archive') {
            $pdo->prepare("INSERT INTO estimate_request_flags(estimate_id,is_spam,archived_at,updated_by) VALUES(?,0,NOW(),?) ON DUPLICATE KEY UPDATE archived_at=NOW(),updated_by=VALUES(updated_by)")->execute([$id,(int)$me['id']]);
            log_activity('estimate_archive','Archived estimate request',['estimate_id'=>$id]);
            flash('success','Request archived.');
        } elseif($action==='restore') {
            $pdo->prepare("INSERT INTO estimate_request_flags(estimate_id,is_spam,archived_at,updated_by) VALUES(?,0,NULL,?) ON DUPLICATE KEY UPDATE is_spam=0,archived_at=NULL,updated_by=VALUES(updated_by)")->execute([$id,(int)$me['id']]);
            log_activity('estimate_restore','Restored estimate request',['estimate_id'=>$id]);
            flash('success','Request restored.');
        } elseif($action==='delete') {
            if(!$canAll)throw new RuntimeException('Only administrators with full request management permission can permanently delete requests.');
            if(trim((string)($_POST['delete_confirm']??''))!=='DELETE')throw new RuntimeException('Type DELETE to permanently remove this request.');
            $paths=[];
            $fs=$pdo->prepare('SELECT file_path FROM estimate_attachments WHERE estimate_id=?');
            $fs->execute([$id]);
            foreach($fs->fetchAll() as $fileRow){
                $candidate=trim((string)($fileRow['file_path']??''));
                if($candidate!=='')$paths[]=$candidate;
            }
            $rfs=$pdo->prepare('SELECT file_path FROM estimate_reply_attachments WHERE estimate_id=?');
            $rfs->execute([$id]);
            foreach($rfs->fetchAll() as $fileRow){
                $candidate=trim((string)($fileRow['file_path']??''));
                if($candidate!=='')$paths[]=$candidate;
            }
            if(!empty($cur['photo_path']))$paths[]=(string)$cur['photo_path'];
            $pdo->beginTransaction();
            try {
                $pdo->prepare('DELETE FROM estimate_reply_attachments WHERE estimate_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM estimate_replies WHERE estimate_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM estimate_request_flags WHERE estimate_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM estimate_notes WHERE estimate_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM estimate_attachments WHERE estimate_id=?')->execute([$id]);
                $pdo->prepare('DELETE FROM estimate_requests WHERE id=?')->execute([$id]);
                $pdo->commit();
            } catch(Throwable $deleteError) {
                if($pdo->inTransaction())$pdo->rollBack();
                throw $deleteError;
            }
            foreach(array_unique($paths) as $relative){
                $relative=ltrim(str_replace('\\','/',$relative),'/');
                if($relative===''||str_contains($relative,'..'))continue;
                $absolute=defined('ROOT_DIR')?ROOT_DIR.'/'.$relative:dirname(__DIR__).'/'.$relative;
                if(is_file($absolute))@unlink($absolute);
            }
            log_activity('estimate_delete','Permanently deleted estimate request',['estimate_id'=>$id]);
            flash('success','Request permanently deleted.');
            header('Location: estimates.php');
            exit;
        }
        header('Location: estimates.php?view='.$id);
        exit;
    } catch(Throwable $e) {
        $error=$e->getMessage();
    }
}
$where=[];
$args=[];
if(!$canAll) {
    $where[]='e.assigned_to=?';
    $args[]=(int)$me['id'];
}
$bucket=(string)($_GET['bucket']??'active');
if($bucket==='spam') {
    $where[]='COALESCE(f.is_spam,0)=1';
} elseif($bucket==='archived') {
    $where[]='f.archived_at IS NOT NULL AND COALESCE(f.is_spam,0)=0';
} elseif($bucket==='all') {
    // No visibility filter beyond permissions.
} else {
    $bucket='active';
    $where[]='f.archived_at IS NULL AND COALESCE(f.is_spam,0)=0';
}
if(!empty($_GET['status'])) {
    $where[]='e.status=?';
    $args[]=$_GET['status'];
}
if(($_GET['follow']??'')==='due') {
    $where[]='e.follow_up_date IS NOT NULL AND e.follow_up_date<=CURDATE()';
}
$sql='SELECT e.*,COALESCE(f.is_spam,0) is_spam,f.archived_at,u.full_name assigned_name,u.username assigned_username FROM estimate_requests e LEFT JOIN estimate_request_flags f ON f.estimate_id=e.id LEFT JOIN admin_users u ON u.id=e.assigned_to'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY CASE e.priority WHEN "urgent" THEN 0 WHEN "high" THEN 1 WHEN "normal" THEN 2 ELSE 3 END,e.id DESC';
$st=$pdo->prepare($sql);
$st->execute($args);
$rows=$st->fetchAll();
$view=null;
$atts=[];
$notes=[];
$replies=[];
$replyAttachments=[];
$leadSource='';
if(isset($_GET['view'])) {
    $st=$pdo->prepare('SELECT e.*,COALESCE(f.is_spam,0) is_spam,f.archived_at,u.full_name assigned_name,u.username assigned_username FROM estimate_requests e LEFT JOIN estimate_request_flags f ON f.estimate_id=e.id LEFT JOIN admin_users u ON u.id=e.assigned_to WHERE e.id=?');
    $st->execute([(int)$_GET['view']]);
    $view=$st->fetch();
    if($view&&!$canAll&&(int)($view['assigned_to']??0)!==(int)$me['id'])$view=null;
    if($view) {
        $a=$pdo->prepare('SELECT * FROM estimate_attachments WHERE estimate_id=? ORDER BY id');
        $a->execute([$view['id']]);
        $atts=$a->fetchAll();
        $n=$pdo->prepare('SELECT n.*,u.full_name,u.username FROM estimate_notes n LEFT JOIN admin_users u ON u.id=n.admin_id WHERE n.estimate_id=? ORDER BY n.id DESC');
        $n->execute([$view['id']]);
        $notes=$n->fetchAll();
        $leadSource='';
        $visibleNotes=[];
        foreach($notes as $noteRow) {
            $noteText=(string)($noteRow['note']??'');
            if(strpos($noteText,'[LEAD_SOURCE] ')===0) {
                if($leadSource==='') $leadSource=trim(substr($noteText,14));
                continue;
            }
            $visibleNotes[]=$noteRow;
        }
        $notes=$visibleNotes;
        $rp=$pdo->prepare('SELECT r.*,u.full_name,u.username FROM estimate_replies r LEFT JOIN admin_users u ON u.id=r.admin_id WHERE r.estimate_id=? ORDER BY r.id DESC');
        $rp->execute([$view['id']]);
        $replies=$rp->fetchAll();
        $raf=$pdo->prepare('SELECT * FROM estimate_reply_attachments WHERE estimate_id=? ORDER BY id');
        $raf->execute([$view['id']]);
        foreach($raf->fetchAll() as $replyFile){
            $replyAttachments[(int)$replyFile['reply_id']][]=$replyFile;
        }
    }
}
$assignees=$canAll?$pdo->query("SELECT u.id,u.full_name,u.username,r.role_name FROM admin_users u LEFT JOIN admin_roles r ON r.id=u.role_id WHERE u.active=1 ORDER BY u.full_name,u.username")->fetchAll():[];
$due=(int)$pdo->query("SELECT COUNT(*) FROM estimate_requests WHERE follow_up_date IS NOT NULL AND follow_up_date<=CURDATE() AND status NOT IN ('won','lost','closed')")->fetchColumn();
$pageTitle='Estimate Requests';
$active='estimates';
require __DIR__.'/_header.php';
?>


<div class="page-heading">
<div>
<p class="eyebrow">FREE ESTIMATES</p>
<h1>Customer requests</h1>
<p class="muted">Assign leads, track follow-ups and keep private team notes in one place.</p>
</div>
<div class="heading-actions request-bucket-actions">
<a class="button <?=$bucket==='active'?'':'secondary'?>" href="estimates.php">Active</a>
<a class="button <?=$bucket==='spam'?'danger':'secondary'?>" href="?bucket=spam">Spam</a>
<a class="button <?=$bucket==='archived'?'':'secondary'?>" href="?bucket=archived">Archived</a>
<a class="button <?=$bucket==='all'?'':'secondary'?>" href="?bucket=all">All</a>
<a class="button warning" href="?follow=due">Follow-ups due: <?=$due?>

</a>
</div>
</div><?php
if($error):
?>

<div class="alert error"><?=h($error)?>

</div><?php
endif;
?>
<?php
if($view):
?>

<section class="panel animate-in">
<div class="section-heading">
<div>
<p class="eyebrow">REQUEST #<?=$view['id']?>

</p>
<h2><?=h($view['full_name']?:'Website visitor')?>

</h2>
</div>
<div class="request-badges">
<span class="badge <?=h($view['priority'])?>"><?=h($view['priority'])?>

</span>
<span class="badge <?=h($view['status'])?>"><?=h(str_replace('_',' ',$view['status']))?>

</span>
</div>
</div>
<div class="estimate-action-center">
<div class="estimate-action-copy">
<strong>Request actions</strong>
<span>Respond, classify or remove this inquiry without leaving the request.</span>
</div>
<div class="estimate-action-buttons">
<a class="button secondary" href="estimates.php">← Back to requests</a>
<?php if(filter_var((string)($view['email']??''),FILTER_VALIDATE_EMAIL)): ?>
<a class="button" href="#reply-to-request">Reply by email</a>
<?php endif; ?>
<?php if(!empty($view['phone'])): $wa=preg_replace('/\D+/','',(string)$view['phone']); ?>
<?php if($wa!==''): ?><a class="button secondary" href="https://wa.me/<?=h($wa)?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
<?php endif; ?>
<?php if((int)($view['is_spam']??0)===1): ?>
<form method="post" class="inline-action-form"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="id" value="<?=$view['id']?>"><input type="hidden" name="action" value="not_spam"><button class="secondary">Not spam</button></form>
<?php else: ?>
<form method="post" class="inline-action-form"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="id" value="<?=$view['id']?>"><input type="hidden" name="action" value="mark_spam"><button class="warning">Mark as spam</button></form>
<?php endif; ?>
<?php if(!empty($view['archived_at'])): ?>
<form method="post" class="inline-action-form"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="id" value="<?=$view['id']?>"><input type="hidden" name="action" value="restore"><button class="secondary">Restore</button></form>
<?php else: ?>
<form method="post" class="inline-action-form"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="id" value="<?=$view['id']?>"><input type="hidden" name="action" value="archive"><button class="secondary">Archive</button></form>
<?php endif; ?>
</div>
</div>
<div class="three-col">
<div class="list-card">
<small>PHONE</small>
<strong><?=h($view['phone']?:'Not provided')?>

</strong>
</div>
<div class="list-card">
<small>EMAIL</small>
<strong><?=h($view['email']?:'Not provided')?>

</strong>
</div>
<div class="list-card">
<small>FOLLOW-UP</small>
<strong><?=h($view['follow_up_date']?:'Not scheduled')?>

</strong>
</div>
</div>
<?php if($leadSource!==''): ?>
<div class="list-card" style="margin-top:14px">
<small>HOW THEY FOUND US</small>
<strong><?=h($leadSource)?></strong>
<p>Provided by the visitor on the public inquiry form.</p>
</div>
<?php endif; ?>
<div class="two-col" style="margin-top:14px">
<div class="list-card">
<small>SERVICE</small>
<strong><?=h($view['service_needed']?:'General project')?>

</strong>
<p><?=h($view['address']?:'No address provided')?>

</p>
</div>
<div class="list-card">
<small>PROJECT DETAILS</small>
<p><?=nl2br(h($view['message']?:'No additional details.'))?>

</p>
</div>
</div>
<?php
if($atts||$view['photo_path']):
?>

<div class="section-heading" style="margin-top:20px">
<div>
<h2>Attached images</h2>
</div>
</div>
<div class="gallery-editor"><?php
$all=$atts;
if(!$atts&&$view['photo_path'])$all=[['file_path'=>$view['photo_path'],'original_name'=>'Project photo']];
foreach($all as $a):
?>

<article class="gallery-item">
<div class="gallery-media">
<img src="../<?=h($a['file_path'])?>" alt="Attachment">
<button type="button" class="zoom-btn" data-preview-src="../<?=h($a['file_path'])?>" data-preview-caption="<?=h($a['original_name']??'Project image')?>"><?=icon('eye')?>

</button>
</div>
</article><?php
endforeach;
?>

</div><?php
endif;
?>
<?php
if($canAssigned):
?>

<form method="post" class="estimate-workflow">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="id" value="<?=$view['id']?>">
<input type="hidden" name="action" value="update">
<div class="four-col">
<label>Status<select name="status"><?php
foreach(['new'=>'New','contacted'=>'Contacted','in_progress'=>'In progress','won'=>'Won','lost'=>'Lost','closed'=>'Closed'] as $k=>$v):
?>

<option value="<?=$k?>" <?=$view['status']===$k?'selected':''?>

><?=$v?>

</option><?php
endforeach;
?>

</select>
</label>
<label>Priority<select name="priority"><?php
foreach(['low'=>'Low','normal'=>'Normal','high'=>'High','urgent'=>'Urgent'] as $k=>$v):
?>

<option value="<?=$k?>" <?=$view['priority']===$k?'selected':''?>

><?=$v?>

</option><?php
endforeach;
?>

</select>
</label>
<label>Follow-up date<input type="date" name="follow_up_date" value="<?=h($view['follow_up_date']??'')?>">
</label><?php
if($canAll):
?>

<label>Assigned to<select name="assigned_to">
<option value="">Unassigned</option><?php
foreach($assignees as $u):
?>

<option value="<?=$u['id']?>" <?=(int)($view['assigned_to']??0)===(int)$u['id']?'selected':''?>

><?=h($u['full_name']?:$u['username'])?>

 · <?=h($u['role_name']?:'User')?>

</option><?php
endforeach;
?>

</select>
</label><?php
else:
?>

<label>Assigned to<input value="<?=h($view['assigned_name']?:$view['assigned_username']?:'You')?>" disabled>
</label><?php
endif;
?>

</div>
<div class="form-actions">
<button>Save workflow</button>
<a class="button secondary" href="estimates.php">← Back to requests</a>
</div>
</form>
<section class="estimate-reply-panel" id="reply-to-request">
<div class="section-heading">
<div>
<p class="eyebrow">CUSTOMER COMMUNICATION</p>
<h2>Reply by email</h2>
<p>Send a formal branded response to <?=h($view['email']?:'the customer')?>. Sent replies are kept in the request history.</p>
</div>
</div>
<?php if(filter_var((string)($view['email']??''),FILTER_VALIDATE_EMAIL)): ?>
<form method="post" enctype="multipart/form-data" class="estimate-reply-form" id="estimate-reply-form">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="id" value="<?=$view['id']?>">
<input type="hidden" name="action" value="reply">
<label>Subject<input type="text" name="reply_subject" maxlength="240" required value="<?=h('Re: '.($view['service_needed']?:'Your request to ES MULTISERVICIOS'))?>"></label>
<div class="rich-mail-field">
<span class="field-label">Response</span>
<div class="rich-mail-toolbar" role="toolbar" aria-label="Email formatting">
<button type="button" data-rich-command="bold" title="Bold"><strong>B</strong></button>
<button type="button" data-rich-command="italic" title="Italic"><em>I</em></button>
<button type="button" data-rich-command="underline" title="Underline"><u>U</u></button>
<button type="button" data-rich-command="insertUnorderedList" title="Bulleted list">• List</button>
<button type="button" data-rich-command="insertOrderedList" title="Numbered list">1. List</button>
<button type="button" data-rich-command="formatBlock" data-rich-value="blockquote" title="Quote">❝ Quote</button>
<button type="button" data-rich-command="removeFormat" title="Clear formatting">Clear</button>
</div>
<div id="estimate-rich-editor" class="rich-mail-editor" contenteditable="true" role="textbox" aria-multiline="true" data-placeholder="Write the response the customer will receive…"></div>
<textarea name="reply_message_html" id="reply-message-html" hidden></textarea>
</div>
<label class="reply-file-uploader" for="reply-files">
<span><strong>Attach files</strong><small>PDF, Word, Excel, TXT or images · up to 5 files · 2.5 MB total.</small></span>
<input id="reply-files" type="file" name="reply_files[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.jpeg,.png,.webp">
</label>
<div id="reply-file-list" class="reply-file-list" aria-live="polite"></div>
<div class="form-actions"><button>Send formal response</button></div>
</form>
<?php else: ?>
<div class="alert warning">This request does not contain a valid email address. Use another available contact channel.</div>
<?php endif; ?>
<?php if($replies): ?>
<div class="estimate-reply-history">
<h3>Sent responses</h3>
<?php foreach($replies as $reply): ?>
<article>
<div class="reply-history-head"><strong><?=h($reply['subject'])?></strong><small><?=h($reply['created_at'])?></small></div>
<div class="reply-history-message"><?=EmailTemplates::sanitizeEditorHtml((string)$reply['message'])?></div>
<?php if(!empty($replyAttachments[(int)$reply['id']])): ?>
<div class="reply-history-files">
<?php foreach($replyAttachments[(int)$reply['id']] as $replyFile): ?>
<a href="../<?=h($replyFile['file_path'])?>" target="_blank" rel="noopener">📎 <?=h($replyFile['original_name']?:'Attachment')?></a>
<?php endforeach; ?>
</div>
<?php endif; ?>
<small>Sent by <?=h($reply['full_name']?:$reply['username']?:'Administrator')?> to <?=h($reply['recipient_email'])?></small>
</article>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>
<section class="estimate-notes">
<div class="section-heading">
<div>
<h2>Internal notes</h2>
<p>Only administrator users can see these notes.</p>
</div>
</div>
<form method="post">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="id" value="<?=$view['id']?>">
<input type="hidden" name="action" value="note">
<label>Add note<textarea name="note" required placeholder="Call outcome, next step, customer preference…">
</textarea>
</label>
<div class="form-actions">
<button>Add internal note</button>
</div>
</form>
<div class="note-timeline"><?php
foreach($notes as $n):
?>

<article>
<span>
</span>
<div>
<strong><?=h($n['full_name']?:$n['username']?:'Administrator')?>

</strong>
<p><?=nl2br(h($n['note']))?>

</p>
<small><?=h($n['created_at'])?>

</small>
</div>
</article><?php
endforeach;
?>

</div>
</section><?php
endif;
?>
<?php if($canAll): ?>
<div class="estimate-danger-zone">
<details>
<summary>Danger zone</summary>
<div class="danger-zone-body">
<div><strong>Delete permanently</strong><p>Removes this request, notes, reply history and attachment records. This action cannot be undone.</p></div>
<form method="post" class="delete-request-form">
<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<input type="hidden" name="id" value="<?=$view['id']?>">
<input type="hidden" name="action" value="delete">
<label>Type DELETE to confirm<input type="text" name="delete_confirm" autocomplete="off" required pattern="DELETE"></label>
<button class="danger">Delete permanently</button>
</form>
</div>
</details>
</div>
<?php endif; ?>
<div class="request-detail-footer-actions"><a class="button secondary" href="estimates.php">← Back to requests</a></div>

</section><?php
endif;
?>


<div class="request-grid"><?php
foreach($rows as $r):
?>

<article class="request-card animate-in priority-<?=h($r['priority'])?>">
<div class="request-top">
<div>
<strong><?=h($r['full_name']?:'Website visitor')?>

</strong>
<small><?=h($r['created_at'])?>

</small>
</div>
<span class="badge <?=h($r['status'])?>"><?=h(str_replace('_',' ',$r['status']))?>

</span>
</div>
<p>
<strong><?=h($r['service_needed']?:'General project')?>

</strong>
<br><?=h($r['phone']?:$r['email']?:'No contact provided')?>

</p>
<div class="request-assignment">
<span class="badge <?=h($r['priority'])?>"><?=h($r['priority'])?>

</span>
<small>Assigned: <?=h($r['assigned_name']?:$r['assigned_username']?:'Unassigned')?>
<?php
if($r['follow_up_date']):
?>

 · Follow-up <?=h($r['follow_up_date'])?>
<?php
endif;
?>

</small>
</div>
<div class="request-card-flags">
<?php if((int)($r['is_spam']??0)===1): ?><span class="badge spam">Spam</span><?php endif; ?>
<?php if(!empty($r['archived_at'])): ?><span class="badge archived">Archived</span><?php endif; ?>
</div>
<div class="actions request-card-actions">
<a class="button secondary small" href="?view=<?=$r['id']?>">Open request</a>
<?php if(filter_var((string)($r['email']??''),FILTER_VALIDATE_EMAIL)): ?><a class="button small" href="?view=<?=$r['id']?>#reply-to-request">Reply</a><?php endif; ?>
</div>
</article><?php
endforeach;
?>

</div><?php
if(!$rows):
?>

<div class="empty-state">
<strong>No requests match this view</strong>
<p>Assignments and permissions determine which requests are visible.</p>
</div><?php
endif;
?>
<script>
(function(){
    const form=document.getElementById('estimate-reply-form');
    const editor=document.getElementById('estimate-rich-editor');
    const hidden=document.getElementById('reply-message-html');
    if(form&&editor&&hidden){
        document.querySelectorAll('[data-rich-command]').forEach(function(button){
            button.addEventListener('click',function(){
                editor.focus();
                const command=button.dataset.richCommand||'';
                const value=button.dataset.richValue||null;
                document.execCommand(command,false,value);
                hidden.value=editor.innerHTML;
            });
        });
        editor.addEventListener('input',function(){hidden.value=editor.innerHTML;});
        editor.addEventListener('paste',function(event){
            event.preventDefault();
            const text=(event.clipboardData||window.clipboardData).getData('text/plain');
            document.execCommand('insertText',false,text);
        });
        form.addEventListener('submit',function(event){
            hidden.value=editor.innerHTML;
            if(!editor.innerText.trim()){
                event.preventDefault();
                editor.focus();
                editor.classList.add('is-invalid');
            }else{
                editor.classList.remove('is-invalid');
            }
        });
    }
    const input=document.getElementById('reply-files');
    const list=document.getElementById('reply-file-list');
    if(input&&list){
        const render=function(){
            const files=Array.from(input.files||[]);
            list.innerHTML='';
            if(!files.length)return;
            const total=files.reduce((sum,file)=>sum+file.size,0);
            files.forEach(function(file){
                const chip=document.createElement('span');
                chip.textContent='📎 '+file.name+' · '+(file.size/1024/1024).toFixed(2)+' MB';
                list.appendChild(chip);
            });
            if(files.length>5||total>2621440){
                const warning=document.createElement('strong');
                warning.className='reply-file-warning';
                warning.textContent=files.length>5?'Maximum 5 files.':'Attachments must be 2.5 MB or less in total.';
                list.appendChild(warning);
            }
        };
        input.addEventListener('change',render);
    }
})();
</script>
<?php
require __DIR__.'/_footer.php';
