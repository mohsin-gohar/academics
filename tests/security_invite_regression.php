<?php
/**
 * DB-free regression checks for invite secrecy and platform-role boundaries.
 * Run: php tests/security_invite_regression.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

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

$functions = (string)file_get_contents($root . '/includes/functions.php');
check('live session checks trial expiry', str_contains($functions, 'trial_expired'));
check('live session checks session_version', str_contains($functions, 'session_revoked'));
check('live session is not cached for 60 seconds', !str_contains($functions, '60 seconds'));

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

echo "\nPassed: {$passed}  Failed: {$failed}\n";
exit($failed === 0 ? 0 : 1);
