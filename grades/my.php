<?php
/**
 * Student's own grades.
 */
$page_title = 'My Grades';
require_once __DIR__ . '/../includes/header.php';
requireAuth();
if (!isStudent()) { header('Location: ../dashboard.php'); exit; }

$schoolId = (int)(currentSchoolId() ?? 0);
$studentId = (int)($_SESSION['student_id'] ?? 0);
if ($schoolId <= 0 || $studentId <= 0) {
    $stmt = $pdo->prepare("SELECT id, school_id FROM students WHERE user_id = ? LIMIT 1");
    $stmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
    $row = $stmt->fetch();
    $studentId = (int)($row['id'] ?? 0);
    $schoolId = (int)($row['school_id'] ?? 0);
    $_SESSION['student_id'] = $studentId;
}
if ($schoolId <= 0 || $studentId <= 0) { header('Location: ../dashboard.php'); exit; }

$summary = getStudentGradeSummary($pdo, $schoolId, $studentId);
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1"><i class="fas fa-clipboard-list me-2 text-primary"></i>My Grades</h2>
        <p class="text-muted mb-0 small">Your assessment results across all courses.</p>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width:52px;height:52px;">
            <i class="fas fa-chart-line fa-lg"></i>
        </div>
        <div>
            <div class="text-muted small">Overall weighted average</div>
            <div class="fw-bold fs-4 text-primary"><?php echo (float)$summary['average']; ?>%</div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Course</th>
                        <th>Assessment</th>
                        <th>Type</th>
                        <th class="text-center">Marks</th>
                        <th class="text-center">Max</th>
                        <th class="text-center">%</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($summary['rows'])): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No graded assessments yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($summary['rows'] as $r): ?>
                            <?php
                            $pct = (float)$r['max_marks'] > 0 && $r['marks_obtained'] !== null ? round(((float)$r['marks_obtained'] / (float)$r['max_marks']) * 100, 1) : null;
                            ?>
                            <tr>
                                <td class="fw-semibold"><?php echo e($r['course_code'] . ' — ' . $r['course_name']); ?></td>
                                <td><?php echo e($r['title']); ?></td>
                                <td><span class="badge bg-secondary bg-opacity-75"><?php echo e(getAssessmentTypes()[$r['assessment_type']] ?? $r['assessment_type']); ?></span></td>
                                <td class="text-center"><?php echo $r['marks_obtained'] === null ? '—' : (float)$r['marks_obtained']; ?></td>
                                <td class="text-center"><?php echo (float)$r['max_marks']; ?></td>
                                <td class="text-center">
                                    <?php if ($pct === null): ?>—<?php else: ?>
                                        <span class="badge bg-<?php echo $pct >= 80 ? 'success' : ($pct >= 60 ? 'warning' : 'danger'); ?>"><?php echo $pct; ?>%</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?php echo e($r['remarks'] ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>