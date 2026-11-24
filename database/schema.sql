-- Loyalty Reward System Database
-- Fresh database setup for MySQL / phpMyAdmin.
-- WARNING: This script drops the existing loyalty_db database and all its data.



CREATE DATABASE rewards_data
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE rewards_data;

CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE products (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(150) NOT NULL,
  coins_per_unit INT UNSIGNED NOT NULL,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE batches (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id  INT UNSIGNED NOT NULL,
  quantity    INT UNSIGNED NOT NULL,
  created_by  INT UNSIGNED NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_batches_product
    FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_batches_user
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE codes (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code_uid     VARCHAR(64) NOT NULL UNIQUE,
  batch_id     INT UNSIGNED NOT NULL,
  product_id   INT UNSIGNED NOT NULL,
  coins_value  INT UNSIGNED NOT NULL,
  status       ENUM('unused','used','blocked') NOT NULL DEFAULT 'unused',
  redeemed_by  INT UNSIGNED NULL,
  redeemed_at  TIMESTAMP NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_codes_status (status),
  INDEX idx_codes_batch (batch_id),
  CONSTRAINT fk_codes_batch
    FOREIGN KEY (batch_id) REFERENCES batches(id),
  CONSTRAINT fk_codes_product
    FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_codes_redeemed_by
    FOREIGN KEY (redeemed_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE transactions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  type        ENUM('earn','redeem','adjust') NOT NULL,
  coins       INT NOT NULL,
  code_id     INT UNSIGNED NULL UNIQUE,
  note        VARCHAR(255) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tx_user (user_id),
  CONSTRAINT fk_transactions_user
    FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_transactions_code
    FOREIGN KEY (code_id) REFERENCES codes(id)
) ENGINE=InnoDB;

CREATE TABLE settings (
  setting_key   VARCHAR(50) PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE scan_attempts (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NULL,
  code_uid_tried VARCHAR(100) NOT NULL,
  result         ENUM('success','invalid','already_used','blocked') NOT NULL,
  ip_address     VARCHAR(45) NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_scan_user (user_id),
  INDEX idx_scan_time (created_at),
  CONSTRAINT fk_scan_attempts_user
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value)
VALUES ('coin_to_rupee_rate', '1.00');
