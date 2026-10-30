<?php
/**
 * Record a payment against an invoice.
 */
$page_title = 'Record Payment';
require_once __DIR__ . '/../includes/header.php';
requireSchoolManagement();

$schoolId = (int)(currentSchoolId() ?? 0);
if ($schoolId <= 0) { header('Location: ../dashboard.php'); exit; }

$invoiceId = (int)($_GET['id'] ?? $_POST['invoice_id'] ?? 0);
if ($invoiceId <= 0) { header('Location: invoices.php'); exit; }

$stmt = $pdo->prepare("SELECT i.*, st.full_name, st.student_number FROM invoices i JOIN students st ON st.id = i.student_id WHERE i.id = ? AND i.school_id = ?");
$stmt->execute([$invoiceId, $schoolId]);
$invoice = $stmt->fetch();
if (!$invoice) { header('Location: invoices.php'); exit; }

$errors = [];
$old = ['amount' => $invoice['amount'], 'payment_date' => date('Y-m-d'), 'method' => 'cash', 'reference' => '', 'notes' => ''];

// already paid sum
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id = ? AND school_id = ?");
$stmt->execute([$invoiceId, $schoolId]);
$paidSoFar = (float)$stmt->fetchColumn();
$balance = (float)$invoice['amount'] - $paidSoFar;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form token.';
    } else {
        foreach (array_keys($old) as $k) { if (isset($_POST[$k])) $old[$k] = trim((string)$_POST[$k]); }
        if (!is_numeric($old['amount']) || (float)$old['amount'] <= 0) $errors[] = 'Amount must be positive.';
        if ($old['payment_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['payment_date'])) $errors[] = 'Valid date required.';
        if (empty($errors)) {
            recordPayment($pdo, $schoolId, $invoiceId, (float)$old['amount'], $old['payment_date'], $old['method'] !== '' ? $old['method'] : null, $old['reference'] !== '' ? $old['reference'] : null, (int)($_SESSION['user_id'] ?? 0), $old['notes'] !== '' ? $old['notes'] : null);
            setFlashMessage('success', 'Payment recorded.');
            header('Location: invoices.php');
            exit;
        }
    }
}
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1"><i class="fas fa-money-bill-wave me-2 text-primary"></i>Record Payment</h2>
        <p class="text-muted mb-0 small"><?php echo e($invoice['invoice_number']); ?> · <?php echo e($invoice['full_name']); ?> · Total <?php echo number_format((float)$invoice['amount'], 2); ?> · Balance <?php echo number_format($balance, 2); ?></p>
    </div>
    <a href="invoices.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?php echo e($e); ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm" style="max-width:600px;">
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="invoice_id" value="<?php echo $invoiceId; ?>">
            <div class="mb-3">
                <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="<?php echo e($old['amount']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Payment Date</label>
                <input type="date" name="payment_date" class="form-control" value="<?php echo e($old['payment_date']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Method</label>
                <select name="method" class="form-select">
                    <?php foreach (['cash' => 'Cash', 'bank' => 'Bank Transfer', 'card' => 'Card', 'online' => 'Online', 'cheque' => 'Cheque'] as $k => $v): ?>
                        <option value="<?php echo e($k); ?>" <?php echo $old['method'] === $k ? 'selected' : ''; ?>><?php echo e($v); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Reference</label>
                <input type="text" name="reference" class="form-control" value="<?php echo e($old['reference']); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Notes</label>
                <textarea name="notes" class="form-control" rows="2"><?php echo e($old['notes']); ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-check me-2"></i>Record Payment</button>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>