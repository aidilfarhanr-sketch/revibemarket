

CREATE TABLE IF NOT EXISTS cron_locks (
  name VARCHAR(120) NOT NULL PRIMARY KEY,
  owner VARCHAR(190) NULL,
  locked_until DATETIME NULL,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS queue_failed_jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id VARCHAR(80) NULL,
  type VARCHAR(80) NULL,
  payload_json LONGTEXT NULL,
  attempts INT NOT NULL DEFAULT 0,
  error_message TEXT NULL,
  failed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_failed_jobs_type_failed_at (type, failed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS deployment_releases (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  release_version VARCHAR(80) NOT NULL,
  git_sha VARCHAR(80) NULL,
  artifact_name VARCHAR(190) NULL,
  deployed_by VARCHAR(120) NULL,
  status ENUM('deployed','rolled_back','failed') NOT NULL DEFAULT 'deployed',
  deployed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  rolled_back_at DATETIME NULL,
  UNIQUE KEY uq_release_version (release_version),
  KEY idx_release_status_date (status, deployed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS backup_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  backup_type VARCHAR(40) NOT NULL,
  status ENUM('running','success','failed') NOT NULL DEFAULT 'running',
  artifact_path VARCHAR(255) NULL,
  offsite_driver VARCHAR(40) NULL,
  size_bytes BIGINT UNSIGNED NULL,
  error_message TEXT NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME NULL,
  KEY idx_backup_status_started (status, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS readiness_audits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  app_env VARCHAR(40) NOT NULL,
  multi_server TINYINT(1) NOT NULL DEFAULT 0,
  status_code INT NOT NULL,
  checks_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_readiness_env_date (app_env, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
