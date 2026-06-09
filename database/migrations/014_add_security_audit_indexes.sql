
CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT NOT NULL AUTO_INCREMENT,
  user_id INT DEFAULT NULL,
  action VARCHAR(160) NOT NULL,
  entity_type VARCHAR(80) DEFAULT NULL,
  entity_id BIGINT DEFAULT NULL,
  ip_address VARCHAR(80) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  context_json LONGTEXT DEFAULT NULL,
  request_id VARCHAR(80) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_audit_user_created (user_id, created_at), KEY idx_audit_entity (entity_type, entity_id), KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS system_logs (
  id BIGINT NOT NULL AUTO_INCREMENT,
  level VARCHAR(20) NOT NULL,
  request_id VARCHAR(80) DEFAULT NULL,
  message TEXT NOT NULL,
  context_json LONGTEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_system_level_created (level, created_at), KEY idx_system_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS entity_type VARCHAR(80) DEFAULT NULL;
ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS entity_id BIGINT DEFAULT NULL;
ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS context_json LONGTEXT DEFAULT NULL;
ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS request_id VARCHAR(80) DEFAULT NULL;
ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_orders_user_id (buyer_id);
ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_orders_seller_id (seller_id);
ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_orders_status_created (status, created_at);
ALTER TABLE products ADD INDEX IF NOT EXISTS idx_products_seller_status (user_id, product_status);
ALTER TABLE chat_messages ADD INDEX IF NOT EXISTS idx_chat_sender (sender_id);
ALTER TABLE chat_messages ADD INDEX IF NOT EXISTS idx_chat_receiver (receiver_id);
