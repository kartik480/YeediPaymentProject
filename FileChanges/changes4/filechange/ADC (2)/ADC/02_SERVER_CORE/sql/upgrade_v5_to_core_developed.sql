-- RUN ONCE on an existing SMS AUTO FINAL V5 database.
-- Take a database backup before running.

CREATE TABLE merchant_clients (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id VARCHAR(64) NOT NULL,
    client_name VARCHAR(120) NOT NULL,
    api_secret VARCHAR(128) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_merchant_client_id (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE bank_receiving_accounts
    ADD COLUMN upi_id VARCHAR(120) NULL AFTER account_last4,
    ADD COLUMN payee_name VARCHAR(120) NULL AFTER upi_id,
    ADD COLUMN qr_image_url VARCHAR(500) NULL AFTER payee_name,
    ADD COLUMN sort_order INT NOT NULL DEFAULT 100 AFTER qr_image_url,
    ADD KEY ix_receiving_bank_active_sort (bank_name, active, sort_order);

ALTER TABLE qr_payment_orders
    ADD COLUMN merchant_client_id BIGINT UNSIGNED NULL AFTER payment_id,
    ADD COLUMN merchant_order_id VARCHAR(100) NULL AFTER merchant_client_id,
    ADD COLUMN public_token_hash CHAR(64) NULL AFTER merchant_order_id,
    ADD COLUMN customer_ack_at DATETIME NULL AFTER submitted_utr,
    ADD COLUMN last_status_poll_at DATETIME NULL AFTER customer_ack_at,
    ADD UNIQUE KEY uq_merchant_order (merchant_client_id, merchant_order_id),
    ADD KEY ix_qr_collision (receiving_account_id, amount, status, expires_at),
    ADD CONSTRAINT fk_qr_merchant_client FOREIGN KEY (merchant_client_id) REFERENCES merchant_clients(id);
