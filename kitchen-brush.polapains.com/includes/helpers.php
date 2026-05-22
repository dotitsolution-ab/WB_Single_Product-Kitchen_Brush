<?php

declare(strict_types=1);

function app_config(?string $key = null, mixed $default = null): mixed
{
    $config = $GLOBALS['app_config'] ?? [];
    if ($key === null) {
        return $config;
    }

    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }

    return $value;
}

function db(): PDO
{
    return Database::pdo();
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function base_url(string $path = ''): string
{
    $base = rtrim((string)app_config('app.base_url', ''), '/');
    $path = '/' . ltrim($path, '/');

    if ($base !== '') {
        return $base . $path;
    }

    return $path;
}

function asset_url(string $path): string
{
    $path = ltrim($path, '/');
    $file = BASE_PATH . '/' . $path;
    $version = is_file($file) ? '?v=' . filemtime($file) : '';
    return base_url($path) . $version;
}

function redirect(string $path): never
{
    header('Location: ' . base_url($path));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = (string)($_POST['_csrf'] ?? '');
    if ($token === '' || empty($_SESSION['_csrf']) || !hash_equals((string)$_SESSION['_csrf'], $token)) {
        http_response_code(419);
        exit('Invalid form token. Please refresh and try again.');
    }
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }

    if (!isset($_SESSION['_flash'][$key])) {
        return null;
    }

    $value = (string)$_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function old(string $key, string $default = ''): string
{
    return (string)($_SESSION['_old'][$key] ?? $default);
}

function remember_old(array $data): void
{
    $_SESSION['_old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

function money(mixed $amount): string
{
    return 'BDT ' . number_format((float)$amount, 0);
}

function taka(mixed $amount): string
{
    return number_format((float)$amount, 0) . ' টাকা';
}

function normalize_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';

    if (str_starts_with($digits, '00880')) {
        $digits = '0' . substr($digits, 5);
    } elseif (str_starts_with($digits, '880')) {
        $digits = '0' . substr($digits, 3);
    } elseif (str_starts_with($digits, '88')) {
        $digits = substr($digits, 2);
    } elseif (str_starts_with($digits, '1')) {
        $digits = '0' . $digits;
    }

    return substr($digits, 0, 11);
}

function valid_bd_phone(string $phone): bool
{
    return preg_match('/^01[3-9]\d{8}$/', normalize_phone($phone)) === 1;
}

function display_phone(string $phone): string
{
    return normalize_phone($phone);
}

function text_has_mojibake(string $value): bool
{
    return preg_match('/(?:Ã|Â|â|à[¦§¥]|à¥)/u', $value) === 1;
}

function repair_text_encoding(string $value): string
{
    $current = $value;

    if ($current === '' || !function_exists('iconv')) {
        return $current;
    }

    if (preg_match('/[\x{0980}-\x{09FF}]/u', $current) === 1) {
        return str_replace(['Â°', 'âœ“', 'â˜…'], ['°', '✓', '★'], $current);
    }

    for ($attempt = 0; $attempt < 3 && text_has_mojibake($current); $attempt++) {
        $decoded = @iconv('UTF-8', 'Windows-1252//IGNORE', $current);
        if ($decoded === false || $decoded === '' || $decoded === $current || preg_match('//u', $decoded) !== 1) {
            break;
        }

        $current = $decoded;
    }

    return $current;
}

function repair_text_fields(array $row, array $fields): array
{
    foreach ($fields as $field) {
        if (isset($row[$field]) && is_string($row[$field])) {
            $row[$field] = repair_text_encoding($row[$field]);
        }
    }

    return $row;
}

function status_options(): array
{
    return ['Pending', 'Confirmed', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
}

function status_class(string $status): string
{
    return match ($status) {
        'Confirmed' => 'badge badge-blue',
        'Processing' => 'badge badge-purple',
        'Shipped' => 'badge badge-orange',
        'Delivered' => 'badge badge-green',
        'Cancelled' => 'badge badge-red',
        default => 'badge badge-gray',
    };
}

function setting(string $key, string $default = ''): string
{
    static $settings = null;

    if ($settings === null) {
        $settings = [];
        try {
            $rows = db()->query('SELECT key_name, value_text FROM settings')->fetchAll();
            foreach ($rows as $row) {
                $settings[$row['key_name']] = (string)$row['value_text'];
            }
        } catch (Throwable) {
            return $default;
        }
    }

    $value = array_key_exists($key, $settings) ? (string)$settings[$key] : $default;
    return repair_text_encoding($value);
}

function save_setting(string $key, string $value): void
{
    $value = repair_text_encoding($value);

    $stmt = db()->prepare(
        'INSERT INTO settings (key_name, value_text) VALUES (:key_name, :value_text)
         ON DUPLICATE KEY UPDATE value_text = VALUES(value_text)'
    );
    $stmt->execute([
        'key_name' => $key,
        'value_text' => $value,
    ]);
}

function clean_tracking_value(string $value): string
{
    return preg_replace('/[^A-Za-z0-9_\-.]/', '', $value) ?? '';
}

function meta_purchase_event_id(array $order): string
{
    $orderNumber = clean_tracking_value((string)($order['order_number'] ?? ''));
    if ($orderNumber === '') {
        $orderNumber = clean_tracking_value((string)($order['id'] ?? ''));
    }

    return 'purchase_' . ($orderNumber !== '' ? $orderNumber : session_id());
}

function render_tracking_head(): void
{
    $googleVerification = clean_tracking_value(setting('google_site_verification'));
    $facebookVerification = clean_tracking_value(setting('facebook_domain_verification'));
    $gtmId = clean_tracking_value(setting('gtm_id'));
    $gaId = clean_tracking_value(setting('ga4_id'));
    $pixelId = clean_tracking_value(setting('facebook_pixel_id'));

    if ($googleVerification !== '') {
        echo '<meta name="google-site-verification" content="' . e($googleVerification) . '">' . PHP_EOL;
    }
    if ($facebookVerification !== '') {
        echo '<meta name="facebook-domain-verification" content="' . e($facebookVerification) . '">' . PHP_EOL;
    }
    if ($gtmId !== '') {
        echo "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','" . e($gtmId) . "');</script>" . PHP_EOL;
    }
    if ($gaId !== '') {
        echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . e($gaId) . '"></script>' . PHP_EOL;
        echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','" . e($gaId) . "');</script>" . PHP_EOL;
    }
    if ($pixelId !== '') {
        echo "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','" . e($pixelId) . "');fbq('track','PageView');</script>" . PHP_EOL;
    }
}

function render_gtm_noscript(): void
{
    $gtmId = clean_tracking_value(setting('gtm_id'));
    if ($gtmId === '') {
        return;
    }

    echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . e($gtmId) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . PHP_EOL;
}

function render_order_success_tracking(array $order): void
{
    $orderNumber = (string)$order['order_number'];
    if (!empty($_SESSION['_tracked_orders'][$orderNumber])) {
        return;
    }
    $_SESSION['_tracked_orders'][$orderNumber] = true;

    $items = [];
    foreach (($order['items'] ?? []) as $item) {
        $items[] = [
            'item_name' => (string)$item['product_name'],
            'price' => (float)$item['unit_price'],
            'quantity' => (int)$item['quantity'],
        ];
    }

    $purchase = [
        'transaction_id' => $orderNumber,
        'value' => (float)$order['total'],
        'currency' => 'BDT',
        'items' => $items,
    ];
    $json = json_encode($purchase, JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return;
    }
    $eventId = json_encode(meta_purchase_event_id($order));
    if ($eventId === false) {
        return;
    }

    echo '<script>';
    echo 'window.dataLayer=window.dataLayer||[];window.dataLayer.push({event:"purchase",ecommerce:' . $json . '});';
    echo 'if(typeof gtag==="function"){gtag("event","purchase",' . $json . ');}';
    echo 'if(typeof fbq==="function"){fbq("track","Purchase",{value:' . json_encode((float)$order['total']) . ',currency:"BDT"},{eventID:' . $eventId . '});}';
    echo '</script>' . PHP_EOL;
}

function whatsapp_url(): string
{
    $whatsappNumber = trim(setting('whatsapp_number'));
    $phone = normalize_phone($whatsappNumber !== '' ? $whatsappNumber : setting('contact_phone'));
    if (!valid_bd_phone($phone)) {
        return '';
    }

    $message = setting('whatsapp_message', 'Hello, I need help with my order.');
    return 'https://wa.me/880' . substr($phone, 1) . '?text=' . rawurlencode($message);
}

function render_whatsapp_button(): void
{
    $url = whatsapp_url();
    if ($url === '') {
        return;
    }

    echo '<a class="whatsapp-float" href="' . e($url) . '" target="_blank" rel="noopener" aria-label="Message us on WhatsApp">WhatsApp</a>' . PHP_EOL;
}
