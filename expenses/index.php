<?php
/**
 * Expenses — list + filters
 * School management sees school-wide expenses; others see their own entries.
 */
$page_title = 'Expenses';
require_once '../includes/header.php';
requireAuth();

$schoolId = currentSchoolId();
$userId = (int)$_SESSION['user_id'];
$role = getUserRole();

// ---- Scope ----
$where = [];
$params = [];
if (!isSuperAdmin()) {
    $where[] = 'e.school_id = ?';
    $params[] = $schoolId;
}
if ($role !== 'admin' && $role !== 'school_admin' && $role !== 'super_admin') {
    $where[] = 'e.created_by = ?';
    $params[] = $userId;
}

// ---- Filters ----
$status = trim($_GET['status'] ?? '');
$category = trim($_GET['category'] ?? '');
$userFilter = (isset($_GET['user_id']) && $_GET['user_id'] !== '') ? (int)$_GET['user_id'] : null;
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');

if (!in_array($status, ['', 'pending', 'approved', 'rejected', 'paid'], true)) $status = '';
if (!in_array($category, ['', 'Utilities', 'Supplies', 'Maintenance', 'Travel', 'Salaries', 'Other'], true)) $category = '';
if ($from !== '' && !isValidIsoDate($from)) $from = '';
if ($to !== '' && !isValidIsoDate($to)) $to = '';
if ($from !== '' && $to !== '' && $from > $to) { [$from, $to] = [$to, $from]; }

if (!empty($status)) { $where[] = 'e.status = ?'; $params[] = $status; }
if (!empty($category)) { $where[] = 'e.category = ?'; $params[] = $category; }
if (!empty($userFilter) && isSchoolManagement()) { $where[] = 'e.created_by = ?'; $params[] = $userFilter; }
if (!empty($from)) { $where[] = 'e.expense_date >= ?'; $params[] = $from; }
if (!empty($to)) { $where[] = 'e.expense_date <= ?'; $params[] = $to; }

$whereClause = $where ? implode(' AND ', $where) : '1=1';

// ---- Count + pagination ----
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM expenses e WHERE $whereClause");
$stmt->execute($params);
$total = (int)$stmt->fetch()['total'];
$pagination = getPagination($total, $perPage, $page);
$page = (int)$pagination['current_page'];
$offset = (int)$pagination['offset'];

$summaryStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(e.amount), 0) AS total_amount,
            COALESCE(SUM(e.status = 'pending'), 0) AS pending_count,
            COALESCE(SUM(e.status = 'approved'), 0) AS approved_count
     FROM expenses e WHERE $whereClause"
);
$summaryStmt->execute($params);
$expenseSummary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'total_amount' => 0, 'pending_count' => 0, 'approved_count' => 0
];

// ---- Fetch ----
$sql = "SELECT e.*, u.full_name AS creator_name, a.full_name AS approver_name
        FROM expenses e
        LEFT JOIN users u ON u.id = e.created_by
        LEFT JOIN users a ON a.id = e.approved_by
        WHERE $whereClause
        ORDER BY e.expense_date DESC, e.created_at DESC
        LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
