<?php
/**
 * Course gradebook matrix (students x assessments).
 */
$page_title = 'Gradebook View';
require_once __DIR__ . '/../includes/header.php';
requireStaff();

$schoolId = (int)(currentSchoolId() ?? 0);
if ($schoolId <= 0) { header('Location: ../dashboard.php'); exit; }

$courseId = (int)($_GET['course_id'] ?? 0);
if ($courseId <= 0) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT course_code, course_name FROM courses WHERE id = ? AND school_id = ?");
$stmt->execute([$courseId, $schoolId]);
$course = $stmt->fetch();
if (!$course) { header('Location: index.php'); exit; }

$gb = getCourseGradebook($pdo, $schoolId, $courseId);
$assessments = $gb['assessments'];
$students = $gb['students'];
$marks = $gb['marks'];
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1"><i class="fas fa-table me-2 text-primary"></i>Gradebook</h2>
        <p class="text-muted mb-0 small"><?php echo e($course['course_code'] . ' — ' . $course['course_name']); ?></p>
    </div>
    <a href="index.php?course_id=<?php echo $courseId; ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Student</th>
                        <?php foreach ($assessments as $a): ?>
                            <th class="text-center" style="min-width:90px;">
                                <div class="small fw-bold"><?php echo e($a['title']); ?></div>
                                <div class="text-muted" style="font-size:.65rem;">/<?php echo (float)$a['max_marks']; ?></div>
                            </th>
                        <?php endforeach; ?>
                        <th class="text-center">Average %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr><td colspan="<?php echo count($assessments) + 2; ?>" class="text-center text-muted py-4">No enrolled students.</td></tr>
                    <?php else: ?>
                        <?php foreach ($students as $st): ?>
                            <?php
                            $sid = (int)$st['id'];
                            $weighted = 0; $w = 0;
                            ?>
                            <tr>
                                <td class="fw-semibold"><?php echo e($st['full_name']); ?></td>
                                <?php foreach ($assessments as $a): ?>
                                    <?php
                                    $m = $marks[$sid][(int)$a['id']] ?? null;
                                    $val = $m ? $m['marks_obtained'] : null;
                                    if ($val !== null && (float)$a['max_marks'] > 0) {
                                        $weighted += (((float)$val / (float)$a['max_marks']) * 100) * (float)$a['weight'];
                                        $w += (float)$a['weight'];
                                    }
                                    ?>
                                    <td class="text-center"><?php echo $val === null ? '—' : (float)$val; ?></td>
                                <?php endforeach; ?>
                                <td class="text-center">
                                    <?php $avg = $w > 0 ? round($weighted / $w, 1) : 0; ?>
                                    <span class="badge bg-<?php echo $avg >= 80 ? 'success' : ($avg >= 60 ? 'warning' : 'danger'); ?>"><?php echo $avg; ?>%</span>
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