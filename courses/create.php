<?php
$page_title = 'Add Course';
require_once '../includes/header.php';
requireStaff();

$errors = [];
$form_data = [];

$instructorSql = "SELECT id, full_name, role FROM users WHERE role IN ('teacher', 'admin') AND is_active = 1";
$instructorParams = [];
if (!isSuperAdmin()) {
    $instructorSql .= " AND school_id = ?";
    $instructorParams[] = currentSchoolId();
}
$instructorSql .= " ORDER BY full_name";
$instructorStmt = $pdo->prepare($instructorSql);
$instructorStmt->execute($instructorParams);
$instructors = $instructorStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }

    $form_data = [
        'course_code' => strtoupper(trim($_POST['course_code'] ?? '')),
        'course_name' => trim($_POST['course_name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'credits' => (int)($_POST['credits'] ?? 3),
        'instructor_id' => !empty($_POST['instructor_id']) ? (int)$_POST['instructor_id'] : null,
        'schedule' => trim($_POST['schedule'] ?? ''),
        'capacity' => (int)($_POST['capacity'] ?? 30),
        'status' => $_POST['status'] ?? 'Active'
    ];

    if (empty($form_data['course_code'])) $errors[] = 'Course code is required.';
    if (empty($form_data['course_name'])) $errors[] = 'Course name is required.';
    if ($form_data['credits'] < 1) $errors[] = 'Credits must be at least 1.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM courses WHERE course_code = ? AND school_id = ?");
        $stmt->execute([$form_data['course_code'], currentSchoolId()]);
        if ($stmt->fetch()) $errors[] = 'Course code already exists.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO courses (school_id, course_code, course_name, description, credits, instructor_id, schedule, capacity, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                currentSchoolId(),
                $form_data['course_code'], $form_data['course_name'], $form_data['description'] ?: null,
                $form_data['credits'], $form_data['instructor_id'], $form_data['schedule'] ?: null,
                $form_data['capacity'], $form_data['status'], $_SESSION['user_id']
            ]);
            $course_id = $pdo->lastInsertId();
            logActivity($pdo, $_SESSION['user_id'], 'create', 'course', $course_id, 'Created course: ' . $form_data['course_name']);
            header('Location: index.php?success=' . urlencode('Course added successfully!'));
            exit;
        } catch (PDOException $e) {
            error_log("Course create failed: " . $e->getMessage());
            $errors[] = 'Failed to create course.';
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-plus-circle text-primary me-2"></i>Add New Course</h5></div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo e($e); ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Course Code *</label>
                            <input type="text" name="course_code" class="form-control" value="<?php echo e($form_data['course_code'] ?? ''); ?>" required placeholder="e.g. CS101">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Course Name *</label>
                            <input type="text" name="course_name" class="form-control" value="<?php echo e($form_data['course_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Credits</label>
                            <input type="number" name="credits" class="form-control" value="<?php echo e($form_data['credits'] ?? 3); ?>" min="1" max="10">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Capacity</label>
                            <input type="number" name="capacity" class="form-control" value="<?php echo e($form_data['capacity'] ?? 30); ?>" min="1" max="500">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Instructor</label>
                            <select name="instructor_id" class="form-select">
                                <option value="">-- Select Instructor --</option>
                                <?php foreach ($instructors as $inst): ?>
                                    <option value="<?php echo $inst['id']; ?>" <?php echo ($form_data['instructor_id'] ?? null) == $inst['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($inst['full_name'] . ' (' . ucfirst($inst['role']) . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="Active" <?php echo ($form_data['status'] ?? 'Active') === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo ($form_data['status'] ?? '') === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="Archived" <?php echo ($form_data['status'] ?? '') === 'Archived' ? 'selected' : ''; ?>>Archived</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Schedule</label>
                            <input type="text" name="schedule" class="form-control" value="<?php echo e($form_data['schedule'] ?? ''); ?>" placeholder="e.g. Mon/Wed 10:00-11:30 AM">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="4"><?php echo e($form_data['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
