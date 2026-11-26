<?php
/**
 * Parent view of children's invoices.
 */
$page_title = 'Children Fees';
require_once __DIR__ . '/../includes/header.php';
requireParent();

$parentId = (int)($_SESSION['user_id'] ?? 0);
$schoolId = (int)(currentSchoolId() ?? 0);

$stmt = $pdo->prepare("SELECT s.id, s.full_name, s.student_number FROM parent_student ps JOIN students s ON s.id = ps.student_id WHERE ps.parent_id = ? AND ps.school_id = ?");
$params = [$parentId, $schoolId];
$stmt->execute($params);
$children = $stmt->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1"><i class="fas fa-file-invoice me-2 text-primary"></i>Children Fees</h2>
        <p class="text-muted mb-0 small">Invoices and payment status for your linked children.</p>
    </div>
</div>

<?php if (empty($children)): ?>
    <div class="alert alert-info">No children linked to your account.</div>
<?php else: ?>
    <?php foreach ($children as $child): ?>
        <?php
        $cid = (int)$child['id'];
        $cschool = $schoolId > 0 ? $schoolId : (int)($pdo->query("SELECT school_id FROM students WHERE id = $cid")->fetchColumn() ?: 0);
        $invoices = getInvoices($pdo, $cschool, $cid);
        $summary = getStudentFeeSummary($pdo, $cschool, $cid);
        ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div class="fw-bold"><i class="fas fa-user-graduate me-2 text-primary"></i><?php echo e($child['full_name']); ?></div>
                <span class="badge bg-<?php echo $summary['due_amt'] > 0 ? 'danger' : 'success'; ?>">Due: <?php echo number_format($summary['due_amt'], 2); ?></span>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-3"><div class="text-muted small">Total</div><div class="fw-bold"><?php echo (int)$summary['total']; ?></div></div>
                    <div class="col-6 col-md-3"><div class="text-muted small">Paid</div><div class="fw-bold text-success"><?php echo number_format($summary['paid_amt'], 2); ?></div></div>
                    <div class="col-6 col-md-3"><div class="text-muted small">Due</div><div class="fw-bold text-danger"><?php echo number_format($summary['due_amt'], 2); ?></div></div>
                    <div class="col-6 col-md-3"><div class="text-muted small">Open</div><div class="fw-bold"><?php echo (int)$summary['unpaid'] + (int)$summary['overdue']; ?></div></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Invoice #</th><th>Title</th><th class="text-end">Amount</th><th>Due</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">No invoices.</td></tr>
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
    <?php endforeach; ?>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>