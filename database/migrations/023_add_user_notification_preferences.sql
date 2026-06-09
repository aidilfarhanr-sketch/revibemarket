
CREATE TABLE IF NOT EXISTS user_notification_preferences (
  id BIGINT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  notify_email_enabled TINYINT(1) DEFAULT 1,
  notify_whatsapp_enabled TINYINT(1) DEFAULT 1,
  notify_order_updates TINYINT(1) DEFAULT 1,
  notify_payment_updates TINYINT(1) DEFAULT 1,
  notify_shipping_updates TINYINT(1) DEFAULT 1,
  notify_promo_updates TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uniq_user_notification_preferences (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE users ADD COLUMN IF NOT EXISTS notify_email_enabled TINYINT(1) DEFAULT 1;
ALTER TABLE users ADD COLUMN IF NOT EXISTS notify_whatsapp_enabled TINYINT(1) DEFAULT 1;
INSERT IGNORE INTO user_notification_preferences (user_id) SELECT id FROM users;
