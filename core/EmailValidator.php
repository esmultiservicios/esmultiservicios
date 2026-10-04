<?php
declare(strict_types=1);

final class EmailValidator
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

    private const OBVIOUS_FAKE_ADDRESSES = [
        'alguien@algo.com',
        'prueba@prueba.com',
        'test@test.com',
        'usuario@example.com',
        'correo@correo.com',
        'user@example.com',
        'test@example.com',
    ];

    private const RESERVED_EXAMPLE_DOMAINS = [
        'example.com',
        'example.net',
        'example.org',
    ];

    public static function validate(string $rawEmail): array
    {
        $original = $rawEmail;
        $email = self::normalize($rawEmail);
        $result = [
            'valid' => false,
            'email' => $email,
            'reason' => 'invalid_format',
            'message' => 'Invalid email format.',
            'suggestion' => null,
            'dns_checked' => false,
            'dns_mode' => null,
        ];

        if ($email === '' || strlen($email) > 190) {
            return $result;
        }

        if (preg_match('/\s/u', $original) === 1) {
            $result['reason'] = 'whitespace';
            $result['message'] = 'Email contains spaces or whitespace characters.';
            return $result;
        }

        if (preg_match('/[\r\n\t]/', $email) === 1 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $result;
        }

        if (preg_match('/\.\.|^\.|\.@|@\.|\.$/', $email) === 1) {
            return $result;
        }

        [$local, $domain] = explode('@', $email, 2);
        if ($local === '' || $domain === '' || !str_contains($domain, '.')) {
            return $result;
        }

        $suggestedDomain = self::COMMON_DOMAIN_TYPOS[$domain] ?? null;
        if ($suggestedDomain !== null) {
            $result['reason'] = 'common_domain_typo';
            $result['suggestion'] = $local.'@'.$suggestedDomain;
            $result['message'] = 'Possible email domain typo. Suggested address: '.$result['suggestion'];
            return $result;
        }

        if (self::isDisposableDomain($domain)) {
            $result['reason'] = 'disposable_domain';
            $result['message'] = 'Disposable or temporary email domains are not accepted.';
            return $result;
        }

        if (in_array($email, self::OBVIOUS_FAKE_ADDRESSES, true) || in_array($domain, self::RESERVED_EXAMPLE_DOMAINS, true)) {
            $result['reason'] = 'obvious_fake';
            $result['message'] = 'Email is a known fictitious or example address.';
            return $result;
        }

        $dns = self::validateDomainDns($domain);
        $result['dns_checked'] = $dns['checked'];
        $result['dns_mode'] = $dns['mode'];

        if ($dns['checked'] && !$dns['valid']) {
            $result['reason'] = 'dns_unreachable';
            $result['message'] = 'Email domain has no MX, A or AAAA DNS record.';
            return $result;
        }

        $result['valid'] = true;
        $result['reason'] = 'ok';
        $result['message'] = 'Email recipient accepted.';
        return $result;
    }

    public static function normalize(string $email): string
    {
        $email = trim($email);
        if (!str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);
        return $local.'@'.strtolower($domain);
    }

    private static function isDisposableDomain(string $domain): bool
    {
        $domain = strtolower(trim($domain));
        foreach (self::DISPOSABLE_DOMAINS as $blocked) {
            if ($domain === $blocked || str_ends_with($domain, '.'.$blocked)) {
                return true;
            }
        }
        return false;
    }

    private static function validateDomainDns(string $domain): array
    {
        if (!function_exists('checkdnsrr')) {
            return ['checked' => false, 'valid' => true, 'mode' => null];
        }

        // First verify that PHP's DNS resolver is actually responding. If the
        // hosting resolver is temporarily unavailable, fail open so legitimate
        // recipients are not blocked because of an infrastructure outage.
        $resolverReady = @checkdnsrr('gmail.com', 'MX')
            || @checkdnsrr('outlook.com', 'MX')
            || @checkdnsrr('microsoft.com', 'A');

        if (!$resolverReady) {
            return ['checked' => false, 'valid' => true, 'mode' => null];
        }

        if (@checkdnsrr($domain, 'MX')) {
            return ['checked' => true, 'valid' => true, 'mode' => 'MX'];
        }

        // RFC mail delivery can fall back to the host itself when there is no
        // MX record. Accept an A or AAAA record to avoid rejecting legitimate
        // domains that intentionally rely on that fallback.
        if (@checkdnsrr($domain, 'A')) {
            return ['checked' => true, 'valid' => true, 'mode' => 'A'];
        }

        if (@checkdnsrr($domain, 'AAAA')) {
            return ['checked' => true, 'valid' => true, 'mode' => 'AAAA'];
        }

        return ['checked' => true, 'valid' => false, 'mode' => null];
    }
}
