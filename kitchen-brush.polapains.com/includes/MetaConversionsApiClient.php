<?php

declare(strict_types=1);

final class MetaConversionsApiClient
{
    private const DEFAULT_GRAPH_VERSION = 'v20.0';

    public function __construct(
        private string $pixelId,
        private string $accessToken,
        private string $graphVersion = self::DEFAULT_GRAPH_VERSION,
        private string $testEventCode = ''
    ) {
    }

    public static function fromSettings(): ?self
    {
        $pixelId = preg_replace('/\D+/', '', setting('facebook_pixel_id')) ?? '';
        $accessToken = trim(setting('facebook_capi_access_token'));
        if ($pixelId === '' || $accessToken === '') {
            return null;
        }

        return new self(
            $pixelId,
            $accessToken,
            self::cleanGraphVersion(setting('facebook_capi_graph_version', self::DEFAULT_GRAPH_VERSION)),
            clean_tracking_value(setting('facebook_capi_test_event_code'))
        );
    }

    public function sendPurchase(array $order): array
    {
        return $this->send([
            'event_name' => 'Purchase',
            'event_time' => $this->eventTime($order),
            'event_id' => meta_purchase_event_id($order),
            'action_source' => 'website',
            'event_source_url' => absolute_base_url('thank-you.php?order=' . urlencode((string)($order['order_number'] ?? ''))),
            'user_data' => $this->userData($order),
            'custom_data' => $this->purchaseCustomData($order),
        ]);
    }

    private function send(array $event): array
    {
        $payload = ['data' => [$event]];
        if ($this->testEventCode !== '') {
            $payload['test_event_code'] = $this->testEventCode;
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Could not encode Meta CAPI payload.');
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/events?access_token=%s',
            rawurlencode($this->graphVersion),
            rawurlencode($this->pixelId),
            rawurlencode($this->accessToken)
        );

        if (function_exists('curl_init')) {
            return $this->sendWithCurl($url, $json);
        }

        return $this->sendWithStream($url, $json);
    }

    private function sendWithCurl(string $url, string $json): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Could not initialize cURL for Meta CAPI.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 4,
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Meta CAPI request failed: ' . $error);
        }

        return $this->decodeResponse((string)$body, $status);
    }

    private function sendWithStream(string $url, string $json): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $json,
                'timeout' => 4,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new RuntimeException('Meta CAPI request failed.');
        }

        $status = 0;
        $headers = $http_response_header ?? [];
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches) === 1) {
                $status = (int)$matches[1];
                break;
            }
        }

        return $this->decodeResponse((string)$body, $status);
    }

    private function decodeResponse(string $body, int $status): array
    {
        $decoded = json_decode($body, true);
        $response = is_array($decoded) ? $decoded : ['raw_body' => $body];

        if ($status < 200 || $status >= 300) {
            $message = (string)($response['error']['message'] ?? 'Meta CAPI returned HTTP ' . $status);
            throw new RuntimeException($message);
        }

        return $response;
    }

    private function userData(array $order): array
    {
        $data = [];

        $email = strtolower(trim((string)($order['customer_email'] ?? '')));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $data['em'] = hash('sha256', $email);
        }

        $phone = $this->normalizePhoneForMeta((string)($order['customer_phone'] ?? ''));
        if ($phone !== '') {
            $data['ph'] = hash('sha256', $phone);
        }

        $name = preg_replace('/\s+/', ' ', trim((string)($order['customer_name'] ?? ''))) ?? '';
        if ($name !== '') {
            $parts = explode(' ', strtolower($name));
            $firstName = trim((string)($parts[0] ?? ''));
            $lastName = trim((string)($parts[count($parts) - 1] ?? ''));
            if ($firstName !== '') {
                $data['fn'] = hash('sha256', $firstName);
            }
            if ($lastName !== '' && $lastName !== $firstName) {
                $data['ln'] = hash('sha256', $lastName);
            }
        }

        $ip = $this->clientIpAddress();
        if ($ip !== '') {
            $data['client_ip_address'] = $ip;
        }

        $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
        if ($userAgent !== '') {
            $data['client_user_agent'] = $userAgent;
        }

        $fbp = $this->cookieValue('_fbp');
        if ($fbp === '') {
            $fbp = $this->cookieValue('sp_fbp');
        }
        if ($fbp !== '') {
            $data['fbp'] = $fbp;
        }

        $fbc = $this->cookieValue('_fbc');
        if ($fbc === '') {
            $fbc = $this->cookieValue('sp_fbc');
        }
        if ($fbc === '') {
            $fbc = $this->fbcFromClickId();
        }
        if ($fbc !== '') {
            $data['fbc'] = $fbc;
        }

        return $data;
    }

    private function purchaseCustomData(array $order): array
    {
        $contents = [];
        $contentIds = [];
        $numItems = 0;

        foreach (($order['items'] ?? []) as $item) {
            $quantity = max(1, (int)($item['quantity'] ?? 1));
            $id = (string)($item['product_id'] ?? $item['product_name'] ?? '');
            if ($id !== '') {
                $contentIds[] = $id;
            }

            $contents[] = [
                'id' => $id !== '' ? $id : (string)($item['product_name'] ?? 'product'),
                'quantity' => $quantity,
                'item_price' => (float)($item['unit_price'] ?? 0),
            ];
            $numItems += $quantity;
        }

        $data = [
            'currency' => 'BDT',
            'value' => (float)($order['total'] ?? 0),
            'order_id' => (string)($order['order_number'] ?? ''),
            'content_type' => 'product',
            'num_items' => $numItems,
        ];

        if ($contentIds !== []) {
            $data['content_ids'] = array_values(array_unique($contentIds));
        }
        if ($contents !== []) {
            $data['contents'] = $contents;
        }

        return $data;
    }

    private function eventTime(array $order): int
    {
        $createdAt = (string)($order['created_at'] ?? '');
        $timestamp = $createdAt !== '' ? strtotime($createdAt) : false;
        return $timestamp !== false ? $timestamp : time();
    }

    private function normalizePhoneForMeta(string $phone): string
    {
        $phone = normalize_phone($phone);
        if ($phone === '') {
            return '';
        }
        if (str_starts_with($phone, '0')) {
            return '880' . substr($phone, 1);
        }

        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private function clientIpAddress(): string
    {
        $candidates = [
            (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''),
            (string)($_SERVER['HTTP_X_REAL_IP'] ?? ''),
            (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''),
            (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        ];

        foreach ($candidates as $candidate) {
            $ip = trim(explode(',', $candidate)[0]);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return '';
    }

    private function cookieValue(string $name): string
    {
        $value = trim((string)($_COOKIE[$name] ?? ''));
        if ($value === '' || strlen($value) > 500) {
            return '';
        }

        return $value;
    }

    private function fbcFromClickId(): string
    {
        $fbclid = trim((string)($_GET['fbclid'] ?? $_POST['fbclid'] ?? ''));
        if ($fbclid === '' || strlen($fbclid) > 500) {
            return '';
        }

        return 'fb.1.' . (time() * 1000) . '.' . $fbclid;
    }

    private static function cleanGraphVersion(string $version): string
    {
        $version = trim($version);
        return preg_match('/^v\d+\.\d+$/', $version) === 1 ? $version : self::DEFAULT_GRAPH_VERSION;
    }
}
