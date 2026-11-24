USE loyalty_db;

CREATE TABLE payout_requests (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  coins           INT UNSIGNED NOT NULL,
  rate_at_request DECIMAL(10,2) NOT NULL,
  rupees          DECIMAL(12,2) NOT NULL,
  payout_details  VARCHAR(255) NOT NULL,          -- customer's UPI ID
  status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by     INT UNSIGNED NULL,
  reviewed_at     TIMESTAMP NULL,
  admin_note      VARCHAR(255) NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)     REFERENCES users(id),
  FOREIGN KEY (reviewed_by) REFERENCES users(id),
  INDEX idx_payout_status (status),
  INDEX idx_payout_user (user_id)
) ENGINE=InnoDB;

-- Link ledger rows to a request; one 'redeem' row and at most one refund row per request
ALTER TABLE transactions
  ADD COLUMN payout_request_id INT UNSIGNED NULL AFTER code_id,
  ADD CONSTRAINT fk_tx_payout FOREIGN KEY (payout_request_id) REFERENCES payout_requests(id),
  ADD UNIQUE KEY uq_tx_request_type (payout_request_id, type);

-- Owner-editable minimum cash-out
INSERT INTO settings (setting_key, setting_value) VALUES ('min_payout_coins', '100')
  ON DUPLICATE KEY UPDATE setting_value = setting_value;