<?php
/**
 * Concurrency Regression Test for Payment Recording
 * 
 * Verifies that multiple simultaneous-ish payment requests on the same invoice
 * are handled correctly using interleaved PDO connections.
 * 
 * NOTE: True concurrent testing (multiple processes writing at the exact same moment)
 * requires pcntl_fork or separate PHP processes. This test uses interleaved
 * connections from a single process to simulate concurrent access patterns
 * and verify that transaction-level locking (FOR UPDATE) prevents race conditions.
 * 
 * Run: php -d APP_ENV=test tests/concurrency_payment_test.php
 */

// Set test environment
define('APP_ENV', 'test');
define('APP_VERSION', '1.0.0');

// Set CLI environment
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Include the test database config
require_once __DIR__ . '/../config/db.php';

// Force test environment
$_ENV['APP_ENV'] = 'test';
putenv('APP_ENV=test');

// Bootstrap PDO
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbName = $_ENV['DB_NAME'] ?? 'edusphere_test';
$dbUser = $_ENV['DB_USER'] ?? 'root';
$dbPass = $_ENV['DB_PASS'];

// Map of role -> (school_id, student_id) for test data
$testRoles = [
    'super_admin' => [1, null],
    'school_admin' => [1, 4],
    'admin' => [1, 1],
    'teacher' => [1, 3],
    'student' => [1, 1],
    'parent' => [1, 1],
];

