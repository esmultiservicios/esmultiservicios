<?php
declare(strict_types=1);

final class ContactEmailValidator
{
    private const COMMON_DOMAIN_TYPOS = [
        'gmail.con' => 'gmail.com',
        'gmail.co' => 'gmail.com',
        'gmial.com' => 'gmail.com',
        'gmai.com' => 'gmail.com',
        'gmal.com' => 'gmail.com',
        'hotmal.com' => 'hotmail.com',
        'hotmai.com' => 'hotmail.com',
        'hotmail.con' => 'hotmail.com',
        'outlook.con' => 'outlook.com',
        'outlok.com' => 'outlook.com',
        'outllok.com' => 'outlook.com',
        'yahoo.con' => 'yahoo.com',
        'yaho.com' => 'yahoo.com',
        'icloud.con' => 'icloud.com',
        'iclod.com' => 'icloud.com',
        'live.con' => 'live.com',
        'msn.con' => 'msn.com',
    ];

    private const DISPOSABLE_DOMAINS = [
        '10minutemail.com', '10minutemail.net', '20minutemail.com', 'dispostable.com',
        'emailondeck.com', 'fakeinbox.com', 'getairmail.com', 'getnada.com',
        'guerrillamail.com', 'guerrillamail.net', 'guerrillamail.org', 'guerrillamailblock.com',
        'inboxkitten.com', 'maildrop.cc', 'mailinator.com', 'mailinator.net', 'mailnesia.com',
        'mintemail.com', 'moakt.com', 'mohmal.com', 'mytemp.email', 'sharklasers.com',
        'spam4.me', 'spamgourmet.com', 'temp-mail.org', 'temp-mail.io', 'tempail.com',
        'tempmail.com', 'tempmail.net', 'tempmailo.com', 'tempinbox.com', 'throwawaymail.com',
        'trashmail.com', 'trashmail.net', 'yopmail.com', 'yopmail.fr', 'yopmail.net',
    ];

    public static function normalize(string $email): string
    {
        $email = trim($email);
        $email = preg_replace('/\s+/u', '', $email) ?? $email;
        return strtolower($email);
    }

    public static function suggestion(string $email): ?string
    {
        $email = self::normalize($email);
        if (!str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        if ($local === '' || $domain === '') {
            return null;
        }

        if (isset(self::COMMON_DOMAIN_TYPOS[$domain])) {
            return $local.'@'.self::COMMON_DOMAIN_TYPOS[$domain];
        }

        return null;
    }

    public static function isDisposable(string $domain): bool
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            return false;
        }

        foreach (self::DISPOSABLE_DOMAINS as $blocked) {
            if ($domain === $blocked || str_ends_with($domain, '.'.$blocked)) {
                return true;
            }
        }

