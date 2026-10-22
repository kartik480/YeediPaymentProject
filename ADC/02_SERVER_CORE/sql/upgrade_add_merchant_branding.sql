-- Adds merchant branding and customer display fields.
-- Run ONCE on an existing database. Take a backup first.

ALTER TABLE merchant_clients
    ADD COLUMN display_name VARCHAR(200) NULL AFTER client_name,
    ADD COLUMN logo_url VARCHAR(500) NULL AFTER display_name,
    ADD COLUMN theme_color VARCHAR(7) NOT NULL DEFAULT '#FF6B35' AFTER logo_url,
    ADD COLUMN support_whatsapp VARCHAR(20) NULL AFTER theme_color,
    ADD COLUMN support_email VARCHAR(200) NULL AFTER support_whatsapp,
    ADD COLUMN video_url VARCHAR(500) NULL AFTER support_email,
    ADD COLUMN website_url VARCHAR(500) NULL AFTER video_url,
    ADD COLUMN quick_amounts VARCHAR(500) NOT NULL DEFAULT '100,500,1000,2000,5000,10000,25000,50000' AFTER website_url;

ALTER TABLE qr_payment_orders
    ADD COLUMN customer_name VARCHAR(200) NULL AFTER merchant_order_id,
    ADD COLUMN customer_display_id VARCHAR(100) NULL AFTER customer_name;
