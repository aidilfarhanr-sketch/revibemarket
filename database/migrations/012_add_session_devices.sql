
CREATE TABLE IF NOT EXISTS user_sessions (
  id BIGINT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  session_token_hash CHAR(64) NOT NULL,
  ip_address VARCHAR(80) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  last_seen_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id), UNIQUE KEY uniq_user_session_hash (session_token_hash), KEY idx_user_sessions_user (user_id, revoked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE users ADD COLUMN IF NOT EXISTS locked_until DATETIME DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_seen_ip VARCHAR(80) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_seen_user_agent VARCHAR(255) DEFAULT NULL;
