

CREATE TABLE IF NOT EXISTS app_sessions (
  id BIGINT NOT NULL AUTO_INCREMENT,
  session_id_hash CHAR(64) NOT NULL,
  payload LONGTEXT NOT NULL,
  ip_address VARCHAR(64) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  expires_at DATETIME DEFAULT NULL,
  revoked_at DATETIME DEFAULT NULL,
  revoked_reason VARCHAR(120) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_app_sessions_hash (session_id_hash),
  KEY idx_app_sessions_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS system_logs (
  id BIGINT NOT NULL AUTO_INCREMENT,
  level VARCHAR(30) NOT NULL DEFAULT 'info',
  message VARCHAR(255) NOT NULL,
  context_json LONGTEXT DEFAULT NULL,
  request_id VARCHAR(80) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_system_logs_level (level),
  KEY idx_system_logs_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE verification_codes ADD COLUMN IF NOT EXISTS last_sent_at DATETIME DEFAULT NULL;
ALTER TABLE verification_codes ADD COLUMN IF NOT EXISTS resend_count INT NOT NULL DEFAULT 0;
ALTER TABLE verification_codes ADD INDEX IF NOT EXISTS idx_verification_purpose_user (purpose, user_id, channel, verified_at, expires_at);

ALTER TABLE seller_ledger ADD COLUMN IF NOT EXISTS request_id VARCHAR(80) DEFAULT NULL;
ALTER TABLE seller_ledger ADD INDEX IF NOT EXISTS idx_seller_ledger_balance_type (seller_id, balance_type);
ALTER TABLE seller_balance_transactions ADD COLUMN IF NOT EXISTS idempotency_key VARCHAR(190) DEFAULT NULL;
ALTER TABLE seller_balance_transactions ADD COLUMN IF NOT EXISTS balance_type VARCHAR(40) DEFAULT 'available';
ALTER TABLE seller_balance_transactions ADD UNIQUE INDEX IF NOT EXISTS uniq_sbt_idempotency (idempotency_key);

ALTER TABLE storage_files ADD COLUMN IF NOT EXISTS checksum_sha256 CHAR(64) DEFAULT NULL;
ALTER TABLE storage_files ADD COLUMN IF NOT EXISTS metadata_json LONGTEXT DEFAULT NULL;
ALTER TABLE storage_files ADD COLUMN IF NOT EXISTS deleted_at DATETIME DEFAULT NULL;
ALTER TABLE storage_files ADD INDEX IF NOT EXISTS idx_storage_visibility (visibility, disk);

ALTER TABLE notification_queue ADD COLUMN IF NOT EXISTS locked_at DATETIME DEFAULT NULL;
ALTER TABLE notification_queue ADD COLUMN IF NOT EXISTS locked_by VARCHAR(120) DEFAULT NULL;
ALTER TABLE notification_queue ADD INDEX IF NOT EXISTS idx_notification_queue_schedule (status, scheduled_at, retry_count);

CREATE TABLE IF NOT EXISTS idempotency_keys (
  id BIGINT NOT NULL AUTO_INCREMENT,
  idempotency_key VARCHAR(190) NOT NULL,
  scope VARCHAR(80) DEFAULT NULL,
  reference_type VARCHAR(80) DEFAULT NULL,
  reference_id BIGINT DEFAULT NULL,
  response_json LONGTEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_idempotency_key (idempotency_key),
  KEY idx_idempotency_reference (reference_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
