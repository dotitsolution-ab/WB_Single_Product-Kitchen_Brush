<?php

declare(strict_types=1);

function active_product(): ?array
{
    $stmt = db()->query('SELECT * FROM products WHERE is_active = 1 ORDER BY id ASC LIMIT 1');
    $product = $stmt->fetch();
    return $product ?: null;
}

function ensure_email_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $pdo = db();
    if (!db_table_exists('orders') || !db_table_exists('customers')) {
        $checked = true;
        return;
    }

    if (!db_column_exists('customers', 'email')) {
        $pdo->exec('ALTER TABLE customers ADD COLUMN email VARCHAR(190) NULL');
    }
    if (!db_column_exists('orders', 'customer_email')) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN customer_email VARCHAR(190) NULL');
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS email_logs (
            id SERIAL PRIMARY KEY,
            order_id INTEGER NULL,
            email_type VARCHAR(60) NOT NULL,
            recipient_email VARCHAR(190) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'sent',
            provider_message_id VARCHAR(120) NULL,
            error_message TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_email_logs_order_type ON email_logs (order_id, email_type)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_email_logs_status ON email_logs (status)');

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS sms_logs (
            id SERIAL PRIMARY KEY,
            order_id INTEGER NULL,
            sms_type VARCHAR(60) NOT NULL,
            recipient_phone VARCHAR(20) NOT NULL,
            message_text TEXT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'sent',
            provider_response TEXT NULL,
            error_message TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sms_logs_order_type ON sms_logs (order_id, sms_type)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sms_logs_status ON sms_logs (status)');

    $checked = true;
}

function db_table_exists(string $table): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = current_schema() AND table_name = :table_name'
    );
    $stmt->execute(['table_name' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function db_column_exists(string $table, string $column): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = current_schema() AND table_name = :table_name AND column_name = :column_name'
    );
    $stmt->execute([
        'table_name' => $table,
        'column_name' => $column,
    ]);
    return (int)$stmt->fetchColumn() > 0;
}

function ensure_analytics_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    if (!db_table_exists('orders')) {
        $checked = true;
        return;
    }

    db()->exec(
        "CREATE TABLE IF NOT EXISTS page_visits (
            id BIGSERIAL PRIMARY KEY,
            page_key VARCHAR(40) NOT NULL,
            visitor_hash CHAR(64) NOT NULL,
            session_id VARCHAR(128) NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(500) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );
    db()->exec('CREATE INDEX IF NOT EXISTS idx_page_visits_page_created ON page_visits (page_key, created_at)');
    db()->exec('CREATE INDEX IF NOT EXISTS idx_page_visits_visitor ON page_visits (page_key, visitor_hash)');

    $checked = true;
}

