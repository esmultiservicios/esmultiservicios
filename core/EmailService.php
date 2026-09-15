<?php
declare(strict_types=1);

require_once __DIR__.'/../config/bootstrap.php';
require_once __DIR__.'/emailTemplates.php';

class EmailService
{
    public function configByType(int $type): ?array
    {
        $st = db()->prepare('SELECT * FROM correo WHERE correo_tipo_id=? AND estado=1 ORDER BY correo_id DESC LIMIT 1');
        $st->execute([$type]);
        return $st->fetch() ?: null;
    }

    public function configById(int $id): ?array
    {
        $st = db()->prepare('SELECT * FROM correo WHERE correo_id=? LIMIT 1');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function validateConfig(array $cfg): array
    {
        $errors = [];
        $method = strtoupper(trim((string)($cfg['metodo_envio'] ?? 'SMTP')));
        $type = (int)($cfg['correo_tipo_id'] ?? 0);
        $sender = $method === 'GRAPH'
            ? trim((string)($cfg['graph_user'] ?? ''))
            : trim((string)($cfg['correo'] ?? ''));

        if (!filter_var($sender, FILTER_VALIDATE_EMAIL)) {
            $errors[] = $method === 'GRAPH'
                ? 'Enter a valid Graph User / mailbox.'
                : 'Enter a valid SMTP user / sender email.';
        }

        // Internal destination is optional. When it is empty, runtime delivery
        // falls back to the selected SMTP user or Microsoft Graph mailbox.
        $destination = trim((string)($cfg['destinatario'] ?? ''));
        if ($destination !== '' && !filter_var($destination, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid internal destination email or leave it blank to use the configured sender mailbox.';
        }

        foreach ($this->normalizeEmails($cfg['copia'] ?? '') as $copy) {
            if (!filter_var($copy, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'One of the hidden copy (BCC) email addresses is invalid.';
                break;
            }
        }

        if ($method === 'GRAPH') {
            if (trim((string)($cfg['tenant_id'] ?? '')) === '') $errors[] = 'Tenant ID is required for Microsoft Graph.';
            if (trim((string)($cfg['client_id'] ?? '')) === '') $errors[] = 'Client ID is required for Microsoft Graph.';
            if (secret_decrypt($cfg['client_secret'] ?? '') === '') $errors[] = 'Client Secret VALUE is required for Microsoft Graph.';
            // Graph User / mailbox is already validated above and is the sender.
        } else {
            if (trim((string)($cfg['server'] ?? '')) === '') $errors[] = 'SMTP server is required.';
            $port = (int)($cfg['port'] ?? 0);
            if ($port < 1 || $port > 65535) $errors[] = 'Enter a valid SMTP port.';
            if (!in_array(strtolower(trim((string)($cfg['smtp_secure'] ?? ''))), ['tls', 'ssl'], true)) $errors[] = 'Choose TLS or SSL for SMTP security.';
            if (secret_decrypt($cfg['password'] ?? '') === '') $errors[] = 'SMTP password or app password is required.';
        }

        return array_values(array_unique($errors));
    }

    public function transportMailbox(array $cfg): string
    {
        return strtoupper(trim((string)($cfg['metodo_envio'] ?? 'SMTP'))) === 'GRAPH'
            ? trim((string)($cfg['graph_user'] ?? ''))
            : trim((string)($cfg['correo'] ?? ''));
    }

    public function resolveDestination(array $cfg, string $preferred = ''): string
    {
        $preferred = trim($preferred);
        if (filter_var($preferred, FILTER_VALIDATE_EMAIL)) return $preferred;

        $configured = trim((string)($cfg['destinatario'] ?? ''));
        if (filter_var($configured, FILTER_VALIDATE_EMAIL)) return $configured;

        return $this->transportMailbox($cfg);
    }

    public function sendByType(int $type, string $to, string $subject, string $html, array $options = []): array
    {
        $cfg = $this->configByType($type);
        if (!$cfg) {
            return ['success' => false, 'message' => 'No active email configuration for this type.'];
        }
        return $this->send($cfg, $this->resolveDestination($cfg, $to), $subject, $html, $options);
    }

    public function sendWithFallback(array $types, string $to, string $subject, string $html, array $options = []): array
    {
        $last = null;
        foreach ($types as $type) {
            $cfg = $this->configByType((int)$type);
            if (!$cfg) continue;
            $result = $this->send($cfg, $this->resolveDestination($cfg, $to), $subject, $html, $options);
            if ($result['success']) return $result;
            $last = $result;
        }
        return $last ?? ['success' => false, 'message' => 'No active email configuration is available.'];
    }

    public function test(int $id, string $to = ''): array
    {
        $cfg = $this->configById($id);
        if (!$cfg) return ['success' => false, 'message' => 'Email configuration not found.'];

        $errors = $this->validateConfig($cfg);
        if ($errors) return ['success' => false, 'message' => implode(' ', $errors)];

        $dest = $this->resolveDestination($cfg, $to);
        return $this->send(
            $cfg,
            $dest,
            'ES MULTISERVICIOS email test',
            EmailTemplates::test((string)$cfg['metodo_envio'], settings())
        );
    }

    public function send(array $cfg, string $to, string $subject, string $html, array $options = []): array
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid destination email.'];
        }

        // The admin's optional copy is intentionally BCC so recipients never see
        // the hidden copy addresses. Empty means no hidden copy is sent.
        $configuredBcc = $this->normalizeEmails($cfg['copia'] ?? '');
        $runtimeBcc = $this->normalizeEmails($options['bcc'] ?? []);
        $bcc = array_values(array_unique(array_merge($configuredBcc, $runtimeBcc)));

        // Keep explicit runtime CC support for callers that intentionally need a visible CC.
        // The Email Configuration UI does not use this for its optional copy field.
        $cc = $this->normalizeEmails($options['cc'] ?? []);

        $replyTo = trim((string)($options['reply_to'] ?? ''));
        if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $replyTo = '';

        $options['bcc'] = $bcc;
        $options['cc'] = $cc;
        $options['reply_to'] = $replyTo;

        return strtoupper((string)$cfg['metodo_envio']) === 'GRAPH'
            ? $this->graph($cfg, $to, $subject, $html, $options)
            : $this->smtp($cfg, $to, $subject, $html, $options);
    }

    public function normalizeEmails(string|array|null $value): array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = preg_split('/[;,\s]+/', trim((string)$value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        $out = [];
        foreach ($items as $item) {
            $email = trim((string)$item);
            if ($email !== '' && !in_array($email, $out, true)) $out[] = $email;
        }
        return $out;
    }

    private function graph(array $c, string $to, string $subject, string $html, array $options): array
    {
        if (!function_exists('curl_init')) return ['success' => false, 'message' => 'cURL is not enabled on this server.'];

        $tenant = trim((string)$c['tenant_id']);
        $client = trim((string)$c['client_id']);
        $secret = secret_decrypt($c['client_secret'] ?? '');
        $from = trim((string)($c['graph_user'] ?: $c['correo']));

        if (!$tenant || !$client || !$secret || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Graph credentials are incomplete.'];
        }

        $ch = curl_init('https://login.microsoftonline.com/'.rawurlencode($tenant).'/oauth2/v2.0/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id' => $client,
                'scope' => 'https://graph.microsoft.com/.default',
                'client_secret' => $secret,
                'grant_type' => 'client_credentials',
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        $json = json_decode((string)$raw, true);
        if ($code < 200 || $code >= 300 || empty($json['access_token'])) {
            return ['success' => false, 'message' => 'Graph token error: '.($err ?: ('HTTP '.$code))];
        }

        $message = [
            'subject' => $subject,
            'body' => ['contentType' => 'HTML', 'content' => $html],
            'toRecipients' => [['emailAddress' => ['address' => $to]]],
        ];

        if (!empty($options['cc'])) {
            $message['ccRecipients'] = array_map(
                static fn(string $email): array => ['emailAddress' => ['address' => $email]],
                $options['cc']
            );
        }

        if (!empty($options['bcc'])) {
            $message['bccRecipients'] = array_map(
                static fn(string $email): array => ['emailAddress' => ['address' => $email]],
                $options['bcc']
            );
        }

        if (!empty($options['reply_to'])) {
            $message['replyTo'] = [['emailAddress' => ['address' => $options['reply_to']]]];
        }

        $payload = [
            'message' => $message,
            'saveToSentItems' => (int)($c['save_to_sent_items'] ?? 1) === 1,
        ];

        $ch = curl_init('https://graph.microsoft.com/v1.0/users/'.rawurlencode($from).'/sendMail');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.$json['access_token'],
                'Content-Type: application/json',
            ],
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        return $code === 202
            ? ['success' => true, 'message' => 'Email sent with Microsoft Graph.']
            : ['success' => false, 'message' => 'Graph send error: '.($err ?: ('HTTP '.$code.' '.$resp))];
    }

    private function smtp(array $c, string $to, string $subject, string $html, array $options): array
    {
        $host = trim((string)$c['server']);
        $port = (int)($c['port'] ?: 587);
        $secure = strtolower(trim((string)$c['smtp_secure']));
        $user = trim((string)$c['correo']);
        $pass = secret_decrypt($c['password'] ?? '');

        if (!$host || !filter_var($user, FILTER_VALIDATE_EMAIL) || $pass === '') {
            return ['success' => false, 'message' => 'SMTP configuration is incomplete.'];
        }

        $target = ($secure === 'ssl' ? 'ssl://' : 'tcp://').$host.':'.$port;
        $fp = @stream_socket_client($target, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
        if (!$fp) return ['success' => false, 'message' => 'SMTP connection failed: '.$errstr];
        stream_set_timeout($fp, 20);

        try {
            $this->expect($fp, [220]);
            $this->cmd($fp, 'EHLO '.($_SERVER['HTTP_HOST'] ?? 'localhost'), [250]);
            if ($secure === 'tls') {
                $this->cmd($fp, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Unable to enable TLS.');
                }
                $this->cmd($fp, 'EHLO '.($_SERVER['HTTP_HOST'] ?? 'localhost'), [250]);
            }
            $this->cmd($fp, 'AUTH LOGIN', [334]);
            $this->cmd($fp, base64_encode($user), [334]);
            $this->cmd($fp, base64_encode($pass), [235]);
            $this->cmd($fp, 'MAIL FROM:<'.$user.'>', [250]);
            $this->cmd($fp, 'RCPT TO:<'.$to.'>', [250, 251]);
            foreach ($options['cc'] ?? [] as $cc) {
                $this->cmd($fp, 'RCPT TO:<'.$cc.'>', [250, 251]);
            }
            // BCC recipients are added only to the SMTP envelope. They are deliberately
            // omitted from message headers so other recipients cannot see them.
            foreach ($options['bcc'] ?? [] as $bcc) {
                $this->cmd($fp, 'RCPT TO:<'.$bcc.'>', [250, 251]);
            }
            $this->cmd($fp, 'DATA', [354]);

            $settings = settings();
            $fromName = trim((string)($settings['company_name'] ?? 'ES MULTISERVICIOS')) ?: 'ES MULTISERVICIOS';
            $headers = [
                'From: '.$this->headerText($fromName).' <'.$user.'>',
                'To: <'.$to.'>',
                'Subject: =?UTF-8?B?'.base64_encode($subject).'?=',
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
            ];
            if (!empty($options['cc'])) $headers[] = 'Cc: '.implode(', ', $options['cc']);
            if (!empty($options['reply_to'])) $headers[] = 'Reply-To: <'.$options['reply_to'].'>';

            $body = implode("\r\n", $headers)."\r\n\r\n".chunk_split(base64_encode($html))."\r\n.";
            fwrite($fp, $body."\r\n");
            $this->expect($fp, [250]);
            $this->cmd($fp, 'QUIT', [221]);
            fclose($fp);

            return ['success' => true, 'message' => 'Email sent with SMTP.'];
        } catch (Throwable $e) {
            @fwrite($fp, "QUIT\r\n");
            @fclose($fp);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function headerText(string $value): string
    {
        return preg_match('/[^\x20-\x7E]/', $value)
            ? '=?UTF-8?B?'.base64_encode($value).'?='
            : str_replace(["\r", "\n"], '', $value);
    }

    private function cmd($fp, string $cmd, array $codes): void
    {
        fwrite($fp, $cmd."\r\n");
        $this->expect($fp, $codes);
    }

    private function expect($fp, array $codes): void
    {
        $resp = '';
        while (($line = fgets($fp, 515)) !== false) {
            $resp .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') break;
        }
        $code = (int)substr($resp, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException('SMTP error '.$code.': '.trim($resp));
        }
    }
}
