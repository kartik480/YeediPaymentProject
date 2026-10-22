-- YEEDIPAY platform schema updates (Super Admin, customer panel, billing, payout)
-- Safe to re-run: duplicate column/table errors are ignored by bin/migrate.php

ALTER TABLE customers ADD COLUMN fixed_fee DECIMAL(14,2) NULL;
ALTER TABLE customers ADD COLUMN pct_fee DECIMAL(8,3) NULL;
ALTER TABLE customers ADD COLUMN setup_fee DECIMAL(14,2) NULL;
ALTER TABLE customers ADD COLUMN merchant_qr_fee DECIMAL(14,2) NULL;
ALTER TABLE customers ADD COLUMN min_txn_limit DECIMAL(14,2) NULL;
ALTER TABLE customers ADD COLUMN service_callback_url VARCHAR(255) NULL;

CREATE TABLE IF NOT EXISTS billing_settings (
 id TINYINT UNSIGNED PRIMARY KEY,
 legal_name VARCHAR(220) NOT NULL DEFAULT '',
 gstin VARCHAR(20) NULL,
 registered_state_code VARCHAR(2) NULL,
 gst_rate DECIMAL(8,3) NOT NULL DEFAULT 18.000,
 gst_summary_email VARCHAR(180) NULL,
 accounts_email VARCHAR(180) NULL,
 fee_invoice_prefix VARCHAR(32) NOT NULL DEFAULT 'YDFEE',
 duplicate_scan_minutes INT NOT NULL DEFAULT 10,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO billing_settings(id,legal_name,gst_summary_email,accounts_email,fee_invoice_prefix,duplicate_scan_minutes,gst_rate)
 VALUES(1,'YEEDI PAYMENT SOLUTIONS LIMITED','admin@yeedipay.local','admin@yeedipay.local','YDFEE',10,18.000);

CREATE TABLE IF NOT EXISTS fee_entries (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 entry_date DATE NOT NULL,
 customer_id BIGINT UNSIGNED NULL,
 description VARCHAR(255) NOT NULL DEFAULT '',
 taxable_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
 gst_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
 invoice_ref VARCHAR(64) NULL,
 entry_type VARCHAR(64) NOT NULL DEFAULT 'PLATFORM_FEE',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_fee_entry_date(entry_date),
 INDEX idx_fee_entry_customer(customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE fee_entries ADD COLUMN entry_type VARCHAR(64) NOT NULL DEFAULT 'PLATFORM_FEE';

CREATE TABLE IF NOT EXISTS gst_monthly_summaries (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 month_key VARCHAR(7) NOT NULL,
 taxable_fees DECIMAL(14,2) NOT NULL DEFAULT 0.00,
 output_gst DECIMAL(14,2) NOT NULL DEFAULT 0.00,
 cgst DECIMAL(14,2) NOT NULL DEFAULT 0.00,
 sgst DECIMAL(14,2) NOT NULL DEFAULT 0.00,
 igst DECIMAL(14,2) NOT NULL DEFAULT 0.00,
 fee_entry_count INT NOT NULL DEFAULT 0,
 eligible_itc DECIMAL(14,2) NOT NULL DEFAULT 0.00,
 other_adjustment DECIMAL(14,2) NOT NULL DEFAULT 0.00,
 net_payable DECIMAL(14,2) NOT NULL DEFAULT 0.00,
 status VARCHAR(24) NOT NULL DEFAULT 'GENERATED',
 generated_at DATETIME NULL,
 emailed_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_gst_month(month_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_outbox (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 event_key VARCHAR(160) NOT NULL UNIQUE,
 recipient VARCHAR(180) NOT NULL,
 subject VARCHAR(220) NOT NULL,
 body TEXT NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'QUEUED',
 last_error TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 sent_at DATETIME NULL,
 INDEX idx_email_status(status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pricing_plans (
 plan_key VARCHAR(32) NOT NULL PRIMARY KEY,
 monthly_fee DECIMAL(14,2) NOT NULL DEFAULT 0.00,
 included_payouts INT NOT NULL DEFAULT 0,
 extra_payout_fee DECIMAL(14,2) NOT NULL DEFAULT 0.00,
 included_banks INT NOT NULL DEFAULT 0,
 extra_bank_month DECIMAL(14,2) NOT NULL DEFAULT 0.00,
 custom_format_setup DECIMAL(14,2) NOT NULL DEFAULT 0.00,
 h2h_month DECIMAL(14,2) NOT NULL DEFAULT 0.00,
 api_month DECIMAL(14,2) NOT NULL DEFAULT 0.00,
 white_label_month DECIMAL(14,2) NOT NULL DEFAULT 0.00,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pricing_overrides (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 charge_key VARCHAR(64) NOT NULL,
 unit_price DECIMAL(14,2) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_price_override(customer_id, charge_key),
 INDEX idx_price_override_cust(customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO pricing_plans(plan_key,monthly_fee,included_payouts,extra_payout_fee,included_banks,extra_bank_month,custom_format_setup,h2h_month,api_month,white_label_month) VALUES
('starter',2999.00,1000,3.00,1,500.00,2500.00,5000.00,5000.00,10000.00),
('business',7499.00,5000,2.00,2,500.00,2500.00,5000.00,5000.00,10000.00),
('pro',14999.00,15000,1.50,3,500.00,2500.00,5000.00,5000.00,10000.00);

CREATE TABLE IF NOT EXISTS payout_connectors (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 bank_id BIGINT UNSIGNED NOT NULL,
 mode VARCHAR(16) NOT NULL DEFAULT 'UAT',
 status VARCHAR(24) NOT NULL DEFAULT 'UNVERIFIED',
 verified_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_payout_connector(customer_id, bank_id, mode),
 INDEX idx_payout_connector_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS source_bank_verifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 bank_name VARCHAR(120) NOT NULL,
 account_number VARCHAR(32) NOT NULL,
 ifsc VARCHAR(16) NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'PENDING',
 verified_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_src_bank_cust(customer_id),
 INDEX idx_src_bank_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kyb_documents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 doc_type VARCHAR(40) NOT NULL,
 file_path VARCHAR(255) NOT NULL,
 file_orig VARCHAR(180) NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'PENDING',
 reviewed_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_kyb_cust(customer_id),
 INDEX idx_kyb_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE admin_users ADD COLUMN name VARCHAR(180) NULL;
ALTER TABLE admin_users ADD COLUMN email VARCHAR(180) NULL;
ALTER TABLE admin_users ADD COLUMN role VARCHAR(32) NOT NULL DEFAULT 'CHECKER';

CREATE TABLE IF NOT EXISTS support_tickets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reference VARCHAR(32) NOT NULL UNIQUE,
 customer_id BIGINT UNSIGNED NOT NULL,
 subject VARCHAR(220) NOT NULL,
 category VARCHAR(64) NOT NULL DEFAULT 'GENERAL',
 message TEXT NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'OPEN',
 priority VARCHAR(16) NOT NULL DEFAULT 'NORMAL',
 assigned_to VARCHAR(80) NULL,
 admin_note TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 resolved_at DATETIME NULL,
 INDEX idx_ticket_customer(customer_id),
 INDEX idx_ticket_status(status),
 INDEX idx_ticket_created(created_at),
 CONSTRAINT fk_ticket_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_ticket_replies (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 ticket_id BIGINT UNSIGNED NOT NULL,
 author_type VARCHAR(16) NOT NULL,
 author_name VARCHAR(120) NOT NULL DEFAULT '',
 message TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_reply_ticket(ticket_id),
 CONSTRAINT fk_reply_ticket FOREIGN KEY(ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallet_balances (
 customer_id BIGINT UNSIGNED PRIMARY KEY,
 available_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
 pending_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_wallet_bal_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallet_recharges (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 transaction_id BIGINT UNSIGNED NULL,
 reference VARCHAR(64) NOT NULL UNIQUE,
 amount DECIMAL(14,2) NOT NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'PENDING',
 bank_id BIGINT UNSIGNED NULL,
 utr VARCHAR(160) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 credited_at DATETIME NULL,
 INDEX idx_wr_customer_status (customer_id, status),
 INDEX idx_wr_txn (transaction_id),
 INDEX idx_wr_created (created_at),
 CONSTRAINT fk_wr_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
 CONSTRAINT fk_wr_txn FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS withdrawal_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 reference VARCHAR(64) NOT NULL UNIQUE,
 amount DECIMAL(14,2) NOT NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'REQUESTED',
 account_holder VARCHAR(180) NOT NULL DEFAULT '',
 bank_name VARCHAR(120) NOT NULL,
 account_number VARCHAR(32) NOT NULL,
 ifsc VARCHAR(16) NOT NULL,
 admin_note TEXT NULL,
 bank_ref VARCHAR(160) NULL,
 processed_by VARCHAR(80) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 processed_at DATETIME NULL,
 INDEX idx_wd_customer_status (customer_id, status),
 INDEX idx_wd_created (created_at),
 CONSTRAINT fk_wd_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE transactions ADD COLUMN customer_reported_email VARCHAR(180) NULL;
ALTER TABLE transactions ADD COLUMN customer_paid_ack_at DATETIME NULL;
