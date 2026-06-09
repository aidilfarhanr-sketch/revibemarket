
CREATE TABLE IF NOT EXISTS invoices (
  id BIGINT NOT NULL AUTO_INCREMENT,
  invoice_number VARCHAR(80) NOT NULL,
  order_id INT NOT NULL,
  user_id INT NOT NULL,
  subtotal BIGINT NOT NULL DEFAULT 0,
  shipping_cost BIGINT NOT NULL DEFAULT 0,
  service_fee BIGINT NOT NULL DEFAULT 0,
  discount_amount BIGINT NOT NULL DEFAULT 0,
  total BIGINT NOT NULL DEFAULT 0,
  status VARCHAR(60) DEFAULT 'unpaid',
  due_at DATETIME DEFAULT NULL,
  paid_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uniq_invoices_number (invoice_number), KEY idx_invoices_order (order_id), KEY idx_invoices_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS shipping_addresses (
  id BIGINT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  recipient_name VARCHAR(160) NOT NULL,
  phone VARCHAR(40) DEFAULT NULL,
  province VARCHAR(100) DEFAULT NULL,
  city VARCHAR(100) DEFAULT NULL,
  district VARCHAR(100) DEFAULT NULL,
  postal_code VARCHAR(20) DEFAULT NULL,
  full_address TEXT NOT NULL,
  latitude DECIMAL(10,7) DEFAULT NULL,
  longitude DECIMAL(10,7) DEFAULT NULL,
  note VARCHAR(255) DEFAULT NULL,
  is_default TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_shipping_addresses_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS vouchers (
  id BIGINT NOT NULL AUTO_INCREMENT,
  code VARCHAR(80) NOT NULL,
  discount_type ENUM('fixed','percentage') DEFAULT 'fixed',
  discount_value BIGINT NOT NULL DEFAULT 0,
  min_order_amount BIGINT NOT NULL DEFAULT 0,
  max_discount BIGINT DEFAULT NULL,
  quota INT DEFAULT NULL,
  used_count INT NOT NULL DEFAULT 0,
  starts_at DATETIME DEFAULT NULL,
  expires_at DATETIME DEFAULT NULL,
  is_active TINYINT(1) DEFAULT 1,
  PRIMARY KEY (id), UNIQUE KEY uniq_voucher_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS voucher_usages (
  id BIGINT NOT NULL AUTO_INCREMENT,
  voucher_id BIGINT NOT NULL,
  user_id INT NOT NULL,
  order_id INT NOT NULL,
  discount_amount BIGINT NOT NULL DEFAULT 0,
  used_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uniq_voucher_order (voucher_id, order_id), KEY idx_voucher_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
