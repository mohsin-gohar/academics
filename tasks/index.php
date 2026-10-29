<?php
$page_title = 'Tasks';
require_once '../includes/header.php';
requireAuth();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$priority = trim($_GET['priority'] ?? '');
$student_filter = (int)($_GET['student'] ?? 0);

// Build where clause based on role
if (isAdmin()) {
    if (isSuperAdmin()) {
        $baseWhere = '1=1';
        $baseParams = [];
    } else {
        $baseWhere = 't.school_id = ?';
        $baseParams = [currentSchoolId()];
    }
} elseif (isStaff()) {
    $baseWhere = 't.school_id = ? AND t.user_id = ?';
    $baseParams = [currentSchoolId(), $user_id];
} else {
    $stStmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $stStmt->execute([$user_id]);
    $stId = $stStmt->fetchColumn();
    if ($stId) {
        $baseWhere = 't.student_id = ?';
        $baseParams = [(int)$stId];
    } else {
        $baseWhere = 't.user_id = ?';
        $baseParams = [$user_id];
    }
}

$where = [$baseWhere];
$params = $baseParams;

if ($search !== '') {
    $where[] = '(t.title LIKE ? OR t.description LIKE ?)';
    $sp = '%' . $search . '%';
    $params[] = $sp; 
    $params[] = $sp;
}
if ($status !== '' && in_array($status, ['Pending', 'In Progress', 'Completed', 'Cancelled'])) {
    $where[] = 't.status = ?';
    $params[] = $status;
}
if ($priority !== '' && in_array($priority, ['Low', 'Medium', 'High', 'Urgent'])) {
    $where[] = 't.priority = ?';
    $params[] = $priority;
}
if ($student_filter > 0 && isStaff()) {
    $where[] = 't.student_id = ?';
    $params[] = $student_filter;
}

$whereClause = implode(' AND ', $where);

// Count
$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM tasks t WHERE $whereClause");
$countStmt->execute($params);
$total = (int)$countStmt->fetch()['total'];
$pagination = getPagination($total, $perPage, $page);

// Fetch tasks
$stmt = $pdo->prepare("SELECT t.*, s.full_name as student_name, c.course_name 
    FROM tasks t 
    LEFT JOIN students s ON t.student_id = s.id 
    LEFT JOIN courses c ON t.course_id = c.id 
    WHERE $whereClause 
    ORDER BY t.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Task stats based on current user's scope
$statStmt = $pdo->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN due_date < CURDATE() AND status != 'Completed' THEN 1 ELSE 0 END) as overdue
    FROM tasks t WHERE $baseWhere");
$statStmt->execute($baseParams);
$statCounts = $statStmt->fetch();

// Students for filter (staff only)
$students = [];
if (isStaff()) {
    if (isSuperAdmin()) {
        $students = $pdo->query("SELECT id, full_name, student_number FROM students WHERE status = 'Active' ORDER BY full_name")->fetchAll();
    } else {
        $studentStmt = $pdo->prepare("SELECT id, full_name, student_number FROM students WHERE status = 'Active' AND school_id = ? ORDER BY full_name");
        $studentStmt->execute([currentSchoolId()]);
        $students = $studentStmt->fetchAll();
    }
}
?>

