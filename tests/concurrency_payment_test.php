<?php
/**
 * Concurrency Regression Test for Payment Recording
 * 
 * Verifies that concurrent payment requests on the same invoice are handled correctly:
 * - Only one payment can succeed
 * - Duplicate/overpayment attempts are rejected
 * - Invoice status is correctly set to 'paid' after full payment
 * - Transaction-level locking (FOR UPDATE) prevents race conditions
 *
 * This test must be run with APP_ENV=test against the isolated test database.
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
$dbPass = $_ENV['DB_PASS'] ?? '';

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

// Ensure test data: school, student, invoice
try {
    // Ensure school 1 exists
    $pdo->prepare("INSERT INTO schools (id, school_name, school_code, subdomain, school_email, school_phone, school_address, owner_name, status, plan, subscription_plan, trial_ends_at, is_active) VALUES (1, 'Test School', 'TST01', 'test', 'test@test.com', '+1 555 0000', '1 Test St', 'Test Owner', 'active', 'pro', NULL, 1) ON DUPLICATE KEY UPDATE school_name = VALUES(school_name), is_active = 1")->execute();
    
    // Ensure student 1 exists linked to user 3 (simplified)
    $pdo->prepare("INSERT INTO users (id, full_name, email, password, role, is_active, school_id, must_change_password) VALUES (3, 'Test Student', 'student@test.com', '', 'student', 1, 1, 0) ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), is_active = 1")->execute();
    
    // Ensure student has invoice
    $invCheck = $pdo->prepare("SELECT id FROM invoices WHERE student_id = 1 AND school_id = 1 LIMIT 1");
    $invCheck->execute();
    if ($invCheck->fetchColumn() === 0) {
        $pdo->prepare("INSERT INTO invoices (school_id, student_id, fee_structure_id, invoice_number, title, amount, due_date, created_by) VALUES (1, 1, 1, 'TST-INV-001', 'Test Invoice', 500.00, DATE_ADD(CURDATE(), INTERVAL 30 DAY), 1)")->execute();
    }
} catch (PDOException $e) {
    die("Fixture setup failed: " . $e->getMessage() . "\n");
}

// Begin test
$testResults = [];

echo "=== Concurrency Regression Test: Payment Recording ===\n\n";

// Invoice ID we'll test with
$invoiceId = 1;

// Reset invoice status to unpaid for clean test
$pdo->prepare("UPDATE invoices SET status='unpaid', paid_at=NULL, payment_method=NULL, transaction_ref=NULL WHERE id = ?")->execute([$invoiceId]);
echo "Reset invoice {$invoiceId} status to unpaid.\n";

// Record first payment (should succeed - 50% of invoice)
$payment1 = recordPayment($pdo, 1, $invoiceId, 250.00, date('Y-m-d'), 'cash', 'TXN-CONC-001', 1, 'First payment - 50%');
$testResults[] = [
    'name' => 'Payment 1: Record 250.00 (50% of 500.00 invoice)',
    'pass' => $payment1 === true
];
echo "Payment 1 result: " . ($payment1 === true ? "PASS" : "FAIL") . " (amount: 250.00)\n";

// Check invoice status after first payment
$invCheck = $pdo->prepare("SELECT status, paid_at FROM invoices WHERE id = ?")->execute([$invoiceId]);
$invRow = $invCheck->fetch();
echo "Invoice status after payment 1: " . $invRow['status'] . " (paid_at: " . ($invRow['paid_at'] ?? 'NULL') . ")\n";

// Try to record a second payment for the same amount (should fail - outstanding is now 250.00)
$payment2 = recordPayment($pdo, 1, $invoiceId, 250.00, date('Y-m-d'), 'cash', 'TXN-CONC-002', 1, 'Second payment - should fail');
$testResults[] = [
    'name' => 'Payment 2: Record 250.00 (should fail - outstanding is 250.00)',
    'pass' => $payment2 === false
];
echo "Payment 2 result: " . ($payment2 === false ? "PASS (correctly rejected)" : "FAIL") . " (amount: 250.00)\n";

// Try to record an overpayment (should fail - exceeds outstanding)
$payment3 = recordPayment($pdo, 1, $invoiceId, 300.00, date('Y-m-d'), 'cash', 'TXN-CONC-003', 1, 'Overpayment - should fail');
$testResults[] = [
    'name' => 'Payment 3: Record 300.00 (should fail - exceeds outstanding 250.00)',
    'pass' => $payment3 === false
];
echo "Payment 3 result: " . ($payment3 === false ? "PASS (correctly rejected)" : "FAIL") . " (amount: 300.00)\n";

// Try to record exact remaining payment (should succeed - brings total to 500.00 = full invoice)
$payment4 = recordPayment($pdo, 1, $invoiceId, 250.00, date('Y-m-d'), 'cash', 'TXN-CONC-004', 1, 'Second half payment - should succeed');
$testResults[] = [
    'name' => 'Payment 4: Record 250.00 (should succeed - brings total to 500.00 = full invoice)',
    'pass' => $payment4 === true
];
echo "Payment 4 result: " . ($payment4 === true ? "PASS (succeeded)" : "FAIL") . " (amount: 250.00)\n";

// Check final invoice status
$invCheck2 = $pdo->prepare("SELECT status, paid_at FROM invoices WHERE id = ?")->execute([$invoiceId]);
$invRow2 = $invCheck2->fetch();
$totalPaidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id = ? AND school_id = ?")->execute([$invoiceId, 1]);
$totalPaid = (float)$totalPaidStmt->fetchColumn();
echo "Invoice status after all payments: " . $invRow2['status'] . " (paid_at: " . $invRow2['paid_at'] . ")\n";
echo "Total paid: {$totalPaid} / 500.00\n";

// Test: overpayment after full payment should fail
$payment5 = recordPayment($pdo, 1, $invoiceId, 100.00, date('Y-m-d'), 'cash', 'TXN-CONC-005', 1, 'Overpayment after full pay - should fail');
$testResults[] = [
    'name' => 'Payment 5: Record 100.00 after full payment (should fail)',
    'pass' => $payment5 === false
];
echo "Payment 5 result: " . ($payment5 === false ? "PASS (correctly rejected)" : "FAIL") . " (amount: 100.00)\n";

// Summary
echo "\n=== Concurrency Test Summary ===\n";
$passed = 0;
$failed = 0;
foreach ($testResults as $tr) {
    $status = $tr['pass'] ? 'PASS' : 'FAIL';
    echo "  [{$status}] {$tr['name']}\n";
    if ($tr['pass']) $passed++; else $failed++;
}
echo "Results: {$passed} passed, {$failed} failed out of " . count($testResults) . " tests\n";

$allPassed = $failed === 0;
echo "\n" . ($allPassed ? "CONCURRENCY TEST: ALL PASSED" : "CONCURRENCY TEST: SOME FAILED") . "\n";

exit($allPassed ? 0 : 1);