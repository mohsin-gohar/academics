<?php
/**
 * Page smoke test — logs in per role and loads every key page.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/tenant.php';
startSecureSession();
require __DIR__ . '/../config/db.php';

$base = getenv('ACADEMICS_BASE') ?: (getenv('EDUSPHERE_BASE') ?: 'http://127.0.0.1:8090');
$tmp = sys_get_temp_dir();
$pass = 0; $fail = 0; $rows = [];

function http(string $url, ?string $jar = null, array $post = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 20,
    ]);
    if ($jar) { curl_setopt($ch, CURLOPT_COOKIEJAR, $jar); curl_setopt($ch, CURLOPT_COOKIEFILE, $jar); }
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $final = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = substr((string)$raw, $hs);
    curl_close($ch);
    return ['code' => $code, 'body' => $body, 'url' => $final];
}

function studentCannotOpenOtherProfile(string $label, int $targetStudentId, int $ownStudentId, string $jar): void {
    global $base, $pass, $fail, $rows;
    $r = http($base . '/students/show.php?id=' . $targetStudentId, $jar);
    $path = (string)(parse_url($r['url'], PHP_URL_PATH) ?? '');
    $query = [];
    parse_str((string)(parse_url($r['url'], PHP_URL_QUERY) ?? ''), $query);
    $ok = $r['code'] === 200 && $path === '/students/show.php'
        && (int)($query['id'] ?? 0) === $ownStudentId;
    if ($ok) $pass++; else $fail++;
    $rows[] = ($ok ? 'PASS ' : 'FAIL ') . str_pad($label, 42)
        . ' HTTP ' . $r['code'] . '  ' . $r['url'];
}

function login(string $email, string $pw): string {
    global $base, $tmp;
    $jar = tempnam($tmp, 'smoke_');
    $p = http("$base/auth/login.php", $jar);
    preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $p['body'], $m);
    if (empty($m[1])) {
        @unlink($jar);
        throw new RuntimeException("Login page did not provide a CSRF token for {$email}.");
    }

    $result = http("$base/auth/login_process.php", $jar, [
        'csrf_token' => $m[1],
        'email' => $email,
        'password' => $pw,
    ]);
    $path = (string)(parse_url($result['url'], PHP_URL_PATH) ?? '');
    if ($result['code'] >= 400 || preg_match('#/auth/login\.php$#', $path)) {
        @unlink($jar);
        throw new RuntimeException("Authentication failed for fixture account {$email}; final URL: {$result['url']} (HTTP {$result['code']}).");
    }

    // Confirm the cookie jar actually carries the authenticated session.
    $probe = http("$base/dashboard.php", $jar);
    $probePath = (string)(parse_url($probe['url'], PHP_URL_PATH) ?? '');
    if ($probe['code'] >= 400 || preg_match('#/auth/login\.php$#', $probePath)) {
        @unlink($jar);
        throw new RuntimeException("Authenticated session was not retained for fixture account {$email}.");
    }
    return $jar;
}

function page(string $label, string $path, string $jar, int $expectedCode = 200): void {
    global $base, $pass, $fail, $rows;
    $r = http($base . $path, $jar);
    $finalPath = (string)(parse_url($r['url'], PHP_URL_PATH) ?? '');
    $isAuthenticatedRequest = $jar !== '';
    $unexpectedLoginRedirect = $isAuthenticatedRequest
        && $expectedCode === 200
        && preg_match('#/auth/login\.php$#', $finalPath);
    $bad = preg_match('/(<b>(Fatal error|Warning|Notice|Deprecated)<\/b>|Uncaught (Error|Exception)|(Fatal error|Warning|Notice|Deprecated):\s+.*\s+in\s+.*\s+on line)/i', $r['body']);
    $ok = $r['code'] === $expectedCode && !$bad && !$unexpectedLoginRedirect;
    if ($ok) $pass++; else $fail++;
    $reason = $unexpectedLoginRedirect ? '  UNAUTHENTICATED-REDIRECT' : '';
    $rows[] = ($ok ? 'PASS ' : 'FAIL ') . str_pad($label, 42) . ' HTTP ' . $r['code'] . ' (expected ' . $expectedCode . ')' . ($bad ? '  PHP-ERROR-IN-BODY' : '') . $reason . '  ' . $r['url'];
}

$sch1Student = (int)$pdo->query("SELECT st.id FROM students st JOIN users u ON u.id=st.user_id WHERE u.email='student@sch001.com' LIMIT 1")->fetchColumn();
$sch1Event = (int)$pdo->query("SELECT id FROM school_events WHERE school_id=(SELECT id FROM schools WHERE school_code='SCH001') LIMIT 1")->fetchColumn();
$sch1User = (int)$pdo->query("SELECT id FROM users WHERE email='teacher@sch001.com' LIMIT 1")->fetchColumn();
$otherStudent = (int)$pdo->query("SELECT id FROM students WHERE email = 'student@sch002.com' AND school_id = 2 LIMIT 1")->fetchColumn();
$otherCourse = (int)$pdo->query("SELECT id FROM courses WHERE course_code = 'TEST201' AND school_id = 2 LIMIT 1")->fetchColumn();
$otherTask = (int)$pdo->query("SELECT id FROM tasks WHERE title = 'Second School Task' AND school_id = 2 LIMIT 1")->fetchColumn();
$sch1Task = (int)$pdo->query("SELECT id FROM tasks WHERE title = 'Test Assigned Task' AND school_id = 1 LIMIT 1")->fetchColumn();
$otherEvent = (int)$pdo->query("SELECT id FROM school_events WHERE title = 'Second School Event' AND school_id = 2 LIMIT 1")->fetchColumn();
if ($otherStudent <= 0 || $otherStudent === $sch1Student || $otherCourse <= 0 || $otherTask <= 0 || $otherEvent <= 0) {
    fwrite(STDERR, "Cross-tenant smoke fixtures are missing or overlap the primary school fixtures.\n");
    exit(1);
}

$super = login('admin@edusphere.com', 'Admin@123');
foreach ([
    'super dashboard' => '/dashboard.php',
    'super schools' => '/admin/schools.php',
    'super users' => '/admin/users.php',
    'super activity log' => '/admin/activity_log.php',
    'super settings' => '/admin/settings.php',
    'super emergency' => '/admin/emergency_alert.php',
    'super students' => '/students/index.php',
    'super courses' => '/courses/index.php',
    'super tasks' => '/tasks/index.php',
    'super events' => '/events/index.php',
    'super reports' => '/reports/index.php?type=students',
    'super create user' => '/admin/create_user.php',
    'super edit user' => '/admin/edit_user.php?id=' . $sch1User,
    'super profile' => '/profile.php',
    'super platform overview' => '/super-admin/dashboard.php',
    'super subscriptions' => '/admin/subscriptions.php',
    'super messages' => '/admin/messages.php',
    'super school detail' => '/super-admin/school.php?id=1',
] as $label => $path) page($label, $path, $super);

$schoolAdmin = login('schooladmin@sch001.com', 'School@123');
foreach ([
    'school admin dashboard' => '/school-admin/dashboard.php',
    'school admin users' => '/admin/users.php',
    'school admin settings' => '/admin/settings.php',
    'school admin activity' => '/admin/activity_log.php',
    'school admin emergency' => '/admin/emergency_alert.php',
    'school admin students' => '/students/index.php',
    'school admin courses' => '/courses/index.php',
    'school admin tasks' => '/tasks/index.php',
    'school admin events' => '/events/index.php',
    'school admin events create' => '/events/create.php',
    'school admin event edit' => '/events/edit.php?id=' . $sch1Event,
    'school admin reports' => '/reports/index.php?type=tasks',
    'school admin create user' => '/admin/create_user.php',
    'school admin edit user' => '/admin/edit_user.php?id=' . $sch1User,
    'school admin messages' => '/admin/messages.php',
] as $label => $path) page($label, $path, $schoolAdmin);
page('school admin cross-school student denied', '/students/show.php?id=' . $otherStudent, $schoolAdmin, 403);
page('school admin cross-school course denied', '/courses/show.php?id=' . $otherCourse, $schoolAdmin, 403);
page('school admin cross-school task denied', '/tasks/show.php?id=' . $otherTask, $schoolAdmin, 403);
page('school admin cross-school event denied', '/events/edit.php?id=' . $otherEvent, $schoolAdmin, 403);

// Exercise a real cross-tenant write attempt, not only page visibility.
$schoolAdminHome = http($base . '/dashboard.php', $schoolAdmin);
preg_match('/name="csrf_token"\\s+value="([a-f0-9]{64})"/', $schoolAdminHome['body'], $writeCsrf);
$otherTaskStatusBefore = (string)$pdo->query("SELECT status FROM tasks WHERE id = " . $otherTask)->fetchColumn();
$crossTenantTaskWrite = http($base . '/tasks/update_status.php', $schoolAdmin, [
    'id' => $otherTask,
    'status' => 'Completed',
    'csrf_token' => $writeCsrf[1] ?? '',
]);
$otherTaskStatusAfter = (string)$pdo->query("SELECT status FROM tasks WHERE id = " . $otherTask)->fetchColumn();
$crossTenantWriteOk = $crossTenantTaskWrite['code'] === 403
    && $otherTaskStatusAfter === $otherTaskStatusBefore;
if ($crossTenantWriteOk) $pass++; else $fail++;
$rows[] = ($crossTenantWriteOk ? 'PASS ' : 'FAIL ')
    . str_pad('cross-school task status write is denied', 42)
    . ' HTTP=' . $crossTenantTaskWrite['code']
    . ' STATUS=' . $otherTaskStatusBefore . '->' . $otherTaskStatusAfter;

$ownTaskStatusBeforeInvalid = (string)$pdo->query("SELECT status FROM tasks WHERE id = " . $sch1Task)->fetchColumn();
$invalidTaskStatusWrite = http($base . '/tasks/update_status.php', $schoolAdmin, [
    'id' => $sch1Task,
    'status' => 'Unexpected Status',
    'csrf_token' => $writeCsrf[1] ?? '',
]);
$ownTaskStatusAfterInvalid = (string)$pdo->query("SELECT status FROM tasks WHERE id = " . $sch1Task)->fetchColumn();
$invalidStatusOk = strpos($invalidTaskStatusWrite['url'], 'error=') !== false
    && $ownTaskStatusAfterInvalid === $ownTaskStatusBeforeInvalid;
if ($invalidStatusOk) $pass++; else $fail++;
$rows[] = ($invalidStatusOk ? 'PASS ' : 'FAIL ')
    . str_pad('invalid task status is rejected without mutation', 42)
    . ' STATUS=' . $ownTaskStatusBeforeInvalid . '->' . $ownTaskStatusAfterInvalid;

$teacher = login('teacher@sch001.com', 'Teacher@123');
foreach ([
    'teacher dashboard' => '/dashboard.php',
    'teacher students' => '/students/index.php',
    'teacher tasks' => '/tasks/index.php',
    'teacher task create' => '/tasks/create.php',
    'teacher events' => '/events/index.php',
    'teacher event create' => '/events/create.php',
    'teacher courses' => '/courses/index.php',
    'teacher reports' => '/reports/index.php?type=students',
    // Teachers must not access school-administration emergency controls.
] as $label => $path) page($label, $path, $teacher);
page('teacher emergency denied', '/admin/emergency_alert.php', $teacher, 403);
page('teacher cross-school student denied', '/students/show.php?id=' . $otherStudent, $teacher, 403);
page('teacher cross-school course denied', '/courses/show.php?id=' . $otherCourse, $teacher, 403);
page('teacher cross-school task denied', '/tasks/show.php?id=' . $otherTask, $teacher, 403);

// Exercise leave creation against the reconciled legacy-compatible enum.
$leaveForm = http($base . '/leave/create.php', $teacher);
preg_match('/name="csrf_token"\\s+value="([a-f0-9]{64})"/', $leaveForm['body'], $leaveCsrf);
$leaveDate = date('Y-m-d', strtotime('+1 day'));
$leaveSubmit = http($base . '/leave/create.php', $teacher, [
    'csrf_token' => $leaveCsrf[1] ?? '',
    'leave_type' => 'Sick',
    'start_date' => $leaveDate,
    'end_date' => $leaveDate,
    'reason' => 'CI smoke leave request',
]);
$leaveCreated = (int)$pdo->query(
    "SELECT COUNT(*) FROM leave_requests WHERE user_id = " . $sch1User
    . " AND school_id = 1 AND leave_type = 'Sick' AND reason = 'CI smoke leave request' AND status = 'Pending'"
)->fetchColumn();
$leaveCreateOk = $leaveCreated === 1 && $leaveSubmit['code'] === 200;
if ($leaveCreateOk) $pass++; else $fail++;
$rows[] = ($leaveCreateOk ? 'PASS ' : 'FAIL ')
    . str_pad('teacher can submit a valid tenant leave request', 42)
    . ' CREATED=' . $leaveCreated . ' HTTP=' . $leaveSubmit['code'];

$leaveCountBeforeInvalid = (int)$pdo->query("SELECT COUNT(*) FROM leave_requests WHERE user_id = " . $sch1User)->fetchColumn();
$invalidLeaveSubmit = http($base . '/leave/create.php', $teacher, [
    'csrf_token' => $leaveCsrf[1] ?? '',
    'leave_type' => 'Sick',
    'start_date' => '2026-02-31',
    'end_date' => '2026-02-31',
    'reason' => 'Invalid date smoke request',
]);
$leaveCountAfterInvalid = (int)$pdo->query("SELECT COUNT(*) FROM leave_requests WHERE user_id = " . $sch1User)->fetchColumn();
$invalidLeaveOk = $leaveCountAfterInvalid === $leaveCountBeforeInvalid;
if ($invalidLeaveOk) $pass++; else $fail++;
$rows[] = ($invalidLeaveOk ? 'PASS ' : 'FAIL ')
    . str_pad('impossible leave dates are rejected', 42)
    . ' ROWS=' . $leaveCountBeforeInvalid . '->' . $leaveCountAfterInvalid;

$student = login('student@sch001.com', 'Student@123');
foreach ([
    'student dashboard' => '/dashboard.php',
    'student tasks' => '/tasks/index.php',
    'student courses' => '/courses/index.php',
    'student events' => '/events/index.php',
    'student reports' => '/reports/index.php',
    'student own profile' => '/students/show.php?id=' . $sch1Student,
    'student profile page' => '/profile.php',
] as $label => $path) page($label, $path, $student);
studentCannotOpenOtherProfile('student cross-school profile redirects to own', $otherStudent, $sch1Student, $student);

// Logout is state-changing: GET must be rejected and a valid CSRF POST must end the session.
$logoutJar = login('admin@edusphere.com', 'Admin@123');
$logoutDashboard = http($base . '/dashboard.php', $logoutJar);
preg_match('/name="csrf_token"\\s+value="([a-f0-9]{64})"/', $logoutDashboard['body'], $logoutToken);
$logoutGet = http($base . '/auth/logout.php', $logoutJar);
$logoutPost = http($base . '/auth/logout.php', $logoutJar, ['csrf_token' => $logoutToken[1] ?? '']);
$afterLogout = http($base . '/dashboard.php', $logoutJar);
$logoutOk = $logoutGet['code'] === 405
    && $logoutPost['code'] === 200
    && strpos($logoutPost['url'], '/auth/login.php') !== false
    && strpos($afterLogout['url'], '/auth/login.php') !== false;
if ($logoutOk) $pass++; else $fail++;
$rows[] = ($logoutOk ? 'PASS ' : 'FAIL ') . str_pad('logout requires CSRF POST', 42)
    . ' GET=' . $logoutGet['code'] . ' POST=' . $logoutPost['code'] . ' AFTER=' . $afterLogout['url'];

$parent = login('parent@sch001.com', 'Parent@123');
foreach ([
    'parent dashboard' => '/parent/dashboard.php',
    'parent children' => '/parent/children.php',
    'parent progress' => '/parent/progress.php',
    'parent announcements' => '/parent/announcements.php',
    'parent tasks' => '/tasks/index.php',
    'parent events' => '/events/index.php',
    'parent grades' => '/parent/grades.php',
    'parent fees' => '/parent/fees.php',
    'parent attendance' => '/parent/attendance.php',
    'parent own child profile' => '/students/show.php?id=' . $sch1Student,
    'parent unlinked student profile guarded' => '/students/show.php?id=' . $otherStudent,
] as $label => $path) page($label, $path, $parent);

$parentCrossProfile = http($base . '/students/show.php?id=' . $otherStudent, $parent);
$parentCrossProfileOk = strpos($parentCrossProfile['url'], '/parent/children.php') !== false
    && strpos($parentCrossProfile['body'], 'Second School Student') === false;
if ($parentCrossProfileOk) $pass++; else $fail++;
$rows[] = ($parentCrossProfileOk ? 'PASS ' : 'FAIL ')
    . str_pad('parent cannot read an unlinked student profile', 42)
    . ' URL=' . $parentCrossProfile['url'];

// A school must retain at least one active school administrator.
$superDashboard = http($base . '/dashboard.php', $super);
preg_match('/name="csrf_token"\\s+value="([a-f0-9]{64})"/', $superDashboard['body'], $superCsrf);
$lastSchoolAdminId = (int)$pdo->query("SELECT id FROM users WHERE email='schooladmin@sch001.com'")->fetchColumn();
$lastAdminToggle = http($base . '/admin/toggle_user.php', $super, [
    'id' => $lastSchoolAdminId,
    'csrf_token' => $superCsrf[1] ?? '',
]);
$lastAdminStillActive = (int)$pdo->query("SELECT is_active FROM users WHERE id = " . $lastSchoolAdminId)->fetchColumn();
$lastAdminGuardOk = $lastAdminStillActive === 1 && strpos($lastAdminToggle['url'], 'users.php?error=') !== false;
if ($lastAdminGuardOk) $pass++; else $fail++;
$rows[] = ($lastAdminGuardOk ? 'PASS ' : 'FAIL ') . str_pad('last school admin cannot be deactivated', 42)
    . ' ACTIVE=' . $lastAdminStillActive . '  ' . $lastAdminToggle['url'];

// Verify student account activation and student profile status stay in sync.
$schoolAdminDashboard = http($base . '/dashboard.php', $schoolAdmin);
preg_match('/name="csrf_token"\\s+value="([a-f0-9]{64})"/', $schoolAdminDashboard['body'], $schoolAdminCsrf);
$studentUserId = (int)$pdo->query("SELECT id FROM users WHERE email='student@sch001.com'")->fetchColumn();
$studentDashboardBeforeDisable = http($base . '/dashboard.php', $student);
preg_match('/name="csrf_token"\\s+value="([a-f0-9]{64})"/', $studentDashboardBeforeDisable['body'], $studentCsrf);
$sch1TaskStatusBeforeDisable = (string)$pdo->query("SELECT status FROM tasks WHERE id = " . $sch1Task)->fetchColumn();
$toggleStudentOnce = http($base . '/admin/toggle_user.php', $schoolAdmin, [
    'id' => $studentUserId,
    'csrf_token' => $schoolAdminCsrf[1] ?? '',
]);
$inactiveUser = (int)$pdo->query("SELECT is_active FROM users WHERE id = " . $studentUserId)->fetchColumn();
$inactiveProfile = (string)$pdo->query("SELECT status FROM students WHERE user_id = " . $studentUserId)->fetchColumn();
$revokedSessionWrite = http($base . '/tasks/update_status.php', $student, [
    'id' => $sch1Task,
    'status' => 'Completed',
    'csrf_token' => $studentCsrf[1] ?? '',
]);
$sch1TaskStatusAfterRevokedWrite = (string)$pdo->query("SELECT status FROM tasks WHERE id = " . $sch1Task)->fetchColumn();
$revocationOk = strpos($revokedSessionWrite['url'], '/auth/login.php') !== false
    && $sch1TaskStatusAfterRevokedWrite === $sch1TaskStatusBeforeDisable;
if ($revocationOk) $pass++; else $fail++;
$rows[] = ($revocationOk ? 'PASS ' : 'FAIL ')
    . str_pad('deactivated session cannot mutate records', 42)
    . ' URL=' . $revokedSessionWrite['url']
    . ' TASK=' . $sch1TaskStatusBeforeDisable . '->' . $sch1TaskStatusAfterRevokedWrite;
$toggleStudentTwice = http($base . '/admin/toggle_user.php', $schoolAdmin, [
    'id' => $studentUserId,
    'csrf_token' => $schoolAdminCsrf[1] ?? '',
]);
$activeUser = (int)$pdo->query("SELECT is_active FROM users WHERE id = " . $studentUserId)->fetchColumn();
$activeProfile = (string)$pdo->query("SELECT status FROM students WHERE user_id = " . $studentUserId)->fetchColumn();
$studentToggleOk = $inactiveUser === 0 && $inactiveProfile === 'Inactive'
    && $activeUser === 1 && $activeProfile === 'Active'
    && $toggleStudentOnce['code'] === 200 && $toggleStudentTwice['code'] === 200;
if ($studentToggleOk) $pass++; else $fail++;
$rows[] = ($studentToggleOk ? 'PASS ' : 'FAIL ') . str_pad('student activation keeps profile status synced', 42)
    . ' USER=' . $activeUser . '/' . $inactiveUser . ' PROFILE=' . $activeProfile . '/' . $inactiveProfile;

// Public routes remain available without an authenticated session.
foreach ([
    'public landing' => '/index.php',
    'public school registration' => '/register.php',
    'public contact' => '/contact.php',
    'public login' => '/auth/login.php',
    'public user registration' => '/auth/register.php',
    'public school directory' => '/public/index.php',
    'public school page' => '/public/school.php?code=SCH001',
    'public school events' => '/public/school_events.php?code=SCH001',
    'public events' => '/public_events.php',
] as $label => $path) page($label, $path, '');

echo implode(PHP_EOL, $rows) . PHP_EOL;
echo str_repeat('-', 80) . PHP_EOL;
echo "PASS: $pass   FAIL: $fail" . PHP_EOL;
exit($fail === 0 ? 0 : 1);
