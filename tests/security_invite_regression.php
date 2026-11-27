<?php
/**
 * DB-free regression checks for invite secrecy and platform-role boundaries.
 * Run: php tests/security_invite_regression.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
startSecureSession();

$failed = 0;
$passed = 0;

function check(string $name, bool $ok): void
{
    global $failed, $passed;
    if ($ok) {
        $passed++;
        echo "[PASS] {$name}\n";
        return;
    }
    $failed++;
    echo "[FAIL] {$name}\n";
}

check('only super_admin is a platform operator', isPlatformOperator('super_admin', null) === true);
check('school admin is not a platform operator', isPlatformOperator('school_admin', 1) === false);
check('admin without a school is not a platform operator', isPlatformOperator('admin', null) === false);
check('teacher is not a platform operator', isPlatformOperator('teacher', 1) === false);

$sent = accountProvisionedMessage('student@school.test', true);
$unsent = accountProvisionedMessage('student@school.test', false);
check('setup message names the mailbox', str_contains($sent, 'student@school.test'));
check('setup message does not reveal a password', !preg_match('/password\s*[:=]/i', $sent) && !preg_match('/password\s*[:=]/i', $unsent));
check('failed delivery does not invent a password', str_contains($unsent, 'Forgot password') && !str_contains($unsent, 'Student@123'));

$root = dirname(__DIR__);
$sources = [
    'students/create.php',
    'teachers/create.php',
    'teachers/edit.php',
    'admin/create_user.php',
    'admin/edit_user.php',
    'includes/functions.php',
    'auth/login_process.php',
];
foreach ($sources as $relative) {
    $src = (string)file_get_contents($root . '/' . $relative);
    check($relative . ' does not flash a one-time password', !str_contains($src, 'One-time password'));
    check($relative . ' does not log reset links', !str_contains($src, 'reset-links.log'));
}

$login = (string)file_get_contents($root . '/auth/login_process.php');
check('login does not treat school-less admin as platform admin', !str_contains($login, "role'] === 'admin' && \$schoolId === null"));

$editUser = (string)file_get_contents($root . '/admin/edit_user.php');
check('editing account activation revokes existing sessions', str_contains($editUser, "(int)$lockedUser['is_active'] !== (int)$form_data['is_active']") && str_contains($editUser, 'session_version = session_version + 1'));

$toggleUser = (string)file_get_contents($root . '/admin/toggle_user.php');
check('user activation changes revoke all existing sessions', str_contains($toggleUser, 'session_version = session_version + 1'));

$functions = (string)file_get_contents($root . '/includes/functions.php');
check('live session checks trial expiry', str_contains($functions, 'trial_expired'));
check('live session checks session_version', str_contains($functions, 'session_revoked'));
check('live session is not cached for 60 seconds', !str_contains($functions, '60 seconds'));
check('live session fails closed when database validation is unavailable', str_contains($functions, "\$fail('session_validation_unavailable', \$isAjax)"));

check('today is an allowed attendance date', isOnOrBeforeToday(date('Y-m-d'), date('Y-m-d')));
check('tomorrow is not an allowed attendance or payment date', !isOnOrBeforeToday(date('Y-m-d', strtotime('+1 day')), date('Y-m-d')));
check('a past date is allowed', isOnOrBeforeToday('2020-01-15', '2026-11-27'));

foreach ([
    'tasks/create.php' => "FROM students WHERE status = 'Active' ORDER BY full_name",
    'tasks/index.php' => "FROM students WHERE status = 'Active' ORDER BY full_name",
    'students/index.php' => "FROM courses WHERE status = 'Active' ORDER BY course_name",
    'parent/fees.php' => 'SELECT school_id FROM students WHERE id = $cid',
    'parent/grades.php' => 'SELECT school_id FROM students WHERE id = $cid',
    'parent/attendance.php' => 'SELECT school_id FROM students WHERE id = $cid',
] as $relative => $forbidden) {
    $src = (string)file_get_contents($root . '/' . $relative);
    check($relative . ' does not use an unscoped list', !str_contains($src, $forbidden));
}

$seed = (string)file_get_contents($root . '/database/seed.sql');
check('seed aborts on production-like database names', str_contains($seed, "REGEXP 'prod|live|production'"));
check('seed no longer claims the broken edusphere_% guard', !str_contains($seed, "NOT LIKE 'edusphere_%'"));

$tenant = (string)file_get_contents($root . '/includes/tenant.php');
check('school scope never opens every tenant', !str_contains($tenant, "return ['1=1', []];"));
check('record access does not exempt platform admins', !str_contains($tenant, "if (isSuperAdmin()) return;"));

$search = (string)file_get_contents($root . '/search.php');
check('search does not dump every school when school id is missing', !str_contains($search, 'full_name LIKE ? OR student_number LIKE ? OR email LIKE ? ORDER BY full_name LIMIT 20'));

$leave = (string)file_get_contents($root . '/leave/index.php');
check('parent leave matches the child user, not the student primary key', str_contains($leave, 'st.user_id = lr.user_id') && !str_contains($leave, 'ps.student_id = lr.user_id'));

$fetchTasks = (string)file_get_contents($root . '/ajax/fetch_tasks.php');
check('task fetch is not unscoped for platform admins', !str_contains($fetchTasks, "\$baseWhere = '1=1'"));

$usersPage = (string)file_get_contents($root . '/admin/users.php');
check('user list does not select password hashes', !str_contains($usersPage, 'SELECT * FROM users'));

$_SESSION['user_role'] = 'super_admin';
unset($_SESSION['school_id']);
[$platformSql] = school_scope('s.school_id');
check('platform admin without a school gets no operational rows', $platformSql === '1=0');
$_SESSION['user_role'] = 'school_admin';
$_SESSION['school_id'] = 4;
[$schoolSql, $schoolParams] = school_scope('s.school_id');
check('school scope binds the signed-in school', $schoolSql === 's.school_id = ?' && $schoolParams === [4]);
check('another school is not accessible', can_access_school(9) === false);
check('the signed-in school is accessible', can_access_school(4) === true);
$_SESSION['user_role'] = 'super_admin';
unset($_SESSION['school_id']);
check('platform admin cannot open another school record by id', can_access_school(4) === false);

echo "\nPassed: {$passed}  Failed: {$failed}\n";
exit($failed === 0 ? 0 : 1);
