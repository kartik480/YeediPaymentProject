ALTER TABLE customers ADD COLUMN account_number VARCHAR(24) NULL;
ALTER TABLE customers ADD UNIQUE KEY uq_customer_account(account_number);
