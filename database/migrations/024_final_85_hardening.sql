

CREATE TABLE IF NOT EXISTS app_sessions (
  id BIGINT NOT NULL AUTO_INCREMENT,
  session_id_hash CHAR(64) NOT NULL,
  user_id INT DEFAULT NULL,
  payload LONGTEXT DEFAULT NULL,
  ip_address VARCHAR(80) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  expires_at DATETIME DEFAULT NULL,
  revoked_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_app_session_hash (session_id_hash),
  KEY idx_app_sessions_user (user_id),
  KEY idx_app_sessions_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS queue_jobs (
  id BIGINT NOT NULL AUTO_INCREMENT,
  queue_name VARCHAR(80) NOT NULL DEFAULT 'default',
  payload_json LONGTEXT NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  max_attempts INT NOT NULL DEFAULT 3,
  available_at DATETIME DEFAULT NULL,
  reserved_at DATETIME DEFAULT NULL,
  completed_at DATETIME DEFAULT NULL,
  failed_at DATETIME DEFAULT NULL,
  last_error TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_queue_jobs_ready (queue_name, completed_at, failed_at, available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cache_items (
  cache_key VARCHAR(190) NOT NULL,
  cache_value LONGTEXT NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (cache_key),
  KEY idx_cache_items_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS health_checks (
  id BIGINT NOT NULL AUTO_INCREMENT,
  check_name VARCHAR(120) NOT NULL,
  status VARCHAR(40) NOT NULL,
  detail_json LONGTEXT DEFAULT NULL,
  checked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_health_name_checked (check_name, checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE storage_files ADD COLUMN IF NOT EXISTS checksum_sha256 CHAR(64) DEFAULT NULL;
ALTER TABLE storage_files ADD COLUMN IF NOT EXISTS entity_type VARCHAR(80) DEFAULT NULL;
ALTER TABLE storage_files ADD COLUMN IF NOT EXISTS entity_id BIGINT DEFAULT NULL;
ALTER TABLE storage_files ADD COLUMN IF NOT EXISTS metadata_json LONGTEXT DEFAULT NULL;
ALTER TABLE storage_files ADD INDEX IF NOT EXISTS idx_storage_entity (entity_type, entity_id);

ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS request_id VARCHAR(80) DEFAULT NULL;
ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS processed_at DATETIME DEFAULT NULL;
ALTER TABLE payment_logs ADD COLUMN IF NOT EXISTS error_message TEXT DEFAULT NULL;

ALTER TABLE payments ADD COLUMN IF NOT EXISTS webhook_processed_at DATETIME DEFAULT NULL;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS provider_fee BIGINT NOT NULL DEFAULT 0;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS net_amount BIGINT NOT NULL DEFAULT 0;

ALTER TABLE orders ADD COLUMN IF NOT EXISTS auto_complete_at DATETIME DEFAULT NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS escrow_released_at DATETIME DEFAULT NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS refund_status VARCHAR(60) DEFAULT NULL;

ALTER TABLE seller_withdrawals ADD COLUMN IF NOT EXISTS idempotency_key VARCHAR(190) DEFAULT NULL;
ALTER TABLE seller_withdrawals ADD UNIQUE INDEX IF NOT EXISTS uniq_seller_withdrawals_idem (idempotency_key);

ALTER TABLE notification_queue ADD COLUMN IF NOT EXISTS locked_at DATETIME DEFAULT NULL;
ALTER TABLE notification_queue ADD COLUMN IF NOT EXISTS locked_by VARCHAR(120) DEFAULT NULL;
ALTER TABLE notification_queue ADD INDEX IF NOT EXISTS idx_notification_retry_ready (status, retry_count, scheduled_at);

ALTER TABLE verification_codes ADD INDEX IF NOT EXISTS idx_verification_resend (user_id, channel, purpose, last_sent_at);
ALTER TABLE rate_limits ADD INDEX IF NOT EXISTS idx_rate_limits_cleanup (created_at);
ALTER TABLE user_sessions ADD COLUMN IF NOT EXISTS revoked_reason VARCHAR(190) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS backup_runs (
  id BIGINT NOT NULL AUTO_INCREMENT,
  backup_type VARCHAR(40) NOT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'started',
  file_path VARCHAR(500) DEFAULT NULL,
  size_bytes BIGINT DEFAULT 0,
  checksum_sha256 CHAR(64) DEFAULT NULL,
  started_at DATETIME DEFAULT NULL,
  finished_at DATETIME DEFAULT NULL,
  error_message TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_backup_runs_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