function track_page_visit(string $pageKey): void
{
    try {
        ensure_analytics_schema();
        if (!db_table_exists('page_visits')) {
            return;
        }

        $visitorId = (string)($_COOKIE['sp_visitor_id'] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/', $visitorId)) {
            $visitorId = bin2hex(random_bytes(16));
            if (!headers_sent()) {
                setcookie('sp_visitor_id', $visitorId, [
                    'expires' => time() + 31536000,
                    'path' => '/',
                    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
        }

        $pageKey = preg_replace('/[^a-z0-9_]/', '', strtolower($pageKey)) ?: 'page';
        $stmt = db()->prepare(
            'INSERT INTO page_visits (page_key, visitor_hash, session_id, ip_address, user_agent)
             VALUES (:page_key, :visitor_hash, :session_id, :ip_address, :user_agent)'
        );
        $stmt->execute([
            'page_key' => $pageKey,
            'visitor_hash' => hash('sha256', $visitorId),
            'session_id' => session_id(),
            'ip_address' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        ]);
    } catch (Throwable) {
        // Analytics should never block a public page.
    }
}

function analytics_stats(): array
{
    try {
        ensure_analytics_schema();
        if (!db_table_exists('page_visits')) {
            return default_analytics_stats();
        }

        $stats = default_analytics_stats();
        $stmt = db()->query(
            "SELECT
                page_key,
                COUNT(*) AS total_views,
                COUNT(DISTINCT visitor_hash) AS unique_visitors,
                SUM(CASE WHEN DATE(created_at) = CURRENT_DATE THEN 1 ELSE 0 END) AS views_today,
                COUNT(DISTINCT CASE WHEN DATE(created_at) = CURRENT_DATE THEN visitor_hash END) AS visitors_today
             FROM page_visits
             WHERE page_key IN ('home', 'thank_you')
             GROUP BY page_key"
        );

        foreach ($stmt->fetchAll() as $row) {
            $key = (string)$row['page_key'];
            $stats[$key] = [
                'total_views' => (int)$row['total_views'],
                'unique_visitors' => (int)$row['unique_visitors'],
                'views_today' => (int)$row['views_today'],
                'visitors_today' => (int)$row['visitors_today'],
            ];
        }

        $home = max(1, $stats['home']['unique_visitors']);
        $stats['conversion_rate'] = round(($stats['thank_you']['unique_visitors'] / $home) * 100, 1);
        return $stats;
    } catch (Throwable) {
        return default_analytics_stats();
    }
}

function default_analytics_stats(): array
{
    return [
        'home' => ['total_views' => 0, 'unique_visitors' => 0, 'views_today' => 0, 'visitors_today' => 0],
        'thank_you' => ['total_views' => 0, 'unique_visitors' => 0, 'views_today' => 0, 'visitors_today' => 0],
        'conversion_rate' => 0.0,
    ];
}

function email_defaults(): array
{
    return [
        'admin_order_email_subject' => 'New order {{order_number}} - {{site_name}}',
        'admin_order_email_html' => '<h2>New order {{order_number}}</h2><p><strong>Customer:</strong> {{customer_name}}<br><strong>Phone:</strong> {{customer_phone}}<br><strong>Email:</strong> {{customer_email}}<br><strong>Area:</strong> {{district_area}}</p>{{items_table}}<p><strong>Total:</strong> {{total}}</p><p><a href="{{admin_order_url}}">Open order in admin</a></p>',
        'admin_order_email_text' => "New order {{order_number}}\nCustomer: {{customer_name}}\nPhone: {{customer_phone}}\nEmail: {{customer_email}}\nArea: {{district_area}}\nTotal: {{total}}\nAdmin: {{admin_order_url}}",
        'customer_order_email_subject' => 'আপনার অর্ডারটি গ্রহণ করা হয়েছে - {{order_number}}',
        'customer_order_email_html' => '<h2>ধন্যবাদ {{customer_name}}</h2><p>আপনার অর্ডারটি গ্রহণ করা হয়েছে। আমাদের টিম দ্রুত ফোন করে কনফার্ম করবে।</p><p><strong>Order ID:</strong> {{order_number}}<br><strong>Total:</strong> {{total}}<br><strong>Payment:</strong> {{payment_method}}</p>{{items_table}}<p>অর্ডার ট্র্যাক করতে: <a href="{{track_url}}">{{track_url}}</a></p>',
        'customer_order_email_text' => "ধন্যবাদ {{customer_name}}\nআপনার অর্ডারটি গ্রহণ করা হয়েছে।\nOrder ID: {{order_number}}\nTotal: {{total}}\nPayment: {{payment_method}}\nTrack: {{track_url}}",
    ];
}

function sms_defaults(): array
{
    return [
        'sms_api_method' => 'POST',
        'sms_request_body' => 'api_key={{sms_api_key}}&senderid={{sms_sender_id}}&number={{phone_880}}&message={{message_url}}',
        'customer_order_sms_message' => 'প্রিয় {{customer_name}}, আপনার অর্ডার {{order_number}} গ্রহণ করা হয়েছে। মোট {{total}}। {{site_name}}',
    ];
}

function email_template_value(string $key): string
{
    $defaults = email_defaults();
    $value = setting($key, (string)($defaults[$key] ?? ''));
    return $value !== '' ? $value : (string)($defaults[$key] ?? '');
}

function sms_template_value(string $key): string
{
    $defaults = sms_defaults();
    $value = setting($key, (string)($defaults[$key] ?? ''));
    return $value !== '' ? $value : (string)($defaults[$key] ?? '');
}

function product_highlights(array $product): array
{
    $lines = preg_split('/\r\n|\r|\n/', (string)($product['highlights'] ?? '')) ?: [];
    return array_values(array_filter(array_map('trim', $lines)));
}

function landing_defaults(): array
{
    return [
        'badge' => 'স্মার্ট ক্লিনিং, সহজ জীবন',
        'hero_title' => '৩৬০° রোটেটিং কিচেন ক্লিনিং ব্রাশ',
        'hero_subtitle' => 'দাগ দূর হবে সহজে, ক্লিনিং হবে আরামে ও নিরাপদে',
        'discount_label' => '২৫% ছাড়',
        'cta_text' => 'এখনই অর্ডার করুন',
        'hero_image_url' => 'assets/images/kitchen-brush-hero-drain.jpg',
        'demo_image_url' => 'assets/images/kitchen-brush-plate-demo.jpg',
        'delivery_inside_charge' => '60',
        'delivery_outside_charge' => '120',
        'feature_rows' => "৩৬০° রোটেটিং ব্রাশ হেড|সব কোণায় পরিষ্কার|assets/images/kitchen-brush-pan-close.jpg\nশক্ত ব্রাশ|দাগ তুলতে সাহায্য করে|assets/images/kitchen-brush-frypan-foam.jpg\nলম্বা হ্যান্ডেল|ব্যবহারে সহজ|assets/images/kitchen-brush-pan-cleaning.jpg\nওয়াল হ্যাঙ্গিং|স্টোরেজ সহজ|assets/images/kitchen-brush-hanging-storage.jpg",
        'usage_rows' => "প্লেট|assets/images/kitchen-brush-plate-demo.jpg\nফ্রাইপ্যান|assets/images/kitchen-brush-frypan-foam.jpg\nহাঁড়ি|assets/images/kitchen-brush-pan-cleaning.jpg\nস্টোরেজ|assets/images/kitchen-brush-hanging-storage.jpg",
        'reason_rows' => "শ্রম ও সময় বাঁচায়\nদাগ দূর করে সহজে, স্ক্র্যাচ হয় না\nটেকসই রোটেশন, ঝামেলামুক্ত প্রয়োগ\nপচা গন্ধ কমায়, দাগ থাকে না",
    ];
}

function landing_value(string $key): string
{
    $defaults = landing_defaults();
    return setting('landing_' . $key, (string)($defaults[$key] ?? ''));
}

function landing_image_value(string $key): string
{
    $value = landing_value($key);
    $defaults = landing_defaults();

    if ($value === '' || str_contains($value, 'placehold.co')) {
        return (string)($defaults[$key] ?? '');
    }

    return $value;
}

function image_src(string $value, string $fallback = ''): string
{
    $value = trim($value);
    if ($value === '') {
        $value = $fallback;
    }
    if ($value === '') {
        return '';
    }
    if (preg_match('/^(https?:)?\/\//', $value) === 1 || str_starts_with($value, '/')) {
        return $value;
    }

    return base_url($value);
}

function landing_rows(string $key, array $columns): array
{
    $rows = [];
    $lines = preg_split('/\r\n|\r|\n/', landing_value($key)) ?: [];

    foreach ($lines as $line) {
        $parts = array_map('trim', explode('|', trim($line)));
        if ($parts === [''] || $parts[0] === '') {
            continue;
        }

        $row = [];
        foreach ($columns as $index => $column) {
            $row[$column] = (string)($parts[$index] ?? '');
        }
        $rows[] = $row;
    }

    return $rows;
}

function delivery_options(array $product): array
{
    return [
        'inside_dhaka' => [
            'label' => 'ঢাকার ভিতরে',
            'charge' => (float)landing_value('delivery_inside_charge'),
        ],
        'outside_dhaka' => [
            'label' => 'ঢাকার বাইরে',
            'charge' => (float)landing_value('delivery_outside_charge'),
        ],
    ];
}

function save_landing_content(array $data): void
{
    $data = normalize_landing_editor_rows($data);

    foreach (array_keys(landing_defaults()) as $key) {
        save_setting('landing_' . $key, trim((string)($data['landing_' . $key] ?? '')));
    }
}

function normalize_landing_editor_rows(array $data): array
{
    if (isset($data['feature_title'], $data['feature_text'], $data['feature_image']) && is_array($data['feature_title'])) {
        $data['landing_feature_rows'] = build_pipe_rows([
            (array)$data['feature_title'],
            (array)$data['feature_text'],
            (array)$data['feature_image'],
        ]);
    }

    if (isset($data['usage_title'], $data['usage_image']) && is_array($data['usage_title'])) {
        $data['landing_usage_rows'] = build_pipe_rows([
            (array)$data['usage_title'],
            (array)$data['usage_image'],
        ]);
    }

    if (isset($data['reason_title']) && is_array($data['reason_title'])) {
        $data['landing_reason_rows'] = build_pipe_rows([
            (array)$data['reason_title'],
        ]);
    }

    return $data;
}

function build_pipe_rows(array $columns): string
{
    $rows = [];
    $rowCount = 0;
    foreach ($columns as $column) {
        $rowCount = max($rowCount, count($column));
    }

    for ($index = 0; $index < $rowCount; $index++) {
        $cells = [];
        foreach ($columns as $column) {
            $cells[] = clean_pipe_cell($column[$index] ?? '');
        }

        if (implode('', $cells) === '') {
            continue;
        }

        $rows[] = implode('|', $cells);
    }

    return implode("\n", $rows);
}

function clean_pipe_cell(mixed $value): string
{
    return trim(str_replace(["\r", "\n", '|'], ' ', (string)$value));
}

function seed_kitchen_brush_content(): void
{
    $product = active_product();
    if (!$product) {
        throw new RuntimeException('No active product found.');
    }

    $data = landing_defaults();
    $data['name'] = '৩৬০° রোটেটিং কিচেন ক্লিনিং ব্রাশ';
    $data['tagline'] = 'স্মার্ট ক্লিনিং, সহজ জীবন';
    $data['description'] = 'দাগ দূর হবে সহজে, ক্লিনিং হবে আরামে ও নিরাপদে।';
    $data['highlights'] = "৩৬০° রোটেটিং ব্রাশ হেড\nশক্ত ব্রাশ দাগ তুলতে সহায়ক\nলম্বা হ্যান্ডেল ব্যবহারে সহজ\nওয়াল হ্যাঙ্গিং স্টোরেজ";
    $data['price'] = '299';
    $data['compare_price'] = '399';
    $data['stock'] = (string)max(100, (int)$product['stock']);
    $data['image_url'] = 'assets/images/kitchen-brush-pan-cleaning.jpg';
    foreach ($data as $key => $value) {
        if (array_key_exists($key, landing_defaults())) {
            $data['landing_' . $key] = $value;
        }
    }

    save_product($data);
}

function create_cod_order(array $data): array
{
    ensure_email_schema();

    $product = active_product();
    if (!$product) {
        throw new RuntimeException('No active product is available.');
    }

    $name = trim((string)($data['name'] ?? ''));
    $phone = normalize_phone((string)($data['phone'] ?? ''));
    $email = strtolower(trim((string)($data['email'] ?? '')));
    $address = trim((string)($data['address'] ?? ''));
    $deliveryArea = (string)($data['delivery_area'] ?? 'inside_dhaka');
    $deliveryOptions = delivery_options($product);
    if (!array_key_exists($deliveryArea, $deliveryOptions)) {
        $deliveryArea = 'inside_dhaka';
    }
    $districtArea = $deliveryOptions[$deliveryArea]['label'];
    $deliveryNote = trim((string)($data['delivery_note'] ?? ''));
    $quantity = max(1, (int)($data['quantity'] ?? 1));

    if ($name === '' || strlen($name) > 120) {
        throw new InvalidArgumentException('Please enter a valid name.');
    }
    if (!valid_bd_phone($phone)) {
        throw new InvalidArgumentException('Please enter a valid Bangladesh phone number.');
    }
    if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190)) {
        throw new InvalidArgumentException('Please enter a valid email address.');
    }
    if ($address === '' || strlen($address) > 500) {
        throw new InvalidArgumentException('Please enter a valid delivery address.');
    }
    if ((int)$product['stock'] < $quantity) {
        throw new InvalidArgumentException('Requested quantity is not available.');
    }

    $unitPrice = (float)$product['price'];
    $subtotal = $unitPrice * $quantity;
    $deliveryCharge = (float)$deliveryOptions[$deliveryArea]['charge'];
    $total = $subtotal + $deliveryCharge;
    $pdo = db();

    $pdo->beginTransaction();
    try {
        $customerId = upsert_customer($name, $phone, $email, $address, $districtArea);
        $orderNumber = unique_order_number();

        $stmt = $pdo->prepare(
            'INSERT INTO orders
            (order_number, customer_id, customer_name, customer_phone, customer_email, customer_address, district_area, delivery_note, subtotal, delivery_charge, total, payment_method, status)
            VALUES
            (:order_number, :customer_id, :customer_name, :customer_phone, :customer_email, :customer_address, :district_area, :delivery_note, :subtotal, :delivery_charge, :total, :payment_method, :status)
            RETURNING id'
        );
        $stmt->execute([
            'order_number' => $orderNumber,
            'customer_id' => $customerId,
            'customer_name' => $name,
            'customer_phone' => $phone,
            'customer_email' => $email !== '' ? $email : null,
            'customer_address' => $address,
            'district_area' => $districtArea,
            'delivery_note' => $deliveryNote,
            'subtotal' => $subtotal,
            'delivery_charge' => $deliveryCharge,
            'total' => $total,
            'payment_method' => 'COD',
            'status' => 'Pending',
        ]);

        $orderId = (int)$stmt->fetchColumn();
        $item = $pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total)
             VALUES (:order_id, :product_id, :product_name, :unit_price, :quantity, :line_total)'
        );
        $item->execute([
            'order_id' => $orderId,
            'product_id' => (int)$product['id'],
            'product_name' => $product['name'],
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'line_total' => $subtotal,
        ]);

        $stock = $pdo->prepare('UPDATE products SET stock = stock - :quantity_remove WHERE id = :id AND stock >= :quantity_check');
        $stock->execute([
            'quantity_remove' => $quantity,
            'quantity_check' => $quantity,
            'id' => (int)$product['id'],
        ]);
        if ($stock->rowCount() !== 1) {
            throw new InvalidArgumentException('Requested quantity is no longer available.');
        }

        $pdo->commit();
        $order = order_with_items_by_id($orderId) ?? [];
        try {
            send_order_created_emails($order);
        } catch (Throwable) {
            // Email delivery must not block a successful COD order.
        }
        return $order;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function upsert_customer(string $name, string $phone, string $email, string $address, string $districtArea): int
{
    ensure_email_schema();

    $stmt = db()->prepare('SELECT id FROM customers WHERE phone = :phone LIMIT 1');
    $stmt->execute(['phone' => $phone]);
    $customer = $stmt->fetch();

    if ($customer) {
        $update = db()->prepare(
            'UPDATE customers SET name = :name, email = :email, address = :address, district_area = :district_area, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $update->execute([
            'name' => $name,
            'email' => $email !== '' ? $email : null,
            'address' => $address,
            'district_area' => $districtArea,
            'id' => (int)$customer['id'],
        ]);
        return (int)$customer['id'];
    }

    $insert = db()->prepare(
        'INSERT INTO customers (name, phone, email, address, district_area)
         VALUES (:name, :phone, :email, :address, :district_area)
         RETURNING id'
    );
    $insert->execute([
        'name' => $name,
        'phone' => $phone,
        'email' => $email !== '' ? $email : null,
        'address' => $address,
        'district_area' => $districtArea,
    ]);

    return (int)$insert->fetchColumn();
}

