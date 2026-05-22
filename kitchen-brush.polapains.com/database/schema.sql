CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL UNIQUE,
    tagline VARCHAR(255) NULL,
    description TEXT NULL,
    highlights TEXT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    compare_price DECIMAL(10,2) NULL,
    delivery_charge DECIMAL(10,2) NOT NULL DEFAULT 0,
    stock INT NOT NULL DEFAULT 0,
    image_url VARCHAR(500) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(190) NULL,
    address VARCHAR(500) NULL,
    district_area VARCHAR(120) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customers_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(40) NOT NULL UNIQUE,
    customer_id INT UNSIGNED NOT NULL,
    customer_name VARCHAR(120) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    customer_email VARCHAR(190) NULL,
    customer_address VARCHAR(500) NOT NULL,
    district_area VARCHAR(120) NOT NULL,
    delivery_note TEXT NULL,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    delivery_charge DECIMAL(10,2) NOT NULL DEFAULT 0,
    total DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_method VARCHAR(30) NOT NULL DEFAULT 'COD',
    status VARCHAR(30) NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_orders_customer (customer_id),
    INDEX idx_orders_phone (customer_phone),
    INDEX idx_orders_status (status),
    INDEX idx_orders_created (created_at),
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    product_name VARCHAR(190) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    quantity INT NOT NULL DEFAULT 1,
    line_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_items_order (order_id),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    key_name VARCHAR(120) PRIMARY KEY,
    value_text TEXT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS courier_shipments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL UNIQUE,
    courier_name VARCHAR(80) NOT NULL DEFAULT 'Steadfast',
    consignment_id VARCHAR(120) NULL,
    tracking_code VARCHAR(120) NULL,
    shipment_status VARCHAR(80) NULL,
    raw_response JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_shipments_tracking (tracking_code),
    CONSTRAINT fk_shipments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(500) NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_lookup (email, ip_address, attempted_at),
    INDEX idx_login_attempts_attempted (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(80) NOT NULL,
    admin_user_id INT UNSIGNED NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(500) NULL,
    details TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_security_events_type (event_type),
    INDEX idx_security_events_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NULL,
    email_type VARCHAR(60) NOT NULL,
    recipient_email VARCHAR(190) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'sent',
    provider_message_id VARCHAR(120) NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_logs_order_type (order_id, email_type),
    INDEX idx_email_logs_status (status),
    CONSTRAINT fk_email_logs_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NULL,
    sms_type VARCHAR(60) NOT NULL,
    recipient_phone VARCHAR(20) NOT NULL,
    message_text TEXT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'sent',
    provider_response TEXT NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sms_logs_order_type (order_id, sms_type),
    INDEX idx_sms_logs_status (status),
    CONSTRAINT fk_sms_logs_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS page_visits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_key VARCHAR(40) NOT NULL,
    visitor_hash CHAR(64) NOT NULL,
    session_id VARCHAR(128) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_page_visits_page_created (page_key, created_at),
    INDEX idx_page_visits_visitor (page_key, visitor_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO products
    (name, slug, tagline, description, highlights, price, compare_price, delivery_charge, stock, image_url, is_active)
VALUES
    (
        '৩৬০° রোটেটিং কিচেন ক্লিনিং ব্রাশ',
        'rotating-kitchen-cleaning-brush',
        'স্মার্ট ক্লিনিং, সহজ জীবন',
        'দাগ দূর হবে সহজে, ক্লিনিং হবে আরামে ও নিরাপদে।',
        '৩৬০° রোটেটিং ব্রাশ হেড\nশক্ত ব্রাশ দাগ তুলতে সহায়ক\nলম্বা হ্যান্ডেল ব্যবহারে সহজ',
        299,
        399,
        60,
        100,
        'assets/images/kitchen-brush-pan-cleaning.jpg',
        1
    )
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO settings (key_name, value_text) VALUES
    ('site_name', 'Single Product Store'),
    ('contact_phone', '01700000000'),
    ('support_email', 'support@example.com'),
    ('whatsapp_number', ''),
    ('whatsapp_message', 'Hello, I need help with my order.'),
    ('gtm_id', ''),
    ('ga4_id', ''),
    ('facebook_pixel_id', ''),
    ('facebook_capi_graph_version', 'v20.0'),
    ('facebook_capi_test_event_code', ''),
    ('facebook_capi_access_token', ''),
    ('facebook_domain_verification', ''),
    ('google_site_verification', ''),
    ('steadfast_base_url', 'https://portal.steadfast.com.bd/api/v1'),
    ('steadfast_api_key', ''),
    ('steadfast_secret_key', ''),
    ('email_enabled', '0'),
    ('zeptomail_api_url', 'https://api.zeptomail.com/v1.1/email'),
    ('zeptomail_send_token', ''),
    ('mail_from_email', 'support@example.com'),
    ('mail_from_name', 'Single Product Store'),
    ('admin_notification_email', 'admin@example.com'),
    ('admin_order_email_enabled', '1'),
    ('customer_order_email_enabled', '1'),
    ('admin_order_email_subject', 'New order {{order_number}} - {{site_name}}'),
    ('admin_order_email_html', ''),
    ('admin_order_email_text', ''),
    ('customer_order_email_subject', 'আপনার অর্ডারটি গ্রহণ করা হয়েছে - {{order_number}}'),
    ('customer_order_email_html', ''),
    ('customer_order_email_text', ''),
    ('sms_enabled', '0'),
    ('customer_order_sms_enabled', '0'),
    ('sms_provider_name', ''),
    ('sms_api_url', ''),
    ('sms_api_method', 'POST'),
    ('sms_api_key', ''),
    ('sms_sender_id', ''),
    ('sms_request_body', 'api_key={{sms_api_key}}&senderid={{sms_sender_id}}&number={{phone_880}}&message={{message_url}}'),
    ('sms_success_keyword', ''),
    ('customer_order_sms_message', 'প্রিয় {{customer_name}}, আপনার অর্ডার {{order_number}} গ্রহণ করা হয়েছে। মোট {{total}}। {{site_name}}')
ON DUPLICATE KEY UPDATE value_text = VALUES(value_text);
