
CREATE TABLE IF NOT EXISTS order_status_history (
  id BIGINT NOT NULL AUTO_INCREMENT,
  order_id INT NOT NULL,
  old_status VARCHAR(60) DEFAULT NULL,
  new_status VARCHAR(60) NOT NULL,
  changed_by INT DEFAULT NULL,
  note TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_order_status_history_order (order_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS payment_status_history (
  id BIGINT NOT NULL AUTO_INCREMENT,
  payment_id INT DEFAULT NULL,
  order_id INT NOT NULL,
  old_status VARCHAR(60) DEFAULT NULL,
  new_status VARCHAR(60) NOT NULL,
  source VARCHAR(80) DEFAULT 'manual',
  note TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_payment_status_order (order_id, created_at), KEY idx_payment_status_payment (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS payment_logs (
  id BIGINT NOT NULL AUTO_INCREMENT,
  order_id INT DEFAULT NULL,
  payment_id INT DEFAULT NULL,
  gateway VARCHAR(80) DEFAULT 'manual',
  event_type VARCHAR(120) DEFAULT NULL,
  payload_json LONGTEXT DEFAULT NULL,
  signature_valid TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_payment_logs_order (order_id), KEY idx_payment_logs_payment (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_status VARCHAR(60) DEFAULT 'pending';
ALTER TABLE orders ADD COLUMN IF NOT EXISTS invoice_number VARCHAR(60) DEFAULT NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS service_fee INT NOT NULL DEFAULT 0;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS discount_amount INT NOT NULL DEFAULT 0;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS paid_at DATETIME DEFAULT NULL;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS gateway VARCHAR(80) DEFAULT 'manual';
ALTER TABLE payments ADD COLUMN IF NOT EXISTS gateway_reference VARCHAR(180) DEFAULT NULL;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS payment_url TEXT DEFAULT NULL;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS expired_at DATETIME DEFAULT NULL;

ALTER TABLE orders MODIFY COLUMN status VARCHAR(60) DEFAULT 'pending_payment';
ALTER TABLE payments MODIFY COLUMN status VARCHAR(60) DEFAULT 'waiting_upload';
