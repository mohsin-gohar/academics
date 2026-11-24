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

function login(string $email, string $pw): string {
    global $base, $tmp;
    $jar = tempnam($tmp, 'smoke_');
    $p = http("$base/auth/login.php", $jar);
    preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $p['body'], $m);
    http("$base/auth/login_process.php", $jar, ['csrf_token' => $m[1] ?? '', 'email' => $email, 'password' => $pw]);
    return $jar;
}

function page(string $label, string $path, string $jar, int $expectedCode = 200): void {
    global $base, $pass, $fail, $rows;
    $r = http($base . $path, $jar);
    $bad = preg_match('/(<b>(Fatal error|Warning|Notice|Deprecated)<\/b>|Uncaught (Error|Exception)|(Fatal error|Warning|Notice|Deprecated):\s+.*\s+in\s+.*\s+on line)/i', $r['body']);
    $ok = $r['code'] === $expectedCode && !$bad;
    if ($ok) $pass++; else $fail++;
    $rows[] = ($ok ? 'PASS ' : 'FAIL ') . str_pad($label, 42) . ' HTTP ' . $r['code'] . ' (expected ' . $expectedCode . ')' . ($bad ? '  PHP-ERROR-IN-BODY' : '') . '  ' . $r['url'];
}

$sch1Student = (int)$pdo->query("SELECT st.id FROM students st JOIN users u ON u.id=st.user_id WHERE u.email='student@sch001.com' LIMIT 1")->fetchColumn();
$sch1Event = (int)$pdo->query("SELECT id FROM school_events WHERE school_id=(SELECT id FROM schools WHERE school_code='SCH001') LIMIT 1")->fetchColumn();
$sch1User = (int)$pdo->query("SELECT id FROM users WHERE email='teacher@sch001.com' LIMIT 1")->fetchColumn();

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

$parent = login('parent@sch001.com', 'Parent@123');
foreach ([
    'parent dashboard' => '/parent/dashboard.php',
    'parent children' => '/parent/children.php',
    'parent progress' => '/parent/progress.php',
    'parent announcements' => '/parent/announcements.php',
    'parent tasks' => '/tasks/index.php',
    'parent events' => '/events/index.php',
] as $label => $path) page($label, $path, $parent);

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
