<?php
/**
 * Parent view of children's grades.
 */
$page_title = 'Children Grades';
require_once __DIR__ . '/../includes/header.php';
requireParent();

$parentId = (int)($_SESSION['user_id'] ?? 0);
$schoolId = (int)(currentSchoolId() ?? 0);

$stmt = $pdo->prepare("SELECT s.id, s.full_name, s.student_number FROM parent_student ps JOIN students s ON s.id = ps.student_id WHERE ps.parent_id = ? AND ps.school_id = ?");
$params = [$parentId, $schoolId];
$stmt->execute($params);
$children = $stmt->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1"><i class="fas fa-clipboard-list me-2 text-primary"></i>Children Grades</h2>
        <p class="text-muted mb-0 small">Assessment results for your linked children.</p>
    </div>
</div>

<?php if (empty($children)): ?>
    <div class="alert alert-info">No children linked to your account.</div>
<?php else: ?>
    <?php foreach ($children as $child): ?>
        <?php
        $cid = (int)$child['id'];
        $cschool = $schoolId > 0 ? $schoolId : (int)($pdo->query("SELECT school_id FROM students WHERE id = $cid")->fetchColumn() ?: 0);
        $summary = getStudentGradeSummary($pdo, $cschool, $cid);
        ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div class="fw-bold"><i class="fas fa-user-graduate me-2 text-primary"></i><?php echo e($child['full_name']); ?></div>
                <span class="badge bg-primary">Avg: <?php echo (float)$summary['average']; ?>%</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Course</th><th>Assessment</th><th>Type</th><th class="text-center">Marks</th><th class="text-center">Max</th><th class="text-center">%</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($summary['rows'])): ?>
                                <tr><td colspan="6" class="text-center text-muted py-3">No graded assessments yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($summary['rows'] as $r): ?>
                                    <?php $pct = (float)$r['max_marks'] > 0 && $r['marks_obtained'] !== null ? round(((float)$r['marks_obtained'] / (float)$r['max_marks']) * 100, 1) : null; ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo e($r['course_code']); ?></td>
                                        <td><?php echo e($r['title']); ?></td>
                                        <td><span class="badge bg-secondary bg-opacity-75"><?php echo e(getAssessmentTypes()[$r['assessment_type']] ?? $r['assessment_type']); ?></span></td>
                                        <td class="text-center"><?php echo $r['marks_obtained'] === null ? '—' : (float)$r['marks_obtained']; ?></td>
                                        <td class="text-center"><?php echo (float)$r['max_marks']; ?></td>
                                        <td class="text-center">
                                            <?php if ($pct === null): ?>—<?php else: ?>
                                                <span class="badge bg-<?php echo $pct >= 80 ? 'success' : ($pct >= 60 ? 'warning' : 'danger'); ?>"><?php echo $pct; ?>%</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>