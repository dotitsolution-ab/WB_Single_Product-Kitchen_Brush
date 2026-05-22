<?php

declare(strict_types=1);

final class SteadfastClient
{
    public function createOrder(array $order): array
    {
        $baseUrl = rtrim(setting('steadfast_base_url', (string)app_config('steadfast.base_url')), '/');
        $apiKey = setting('steadfast_api_key', (string)app_config('steadfast.api_key'));
        $secretKey = setting('steadfast_secret_key', (string)app_config('steadfast.secret_key'));

        if ($baseUrl === '' || $apiKey === '' || $secretKey === '') {
            throw new RuntimeException('Steadfast API settings are missing.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for Steadfast API calls.');
        }

        $payload = [
            'invoice' => $order['order_number'],
            'recipient_name' => $order['customer_name'],
            'recipient_phone' => normalize_phone((string)$order['customer_phone']),
            'recipient_address' => trim($order['customer_address'] . ', ' . $order['district_area']),
            'cod_amount' => (float)$order['total'],
            'note' => (string)($order['delivery_note'] ?? ''),
            'item_description' => $this->itemDescription((int)$order['id']),
            'total_lot' => 1,
            'delivery_type' => 0,
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Unable to encode Steadfast payload.');
        }

        $ch = curl_init($baseUrl . '/create_order');
        if ($ch === false) {
            throw new RuntimeException('Unable to initialize Steadfast request.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Api-Key: ' . $apiKey,
                'Secret-Key: ' . $secretKey,
            ],
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false || $error !== '') {
            throw new RuntimeException('Steadfast request failed: ' . $error);
        }

        $decoded = json_decode((string)$body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Steadfast returned an invalid response.');
        }

        if ($status >= 400) {
            throw new RuntimeException('Steadfast returned HTTP ' . $status . ': ' . substr((string)$body, 0, 300));
        }

        $decoded['_http_status'] = $status;
        $decoded['_payload'] = $payload;
        return $decoded;
    }

    private function itemDescription(int $orderId): string
    {
        $stmt = db()->prepare('SELECT product_name, quantity FROM order_items WHERE order_id = :order_id');
        $stmt->execute(['order_id' => $orderId]);
        $items = $stmt->fetchAll();
        $parts = [];

        foreach ($items as $item) {
            $parts[] = $item['product_name'] . ' x ' . $item['quantity'];
        }

        return implode(', ', $parts);
    }
}
