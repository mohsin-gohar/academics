<?php
$page_title = 'Courses';
require_once '../includes/header.php';
requireAuth();

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$where = ['1=1'];
$params = [];
if (!isSuperAdmin()) {
    $where[] = 'c.school_id = ?';
    $params[] = currentSchoolId();
}
if (!empty($search)) {
    $where[] = '(course_code LIKE ? OR course_name LIKE ?)';
    $sp = '%' . $search . '%';
    $params[] = $sp; $params[] = $sp;
}
if (!empty($status)) {
    $where[] = 'status = ?';
    $params[] = $status;
}
$whereClause = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM courses c WHERE $whereClause");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$pagination = getPagination($total, $perPage, $page);

$stmt = $pdo->prepare("SELECT c.*, u.full_name as instructor_name, (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'Enrolled') as enrolled_count 
    FROM courses c 
    LEFT JOIN users u ON c.instructor_id = u.id 
    WHERE $whereClause ORDER BY c.created_at DESC LIMIT ? OFFSET ?");
$params[] = $perPage;
$params[] = $offset;
$stmt->execute($params);
$courses = $stmt->fetchAll();
?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Course code or name..." value="<?php echo e($search); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="Active" <?php echo $status === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo $status === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="Archived" <?php echo $status === 'Archived' ? 'selected' : ''; ?>>Archived</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="fas fa-search"></i> Search</button>
                <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-book me-2 text-primary"></i>Courses (<?php echo $total; ?>)</span>
        <?php if (isStaff()): ?>
        <a href="create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> Add Course</a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (empty($courses)): ?>
            <div class="empty-state"><i class="fas fa-book"></i><h5>No courses found</h5>
            <?php if (isStaff()): ?>
            <a href="create.php" class="btn btn-primary btn-sm">Add Course</a>
            <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Code</th><th>Name</th><th>Credits</th><th>Instructor</th><th>Enrolled</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($courses as $c): ?>
                        <tr>
                            <td><code><?php echo e($c['course_code']); ?></code></td>
                            <td><a href="show.php?id=<?php echo $c['id']; ?>" class="text-decoration-none text-dark fw-medium"><?php echo e($c['course_name']); ?></a></td>
                            <td><?php echo $c['credits'] ?? 3; ?></td>
                            <td><?php echo e($c['instructor_name'] ?: '-'); ?></td>
                            <td><?php echo $c['enrolled_count']; ?></td>
                            <td><span class="badge bg-<?php echo $c['status'] === 'Active' ? 'success' : 'secondary'; ?>"><?php echo e($c['status']); ?></span></td>
                            <td class="text-end">
                                <a href="show.php?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-info" title="View"><i class="fas fa-eye"></i></a>
                                <?php if (isStaff()): ?>
                                <a href="edit.php?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="delete.php?id=<?php echo $c['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this course?')"><i class="fas fa-trash"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer">
        <nav><ul class="pagination pagination-sm mb-0 justify-content-center">
            <?php if ($pagination['has_prev']): ?>
            <li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>">Previous</a></li>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>"><?php echo $i; ?></a></li>
            <?php endfor; ?>
            <?php if ($pagination['has_next']): ?>
            <li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>">Next</a></li>
            <?php endif; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
