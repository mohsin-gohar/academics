<?php
/**
 * Academics / EduSphere Automated Test Runner
 * Executes unit, integration, database, security, and feature tests.
 */

// Set CLI environment
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';

// Seed deterministic data only for the isolated CI/test database.
require_once __DIR__ . '/fixtures.php';

// Core suites
require_once __DIR__ . '/AuthTest.php';
require_once __DIR__ . '/TasksTest.php';
require_once __DIR__ . '/StudentsCoursesTest.php';
require_once __DIR__ . '/SecurityTest.php';
require_once __DIR__ . '/EventsAlertsTest.php';
require_once __DIR__ . '/SaasTest.php';
require_once __DIR__ . '/TenantIsolationTest.php';

// Expanded modular suites
require_once __DIR__ . '/test_auth.php';
require_once __DIR__ . '/test_security.php';
require_once __DIR__ . '/test_models.php';
require_once __DIR__ . '/test_features.php';

echo "\n";
echo "====================================================================\n";
echo "       EDUSPHERE ENTERPRISE SCHOOL MANAGEMENT — TEST SUITE          \n";
echo "====================================================================\n";
echo " Environment: PHP " . PHP_VERSION . " | MySQL PDO | UTF-8\n";
echo " Timezone:    " . date_default_timezone_get() . "\n";
echo " Started at:  " . date('Y-m-d H:i:s') . "\n";
echo "--------------------------------------------------------------------\n\n";

$suites = [
    'Authentication & Session Security' => new AuthTest($pdo),
    'Auth Deep-Dive Suite (test_auth)' => new TestAuthSuite($pdo),
    'Mandatory Tasks Module & Lifecycle' => new TasksTest($pdo),
    'Students, Courses & Enrollments'   => new StudentsCoursesTest($pdo),
    'Database Models & Constraints (test_models)' => new TestModelsSuite($pdo),
    'Security, SQLi, XSS & Injection'   => new SecurityTest($pdo),
    'Security Hardening & RBAC (test_security)' => new TestSecuritySuite($pdo),
    'School Events, Holidays & Alerts'  => new EventsAlertsTest($pdo),
    'Advanced Features: Attendance, Grades, Fees, Timetable (test_features)' => new TestFeaturesSuite($pdo),
    'SaaS Plans, Trials & Subscriptions' => new SaasTest($pdo),
    'Multi-school tenant isolation' => new TenantIsolationTest($pdo),
];

$totalTests = 0;
$totalPassed = 0;
$totalFailed = 0;
$startTime = microtime(true);

foreach ($suites as $suiteName => $suite) {
    echo "[\033[36mSUITE\033[0m] \033[1m$suiteName\033[0m\n";
    $results = $suite->run();

    foreach ($results as $res) {
        $totalTests++;
        if ($res['pass']) {
            $totalPassed++;
            echo "  [\033[32mPASS\033[0m] " . $res['name'] . "\n";
        } else {
            $totalFailed++;
            echo "  [\033[31mFAIL\033[0m] " . $res['name'] . "\n";
        }
    }
    echo "\n";
}

$elapsed = round((microtime(true) - $startTime) * 1000, 2);

echo "--------------------------------------------------------------------\n";
echo " TEST SUMMARY:\n";
echo " Total Tests: $totalTests\n";
echo " Passed:      \033[32m$totalPassed\033[0m\n";
echo " Failed:      " . ($totalFailed > 0 ? "\033[31m$totalFailed\033[0m" : "0") . "\n";
echo " Duration:    {$elapsed} ms\n";
echo " Result:      " . ($totalFailed === 0 ? "\033[32;1mALL TESTS PASSED SUCCESSFULLY\033[0m" : "\033[31;1mSOME TESTS FAILED\033[0m") . "\n";
echo "====================================================================\n\n";

exit($totalFailed === 0 ? 0 : 1);
