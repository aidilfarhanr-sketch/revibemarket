

ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified_at DATETIME DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_token VARCHAR(128) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_seen_ip VARCHAR(80) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_seen_user_agent VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD UNIQUE INDEX IF NOT EXISTS uniq_users_email (email);
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_users_role_status (role, status);

ALTER TABLE payments ADD UNIQUE INDEX IF NOT EXISTS uniq_payments_order (order_id);
ALTER TABLE payments ADD INDEX IF NOT EXISTS idx_payments_status_created (status, created_at);
ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_orders_buyer_created (buyer_id, created_at);
ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_orders_seller_status (seller_id, status);
ALTER TABLE products ADD INDEX IF NOT EXISTS idx_products_user_status_created (user_id, product_status, created_at);
ALTER TABLE products ADD INDEX IF NOT EXISTS idx_products_category_status (category, product_status);
ALTER TABLE chat_messages ADD INDEX IF NOT EXISTS idx_chat_participants_created (sender_id, receiver_id, created_at);
ALTER TABLE withdrawals ADD INDEX IF NOT EXISTS idx_withdrawals_user_status (user_id, status);
ALTER TABLE seller_withdrawals ADD INDEX IF NOT EXISTS idx_seller_withdrawal_status_created (status, created_at);
