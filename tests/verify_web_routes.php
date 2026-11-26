<?php
/**
 * Public and unauthenticated route smoke checks.
 * Run from CLI after starting the application web server.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$baseUrl = rtrim((string)(getenv('ACADEMICS_BASE') ?: 'http://127.0.0.1:8090'), '/');
$routes = [
    '/index.php' => 200,
    '/public_events.php' => 200,
    '/auth/login.php' => 200,
    '/auth/register.php' => 200,
    '/dashboard.php' => 302,
    '/errors/403.php' => 403,
    '/errors/404.php' => 404,
];

$allPassed = true;
foreach ($routes as $route => $expectedCode) {
    $ch = curl_init($baseUrl . $route);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HEADER => false,
    ]);
    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $hasPhpError = is_string($body)
        && preg_match('/Fatal error|Parse error|Uncaught (?:Error|Exception)|SQLSTATE/i', $body);
    $passed = $body !== false && $httpCode === $expectedCode && !$hasPhpError;
    echo ($passed ? "[PASS] " : "[FAIL] ")
        . "Route {$route} returned {$httpCode} (expected {$expectedCode})"
        . ($curlError !== '' ? " — {$curlError}" : '')
        . ($hasPhpError ? " — PHP/database error in response" : '')
        . PHP_EOL;
    if (!$passed) $allPassed = false;
}

if ($allPassed) {
    echo "\nAll public and unauthenticated routes verified successfully.\n";
    exit(0);
}
echo "\nOne or more public route checks failed.\n";
exit(1);
