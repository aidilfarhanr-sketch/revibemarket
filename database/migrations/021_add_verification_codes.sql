
CREATE TABLE IF NOT EXISTS verification_codes (
  id BIGINT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  channel ENUM('email','whatsapp') NOT NULL,
  destination_hash CHAR(64) NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  purpose ENUM('register','login','reset_password','admin_2fa') DEFAULT 'register',
  attempts INT NOT NULL DEFAULT 0,
  max_attempts INT NOT NULL DEFAULT 5,
  expires_at DATETIME NOT NULL,
  verified_at DATETIME DEFAULT NULL,
  resend_count INT NOT NULL DEFAULT 0,
  last_sent_at DATETIME DEFAULT NULL,
  ip_address VARCHAR(80) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_verification_user (user_id), KEY idx_verification_channel (channel), KEY idx_verification_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(30) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified_at DATETIME DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone_verified_at DATETIME DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS account_status ENUM('unverified','active','suspended','banned') DEFAULT 'unverified';
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_verification_sent_at DATETIME DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS notify_email_enabled TINYINT(1) DEFAULT 1;
ALTER TABLE users ADD COLUMN IF NOT EXISTS notify_whatsapp_enabled TINYINT(1) DEFAULT 1;
UPDATE users SET account_status='active' WHERE (email_verified=1 OR email_verified_at IS NOT NULL) AND account_status='unverified';
