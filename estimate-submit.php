<?php
declare(strict_types=1);

require __DIR__.'/config/bootstrap.php';
require_once __DIR__.'/core/EmailService.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Invalid request.');

    $name = trim((string)($_POST['name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $service = trim((string)($_POST['service'] ?? ''));
    $date = trim((string)($_POST['date'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    if ($name === '' && $phone === '' && $email === '' && $message === '') {
        throw new RuntimeException('Please provide at least your name or contact information.');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Please enter a valid email address.');
    }

    $files = normalized_files('photos');
    if (count($files) > 8) throw new RuntimeException('Please upload no more than 8 images.');

    $pdo = db();
    $pdo->beginTransaction();
    $st = $pdo->prepare('INSERT INTO estimate_requests(full_name,phone,email,address,service_needed,desired_date,message,photo_path) VALUES(?,?,?,?,?,?,?,NULL)');
    $st->execute([$name, $phone, $email, $address, $service, $date !== '' ? $date : null, $message]);
    $id = (int)$pdo->lastInsertId();

    $first = null;
    $attachmentNames = [];
    foreach ($files as $f) {
        $path = upload_image($f, 'estimates', 'estimate-'.$id, 8);
        if ($first === null) $first = $path;
        $originalName = trim((string)($f['name'] ?? ''));
        if ($originalName !== '') $attachmentNames[] = $originalName;
        $ins = $pdo->prepare('INSERT INTO estimate_attachments(estimate_id,file_path,original_name,mime_type,file_size) VALUES(?,?,?,?,?)');
        $ins->execute([
            $id,
            $path,
            $originalName,
            mime_content_type(ROOT_DIR.'/'.$path) ?: '',
            (int)($f['size'] ?? 0),
        ]);
    }
    if ($first) $pdo->prepare('UPDATE estimate_requests SET photo_path=? WHERE id=?')->execute([$first, $id]);
    $pdo->commit();

    admin_notify(
        'info',
        'New estimate request',
        ($name !== '' ? $name : 'A website visitor').' submitted a website request.',
        'estimates.php?view='.$id
    );
    log_activity('estimate_received', 'New website estimate request', ['estimate_id' => $id]);

    $request = [
        'id' => $id,
        'full_name' => $name,
        'phone' => $phone,
        'email' => $email,
        'address' => $address,
        'service_needed' => $service,
        'desired_date' => $date,
        'message' => $message,
        'attachments' => $attachmentNames,
    ];

    $set = settings();
    $companyName = trim((string)($set['company_name'] ?? 'ES MULTISERVICIOS')) ?: 'ES MULTISERVICIOS';
    $mailer = new EmailService();

    try {
        $internalCfg = $mailer->configByType(3) ?: $mailer->configByType(1);
        if ($internalCfg) {
            // Internal destination is optional. When blank, use the account configured
            // for the selected transport (SMTP user or Graph mailbox).
            $internalTo = $mailer->resolveDestination($internalCfg);

            if (filter_var($internalTo, FILTER_VALIDATE_EMAIL)) {
                $internalResult = $mailer->send(
                    $internalCfg,
                    $internalTo,
                    "New {$companyName} website inquiry #{$id}",
                    EmailTemplates::estimateAdmin($request, $set),
                    [
                        'reply_to' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '',
                        'cc' => $internalCfg['copia'] ?? '',
                    ]
                );
                if (!$internalResult['success']) {
                    admin_notify('error', 'Email delivery failed', 'Request #'.$id.' was saved, but the internal notification email could not be sent. Review Email Configuration.', 'email.php');
                    log_activity('estimate_email_failed', 'Internal estimate notification email failed', ['estimate_id' => $id]);
                }
            } else {
                admin_notify('warning', 'Email destination missing', 'Request #'.$id.' was saved, but no valid internal destination email is configured.', 'email.php');
            }
        } else {
            admin_notify('warning', 'Email configuration missing', 'Request #'.$id.' was saved, but there is no active Estimate Requests or Website Alerts email configuration.', 'email.php');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $replyCfg = $mailer->configByType(4) ?: $mailer->configByType(1);
            if ($replyCfg) {
                $replyTo = $mailer->resolveDestination($replyCfg);
                $customerResult = $mailer->send(
                    $replyCfg,
                    $email,
                    "We received your {$companyName} request",
                    EmailTemplates::estimateCustomer($request, $set),
                    ['reply_to' => filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? $replyTo : '']
                );
                if (!$customerResult['success']) {
                    admin_notify('warning', 'Automatic reply failed', 'Request #'.$id.' was saved, but the visitor confirmation email could not be sent.', 'email.php');
                    log_activity('estimate_autoreply_failed', 'Estimate automatic reply failed', ['estimate_id' => $id]);
                }
            } else {
                admin_notify('warning', 'Auto Reply configuration missing', 'Request #'.$id.' was saved, but no active Auto Replies sender is configured.', 'email.php');
            }
        }
    } catch (Throwable $mailError) {
        admin_notify('error', 'Email delivery error', 'Request #'.$id.' was saved successfully, but email delivery encountered an error. Review Email Configuration.', 'email.php');
        log_activity('estimate_email_exception', 'Email delivery exception after estimate save', ['estimate_id' => $id]);
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Thank you. Your request has been received.',
        'request_id' => $id,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
