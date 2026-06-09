
CREATE TABLE IF NOT EXISTS notification_queue (
  id BIGINT NOT NULL AUTO_INCREMENT,
  user_id INT DEFAULT NULL,
  channel ENUM('email','whatsapp','in_app') NOT NULL DEFAULT 'in_app',
  type VARCHAR(80) NOT NULL,
  title VARCHAR(180) NOT NULL,
  message TEXT NOT NULL,
  destination VARCHAR(180) DEFAULT NULL,
  payload_json LONGTEXT DEFAULT NULL,
  status ENUM('pending','sent','failed','cancelled') DEFAULT 'pending',
  retry_count INT NOT NULL DEFAULT 0,
  last_error TEXT DEFAULT NULL,
  scheduled_at DATETIME DEFAULT NULL,
  sent_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_notification_user (user_id), KEY idx_notification_status (status), KEY idx_notification_channel (channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS notification_logs (
  id BIGINT NOT NULL AUTO_INCREMENT,
  notification_id BIGINT DEFAULT NULL,
  provider VARCHAR(80) DEFAULT NULL,
  provider_message_id VARCHAR(190) DEFAULT NULL,
  status VARCHAR(60) DEFAULT NULL,
  response_json LONGTEXT DEFAULT NULL,
  error_message TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_notification_logs_notification (notification_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
