<?php
declare(strict_types=1);

require __DIR__.'/config/bootstrap.php';
require_once __DIR__.'/core/ContactEmailValidator.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

try {
    $email = trim((string)($_POST['email'] ?? $_GET['email'] ?? ''));
    $lang = strtolower(trim((string)($_POST['lang'] ?? $_GET['lang'] ?? 'es'))) === 'en' ? 'en' : 'es';

    $now = time();
    $checks = array_values(array_filter(
        is_array($_SESSION['contact_email_checks'] ?? null) ? $_SESSION['contact_email_checks'] : [],
        static fn($ts) => is_int($ts) && $ts >= ($now - 60)
    ));
    if (count($checks) >= 30) {
        http_response_code(429);
        echo json_encode([
            'ok' => false,
            'valid' => false,
            'message' => $lang === 'es' ? 'Espera un momento antes de validar otro correo.' : 'Please wait a moment before checking another email.',
        ]);
        exit;
    }
    $checks[] = $now;
    $_SESSION['contact_email_checks'] = $checks;

    $set = settings();
    $set['lang'] = $lang;
    $result = ContactEmailValidator::validate($email, $set, true);

    echo json_encode([
        'ok' => true,
        'valid' => (bool)$result['valid'],
        'email' => $result['email'],
        'suggestion' => $result['suggestion'],
        'reason' => $result['reason'],
        'message' => $result['message'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'valid' => null,
        'message' => 'Email validation is temporarily unavailable.',
    ]);
}
