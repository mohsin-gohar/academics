<?php
$page_title = 'User Management';
require_once '../includes/header.php';
requireAdmin();

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;
$search = trim($_GET['search'] ?? '');
$role = trim($_GET['role'] ?? '');

$where = ['1=1'];
$params = [];
if (!empty($search)) {
    $where[] = '(full_name LIKE ? OR email LIKE ?)';
    $sp = '%' . $search . '%';
    $params[] = $sp; $params[] = $sp;
}
if (!empty($role)) {
    $where[] = 'role = ?';
    $params[] = $role;
}
$whereClause = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE $whereClause");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$pagination = getPagination($total, $perPage, $page);

$stmt = $pdo->prepare("SELECT * FROM users WHERE $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?");
$params[] = $perPage;
$params[] = $offset;
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Name or email..." value="<?php echo e($search); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Role</label>
                <select name="role" class="form-select">
                    <option value="">All Roles</option>
                    <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="teacher" <?php echo $role === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                    <option value="student" <?php echo $role === 'student' ? 'selected' : ''; ?>>Student</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="fas fa-search"></i> Search</button>
                <a href="users.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-users-cog me-2 text-primary"></i>Users (<?php echo $total; ?>)</span>
        <a href="create_user.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> Add User</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th>Created</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="fw-medium"><?php echo e($u['full_name']); ?></td>
                        <td><?php echo e($u['email']); ?></td>
                        <td><span class="badge bg-<?php echo $u['role'] === 'admin' ? 'danger' : ($u['role'] === 'teacher' ? 'primary' : 'secondary'); ?>"><?php echo ucfirst($u['role']); ?></span></td>
                        <td><span class="badge bg-<?php echo $u['is_active'] ? 'success' : 'secondary'; ?>"><?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                        <td><?php echo $u['last_login'] ? formatDateTime($u['last_login']) : 'Never'; ?></td>
                        <td><?php echo formatDate($u['created_at']); ?></td>
                        <td class="text-end">
                            <a href="edit_user.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <a href="toggle_user.php?id=<?php echo $u['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" class="btn btn-sm btn-outline-warning" title="Toggle Status"><i class="fas fa-power-off"></i></a>
                            <a href="delete_user.php?id=<?php echo $u['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this user?')"><i class="fas fa-trash"></i></a>
                            <?php endif; ?>
                        </td>
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
            <li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role); ?>">Previous</a></li>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role); ?>"><?php echo $i; ?></a></li>
            <?php endfor; ?>
            <?php if ($pagination['has_next']): ?>
            <li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role); ?>">Next</a></li>
            <?php endif; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