function unique_order_number(): string
{
    do {
        $number = 'SP' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
        $stmt = db()->prepare('SELECT id FROM orders WHERE order_number = :order_number LIMIT 1');
        $stmt->execute(['order_number' => $number]);
    } while ($stmt->fetch());

    return $number;
}

function order_with_items_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }

    $order['items'] = order_items((int)$order['id']);
    $order['shipment'] = order_shipment((int)$order['id']);
    return $order;
}

function order_by_number(string $orderNumber, ?string $phone = null): ?array
{
    $sql = 'SELECT * FROM orders WHERE order_number = :order_number';
    $params = ['order_number' => strtoupper(trim($orderNumber))];

    if ($phone !== null) {
        $sql .= ' AND customer_phone = :phone';
        $params['phone'] = normalize_phone($phone);
    }

    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }

    $order['items'] = order_items((int)$order['id']);
    $order['shipment'] = order_shipment((int)$order['id']);
    return $order;
}

function order_items(int $orderId): array
{
    $stmt = db()->prepare('SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id ASC');
    $stmt->execute(['order_id' => $orderId]);
    return $stmt->fetchAll();
}

function order_shipment(int $orderId): ?array
{
    $stmt = db()->prepare('SELECT * FROM courier_shipments WHERE order_id = :order_id LIMIT 1');
    $stmt->execute(['order_id' => $orderId]);
    $shipment = $stmt->fetch();
    return $shipment ?: null;
}

