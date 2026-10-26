<?php
$page_title = 'Student Details';
require_once '../includes/header.php';
requireAuth();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { 
    header('Location: ' . (isStaff() ? 'index.php' : '../dashboard.php')); 
    exit; 
}

// Student can only view their own profile - gracefully redirect instead of 403
if (!isStaff()) {
    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $ownId = (int)$stmt->fetchColumn();
    if ($ownId && $id !== $ownId) {
        header('Location: show.php?id=' . $ownId);
        exit;
    } elseif (!$ownId) {
        header('Location: ../dashboard.php');
        exit;
    }
}

$stmt = $pdo->prepare("SELECT s.*, u.is_active as user_active, u.last_login FROM students s LEFT JOIN users u ON s.user_id = u.id WHERE s.id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();
if (!$student) { header('Location: index.php?error=' . urlencode('Student not found.')); exit; }

// Enrollments
$stmt = $pdo->prepare("SELECT e.*, c.course_code, c.course_name FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE e.student_id = ? ORDER BY e.enrollment_date DESC");
$stmt->execute([$id]);
$enrollments = $stmt->fetchAll();

// Tasks
$stmt = $pdo->prepare("SELECT t.*, c.course_name FROM tasks t LEFT JOIN courses c ON t.course_id = c.id WHERE t.student_id = ? ORDER BY t.created_at DESC");
$stmt->execute([$id]);
$tasks = $stmt->fetchAll();

// Task stats
$taskStats = ['total' => 0, 'completed' => 0, 'pending' => 0, 'in_progress' => 0];
foreach ($tasks as $t) {
    $taskStats['total']++;
    if ($t['status'] === 'Completed') $taskStats['completed']++;
    elseif ($t['status'] === 'Pending') $taskStats['pending']++;
    elseif ($t['status'] === 'In Progress') $taskStats['in_progress']++;
}
?>

<div class="row mb-3">
    <div class="col-12">
        <a href="<?php echo isStaff() ? 'index.php' : '../dashboard.php'; ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> <?php echo isStaff() ? 'Back to Students' : 'Back to Dashboard'; ?>
        </a>
    </div>
</div>

<div class="row g-3">
    <!-- Profile Card -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="mb-3">
                    <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" style="width:80px;height:80px;font-size:2rem;">
                        <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                    </div>
                </div>
                <h4 class="mb-1"><?php echo e($student['full_name']); ?></h4>
                <p class="text-muted mb-2"><?php echo e($student['student_number']); ?></p>
                <span class="badge bg-<?php echo $student['status'] === 'Active' ? 'success' : 'secondary'; ?> mb-3"><?php echo e($student['status']); ?></span>
                
                <hr>
                <div class="text-start">
                    <p class="mb-1"><i class="fas fa-envelope me-2 text-muted"></i><?php echo e($student['email']); ?></p>
                    <?php if ($student['phone']): ?>
                    <p class="mb-1"><i class="fas fa-phone me-2 text-muted"></i><?php echo e($student['phone']); ?></p>
                    <?php endif; ?>
                    <?php if ($student['date_of_birth']): ?>
                    <p class="mb-1"><i class="fas fa-birthday-cake me-2 text-muted"></i><?php echo formatDate($student['date_of_birth']); ?></p>
                    <?php endif; ?>
                    <?php if ($student['gender']): ?>
                    <p class="mb-1"><i class="fas fa-venus-mars me-2 text-muted"></i><?php echo e($student['gender']); ?></p>
                    <?php endif; ?>
                    <p class="mb-1"><i class="fas fa-calendar me-2 text-muted"></i>Enrolled: <?php echo formatDate($student['enrollment_date']); ?></p>
                    <?php if ($student['address']): ?>
                    <p class="mb-1"><i class="fas fa-map-marker-alt me-2 text-muted"></i><?php echo e($student['address']); ?></p>
                    <?php endif; ?>
                </div>
                
                <?php if (isStaff()): ?>
                <hr>
                <div class="d-flex gap-2 justify-content-center">
                    <a href="edit.php?id=<?php echo $student['id']; ?>" class="btn btn-primary btn-sm"><i class="fas fa-edit me-1"></i>Edit</a>
                    <a href="delete.php?id=<?php echo $student['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this student?')"><i class="fas fa-trash me-1"></i>Delete</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Task Stats -->
        <div class="card mt-3">
            <div class="card-header"><i class="fas fa-chart-pie me-2"></i>Task Summary</div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2"><span>Total</span><strong><?php echo $taskStats['total']; ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span>Completed</span><strong class="text-success"><?php echo $taskStats['completed']; ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span>Pending</span><strong class="text-secondary"><?php echo $taskStats['pending']; ?></strong></div>
                <div class="d-flex justify-content-between"><span>In Progress</span><strong class="text-info"><?php echo $taskStats['in_progress']; ?></strong></div>
            </div>
        </div>
    </div>
    
    <!-- Enrollments & Tasks -->
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-book me-2 text-primary"></i>Enrolled Courses (<?php echo count($enrollments); ?>)</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($enrollments)): ?>
                    <div class="empty-state"><i class="fas fa-book-open"></i><h5>No enrollments</h5></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Course</th><th>Enrolled</th><th>Status</th><th>Grade</th></tr></thead>
                            <tbody>
                                <?php foreach ($enrollments as $e): ?>
                                <tr>
                                    <td><strong><?php echo e($e['course_code']); ?></strong> - <?php echo e($e['course_name']); ?></td>
                                    <td><?php echo formatDate($e['enrollment_date']); ?></td>
                                    <td><span class="badge bg-<?php echo $e['status'] === 'Enrolled' ? 'primary' : ($e['status'] === 'Completed' ? 'success' : 'secondary'); ?>"><?php echo e($e['status']); ?></span></td>
                                    <td><?php echo e($e['grade'] ?: '-'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header"><i class="fas fa-tasks me-2 text-primary"></i>Assigned Tasks (<?php echo count($tasks); ?>)</div>
            <div class="card-body p-0">
                <?php if (empty($tasks)): ?>
                    <div class="empty-state"><i class="fas fa-clipboard-list"></i><h5>No tasks assigned</h5></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Title</th><th>Priority</th><th>Status</th><th>Due Date</th></tr></thead>
                            <tbody>
                                <?php foreach ($tasks as $t): ?>
                                <tr>
                                    <td><?php echo e($t['title']); ?></td>
                                    <td><span class="badge priority-<?php echo strtolower($t['priority']); ?>"><?php echo e($t['priority']); ?></span></td>
                                    <td><span class="badge bg-<?php echo $t['status'] === 'Completed' ? 'success' : ($t['status'] === 'In Progress' ? 'warning' : 'secondary'); ?>"><?php echo e($t['status']); ?></span></td>
                                    <td><?php echo $t['due_date'] ? formatDate($t['due_date']) : '-'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
