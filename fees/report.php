<?php
/**
 * Fee collection report + CSV export.
 */
$page_title = 'Fee Report';
require_once __DIR__ . '/../includes/header.php';
requireSchoolManagement();

$schoolId = (int)(currentSchoolId() ?? 0);
if ($schoolId <= 0) { header('Location: ../dashboard.php'); exit; }

$from = trim((string)($_GET['from'] ?? date('Y-m-d', strtotime('-30 days'))));
$to = trim((string)($_GET['to'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = date('Y-m-d');

getFeeSummary($pdo, $schoolId);
$invoices = getInvoices($pdo, $schoolId);

// filter by date range on issued_at
$filtered = array_values(array_filter($invoices, function ($inv) use ($from, $to) {
    $d = substr((string)$inv['issued_at'], 0, 10);
    return $d >= $from && $d <= $to;
}));

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="fees_' . $from . '_to_' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Invoice #', 'Student', 'Title', 'Amount', 'Status', 'Due Date', 'Issued']);
    foreach ($filtered as $inv) {
        fputcsv($out, [$inv['invoice_number'], $inv['full_name'], $inv['title'], $inv['amount'], $inv['status'], $inv['due_date'], $inv['issued_at']]);
    }
    fclose($out);
    exit;
}
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1"><i class="fas fa-chart-bar me-2 text-primary"></i>Fee Report</h2>
        <p class="text-muted mb-0 small">Collection summary for the selected period.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="invoices.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Invoices</a>
        <a href="report.php?from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>&export=csv" class="btn btn-outline-success btn-sm"><i class="fas fa-file-csv me-1"></i>Export CSV</a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?php echo e($from); ?>">
            </div>
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?php echo e($to); ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary" type="submit"><i class="fas fa-filter me-1"></i>Apply</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Invoice #</th><th>Student</th><th>Title</th><th class="text-end">Amount</th><th>Status</th><th>Issued</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($filtered)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No invoices in this period.</td></tr>
                    <?php else: ?>
                        <?php foreach ($filtered as $inv): ?>
                            <tr>
                                <td class="fw-semibold small"><?php echo e($inv['invoice_number']); ?></td>
                                <td><?php echo e($inv['full_name']); ?></td>
                                <td class="small"><?php echo e($inv['title']); ?></td>
                                <td class="text-end"><?php echo number_format((float)$inv['amount'], 2); ?></td>
                                <td>
                                    <?php
                                    $badge = ['unpaid' => 'warning', 'paid' => 'success', 'overdue' => 'danger', 'cancelled' => 'secondary'];
                                    $cls = $badge[$inv['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $cls; ?> bg-opacity-75"><?php echo e(ucfirst($inv['status'])); ?></span>
                                </td>
                                <td class="text-muted small"><?php echo e(substr((string)$inv['issued_at'], 0, 10)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>