function send_order_created_emails(array $order): void
{
    if (!$order) {
        return;
    }

    if (setting('email_enabled', '0') === '1' && setting('admin_order_email_enabled', '1') === '1') {
        try {
            send_order_email(
                $order,
                'admin_order_created',
                setting('admin_notification_email', setting('support_email')),
                setting('site_name', 'Admin'),
                'admin_order_email_subject',
                'admin_order_email_html',
                'admin_order_email_text'
            );
        } catch (Throwable) {
            // Logged inside send_order_email.
        }
    }

    if (setting('email_enabled', '0') === '1' && setting('customer_order_email_enabled', '1') === '1') {
        try {
            send_customer_order_email($order);
        } catch (Throwable) {
            // Logged inside send_order_email.
        }
    }

    if (setting('sms_enabled', '0') === '1' && setting('customer_order_sms_enabled', '0') === '1') {
        try {
            send_customer_order_sms($order);
        } catch (Throwable) {
            // Logged inside send_order_sms.
        }
    }
}

function send_order_customer_email_once(int $orderId): void
{
    $order = order_with_items_by_id($orderId);
    if (!$order) {
        throw new InvalidArgumentException('Order not found.');
    }

    send_customer_order_email($order, true);
}

function send_customer_order_email(array $order, bool $throwIfAlreadySent = false): bool
{
    $orderId = (int)($order['id'] ?? 0);
    $email = trim((string)($order['customer_email'] ?? ''));
    if ($email === '') {
        if ($throwIfAlreadySent) {
            throw new RuntimeException('This order does not have a customer email address.');
        }
        return false;
    }

    if (email_log_success_exists($orderId, 'customer_order_created')) {
        if ($throwIfAlreadySent) {
            throw new RuntimeException('Customer order email was already sent once.');
        }
        return false;
    }

    send_order_email(
        $order,
        'customer_order_created',
        $email,
        (string)($order['customer_name'] ?? ''),
        'customer_order_email_subject',
        'customer_order_email_html',
        'customer_order_email_text'
    );

    return true;
}

