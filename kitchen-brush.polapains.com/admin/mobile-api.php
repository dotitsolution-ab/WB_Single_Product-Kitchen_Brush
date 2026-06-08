<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once BASE_PATH . '/includes/admin_mobile.php';

function mobile_api_response(array $payload, int $status = 200): never
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function mobile_api_csrf_valid(): bool
{
    $token = (string)($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));

    return $token !== ''
        && !empty($_SESSION['_csrf'])
        && hash_equals((string)$_SESSION['_csrf'], $token);
}

if (!Auth::check()) {
    mobile_api_response(['ok' => false, 'message' => 'Authentication required.'], 401);
}

$filters = [
    'status' => (string)($_GET['status'] ?? $_POST['status_filter'] ?? ''),
    'q' => (string)($_GET['q'] ?? $_POST['q'] ?? ''),
];
$afterId = max(0, (int)($_GET['after_id'] ?? $_POST['after_id'] ?? 0));
$selectedOrderId = max(0, (int)($_GET['order_id'] ?? $_POST['order_id'] ?? 0));

if (!is_post()) {
    mobile_api_response([
        'ok' => true,
        'data' => admin_mobile_state_payload($filters, 100, $afterId, $selectedOrderId),
    ]);
}

if (!mobile_api_csrf_valid()) {
    mobile_api_response(['ok' => false, 'message' => 'Invalid form token.'], 419);
}

$action = (string)($_POST['action'] ?? '');

try {
    if ($action === 'status') {
        $orderId = max(0, (int)($_POST['order_id'] ?? 0));
        if ($orderId < 1) {
            throw new InvalidArgumentException('Order not found.');
        }
        update_order_status($orderId, (string)($_POST['status'] ?? 'Pending'));
        $selectedOrderId = $orderId;
        $message = 'Order status updated.';
    } elseif ($action === 'steadfast') {
        $orderId = max(0, (int)($_POST['order_id'] ?? 0));
        if ($orderId < 1) {
            throw new InvalidArgumentException('Order not found.');
        }
        create_steadfast_shipment($orderId);
        $selectedOrderId = $orderId;
        $message = 'Steadfast shipment created.';
    } elseif ($action === 'customer_sms') {
        $orderId = max(0, (int)($_POST['order_id'] ?? 0));
        if ($orderId < 1) {
            throw new InvalidArgumentException('Order not found.');
        }
        send_order_customer_sms_once($orderId);
        $selectedOrderId = $orderId;
        $message = 'Customer SMS sent.';
    } elseif ($action === 'customer_email') {
        $orderId = max(0, (int)($_POST['order_id'] ?? 0));
        if ($orderId < 1) {
            throw new InvalidArgumentException('Order not found.');
        }
        send_order_customer_email_once($orderId);
        $selectedOrderId = $orderId;
        $message = 'Customer email sent.';
    } else {
        mobile_api_response(['ok' => false, 'message' => 'Unknown action.'], 400);
    }

    mobile_api_response([
        'ok' => true,
        'message' => $message,
        'data' => admin_mobile_state_payload($filters, 100, $afterId, $selectedOrderId),
    ]);
} catch (Throwable $exception) {
    mobile_api_response(['ok' => false, 'message' => $exception->getMessage()], 422);
}
