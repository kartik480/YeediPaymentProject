-- Schema for the database configured in .env.
CREATE DATABASE IF NOT EXISTS Ycust CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE Ycust;

CREATE TABLE IF NOT EXISTS customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(180) NOT NULL,
    phone VARCHAR(32) NOT NULL DEFAULT '',
    email VARCHAR(180) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    service_activated TINYINT(1) NOT NULL DEFAULT 0,
    service_activated_at DATETIME NULL,
    account_number VARCHAR(24) NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_customer_code (code),
    UNIQUE KEY uq_customer_account (account_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL,
    bank_name VARCHAR(180) NOT NULL,
    label VARCHAR(180) NULL,
    vpa VARCHAR(120) NOT NULL,
    payee_name VARCHAR(180) NOT NULL,
    currency VARCHAR(8) NOT NULL DEFAULT 'INR',
    qr_payload TEXT NOT NULL,
    qr_params_json TEXT NULL,
    qr_image_path VARCHAR(255) NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    rotation_order INT NOT NULL DEFAULT 1,
    rotation_minutes INT NOT NULL DEFAULT 60,
    signed_qr TINYINT(1) NOT NULL DEFAULT 0,
    webhook_secret VARCHAR(120) NOT NULL,
    mcc VARCHAR(16) NULL,
    provider_name VARCHAR(120) NULL,
    provider_base_url VARCHAR(255) NULL,
    create_order_path VARCHAR(255) NULL,
    status_path VARCHAR(255) NULL,
    refund_path VARCHAR(255) NULL,
    api_key_encrypted TEXT NULL,
    api_secret_encrypted TEXT NULL,
    success_values VARCHAR(255) NOT NULL DEFAULT 'SUCCESS',
    failed_values VARCHAR(255) NOT NULL DEFAULT 'FAILED',
    reference_field VARCHAR(120) NOT NULL DEFAULT 'reference',
    status_field VARCHAR(120) NOT NULL DEFAULT 'status',
    amount_field VARCHAR(120) NOT NULL DEFAULT 'amount',
    txn_id_field VARCHAR(120) NOT NULL DEFAULT 'upi_txn_id',
    rrn_field VARCHAR(120) NOT NULL DEFAULT 'rrn',
    payer_vpa_field VARCHAR(120) NOT NULL DEFAULT 'payer_vpa',
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_bank_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_config (
    id INT PRIMARY KEY,
    rotation_enabled TINYINT(1) NOT NULL DEFAULT 0,
    rotation_started_at DATETIME NULL,
    manual_active_bank_id BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    bank_id BIGINT UNSIGNED NOT NULL,
    reference VARCHAR(64) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'PENDING',
    requested_amount DECIMAL(14,2) NULL,
    confirmed_amount DECIMAL(14,2) NULL,
    intent_uri TEXT NULL,
    limit_breach TINYINT(1) NOT NULL DEFAULT 0,
    limit_note VARCHAR(255) NOT NULL DEFAULT '',
    service VARCHAR(64) NOT NULL DEFAULT 'UPI',
    settlement_status VARCHAR(32) NOT NULL DEFAULT 'UNSETTLED',
    invoice_ref VARCHAR(160) NULL,
    customer_paid_ack_at DATETIME NULL,
    utr_screenshot_path VARCHAR(255) NULL,
    utr_screenshot_orig VARCHAR(180) NULL,
    customer_reported_email VARCHAR(180) NULL,
    customer_reported_utr VARCHAR(160) NULL,
    upi_txn_id VARCHAR(160) NULL,
    rrn VARCHAR(160) NULL,
    payer_vpa VARCHAR(180) NULL,
    confirmation_source VARCHAR(64) NULL,
    raw_confirmation TEXT NULL,
    sms_match VARCHAR(32) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_txn_reference (reference),
    INDEX idx_txn_customer (customer_id, id),
    INDEX idx_txn_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO customers (code, name, phone, email, active, created_at)
SELECT 'CUST0003', 'Customer 0003', '9999990003', 'cust0003@yeedipay.local', 1, UTC_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM customers WHERE code='CUST0003');

INSERT INTO bank_profiles (
    code, bank_name, label, vpa, payee_name, currency, qr_payload, enabled,
    rotation_order, rotation_minutes, signed_qr, webhook_secret, created_at
)
SELECT 'DEMO', 'Demo Bank', 'Local demo', 'yeedipay.demo@upi', 'YEEDIPAY', 'INR',
       'upi://pay?pa=yeedipay.demo@upi&pn=YEEDIPAY&cu=INR', 1, 1, 60, 0,
       'local-demo-webhook', UTC_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM bank_profiles WHERE code='DEMO');

INSERT INTO system_config (id, rotation_enabled, manual_active_bank_id, updated_at)
SELECT 1, 0, (SELECT id FROM bank_profiles WHERE code='DEMO' LIMIT 1), UTC_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM system_config WHERE id=1);
