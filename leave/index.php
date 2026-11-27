<?php
/**
 * Leave Requests — list + filters
 * Staff see school-wide requests; students/parents see their own (or their children's).
 */
$page_title = 'Leave Requests';
require_once '../includes/header.php';
requireAuth();

$schoolId = currentSchoolId();
$userId = (int)$_SESSION['user_id'];
$role = getUserRole();

// ---- Scope: one school, and parents only see leave filed by a linked child ----
$where = [];
$params = [];
if ($schoolId) {
    $where[] = 'lr.school_id = ?';
    $params[] = $schoolId;
} else {
    $where[] = '1=0';
}
if ($role === 'parent') {
    $where[] = 'EXISTS (
        SELECT 1 FROM parent_student ps
        JOIN students st ON st.id = ps.student_id AND st.school_id = ps.school_id
        WHERE st.user_id = lr.user_id AND ps.parent_id = ? AND ps.school_id = lr.school_id
    )';
    $params[] = $userId;
}
if ($role === 'student') {
    $where[] = 'lr.user_id = ?';
    $params[] = $userId;
}

// ---- Filters ----
$status = trim($_GET['status'] ?? '');
$userFilter = (isset($_GET['user_id']) && $_GET['user_id'] !== '') ? (int)$_GET['user_id'] : null;
$type = trim($_GET['type'] ?? '');
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');

if (!empty($status)) { $where[] = 'lr.status = ?'; $params[] = $status; }
if (!empty($type)) { $where[] = 'lr.leave_type = ?'; $params[] = $type; }
if (!empty($userFilter) && isSchoolManagement()) { $where[] = 'lr.user_id = ?'; $params[] = $userFilter; }
if (!empty($from)) { $where[] = 'lr.end_date >= ?'; $params[] = $from; }
if (!empty($to)) { $where[] = 'lr.start_date <= ?'; $params[] = $to; }

$whereClause = $where ? implode(' AND ', $where) : '1=1';

// ---- Count + pagination ----
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM leave_requests lr WHERE $whereClause");
$stmt->execute($params);
$total = (int)$stmt->fetch()['total'];
$pagination = getPagination($total, $perPage, $page);

// ---- Fetch ----
$sql = "SELECT lr.*, u.full_name AS user_name, u.email AS user_email, u.role AS user_role,
               a.full_name AS approver_name
        FROM leave_requests lr
        JOIN users u ON u.id = lr.user_id
        LEFT JOIN users a ON a.id = lr.approved_by
        WHERE $whereClause
        ORDER BY lr.created_at DESC
        LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
