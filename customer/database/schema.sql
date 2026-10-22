SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS admin_users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(80) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS auth_rate_limits (
  rate_key VARCHAR(160) PRIMARY KEY,
  attempts INT NOT NULL DEFAULT 0,
  window_started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  blocked_until DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_config (
 id TINYINT UNSIGNED PRIMARY KEY,
 manual_active_bank_id BIGINT UNSIGNED NULL,
 rotation_enabled TINYINT(1) NOT NULL DEFAULT 0,
 rotation_started_at DATETIME NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO system_config(id,rotation_enabled) VALUES(1,0);

CREATE TABLE IF NOT EXISTS bank_profiles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(32) NOT NULL UNIQUE,
 bank_name VARCHAR(120) NOT NULL,
 label VARCHAR(120) NULL,
 vpa VARCHAR(180) NOT NULL,
 payee_name VARCHAR(220) NOT NULL,
 currency VARCHAR(8) NOT NULL DEFAULT 'INR',
 qr_payload TEXT NOT NULL,
 qr_params_json TEXT NOT NULL,
 qr_image_path VARCHAR(255) NULL,
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 rotation_order INT NOT NULL DEFAULT 1,
 rotation_minutes INT NOT NULL DEFAULT 60,
 signed_qr TINYINT(1) NOT NULL DEFAULT 0,
 webhook_secret VARCHAR(96) NOT NULL,
 mcc VARCHAR(16) NULL,
 provider_name VARCHAR(120) NULL,
 provider_base_url VARCHAR(255) NULL,
 create_order_path VARCHAR(255) NULL,
 status_path VARCHAR(255) NULL,
 refund_path VARCHAR(255) NULL,
 api_key_encrypted TEXT NULL,
 api_secret_encrypted TEXT NULL,
 reference_field VARCHAR(120) NOT NULL DEFAULT 'reference',
 status_field VARCHAR(120) NOT NULL DEFAULT 'status',
 amount_field VARCHAR(120) NOT NULL DEFAULT 'amount',
 txn_id_field VARCHAR(120) NOT NULL DEFAULT 'upi_txn_id',
 rrn_field VARCHAR(120) NOT NULL DEFAULT 'rrn',
 payer_vpa_field VARCHAR(120) NOT NULL DEFAULT 'payer_vpa',
 success_values VARCHAR(200) NOT NULL DEFAULT 'SUCCESS,SUCCESSFUL,PAID,COMPLETED',
 failed_values VARCHAR(200) NOT NULL DEFAULT 'FAILED,FAILURE,DECLINED,REVERSED',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_bank_enabled(enabled), INDEX idx_bank_vpa(vpa), INDEX idx_bank_rotation(rotation_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE system_config ADD CONSTRAINT fk_system_active_bank FOREIGN KEY (manual_active_bank_id) REFERENCES bank_profiles(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS customers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(32) NOT NULL UNIQUE,
 name VARCHAR(180) NOT NULL,
 phone VARCHAR(24) NOT NULL,
 email VARCHAR(180) NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 per_txn_limit DECIMAL(14,2) NULL,
 daily_limit DECIMAL(14,2) NULL,
 weekly_limit DECIMAL(14,2) NULL,
 monthly_limit DECIMAL(14,2) NULL,
 daily_txn_count_limit INT NULL,
 max_pending_attempts INT NOT NULL DEFAULT 5,
 login_id VARCHAR(64) NULL UNIQUE,
 password_hash VARCHAR(255) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_customer_phone(phone), INDEX idx_customer_active(active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 bank_id BIGINT UNSIGNED NOT NULL,
 reference VARCHAR(120) NOT NULL UNIQUE,
 status ENUM('PENDING','SUCCESS','FAILED','EXPIRED') NOT NULL DEFAULT 'PENDING',
 requested_amount DECIMAL(14,2) NULL,
 confirmed_amount DECIMAL(14,2) NULL,
 intent_uri TEXT NOT NULL,
 upi_txn_id VARCHAR(160) NULL,
 rrn VARCHAR(160) NULL,
 payer_vpa VARCHAR(180) NULL,
 confirmation_source VARCHAR(64) NULL,
 raw_confirmation TEXT NULL,
 customer_reported_utr VARCHAR(160) NULL UNIQUE,
 customer_reported_at DATETIME NULL,
 limit_breach TINYINT(1) NOT NULL DEFAULT 0,
 limit_note TEXT NOT NULL,
 service VARCHAR(64) NOT NULL DEFAULT 'UPI',
 settlement_status VARCHAR(32) NOT NULL DEFAULT 'UNSETTLED',
 invoice_ref VARCHAR(160) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_txn_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
 CONSTRAINT fk_txn_bank FOREIGN KEY(bank_id) REFERENCES bank_profiles(id) ON DELETE RESTRICT,
 settlement_id BIGINT UNSIGNED NULL,
 INDEX idx_txn_customer_status(customer_id,status), INDEX idx_txn_bank_status(bank_id,status), INDEX idx_txn_created(created_at), UNIQUE KEY uq_txn_bank_rrn(bank_id,rrn), UNIQUE KEY uq_txn_bank_upi(bank_id,upi_txn_id), INDEX idx_txn_rrn(rrn), INDEX idx_txn_upi(upi_txn_id), INDEX idx_txn_limit(limit_breach), INDEX idx_txn_settlement(settlement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settlements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reference VARCHAR(64) NOT NULL UNIQUE,
 range_start DATE NOT NULL,
 range_end DATE NOT NULL,
 txn_count INT NOT NULL DEFAULT 0,
 gross_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
 fee_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
 tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
 net_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
 status VARCHAR(32) NOT NULL DEFAULT 'REQUESTED',
 bank_ref VARCHAR(160) NULL,
 created_by VARCHAR(80) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 settled_at DATETIME NULL,
 INDEX idx_stl_status(status), INDEX idx_stl_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS otp_codes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 otp_hash VARCHAR(128) NOT NULL,
 expires_at DATETIME NOT NULL,
 consumed TINYINT(1) NOT NULL DEFAULT 0,
 attempts INT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_otp_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
 INDEX idx_otp_customer(customer_id,created_at), INDEX idx_otp_exp(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_gateway_settings (
 id TINYINT UNSIGNED PRIMARY KEY,
 enabled TINYINT(1) NOT NULL DEFAULT 0,
 gateway_name VARCHAR(120) NOT NULL DEFAULT 'Generic SMS Gateway',
 endpoint_url TEXT NULL,
 http_method VARCHAR(8) NOT NULL DEFAULT 'POST',
 content_type VARCHAR(16) NOT NULL DEFAULT 'json',
 auth_header_name VARCHAR(80) NOT NULL DEFAULT 'Authorization',
 auth_prefix VARCHAR(40) NOT NULL DEFAULT 'Bearer ',
 auth_token_encrypted TEXT NULL,
 sender_id VARCHAR(32) NOT NULL DEFAULT 'YEEDIPAY',
 request_template TEXT NOT NULL,
 otp_message TEXT NOT NULL,
 pending_message TEXT NOT NULL,
 success_message TEXT NOT NULL,
 failed_message TEXT NOT NULL,
 expired_message TEXT NOT NULL,
 otp_template_id VARCHAR(120) NOT NULL DEFAULT '',
 pending_template_id VARCHAR(120) NOT NULL DEFAULT '',
 success_template_id VARCHAR(120) NOT NULL DEFAULT '',
 failed_template_id VARCHAR(120) NOT NULL DEFAULT '',
 expired_template_id VARCHAR(120) NOT NULL DEFAULT '',
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_outbox (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 event_key VARCHAR(180) NOT NULL UNIQUE,
 phone VARCHAR(24) NOT NULL,
 message TEXT NOT NULL,
 template_id VARCHAR(120) NOT NULL DEFAULT '',
 status ENUM('QUEUED','RETRY','SENT','FAILED') NOT NULL DEFAULT 'QUEUED',
 attempts INT NOT NULL DEFAULT 0,
 last_error TEXT NOT NULL,
 next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 sent_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_sms_status_next(status,next_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 actor VARCHAR(80) NOT NULL,
 action VARCHAR(120) NOT NULL,
 detail TEXT NOT NULL,
 ip_address VARCHAR(64) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_audit_action(action), INDEX idx_audit_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
