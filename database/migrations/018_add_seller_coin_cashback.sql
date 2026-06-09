
CREATE TABLE IF NOT EXISTS seller_coin_ledger (
  id BIGINT NOT NULL AUTO_INCREMENT,
  seller_id INT NOT NULL,
  order_id INT DEFAULT NULL,
  type VARCHAR(80) NOT NULL,
  amount BIGINT NOT NULL,
  idempotency_key VARCHAR(190) DEFAULT NULL,
  description VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uniq_seller_coin_idempotency (idempotency_key), KEY idx_seller_coin_seller (seller_id), KEY idx_seller_coin_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE coin_transactions ADD COLUMN IF NOT EXISTS idempotency_key VARCHAR(190) DEFAULT NULL;
ALTER TABLE coin_transactions ADD COLUMN IF NOT EXISTS order_id INT DEFAULT NULL;
ALTER TABLE coin_transactions ADD INDEX IF NOT EXISTS idx_coin_order (order_id);
