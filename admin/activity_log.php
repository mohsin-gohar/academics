<?php
$page_title = 'Activity Log';
require_once '../includes/header.php';
requireAdmin();

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;
$action = trim($_GET['action'] ?? '');

$where = ['1=1'];
$params = [];
if (!empty($action)) {
    $where[] = 'al.action = ?';
    $params[] = $action;
}
$whereClause = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM activity_logs al WHERE $whereClause");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$pagination = getPagination($total, $perPage, $page);

$stmt = $pdo->prepare("SELECT al.*, u.full_name FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id WHERE $whereClause ORDER BY al.created_at DESC LIMIT ? OFFSET ?");
$params[] = $perPage;
$params[] = $offset;
$stmt->execute($params);
$logs = $stmt->fetchAll();

$actions = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Filter by Action</label>
                <select name="action" class="form-select">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $a): ?>
                    <option value="<?php echo e($a); ?>" <?php echo $action === $a ? 'selected' : ''; ?>><?php echo e($a); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="fas fa-history me-2 text-primary"></i>Activity Log (<?php echo $total; ?>)</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>User</th><th>Action</th><th>Entity</th><th>Description</th><th>IP</th><th>Time</th></tr></thead>
                <tbody>
                    <?php foreach ($logs as $l): ?>
                    <tr>
                        <td><?php echo e($l['full_name'] ?: 'System'); ?></td>
                        <td><span class="badge bg-info"><?php echo e($l['action']); ?></span></td>
                        <td><?php echo e($l['entity_type'] ?: '-'); ?> <?php echo $l['entity_id'] ? '#' . $l['entity_id'] : ''; ?></td>
                        <td><?php echo e($l['description'] ?: '-'); ?></td>
                        <td><small class="text-muted"><?php echo e($l['ip_address']); ?></small></td>
                        <td><small><?php echo formatDateTime($l['created_at']); ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer">
        <nav><ul class="pagination pagination-sm mb-0 justify-content-center">
            <?php if ($pagination['has_prev']): ?>
            <li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>&action=<?php echo urlencode($action); ?>">Previous</a></li>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&action=<?php echo urlencode($action); ?>"><?php echo $i; ?></a></li>
            <?php endfor; ?>
            <?php if ($pagination['has_next']): ?>
            <li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>&action=<?php echo urlencode($action); ?>">Next</a></li>
            <?php endif; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
