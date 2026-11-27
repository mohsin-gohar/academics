<?php
/**
 * One payment attempt in its own process. Exit 0 on success, 1 on rejection.
 * Usage: php tests/pay_worker.php <invoiceId> <amount> <schoolId>
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

$invoiceId = (int)($argv[1] ?? 0);
$amount = (float)($argv[2] ?? 0);
$schoolId = (int)($argv[3] ?? 0);

$ok = recordPayment(
    $pdo,
    $schoolId,
    $invoiceId,
    $amount,
    date('Y-m-d'),
    'cash',
    'CONC-' . getmypid(),
    null,
    'parallel payment worker'
);

exit($ok ? 0 : 1);