        return false;
    }

    public static function validate(string $email, array $settings = [], bool $includeApi = true): array
    {
        $email = self::normalize($email);
        $lang = (($settings['lang'] ?? 'es') === 'en') ? 'en' : 'es';

        $base = [
            'valid' => false,
            'email' => $email,
            'suggestion' => self::suggestion($email),
            'reason' => 'format',
            'message' => $lang === 'es'
                ? 'Ingresa un correo electrónico válido. Ejemplo: nombre@empresa.com'
                : 'Enter a valid email address. Example: name@company.com',
            'dns_checked' => false,
            'api_checked' => false,
        ];

        if ($email === '' || strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $base;
        }

        if (preg_match('/\.\.|^\.|\.@|@\.|\.$/', $email)) {
            return $base;
        }

        [$local, $domain] = explode('@', $email, 2);
        if ($local === '' || $domain === '' || !str_contains($domain, '.')) {
            return $base;
        }

        if ($base['suggestion'] !== null) {
            $base['reason'] = 'suggestion';
            $base['message'] = $lang === 'es'
                ? '¿Quisiste escribir '.$base['suggestion'].'?'
                : 'Did you mean '.$base['suggestion'].'?';
            return $base;
        }

        $blockDisposable = ($settings['contact_email_block_disposable'] ?? '1') === '1';
        if ($blockDisposable && self::isDisposable($domain)) {
            $base['reason'] = 'disposable';
            $base['message'] = $lang === 'es'
                ? 'Utiliza un correo electrónico permanente para continuar.'
                : 'Use a permanent email address to continue.';
            return $base;
        }

        $dnsEnabled = ($settings['contact_email_dns_validation'] ?? '1') === '1';
        if ($dnsEnabled && function_exists('checkdnsrr')) {
            // Verify that the server DNS resolver itself is answering MX queries.
            // If DNS is temporarily unavailable, do not lock out a legitimate visitor.
            $resolverReady = @checkdnsrr('gmail.com', 'MX') || @checkdnsrr('outlook.com', 'MX');
            if ($resolverReady) {
                $base['dns_checked'] = true;
                $hasMx = @checkdnsrr($domain, 'MX');
                if (!$hasMx) {
                    $base['reason'] = 'dns';
                    $base['message'] = $lang === 'es'
                        ? 'El dominio de este correo no parece válido o no puede recibir correo. Revisa la dirección e inténtalo nuevamente.'
                        : 'This email domain does not appear valid or able to receive mail. Check the address and try again.';
                    return $base;
                }
            }
        }

        if ($includeApi && ($settings['contact_email_api_enabled'] ?? '0') === '1') {
            $apiResult = self::verifyWithApi($email, $settings);
            $base['api_checked'] = $apiResult['checked'];
            if ($apiResult['checked'] && $apiResult['valid'] === false) {
                $base['reason'] = 'mailbox';
                $base['message'] = $lang === 'es'
                    ? 'Este correo no parece entregable. Revisa la dirección e inténtalo nuevamente.'
                    : 'This email does not appear deliverable. Check the address and try again.';
                return $base;
            }
        }

        $base['valid'] = true;
        $base['reason'] = 'ok';
        $base['message'] = $lang === 'es' ? 'Correo válido' : 'Valid email';
        return $base;
    }

    private static function verifyWithApi(string $email, array $settings): array
    {
        $urlTemplate = trim((string)($settings['contact_email_api_url'] ?? ''));
        if ($urlTemplate === '') {
            return ['checked' => false, 'valid' => null];
        }

        $url = str_replace('{email}', rawurlencode($email), $urlTemplate);
        if ($url === $urlTemplate && !str_contains($urlTemplate, '{email}')) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator.'email='.rawurlencode($email);
        }

        $headers = ['Accept: application/json'];
        $encryptedKey = (string)($settings['contact_email_api_key'] ?? '');
        $apiKey = $encryptedKey !== '' && function_exists('secret_decrypt') ? secret_decrypt($encryptedKey) : '';
        if ($apiKey !== '') {
            $headers[] = 'Authorization: Bearer '.$apiKey;
        }

        $raw = null;
        $status = 0;
        try {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_TIMEOUT => 5,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_FOLLOWLOCATION => false,
                ]);
                $result = curl_exec($ch);
                $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $errno = curl_errno($ch);
                curl_close($ch);
                if ($errno === 0 && is_string($result)) {
                    $raw = $result;
                }
            }
        } catch (Throwable $ignored) {
            return ['checked' => false, 'valid' => null];
        }

        if (!is_string($raw) || $raw === '' || $status < 200 || $status >= 300) {
            return ['checked' => false, 'valid' => null];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return ['checked' => false, 'valid' => null];
        }

        foreach (['deliverable', 'valid', 'is_valid'] as $key) {
            if (array_key_exists($key, $data) && is_bool($data[$key])) {
                return ['checked' => true, 'valid' => $data[$key]];
            }
        }

        $result = strtolower(trim((string)($data['result'] ?? $data['status'] ?? '')));
        if (in_array($result, ['deliverable', 'valid', 'ok', 'safe'], true)) {
            return ['checked' => true, 'valid' => true];
        }
        if (in_array($result, ['undeliverable', 'invalid', 'rejected', 'dead'], true)) {
            return ['checked' => true, 'valid' => false];
        }

        return ['checked' => false, 'valid' => null];
    }
}