foreach ($params as $i => $p) {
    $stmt->bindValue($i + 1, is_int($p) ? $p : (string)$p, is_int($p) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$expenses = $stmt->fetchAll();

// ---- Users for filter (management) ----
$users = [];
if (isSuperAdmin()) {
    $users = $pdo->query(
        "SELECT u.id, u.full_name, u.role, s.school_name
         FROM users u JOIN schools s ON s.id = u.school_id
         ORDER BY s.school_name, u.full_name"
    )->fetchAll(PDO::FETCH_ASSOC);
} elseif (isSchoolManagement() && $schoolId) {
    $stmt = $pdo->prepare("SELECT id, full_name, role FROM users WHERE school_id = ? ORDER BY full_name ASC");
    $stmt->execute([$schoolId]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$totalAmount = (float)$expenseSummary['total_amount'];
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1"><i class="fas fa-money-bill-wave me-2 text-primary"></i>Expenses</h2>
        <p class="text-muted mb-0 small">Track and approve school expenses.</p>
    </div>
    <div class="d-flex gap-2">
        <?php if (isSchoolManagement()): ?>
        <a href="create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> Add Expense</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="text-muted small">Total Expenses</div>
                <div class="fw-bold fs-4"><?php echo number_format($totalAmount, 2); ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="text-muted small">Pending</div>
                <div class="fw-bold fs-4 text-warning"><?php echo (int)$expenseSummary['pending_count']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="text-muted small">Approved</div>
                <div class="fw-bold fs-4 text-success"><?php echo (int)$expenseSummary['approved_count']; ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1">Category</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="Utilities" <?php echo $category === 'Utilities' ? 'selected' : ''; ?>>Utilities</option>
                    <option value="Supplies" <?php echo $category === 'Supplies' ? 'selected' : ''; ?>>Supplies</option>
                    <option value="Maintenance" <?php echo $category === 'Maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                    <option value="Travel" <?php echo $category === 'Travel' ? 'selected' : ''; ?>>Travel</option>
                    <option value="Salaries" <?php echo $category === 'Salaries' ? 'selected' : ''; ?>>Salaries</option>
                    <option value="Other" <?php echo $category === 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <?php if (isSchoolManagement()): ?>
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1">Created By</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">All Users</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?php echo (int)$u['id']; ?>" <?php echo $userFilter === (int)$u['id'] ? 'selected' : ''; ?>>
                        <?php echo e($u['full_name']) . ' (' . e(tenant_role_label($u['role'])) . ')' . (!empty($u['school_name']) ? ' — ' . e($u['school_name']) : ''); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?php echo e($from); ?>">
            </div>
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?php echo e($to); ?>">
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-sm btn-primary" type="submit"><i class="fas fa-filter me-1"></i>Apply</button>
                <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($expenses)): ?>
        <div class="text-center text-muted py-5 px-3">
            <i class="fas fa-money-bill-wave fa-2x mb-2 d-block opacity-50"></i>
            <h5>No expenses found</h5>
            <p class="mb-0">No expenses match your filters.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th class="text-end">Amount</th>
                        <th>Date</th>
                        <th>Category</th>
                        <th class="text-end">Status</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $e): ?>
                    <?php
                    $badge = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'paid' => 'secondary'];
                    $cls = $badge[$e['status']] ?? 'secondary';
                    ?>
                    <tr>
                        <td class="fw-semibold small">#<?php echo (int)$e['id']; ?></td>
                        <td>
                            <div class="fw-medium small"><?php echo e($e['title']); ?></div>
                            <div class="text-muted small"><?php echo e($e['creator_name']); ?></div>
                        </td>
                        <td class="text-end"><?php echo number_format((float)$e['amount'], 2); ?></td>
                        <td class="text-muted small"><?php echo formatDate($e['expense_date']); ?></td>
                        <td class="small"><?php echo e($e['category']); ?></td>
                        <td class="text-end">
                            <span class="badge bg-<?php echo $cls; ?> bg-opacity-75"><?php echo e(ucfirst($e['status'])); ?></span>
                        </td>
                        <td class="text-center">
                            <?php if ($e['status'] === 'pending' && isAdmin()): ?>
                            <div class="btn-group btn-group-sm" role="group">
                                <form method="post" action="approve.php" class="d-inline" onsubmit="return confirm('Approve this expense?');">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int)$e['id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-outline-success" title="Approve"><i class="fas fa-check"></i></button>
                                </form>
                                <form method="post" action="approve.php" class="d-inline" onsubmit="return confirm('Reject this expense?');">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int)$e['id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-outline-danger" title="Reject"><i class="fas fa-times"></i></button>
                                </form>
                            </div>
                            <?php endif; ?>
                            <?php if ($e['status'] === 'approved' && isAdmin()): ?>
                            <form method="post" action="approve.php" class="d-inline" onsubmit="return confirm('Mark this approved expense as paid?');">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="id" value="<?php echo (int)$e['id']; ?>">
                                <input type="hidden" name="action" value="pay">
                                <button type="submit" class="btn btn-sm btn-outline-primary" title="Mark as paid"><i class="fas fa-money-check-dollar"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer">
        <nav>
            <ul class="pagination pagination-sm mb-0 justify-content-center">
                <?php if ($pagination['has_prev']): ?>
                <li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>&user_id=<?php echo urlencode((string)$userFilter); ?>&from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>">Previous</a></li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>&user_id=<?php echo urlencode((string)$userFilter); ?>&from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($pagination['has_next']): ?>
                <li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>&user_id=<?php echo urlencode((string)$userFilter); ?>&from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>">Next</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
