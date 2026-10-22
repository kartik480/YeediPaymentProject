-- Banks & Routing columns for existing YEEDIPAY databases.
-- Safe to run more than once if you skip columns that already exist.
-- The admin Banks & Routing page also applies these columns automatically.

ALTER TABLE bank_profiles
  ADD COLUMN mcc VARCHAR(16) NULL,
  ADD COLUMN provider_name VARCHAR(120) NULL,
  ADD COLUMN provider_base_url VARCHAR(255) NULL,
  ADD COLUMN create_order_path VARCHAR(255) NULL,
  ADD COLUMN status_path VARCHAR(255) NULL,
  ADD COLUMN refund_path VARCHAR(255) NULL,
  ADD COLUMN api_key_encrypted TEXT NULL,
  ADD COLUMN api_secret_encrypted TEXT NULL;
