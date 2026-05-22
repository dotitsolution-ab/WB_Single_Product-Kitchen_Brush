<?php

declare(strict_types=1);

final class ZeptoMailMailer
{
    private const DEFAULT_ENDPOINT = 'https://api.zeptomail.com/v1.1/email';

    public function send(string $toEmail, string $toName, string $subject, string $html, string $text): array
    {
        $endpoint = trim(setting('zeptomail_api_url', self::DEFAULT_ENDPOINT));
        $sendMailToken = trim(setting('zeptomail_send_token'));
        $fromEmail = trim(setting('mail_from_email', setting('support_email')));
        $fromName = trim(setting('mail_from_name', setting('site_name', app_config('app.name', 'Store'))));
        if ($endpoint === '') {
            $endpoint = self::DEFAULT_ENDPOINT;
        }
        if ($fromEmail === '') {
            $fromEmail = trim(setting('support_email'));
        }
        if ($fromName === '') {
            $fromName = trim(setting('site_name', app_config('app.name', 'Store')));
        }

        if ($sendMailToken === '') {
            throw new RuntimeException('ZeptoMail Send Mail Token is required.');
        }
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('A valid From Email is required.');
        }
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('A valid recipient email is required.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for ZeptoMail API.');
        }

        $payload = [
            'from' => [
                'address' => $fromEmail,
                'name' => $fromName !== '' ? $fromName : $fromEmail,
            ],
            'to' => [[
                'email_address' => [
                    'address' => $toEmail,
                    'name' => $toName !== '' ? $toName : $toEmail,
                ],
            ]],
            'subject' => $subject,
            'htmlbody' => $html,
            'textbody' => $text,
            'track_clicks' => false,
            'track_opens' => false,
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Zoho-enczapikey ' . $sendMailToken,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 12,
        ]);

        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $body === '') {
            throw new RuntimeException($error !== '' ? $error : 'ZeptoMail returned an empty response.');
        }

        $decoded = json_decode($body, true);
        if ($status < 200 || $status >= 300) {
            $message = is_array($decoded)
                ? (string)($decoded['error']['message'] ?? $decoded['message'] ?? $body)
                : $body;
            throw new RuntimeException('ZeptoMail send failed: ' . $message);
        }

        return is_array($decoded) ? $decoded : ['raw' => $body];
    }
}
