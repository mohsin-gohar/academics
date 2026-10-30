<?php
$page_title = 'Create Task';
require_once '../includes/header.php';
requireAuth();
if (!isStaff() && !isStudent()) {
    header('Location: ../errors/403.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$errors = [];
$form_data = [];

$students = [];
if (isSuperAdmin()) {
    $courses = $pdo->query("SELECT id, course_code, course_name FROM courses WHERE status = 'Active' ORDER BY course_name")->fetchAll();
} else {
    $courseStmt = $pdo->prepare("SELECT id, course_code, course_name FROM courses WHERE status = 'Active' AND school_id = ? ORDER BY course_name");
    $courseStmt->execute([currentSchoolId()]);
    $courses = $courseStmt->fetchAll();
}
if (isStaff()) {
    if (isSuperAdmin()) {
        $students = $pdo->query("SELECT id, full_name, student_number FROM students WHERE status = 'Active' ORDER BY full_name")->fetchAll();
    } else {
        $studentStmt = $pdo->prepare("SELECT id, full_name, student_number FROM students WHERE status = 'Active' AND school_id = ? ORDER BY full_name");
        $studentStmt->execute([currentSchoolId()]);
        $students = $studentStmt->fetchAll();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }

    $assignedStudentId = (int)($_POST['student_id'] ?? 0);
    if (isStudent()) {
        $stStmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
        $stStmt->execute([$user_id]);
        $stRow = $stStmt->fetch();
        if ($stRow) {
            $assignedStudentId = (int)$stRow['id'];
        }
    }

    $form_data = [
        'title' => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'student_id' => $assignedStudentId,
        'course_id' => (int)($_POST['course_id'] ?? 0),
        'priority' => $_POST['priority'] ?? 'Medium',
        'status' => $_POST['status'] ?? 'Pending',
        'due_date' => $_POST['due_date'] ?? ''
    ];

    if (empty($form_data['title'])) $errors[] = 'Task title is required.';
    if (strlen($form_data['title']) < 3) $errors[] = 'Title must be at least 3 characters.';
    if (!in_array($form_data['priority'], ['Low','Medium','High','Urgent'])) $errors[] = 'Invalid priority.';
    if (!in_array($form_data['status'], ['Pending','In Progress','Completed','Cancelled'])) $errors[] = 'Invalid status.';

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
            $stmt = $pdo->prepare("INSERT INTO tasks (school_id, user_id, student_id, course_id, title, description, priority, status, due_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                currentSchoolId(),
                $user_id,
                $form_data['student_id'] ?: null,
                $form_data['course_id'] ?: null,
                $form_data['title'],
                $form_data['description'] ?: null,
                $form_data['priority'],
                $form_data['status'],
                $form_data['due_date'] ?: null
            ]);
            $task_id = $pdo->lastInsertId();
            logActivity($pdo, $user_id, 'create', 'task', $task_id, 'Created task: ' . $form_data['title']);
            header('Location: index.php?success=' . urlencode('Task created successfully!'));
            exit;
        } catch (PDOException $e) {
            error_log("Task create failed: " . $e->getMessage());
            $errors[] = 'Failed to create task.';
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-plus-circle text-primary me-2"></i>Create New Task</h5></div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo e($e); ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" value="<?php echo e($form_data['title'] ?? ''); ?>" required>
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
                                <option value="Low" <?php echo ($form_data['priority'] ?? 'Medium') === 'Low' ? 'selected' : ''; ?>>Low</option>
                                <option value="Medium" <?php echo ($form_data['priority'] ?? 'Medium') === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="High" <?php echo ($form_data['priority'] ?? 'Medium') === 'High' ? 'selected' : ''; ?>>High</option>
                                <option value="Urgent" <?php echo ($form_data['priority'] ?? 'Medium') === 'Urgent' ? 'selected' : ''; ?>>Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="Pending" <?php echo ($form_data['status'] ?? 'Pending') === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="In Progress" <?php echo ($form_data['status'] ?? '') === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="Completed" <?php echo ($form_data['status'] ?? '') === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Due Date</label>
                            <input type="date" name="due_date" class="form-control" value="<?php echo e($form_data['due_date'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Create Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
