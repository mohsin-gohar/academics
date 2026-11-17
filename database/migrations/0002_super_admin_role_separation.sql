-- Academics - Migration 0002: Super Admin Role Separation (C2)
-- Converts legacy platform admin (role='admin' + school_id=NULL) to explicit super_admin role.
-- Ensures isSuperAdmin() recognizes only role='super_admin'.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Migrate legacy platform administrators to explicit super_admin role.
-- Previously, role='admin' with school_id=NULL was treated as super admin.
-- Now only role='super_admin' grants platform privileges.
UPDATE `users`
SET `role` = 'super_admin'
WHERE `role` = 'admin' AND `school_id` IS NULL;

-- Also migrate legacy super_admin users who may have been stored as role='admin'
-- with no school scope from the demo account self-heal in config/db.php.
UPDATE `users`
SET `role` = 'super_admin'
WHERE `email` = 'admin@edusphere.com' AND `role` = 'admin' AND `school_id` IS NULL;

SET FOREIGN_KEY_CHECKS = 1;