function send_order_customer_sms_once(int $orderId): void
{
    $order = order_with_items_by_id($orderId);
    if (!$order) {
        throw new InvalidArgumentException('Order not found.');
    }

    send_customer_order_sms($order, true);
}

function send_customer_order_sms(array $order, bool $throwIfAlreadySent = false): bool
{
    $orderId = (int)($order['id'] ?? 0);
    $phone = normalize_phone((string)($order['customer_phone'] ?? ''));
    if (!valid_bd_phone($phone)) {
        if ($throwIfAlreadySent) {
            throw new RuntimeException('This order does not have a valid customer phone number.');
        }
        return false;
    }

    if (sms_log_success_exists($orderId, 'customer_order_created')) {
        if ($throwIfAlreadySent) {
            throw new RuntimeException('Customer order SMS was already sent once.');
        }
        return false;
    }

    send_order_sms($order, 'customer_order_created', $phone, 'customer_order_sms_message');
    return true;
}

function send_order_sms(array $order, string $type, string $phone, string $messageKey): void
{
    ensure_email_schema();

    $phone = normalize_phone($phone);
    $message = render_order_sms_template(sms_template_value($messageKey), $order);

    try {
        $response = (new SmsGateway())->send($phone, $message);
        log_sms_event(
            (int)($order['id'] ?? 0),
            $type,
            $phone,
            $message,
            'sent',
            (string)($response['body'] ?? '')
        );
    } catch (Throwable $exception) {
        log_sms_event(
            (int)($order['id'] ?? 0),
            $type,
            $phone,
            $message,
            'failed',
            $exception->getMessage()
        );
        throw $exception;
    }
}

