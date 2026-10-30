<?php
/**
 * Gradebook: list assessments by course (staff) / own grades link.
 */
$page_title = 'Gradebook';
require_once __DIR__ . '/../includes/header.php';
requireStaff();

$schoolId = (int)(currentSchoolId() ?? 0);
if ($schoolId <= 0) { header('Location: ../dashboard.php'); exit; }

// courses in this school
$stmt = $pdo->prepare("SELECT id, course_code, course_name FROM courses WHERE school_id = ? ORDER BY course_name ASC");
$stmt->execute([$schoolId]);
$courses = $stmt->fetchAll();

$selectedCourse = isset($_GET['course_id']) ? (int)$_GET['course_id'] : (count($courses) ? (int)$courses[0]['id'] : 0);
$assessments = $selectedCourse > 0 ? getAssessmentsByCourse($pdo, $schoolId, $selectedCourse) : [];
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1"><i class="fas fa-clipboard-list me-2 text-primary"></i>Gradebook</h2>
        <p class="text-muted mb-0 small">Assessments and marks per course.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>New Assessment</a>
        <?php if ($selectedCourse > 0): ?>
            <a href="view.php?course_id=<?php echo $selectedCourse; ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-table me-1"></i>Gradebook View</a>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1">Course</label>
                <select name="course_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($courses as $c): ?>
                        <option value="<?php echo (int)$c['id']; ?>" <?php echo $selectedCourse === (int)$c['id'] ? 'selected' : ''; ?>>
                            <?php echo e($c['course_code'] . ' — ' . $c['course_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th class="text-center">Max Marks</th>
                        <th class="text-center">Weight</th>
                        <th>Due Date</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assessments)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No assessments for this course yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($assessments as $a): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo e($a['title']); ?></td>
                                <td><span class="badge bg-secondary bg-opacity-75"><?php echo e(getAssessmentTypes()[$a['assessment_type']] ?? $a['assessment_type']); ?></span></td>
                                <td class="text-center"><?php echo (float)$a['max_marks']; ?></td>
                                <td class="text-center"><?php echo (float)$a['weight']; ?></td>
                                <td class="text-muted small"><?php echo $a['due_date'] ? e(formatDate($a['due_date'])) : '—'; ?></td>
                                <td class="text-center">
                                    <a href="enter.php?assessment_id=<?php echo (int)$a['id']; ?>" class="btn btn-sm btn-outline-primary" title="Enter marks"><i class="fas fa-pen"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>