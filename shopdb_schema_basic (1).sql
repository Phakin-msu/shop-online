
USE shopdb;


CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL UNIQUE,
  password      VARCHAR(255) NOT NULL,
  name          VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  phone         VARCHAR(32) NULL,
  role          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  status        TINYINT NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*INSERT INTO users (email, password, name, phone, role) VALUES
('admin@example.com', '$2y$12$0.oY7p62G7IvxdD3TzJ/2.xGQ9pciLkESVzWd6ZGRHga/j1SqRJDq', 'ผู้ดูแลระบบ', '0812345678', 'admin'),
('user@example.com',  '$2y$12$0.oY7p62G7IvxdD3TzJ/2.xGQ9pciLkESVzWd6ZGRHga/j1SqRJDq', 'ลูกค้าเดโม่',  '0890001111', 'customer');*/

-- CATEGORIES
CREATE TABLE IF NOT EXISTS categories (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL UNIQUE,
  slug       VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL UNIQUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- PRODUCTS
CREATE TABLE IF NOT EXISTS products (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  sku         VARCHAR(64) NOT NULL UNIQUE,
  name        VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  description TEXT NULL,
  price       DECIMAL(10,2) NOT NULL,
  image_url   VARCHAR(255) NULL,
  stock       INT NOT NULL DEFAULT 0,
  category_id INT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE INDEX idx_products_name ON products (name);
CREATE INDEX idx_products_price ON products (price);

/*INSERT INTO products (sku, name, description, price, image_url, stock) VALUES
('SKU-WATCH-001', 'นาฬิกาสุดหรู', 'นาฬิกาคุณภาพสูง วัสดุสแตนเลส', 12500.00, 'img/h.png', 10),
('SKU-GLASS-001', 'แว่นตาแฟชั่น', 'แว่นตาทรงสวย กรอบไทเทเนียม',  3200.00,  'img/q.png',  20),
('SKU-BAG-001',   'กระเป๋าหนังแท้', 'กระเป๋าหนังแท้คุณภาพพรีเมียม', 6900.00,  'img/a.png',  15),
('SKU-SHOE-001',  'รองเท้าผ้าใบ', 'รองเท้าผ้าใบใส่สบาย',          4500.00,  'img/b.png',  25),
('SKU-HP-001',    'หูฟังไร้สาย', 'หูฟังไร้สาย เสียงใสคมชัด',        2590.00,  'img/headphone.png', 30);*/

-- ORDERS
CREATE TABLE IF NOT EXISTS orders (
  id                 BIGINT AUTO_INCREMENT PRIMARY KEY,
  order_code         VARCHAR(32) NOT NULL UNIQUE,
  user_id            INT NULL,
  status             ENUM('awaiting_payment','paid','processing','shipped','completed','cancelled','refunded')
                     NOT NULL DEFAULT 'awaiting_payment',
  item_count         INT NOT NULL,
  subtotal           DECIMAL(10,2) NOT NULL,
  shipping_fee       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  discount_amount    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total_amount       DECIMAL(10,2) NOT NULL,
  payment_method     ENUM('promptpay','transfer','cod','none') NOT NULL DEFAULT 'promptpay',
  promptpay_ref      VARCHAR(64) NULL,
  slip_url           VARCHAR(255) NULL,
  shipping_name      VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  shipping_phone     VARCHAR(32) NOT NULL,
  shipping_address1  VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  shipping_subdistrict VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  shipping_district    VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  shipping_province    VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  shipping_zipcode     VARCHAR(10)  NULL,
  user_note          VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  paid_at            DATETIME NULL,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE INDEX idx_orders_user ON orders (user_id);
CREATE INDEX idx_orders_status ON orders (status);
CREATE INDEX idx_orders_created ON orders (created_at);

-- ORDER ITEMS 
CREATE TABLE IF NOT EXISTS order_items (
  id         BIGINT AUTO_INCREMENT PRIMARY KEY,
  order_id   BIGINT NOT NULL,
  product_id INT NULL,
  sku        VARCHAR(64) NULL,
  name       VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  price      DECIMAL(10,2) NOT NULL,
  qty        INT NOT NULL,
  line_total DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_items_order   FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE INDEX idx_items_order ON order_items (order_id);
CREATE INDEX idx_items_product ON order_items (product_id);

-- CARTS (optional)
CREATE TABLE IF NOT EXISTS carts (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NULL,
  session_id  VARCHAR(128) NULL,
  status      ENUM('active','converted','abandoned') NOT NULL DEFAULT 'active',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_cart_user_session (user_id, session_id),
  CONSTRAINT fk_carts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cart_items (
  id         BIGINT AUTO_INCREMENT PRIMARY KEY,
  cart_id    BIGINT NOT NULL,
  product_id INT NOT NULL,
  price      DECIMAL(10,2) NOT NULL,
  qty        INT NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_cart_product (cart_id, product_id),
  CONSTRAINT fk_cart_items_cart    FOREIGN KEY (cart_id)    REFERENCES carts(id)    ON DELETE CASCADE,
  CONSTRAINT fk_cart_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

