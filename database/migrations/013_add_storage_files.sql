
CREATE TABLE IF NOT EXISTS storage_files (
  id BIGINT NOT NULL AUTO_INCREMENT,
  user_id INT DEFAULT NULL,
  disk VARCHAR(40) DEFAULT 'local',
  visibility VARCHAR(40) DEFAULT 'private',
  original_name VARCHAR(255) DEFAULT NULL,
  stored_name VARCHAR(255) NOT NULL,
  path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) DEFAULT NULL,
  size BIGINT DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_storage_user (user_id), KEY idx_storage_path (path(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
