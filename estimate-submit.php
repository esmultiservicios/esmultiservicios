<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';
require_once __DIR__.'/core/EmailService.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new RuntimeException('Invalid request.');
    $name=trim((string)($_POST['name']??''));
    $phone=trim((string)($_POST['phone']??''));
    $email=trim((string)($_POST['email']??''));
    $address=trim((string)($_POST['address']??''));
    $service=trim((string)($_POST['service']??''));
    $date=trim((string)($_POST['date']??''));
    $message=trim((string)($_POST['message']??''));
    if($name===''&&$phone===''&&$email===''&&$message==='')throw new RuntimeException('Please provide at least your name or contact information.');
    if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Please enter a valid email address.');
    $files=normalized_files('photos');
    if(count($files)>8)throw new RuntimeException('Please upload no more than 8 images.');
    $pdo=db();
    $pdo->beginTransaction();
    $st=$pdo->prepare('INSERT INTO estimate_requests(full_name,phone,email,address,service_needed,desired_date,message,photo_path) VALUES(?,?,?,?,?,?,?,NULL)');
    $st->execute([$name,$phone,$email,$address,$service,$date!==''?$date:null,$message]);
    $id=(int)$pdo->lastInsertId();
    $first=null;
    foreach($files as $f) {
        $path=upload_image($f,'estimates','estimate-'.$id,8);
        if($first===null)$first=$path;
        $ins=$pdo->prepare('INSERT INTO estimate_attachments(estimate_id,file_path,original_name,mime_type,file_size) VALUES(?,?,?,?,?)');
        $ins->execute([$id,$path,$f['name']??'',mime_content_type(ROOT_DIR.'/'.$path)?:'',(int)($f['size']??0)]);
    }
    if($first)$pdo->prepare('UPDATE estimate_requests SET photo_path=? WHERE id=?')->execute([$first,$id]);
    $pdo->commit();
    admin_notify('info','New estimate request',($name!==''?$name:'A website visitor').' submitted a free estimate request.','estimates.php?view='.$id);
    log_activity('estimate_received','New website estimate request',['estimate_id'=>$id]);
    $request=['full_name'=>$name,
    'phone'=>$phone,
    'email'=>$email,
    'address'=>$address,
    'service_needed'=>$service,
    'desired_date'=>$date,
    'message'=>$message];
    $set=settings();
    $companyName=trim((string)($set['company_name']??'ES MULTISERVICIOS'))?:'ES MULTISERVICIOS';
    try {
        $mailer=new EmailService();
        $adminTo=$set['email']??'';
        if(filter_var($adminTo,FILTER_VALIDATE_EMAIL))$mailer->sendWithFallback([3,1],$adminTo,"New {$companyName} website inquiry",EmailTemplates::estimateAdmin($request,$set));
        if(filter_var($email,FILTER_VALIDATE_EMAIL)) {
            $mailer->sendWithFallback([4,1],$email,"We received your {$companyName} request",EmailTemplates::estimateCustomer($request,$set));
        }
    } catch(Throwable $mailError) {
    }
    echo json_encode(['ok'=>true,'message'=>'Thank you. Your request has been received.','request_id'=>$id]);
} catch(Throwable $e) {
    if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
    http_response_code(422);
    echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);
}
