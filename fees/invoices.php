<?php
/**
 * Invoices list with filters.
 */
$page_title = 'Invoices';
require_once __DIR__ . '/../includes/header.php';
requireSchoolManagement();

$schoolId = (int)(currentSchoolId() ?? 0);
if ($schoolId <= 0) { header('Location: ../dashboard.php'); exit; }

$status = trim((string)($_GET['status'] ?? ''));
$studentId = isset($_GET['student_id']) && $_GET['student_id'] !== '' ? (int)$_GET['student_id'] : null;

// mark overdue
getFeeSummary($pdo, $schoolId);

$invoices = getInvoices($pdo, $schoolId, $studentId, $status !== '' ? $status : null);

$students = [];
$stmt = $pdo->prepare("SELECT id, full_name FROM students WHERE school_id = ? ORDER BY full_name ASC");
$stmt->execute([$schoolId]);
$students = $stmt->fetchAll();

$summary = getFeeSummary($pdo, $schoolId);
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1"><i class="fas fa-file-invoice me-2 text-primary"></i>Invoices</h2>
        <p class="text-muted mb-0 small">Track and collect student fees.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="structures.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-cog me-1"></i>Structures</a>
        <a href="create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>New Invoice</a>
        <a href="report.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-chart-bar me-1"></i>Report</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['label' => 'Unpaid', 'key' => 'unpaid', 'amt' => 'unpaid_amt', 'color' => 'warning'],
        ['label' => 'Overdue', 'key' => 'overdue', 'amt' => 'overdue_amt', 'color' => 'danger'],
        ['label' => 'Paid', 'key' => 'paid', 'amt' => 'paid_amt', 'color' => 'success'],
    ];
    foreach ($cards as $c): ?>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-<?php echo $c['color']; ?> bg-opacity-10 text-<?php echo $c['color']; ?> d-flex align-items-center justify-content-center" style="width:42px;height:42px;">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div>
                        <div class="text-muted small"><?php echo $c['label']; ?> (<?php echo (int)$summary[$c['key']]; ?>)</div>
                        <div class="fw-bold fs-5"><?php echo number_format((float)$summary[$c['amt']], 2); ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach (getInvoiceStatuses() as $k => $v): ?>
                        <option value="<?php echo e($k); ?>" <?php echo $status === $k ? 'selected' : ''; ?>><?php echo e($v); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1">Student</label>
                <select name="student_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($students as $st): ?>
                        <option value="<?php echo (int)$st['id']; ?>" <?php echo $studentId === (int)$st['id'] ? 'selected' : ''; ?>><?php echo e($st['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary" type="submit"><i class="fas fa-filter me-1"></i>Apply</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($invoices)): ?>
            <div class="text-center text-muted py-5 px-3">
                <i class="fas fa-file-invoice fa-2x mb-2 d-block opacity-50"></i>
                No invoices found.
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice #</th>
                        <th>Student</th>
                        <th>Title</th>
                        <th class="text-end">Amount</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td class="fw-semibold small"><?php echo e($inv['invoice_number']); ?></td>
                            <td><?php echo e($inv['full_name']); ?></td>
                            <td class="small"><?php echo e($inv['title']); ?></td>
                            <td class="text-end"><?php echo number_format((float)$inv['amount'], 2); ?></td>
                            <td class="text-muted small"><?php echo $inv['due_date'] ? e(formatDate($inv['due_date'])) : '—'; ?></td>
                            <td>
                                <?php
                                $badge = ['unpaid' => 'warning', 'paid' => 'success', 'overdue' => 'danger', 'cancelled' => 'secondary'];
                                $cls = $badge[$inv['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $cls; ?> bg-opacity-75"><?php echo e(ucfirst($inv['status'])); ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ($inv['status'] !== 'paid' && $inv['status'] !== 'cancelled'): ?>
                                    <a href="pay.php?id=<?php echo (int)$inv['id']; ?>" class="btn btn-sm btn-outline-success" title="Record payment"><i class="fas fa-money-bill"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>