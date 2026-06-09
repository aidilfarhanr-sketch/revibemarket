
CREATE TABLE IF NOT EXISTS idempotency_keys (
  id BIGINT NOT NULL AUTO_INCREMENT,
  key_value VARCHAR(190) NOT NULL,
  action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(80) DEFAULT NULL,
  entity_id BIGINT DEFAULT NULL,
  response_hash VARCHAR(128) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uniq_idempotency_action_key (action, key_value), KEY idx_idempotency_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE coin_transactions ADD COLUMN IF NOT EXISTS order_id INT DEFAULT NULL;
ALTER TABLE coin_transactions ADD COLUMN IF NOT EXISTS idempotency_key VARCHAR(190) DEFAULT NULL;
ALTER TABLE coin_transactions ADD UNIQUE INDEX IF NOT EXISTS uniq_coin_idempotency (idempotency_key);
