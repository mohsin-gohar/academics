<?php
$page_title = 'Task Details';
require_once '../includes/header.php';
requireAuth();

$user_id = $_SESSION['user_id'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT t.*, s.full_name as student_name, s.student_number, c.course_name, c.course_code, u.full_name as created_by_name 
    FROM tasks t 
    LEFT JOIN students s ON t.student_id = s.id 
    LEFT JOIN courses c ON t.course_id = c.id 
    LEFT JOIN users u ON t.user_id = u.id 
    WHERE t.id = ?");
$stmt->execute([$id]);
$task = $stmt->fetch();
if (!$task) { header('Location: index.php?error=' . urlencode('Task not found.')); exit; }
require_record_school($task);

// Permission check: staff can see own, admin all, student only assigned
if (isStudent()) {
    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $st = $stmt->fetch();
    if (!$st || $task['student_id'] != $st['id']) {
        header('Location: ../errors/403.php'); exit;
    }
}
?>

<div class="row mb-3">
    <div class="col-12"><a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Back to Tasks</a></div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-tasks me-2 text-primary"></i>Task Details</span>
                <?php if (isAdmin() || (isStaff() && $task['user_id'] == $user_id)): ?>
                <div class="d-flex gap-2">
                    <a href="edit.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit me-1"></i>Edit</a>
                    <a href="delete.php?id=<?php echo $task['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this task?')"><i class="fas fa-trash me-1"></i>Delete</a>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <h3 class="mb-3"><?php echo e($task['title']); ?></h3>
                <div class="mb-3">
                    <span class="badge priority-<?php echo strtolower($task['priority']); ?> me-1"><?php echo e($task['priority']); ?></span>
                    <span class="badge bg-<?php echo $task['status'] === 'Completed' ? 'success' : ($task['status'] === 'In Progress' ? 'warning' : ($task['status'] === 'Cancelled' ? 'danger' : 'secondary')); ?>"><?php echo e($task['status']); ?></span>
                </div>
                <?php if ($task['description']): ?>
                <p class="mb-3"><?php echo nl2br(e($task['description'])); ?></p>
                <?php endif; ?>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Student:</strong> <?php echo $task['student_name'] ? e($task['student_number'] . ' - ' . $task['student_name']) : '-'; ?></p>
                        <p class="mb-1"><strong>Course:</strong> <?php echo $task['course_name'] ? e($task['course_code'] . ' - ' . $task['course_name']) : '-'; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Due Date:</strong> <?php echo $task['due_date'] ? formatDate($task['due_date']) : '-'; ?></p>
                        <p class="mb-1"><strong>Created By:</strong> <?php echo e($task['created_by_name'] ?: '-'); ?></p>
                        <p class="mb-1"><strong>Created:</strong> <?php echo formatDateTime($task['created_at']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-cog me-2"></i>Quick Status Update</div>
            <div class="card-body">
                <form method="POST" action="update_status.php">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
                    <select name="status" class="form-select mb-3">
                        <option value="Pending" <?php echo $task['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="In Progress" <?php echo $task['status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="Completed" <?php echo $task['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="Cancelled" <?php echo $task['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-1"></i> Update Status</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
