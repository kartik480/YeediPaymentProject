-- Transactions list columns for existing YEEDIPAY databases.
-- The admin Transactions page also applies these automatically.

ALTER TABLE customers ADD COLUMN email VARCHAR(180) NULL;

ALTER TABLE transactions
  ADD COLUMN service VARCHAR(64) NOT NULL DEFAULT 'UPI',
  ADD COLUMN settlement_status VARCHAR(32) NOT NULL DEFAULT 'UNSETTLED';

UPDATE transactions SET service='UPI' WHERE service IS NULL OR service='';
UPDATE transactions SET settlement_status='N/A' WHERE status IN ('FAILED','EXPIRED') AND settlement_status='UNSETTLED';
