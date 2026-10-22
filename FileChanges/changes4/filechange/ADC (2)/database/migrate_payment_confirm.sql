ALTER TABLE customers ADD COLUMN service_activated TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE customers ADD COLUMN service_activated_at DATETIME NULL;
UPDATE customers c
  JOIN transactions t ON t.customer_id=c.id AND t.status='SUCCESS'
  SET c.service_activated=1, c.service_activated_at=COALESCE(c.service_activated_at, t.updated_at);
