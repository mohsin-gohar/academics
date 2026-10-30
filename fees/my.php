<?php
/**
 * Student's own invoices.
 */
$page_title = 'My Fees';
require_once __DIR__ . '/../includes/header.php';
requireAuth();
if (!isStudent()) { header('Location: ../dashboard.php'); exit; }

$schoolId = (int)(currentSchoolId() ?? 0);
$studentId = (int)($_SESSION['student_id'] ?? 0);
if ($schoolId <= 0 || $studentId <= 0) {
    $stmt = $pdo->prepare("SELECT id, school_id FROM students WHERE user_id = ? LIMIT 1");
    $stmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
    $row = $stmt->fetch();
    if ($row) { $studentId = (int)$row['id']; $schoolId = (int)$row['school_id']; $_SESSION['student_id'] = $studentId; }
}
if ($schoolId <= 0 || $studentId <= 0) { header('Location: ../dashboard.php'); exit; }

getFeeSummary($pdo, $schoolId);
$invoices = getInvoices($pdo, $schoolId, $studentId);
$summary = getStudentFeeSummary($pdo, $schoolId, $studentId);
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1"><i class="fas fa-file-invoice me-2 text-primary"></i>My Fees</h2>
        <p class="text-muted mb-0 small">Your invoices and payment status.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="text-muted small">Total</div><div class="fw-bold fs-4"><?php echo (int)$summary['total']; ?></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="text-muted small">Paid</div><div class="fw-bold fs-4 text-success"><?php echo number_format($summary['paid_amt'], 2); ?></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="text-muted small">Due</div><div class="fw-bold fs-4 text-danger"><?php echo number_format($summary['due_amt'], 2); ?></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="text-muted small">Invoices</div><div class="fw-bold fs-4"><?php echo (int)$summary['unpaid'] + (int)$summary['overdue']; ?> open</div></div></div></div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Invoice #</th><th>Title</th><th class="text-end">Amount</th><th>Due Date</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No invoices yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td class="fw-semibold small"><?php echo e($inv['invoice_number']); ?></td>
                                <td><?php echo e($inv['title']); ?></td>
                                <td class="text-end"><?php echo number_format((float)$inv['amount'], 2); ?></td>
                                <td class="text-muted small"><?php echo $inv['due_date'] ? e(formatDate($inv['due_date'])) : '—'; ?></td>
                                <td>
                                    <?php
                                    $badge = ['unpaid' => 'warning', 'paid' => 'success', 'overdue' => 'danger', 'cancelled' => 'secondary'];
                                    $cls = $badge[$inv['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $cls; ?> bg-opacity-75"><?php echo e(ucfirst($inv['status'])); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>