<?php
/**
 * Comprehensive Automated Page Execution & QA Suite
 * Tests rendering of EVERY single page with simulated Admin, Teacher, and Student sessions.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pages = [
    'index.php',
    'public_events.php',
    'dashboard.php',
    'students/index.php',
    'students/create.php',
    'students/show.php?id=1',
    'students/edit.php?id=1',
    'courses/index.php',
    'courses/create.php',
    'courses/show.php?id=1',
    'courses/edit.php?id=1',
    'tasks/index.php',
    'tasks/create.php',
    'tasks/edit.php?id=1',
    'events/index.php',
    'events/create.php',
    'reports/index.php',
    'admin/emergency_alert.php',
    'admin/users.php',
    'admin/activity_log.php',
    'admin/settings.php',
    'errors/403.php',
    'errors/404.php',
    'errors/500.php',
];

$roles = [
    'admin' => ['email' => 'admin@edusphere.com', 'password' => 'Admin@123'],
    'teacher' => ['email' => 'teacher@edusphere.com', 'password' => 'Teacher@123'],
    'student' => ['email' => 'student@edusphere.com', 'password' => 'Student@123'],
];

echo "====================================================================\n";
echo "      COMPREHENSIVE PAGE EXECUTION TEST SUITE\n";
echo "====================================================================\n";

$failures = [];
$totalTested = 0;
$baseUrl = rtrim(getenv('APP_BASE_URL') ?: 'http://localhost/student-task-manager/', '/') . '/';

foreach ($roles as $roleKey => $creds) {
    echo "\nTesting as Role: [{$roleKey}]\n";
    
    $cookieFile = sys_get_temp_dir() . "/edusphere_cookie_{$roleKey}.txt";
    if (file_exists($cookieFile)) @unlink($cookieFile);

    // 1. Get CSRF Token
    $ch = curl_init($baseUrl . 'auth/login.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    $html = curl_exec($ch);
    curl_close($ch);
    
    preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/i', $html, $matches);
    $csrfToken = $matches[1] ?? '';

    // 2. Perform Login
    $ch = curl_init($baseUrl . 'auth/login_process.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'csrf_token' => $csrfToken,
        'email' => $creds['email'],
        'password' => $creds['password'],
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    $loginResult = curl_exec($ch);
    $loginCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 3. Test Each Page
    foreach ($pages as $page) {
        $totalTested++;
        $url = $baseUrl . $page;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $hasError = false;
        $errorMsg = '';

        if (strpos($page, 'errors/500.php') !== false) {
            if ($httpCode !== 500) {
                $hasError = true;
                $errorMsg = "Expected HTTP 500, got {$httpCode}";
            }
        } elseif (strpos($page, 'errors/403.php') !== false) {
            if ($httpCode !== 403) {
                $hasError = true;
                $errorMsg = "Expected HTTP 403, got {$httpCode}";
            }
        } elseif (strpos($page, 'errors/404.php') !== false) {
            if ($httpCode !== 404) {
                $hasError = true;
                $errorMsg = "Expected HTTP 404, got {$httpCode}";
            }
        } else {
            // Should be 200 (or 403 if role unauthorized for admin tools)
            if ($httpCode >= 500) {
                $hasError = true;
                $errorMsg = "HTTP {$httpCode} Server Error";
            } elseif (preg_match('/Fatal error|Parse error|Uncaught Exception|SQLSTATE/i', $content, $m)) {
                $hasError = true;
                $errorMsg = "PHP Error: " . $m[0];
            }
        }

        if ($hasError) {
            echo "  [FAIL] {$page} -> {$errorMsg}\n";
            $failures[] = "[{$roleKey}] {$page}: {$errorMsg}";
        } else {
            echo "  [PASS] {$page} (HTTP {$httpCode})\n";
        }
    }
}

echo "\n--------------------------------------------------------------------\n";
echo "Total Page Executions Tested: {$totalTested}\n";
if (empty($failures)) {
    echo "RESULT: ALL PAGES PASSED WITH 0 ERRORS!\n";
    echo "====================================================================\n";
    exit(0);
} else {
    echo "RESULT: " . count($failures) . " FAILURE(S) DETECTED:\n";
    foreach ($failures as $f) {
        echo " - " . $f . "\n";
    }
    echo "====================================================================\n";
    exit(1);
}
