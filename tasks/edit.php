<?php
$page_title = 'Edit Task';
require_once '../includes/header.php';
requireAuth();
if (!isStaff() && !isStudent()) {
    header('Location: ../errors/403.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$id]);
$task = $stmt->fetch();
if (!$task) { header('Location: index.php?error=' . urlencode('Task not found.')); exit; }
require_record_school($task);

// Permission: staff can edit own tasks, admin all; student can edit tasks they created themselves
if (isStudent()) {
    if ((int)$task['user_id'] !== (int)$user_id) {
        header('Location: show.php?id=' . $id . '&error=' . urlencode('Only the task creator or teacher can edit this task. You can change your status directly.'));
        exit;
    }
} elseif (!isAdmin() && (int)$task['user_id'] !== (int)$user_id) {
    header('Location: index.php?error=' . urlencode('You do not have permission to edit this task.'));
    exit;
}

$errors = [];
$form_data = $task;

$students = [];
$courseSql = "SELECT id, course_code, course_name FROM courses WHERE status = 'Active'";
$courseParams = [];
$studentSql = "SELECT id, full_name, student_number FROM students WHERE status = 'Active'";
$studentParams = [];
if (!isSuperAdmin()) {
    $courseSql .= " AND school_id = ?";
    $courseParams[] = currentSchoolId();
    $studentSql .= " AND school_id = ?";
    $studentParams[] = currentSchoolId();
}
$courseSql .= " ORDER BY course_name";
$studentSql .= " ORDER BY full_name";
$stmt = $pdo->prepare($courseSql);
$stmt->execute($courseParams);
$courses = $stmt->fetchAll();
if (isStaff()) {
    $stmt = $pdo->prepare($studentSql);
    $stmt->execute($studentParams);
    $students = $stmt->fetchAll();
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
    if (strlen($form_data['title']) > 200) $errors[] = 'Title cannot exceed 200 characters.';
    if (!in_array($form_data['priority'], ['Low','Medium','High','Urgent'])) $errors[] = 'Invalid priority.';
    if (!in_array($form_data['status'], ['Pending','In Progress','Completed','Cancelled'])) $errors[] = 'Invalid status.';
    if ($form_data['due_date'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $form_data['due_date'])) $errors[] = 'Due date must be in YYYY-MM-DD format.';
    if ($form_data['due_date'] && !checkdate((int)substr($form_data['due_date'], 5, 2), (int)substr($form_data['due_date'], 8, 2), (int)substr($form_data['due_date'], 0, 4))) {
        $errors[] = 'Due date is not a valid calendar date.';
    }

    // Tenant guard: linked records must belong to the actor's school.
    if (!isSuperAdmin()) {
        if ($form_data['student_id']) {
            $chk = $pdo->prepare("SELECT 1 FROM students WHERE id = ? AND school_id = ?");
            $chk->execute([$form_data['student_id'], currentSchoolId()]);
            if (!$chk->fetchColumn()) $errors[] = 'Selected student is not available for your school.';
        }
        if ($form_data['course_id']) {
            $chk = $pdo->prepare("SELECT 1 FROM courses WHERE id = ? AND school_id = ?");
            $chk->execute([$form_data['course_id'], currentSchoolId()]);
            if (!$chk->fetchColumn()) $errors[] = 'Selected course is not available for your school.';
        }
    }

    if (empty($errors)) {
        try {
            $completedSql = ($form_data['status'] === 'Completed') ? ', completed_at = COALESCE(completed_at, NOW())' : ', completed_at = NULL';
            $stmt = $pdo->prepare("UPDATE tasks SET student_id=?, course_id=?, title=?, description=?, priority=?, status=?, due_date=? $completedSql, updated_at=NOW() WHERE id=? AND school_id=?");
            $stmt->execute([
                $form_data['student_id'] ?: null,
                $form_data['course_id'] ?: null,
                $form_data['title'],
                $form_data['description'] ?: null,
                $form_data['priority'],
                $form_data['status'],
                $form_data['due_date'] ?: null,
                $id,
                (int)$task['school_id']
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
