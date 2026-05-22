<?php

declare(strict_types=1);

final class SmsGateway
{
    public function send(string $phone, string $message): array
    {
        $apiUrl = trim(setting('sms_api_url'));
        $method = strtoupper(trim(setting('sms_api_method', 'POST')));
        $bodyTemplate = trim(sms_template_value('sms_request_body'));
        $successKeyword = trim(setting('sms_success_keyword'));

        if ($apiUrl === '') {
            throw new RuntimeException('SMS API URL is required.');
        }
        if (!in_array($method, ['GET', 'POST'], true)) {
            throw new RuntimeException('SMS API method must be GET or POST.');
        }
        if ($bodyTemplate === '') {
            throw new RuntimeException('SMS request body template is required.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for SMS API.');
        }

        $phone = normalize_phone($phone);
        if (!valid_bd_phone($phone)) {
            throw new RuntimeException('A valid Bangladesh phone number is required for SMS.');
        }

        $phone880 = '880' . substr($phone, 1);
        $replacements = [
            '{{sms_api_key}}' => setting('sms_api_key'),
            '{{sms_sender_id}}' => setting('sms_sender_id'),
            '{{phone}}' => $phone,
            '{{phone_880}}' => $phone880,
            '{{message}}' => $message,
            '{{message_url}}' => rawurlencode($message),
        ];
        $body = strtr($bodyTemplate, $replacements);

        $url = $apiUrl;
        $postFields = null;
        if ($method === 'GET') {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . ltrim($body, '?&');
        } else {
            $postFields = $body;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_POST => $method === 'POST',
            CURLOPT_POSTFIELDS => $postFields,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded; charset=UTF-8']);
        }

        $responseBody = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false || $responseBody === '') {
            throw new RuntimeException($error !== '' ? $error : 'SMS provider returned an empty response.');
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('SMS provider returned HTTP ' . $status . ': ' . $responseBody);
        }
        if ($successKeyword !== '' && stripos($responseBody, $successKeyword) === false) {
            throw new RuntimeException('SMS provider response did not contain success keyword: ' . $responseBody);
        }

        return [
            'status' => $status,
            'body' => $responseBody,
        ];
    }
}
