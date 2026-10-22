-- YEEDIPAY CUST0003 BigRock customer package
-- Import this file inside the database already created in BigRock/cPanel.
-- Do not CREATE/USE a hard-coded database name here.

SET NAMES utf8mb4;

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
    UNIQUE KEY uq_customer_code (code), UNIQUE KEY uq_customer_account (account_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL, bank_name VARCHAR(180) NOT NULL, label VARCHAR(180) NULL,
    vpa VARCHAR(120) NOT NULL, payee_name VARCHAR(180) NOT NULL, currency VARCHAR(8) NOT NULL DEFAULT 'INR',
    qr_payload TEXT NOT NULL, qr_params_json TEXT NULL, qr_image_path VARCHAR(255) NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1, rotation_order INT NOT NULL DEFAULT 1, rotation_minutes INT NOT NULL DEFAULT 30,
    signed_qr TINYINT(1) NOT NULL DEFAULT 0, webhook_secret VARCHAR(120) NOT NULL,
    mcc VARCHAR(16) NULL, provider_name VARCHAR(120) NULL, provider_base_url VARCHAR(255) NULL,
    create_order_path VARCHAR(255) NULL, status_path VARCHAR(255) NULL, refund_path VARCHAR(255) NULL,
    api_key_encrypted TEXT NULL, api_secret_encrypted TEXT NULL,
    success_values VARCHAR(255) NOT NULL DEFAULT 'SUCCESS', failed_values VARCHAR(255) NOT NULL DEFAULT 'FAILED',
    reference_field VARCHAR(120) NOT NULL DEFAULT 'reference', status_field VARCHAR(120) NOT NULL DEFAULT 'status',
    amount_field VARCHAR(120) NOT NULL DEFAULT 'amount', txn_id_field VARCHAR(120) NOT NULL DEFAULT 'upi_txn_id',
    rrn_field VARCHAR(120) NOT NULL DEFAULT 'rrn', payer_vpa_field VARCHAR(120) NOT NULL DEFAULT 'payer_vpa',
    created_at DATETIME NOT NULL, UNIQUE KEY uq_bank_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_config (
    id INT PRIMARY KEY, rotation_enabled TINYINT(1) NOT NULL DEFAULT 0, rotation_started_at DATETIME NULL,
    manual_active_bank_id BIGINT UNSIGNED NULL, updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL, bank_id BIGINT UNSIGNED NOT NULL,
    reference VARCHAR(64) NOT NULL, status VARCHAR(32) NOT NULL DEFAULT 'PENDING',
    requested_amount DECIMAL(14,2) NULL, confirmed_amount DECIMAL(14,2) NULL, intent_uri TEXT NULL,
    limit_breach TINYINT(1) NOT NULL DEFAULT 0, limit_note VARCHAR(255) NOT NULL DEFAULT '',
    service VARCHAR(64) NOT NULL DEFAULT 'UPI', settlement_status VARCHAR(32) NOT NULL DEFAULT 'UNSETTLED',
    invoice_ref VARCHAR(160) NULL, customer_paid_ack_at DATETIME NULL,
    utr_screenshot_path VARCHAR(255) NULL, utr_screenshot_orig VARCHAR(180) NULL,
    customer_reported_email VARCHAR(180) NULL, customer_reported_utr VARCHAR(160) NULL, customer_reported_at DATETIME NULL,
    upi_txn_id VARCHAR(160) NULL, rrn VARCHAR(160) NULL, payer_vpa VARCHAR(180) NULL,
    confirmation_source VARCHAR(64) NULL, raw_confirmation TEXT NULL, sms_match VARCHAR(32) NULL,
    created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_txn_reference(reference), INDEX idx_txn_customer(customer_id,id), INDEX idx_txn_status(status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_otps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, customer_id BIGINT UNSIGNED NOT NULL,
    otp_hash VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL, attempts INT NOT NULL DEFAULT 0,
    consumed_at DATETIME NULL, created_at DATETIME NOT NULL, INDEX idx_customer_otp(customer_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, customer_id BIGINT UNSIGNED NOT NULL,
    reference VARCHAR(40) NOT NULL, subject VARCHAR(180) NOT NULL, category VARCHAR(60) NOT NULL DEFAULT 'GENERAL',
    status VARCHAR(32) NOT NULL DEFAULT 'OPEN', priority VARCHAR(20) NOT NULL DEFAULT 'NORMAL',
    created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_ticket_reference(reference), INDEX idx_ticket_customer(customer_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_ticket_replies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ticket_id BIGINT UNSIGNED NOT NULL,
    sender_type VARCHAR(20) NOT NULL, sender_name VARCHAR(180) NOT NULL, message TEXT NOT NULL,
    created_at DATETIME NOT NULL, INDEX idx_ticket_reply(ticket_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallet_ledger (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, customer_id BIGINT UNSIGNED NOT NULL,
    transaction_id BIGINT UNSIGNED NULL, entry_type VARCHAR(40) NOT NULL, reference VARCHAR(80) NOT NULL,
    amount DECIMAL(14,2) NOT NULL, note VARCHAR(255) NULL, created_at DATETIME NOT NULL,
    UNIQUE KEY uq_wallet_entry(entry_type,reference), INDEX idx_wallet_customer(customer_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallet_recharges (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, customer_id BIGINT UNSIGNED NOT NULL,
    transaction_id BIGINT UNSIGNED NOT NULL, txn_reference VARCHAR(64) NOT NULL, amount DECIMAL(14,2) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'PENDING', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_wallet_recharge_txn(transaction_id), UNIQUE KEY uq_wallet_recharge_ref(txn_reference), INDEX idx_wallet_recharge_customer(customer_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallet_withdrawals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, customer_id BIGINT UNSIGNED NOT NULL,
    reference VARCHAR(64) NOT NULL, amount DECIMAL(14,2) NOT NULL, account_name VARCHAR(180) NOT NULL,
    account_number VARCHAR(64) NOT NULL, ifsc VARCHAR(20) NOT NULL, status VARCHAR(32) NOT NULL DEFAULT 'PENDING',
    created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_wallet_withdraw_ref(reference), INDEX idx_wallet_withdraw_customer(customer_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank_sms_devices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, device_id VARCHAR(64) NOT NULL,
    secret_encrypted TEXT NOT NULL, bank_code VARCHAR(32) NOT NULL, account_last4 CHAR(4) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1, last_seen_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_bank_sms_device(device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank_sms_device_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, device_row_id BIGINT UNSIGNED NOT NULL, bank_code VARCHAR(32) NOT NULL,
    account_last4 CHAR(4) NOT NULL, created_at DATETIME NOT NULL,
    UNIQUE KEY uq_device_bank_last4(device_row_id,bank_code,account_last4), INDEX idx_device_accounts(device_row_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portal_bank_sms_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, device_id VARCHAR(64) NOT NULL, android_event_id CHAR(64) NULL,
    bank_code VARCHAR(32) NOT NULL, amount DECIMAL(14,2) NOT NULL, utr VARCHAR(40) NULL, account_last4 CHAR(4) NOT NULL,
    sender_id VARCHAR(80) NULL, sms_time DATETIME NOT NULL, raw_body TEXT NULL, match_status VARCHAR(32) NOT NULL,
    transaction_id BIGINT UNSIGNED NULL, candidate_refs VARCHAR(500) NULL, created_at DATETIME NOT NULL,
    UNIQUE KEY uq_bank_sms_utr(utr), UNIQUE KEY uq_bank_sms_android_event(device_id,android_event_id),
    INDEX idx_bank_sms_status(match_status,id), INDEX idx_bank_sms_lookup(bank_code,amount,sms_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank_sms_nonces (nonce VARCHAR(80) PRIMARY KEY, created_at DATETIME NOT NULL)
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO customers(code,name,phone,email,active,created_at)
SELECT 'CUST0003','SIRI ENTERPRISES','','',1,UTC_TIMESTAMP()
WHERE NOT EXISTS(SELECT 1 FROM customers WHERE code='CUST0003');

INSERT INTO system_config(id,rotation_enabled,manual_active_bank_id,updated_at)
SELECT 1,0,NULL,UTC_TIMESTAMP() WHERE NOT EXISTS(SELECT 1 FROM system_config WHERE id=1);

-- IMPORTANT: No fake bank/VPA is inserted. Keep the existing live bank_profiles data,
-- or insert your actual bank QR/VPA profile before accepting payments.
-- YEEDIPAY Merchant API + Hosted Payment + Webhook patch
-- Safe to import after database/schema.sql. Uses CREATE TABLE IF NOT EXISTS.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS merchant_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    merchant_id VARCHAR(32) NOT NULL,
    display_name VARCHAR(180) NOT NULL,
    mode VARCHAR(12) NOT NULL DEFAULT 'LIVE',
    api_key_hash CHAR(64) NOT NULL,
    api_key_hint VARCHAR(24) NOT NULL,
    api_secret_encrypted TEXT NOT NULL,
    webhook_secret_encrypted TEXT NOT NULL,
    webhook_url VARCHAR(500) NULL,
    return_url VARCHAR(500) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_merchant_id(merchant_id),
    UNIQUE KEY uq_merchant_api_key(api_key_hash),
    INDEX idx_merchant_customer(customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    merchant_account_id BIGINT UNSIGNED NOT NULL,
    transaction_id BIGINT UNSIGNED NOT NULL,
    order_id VARCHAR(80) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    currency VARCHAR(8) NOT NULL DEFAULT 'INR',
    buyer_name VARCHAR(180) NULL,
    buyer_mobile VARCHAR(32) NULL,
    buyer_email VARCHAR(180) NULL,
    return_url VARCHAR(500) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'PENDING',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_merchant_order(merchant_account_id,order_id),
    UNIQUE KEY uq_merchant_transaction(transaction_id),
    INDEX idx_merchant_order_status(status,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merchant_webhook_deliveries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(80) NOT NULL,
    merchant_account_id BIGINT UNSIGNED NOT NULL,
    merchant_order_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(64) NOT NULL,
    webhook_url VARCHAR(500) NOT NULL,
    payload_json LONGTEXT NOT NULL,
    signature VARCHAR(128) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'PENDING',
    attempts INT NOT NULL DEFAULT 0,
    next_retry_at DATETIME NULL,
    response_code INT NULL,
    response_body VARCHAR(1000) NULL,
    last_error VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_merchant_webhook_event(event_id),
    INDEX idx_merchant_webhook_pending(status,next_retry_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
