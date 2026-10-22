-- Settlements for existing YEEDIPAY databases.
-- The admin Settlements page also applies this automatically.

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
 INDEX idx_stl_status(status),
 INDEX idx_stl_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE transactions ADD COLUMN settlement_id BIGINT UNSIGNED NULL, ADD INDEX idx_txn_settlement(settlement_id);