<!-- Task Stats Ribbon -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md">
        <div class="card stat-card shadow-sm border-0">
            <div class="card-body p-3 text-center">
                <span class="text-muted small fw-medium d-block">Total Tasks</span>
                <span class="h4 mb-0 fw-bold text-dark" id="statTotalTasks"><?php echo (int)($statCounts['total'] ?? 0); ?></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="card stat-card shadow-sm border-0">
            <div class="card-body p-3 text-center">
                <span class="text-muted small fw-medium d-block">Pending</span>
                <span class="h4 mb-0 fw-bold text-secondary" id="statPendingTasks"><?php echo (int)($statCounts['pending'] ?? 0); ?></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="card stat-card shadow-sm border-0">
            <div class="card-body p-3 text-center">
                <span class="text-muted small fw-medium d-block">In Progress</span>
                <span class="h4 mb-0 fw-bold text-warning" id="statInProgressTasks"><?php echo (int)($statCounts['in_progress'] ?? 0); ?></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md">
        <div class="card stat-card shadow-sm border-0">
            <div class="card-body p-3 text-center">
                <span class="text-muted small fw-medium d-block">Completed</span>
                <span class="h4 mb-0 fw-bold text-success" id="statCompletedTasks"><?php echo (int)($statCounts['completed'] ?? 0); ?></span>
            </div>
        </div>
    </div>
    <div class="col-12 col-md">
        <div class="card stat-card shadow-sm border-0">
            <div class="card-body p-3 text-center">
                <span class="text-muted small fw-medium d-block">Overdue</span>
                <span class="h4 mb-0 fw-bold text-danger" id="statOverdueTasks"><?php echo (int)($statCounts['overdue'] ?? 0); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end" id="taskFilterForm">
            <div class="col-md-3">
                <label class="form-label small fw-medium text-muted">Search Title / Notes</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" id="taskSearchInput" class="form-control border-start-0" placeholder="Type to filter instantly..." value="<?php echo e($search); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-medium text-muted">Status</label>
                <select name="status" id="taskStatusSelect" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="Pending" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="In Progress" <?php echo $status === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="Completed" <?php echo $status === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="Cancelled" <?php echo $status === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-medium text-muted">Priority</label>
                <select name="priority" id="taskPrioritySelect" class="form-select">
                    <option value="">All Priorities</option>
                    <option value="Low" <?php echo $priority === 'Low' ? 'selected' : ''; ?>>Low</option>
                    <option value="Medium" <?php echo $priority === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="High" <?php echo $priority === 'High' ? 'selected' : ''; ?>>High</option>
                    <option value="Urgent" <?php echo $priority === 'Urgent' ? 'selected' : ''; ?>>Urgent</option>
                </select>
            </div>
            <?php if (isStaff()): ?>
            <div class="col-md-3">
                <label class="form-label small fw-medium text-muted">Assigned Student</label>
                <select name="student" id="taskStudentSelect" class="form-select">
                    <option value="">All Students</option>
                    <?php foreach ($students as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php echo $student_filter == $s['id'] ? 'selected' : ''; ?>>
                        <?php echo e($s['student_number'] . ' - ' . $s['full_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="fas fa-filter me-1"></i> Filter</button>
                <button type="button" id="taskClearFilters" class="btn btn-outline-secondary" title="Reset Filters"><i class="fas fa-undo"></i></button>
            </div>
        </form>
    </div>
</div>

<!-- Task Table Card -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <span class="fw-semibold text-dark"><i class="fas fa-tasks me-2 text-primary"></i>Task Directory</span>
        <a href="create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> Add Task</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tasksTable">
                <thead class="table-light">
                    <tr>
                        <th style="min-width: 220px;">Task Title</th>
                        <th>Student</th>
                        <th>Course</th>
                        <th>Priority</th>
                        <th style="min-width: 140px;">Status</th>
                        <th>Due Date</th>
                        <th class="text-end" style="min-width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="tasksTableBody">
                    <?php if (empty($tasks)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-clipboard-list fa-3x mb-3 opacity-25"></i>
                                <h5>No tasks found</h5>
                                <p class="small text-muted mb-2">Create a task to get started.</p>
                                <a href="create.php" class="btn btn-primary btn-sm">Add Task</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tasks as $t): ?>
                        <tr id="task-row-<?php echo $t['id']; ?>">
                            <td>
                                <a href="show.php?id=<?php echo $t['id']; ?>" class="text-decoration-none text-dark fw-medium">
                                    <?php echo e($t['title']); ?>
                                </a>
                                <?php if (!empty($t['description'])): ?>
                                    <br><small class="text-muted"><?php echo e(mb_strimwidth($t['description'], 0, 50, '...')); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($t['student_name'] ?: '-'); ?></td>
                            <td><?php echo e($t['course_name'] ?: '-'); ?></td>
                            <td>
                                <span class="badge priority-<?php echo strtolower($t['priority']); ?>">
                                    <?php echo e($t['priority']); ?>
                                </span>
                            </td>
                            <td>
                                <select class="form-select form-select-sm task-status-select" data-task-id="<?php echo $t['id']; ?>" style="width: auto; min-width: 120px;">
                                    <option value="Pending" <?php echo $t['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="In Progress" <?php echo $t['status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="Completed" <?php echo $t['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="Cancelled" <?php echo $t['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </td>
                            <td>
                                <?php if ($t['due_date']): ?>
                                    <?php echo formatDate($t['due_date']); ?>
                                    <?php if (strtotime($t['due_date']) < time() && $t['status'] !== 'Completed'): ?>
                                        <span class="badge bg-danger ms-1">Overdue</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="show.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-info" title="View"><i class="fas fa-eye"></i></a>
                                <?php if (isAdmin() || (isStaff() && $t['user_id'] == $user_id)): ?>
                                <a href="edit.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="delete.php?id=<?php echo $t['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this task?');"><i class="fas fa-trash"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white py-2" id="taskPaginationNav">
        <?php if ($pagination['total_pages'] > 1): ?>
        <nav>
            <ul class="pagination pagination-sm mb-0 justify-content-center">
                <?php if ($pagination['has_prev']): ?>
                <li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&priority=<?php echo urlencode($priority); ?>&student=<?php echo urlencode($student_filter); ?>">Previous</a></li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&priority=<?php echo urlencode($priority); ?>&student=<?php echo urlencode($student_filter); ?>"><?php echo $i; ?></a></li>
                <?php endfor; ?>
                <?php if ($pagination['has_next']): ?>
                <li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&priority=<?php echo urlencode($priority); ?>&student=<?php echo urlencode($student_filter); ?>">Next</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<?php 
$extra_js = '<script src="' . $base . 'assets/js/tasks.js"></script>';
require_once '../includes/footer.php'; 
?>
