<?php
declare(strict_types=1);

require __DIR__.'/config/bootstrap.php';
require_once __DIR__.'/core/EmailService.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

/**
 * Return a normal success response for automated/spam submissions without saving
 * a request or sending any email. This avoids teaching bots which check failed.
 */
function contact_spam_sink(string $lang, string $reason): void
{
    error_log('ES MULTISERVICIOS contact anti-spam blocked: '.$reason);
    echo json_encode([
        'ok' => true,
        'message' => $lang === 'es' ? 'Gracias. Recibimos tu consulta.' : 'Thank you. Your request has been received.',
    ]);
    exit;
}

/**
 * Conservative score for obvious unsolicited sales outreach.
 * It intentionally requires several independent signals to reduce false positives.
 */

/**
 * Validate a Cloudflare Turnstile token without sending the visitor IP.
 * The secret key is encrypted at rest in settings and decrypted only server-side.
 */
function contact_turnstile_verify(string $token, string $secret): array
{
    if ($token === '' || $secret === '') {
        return ['success' => false, 'error-codes' => ['missing-input']];
    }

    $payload = http_build_query([
        'secret' => $secret,
        'response' => $token,
    ], '', '&');

    if (function_exists('curl_init')) {
        $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno !== 0 || !is_string($raw) || $raw === '' || $status < 200 || $status >= 300) {
            return ['success' => false, 'error-codes' => ['siteverify-unavailable']];
        }
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 8,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx);
        if (!is_string($raw) || $raw === '') {
            return ['success' => false, 'error-codes' => ['siteverify-unavailable']];
        }
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : ['success' => false, 'error-codes' => ['invalid-siteverify-response']];
}

