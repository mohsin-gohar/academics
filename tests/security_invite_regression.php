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

echo "\nPassed: {$passed}  Failed: {$failed}\n";
exit($failed === 0 ? 0 : 1);
