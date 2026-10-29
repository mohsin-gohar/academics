<?php
/**
 * Live verification for multi-school Academics.
 * Run: php storage/verify_live.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/tenant.php';
startSecureSession();
require __DIR__ . '/../config/db.php';

$base = getenv('ACADEMICS_BASE') ?: (getenv('EDUSPHERE_BASE') ?: 'http://127.0.0.1:8090');
$tmp  = sys_get_temp_dir();
$pass = 0; $fail = 0;
$results = [];

function check(string $name, bool $ok, string $detail = ''): void {
    global $pass, $fail, $results;
    if ($ok) { $pass++; $results[] = "PASS  $name" . ($detail ? "  [$detail]" : ''); }
    else     { $fail++; $results[] = "FAIL  $name" . ($detail ? "  [$detail]" : ''); }
}

function http(string $url, ?string $jar = null, array $post = null, bool $follow = true): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    if ($jar) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $jar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $jar);
    }
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $final = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr((string)$raw, 0, $headerSize);
    $body = substr((string)$raw, $headerSize);
    curl_close($ch);
    return ['code' => $code, 'body' => $body, 'headers' => $headers, 'url' => $final];
}

function login(string $email, string $password): array {
    global $base, $tmp;
    $jar = tempnam($tmp, 'edu_jar_');
    $loginPage = http("$base/auth/login.php", $jar);
    if (!preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $loginPage['body'], $m)) {
        return ['ok' => false, 'reason' => 'no csrf', 'jar' => $jar];
    }
    $res = http("$base/auth/login_process.php", $jar, [
        'csrf_token' => $m[1],
        'email' => $email,
        'password' => $password,
        'remember' => '1',
    ]);
    return ['ok' => $res['code'] === 200 && strpos($res['url'], 'login.php') === false, 'res' => $res, 'jar' => $jar];
}

function bodyHas(array $res, string $needle): bool {
    return stripos($res['body'], $needle) !== false;
}

$roles = [
    'super_admin'  => ['admin@edusphere.com', 'Admin@123', 'dashboard.php', 'Dashboard'],
    'school_admin' => ['schooladmin@sch001.com', 'School@123', 'school-admin/dashboard.php', 'Academics International School'],
    'teacher'      => ['teacher@sch001.com', 'Teacher@123', 'dashboard.php', 'Dashboard'],
    'student'      => ['student@sch001.com', 'Student@123', 'dashboard.php', 'Dashboard'],
    'parent'       => ['parent@sch001.com', 'Parent@123', 'parent/dashboard.php', 'Parent Dashboard'],
];

$jars = [];
foreach ($roles as $role => [$email, $pw, $expectedPath, $marker]) {
    $r = login($email, $pw);
    $jars[$role] = $r['jar'] ?? null;
    if (!$r['ok']) { check("Login $role", false, $r['reason'] ?? 'redirect back to login'); continue; }
    check("Login $role", true, $r['res']['url']);
    check("$role lands on $expectedPath", strpos($r['res']['url'], $expectedPath) !== false, $r['res']['url']);
    check("$role dashboard renders '$marker'", bodyHas($r['res'], $marker));
}

// ---------- School branding in sidebar ----------
$sa = http("$base/school-admin/dashboard.php", $jars['school_admin']);
check('School admin sees own school name in sidebar', bodyHas($sa, 'Academics International School'));
check('School admin does NOT see other school name', !bodyHas($sa, 'Beaconhouse Model School'));

// ---------- Super admin sees both schools ----------
$sup = http("$base/admin/schools.php", $jars['super_admin']);
check('Super admin schools page lists SCH001', bodyHas($sup, 'Academics International School'));
check('Super admin schools page lists SCH002', bodyHas($sup, 'Beaconhouse Model School'));

// ---------- Cross-school isolation ----------
$sch2Student = $pdo->query("SELECT st.id FROM students st JOIN users u ON u.id = st.user_id WHERE u.email = 'student@sch002.com' LIMIT 1")->fetchColumn();
$sch2Task = $pdo->query("SELECT id FROM tasks WHERE school_id = (SELECT id FROM schools WHERE school_code='SCH002') LIMIT 1")->fetchColumn();
$sch2User = $pdo->query("SELECT id FROM users WHERE email = 'student@sch002.com' LIMIT 1")->fetchColumn();

$cross1 = http("$base/students/edit.php?id=" . (int)$sch2Student, $jars['school_admin']);
check('Cross-school student edit blocked (403)', strpos($cross1['url'], 'errors/403.php') !== false, $cross1['url']);
$cross2 = http("$base/tasks/show.php?id=" . (int)$sch2Task, $jars['school_admin']);
check('Cross-school task view blocked (403)', strpos($cross2['url'], 'errors/403.php') !== false, $cross2['url']);
$cross3 = http("$base/admin/edit_user.php?id=" . (int)$sch2User, $jars['school_admin']);
check('Cross-school user edit blocked (403)', strpos($cross3['url'], 'errors/403.php') !== false, $cross3['url']);

// Same-school access still works
$sch1Student = $pdo->query("SELECT st.id FROM students st JOIN users u ON u.id = st.user_id WHERE u.email = 'student@sch001.com' LIMIT 1")->fetchColumn();
$same = http("$base/students/show.php?id=" . (int)$sch1Student, $jars['school_admin']);
check('Same-school student view allowed', $same['code'] === 200 && bodyHas($same, 'Ayesha Malik'), $same['url']);

// ---------- Role-based task visibility ----------
$teacherTasks = http("$base/tasks/index.php", $jars['teacher']);
check('Teacher tasks page loads', $teacherTasks['code'] === 200);
check('Teacher does not see SCH002 task', !bodyHas($teacherTasks, 'English Essay'));

$studentTasks = http("$base/tasks/index.php", $jars['student']);
check('Student sees own SCH001 task', bodyHas($studentTasks, 'Algebra Worksheet Chapter 4'));
check('Student does not see SCH002 task', !bodyHas($studentTasks, 'English Essay'));

$parentDash = http("$base/parent/dashboard.php", $jars['parent']);
check('Parent sees linked child', bodyHas($parentDash, 'Ayesha Malik'));
check('Parent sees child task', bodyHas($parentDash, 'Algebra Worksheet Chapter 4'));
check('Parent does not see SCH002 child', !bodyHas($parentDash, 'Hamza Sheikh'));

// ---------- Reports & CSV isolation ----------
$rep = http("$base/reports/index.php?type=students", $jars['school_admin']);
check('School report shows own student', bodyHas($rep, 'Ayesha Malik'));
check('School report hides other school student', !bodyHas($rep, 'Hamza Sheikh'));
$csv = http("$base/reports/index.php?export=csv&type=students", $jars['school_admin']);
check('CSV export is school-scoped', bodyHas($csv, 'Ayesha Malik') && !bodyHas($csv, 'Hamza Sheikh'));

// ---------- Public pages ----------
$pub1 = http("$base/public/school.php?code=SCH001");
check('Public SCH001 page 200', $pub1['code'] === 200);
check('Public SCH001 shows school name', bodyHas($pub1, 'Academics International School'));
check('Public SCH001 does not leak SCH002 name', !bodyHas($pub1, 'Beaconhouse Model School'));

$pub2 = http("$base/public/school.php?code=SCH002");
check('Public SCH002 page 200', $pub2['code'] === 200);
check('Public SCH002 shows school name', bodyHas($pub2, 'Beaconhouse Model School'));
check('Public SCH002 does not leak SCH001 name', !bodyHas($pub2, 'Academics International School'));

$dir = http("$base/public/index.php");
check('Public directory lists both schools', bodyHas($dir, 'Academics International School') && bodyHas($dir, 'Beaconhouse Model School'));

$ev1 = http("$base/public/school_events.php?code=SCH001");
check('Public SCH001 events page 200', $ev1['code'] === 200);
check('Public SCH001 events shows own event', bodyHas($ev1, 'Annual Science Exhibition'));
check('Public SCH001 events does not show SCH002 event', !bodyHas($ev1, 'Inter-School Debate'));

// ---------- Auto-login (single-use) ----------
$parentId = (int)$pdo->query("SELECT id FROM users WHERE email='parent@sch001.com'")->fetchColumn();
$cache = $pdo->prepare("SELECT id FROM user_redis_cache WHERE user_id = ?");
$cache->execute([$parentId]);
if (!$cache->fetchColumn()) {
    $u = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $u->execute([$parentId]);
    tenant_user_cache_upsert($pdo, $u->fetch());
}
$token = create_auto_login_token($pdo, $parentId, 300);
check('Auto-login token created', is_string($token) && strlen($token) === 64);

$autoJar = tempnam($tmp, 'edu_auto_');
$auto = http("$base/auth/auto_login.php?token=" . urlencode($token), $autoJar);
check('Auto-login redirects to parent dashboard', strpos($auto['url'], 'parent/dashboard.php') !== false, $auto['url']);
check('Auto-login session works', bodyHas($auto, 'Ayesha Malik'));

$auto2 = http("$base/auth/auto_login.php?token=" . urlencode($token), tempnam($tmp, 'edu_auto2_'));
check('Auto-login token is single-use', strpos($auto2['url'], 'login.php') !== false, $auto2['url']);

// ---------- Redis/file cache ----------
check('TenantCache using Redis?', true, TenantCache::usingRedis() ? 'Redis' : 'Encrypted file fallback');

echo implode(PHP_EOL, $results) . PHP_EOL;
echo str_repeat('-', 70) . PHP_EOL;
echo "PASS: $pass   FAIL: $fail" . PHP_EOL;
exit($fail === 0 ? 0 : 1);
