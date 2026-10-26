<?php
$page_title = 'Dashboard';
require_once 'includes/header.php';
requireAuth();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

// Dashboard statistics
$stats = [
    'total_students' => 0,
    'active_students' => 0,
    'inactive_students' => 0,
    'total_courses' => 0,
    'total_tasks' => 0,
    'completed_tasks' => 0,
    'pending_tasks' => 0,
    'in_progress_tasks' => 0,
    'overdue_tasks' => 0,
    'completion_rate' => 0
];

// Students stats (staff only)
if (isStaff()) {
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status != 'Active' THEN 1 ELSE 0 END) as inactive
        FROM students");
    $s = $stmt->fetch();
    $stats['total_students'] = (int)($s['total'] ?? 0);
    $stats['active_students'] = (int)($s['active'] ?? 0);
    $stats['inactive_students'] = (int)($s['inactive'] ?? 0);

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM courses WHERE status = 'Active'");
    $stats['total_courses'] = (int)$stmt->fetch()['total'];
}

// Task stats
if (isAdmin()) {
    $taskWhere = "1=1";
    $taskParams = [];
} elseif (isStaff()) {
    $taskWhere = "user_id = ?";
    $taskParams = [$user_id];
} else {
    // Student sees their assigned tasks
    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch();
    if ($student) {
        $taskWhere = "student_id = ?";
        $taskParams = [$student['id']];
    } else {
        $taskWhere = "user_id = ?";
        $taskParams = [$user_id];
    }
}

$sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN due_date < CURDATE() AND status != 'Completed' THEN 1 ELSE 0 END) as overdue
    FROM tasks WHERE $taskWhere";
$stmt = $pdo->prepare($sql);
$stmt->execute($taskParams);
$t = $stmt->fetch();

$stats['total_tasks'] = (int)($t['total'] ?? 0);
$stats['completed_tasks'] = (int)($t['completed'] ?? 0);
$stats['pending_tasks'] = (int)($t['pending'] ?? 0);
$stats['in_progress_tasks'] = (int)($t['in_progress'] ?? 0);
$stats['overdue_tasks'] = (int)($t['overdue'] ?? 0);

if ($stats['total_tasks'] > 0) {
    $stats['completion_rate'] = round(($stats['completed_tasks'] / $stats['total_tasks']) * 100);
}

// Priority distribution
$priorityStmt = $pdo->prepare("SELECT priority, COUNT(*) as cnt FROM tasks WHERE $taskWhere GROUP BY priority");
$priorityStmt->execute($taskParams);
$priorities = ['Low' => 0, 'Medium' => 0, 'High' => 0, 'Urgent' => 0];
foreach ($priorityStmt->fetchAll() as $row) {
    if (isset($priorities[$row['priority']])) {
        $priorities[$row['priority']] = (int)$row['cnt'];
    }
}

