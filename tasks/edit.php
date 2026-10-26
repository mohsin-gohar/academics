<?php
$page_title = 'Edit Task';
require_once '../includes/header.php';
requireAuth();

$user_id = $_SESSION['user_id'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$id]);
$task = $stmt->fetch();
if (!$task) { header('Location: index.php?error=' . urlencode('Task not found.')); exit; }

// Permission: staff can edit own tasks, admin all, student cannot edit
if (isStudent()) { header('Location: ../errors/403.php'); exit; }
if (!isAdmin() && $task['user_id'] != $user_id) { header('Location: ../errors/403.php'); exit; }

$errors = [];
$form_data = $task;

$students = [];
$courses = [];
if (isStaff()) {
    $students = $pdo->query("SELECT id, full_name, student_number FROM students WHERE status = 'Active' ORDER BY full_name")->fetchAll();
    $courses = $pdo->query("SELECT id, course_code, course_name FROM courses WHERE status = 'Active' ORDER BY course_name")->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }

    $form_data = [
        'title' => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'student_id' => (int)($_POST['student_id'] ?? 0),
        'course_id' => (int)($_POST['course_id'] ?? 0),
        'priority' => $_POST['priority'] ?? 'Medium',
        'status' => $_POST['status'] ?? 'Pending',
        'due_date' => $_POST['due_date'] ?? ''
    ];

    if (empty($form_data['title'])) $errors[] = 'Task title is required.';
    if (strlen($form_data['title']) < 3) $errors[] = 'Title must be at least 3 characters.';

    if (empty($errors)) {
        try {
            $completedSql = ($form_data['status'] === 'Completed') ? ', completed_at = COALESCE(completed_at, NOW())' : ', completed_at = NULL';
            $stmt = $pdo->prepare("UPDATE tasks SET student_id=?, course_id=?, title=?, description=?, priority=?, status=?, due_date=? $completedSql, updated_at=NOW() WHERE id=?");
            $stmt->execute([
                $form_data['student_id'] ?: null,
                $form_data['course_id'] ?: null,
                $form_data['title'],
                $form_data['description'] ?: null,
                $form_data['priority'],
                $form_data['status'],
                $form_data['due_date'] ?: null,
                $id
            ]);
            logActivity($pdo, $user_id, 'update', 'task', $id, 'Updated task: ' . $form_data['title']);
            header('Location: show.php?id=' . $id . '&success=' . urlencode('Task updated successfully!'));
            exit;
        } catch (PDOException $e) {
            error_log("Task update failed: " . $e->getMessage());
            $errors[] = 'Failed to update task.';
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-edit text-primary me-2"></i>Edit Task</h5></div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo e($e); ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success"><?php echo e($_GET['success']); ?></div>
                <?php endif; ?>
                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" value="<?php echo e($form_data['title']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?php echo e($form_data['description'] ?? ''); ?></textarea>
                    </div>
                    <?php if (isStaff()): ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Assign to Student</label>
                            <select name="student_id" class="form-select">
                                <option value="">-- None --</option>
                                <?php foreach ($students as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo ($form_data['student_id'] ?? 0) == $s['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($s['student_number'] . ' - ' . $s['full_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Course</label>
                            <select name="course_id" class="form-select">
                                <option value="">-- None --</option>
                                <?php foreach ($courses as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($form_data['course_id'] ?? 0) == $c['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($c['course_code'] . ' - ' . $c['course_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <?php foreach (['Low','Medium','High','Urgent'] as $p): ?>
                                <option value="<?php echo $p; ?>" <?php echo $form_data['priority'] === $p ? 'selected' : ''; ?>><?php echo $p; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <?php foreach (['Pending','In Progress','Completed','Cancelled'] as $st): ?>
                                <option value="<?php echo $st; ?>" <?php echo $form_data['status'] === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Due Date</label>
                            <input type="date" name="due_date" class="form-control" value="<?php echo e($form_data['due_date'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="show.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Update Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
