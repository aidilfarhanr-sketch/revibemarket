
CREATE TABLE IF NOT EXISTS seller_balances (
  id BIGINT NOT NULL AUTO_INCREMENT,
  seller_id INT DEFAULT NULL,
  user_id INT DEFAULT NULL,
  pending_balance BIGINT NOT NULL DEFAULT 0,
  available_balance BIGINT NOT NULL DEFAULT 0,
  withdrawn_balance BIGINT NOT NULL DEFAULT 0,
  total_earned BIGINT NOT NULL DEFAULT 0,
  balance BIGINT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uniq_seller_balance_seller (seller_id), UNIQUE KEY uniq_seller_balance_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS seller_ledger (
  id BIGINT NOT NULL AUTO_INCREMENT,
  seller_id INT NOT NULL,
  order_id INT DEFAULT NULL,
  withdrawal_id INT DEFAULT NULL,
  type VARCHAR(80) NOT NULL,
  amount BIGINT NOT NULL,
  balance_type VARCHAR(40) DEFAULT 'available',
  balance_before BIGINT NOT NULL DEFAULT 0,
  balance_after BIGINT NOT NULL DEFAULT 0,
  idempotency_key VARCHAR(190) DEFAULT NULL,
  description VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uniq_seller_ledger_idempotency (idempotency_key), KEY idx_seller_ledger_seller (seller_id), KEY idx_seller_ledger_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE seller_balances ADD COLUMN IF NOT EXISTS seller_id INT DEFAULT NULL;
ALTER TABLE seller_balances ADD COLUMN IF NOT EXISTS pending_balance BIGINT NOT NULL DEFAULT 0;
ALTER TABLE seller_balances ADD COLUMN IF NOT EXISTS available_balance BIGINT NOT NULL DEFAULT 0;
ALTER TABLE seller_balances ADD COLUMN IF NOT EXISTS withdrawn_balance BIGINT NOT NULL DEFAULT 0;
ALTER TABLE seller_balances ADD COLUMN IF NOT EXISTS total_earned BIGINT NOT NULL DEFAULT 0;
ALTER TABLE seller_balance_transactions ADD COLUMN IF NOT EXISTS idempotency_key VARCHAR(190) DEFAULT NULL;
ALTER TABLE seller_balance_transactions ADD COLUMN IF NOT EXISTS balance_type VARCHAR(40) DEFAULT 'available';
ALTER TABLE seller_balance_transactions ADD UNIQUE INDEX IF NOT EXISTS uniq_sbt_idempotency (idempotency_key);
