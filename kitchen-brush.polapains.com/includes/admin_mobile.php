<?php

declare(strict_types=1);

function admin_mobile_status_counts(): array
{
    $counts = array_fill_keys(status_options(), 0);

    try {
        $stmt = db()->query('SELECT status, COUNT(*) AS total FROM orders GROUP BY status');
        foreach ($stmt->fetchAll() as $row) {
            $status = (string)$row['status'];
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int)$row['total'];
            }
        }
    } catch (Throwable) {
        return $counts;
    }

    return $counts;
}

function admin_mobile_latest_order_id(): int
{
    try {
        return (int)db()->query('SELECT COALESCE(MAX(id), 0) FROM orders')->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function admin_mobile_order_age_label(string $createdAt): string
{
    $timestamp = strtotime($createdAt);
    if ($timestamp === false) {
        return '';
    }

    $diff = max(0, time() - $timestamp);
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        return (string)floor($diff / 60) . 'm ago';
    }
    if ($diff < 86400) {
        return (string)floor($diff / 3600) . 'h ago';
    }

    return date('d M', $timestamp);
}

function admin_mobile_order_summary(array $order): array
{
    $orderId = (int)($order['id'] ?? 0);
    $createdAt = (string)($order['created_at'] ?? '');
    $createdTimestamp = strtotime($createdAt);

    return [
        'id' => $orderId,
        'order_number' => (string)($order['order_number'] ?? ''),
        'customer_name' => (string)($order['customer_name'] ?? ''),
        'customer_phone' => display_phone((string)($order['customer_phone'] ?? '')),
        'customer_email' => (string)($order['customer_email'] ?? ''),
        'customer_address' => (string)($order['customer_address'] ?? ''),
        'district_area' => (string)($order['district_area'] ?? ''),
        'delivery_note' => (string)($order['delivery_note'] ?? ''),
        'status' => (string)($order['status'] ?? 'Pending'),
        'payment_method' => (string)($order['payment_method'] ?? 'COD'),
        'subtotal' => (float)($order['subtotal'] ?? 0),
        'delivery_charge' => (float)($order['delivery_charge'] ?? 0),
        'total' => (float)($order['total'] ?? 0),
        'subtotal_formatted' => money($order['subtotal'] ?? 0),
        'delivery_charge_formatted' => money($order['delivery_charge'] ?? 0),
        'total_formatted' => money($order['total'] ?? 0),
        'created_at' => $createdAt,
        'created_label' => $createdTimestamp !== false ? date('d M, h:i A', $createdTimestamp) : '',
        'age_label' => admin_mobile_order_age_label($createdAt),
        'detail_url' => base_url('admin/order.php?id=' . $orderId),
        'invoice_url' => base_url('admin/invoice.php?id=' . $orderId),
        'mobile_url' => base_url('admin/mobile.php?order=' . $orderId),
    ];
}

function admin_mobile_order_detail(array $order): array
{
    $payload = admin_mobile_order_summary($order);
    $payload['items'] = array_map(static function (array $item): array {
        return [
            'id' => (int)($item['id'] ?? 0),
            'product_name' => (string)($item['product_name'] ?? ''),
            'unit_price' => (float)($item['unit_price'] ?? 0),
            'quantity' => (int)($item['quantity'] ?? 0),
            'line_total' => (float)($item['line_total'] ?? 0),
            'unit_price_formatted' => money($item['unit_price'] ?? 0),
            'line_total_formatted' => money($item['line_total'] ?? 0),
        ];
    }, (array)($order['items'] ?? []));
    $payload['shipment'] = $order['shipment'] ?? null;

    return $payload;
}

function admin_mobile_orders(array $filters = [], int $limit = 100): array
{
    $orders = list_orders($filters);
    if ($limit > 0) {
        $orders = array_slice($orders, 0, $limit);
    }

    return array_map('admin_mobile_order_summary', $orders);
}

function admin_mobile_new_orders_after(int $afterId, int $limit = 20): array
{
    if ($afterId < 1) {
        return [];
    }

    $limit = max(1, min(50, $limit));
    $stmt = db()->prepare('SELECT * FROM orders WHERE id > :after_id ORDER BY id DESC LIMIT ' . $limit);
    $stmt->execute(['after_id' => $afterId]);

    return array_map(
        static fn (array $order): array => admin_mobile_order_summary(
            repair_text_fields($order, ['customer_name', 'customer_address', 'district_area', 'delivery_note'])
        ),
        $stmt->fetchAll()
    );
}

function admin_mobile_stats_payload(): array
{
    $stats = dashboard_stats();

    return [
        'orders_today' => (int)($stats['orders_today'] ?? 0),
        'orders_total' => (int)($stats['orders_total'] ?? 0),
        'pending' => (int)($stats['pending'] ?? 0),
        'sales_total' => (float)($stats['sales_total'] ?? 0),
        'sales_total_formatted' => money($stats['sales_total'] ?? 0),
    ];
}

function admin_mobile_state_payload(array $filters = [], int $limit = 100, int $afterId = 0, int $selectedOrderId = 0): array
{
    $payload = [
        'stats' => admin_mobile_stats_payload(),
        'status_counts' => admin_mobile_status_counts(),
        'orders' => admin_mobile_orders($filters, $limit),
        'latest_order_id' => admin_mobile_latest_order_id(),
        'new_orders' => admin_mobile_new_orders_after($afterId),
        'server_time' => date(DATE_ATOM),
    ];

    if ($selectedOrderId > 0) {
        $order = order_with_items_by_id($selectedOrderId);
        $payload['selected_order'] = $order ? admin_mobile_order_detail($order) : null;
    }

    return $payload;
}
