<?php

declare(strict_types=1);

class EmailTemplates
{
    private static function base(string $title, string $content, array $settings): string
    {
        $name = h($settings['company_name'] ?? $settings['admin_brand_name'] ?? "Your Company");
        $phone = h($settings['phone'] ?? '');
        $email = h($settings['email'] ?? '');
        $site = h($settings['website'] ?? 'example.com');
        $safeTitle = h($title);
        $year = date('Y');

        return <<<HTML
<!doctype html>
<html lang="en">
<body style="margin:0;background:#f3f5f3;font-family:Arial,Helvetica,sans-serif;color:#20302f">
    <div style="max-width:640px;margin:24px auto;background:#fff;border:1px solid #dfe5e1;border-radius:18px;overflow:hidden">
        <div style="padding:26px 28px;background:#103c39;color:#fff;border-bottom:5px solid #f2d45c">
            <div style="font-size:14px;letter-spacing:.12em;text-transform:uppercase;opacity:.85">{$name}</div>
            <h1 style="font-size:24px;margin:7px 0 0">{$safeTitle}</h1>
        </div>

        <div style="padding:28px;font-size:16px;line-height:1.65">
            {$content}
        </div>

        <div style="padding:20px 28px;background:#f8faf9;border-top:1px solid #e4e9e6;font-size:14px;line-height:1.55;color:#667170">
            <strong>{$name}</strong><br>
            {$phone} · {$email}<br>
            {$site}
            <div style="margin-top:12px">© {$year} {$name}</div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    public static function estimateAdmin(array $request, array $settings): string
    {
        $rows = [
            'Name' => $request['full_name'] ?? '',
            'Phone' => $request['phone'] ?? '',
            'Email' => $request['email'] ?? '',
            'Address' => $request['address'] ?? '',
            'Service' => $request['service_needed'] ?? '',
            'Desired date' => $request['desired_date'] ?? '',
        ];

        $html = '<p>A new free estimate request was submitted from the website.</p>';
        $html .= '<div style="border:1px solid #e1e6e3;border-radius:12px;padding:16px">';

        foreach ($rows as $label => $value) {
            $html .= '<p style="margin:7px 0"><strong>'
                . h($label)
                . ':</strong> '
                . h((string) $value)
                . '</p>';
        }

        $html .= '</div>';
        $html .= '<p><strong>Project details</strong><br>'
            . nl2br(h($request['message'] ?? ''))
            . '</p>';

        return self::base('New estimate request', $html, $settings);
    }

    public static function estimateCustomer(array $request, array $settings): string
    {
        $name = h($request['full_name'] ?? 'there');
        $company = h($settings['company_name'] ?? $settings['admin_brand_name'] ?? 'Your Company');
        $content = '<p>Hello ' . $name . ',</p>'
            . '<p>Thank you for contacting ' . $company . '. We successfully received your free estimate request and our team will review it as soon as possible.</p>'
            . '<p>If you have additional information, you can reply through the contact methods shown on our website.</p>';

        return self::base('We received your request', $content, $settings);
    }

    public static function adminReset(string $name, array $settings): string
    {
        $content = '<p>Hello ' . h($name) . ',</p>'
            . '<p>Administrator access for the ES CMS Core was reset for client handoff. Website content and customer requests were not deleted.</p>'
            . '<p>If you did not perform this action, review the hosting account and database immediately.</p>';

        return self::base('Administrator access reset', $content, $settings);
    }

    public static function passwordReset(string $name, string $url, array $settings): string
    {
        $safeUrl = h($url);
        $company = h($settings['company_name'] ?? $settings['admin_brand_name'] ?? 'Your Company');
        $content = '<p>Hello ' . h($name) . ',</p>'
            . '<p>A password reset was requested for your ' . $company . ' administrator account.</p>'
            . '<p style="margin:24px 0"><a href="' . $safeUrl . '" style="display:inline-block;background:#0f7777;color:#fff;text-decoration:none;font-weight:700;padding:13px 18px;border-radius:10px">Create new password</a></p>'
            . '<p>This secure link expires in 60 minutes and can only be used once. Your password is never sent by email.</p>'
            . '<p style="font-size:14px;color:#687574;word-break:break-all">' . $safeUrl . '</p>';

        return self::base('Reset administrator password', $content, $settings);
    }

    public static function test(string $method, array $settings): string
    {
        $company = h($settings['company_name'] ?? $settings['admin_brand_name'] ?? 'Your Company');
        $content = '<p>Your <strong>'
            . h($method)
            . '</strong> email configuration is working correctly.</p>'
            . '<p>This message was generated from the ' . $company . ' administrator.</p>';

        return self::base('Email configuration test', $content, $settings);
    }
}
