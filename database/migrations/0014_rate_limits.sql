-- 0014_rate_limits.sql
-- Sliding-window counters for unauthenticated endpoints (registration,
-- contact form). Rows are pruned opportunistically by rate_limit_exceeded().

CREATE TABLE IF NOT EXISTS rate_limits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    bucket VARCHAR(40) NOT NULL,
    identifier CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rate_bucket_identifier (bucket, identifier, created_at),
    KEY idx_rate_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
