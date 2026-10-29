<?php
/**
 * Academics Automated Test Runner
 * Executes unit, integration, database, and security tests.
 */

// Set CLI environment
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';

require_once __DIR__ . '/AuthTest.php';
require_once __DIR__ . '/TasksTest.php';
require_once __DIR__ . '/StudentsCoursesTest.php';
require_once __DIR__ . '/SecurityTest.php';
require_once __DIR__ . '/EventsAlertsTest.php';
require_once __DIR__ . '/SaasTest.php';

echo "\n";
echo "====================================================================\n";
echo "       ACADEMICS SCHOOL MANAGEMENT PLATFORM — TEST SUITE          \n";
echo "====================================================================\n";
echo " Environment: PHP " . PHP_VERSION . " | MySQL PDO | UTF-8\n";
echo " Timezone:    " . date_default_timezone_get() . "\n";
echo " Started at:  " . date('Y-m-d H:i:s') . "\n";
echo "--------------------------------------------------------------------\n\n";

$suites = [
    'Authentication & Session Security' => new AuthTest($pdo),
    'Mandatory Tasks Module & Lifecycle' => new TasksTest($pdo),
    'Students, Courses & Enrollments'   => new StudentsCoursesTest($pdo),
    'Security, SQLi, XSS & Injection'   => new SecurityTest($pdo),
    'School Events, Holidays & Alerts'  => new EventsAlertsTest($pdo),
    'SaaS Plans, Trials & Subscriptions' => new SaasTest($pdo),
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
