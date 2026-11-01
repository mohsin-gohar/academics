<?php
/**
 * QA crawler: logs in per role and loads every key page, reporting HTTP status,
 * redirects, PHP errors/warnings and empty pages.
 * CLI only.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once __DIR__ . '/../config/db.php';

$base = getenv('ACADEMICS_BASE') ?: 'http://localhost/student-task-manager/';
$tmp = sys_get_temp_dir();

function http_req(string $url, array $opts = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HEADER => true,
    ]);
    if (!empty($opts['cookiejar'])) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $opts['cookiejar']);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $opts['cookiejar']);
    }
    if (!empty($opts['post'])) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($opts['post']));
    }
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    $loc = '';
    if (preg_match('/^Location:\s*(.+)$/mi', $headers, $m)) { $loc = trim($m[1]); }
    curl_close($ch);
    return ['code' => $code, 'body' => $body, 'location' => $loc, 'headers' => $headers];
}

function login(string $base, string $email, string $pass, string $jar): array {
    @unlink($jar);
    $r = http_req($base . 'auth/login.php', ['cookiejar' => $jar]);
    preg_match('/name="csrf_token"\s+value="([^"]+)"/i', $r['body'], $m);
    $csrf = $m[1] ?? '';
    $r2 = http_req($base . 'auth/login_process.php', ['cookiejar' => $jar, 'post' => [
        'csrf_token' => $csrf, 'email' => $email, 'password' => $pass,
    ]]);
    return ['code' => $r2['code'], 'location' => $r2['location']];
}

$roles = [
    'super' => ['admin@edusphere.com', 'Admin@123'],
    'schooladmin' => ['schooladmin@sch001.com', 'School@123'],
    'teacher' => ['teacher@sch001.com', 'Teacher@123'],
    'student' => ['student@sch001.com', 'Student@123'],
    'parent' => ['parent@sch001.com', 'Parent@123'],
];

$ids = [];
$ids['student'] = (int)$pdo->query("SELECT id FROM students WHERE school_id=1 ORDER BY id LIMIT 1")->fetchColumn();
$ids['course'] = (int)$pdo->query("SELECT id FROM courses WHERE school_id=1 ORDER BY id LIMIT 1")->fetchColumn();
$ids['task'] = (int)$pdo->query("SELECT id FROM tasks WHERE school_id=1 ORDER BY id LIMIT 1")->fetchColumn();
$ids['assessment'] = (int)$pdo->query("SELECT id FROM assessments WHERE school_id=1 ORDER BY id LIMIT 1")->fetchColumn();
$ids['invoice'] = (int)$pdo->query("SELECT id FROM invoices WHERE school_id=1 ORDER BY id LIMIT 1")->fetchColumn();
$ids['event'] = (int)$pdo->query("SELECT id FROM school_events WHERE school_id=1 ORDER BY id LIMIT 1")->fetchColumn();
$ids['user'] = (int)$pdo->query("SELECT id FROM users WHERE school_id=1 ORDER BY id LIMIT 1")->fetchColumn();

$routes = [
    'public' => [
        'index.php', 'public_events.php', 'contact.php', 'auth/login.php', 'auth/register.php', 'auth/forgot_password.php',
        'public/index.php', 'public/school.php?code=SCH001',
    ],
    'super' => [
        'dashboard.php', 'super-admin/dashboard.php', 'super-admin/schools.php', 'super-admin/school.php?id=1', 'super-admin/subscriptions.php',
        'admin/schools.php', 'admin/subscriptions.php', 'admin/messages.php', 'admin/users.php', 'admin/settings.php',
        'admin/emergency_alert.php', 'admin/activity_log.php', 'admin/create_user.php', 'admin/edit_user.php?id=' . $ids['user'],
        'reports/index.php', 'events/index.php', 'events/create.php', 'students/index.php', 'students/show.php?id=' . $ids['student'],
        'courses/index.php', 'courses/show.php?id=' . $ids['course'], 'tasks/index.php', 'tasks/show.php?id=' . $ids['task'],
        'fees/invoices.php', 'fees/structures.php', 'fees/create.php', 'fees/report.php',
        'grades/index.php', 'grades/create.php', 'attendance/index.php', 'attendance/mark.php', 'attendance/report.php',
        'profile.php', 'search.php?q=test',
    ],
    'schooladmin' => [
        'dashboard.php', 'school-admin/dashboard.php', 'students/index.php', 'students/create.php', 'students/show.php?id=' . $ids['student'],
        'students/edit.php?id=' . $ids['student'], 'courses/index.php', 'courses/create.php', 'courses/show.php?id=' . $ids['course'],
        'courses/edit.php?id=' . $ids['course'], 'tasks/index.php', 'tasks/create.php', 'tasks/show.php?id=' . $ids['task'],
        'tasks/edit.php?id=' . $ids['task'], 'events/index.php', 'events/create.php', 'events/edit.php?id=' . $ids['event'],
        'attendance/index.php', 'attendance/mark.php', 'attendance/report.php',
        'grades/index.php', 'grades/create.php', 'grades/view.php?course_id=' . $ids['course'],
        'fees/invoices.php', 'fees/structures.php', 'fees/create.php', 'fees/pay.php?id=' . $ids['invoice'], 'fees/report.php',
        'admin/users.php', 'admin/create_user.php', 'admin/emergency_alert.php', 'admin/activity_log.php', 'admin/messages.php',
        'admin/settings.php', 'reports/index.php', 'profile.php', 'search.php?q=test',
    ],
    'teacher' => [
        'dashboard.php', 'attendance/mark.php', 'attendance/index.php', 'attendance/report.php',
        'grades/index.php', 'grades/create.php', 'grades/view.php?course_id=' . $ids['course'],
        'students/index.php', 'students/show.php?id=' . $ids['student'], 'courses/index.php', 'courses/show.php?id=' . $ids['course'],
        'tasks/index.php', 'tasks/create.php', 'tasks/show.php?id=' . $ids['task'], 'tasks/edit.php?id=' . $ids['task'],
        'events/index.php', 'events/create.php', 'reports/index.php', 'profile.php', 'search.php?q=test',
        'fees/invoices.php', 'admin/users.php', 'admin/emergency_alert.php', 'admin/settings.php',
    ],
    'student' => [
        'dashboard.php', 'students/show.php?id=' . $ids['student'], 'courses/index.php', 'courses/show.php?id=' . $ids['course'],
        'tasks/index.php', 'tasks/show.php?id=' . $ids['task'], 'events/index.php', 'attendance/my.php', 'grades/my.php',
        'fees/my.php', 'reports/index.php', 'profile.php', 'search.php?q=test',
        'students/index.php', 'attendance/mark.php', 'grades/index.php', 'fees/invoices.php', 'admin/users.php',
    ],
    'parent' => [
        'parent/dashboard.php', 'parent/children.php', 'parent/progress.php', 'parent/attendance.php', 'parent/grades.php',
        'parent/fees.php', 'parent/announcements.php', 'events/index.php', 'profile.php',
        'dashboard.php', 'students/index.php', 'admin/users.php',
    ],
];

$fail = 0; $tested = 0;
foreach ($roles as $role => [$email, $pass]) {
    $jar = $tmp . '/qa_' . $role . '.txt';
    $lr = login($base, $email, $pass, $jar);
    echo "\n=== ROLE: $role | login {$lr['code']} -> {$lr['location']} ===\n";
    if ($lr['code'] !== 302) { echo "  LOGIN FAILED\n"; $fail++; continue; }
    $list = array_merge($routes['public'], $routes[$role]);
    foreach (array_unique($list) as $route) {
        $r = http_req($base . $route, ['cookiejar' => $jar]);
        $tested++;
        $bad = '';
        if (preg_match('/(Fatal error|Parse error|Uncaught|Warning:|Notice:|Deprecated:)/i', $r['body'], $m)) {
            $bad = 'PHP_ERROR: ' . $m[1];
        }
        $empty = strlen(trim(strip_tags($r['body']))) < 40;
        if ($empty && $r['code'] === 200) { $bad = ($bad ? $bad . ' | ' : '') . 'EMPTY_BODY'; }
        if ($bad !== '') { $fail++; echo "  [FAIL] $route ({$r['code']}) $bad\n"; }
        else { echo "  [ok] $route ({$r['code']})\n"; }
    }
}
echo "\nTested: $tested | Failures: $fail\n";
exit($fail ? 1 : 0);