// Recent tasks
$stmt = $pdo->prepare("SELECT t.*, s.full_name as student_name, c.course_name 
    FROM tasks t 
    LEFT JOIN students s ON t.student_id = s.id 
    LEFT JOIN courses c ON t.course_id = c.id 
    WHERE $taskWhere 
    ORDER BY t.created_at DESC LIMIT 5");
$stmt->execute($taskParams);
$recent_tasks = $stmt->fetchAll();

// Recent students (staff only)
$recent_students = [];
if (isStaff()) {
    $stmt = $pdo->query("SELECT * FROM students ORDER BY created_at DESC LIMIT 5");
    $recent_students = $stmt->fetchAll();
}

// Upcoming deadlines
$stmt = $pdo->prepare("SELECT t.*, s.full_name as student_name 
    FROM tasks t 
    LEFT JOIN students s ON t.student_id = s.id 
    WHERE $taskWhere AND t.due_date >= CURDATE() AND t.status != 'Completed' 
    ORDER BY t.due_date ASC LIMIT 5");
$stmt->execute($taskParams);
$upcoming = $stmt->fetchAll();
?>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <?php if (isStaff()): ?>
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(79,70,229,0.1); color: var(--primary);">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $stats['total_students']; ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(16,185,129,0.1); color: var(--success);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $stats['active_students']; ?></div>
                    <div class="stat-label">Active Students</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(59,130,246,0.1); color: var(--info);">
                    <i class="fas fa-book"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $stats['total_courses']; ?></div>
                    <div class="stat-label">Active Courses</div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(245,158,11,0.1); color: var(--warning);">
                    <i class="fas fa-tasks"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $stats['total_tasks']; ?></div>
                    <div class="stat-label">Total Tasks</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(16,185,129,0.1); color: var(--success);">
                    <i class="fas fa-check-double"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $stats['completed_tasks']; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(100,116,139,0.1); color: var(--text-secondary);">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $stats['pending_tasks']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(59,130,246,0.1); color: var(--info);">
                    <i class="fas fa-spinner"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $stats['in_progress_tasks']; ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(239,68,68,0.1); color: var(--danger);">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $stats['overdue_tasks']; ?></div>
                    <div class="stat-label">Overdue</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Completion Rate -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 fw-semibold text-dark"><i class="fas fa-chart-line text-success me-2"></i>Overall Task Completion Rate</h6>
                    <span class="fw-bold text-primary fs-5"><?php echo $stats['completion_rate']; ?>%</span>
                </div>
                <div class="progress" style="height: 12px; border-radius: 6px;">
                    <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?php echo $stats['completion_rate']; ?>%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Analytics & Visual Statistics -->
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-semibold text-dark"><i class="fas fa-chart-pie me-2 text-primary"></i>Task Status Breakdown</h6>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center" style="min-height: 260px;">
                <canvas id="taskStatusChart" style="max-height: 240px;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-semibold text-dark"><i class="fas fa-layer-group me-2 text-indigo"></i>Tasks by Priority</h6>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center" style="min-height: 260px;">
                <canvas id="taskPriorityChart" style="max-height: 240px;"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Recent Tasks -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-tasks me-2 text-primary"></i>Recent Tasks</span>
                <a href="tasks/index.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recent_tasks)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h5>No tasks yet</h5>
                        <p>Create your first task to get started.</p>
                        <a href="tasks/create.php" class="btn btn-primary btn-sm">Add Task</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Due Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_tasks as $task): ?>
                                <tr>
                                    <td>
                                        <a href="tasks/show.php?id=<?php echo $task['id']; ?>" class="text-decoration-none text-dark fw-medium">
                                            <?php echo e($task['title']); ?>
                                        </a>
                                        <?php if ($task['student_name']): ?>
                                            <br><small class="text-muted"><?php echo e($task['student_name']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $task['status'] === 'Completed' ? 'success' : ($task['status'] === 'In Progress' ? 'warning' : 'secondary'); ?>">
                                            <?php echo e($task['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($task['due_date']): ?>
                                            <?php echo formatDate($task['due_date']); ?>
                                            <?php if (strtotime($task['due_date']) < time() && $task['status'] !== 'Completed'): ?>
                                                <span class="badge bg-danger ms-1">Overdue</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            -
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
    </div>

    <!-- Upcoming Deadlines -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="fas fa-calendar-alt me-2 text-warning"></i>Upcoming Deadlines
            </div>
            <div class="card-body p-0">
                <?php if (empty($upcoming)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-check"></i>
                        <h5>No upcoming deadlines</h5>
                        <p>You're all caught up!</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($upcoming as $task): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-medium"><?php echo e($task['title']); ?></div>
                                <small class="text-muted"><?php echo e($task['student_name'] ?? ''); ?></small>
                            </div>
                            <span class="badge bg-warning text-dark"><?php echo formatDate($task['due_date']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (isStaff() && !empty($recent_students)): ?>
<div class="row g-3 mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-user-graduate me-2 text-primary"></i>Recent Students</span>
                <a href="students/index.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Student #</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Enrolled</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_students as $student): ?>
                            <tr>
                                <td><code><?php echo e($student['student_number']); ?></code></td>
                                <td>
                                    <a href="students/show.php?id=<?php echo $student['id']; ?>" class="text-decoration-none text-dark fw-medium">
                                        <?php echo e($student['full_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo e($student['email']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $student['status'] === 'Active' ? 'success' : 'secondary'; ?>">
                                        <?php echo e($student['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($student['enrollment_date']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Quick Actions -->
<div class="row g-3 mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="fas fa-bolt me-2 text-warning"></i>Quick Actions</div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <?php if (isStaff()): ?>
                    <a href="students/create.php" class="btn btn-primary btn-sm"><i class="fas fa-user-plus me-1"></i> Add Student</a>
                    <a href="courses/create.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-plus me-1"></i> Add Course</a>
                    <?php endif; ?>
                    <a href="tasks/create.php" class="btn btn-outline-success btn-sm"><i class="fas fa-plus me-1"></i> Add Task</a>
                    <?php if (isStaff()): ?>
                    <a href="reports/index.php" class="btn btn-outline-info btn-sm"><i class="fas fa-chart-bar me-1"></i> View Reports</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
$extra_js = '
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctxStatus = document.getElementById("taskStatusChart");
    if (ctxStatus) {
        new Chart(ctxStatus, {
            type: "doughnut",
            data: {
                labels: ["Completed", "In Progress", "Pending", "Overdue"],
                datasets: [{
                    data: [' . (int)$stats['completed_tasks'] . ', ' . (int)$stats['in_progress_tasks'] . ', ' . (int)$stats['pending_tasks'] . ', ' . (int)$stats['overdue_tasks'] . '],
                    backgroundColor: ["#10b981", "#f59e0b", "#94a3b8", "#ef4444"],
                    borderWidth: 2,
                    borderColor: "#ffffff"
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: "bottom" }
                }
            }
        });
    }

    const ctxPriority = document.getElementById("taskPriorityChart");
    if (ctxPriority) {
        new Chart(ctxPriority, {
            type: "bar",
            data: {
                labels: ["Low", "Medium", "High", "Urgent"],
                datasets: [{
                    label: "Tasks",
                    data: [' . (int)$priorities['Low'] . ', ' . (int)$priorities['Medium'] . ', ' . (int)$priorities['High'] . ', ' . (int)$priorities['Urgent'] . '],
                    backgroundColor: ["#3b82f6", "#4f46e5", "#f59e0b", "#ef4444"],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, precision: 0 }
                    }
                }
            }
        });
    }
});
</script>
';
require_once 'includes/footer.php'; 
?>
