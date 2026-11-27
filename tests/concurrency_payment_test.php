<?php
/**
 * Payment balance test with two real PHP processes.
 *
 * Both processes try to pay 400 against a 500 invoice at the same time.
 * The row lock in recordPayment must leave exactly one success and a paid
 * total that never exceeds the invoice.
 *
 * Run: APP_ENV=test DB_HOST=127.0.0.1 DB_PASS=root php tests/concurrency_payment_test.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

$failed = 0;
$passed = 0;

function check(string $name, bool $ok): void
{
    global $failed, $passed;
    if ($ok) {
        $passed++;
        echo "[PASS] {$name}\n";
        return;
    }
    $failed++;
    echo "[FAIL] {$name}\n";
}

$student = $pdo->query("SELECT id, school_id FROM students WHERE school_id IS NOT NULL ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$student) {
    fwrite(STDERR, "No student fixture found. Run tests/run_tests.php first.\n");
    exit(1);
}

$schoolId = (int)$student['school_id'];
$studentId = (int)$student['id'];
$number = 'CONC-' . bin2hex(random_bytes(4));
$pdo->prepare(
    "INSERT INTO invoices (school_id, student_id, invoice_number, title, amount, due_date, status)
     VALUES (?, ?, ?, 'Concurrency probe', 500.00, CURDATE(), 'unpaid')"
)->execute([$schoolId, $studentId, $number]);
$invoiceId = (int)$pdo->lastInsertId();

$codes = [];
$processes = [];
try {
    foreach ([400.00, 400.00] as $amount) {
        $cmd = escapeshellarg(PHP_BINARY)
            . ' ' . escapeshellarg(__DIR__ . '/pay_worker.php')
            . ' ' . $invoiceId
            . ' ' . $amount
            . ' ' . $schoolId;
        $processes[] = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        if (is_resource($processes[array_key_last($processes)])) {
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
        }
    }

    foreach ($processes as $process) {
        if (!is_resource($process)) {
            $codes[] = 99;
            continue;
        }
        $codes[] = proc_close($process);
    }

    $successes = count(array_filter($codes, static fn(int $code): bool => $code === 0));
    check('two parallel 400 payments produce exactly one success', $successes === 1);

    $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id = ? AND school_id = ?");
    $paidStmt->execute([$invoiceId, $schoolId]);
    $paid = round((float)$paidStmt->fetchColumn(), 2);
    check('recorded total does not exceed 500', $paid > 0 && $paid <= 500.00);

    $overpay = recordPayment($pdo, $schoolId, $invoiceId, 400.00, date('Y-m-d'), 'cash', 'CONC-OVER', null, 'must fail');
    check('a later overpayment is rejected', $overpay === false);

    $paidStmt->execute([$invoiceId, $schoolId]);
    $paidAfter = round((float)$paidStmt->fetchColumn(), 2);
    check('overpayment did not change the total', abs($paidAfter - $paid) < 0.001);
} finally {
    $pdo->prepare("DELETE FROM payments WHERE invoice_id = ?")->execute([$invoiceId]);
    $pdo->prepare("DELETE FROM invoices WHERE id = ?")->execute([$invoiceId]);
}

echo "\nPassed: {$passed}  Failed: {$failed}\n";
exit($failed === 0 ? 0 : 1);
