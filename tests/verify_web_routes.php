<?php
/**
 * Verification of All Web Routes
 */
$baseUrl = 'http://localhost/student-task-manager';

$routes = [
    '/index.php' => 200,
    '/public_events.php' => 200,
    '/auth/login.php' => 200,
    '/auth/register.php' => 200,
    '/dashboard.php' => 302, // redirects to login when unauthenticated
    '/errors/403.php' => 403,
    '/errors/404.php' => 404,
];

$allPassed = true;

foreach ($routes as $route => $expectedCode) {
    $ch = curl_init($baseUrl . $route);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $passed = ($httpCode === $expectedCode);
    echo ($passed ? "[PASS] " : "[FAIL] ") . "Route {$route} returned {$httpCode} (expected {$expectedCode})\n";
    if (!$passed) {
        $allPassed = false;
    }
}

if ($allPassed) {
    echo "\nAll Web Routes Verified Successfully!\n";
    exit(0);
} else {
    echo "\nSome Web Routes Failed!\n";
    exit(1);
}
