SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS app_sessions;
DROP TABLE IF EXISTS admin_logs;
DROP TABLE IF EXISTS notification_queue;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS payment_status_history;
DROP TABLE IF EXISTS order_status_history;
DROP TABLE IF EXISTS seller_coin_ledger;
DROP TABLE IF EXISTS coin_transactions;
DROP TABLE IF EXISTS coins;
DROP TABLE IF EXISTS platform_commissions;
DROP TABLE IF EXISTS seller_balance_transactions;
DROP TABLE IF EXISTS seller_withdrawals;
DROP TABLE IF EXISTS seller_balances;
DROP TABLE IF EXISTS withdrawals;
DROP TABLE IF EXISTS complaints;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS shipments;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart;
DROP TABLE IF EXISTS carts;
DROP TABLE IF EXISTS wishlist;
DROP TABLE IF EXISTS wishlists;
DROP TABLE IF EXISTS chat_messages;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS sellers;
DROP TABLE IF EXISTS verification_codes;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS roles;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE roles (
  id INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(50) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_roles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE users (
  id INT NOT NULL AUTO_INCREMENT,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  birthdate DATE DEFAULT NULL,
  password VARCHAR(255) NOT NULL,
  address TEXT DEFAULT NULL,
  profile_photo VARCHAR(255) DEFAULT NULL,
  latitude DECIMAL(10,7) DEFAULT NULL,
  longitude DECIMAL(10,7) DEFAULT NULL,
  city VARCHAR(100) DEFAULT NULL,
  address_region TEXT DEFAULT NULL,
  street_address TEXT DEFAULT NULL,
  address_detail VARCHAR(255) DEFAULT NULL,
  address_label VARCHAR(50) DEFAULT 'Rumah',
  is_main_address TINYINT(1) DEFAULT 1,
  is_store_address TINYINT(1) DEFAULT 1,
  is_return_address TINYINT(1) DEFAULT 0,
  bio TEXT DEFAULT NULL,
  coins INT DEFAULT 0,
  role ENUM('user','admin') DEFAULT 'user',
  email_verified TINYINT(1) DEFAULT 1,
  phone_verified TINYINT(1) DEFAULT 1,
  email_verified_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  phone_verified_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  notify_email_enabled TINYINT(1) DEFAULT 0,
  notify_whatsapp_enabled TINYINT(1) DEFAULT 0,
  account_status VARCHAR(40) DEFAULT 'active',
  status ENUM('active','blocked') DEFAULT 'active',
  reset_token VARCHAR(255) DEFAULT NULL,
  reset_expires DATETIME DEFAULT NULL,
  last_login DATETIME DEFAULT NULL,
  login_attempts INT DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE verification_codes (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  channel VARCHAR(20) NOT NULL,
  code VARCHAR(20) NOT NULL,
  purpose VARCHAR(40) DEFAULT 'register',
  expires_at DATETIME NOT NULL,
  attempts INT DEFAULT 0,
  used_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_verification_user (user_id, channel, purpose)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE sellers (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  store_name VARCHAR(150) NOT NULL,
  store_description TEXT DEFAULT NULL,
  verification_status ENUM('pending','verified','rejected') DEFAULT 'verified',
  rating DECIMAL(3,2) DEFAULT 0.00,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_sellers_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE categories (
  id INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE products (
  id INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  category VARCHAR(80) NOT NULL,
  condition_status VARCHAR(80) DEFAULT 'Baik',
  purchase_year INT DEFAULT NULL,
  price INT NOT NULL,
  description TEXT DEFAULT NULL,
  reason_sell TEXT DEFAULT NULL,
  completeness VARCHAR(255) DEFAULT NULL,
  shipping_option VARCHAR(30) DEFAULT 'shipping',
  product_status VARCHAR(40) DEFAULT 'approved',
  is_active TINYINT(1) DEFAULT 1,
  badges VARCHAR(255) DEFAULT 'Eco Choice',
  minus_photo VARCHAR(255) DEFAULT NULL,
  verified_at DATETIME DEFAULT NULL,
  location VARCHAR(150) DEFAULT NULL,
  stock INT DEFAULT 0,
  weight_gram INT NOT NULL DEFAULT 1000,
  rating FLOAT DEFAULT 0,
  review_count INT DEFAULT 0,
  sold INT DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_id INT DEFAULT NULL,
  seller_latitude DECIMAL(10,7) DEFAULT NULL,
  seller_longitude DECIMAL(10,7) DEFAULT NULL,
  seller_address_snapshot TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_products_user (user_id),
  KEY idx_products_status (product_status),
  KEY idx_products_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE product_images (
  id INT NOT NULL AUTO_INCREMENT,
  product_id INT DEFAULT NULL,
  image VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_product_images_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE chat_messages (
  id INT NOT NULL AUTO_INCREMENT,
  sender_id INT NOT NULL,
  receiver_id INT NOT NULL,
  product_id INT DEFAULT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_chat_pair (sender_id, receiver_id),
  KEY idx_chat_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE cart (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT DEFAULT NULL,
  product_id INT DEFAULT NULL,
  qty INT DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_cart_user_product (user_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE carts (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT DEFAULT NULL,
  product_id INT DEFAULT NULL,
  qty INT DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE wishlist (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  product_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_wishlist_user_product (user_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE wishlists (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  product_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE orders (
  id INT NOT NULL AUTO_INCREMENT,
  order_code VARCHAR(50) DEFAULT NULL,
  buyer_id INT DEFAULT NULL,
  seller_id INT NOT NULL,
  product_id INT NOT NULL,
  qty INT DEFAULT 1,
  total_price INT NOT NULL,
  shipping_cost INT DEFAULT 0,
  service_fee INT DEFAULT 0,
  seller_cashback_amount INT DEFAULT 0,
  platform_margin_amount INT DEFAULT 0,
  discount_amount INT DEFAULT 0,
  status VARCHAR(60) DEFAULT 'pending_payment',
  payment_status VARCHAR(60) DEFAULT 'waiting_upload',
  shipping_address TEXT DEFAULT NULL,
  courier VARCHAR(80) DEFAULT NULL,
  payment_method VARCHAR(80) DEFAULT 'transfer_bank',
  notes TEXT DEFAULT NULL,
  buyer_latitude DECIMAL(10,7) DEFAULT NULL,
  buyer_longitude DECIMAL(10,7) DEFAULT NULL,
  seller_latitude DECIMAL(10,7) DEFAULT NULL,
  seller_longitude DECIMAL(10,7) DEFAULT NULL,
  distance_km DECIMAL(10,2) DEFAULT NULL,
  delivery_estimate VARCHAR(80) DEFAULT NULL,
  tracking_number VARCHAR(120) DEFAULT NULL,
  paid_at DATETIME DEFAULT NULL,
  completed_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_orders_buyer (buyer_id),
  KEY idx_orders_seller (seller_id),
  KEY idx_orders_product (product_id),
  KEY idx_orders_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE order_items (
  id INT NOT NULL AUTO_INCREMENT,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  seller_id INT NOT NULL,
  product_name VARCHAR(255) NOT NULL,
  price INT NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  subtotal INT NOT NULL,
  PRIMARY KEY (id),
  KEY idx_order_items_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE shipments (
  id INT NOT NULL AUTO_INCREMENT,
  order_id INT NOT NULL,
  courier VARCHAR(80) DEFAULT NULL,
  tracking_number VARCHAR(100) DEFAULT NULL,
  shipping_address TEXT DEFAULT NULL,
  shipping_cost INT DEFAULT 0,
  distance_km DECIMAL(10,2) DEFAULT NULL,
  delivery_estimate VARCHAR(80) DEFAULT NULL,
  status VARCHAR(40) DEFAULT 'waiting_payment',
  shipped_at DATETIME DEFAULT NULL,
  delivered_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_shipments_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE payments (
  id INT NOT NULL AUTO_INCREMENT,
  order_id INT NOT NULL,
  user_id INT NOT NULL,
  method VARCHAR(80) DEFAULT 'transfer_bank',
  amount INT NOT NULL DEFAULT 0,
  gateway VARCHAR(60) DEFAULT 'manual_demo',
  gateway_reference VARCHAR(120) DEFAULT NULL,
  payment_url TEXT DEFAULT NULL,
  snap_token TEXT DEFAULT NULL,
  gateway_payload LONGTEXT DEFAULT NULL,
  expired_at DATETIME DEFAULT NULL,
  proof_file VARCHAR(255) DEFAULT NULL,
  status VARCHAR(60) DEFAULT 'waiting_upload',
  paid_at DATETIME DEFAULT NULL,
  verified_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_payments_order (order_id),
  KEY idx_payments_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE invoices (
  id INT NOT NULL AUTO_INCREMENT,
  invoice_number VARCHAR(60) NOT NULL,
  order_id INT NOT NULL,
  user_id INT NOT NULL,
  buyer_id INT DEFAULT NULL,
  seller_id INT DEFAULT NULL,
  subtotal INT DEFAULT 0,
  shipping_cost INT DEFAULT 0,
  service_fee INT DEFAULT 0,
  discount_amount INT DEFAULT 0,
  total_amount INT DEFAULT 0,
  total INT DEFAULT 0,
  status VARCHAR(40) DEFAULT 'unpaid',
  due_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_invoice_number (invoice_number),
  KEY idx_invoices_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE reviews (
  id INT NOT NULL AUTO_INCREMENT,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  user_id INT NOT NULL,
  seller_id INT NOT NULL,
  rating TINYINT(1) NOT NULL DEFAULT 5,
  comment TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_reviews_order (order_id),
  KEY idx_reviews_product (product_id),
  KEY idx_reviews_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE complaints (
  id INT NOT NULL AUTO_INCREMENT,
  order_id INT NOT NULL,
  buyer_id INT NOT NULL,
  seller_id INT NOT NULL,
  reason VARCHAR(150) NOT NULL,
  detail TEXT DEFAULT NULL,
  evidence_file VARCHAR(255) DEFAULT NULL,
  status VARCHAR(40) DEFAULT 'open',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_complaints_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE withdrawals (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  amount INT NOT NULL,
  method VARCHAR(80) NOT NULL,
  account_number VARCHAR(100) NOT NULL,
  account_name VARCHAR(150) NOT NULL,
  transfer_reference VARCHAR(150) DEFAULT NULL,
  admin_note TEXT DEFAULT NULL,
  status VARCHAR(40) DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME DEFAULT NULL,
  processed_by INT DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_withdrawals_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE seller_balances (
  id INT NOT NULL AUTO_INCREMENT,
  seller_id INT DEFAULT NULL,
  user_id INT NOT NULL,
  pending_balance BIGINT NOT NULL DEFAULT 0,
  available_balance BIGINT NOT NULL DEFAULT 0,
  withdrawn_balance BIGINT NOT NULL DEFAULT 0,
  total_earned BIGINT NOT NULL DEFAULT 0,
  balance BIGINT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_seller_balances_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE seller_balance_transactions (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  type VARCHAR(60) NOT NULL,
  amount BIGINT NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  reference_type VARCHAR(50) DEFAULT NULL,
  reference_id INT DEFAULT NULL,
  status VARCHAR(40) DEFAULT 'success',
  balance_type VARCHAR(40) DEFAULT 'available',
  idempotency_key VARCHAR(120) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_seller_balance_idem (idempotency_key),
  KEY idx_seller_balance_user (user_id, status, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE seller_withdrawals (
  id INT NOT NULL AUTO_INCREMENT,
  withdrawal_code VARCHAR(40) NOT NULL,
  user_id INT NOT NULL,
  amount BIGINT NOT NULL,
  method VARCHAR(80) NOT NULL,
  account_number VARCHAR(120) NOT NULL,
  account_name VARCHAR(150) NOT NULL,
  transfer_reference VARCHAR(150) DEFAULT NULL,
  admin_note TEXT DEFAULT NULL,
  status VARCHAR(40) DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME DEFAULT NULL,
  processed_by INT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_seller_withdrawal_code (withdrawal_code),
  KEY idx_seller_withdrawal_user (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE platform_commissions (
  id INT NOT NULL AUTO_INCREMENT,
  order_id INT NOT NULL,
  seller_id INT NOT NULL,
  gross_amount BIGINT NOT NULL DEFAULT 0,
  commission_amount BIGINT NOT NULL DEFAULT 0,
  net_amount BIGINT NOT NULL DEFAULT 0,
  status VARCHAR(40) DEFAULT 'success',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_platform_commission_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE coins (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  balance INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_coins_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE coin_transactions (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  type VARCHAR(60) NOT NULL,
  amount INT NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  reference_type VARCHAR(50) DEFAULT NULL,
  reference_id INT DEFAULT NULL,
  status VARCHAR(40) DEFAULT 'success',
  idempotency_key VARCHAR(120) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_coin_tx_idem (idempotency_key),
  KEY idx_coin_tx_user (user_id, status, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE seller_coin_ledger (
  id INT NOT NULL AUTO_INCREMENT,
  seller_id INT NOT NULL,
  order_id INT DEFAULT NULL,
  type VARCHAR(80) NOT NULL,
  amount INT NOT NULL,
  idempotency_key VARCHAR(120) DEFAULT NULL,
  description VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_seller_coin_idem (idempotency_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE order_status_history (
  id INT NOT NULL AUTO_INCREMENT,
  order_id INT NOT NULL,
  old_status VARCHAR(60) DEFAULT NULL,
  new_status VARCHAR(60) NOT NULL,
  source VARCHAR(60) DEFAULT 'manual',
  changed_by INT DEFAULT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_order_status_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE payment_status_history (
  id INT NOT NULL AUTO_INCREMENT,
  payment_id INT DEFAULT NULL,
  order_id INT DEFAULT NULL,
  old_status VARCHAR(60) DEFAULT NULL,
  new_status VARCHAR(60) NOT NULL,
  source VARCHAR(60) DEFAULT 'manual',
  changed_by INT DEFAULT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_payment_status_payment (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE notifications (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  title VARCHAR(160) NOT NULL,
  message TEXT DEFAULT NULL,
  type VARCHAR(60) DEFAULT 'info',
  link VARCHAR(255) DEFAULT NULL,
  is_read TINYINT(1) DEFAULT 0,
  payload TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_user (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE notification_queue (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT DEFAULT NULL,
  channel VARCHAR(30) NOT NULL DEFAULT 'in_app',
  type VARCHAR(80) NOT NULL DEFAULT 'general',
  title VARCHAR(160) NOT NULL,
  message TEXT DEFAULT NULL,
  destination VARCHAR(255) DEFAULT NULL,
  payload_json TEXT DEFAULT NULL,
  status VARCHAR(40) DEFAULT 'pending',
  retry_count INT DEFAULT 0,
  scheduled_at DATETIME DEFAULT NULL,
  sent_at DATETIME DEFAULT NULL,
  last_error TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notification_queue_status (status, scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE admin_logs (
  id INT NOT NULL AUTO_INCREMENT,
  admin_id INT DEFAULT NULL,
  action VARCHAR(120) NOT NULL,
  target_type VARCHAR(80) DEFAULT NULL,
  target_id INT DEFAULT NULL,
  detail TEXT DEFAULT NULL,
  ip_address VARCHAR(80) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_logs_admin (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE app_sessions (
  id VARCHAR(128) NOT NULL,
  user_id INT DEFAULT NULL,
  payload MEDIUMTEXT DEFAULT NULL,
  ip_address VARCHAR(80) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  last_activity INT DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_app_sessions_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO roles (id, name, description) VALUES
(1,'admin','Admin demo ReVibe'),
(2,'user','Buyer dan seller demo');

INSERT INTO users (id, first_name, last_name, email, phone, password, address, latitude, longitude, city, role, email_verified, phone_verified, status, coins) VALUES
(1,'Admin','ReVibe','admin@revibe.local','080000000001','$2y$12$YE1gg61qyAFXwnTrdZAdl.DyBdfBqaikVjxsE2Yyrt3D.7hJNBSnm','Alamat demo admin ReVibe',-6.2000000,106.8166660,'Jakarta','admin',1,1,'active',0),
(2,'Buyer','Demo','buyer@revibe.local','080000000002','$2y$12$h94WHU.pddxTA1BTTVpzzeIbWP9tyWowTr8LO79I.OFfaUp/MTKJu','Alamat demo buyer ReVibe',-6.2100000,106.8200000,'Jakarta','user',1,1,'active',0),
(3,'Seller','Demo','seller@revibe.local','080000000003','$2y$12$TbyaPpVIJmVKsjtyO9muwuDLL/ChDttO8n2vOAnxjc9G0X5kUmJ9C','Alamat demo seller ReVibe',-6.1900000,106.8100000,'Jakarta','user',1,1,'active',0);

INSERT INTO sellers (id, user_id, store_name, store_description, verification_status, rating) VALUES
(1,3,'Seller Demo ReVibe','Toko demo untuk alur buyer admin seller.','verified',0.00);

INSERT INTO categories (id, name, slug, description) VALUES
(1,'Fashion','fashion','Produk fashion preloved'),
(2,'Elektronik','elektronik','Elektronik bekas layak pakai'),
(3,'Rumah Tangga','rumah-tangga','Barang rumah tangga upcycle'),
(4,'Daur Ulang Kreatif','daur-ulang-kreatif','Produk kreatif hasil daur ulang');

INSERT INTO coins (user_id, balance) VALUES
(1,0),(2,0),(3,0);

INSERT INTO seller_balances (seller_id, user_id, pending_balance, available_balance, withdrawn_balance, total_earned, balance) VALUES
(3,3,0,0,0,0,0);