function contact_solicitation_score(string $text): int
{
    $normalized = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    $score = 0;

    $urls = preg_match_all('~https?://|www\.~iu', $normalized, $urlMatches);
    if ($urls >= 2) $score += 2;
    elseif ($urls === 1) $score += 1;

    $strongPatterns = [
        '/\b(?:we|i)\s+(?:offer|provide|specialize in|can provide|would like to offer)\b/i',
        '/\bour\s+(?:agency|company|team)\s+(?:offers|provides|specializes)\b/i',
        '/\b(?:seo services?|link building|backlinks?|guest posts?|domain authority)\b/i',
        '/\b(?:increase|boost|improve)\s+(?:your\s+)?(?:traffic|rankings?|google ranking|online presence)\b/i',
        '/\b(?:digital marketing|lead generation|marketing agency|social media marketing)\b/i',
        '/\b(?:video production|video editing|explainer videos?|promotional videos?)\b/i',
        '/\b(?:free website audit|free seo audit|website audit)\b/i',
        '/\b(?:partnership opportunity|business proposal|special offer)\b/i',
    ];
    foreach ($strongPatterns as $pattern) {
        if (preg_match($pattern, $normalized)) $score++;
    }

    if (preg_match('/\b(?:came across|found|visited)\s+your\s+(?:website|site)\b/i', $normalized)) $score++;
    if (preg_match('/\b(?:help you|get you)\s+(?:more|new)\s+(?:customers|clients|leads|traffic)\b/i', $normalized)) $score++;

    return $score;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Invalid request.');

    $name = trim((string)($_POST['name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $service = trim((string)($_POST['service'] ?? ''));
    $date = trim((string)($_POST['date'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));
    $referralSource = trim((string)($_POST['referral_source'] ?? ''));
    $referralDetails = trim((string)($_POST['referral_details'] ?? ''));
    $lang = strtolower(trim((string)($_POST['lang'] ?? 'en'))) === 'es' ? 'es' : 'en';
    $honeypot = trim((string)($_POST['website_url_confirm'] ?? ''));
    $formStartedAt = (int)($_POST['form_started_at'] ?? 0);
    $turnstileToken = trim((string)($_POST['cf-turnstile-response'] ?? ''));

    $set = settings();

    $antiSpamEnabled = ($set['contact_antispam_enabled'] ?? '1') === '1';
    $antiSpamBlockSolicitation = ($set['contact_antispam_block_solicitation'] ?? '1') === '1';
    $antiSpamMinSeconds = max(1, min(30, (int)($set['contact_antispam_min_seconds'] ?? 3)));
    $antiSpamCooldownSeconds = max(15, min(600, (int)($set['contact_antispam_cooldown_seconds'] ?? 60)));
    $antiSpamHourlyLimit = max(1, min(20, (int)($set['contact_antispam_hourly_limit'] ?? 5)));
    $turnstileEnabled = ($set['contact_turnstile_enabled'] ?? '0') === '1';
    $turnstileSiteKey = trim((string)($set['contact_turnstile_site_key'] ?? ''));
    $turnstileSecret = secret_decrypt((string)($set['contact_turnstile_secret'] ?? ''));
    $turnstileReady = $turnstileEnabled && $turnstileSiteKey !== '' && $turnstileSecret !== '';

    if ($antiSpamEnabled) {
        // Honeypot: real visitors never see or fill this field.
        if ($honeypot !== '') {
            contact_spam_sink($lang, 'honeypot');
        }

        // A bot often submits immediately without spending any time on the form.
        $now = time();
        if ($formStartedAt <= 0 || ($now - $formStartedAt) < $antiSpamMinSeconds) {
            contact_spam_sink($lang, 'invalid_form_timing');
        }

        // Browser-session rate limiting. No IP address or fingerprint is stored.
        $recent = array_values(array_filter(
            is_array($_SESSION['contact_submit_times'] ?? null) ? $_SESSION['contact_submit_times'] : [],
            static fn($ts) => is_int($ts) && $ts >= ($now - 3600)
        ));
        $lastSubmit = $recent ? max($recent) : 0;
        if ($lastSubmit > 0 && ($now - $lastSubmit) < $antiSpamCooldownSeconds) {
            http_response_code(429);
            throw new DomainException($lang === 'es'
                ? 'Espera un momento antes de enviar otra consulta.'
                : 'Please wait a moment before sending another inquiry.');
        }
        if (count($recent) >= $antiSpamHourlyLimit) {
            http_response_code(429);
            throw new DomainException($lang === 'es'
                ? 'Se alcanzó temporalmente el límite de consultas desde este navegador. Intenta nuevamente más tarde.'
                : 'The temporary inquiry limit for this browser has been reached. Please try again later.');
        }
        $_SESSION['contact_submit_times'] = $recent;

        // Block only strongly-scored unsolicited commercial outreach.
        if ($antiSpamBlockSolicitation) {
            $solicitationText = trim($name.' '.$service.' '.$message.' '.$referralDetails);
            if (contact_solicitation_score($solicitationText) >= 4) {
                contact_spam_sink($lang, 'sales_solicitation');
            }
        }
    }
    $required = [
        'name' => ($set['contact_required_name'] ?? '1') === '1',
        'email' => true,
        'phone' => ($set['contact_required_phone'] ?? '1') === '1',
        'service' => ($set['contact_required_service'] ?? '1') === '1',
        'message' => ($set['contact_required_message'] ?? '1') === '1',
        'referral' => ($set['contact_required_referral'] ?? '1') === '1',
    ];
    $values = [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'service' => $service,
        'message' => $message,
        'referral' => $referralSource,
    ];
    $labels = $lang === 'es'
        ? ['name'=>'Nombre','email'=>'Correo','phone'=>'Teléfono','service'=>'¿Qué necesitas?','message'=>'Mensaje','referral'=>'¿Cómo nos conociste?']
        : ['name'=>'Name','email'=>'Email','phone'=>'Phone','service'=>'What do you need?','message'=>'Message','referral'=>'How did you hear about us?'];

    foreach ($required as $field => $isRequired) {
        if ($isRequired && $values[$field] === '') {
            throw new DomainException($lang === 'es'
                ? 'Completa el campo obligatorio: '.$labels[$field].'.'
                : 'Please complete the required field: '.$labels[$field].'.');
        }
    }
    if ($name === '' && $phone === '' && $email === '' && $service === '' && $message === '') {
        throw new DomainException($lang === 'es' ? 'Completa la información de tu consulta.' : 'Please provide your inquiry information.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new DomainException($lang === 'es' ? 'Ingresa un correo electrónico válido. El correo es obligatorio para poder responderte.' : 'Please enter a valid email address. Email is required so we can reply to you.');
    }

    $defaultReferralEs = "Búsqueda en Google u otro buscador\nFacebook\nTikTok\nInstagram\nWhatsApp\nRecomendación de una persona o empresa\nYa conocía ES MULTISERVICIOS\nOtro";
    $defaultReferralEn = "Google or another search engine\nFacebook\nTikTok\nInstagram\nWhatsApp\nRecommendation from a person or company\nI already knew ES MULTISERVICIOS\nOther";
    $referralRaw = (string)($set[$lang === 'es' ? 'contact_referral_options_es' : 'contact_referral_options_en'] ?? ($lang === 'es' ? $defaultReferralEs : $defaultReferralEn));
    $allowedReferral = array_values(array_filter(array_map('trim', preg_split('/\R/u', $referralRaw) ?: []), static fn($v) => $v !== ''));
    if ($referralSource !== '' && !in_array($referralSource, $allowedReferral, true)) {
        throw new DomainException($lang === 'es' ? 'Selecciona una opción válida en ¿Cómo nos conociste?.' : 'Please select a valid option for How did you hear about us?.');
    }
    if (preg_match('/^(otro|other)$/iu', $referralSource)) {
        $detailMeaningful = preg_replace('/[^\p{L}\p{N}]+/u', '', $referralDetails) ?? '';
        $detailLen = function_exists('mb_strlen') ? mb_strlen($detailMeaningful, 'UTF-8') : strlen($detailMeaningful);
        if ($detailLen < 3) {
            throw new DomainException($lang === 'es' ? 'Indica brevemente dónde nos encontraste al seleccionar “Otro”.' : 'Please briefly tell us where you found us when selecting “Other”.');
        }
    } else {
        $referralDetails = '';
    }

    $messageMinChars = max(20, min(300, (int)($set['contact_message_min_chars'] ?? 30)));
    $messageMinWords = max(3, min(30, (int)($set['contact_message_min_words'] ?? 5)));
    if ($message !== '') {
        $meaningful = preg_replace('/[^\p{L}\p{N}]+/u', '', $message) ?? '';
        $meaningfulChars = function_exists('mb_strlen') ? mb_strlen($meaningful, 'UTF-8') : strlen($meaningful);
        preg_match_all('/[\p{L}\p{N}]{2,}/u', $message, $wordMatches);
        $meaningfulWords = count($wordMatches[0] ?? []);
        if ($meaningfulChars < $messageMinChars || $meaningfulWords < $messageMinWords) {
            throw new DomainException($lang === 'es'
                ? 'Describe con un poco más de detalle lo que necesitas. El mensaje debe tener al menos '.$messageMinChars.' caracteres útiles y '.$messageMinWords.' palabras; espacios, signos o un simple “Hola” no son suficientes.'
                : 'Please describe what you need in a little more detail. The message must contain at least '.$messageMinChars.' meaningful characters and '.$messageMinWords.' words; spaces, punctuation or a simple “Hello” are not enough.');
        }
    }
    if ($required['message'] && $message === '') {
        throw new DomainException($lang === 'es' ? 'Describe lo que necesitas en el campo Mensaje.' : 'Please describe what you need in the Message field.');
    }

    if (strlen($name) > 240 || strlen($email) > 380 || strlen($phone) > 100 || strlen($service) > 240 || strlen($message) > 10000 || strlen($referralSource) > 360 || strlen($referralDetails) > 360) {
        throw new DomainException($lang === 'es' ? 'Uno de los campos supera la longitud permitida.' : 'One of the fields exceeds the allowed length.');
    }

    if ($turnstileEnabled && !$turnstileReady) {
        error_log('ES MULTISERVICIOS Turnstile enabled but keys are incomplete or the secret cannot be decrypted.');
        throw new RuntimeException($lang === 'es'
            ? 'La verificación de seguridad está temporalmente indisponible. Intenta nuevamente más tarde.'
            : 'Security verification is temporarily unavailable. Please try again later.');
    }

    if ($turnstileReady) {
        $turnstileResult = contact_turnstile_verify($turnstileToken, $turnstileSecret);
        $turnstileOk = ($turnstileResult['success'] ?? false) === true;
        $turnstileAction = (string)($turnstileResult['action'] ?? '');
        if (!$turnstileOk || ($turnstileAction !== '' && $turnstileAction !== 'contact_inquiry')) {
            $codes = $turnstileResult['error-codes'] ?? [];
            error_log('ES MULTISERVICIOS Turnstile rejected contact form: '.json_encode($codes));
            throw new DomainException($lang === 'es'
                ? 'No pudimos completar la verificación de seguridad. Actualiza la página e intenta nuevamente.'
                : 'We could not complete the security verification. Refresh the page and try again.');
        }
    }

    $files = normalized_files('photos');
    if (count($files) > 8) throw new RuntimeException('Please upload no more than 8 images.');

    $pdo = db();
    $pdo->beginTransaction();
    $st = $pdo->prepare('INSERT INTO estimate_requests(full_name,phone,email,address,service_needed,desired_date,message,photo_path) VALUES(?,?,?,?,?,?,?,NULL)');
    $st->execute([$name, $phone, $email, $address, $service, $date !== '' ? $date : null, $message]);
    $id = (int)$pdo->lastInsertId();

    if ($referralSource !== '') {
        $sourceNote = '[LEAD_SOURCE] '.$referralSource.($referralDetails !== '' ? ' — '.$referralDetails : '');
        $noteSt = $pdo->prepare('INSERT INTO estimate_notes(estimate_id,admin_id,note) VALUES(?,NULL,?)');
        $noteSt->execute([$id, $sourceNote]);
    }

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

    if ($antiSpamEnabled) {
        $now = time();
        $recent = is_array($_SESSION['contact_submit_times'] ?? null) ? $_SESSION['contact_submit_times'] : [];
        $recent[] = $now;
        $_SESSION['contact_submit_times'] = array_values(array_filter($recent, static fn($ts) => is_int($ts) && $ts >= ($now - 3600)));
    }

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
        'referral_source' => $referralSource,
        'referral_details' => $referralDetails,
        'attachments' => $attachmentNames,
    ];

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
        'message' => $lang === 'es' ? 'Gracias. Recibimos tu consulta.' : 'Thank you. Your request has been received.',
        'request_id' => $id,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(422);
    if ($e instanceof DomainException) {
        $publicMessage = $e->getMessage();
    } else {
        $requestLang = isset($lang) && $lang === 'es' ? 'es' : 'en';
        $publicMessage = $requestLang === 'es'
            ? 'No pudimos procesar la consulta. Revisa los campos e intenta nuevamente.'
            : 'We could not process the inquiry. Please review the fields and try again.';
    }
    echo json_encode(['ok' => false, 'message' => $publicMessage]);
}