foreach ($params as $i => $p) {
    $stmt->bindValue($i + 1, is_int($p) ? $p : (string)$p, is_int($p) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$leaves = $stmt->fetchAll();

// ---- Users for filter (staff only) ----
$users = [];
if (isSchoolManagement()) {
    $uWhere = ['u.school_id = ?'];
    $uParams = [$schoolId];
    $stmt = $pdo->prepare("SELECT u.id, u.full_name, u.role FROM users u WHERE " . implode(' AND ', $uWhere) . " ORDER BY u.full_name ASC");
    $stmt->execute($uParams);
    $users = $stmt->fetchAll();
}
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1"><i class="fas fa-calendar-times me-2 text-primary"></i>Leave Requests</h2>
        <p class="text-muted mb-0 small">Manage staff and student leave applications.</p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($role !== 'student'): ?>
        <a href="create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> Apply for Leave</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="text-muted small">Total Requests</div>
                <div class="fw-bold fs-4"><?php echo $total; ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="text-muted small">Pending</div>
                <div class="fw-bold fs-4 text-warning"><?php echo (int)array_reduce($leaves, fn($c, $r) => $c + (($r['status'] ?? '') === 'Pending'), 0); ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="text-muted small">Approved</div>
                <div class="fw-bold fs-4 text-success"><?php echo (int)array_reduce($leaves, fn($c, $r) => $c + (($r['status'] ?? '') === 'Approved'), 0); ?></div>
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
                    <option value="Pending" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Approved" <?php echo $status === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="Rejected" <?php echo $status === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="Cancelled" <?php echo $status === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1">Type</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="Sick" <?php echo $type === 'Sick' ? 'selected' : ''; ?>>Sick</option>
                    <option value="Casual" <?php echo $type === 'Casual' ? 'selected' : ''; ?>>Casual</option>
                    <option value="Emergency" <?php echo $type === 'Emergency' ? 'selected' : ''; ?>>Emergency</option>
                    <option value="Maternity" <?php echo $type === 'Maternity' ? 'selected' : ''; ?>>Maternity</option>
                    <option value="Paternity" <?php echo $type === 'Paternity' ? 'selected' : ''; ?>>Paternity</option>
                    <option value="Other" <?php echo $type === 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <?php if (isSchoolManagement()): ?>
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1">User</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">All Users</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?php echo (int)$u['id']; ?>" <?php echo $userFilter === (int)$u['id'] ? 'selected' : ''; ?>>
                        <?php echo e($u['full_name']) . ' (' . e(tenant_role_label($u['role'])) . ')'; ?>
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
        <?php if (empty($leaves)): ?>
        <div class="text-center text-muted py-5 px-3">
            <i class="fas fa-calendar-times fa-2x mb-2 d-block opacity-50"></i>
            <h5>No leave requests found</h5>
            <p class="mb-0">No requests match your filters.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Staff / Student</th>
                        <th>Type</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Days</th>
                        <th class="text-end">Status</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leaves as $l): ?>
                    <?php
                    $start = new DateTime($l['start_date']);
                    $end = new DateTime($l['end_date']);
                    $days = (int)$start->diff($end)->days + 1;
                    $badge = ['Pending' => 'warning', 'Approved' => 'success', 'Rejected' => 'danger', 'Cancelled' => 'secondary'];
                    $cls = $badge[$l['status']] ?? 'secondary';
                    ?>
                    <tr>
                        <td class="fw-semibold small">#<?php echo (int)$l['id']; ?></td>
                        <td>
                            <div class="fw-medium small"><?php echo e($l['user_name']); ?></div>
                            <div class="text-muted small"><?php echo e(tenant_role_label($l['user_role'])); ?></div>
                        </td>
                        <td class="small"><?php echo e($l['leave_type']); ?></td>
                        <td class="text-muted small"><?php echo formatDate($l['start_date']); ?></td>
                        <td class="text-muted small"><?php echo formatDate($l['end_date']); ?></td>
                        <td class="small"><?php echo $days; ?></td>
                        <td class="text-end">
                            <span class="badge bg-<?php echo $cls; ?> bg-opacity-75"><?php echo e($l['status']); ?></span>
                        </td>
                        <td class="text-center">
                            <?php if ($l['status'] === 'Pending' && isSchoolManagement()): ?>
                            <div class="btn-group btn-group-sm" role="group">
                                <form method="post" action="approve.php" class="d-inline" onsubmit="return confirm('Approve this leave request?');">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int)$l['id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-outline-success" title="Approve"><i class="fas fa-check"></i></button>
                                </form>
                                <form method="post" action="approve.php" class="d-inline" onsubmit="return confirm('Reject this leave request?');">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int)$l['id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-outline-danger" title="Reject"><i class="fas fa-times"></i></button>
                                </form>
                            </div>
                            <?php elseif ($role === 'student' || $role === 'parent'): ?>
                            <a href="create.php" class="btn btn-sm btn-outline-secondary" title="New request"><i class="fas fa-plus"></i></a>
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
                <li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo urlencode($status); ?>&type=<?php echo urlencode($type); ?>&user_id=<?php echo urlencode((string)$userFilter); ?>&from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>">Previous</a></li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&type=<?php echo urlencode($type); ?>&user_id=<?php echo urlencode((string)$userFilter); ?>&from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($pagination['has_next']): ?>
                <li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo urlencode($status); ?>&type=<?php echo urlencode($type); ?>&user_id=<?php echo urlencode((string)$userFilter); ?>&from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>">Next</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
