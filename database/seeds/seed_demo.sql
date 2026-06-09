
INSERT IGNORE INTO roles (id, name, description) VALUES
(1, 'admin', 'Administrator ReVibe'),
(2, 'user', 'Member marketplace ReVibe');

INSERT IGNORE INTO categories (id, name, slug, description) VALUES
(1, 'Pakaian', 'pakaian', 'Fashion dan pakaian preloved'),
(2, 'Aksesoris', 'aksesoris', 'Tas, sepatu, dan aksesoris'),
(3, 'Pajangan', 'pajangan', 'Dekorasi rumah dan barang estetik'),
(4, 'Tanaman', 'tanaman', 'Tanaman dan produk eco-friendly');

INSERT IGNORE INTO users (id, first_name, last_name, email, password, role, status, email_verified_at)
VALUES (1, 'Admin', 'ReVibe', 'admin@revibe.local', '$2y$10$futpaLCRqclv8vI9HHzrwu37lFhETUdjzsMXcH9t1u4ffZMOl2jJW', 'admin', 'active', NOW());

