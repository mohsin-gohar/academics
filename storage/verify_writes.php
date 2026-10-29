<?php
/**
 * Write-flow verification: tenant stamping, cross-school access, school deactivation.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/tenant.php';
startSecureSession();
require __DIR__ . '/../config/db.php';

$base = getenv('ACADEMICS_BASE') ?: (getenv('EDUSPHERE_BASE') ?: 'http://127.0.0.1:8090');
$tmp = sys_get_temp_dir();
$pass = 0; $fail = 0; $rows = [];

function check(string $name, bool $ok, string $detail = ''): void {
    global $pass, $fail, $rows;
    $ok ? $pass++ : $fail++;
    $rows[] = ($ok ? 'PASS ' : 'FAIL ') . $name . ($detail !== '' ? "  [$detail]" : '');
}

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

function token(string $html): string {
    return preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $html, $m) ? $m[1] : '';
}

function login(string $email, string $pw): array {
    global $base, $tmp;
    $jar = tempnam($tmp, 'wjar_');
    $p = http("$base/auth/login.php", $jar);
    $r = http("$base/auth/login_process.php", $jar, ['csrf_token' => token($p['body']), 'email' => $email, 'password' => $pw]);
    return ['jar' => $jar, 'res' => $r];
}

$school1 = (int)$pdo->query("SELECT id FROM schools WHERE school_code='SCH001'")->fetchColumn();
$school2 = (int)$pdo->query("SELECT id FROM schools WHERE school_code='SCH002'")->fetchColumn();
$stamp = time();

// ---------------- School admin write flows ----------------
$admin = login('schooladmin@sch001.com', 'School@123');
$jar = $admin['jar'];
check('School admin login for write tests', $admin['res']['code'] === 200);

// Event create
$page = http("$base/events/create.php", $jar);
$eventTitle = 'Verify Event ' . $stamp;
$res = http("$base/events/create.php", $jar, [
    'csrf_token' => token($page['body']), 'title' => $eventTitle, 'event_type' => 'Function',
    'event_date' => date('Y-m-d', strtotime('+5 days')), 'end_date' => '', 'description' => 'verification',
]);
$ev = $pdo->prepare("SELECT * FROM school_events WHERE title = ?");
$ev->execute([$eventTitle]);
$evRow = $ev->fetch();
check('School admin created event stamped with own school_id', $evRow && (int)$evRow['school_id'] === $school1, $evRow ? 'school_id=' . $evRow['school_id'] : 'not inserted');
check('Created event appears on events list', strpos($res['body'], $eventTitle) !== false);

// Task create
$page = http("$base/tasks/create.php", $jar);
$taskTitle = 'Verify Task ' . $stamp;
$sch1Student = (int)$pdo->query("SELECT st.id FROM students st JOIN users u ON u.id=st.user_id WHERE u.email='student@sch001.com' LIMIT 1")->fetchColumn();
$res = http("$base/tasks/create.php", $jar, [
    'csrf_token' => token($page['body']), 'title' => $taskTitle, 'description' => 'verification',
    'student_id' => $sch1Student, 'course_id' => '', 'priority' => 'Medium', 'status' => 'Pending',
    'due_date' => date('Y-m-d', strtotime('+7 days')),
]);
$t = $pdo->prepare("SELECT * FROM tasks WHERE title = ?");
$t->execute([$taskTitle]);
$tRow = $t->fetch();
check('School admin created task stamped with own school_id', $tRow && (int)$tRow['school_id'] === $school1, $tRow ? 'school_id=' . $tRow['school_id'] : 'not inserted');

// Student create
$page = http("$base/students/create.php", $jar);
$stuEmail = 'verify.student.' . $stamp . '@sch001.test';
$res = http("$base/students/create.php", $jar, [
    'csrf_token' => token($page['body']), 'full_name' => 'Verify Student ' . $stamp, 'email' => $stuEmail,
    'phone' => '', 'date_of_birth' => '2010-01-01', 'gender' => 'Male', 'address' => '',
    'enrollment_date' => date('Y-m-d'), 'status' => 'Active',
]);
$u = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$u->execute([$stuEmail]);
$uRow = $u->fetch();
$s = $pdo->prepare("SELECT * FROM students WHERE email = ?");
$s->execute([$stuEmail]);
$sRow = $s->fetch();
check('School admin created student user stamped with own school_id', $uRow && (int)$uRow['school_id'] === $school1);
check('School admin created student profile stamped with own school_id', $sRow && (int)$sRow['school_id'] === $school1);

// User create (teacher)
$page = http("$base/admin/create_user.php", $jar);
$teacherEmail = 'verify.teacher.' . $stamp . '@sch001.test';
$res = http("$base/admin/create_user.php", $jar, [
    'csrf_token' => token($page['body']), 'full_name' => 'Verify Teacher ' . $stamp,
    'email' => $teacherEmail, 'password' => 'Verify@123', 'role' => 'teacher', 'is_active' => '1',
]);
$u2 = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$u2->execute([$teacherEmail]);
$u2Row = $u2->fetch();
check('School admin created teacher stamped with own school_id', $u2Row && (int)$u2Row['school_id'] === $school1);

// School settings update (branding)
$page = http("$base/admin/settings.php", $jar);
$res = http("$base/admin/settings.php", $jar, [
    'csrf_token' => token($page['body']), 'school_name' => 'Academics International School',
    'school_email' => 'info@sch001.edu', 'school_phone' => '+92 300 111 2233',
    'school_address' => '12-A, Knowledge Avenue, Lahore', 'motto' => 'Learn. Lead. Inspire.',
    'website' => 'https://sch001.example.edu', 'primary_color' => '#4f46e5', 'secondary_color' => '#06b6d4',
    'public_page_title' => 'Academics International School — Official Portal',
    'public_page_description' => 'Welcome to Academics International School.',
    'session_timeout_minutes' => '30', 'allow_parent_auto_login' => '1',
]);
check('School admin can save school branding settings', strpos($res['body'], 'updated successfully') !== false);

// ---------------- Super admin creates a school ----------------
$super = login('admin@edusphere.com', 'Admin@123');
$page = http("$base/admin/schools.php", $super['jar']);
$newCode = 'VER' . substr((string)$stamp, -6);
$newSubdomain = 'ver-' . substr((string)$stamp, -8);
$res = http("$base/admin/schools.php", $super['jar'], [
    'csrf_token' => token($page['body']), 'action' => 'save', 'id' => '0',
    'school_name' => 'Verification School ' . $stamp, 'school_code' => $newCode,
    'subdomain' => $newSubdomain, 'owner_name' => 'Verification Owner',
    'school_email' => 'verify@school.test', 'school_phone' => '123', 'school_address' => 'Test',
    'motto' => 'Test motto', 'website' => 'https://verify.test',
    'primary_color' => '#112233', 'secondary_color' => '#445566',
    'plan' => 'basic', 'status' => 'trial', 'trial_ends_at' => date('Y-m-d', strtotime('+30 days')),
    'is_active' => '1',
]);
$ns = $pdo->prepare("SELECT * FROM schools WHERE school_code = ?");
$ns->execute([$newCode]);
$nsRow = $ns->fetch();
check('Super admin created a new school', (bool)$nsRow, $nsRow ? 'id=' . $nsRow['id'] : 'not inserted');
check('New school stores its subdomain and trial plan', $nsRow && $nsRow['subdomain'] === $newSubdomain && $nsRow['status'] === 'trial' && $nsRow['plan'] === 'basic');
if ($nsRow) {
    $pub = http("$base/public/school.php?code=" . urlencode($newCode));
    check('New school public page works with custom name', $pub['code'] === 200 && strpos($pub['body'], 'Verification School ' . $stamp) !== false);
    $settings = $pdo->prepare("SELECT COUNT(*) FROM school_settings WHERE school_id = ?");
    $settings->execute([$nsRow['id']]);
    check('New school got default public settings row', (int)$settings->fetchColumn() === 1);
    $trialSub = $pdo->prepare("SELECT COUNT(*) FROM school_subscriptions WHERE school_id = ? AND payment_method = 'trial'");
    $trialSub->execute([$nsRow['id']]);
    check('New school got a zero-price trial subscription', (int)$trialSub->fetchColumn() === 1);
}

// ---------------- Cross-school parent / student ----------------
$sch2Student = (int)$pdo->query("SELECT st.id FROM students st JOIN users u ON u.id=st.user_id WHERE u.email='student@sch002.com' LIMIT 1")->fetchColumn();
$sch2Task = (int)$pdo->query("SELECT id FROM tasks WHERE school_id = $school2 LIMIT 1")->fetchColumn();

$parent = login('parent@sch001.com', 'Parent@123');
$pRes = http("$base/students/show.php?id=" . $sch2Student, $parent['jar']);
check('Parent cannot view another school child', strpos($pRes['url'], 'parent/children.php') !== false, $pRes['url']);
$pTask = http("$base/tasks/show.php?id=" . $sch2Task, $parent['jar']);
check('Parent cannot view another school task', strpos($pTask['url'], 'errors/403.php') !== false, $pTask['url']);

$student = login('student@sch001.com', 'Student@123');
$sRes = http("$base/tasks/show.php?id=" . $sch2Task, $student['jar']);
check('Student cannot view another school task', strpos($sRes['url'], 'errors/403.php') !== false, $sRes['url']);

// ---------------- School deactivation blocks login ----------------
$pdo->prepare("UPDATE schools SET is_active = 0 WHERE id = ?")->execute([$school2]);
$blocked = login('schooladmin@sch002.com', 'School@123');
check('Deactivated school login is blocked', strpos($blocked['res']['url'], 'login.php') !== false && strpos($blocked['res']['body'], 'disabled') !== false, $blocked['res']['url']);
$pdo->prepare("UPDATE schools SET is_active = 1 WHERE id = ?")->execute([$school2]);
$restored = login('schooladmin@sch002.com', 'School@123');
check('Reactivated school login works again', strpos($restored['res']['url'], 'school-admin/dashboard.php') !== false, $restored['res']['url']);

// ---------------- Cleanup verification rows ----------------
$pdo->prepare("DELETE FROM tasks WHERE title = ?")->execute([$taskTitle]);
$pdo->prepare("DELETE FROM school_events WHERE title = ?")->execute([$eventTitle]);
if ($sRow) {
    $pdo->prepare("DELETE FROM tasks WHERE student_id = ?")->execute([$sRow['id']]);
    $pdo->prepare("DELETE FROM enrollments WHERE student_id = ?")->execute([$sRow['id']]);
    $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$sRow['id']]);
}
if ($uRow) $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uRow['id']]);
if ($u2Row) $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$u2Row['id']]);
if ($nsRow) {
    $pdo->prepare("DELETE FROM activity_logs WHERE school_id = ?")->execute([$nsRow['id']]);
    $pdo->prepare("DELETE FROM contact_messages WHERE school_id = ?")->execute([$nsRow['id']]);
    $pdo->prepare("DELETE FROM school_subscriptions WHERE school_id = ?")->execute([$nsRow['id']]);
    $pdo->prepare("DELETE FROM school_settings WHERE school_id = ?")->execute([$nsRow['id']]);
    $pdo->prepare("DELETE FROM users WHERE school_id = ?")->execute([$nsRow['id']]);
    $pdo->prepare("DELETE FROM schools WHERE id = ?")->execute([$nsRow['id']]);
}

echo implode(PHP_EOL, $rows) . PHP_EOL;
echo str_repeat('-', 80) . PHP_EOL;
echo "PASS: $pass   FAIL: $fail" . PHP_EOL;
exit($fail === 0 ? 0 : 1);