function send_order_email(
    array $order,
    string $type,
    string $toEmail,
    string $toName,
    string $subjectKey,
    string $htmlKey,
    string $textKey
): void {
    ensure_email_schema();

    $toEmail = strtolower(trim($toEmail));
    $subject = render_order_email_template(email_template_value($subjectKey), $order, false);
    $html = render_order_email_template(email_template_value($htmlKey), $order, true);
    $text = render_order_email_template(email_template_value($textKey), $order, false);
    if (trim(strip_tags($html)) === '') {
        $html = nl2br(e($text));
    }
    $html = email_html_shell($subject, $html);
    if (trim($text) === '') {
        $text = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)));
    }

    try {
        $response = (new MailjetMailer())->send($toEmail, $toName, $subject, $html, $text);
        log_email_event(
            (int)($order['id'] ?? 0),
            $type,
            $toEmail,
            $subject,
            'sent',
            extract_mailjet_message_id($response),
            null
        );
    } catch (Throwable $exception) {
        log_email_event(
            (int)($order['id'] ?? 0),
            $type,
            $toEmail,
            $subject,
            'failed',
            null,
            $exception->getMessage()
        );
        throw $exception;
    }
}

function render_order_email_template(string $template, array $order, bool $html): string
{
    $values = order_email_variables($order, $html);
    return strtr($template, $values);
}

function render_order_sms_template(string $template, array $order): string
{
    $values = order_email_variables($order, false);
    $message = strtr($template, $values);
    $message = preg_replace('/\s+/', ' ', $message) ?? $message;
    return trim($message);
}

function order_email_variables(array $order, bool $html): array
{
    $itemsTable = $html ? order_items_html_table($order) : order_items_text_lines($order);
    $trackUrl = absolute_base_url('track.php?order=' . urlencode((string)($order['order_number'] ?? '')));
    $adminOrderUrl = absolute_base_url('admin/order.php?id=' . urlencode((string)($order['id'] ?? '')));
    $data = [
        'site_name' => setting('site_name', app_config('app.name', 'Store')),
        'support_email' => setting('support_email'),
        'contact_phone' => setting('contact_phone'),
        'order_number' => (string)($order['order_number'] ?? ''),
        'customer_name' => (string)($order['customer_name'] ?? ''),
        'customer_phone' => display_phone((string)($order['customer_phone'] ?? '')),
        'customer_email' => (string)($order['customer_email'] ?? ''),
        'customer_address' => (string)($order['customer_address'] ?? ''),
        'district_area' => (string)($order['district_area'] ?? ''),
        'delivery_note' => (string)($order['delivery_note'] ?? ''),
        'payment_method' => (string)($order['payment_method'] ?? 'COD'),
        'status' => (string)($order['status'] ?? ''),
        'subtotal' => taka($order['subtotal'] ?? 0),
        'delivery_charge' => taka($order['delivery_charge'] ?? 0),
        'total' => taka($order['total'] ?? 0),
        'track_url' => $trackUrl,
        'admin_order_url' => $adminOrderUrl,
        'items_table' => $itemsTable,
    ];

    $variables = [];
    foreach ($data as $key => $value) {
        $variables['{{' . $key . '}}'] = $html && $key !== 'items_table' ? e($value) : $value;
    }

    return $variables;
}

function order_items_html_table(array $order): string
{
    $rows = '';
    foreach (($order['items'] ?? []) as $item) {
        $rows .= '<tr><td style="padding:10px;border-bottom:1px solid #e5e7eb;">' . e($item['product_name'] ?? '') . '</td><td style="padding:10px;border-bottom:1px solid #e5e7eb;text-align:center;">' . e((string)($item['quantity'] ?? '')) . '</td><td style="padding:10px;border-bottom:1px solid #e5e7eb;text-align:right;">' . e(taka($item['line_total'] ?? 0)) . '</td></tr>';
    }

    return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:16px 0;border:1px solid #e5e7eb;"><thead><tr><th align="left" style="padding:10px;background:#f9fafb;border-bottom:1px solid #e5e7eb;">Product</th><th style="padding:10px;background:#f9fafb;border-bottom:1px solid #e5e7eb;">Qty</th><th align="right" style="padding:10px;background:#f9fafb;border-bottom:1px solid #e5e7eb;">Total</th></tr></thead><tbody>' . $rows . '</tbody></table>';
}

