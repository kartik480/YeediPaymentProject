-- YEEDIPAY Razorpay integration (Orders + Checkout + verified webhook)
-- Isolated ledger: does NOT touch transactions / bank-SMS / settlement logic.
-- Safe to run repeatedly (IF NOT EXISTS). Rollback: see docs/RAZORPAY_INTEGRATION.md.

CREATE TABLE IF NOT EXISTS razorpay_settings (
  id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  live_mode TINYINT(1) NOT NULL DEFAULT 0,
  key_id VARCHAR(64) NOT NULL DEFAULT '',
  key_secret_encrypted TEXT NULL,
  webhook_secret_encrypted TEXT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'INR',
  button_label VARCHAR(80) NOT NULL DEFAULT 'YEEDIPAY Payment',
  min_amount DECIMAL(14,2) NOT NULL DEFAULT 1.00,
  max_amount DECIMAL(14,2) NOT NULL DEFAULT 100000.00,
  webhook_configured TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_rzp_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO razorpay_settings (id) VALUES (1);

CREATE TABLE IF NOT EXISTS razorpay_orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  local_reference VARCHAR(64) NOT NULL,
  customer_id BIGINT UNSIGNED NULL,
  customer_code VARCHAR(32) NULL,
  buyer_name VARCHAR(180) NULL,
  rzp_order_id VARCHAR(64) NOT NULL,
  rzp_payment_id VARCHAR(64) NULL,
  amount_paise BIGINT UNSIGNED NOT NULL,
  captured_amount_paise BIGINT UNSIGNED NULL,
  currency CHAR(3) NOT NULL DEFAULT 'INR',
  status ENUM('CREATED','PENDING','PAID','FAILED','CANCELLED','REVIEW') NOT NULL DEFAULT 'CREATED',
  verified TINYINT(1) NOT NULL DEFAULT 0,
  verification_source VARCHAR(32) NULL,
  verification_note VARCHAR(500) NULL,
  method VARCHAR(40) NULL,
  email VARCHAR(190) NULL,
  contact VARCHAR(40) NULL,
  notes_json TEXT NULL,
  live_mode TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  paid_at DATETIME NULL,
  UNIQUE KEY uq_rzp_local_reference (local_reference),
  UNIQUE KEY uq_rzp_order_id (rzp_order_id),
  UNIQUE KEY uq_rzp_payment_id (rzp_payment_id),
  INDEX idx_rzp_status (status),
  INDEX idx_rzp_customer (customer_id, status),
  INDEX idx_rzp_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS razorpay_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id VARCHAR(80) NOT NULL,
  event_type VARCHAR(64) NOT NULL,
  rzp_order_id VARCHAR(64) NULL,
  rzp_payment_id VARCHAR(64) NULL,
  signature_valid TINYINT(1) NOT NULL DEFAULT 0,
  amount_paise BIGINT UNSIGNED NULL,
  currency CHAR(3) NULL,
  processed TINYINT(1) NOT NULL DEFAULT 0,
  process_note VARCHAR(500) NULL,
  payload_json LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rzp_event_id (event_id),
  INDEX idx_rzp_event_order (rzp_order_id),
  INDEX idx_rzp_event_payment (rzp_payment_id),
  INDEX idx_rzp_event_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
