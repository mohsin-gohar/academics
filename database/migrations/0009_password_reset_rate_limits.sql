-- 0009_password_reset_rate_limits.sql
-- Persist rate limits independently of account existence so reset requests
-- cannot reveal whether an email address belongs to a registered user.

CREATE TABLE IF NOT EXISTS password_reset_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email_hash CHAR(64) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pra_email_time (email_hash, requested_at),
    KEY idx_pra_ip_time (ip_address, requested_at),
    KEY idx_pra_requested_at (requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
