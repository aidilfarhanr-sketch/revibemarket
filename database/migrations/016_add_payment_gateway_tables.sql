
CREATE TABLE IF NOT EXISTS payment_gateway_requests (
  id BIGINT NOT NULL AUTO_INCREMENT,
  order_id INT NOT NULL,
  payment_id INT DEFAULT NULL,
  gateway VARCHAR(80) NOT NULL,
  request_json LONGTEXT DEFAULT NULL,
  response_json LONGTEXT DEFAULT NULL,
  status VARCHAR(60) DEFAULT 'created',
  gateway_reference VARCHAR(190) DEFAULT NULL,
  payment_url TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_pg_order (order_id), KEY idx_pg_ref (gateway_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS snap_token VARCHAR(255) DEFAULT NULL;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS gateway_payload LONGTEXT DEFAULT NULL;
ALTER TABLE payments ADD INDEX IF NOT EXISTS idx_payments_status (status);
ALTER TABLE payments ADD INDEX IF NOT EXISTS idx_payments_order_status (order_id, status);
