-- 0013_legacy_school_subscription_backfill.sql
-- Provision a one-year migration grace subscription for existing tenants that
-- predate the SaaS billing tables. This is idempotent and never rewrites an
-- existing subscription or payment history.

INSERT INTO school_subscriptions
    (school_id, plan, price, billing_cycle, start_date, end_date, status, payment_method, transaction_id)
SELECT
    s.id,
    CASE
        WHEN LOWER(COALESCE(s.subscription_plan, 'basic')) = 'premium' THEN 'enterprise'
        WHEN LOWER(COALESCE(s.subscription_plan, 'basic')) = 'pro' THEN 'pro'
        ELSE 'basic'
    END,
    CASE
        WHEN LOWER(COALESCE(s.subscription_plan, 'basic')) = 'premium' THEN 500.00
        WHEN LOWER(COALESCE(s.subscription_plan, 'basic')) = 'pro' THEN 150.00
        ELSE 50.00
    END,
    'yearly',
    CURDATE(),
    DATE_ADD(CURDATE(), INTERVAL 1 YEAR),
    CASE WHEN COALESCE(s.is_active, 1) = 1 THEN 'active' ELSE 'cancelled' END,
    'migration',
    CONCAT('MIGRATED-', COALESCE(s.school_code, s.id))
FROM schools s
LEFT JOIN school_subscriptions existing ON existing.school_id = s.id
WHERE existing.id IS NULL;
