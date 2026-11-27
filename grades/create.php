<?php
/**
 * Create a new assessment.
 */
$page_title = 'New Assessment';
require_once __DIR__ . '/../includes/header.php';
requireStaff();

$schoolId = (int)(currentSchoolId() ?? 0);
if ($schoolId <= 0) { header('Location: ../dashboard.php'); exit; }

$stmt = $pdo->prepare("SELECT id, course_code, course_name FROM courses WHERE school_id = ? ORDER BY course_name ASC");
$stmt->execute([$schoolId]);
$courses = $stmt->fetchAll();

$errors = [];
$old = ['course_id' => (int)($_GET['course_id'] ?? 0), 'title' => '', 'assessment_type' => 'exam', 'max_marks' => '100', 'weight' => '1', 'due_date' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form token.';
    } else {
        foreach (array_keys($old) as $k) { if (isset($_POST[$k])) $old[$k] = trim((string)$_POST[$k]); }
        if ((int)$old['course_id'] <= 0) $errors[] = 'Course is required.';
        if ($old['title'] === '') $errors[] = 'Title is required.';
        if (!is_numeric($old['max_marks']) || (float)$old['max_marks'] <= 0) $errors[] = 'Max marks must be positive.';
        if (!is_numeric($old['weight']) || (float)$old['weight'] <= 0) $errors[] = 'Weight must be positive.';
        if (!array_key_exists($old['assessment_type'], getAssessmentTypes())) $errors[] = 'Invalid type.';

        if (empty($errors)) {
            $id = createAssessment($pdo, $schoolId, (int)$old['course_id'], $old['title'], $old['assessment_type'], (float)$old['max_marks'], (float)$old['weight'], $old['due_date'] !== '' ? $old['due_date'] : null, $old['description'] !== '' ? $old['description'] : null, (int)($_SESSION['user_id'] ?? 0));
            if ($id > 0) {
                setFlashMessage('success', 'Assessment created.');
                header('Location: index.php?course_id=' . (int)$old['course_id']);
                exit;
            }
            $errors[] = 'Selected course does not belong to this school.';
        }
    }
}
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1"><i class="fas fa-plus-circle me-2 text-primary"></i>New Assessment</h2>
        <p class="text-muted mb-0 small">Create an exam, quiz, assignment or project.</p>
    </div>
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?php echo e($e); ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Course <span class="text-danger">*</span></label>
                    <select name="course_id" class="form-select" required>
                        <option value="">Select course</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>" <?php echo (int)$old['course_id'] === (int)$c['id'] ? 'selected' : ''; ?>><?php echo e($c['course_code'] . ' — ' . $c['course_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" value="<?php echo e($old['title']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Type</label>
                    <select name="assessment_type" class="form-select">
                        <?php foreach (getAssessmentTypes() as $k => $v): ?>
                            <option value="<?php echo e($k); ?>" <?php echo $old['assessment_type'] === $k ? 'selected' : ''; ?>><?php echo e($v); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Max Marks</label>
                    <input type="number" step="0.01" min="1" name="max_marks" class="form-control" value="<?php echo e($old['max_marks']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Weight</label>
                    <input type="number" step="0.01" min="0.01" name="weight" class="form-control" value="<?php echo e($old['weight']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="<?php echo e($old['due_date']); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?php echo e($old['description']); ?></textarea>
                </div>
            </div>
            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i> Create Assessment</button>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>