-- YEEDI SMS AUTO V5 CORE DEVELOPED
-- Three-module architecture: Android SMS Relay <-> Server Core <-> Customer Dashboard
-- MySQL 8 / MariaDB compatible baseline. All server timestamps are UTC.

CREATE TABLE IF NOT EXISTS sms_relay_devices (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    device_id VARCHAR(64) NOT NULL,
    device_label VARCHAR(100) NOT NULL,
    api_secret VARCHAR(128) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    last_seen_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sms_relay_device_id (device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS merchant_clients (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id VARCHAR(64) NOT NULL,
    client_name VARCHAR(120) NOT NULL,
    api_secret VARCHAR(128) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_merchant_client_id (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bank_receiving_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_key VARCHAR(64) NOT NULL,
    bank_name VARCHAR(20) NOT NULL,
    account_label VARCHAR(100) NOT NULL,
    account_last4 CHAR(4) NULL,
    upi_id VARCHAR(120) NULL,
    payee_name VARCHAR(120) NULL,
    qr_image_url VARCHAR(500) NULL,
    sort_order INT NOT NULL DEFAULT 100,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_receiving_account_key (account_key),
    UNIQUE KEY uq_bank_account_mask (bank_name, account_last4),
    KEY ix_receiving_bank_active (bank_name, active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS qr_payment_orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_id VARCHAR(64) NOT NULL,
    merchant_client_id BIGINT UNSIGNED NULL,
    merchant_order_id VARCHAR(100) NULL,
    public_token_hash CHAR(64) NOT NULL,
    receiving_account_id BIGINT UNSIGNED NOT NULL,
    bank_name VARCHAR(20) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    status ENUM('PENDING','SUCCESS','FAILED','EXPIRED') NOT NULL DEFAULT 'PENDING',
    submitted_utr VARCHAR(35) NULL COMMENT 'Auto-filled only from verified bank SMS',
    customer_ack_at DATETIME NULL,
    last_status_poll_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    sms_confirmed_at DATETIME NULL,
    paid_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_qr_payment_id (payment_id),
    UNIQUE KEY uq_qr_bank_submitted_utr (bank_name, submitted_utr),
    UNIQUE KEY uq_merchant_order (merchant_client_id, merchant_order_id),
    KEY ix_qr_payment_match (status, bank_name, receiving_account_id, amount, created_at, expires_at),
    KEY ix_qr_collision (receiving_account_id, amount, status, expires_at),
    CONSTRAINT fk_qr_receiving_account FOREIGN KEY (receiving_account_id) REFERENCES bank_receiving_accounts(id),
    CONSTRAINT fk_qr_merchant_client FOREIGN KEY (merchant_client_id) REFERENCES merchant_clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bank_sms_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id CHAR(64) NOT NULL,
    device_id VARCHAR(64) NOT NULL,
    receiving_account_id BIGINT UNSIGNED NULL,
    bank_name VARCHAR(20) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    reference_no VARCHAR(35) NOT NULL,
    account_last4 CHAR(4) NULL,
    sender_id VARCHAR(80) NOT NULL,
    received_at DATETIME NOT NULL,
    match_state ENUM('UNMATCHED','MATCHED','AMBIGUOUS') NOT NULL DEFAULT 'UNMATCHED',
    matched_payment_id BIGINT UNSIGNED NULL,
    matched_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bank_sms_event_id (event_id),
    UNIQUE KEY uq_bank_sms_reference (bank_name, reference_no),
    KEY ix_bank_sms_match (bank_name, receiving_account_id, amount, match_state),
    KEY ix_bank_sms_received (received_at),
    CONSTRAINT fk_sms_receiving_account FOREIGN KEY (receiving_account_id) REFERENCES bank_receiving_accounts(id),
    CONSTRAINT fk_sms_payment_order FOREIGN KEY (matched_payment_id) REFERENCES qr_payment_orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
