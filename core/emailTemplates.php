<?php
declare(strict_types=1);

/**
 * Transactional email templates for the ES MULTISERVICIOS corporate CMS.
 *
 * Design goals:
 * - Dedicated ES MULTISERVICIOS visual identity (no client/Castro styling).
 * - Table-based, email-client-safe markup.
 * - No gradients, no remote font dependency, no JavaScript.
 * - Responsive and readable on desktop and mobile.
 */
final class EmailTemplates
{
    private const NAVY = '#0B315B';
    private const BLUE = '#1186B9';
    private const ORANGE = '#F28C00';
    private const TEXT = '#18324A';
    private const MUTED = '#64788C';
    private const BORDER = '#D9E3EC';
    private const SURFACE = '#F4F7FA';
    private const SOFT_BLUE = '#EEF6FB';

    private static function esc(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function setting(array $settings, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $settings)) {
                $value = trim((string)$settings[$key]);
                if ($value !== '') return $value;
            }
        }
        return $default;
    }

    private static function company(array $settings): array
    {
        $name = self::setting($settings, ['company_name', 'site_name', 'business_name'], 'ES MULTISERVICIOS');
        $tagline = self::setting($settings, ['company_tagline', 'tagline', 'site_tagline'], 'Más que servicio, construimos soluciones');
        $phone = self::setting($settings, ['phone', 'company_phone', 'support_phone'], '+504 8913-6844');
        $email = self::setting($settings, ['contact_email', 'company_email', 'email'], 'administracion@esmultiservicios.com');
        $website = self::setting($settings, ['site_url', 'website_url', 'website'], 'https://esmultiservicios.com/');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';
        if (!filter_var($website, FILTER_VALIDATE_URL)) $website = '';

        return compact('name', 'tagline', 'phone', 'email', 'website');
    }

    private static function shell(string $eyebrow, string $title, string $content, array $settings, string $status = 'info'): string
    {
        $c = self::company($settings);
        $year = date('Y');

        $statusLabel = match ($status) {
            'success' => 'CONFIRMATION',
            'security' => 'SECURITY',
            'request' => 'WEBSITE REQUEST',
            default => 'NOTIFICATION',
        };

        $companyName = self::esc($c['name']);
        $tagline = self::esc($c['tagline']);
        $phone = self::esc($c['phone']);
        $email = self::esc($c['email']);
        $website = self::esc($c['website']);
        $eyebrow = self::esc($eyebrow);
        $title = self::esc($title);
        $statusLabel = self::esc($statusLabel);

        $contactParts = [];
        if ($c['phone'] !== '') $contactParts[] = '<span style="white-space:nowrap;">'.$phone.'</span>';
        if ($c['email'] !== '') $contactParts[] = '<a href="mailto:'.$email.'" style="color:'.self::NAVY.';text-decoration:none;">'.$email.'</a>';
        $contacts = implode('<span style="color:#A5B2BE;padding:0 8px;">•</span>', $contactParts);

        $siteLink = $c['website'] !== ''
            ? '<a href="'.$website.'" style="color:'.self::BLUE.';font-weight:700;text-decoration:none;">'.preg_replace('#^https?://#', '', rtrim($website, '/')).'</a>'
            : '';

        return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light only">
<meta name="supported-color-schemes" content="light only">
<title>'.$title.'</title>
<style>
@media only screen and (max-width:640px){
  .esm-wrap{width:100%!important;max-width:100%!important}
  .esm-pad{padding-left:22px!important;padding-right:22px!important}
  .esm-title{font-size:27px!important;line-height:1.18!important}
  .esm-grid{display:block!important;width:100%!important}
  .esm-grid td{display:block!important;width:100%!important;box-sizing:border-box!important}
  .esm-meta{padding-top:10px!important}
}
</style>
</head>
<body style="margin:0;padding:0;background:'.self::SURFACE.';font-family:Arial,Helvetica,sans-serif;color:'.self::TEXT.';-webkit-text-size-adjust:100%;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:'.self::SURFACE.';border-collapse:collapse;">
<tr><td align="center" style="padding:34px 14px;">
<table role="presentation" class="esm-wrap" width="640" cellspacing="0" cellpadding="0" border="0" style="width:640px;max-width:640px;background:#FFFFFF;border:1px solid '.self::BORDER.';border-radius:18px;border-collapse:separate;overflow:hidden;box-shadow:0 12px 34px rgba(11,49,91,.07);">
<tr>
<td class="esm-pad" style="padding:28px 34px 24px;background:#FFFFFF;border-top:4px solid '.self::ORANGE.';border-bottom:1px solid '.self::BORDER.';">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr>
<td valign="middle">
<div style="font-size:20px;line-height:1.1;font-weight:800;letter-spacing:.2px;color:'.self::NAVY.';">'.$companyName.'</div>
<div style="margin-top:6px;font-size:12px;line-height:1.4;color:'.self::MUTED.';">'.$tagline.'</div>
</td>
<td valign="middle" align="right" style="width:48px;">
<div style="display:inline-block;width:42px;height:42px;line-height:42px;border-radius:12px;background:'.self::SOFT_BLUE.';border:1px solid #D4E8F5;color:'.self::NAVY.';font-size:14px;font-weight:900;text-align:center;letter-spacing:-.5px;">ES</div>
</td>
</tr></table>
</td>
</tr>
<tr><td class="esm-pad" style="padding:34px 34px 12px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr>
<td>
<div style="font-size:11px;line-height:1;font-weight:800;letter-spacing:1.7px;color:'.self::BLUE.';text-transform:uppercase;">'.$eyebrow.'</div>
<h1 class="esm-title" style="margin:11px 0 0;font-size:32px;line-height:1.16;color:'.self::NAVY.';font-weight:800;letter-spacing:-.6px;">'.$title.'</h1>
</td>
<td class="esm-meta" align="right" valign="top" style="width:150px;">
<span style="display:inline-block;padding:7px 10px;border-radius:999px;background:'.self::SOFT_BLUE.';border:1px solid #D7EAF5;color:'.self::NAVY.';font-size:10px;line-height:1;font-weight:800;letter-spacing:.7px;white-space:nowrap;">'.$statusLabel.'</span>
</td>
</tr></table>
</td></tr>
<tr><td class="esm-pad" style="padding:12px 34px 36px;font-size:15px;line-height:1.65;color:'.self::TEXT.';overflow-wrap:anywhere;word-break:break-word;">'.$content.'</td></tr>
<tr><td class="esm-pad" style="padding:22px 34px 26px;background:#F9FBFC;border-top:1px solid '.self::BORDER.';">
<div style="font-size:12px;line-height:1.7;color:'.self::MUTED.';">
<div style="font-size:12px;font-weight:800;color:'.self::NAVY.';letter-spacing:.2px;">'.$companyName.'</div>
'.($contacts !== '' ? '<div style="margin-top:5px;">'.$contacts.'</div>' : '').'
'.($siteLink !== '' ? '<div style="margin-top:3px;">'.$siteLink.'</div>' : '').'
<div style="margin-top:13px;color:#8494A3;">© '.$year.' '.$companyName.'. All rights reserved.</div>
</div>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>';
    }

    private static function infoRow(string $label, string $value): string
    {
        $value = trim($value);
        if ($value === '') return '';
        return '<tr><td style="padding:7px 0;width:150px;color:'.self::MUTED.';font-size:13px;vertical-align:top;">'.self::esc($label).'</td><td style="padding:7px 0;color:'.self::TEXT.';font-size:14px;font-weight:700;vertical-align:top;">'.nl2br(self::esc($value)).'</td></tr>';
    }

    public static function test(string $method, array $settings): string
    {
        $method = strtoupper(trim($method)) === 'GRAPH' ? 'Microsoft Graph' : 'SMTP';
        $content = '<p style="margin:0 0 20px;font-size:16px;line-height:1.65;color:'.self::TEXT.';">Your <strong>'.self::esc($method).'</strong> connection is configured correctly and ES MULTISERVICIOS can deliver transactional messages through this channel.</p>
<div style="padding:18px 20px;background:'.self::SOFT_BLUE.';border:1px solid #D7EAF5;border-left:4px solid '.self::BLUE.';border-radius:12px;">
<div style="font-size:12px;font-weight:800;letter-spacing:.9px;color:'.self::NAVY.';text-transform:uppercase;">Connection verified</div>
<div style="margin-top:7px;font-size:14px;line-height:1.55;color:'.self::MUTED.';">This test was generated securely from the ES MULTISERVICIOS administration panel.</div>
</div>
<p style="margin:22px 0 0;font-size:13px;line-height:1.6;color:'.self::MUTED.';">No action is required. You can return to Email Configuration and continue using this sender.</p>';

        return self::shell('EMAIL DELIVERY', 'Email configuration test', $content, $settings, 'success');
    }

    public static function estimateAdmin(array $request, array $settings): string
    {
        $id = (string)($request['id'] ?? '');
        $name = trim((string)($request['full_name'] ?? '')) ?: 'Website visitor';
        $attachments = $request['attachments'] ?? [];
        $attachmentText = is_array($attachments) && $attachments
            ? implode(', ', array_map(static fn($v) => (string)$v, $attachments))
            : '';

        $rows = self::infoRow('Request ID', $id !== '' ? '#'.$id : '')
            . self::infoRow('Name', $name)
            . self::infoRow('Email', (string)($request['email'] ?? ''))
            . self::infoRow('Phone', (string)($request['phone'] ?? ''))
            . self::infoRow('Service', (string)($request['service_needed'] ?? ''))
            . self::infoRow('Preferred date', (string)($request['desired_date'] ?? ''))
            . self::infoRow('Address', (string)($request['address'] ?? ''))
            . self::infoRow('Attachments', $attachmentText);

        $message = trim((string)($request['message'] ?? ''));
        $messageBlock = $message !== ''
            ? '<div style="margin-top:22px;padding:18px 20px;background:#F9FBFC;border:1px solid '.self::BORDER.';border-radius:12px;"><div style="font-size:11px;font-weight:800;letter-spacing:1px;color:'.self::BLUE.';text-transform:uppercase;">Message</div><div style="margin-top:8px;font-size:14px;line-height:1.65;color:'.self::TEXT.';">'.nl2br(self::esc($message)).'</div></div>'
            : '';

        $content = '<p style="margin:0 0 20px;font-size:15px;color:'.self::TEXT.';">A new website inquiry has been received. The request was saved in the administration panel before this notification was sent.</p>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;">'.$rows.'</table>'.$messageBlock.'
<p style="margin:22px 0 0;font-size:13px;color:'.self::MUTED.';">Reply to this email to contact the visitor directly when a valid visitor email was provided.</p>';

        return self::shell('NEW INQUIRY', 'Website request received', $content, $settings, 'request');
    }

    public static function estimateCustomer(array $request, array $settings): string
    {
        $c = self::company($settings);
        $name = trim((string)($request['full_name'] ?? ''));
        $greeting = $name !== '' ? 'Hello '.self::esc($name).',' : 'Hello,';
        $service = trim((string)($request['service_needed'] ?? ''));

        $summary = $service !== ''
            ? '<div style="margin:20px 0;padding:16px 18px;background:'.self::SOFT_BLUE.';border:1px solid #D7EAF5;border-radius:12px;"><div style="font-size:11px;font-weight:800;letter-spacing:.9px;color:'.self::BLUE.';text-transform:uppercase;">Request type</div><div style="margin-top:6px;font-size:14px;font-weight:700;color:'.self::NAVY.';">'.self::esc($service).'</div></div>'
            : '';

        $content = '<p style="margin:0 0 16px;font-size:16px;color:'.self::TEXT.';">'.$greeting.'</p>
<p style="margin:0;font-size:15px;line-height:1.7;color:'.self::TEXT.';">We received your request successfully. Our team will review the information you submitted and will contact you through the details provided.</p>'.$summary.'
<p style="margin:20px 0 0;font-size:14px;line-height:1.65;color:'.self::MUTED.';">Thank you for contacting <strong style="color:'.self::NAVY.';">'.self::esc($c['name']).'</strong>.</p>';

        return self::shell('REQUEST RECEIVED', 'Thank you for contacting us', $content, $settings, 'success');
    }
}