function order_items_text_lines(array $order): string
{
    $lines = [];
    foreach (($order['items'] ?? []) as $item) {
        $lines[] = '- ' . ($item['product_name'] ?? '') . ' x ' . ($item['quantity'] ?? '') . ' = ' . taka($item['line_total'] ?? 0);
    }

    return implode("\n", $lines);
}

function email_html_shell(string $subject, string $body): string
{
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;background:#f6f7f9;font-family:Arial,Nirmala UI,sans-serif;color:#111827;"><div style="max-width:640px;margin:0 auto;padding:24px;"><div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:24px;"><h1 style="margin:0 0 16px;font-size:22px;color:#111827;">' . e($subject) . '</h1>' . $body . '</div><p style="color:#6b7280;font-size:12px;text-align:center;">' . e(setting('site_name', 'Store')) . '</p></div></body></html>';
}

function email_log_success_exists(int $orderId, string $type): bool
{
    ensure_email_schema();
    $stmt = db()->prepare('SELECT id FROM email_logs WHERE order_id = :order_id AND email_type = :email_type AND status = :status LIMIT 1');
    $stmt->execute([
        'order_id' => $orderId,
        'email_type' => $type,
        'status' => 'sent',
    ]);
    return (bool)$stmt->fetchColumn();
}

function sms_log_success_exists(int $orderId, string $type): bool
{
    ensure_email_schema();
    $stmt = db()->prepare('SELECT id FROM sms_logs WHERE order_id = :order_id AND sms_type = :sms_type AND status = :status LIMIT 1');
    $stmt->execute([
        'order_id' => $orderId,
        'sms_type' => $type,
        'status' => 'sent',
    ]);
    return (bool)$stmt->fetchColumn();
}

function log_email_event(int $orderId, string $type, string $recipient, string $subject, string $status, ?string $messageId, ?string $error): void
{
    ensure_email_schema();
    $stmt = db()->prepare(
        'INSERT INTO email_logs (order_id, email_type, recipient_email, subject, status, provider_message_id, error_message)
         VALUES (:order_id, :email_type, :recipient_email, :subject, :status, :provider_message_id, :error_message)'
    );
    $stmt->execute([
        'order_id' => $orderId > 0 ? $orderId : null,
        'email_type' => $type,
        'recipient_email' => $recipient,
        'subject' => function_exists('mb_substr') ? mb_substr($subject, 0, 255) : substr($subject, 0, 255),
        'status' => $status,
        'provider_message_id' => $messageId,
        'error_message' => $error,
    ]);
}

function log_sms_event(int $orderId, string $type, string $phone, string $message, string $status, string $providerResponseOrError): void
{
    ensure_email_schema();
    $stmt = db()->prepare(
        'INSERT INTO sms_logs (order_id, sms_type, recipient_phone, message_text, status, provider_response, error_message)
         VALUES (:order_id, :sms_type, :recipient_phone, :message_text, :status, :provider_response, :error_message)'
    );
    $stmt->execute([
        'order_id' => $orderId > 0 ? $orderId : null,
        'sms_type' => $type,
        'recipient_phone' => $phone,
        'message_text' => $message,
        'status' => $status,
        'provider_response' => $status === 'sent' ? $providerResponseOrError : null,
        'error_message' => $status === 'failed' ? $providerResponseOrError : null,
    ]);
}

function list_email_logs(int $limit = 20): array
{
    ensure_email_schema();
    $limit = max(1, min(100, $limit));
    $stmt = db()->query(
        'SELECT email_logs.*, orders.order_number
         FROM email_logs
         LEFT JOIN orders ON orders.id = email_logs.order_id
         ORDER BY email_logs.created_at DESC
         LIMIT ' . $limit
    );

    return $stmt->fetchAll();
}

function list_sms_logs(int $limit = 20): array
{
    ensure_email_schema();
    $limit = max(1, min(100, $limit));
    $stmt = db()->query(
        'SELECT sms_logs.*, orders.order_number
         FROM sms_logs
         LEFT JOIN orders ON orders.id = sms_logs.order_id
         ORDER BY sms_logs.created_at DESC
         LIMIT ' . $limit
    );

    return $stmt->fetchAll();
}

function extract_mailjet_message_id(array $response): ?string
{
    $message = $response['Messages'][0]['To'][0] ?? [];
    $id = $message['MessageUUID'] ?? $message['MessageID'] ?? null;
    return $id === null ? null : (string)$id;
}

function absolute_base_url(string $path): string
{
    $base = rtrim((string)app_config('app.base_url', ''), '/');
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        $base = $host !== '' ? $scheme . '://' . $host : '';
    }

    return $base . '/' . ltrim($path, '/');
}

