CREATE TABLE IF NOT EXISTS products (
    id SERIAL PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL UNIQUE,
    tagline VARCHAR(255) NULL,
    description TEXT NULL,
    highlights TEXT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    compare_price DECIMAL(10,2) NULL,
    delivery_charge DECIMAL(10,2) NOT NULL DEFAULT 0,
    stock INTEGER NOT NULL DEFAULT 0,
    image_url VARCHAR(500) NULL,
    is_active SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS customers (
    id SERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(190) NULL,
    address VARCHAR(500) NULL,
    district_area VARCHAR(120) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL
);
CREATE INDEX IF NOT EXISTS idx_customers_phone ON customers (phone);

CREATE TABLE IF NOT EXISTS orders (
    id SERIAL PRIMARY KEY,
    order_number VARCHAR(40) NOT NULL UNIQUE,
    customer_id INTEGER NOT NULL,
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
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
);
CREATE INDEX IF NOT EXISTS idx_orders_customer ON orders (customer_id);
CREATE INDEX IF NOT EXISTS idx_orders_phone ON orders (customer_phone);
CREATE INDEX IF NOT EXISTS idx_orders_status ON orders (status);
CREATE INDEX IF NOT EXISTS idx_orders_created ON orders (created_at);

CREATE TABLE IF NOT EXISTS order_items (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL,
    product_id INTEGER NULL,
    product_name VARCHAR(190) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    quantity INTEGER NOT NULL DEFAULT 1,
    line_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items (order_id);

CREATE TABLE IF NOT EXISTS admin_users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS settings (
    key_name VARCHAR(120) PRIMARY KEY,
    value_text TEXT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS courier_shipments (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL UNIQUE,
    courier_name VARCHAR(80) NOT NULL DEFAULT 'Steadfast',
    consignment_id VARCHAR(120) NULL,
    tracking_code VARCHAR(120) NULL,
    shipment_status VARCHAR(80) NULL,
    raw_response JSONB NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_shipments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_shipments_tracking ON courier_shipments (tracking_code);

CREATE TABLE IF NOT EXISTS login_attempts (
    id SERIAL PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(500) NULL,
    success SMALLINT NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_login_attempts_lookup ON login_attempts (email, ip_address, attempted_at);
CREATE INDEX IF NOT EXISTS idx_login_attempts_attempted ON login_attempts (attempted_at);

CREATE TABLE IF NOT EXISTS security_events (
    id SERIAL PRIMARY KEY,
    event_type VARCHAR(80) NOT NULL,
    admin_user_id INTEGER NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(500) NULL,
    details TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_security_events_type ON security_events (event_type);
CREATE INDEX IF NOT EXISTS idx_security_events_created ON security_events (created_at);

CREATE TABLE IF NOT EXISTS email_logs (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NULL,
    email_type VARCHAR(60) NOT NULL,
    recipient_email VARCHAR(190) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'sent',
    provider_message_id VARCHAR(120) NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_email_logs_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_email_logs_order_type ON email_logs (order_id, email_type);
CREATE INDEX IF NOT EXISTS idx_email_logs_status ON email_logs (status);

CREATE TABLE IF NOT EXISTS sms_logs (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NULL,
    sms_type VARCHAR(60) NOT NULL,
    recipient_phone VARCHAR(20) NOT NULL,
    message_text TEXT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'sent',
    provider_response TEXT NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sms_logs_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_sms_logs_order_type ON sms_logs (order_id, sms_type);
CREATE INDEX IF NOT EXISTS idx_sms_logs_status ON sms_logs (status);

CREATE TABLE IF NOT EXISTS page_visits (
    id BIGSERIAL PRIMARY KEY,
    page_key VARCHAR(40) NOT NULL,
    visitor_hash CHAR(64) NOT NULL,
    session_id VARCHAR(128) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_page_visits_page_created ON page_visits (page_key, created_at);
CREATE INDEX IF NOT EXISTS idx_page_visits_visitor ON page_visits (page_key, visitor_hash);

INSERT INTO products
    (name, slug, tagline, description, highlights, price, compare_price, delivery_charge, stock, image_url, is_active)
VALUES
    (
        '৩৬০° রোটেটিং কিচেন ক্লিনিং ব্রাশ',
        'rotating-kitchen-cleaning-brush',
        'স্মার্ট ক্লিনিং, সহজ জীবন',
        'দাগ দূর হবে সহজে, ক্লিনিং হবে আরামে ও নিরাপদে।',
        E'৩৬০° রোটেটিং ব্রাশ হেড\nশক্ত ব্রাশ দাগ তুলতে সহায়ক\nলম্বা হ্যান্ডেল ব্যবহারে সহজ',
        299,
        399,
        60,
        100,
        'assets/images/kitchen-brush-pan-cleaning.jpg',
        1
    )
ON CONFLICT (slug) DO UPDATE SET
    name = EXCLUDED.name,
    updated_at = CURRENT_TIMESTAMP;

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
    ('facebook_capi_last_status', ''),
    ('facebook_capi_last_message', ''),
    ('facebook_capi_last_order', ''),
    ('facebook_capi_last_event_id', ''),
    ('facebook_capi_last_checked_at', ''),
    ('facebook_capi_last_response', ''),
    ('facebook_domain_verification', ''),
    ('google_site_verification', ''),
    ('steadfast_base_url', 'https://portal.steadfast.com.bd/api/v1'),
    ('steadfast_api_key', ''),
    ('steadfast_secret_key', ''),
    ('email_enabled', '0'),
    ('mailjet_api_key', ''),
    ('mailjet_secret_key', ''),
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
ON CONFLICT (key_name) DO UPDATE SET
    value_text = EXCLUDED.value_text,
    updated_at = CURRENT_TIMESTAMP;
