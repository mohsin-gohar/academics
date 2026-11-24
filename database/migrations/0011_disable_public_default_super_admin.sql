-- 0011_disable_public_default_super_admin.sql
-- Retire the legacy bootstrap account only while its publicly documented
-- default password is still present. Preserve the user row so historical
-- courses, expenses, invites and audit references are not cascaded away.
UPDATE users
SET is_active = 0,
    must_change_password = 1,
    password = SHA2(CONCAT(UUID(), RAND(), NOW()), 256),
    updated_at = NOW()
WHERE email = 'superadmin@academics.app'
  AND role = 'super_admin'
  AND password = '$2y$10$mmx/UMwzNmkfhEKRGUYctuNSrl/xrRLp4f8o3R5M1ksN1c2J45tQu';