function list_orders(array $filters = []): array
{
    $where = [];
    $params = [];

    $status = (string)($filters['status'] ?? '');
    if ($status !== '' && in_array($status, status_options(), true)) {
        $where[] = 'status = :status';
        $params['status'] = $status;
    }

    $query = trim((string)($filters['q'] ?? ''));
    if ($query !== '') {
        $where[] = '(order_number LIKE :query_order OR customer_name LIKE :query_customer OR customer_phone LIKE :query_phone)';
        $likeQuery = '%' . $query . '%';
        $phoneQuery = normalize_phone($query);
        $params['query_order'] = $likeQuery;
        $params['query_customer'] = $likeQuery;
        $params['query_phone'] = '%' . ($phoneQuery !== '' ? $phoneQuery : $query) . '%';
    }

    $sql = 'SELECT * FROM orders';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY created_at DESC LIMIT 100';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function dashboard_stats(): array
{
    $stats = [
        'orders_today' => 0,
        'orders_total' => 0,
        'sales_total' => 0.0,
        'pending' => 0,
    ];

    $row = db()->query(
        "SELECT
            COUNT(*) AS orders_total,
            COALESCE(SUM(CASE WHEN DATE(created_at) = CURRENT_DATE THEN 1 ELSE 0 END), 0) AS orders_today,
            COALESCE(SUM(total), 0) AS sales_total,
            COALESCE(SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END), 0) AS pending
         FROM orders"
    )->fetch();

    if ($row) {
        $stats = array_merge($stats, $row);
    }

    return $stats;
}

function update_order_status(int $orderId, string $status): void
{
    if (!in_array($status, status_options(), true)) {
        throw new InvalidArgumentException('Invalid status.');
    }

    $stmt = db()->prepare('UPDATE orders SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
    $stmt->execute([
        'status' => $status,
        'id' => $orderId,
    ]);
}

function save_manual_shipment(int $orderId, array $data): void
{
    $stmt = db()->prepare(
        'INSERT INTO courier_shipments
        (order_id, courier_name, consignment_id, tracking_code, shipment_status, raw_response)
        VALUES (:order_id, :courier_name, :consignment_id, :tracking_code, :shipment_status, :raw_response)
        ON CONFLICT (order_id) DO UPDATE SET
            courier_name = EXCLUDED.courier_name,
            consignment_id = EXCLUDED.consignment_id,
            tracking_code = EXCLUDED.tracking_code,
            shipment_status = EXCLUDED.shipment_status,
            raw_response = EXCLUDED.raw_response,
            updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        'order_id' => $orderId,
        'courier_name' => trim((string)($data['courier_name'] ?? 'Steadfast')),
        'consignment_id' => trim((string)($data['consignment_id'] ?? '')),
        'tracking_code' => trim((string)($data['tracking_code'] ?? '')),
        'shipment_status' => trim((string)($data['shipment_status'] ?? '')),
        'raw_response' => trim((string)($data['raw_response'] ?? '')) === '' ? null : (string)$data['raw_response'],
    ]);
}

function create_steadfast_shipment(int $orderId): array
{
    $order = order_with_items_by_id($orderId);
    if (!$order) {
        throw new InvalidArgumentException('Order not found.');
    }

    $response = (new SteadfastClient())->createOrder($order);
    $consignment = $response['consignment']
        ?? $response['data']['consignment']
        ?? $response['data']
        ?? [];

    $consignmentId = (string)($consignment['consignment_id'] ?? $consignment['id'] ?? '');
    $trackingCode = (string)($consignment['tracking_code'] ?? $consignment['trackingCode'] ?? '');
    $shipmentStatus = (string)($consignment['status'] ?? $response['status'] ?? 'Created');

    save_manual_shipment($orderId, [
        'courier_name' => 'Steadfast',
        'consignment_id' => $consignmentId,
        'tracking_code' => $trackingCode,
        'shipment_status' => $shipmentStatus,
        'raw_response' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    ]);

    if ($order['status'] === 'Pending' || $order['status'] === 'Confirmed') {
        update_order_status($orderId, 'Processing');
    }

    return $response;
}

function save_product(array $data): void
{
    $product = active_product();
    if (!$product) {
        throw new RuntimeException('No product found.');
    }

    $stmt = db()->prepare(
        'UPDATE products SET
            name = :name,
            tagline = :tagline,
            description = :description,
            highlights = :highlights,
            price = :price,
            compare_price = :compare_price,
            delivery_charge = :delivery_charge,
            stock = :stock,
            image_url = :image_url,
            updated_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    );
    $stmt->execute([
        'name' => trim((string)$data['name']),
        'tagline' => trim((string)$data['tagline']),
        'description' => trim((string)$data['description']),
        'highlights' => trim((string)$data['highlights']),
        'price' => (float)$data['price'],
        'compare_price' => $data['compare_price'] === '' ? null : (float)$data['compare_price'],
        'delivery_charge' => (float)($data['landing_delivery_inside_charge'] ?? $data['delivery_charge']),
        'stock' => (int)$data['stock'],
        'image_url' => trim((string)$data['image_url']),
        'id' => (int)$product['id'],
    ]);

    save_landing_content($data);
}