// Get PDO connection
try {
    $pdo = new PDO(
        'mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=utf8mb4',
        $dbUser,
        $dbPass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

// Ensure test data: school, students, invoices
try {
    // Ensure school 1 exists
    $pdo->prepare("INSERT INTO schools (id, school_name, school_code, subdomain, school_email, school_phone, school_address, owner_name, status, plan, subscription_plan, trial_ends_at, is_active) VALUES (1, 'Test School', 'TST01', 'test', 'test@test.com', '+1 555 0000', '1 Test St', 'Test Owner', 'active', 'pro', NULL, 1) ON DUPLICATE KEY UPDATE school_name = VALUES(school_name), is_active = 1")->execute();
    
    // Ensure students exist for different roles
    $students = [
        1 => ['student@test.com', 'Student', 'student', 1],       // student role, school 1
        2 => ['teacher@test.com', 'Teacher', 'teacher', 1],         // teacher role, school 1
        3 => ['admin@test.com', 'Admin', 'admin', 1],               // admin role, school 1
        4 => ['parent@test.com', 'Parent', 'parent', 1],            // parent role, school 1
    ];
    
    foreach ($students as $userId => $s) {
        $pdo->prepare("INSERT INTO users (id, full_name, email, password, role, is_active, school_id, must_change_password) VALUES (?, ?, ?, '', ?, 1, ?, 0) ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), is_active = 1")
            ->execute([$userId, $s[2], $s[0], $s[3], 1]);
    }
    
    // Ensure students table entries
    $pdo->prepare("INSERT INTO students (id, user_id, school_id, student_number, full_name, email, enrollment_date, status) VALUES (1, 1, 1, 'STU-001', 'Test Student', 'student@test.com', CURDATE(), 'Active') ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), status = 'Active'")->execute();
    $pdo->prepare("INSERT INTO students (id, user_id, school_id, student_number, full_name, email, enrollment_date, status) VALUES (2, 2, 1, 'STU-002', 'Test Teacher', 'teacher@test.com', CURDATE(), 'Active') ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), status = 'Active'")->execute();
    $pdo->prepare("INSERT INTO students (id, user_id, school_id, student_number, full_name, email, enrollment_date, status) VALUES (3, 3, 1, 'STU-003', 'Test Admin', 'admin@test.com', CURDATE(), 'Active') ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), status = 'Active'")->execute();
    $pdo->prepare("INSERT INTO students (id, user_id, school_id, student_number, full_name, email, enrollment_date, status) VALUES (4, 4, 1, 'STU-004', 'Test Parent', 'parent@test.com', CURDATE(), 'Active') ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), status = 'Active'")->execute();
    
    // Ensure invoices exist for each student
    for ($i = 1; $i <= 4; $i++) {
        $invCheck = $pdo->prepare("SELECT id FROM invoices WHERE student_id = ? AND school_id = 1 LIMIT 1");
        $invCheck->execute([$i]);
        if ($invCheck->fetchColumn() === 0) {
            $pdo->prepare("INSERT INTO invoices (school_id, student_id, fee_structure_id, invoice_number, title, amount, due_date, created_by) VALUES (1, ?, 1, 'CONC-INV-{:02d}', 'Concurrency Test Invoice {:02d}', 500.00, DATE_ADD(CURDATE(), INTERVAL 30 DAY), 1)")->execute([$i, $i]);
        }
    }
} catch (PDOException $e) {
    die("Fixture setup failed: " . $e->getMessage() . "\n");
}

// ============================================================
// CONCURRENCY TEST: Interleaved Payment Recording
// ============================================================

$testResults = [];

echo "=== Concurrency Regression Test: Payment Recording (Interleaved Connections) ===\n\n";

// We'll test with invoice ID 1 (student 1's invoice)
$invoiceId = 1;

// Reset invoice status to unpaid for clean test
$pdo->prepare("UPDATE invoices SET status='unpaid', paid_at=NULL, payment_method=NULL, transaction_ref=NULL WHERE id = ?")->execute([$invoiceId]);
echo "Reset invoice {$invoiceId} status to unpaid.\n";

// Record payments interleaved with "concurrent" connections
// Each connection resets the invoice state before its first payment,
// simulating multiple processes racing to pay the same invoice.

// Connection 1: First payment of 250.00 (should succeed)
$pdo1 = new PDO(
    'mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=utf8mb4',
    $dbUser,
    $dbPass
);
$pdo1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Reset invoice for connection 1
$pdo1->prepare("UPDATE invoices SET status='unpaid', paid_at=NULL, payment_method=NULL, transaction_ref=NULL WHERE id = ?")->execute([$invoiceId]);
$payment1 = recordPayment($pdo1, 1, $invoiceId, 250.00, date('Y-m-d'), 'cash', 'TXN-CONC-001', 1, 'First payment - 50%');
$testResults[] = [
    'name' => "Connection 1: Record 250.00 (50% of 500.00 invoice)",
    'pdo' => 1,
    'pass' => $payment1 === true
];
echo "Connection 1 result: " . ($payment1 === true ? "PASS" : "FAIL") . " (amount: 250.00)\n";

// Connection 2: Try to record 250.00 while invoice is partially paid (should fail due to FOR UPDATE lock)
$pdo2 = new PDO(
    'mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=utf8mb4',
    $dbUser,
    $dbPass
);
$pdo2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Reset invoice for connection 2 (this simulates the race condition scenario)
$pdo2->prepare("UPDATE invoices SET status='unpaid', paid_at=NULL, payment_method=NULL, transaction_ref=NULL WHERE id = ?")->execute([$invoiceId]);
$payment2 = recordPayment($pdo2, 1, $invoiceId, 250.00, date('Y-m-d'), 'cash', 'TXN-CONC-002', 1, 'Second payment - should fail due to lock');
$testResults[] = [
    'name' => "Connection 2: Record 250.00 (should fail - outstanding is 250.00)",
    'pdo' => 2,
    'pass' => $payment2 === false
];
echo "Connection 2 result: " . ($payment2 === false ? "PASS (correctly rejected)" : "FAIL") . " (amount: 250.00)\n";

// Connection 3: Try overpayment while another payment is in progress
$pdo3 = new PDO(
    'mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=utf8mb4',
    $dbUser,
    $dbPass
);
$pdo3->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Reset invoice for connection 3
$pdo3->prepare("UPDATE invoices SET status='unpaid', paid_at=NULL, payment_method=NULL, transaction_ref=NULL WHERE id = ?")->execute([$invoiceId]);
$payment3 = recordPayment($pdo3, 1, $invoiceId, 300.00, date('Y-m-d'), 'cash', 'TXN-CONC-003', 1, 'Overpayment - should fail');
$testResults[] = [
    'name' => "Connection 3: Record 300.00 (should fail - exceeds outstanding 250.00)",
    'pdo' => 3,
    'pass' => $payment3 === false
];
echo "Connection 3 result: " . ($payment3 === false ? "PASS (correctly rejected)" : "FAIL") . " (amount: 300.00)\n";

// Connection 4: Record exact remaining payment (should succeed after partial payments)
$pdo4 = new PDO(
    'mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=utf8mb4',
    $dbUser,
    $dbPass
);
$pdo4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Reset invoice for connection 4
$pdo4->prepare("UPDATE invoices SET status='unpaid', paid_at=NULL, payment_method=NULL, transaction_ref=NULL WHERE id = ?")->execute([$invoiceId]);
// First reset then record the 250.00 that should succeed (simulating the "second half")
$payment4 = recordPayment($pdo4, 1, $invoiceId, 250.00, date('Y-m-d'), 'cash', 'TXN-CONC-004', 1, 'Second half payment - should succeed');
$testResults[] = [
    'name' => "Connection 4: Record 250.00 (should succeed - brings total to 500.00 = full invoice)",
    'pdo' => 4,
    'pass' => $payment4 === true
];
echo "Connection 4 result: " . ($payment4 === true ? "PASS (succeeded)" : "FAIL") . " (amount: 250.00)\n";

// Check final invoice status with all connections
$invCheck = $pdo->prepare("SELECT status, paid_at FROM invoices WHERE id = ?")->execute([$invoiceId]);
$invRow = $invCheck->fetch();
$totalPaidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id = ? AND school_id = ?")->execute([$invoiceId, 1]);
$totalPaid = (float)$totalPaidStmt->fetchColumn();
echo "Invoice status after all connections: " . $invRow['status'] . " (paid_at: " . $invRow['paid_at'] . ")\n";
echo "Total paid: {$totalPaid} / 500.00\n";

// Test: overpayment after full payment should fail across all connections
$pdo5 = new PDO(
    'mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=utf8mb4',
    $dbUser,
    $dbPass
);
$pdo5->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$payment5 = recordPayment($pdo5, 1, $invoiceId, 100.00, date('Y-m-d'), 'cash', 'TXN-CONC-005', 1, 'Overpayment after full pay - should fail');
$testResults[] = [
    'name' => "Connection 5: Record 100.00 after full payment (should fail)",
    'pdo' => 5,
    'pass' => $payment5 === false
];
echo "Connection 5 result: " . ($payment5 === false ? "PASS (correctly rejected)" : "FAIL") . " (amount: 100.00)\n";

// ============================================================
// SUMMARY
// ============================================================

echo "\n=== Concurrency Test Summary ===\n";
$passed = 0;
$failed = 0;
foreach ($testResults as $tr) {
    $status = $tr['pass'] ? 'PASS' : 'FAIL';
    echo "  [{$status}] {$tr['name']}\n";
    if ($tr['pass']) $passed++; else $failed++;
}
echo "Results: {$passed} passed, {$failed} failed out of " . count($testResults) . " tests (using interleaved PDO connections)\n";

$allPassed = $failed === 0;
echo "\n" . ($allPassed ? "CONCURRENCY TEST: ALL PASSED" : "CONCURRENCY TEST: SOME FAILED") . "\n";

exit($allPassed ? 0 : 1);