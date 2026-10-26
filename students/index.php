<?php
$page_title = 'Students';
require_once '../includes/header.php';
requireAuth();

// If student user visits students index, automatically redirect to their personal profile
if (isStudent()) {
    $myId = $_SESSION['student_id'] ?? null;
    if (!$myId && isset($_SESSION['user_id'])) {
        $st = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
        $st->execute([$_SESSION['user_id']]);
        $myId = $st->fetchColumn();
        if ($myId) {
            $_SESSION['student_id'] = (int)$myId;
        }
    }
    if ($myId) {
        header("Location: show.php?id=" . (int)$myId);
        exit;
    }
    header("Location: ../dashboard.php");
    exit;
}

requireStaff();

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Filters
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$course = trim($_GET['course'] ?? '');

// Build query
$where = ['1=1'];
$params = [];

if (!empty($search)) {
    $where[] = '(s.full_name LIKE ? OR s.student_number LIKE ? OR s.email LIKE ?)';
    $sp = '%' . $search . '%';
    $params[] = $sp; $params[] = $sp; $params[] = $sp;
}
if (!empty($status)) {
    $where[] = 's.status = ?';
    $params[] = $status;
}
if (!empty($course)) {
    $where[] = 'EXISTS (SELECT 1 FROM enrollments e WHERE e.student_id = s.id AND e.course_id = ?)';
    $params[] = $course;
}

$whereClause = implode(' AND ', $where);

// Count
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM students s WHERE $whereClause");
$stmt->execute($params);
$total = $stmt->fetch()['total'];

$pagination = getPagination($total, $perPage, $page);

// Fetch students
$stmt = $pdo->prepare("SELECT s.*, u.is_active as user_active FROM students s LEFT JOIN users u ON s.user_id = u.id WHERE $whereClause ORDER BY s.created_at DESC LIMIT ? OFFSET ?");
$params[] = $perPage;
$params[] = $offset;
$stmt->execute($params);
$students = $stmt->fetchAll();

// Courses for filter
$courses = $pdo->query("SELECT id, course_code, course_name FROM courses WHERE status = 'Active' ORDER BY course_name")->fetchAll();
?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Name, number, or email..." value="<?php echo e($search); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="Active" <?php echo $status === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo $status === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="Graduated" <?php echo $status === 'Graduated' ? 'selected' : ''; ?>>Graduated</option>
                    <option value="Suspended" <?php echo $status === 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Course</label>
                <select name="course" class="form-select">
                    <option value="">All Courses</option>
                    <?php foreach ($courses as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $course == $c['id'] ? 'selected' : ''; ?>>
                        <?php echo e($c['course_code'] . ' - ' . $c['course_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="fas fa-search"></i></button>
                <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-user-graduate me-2 text-primary"></i>Students (<?php echo $total; ?>)</span>
        <a href="create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> Add Student</a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($students)): ?>
            <div class="empty-state">
                <i class="fas fa-user-graduate"></i>
                <h5>No students found</h5>
                <p>Try adjusting your filters or add a new student.</p>
                <a href="create.php" class="btn btn-primary btn-sm">Add Student</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student #</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Enrolled</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $i => $s): ?>
                        <tr>
                            <td><?php echo $offset + $i + 1; ?></td>
                            <td><code><?php echo e($s['student_number']); ?></code></td>
                            <td>
                                <a href="show.php?id=<?php echo $s['id']; ?>" class="text-decoration-none text-dark fw-medium">
                                    <?php echo e($s['full_name']); ?>
                                </a>
                            </td>
                            <td><?php echo e($s['email']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $s['status'] === 'Active' ? 'success' : ($s['status'] === 'Graduated' ? 'info' : 'secondary'); ?>">
                                    <?php echo e($s['status']); ?>
                                </span>
                            </td>
                            <td><?php echo formatDate($s['enrollment_date']); ?></td>
                            <td class="text-end">
                                <a href="show.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-info" title="View"><i class="fas fa-eye"></i></a>
                                <a href="edit.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="delete.php?id=<?php echo $s['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this student?')"><i class="fas fa-trash"></i></a>
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
        <nav>
            <ul class="pagination pagination-sm mb-0 justify-content-center">
                <?php if ($pagination['has_prev']): ?>
                <li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&course=<?php echo urlencode($course); ?>">Previous</a></li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&course=<?php echo urlencode($course); ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($pagination['has_next']): ?>
                <li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&course=<?php echo urlencode($course); ?>">Next</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
