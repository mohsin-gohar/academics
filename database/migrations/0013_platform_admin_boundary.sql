-- 0013_platform_admin_boundary.sql
-- A school-less "admin" is not a platform operator. The historical localhost
-- demo account used that shape. Promote only that known account to super_admin
-- and force a password change. Do not reactivate accounts migration 0011
-- already disabled, and do not touch any other admin.

UPDATE users
SET role = 'super_admin',
    must_change_password = 1
WHERE email = 'admin@edusphere.com'
  AND role = 'admin'
  AND school_id IS NULL
  AND is_active = 1
  AND password = '$2y$10$mmx/UMwzNmkfhEKRGUYctuNSrl/xrRLp4f8o3R5M1ksN1c2J45tQu';
