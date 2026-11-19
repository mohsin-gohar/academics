<?php
/**
 * Enter marks for an assessment.
 */
$page_title = 'Enter Marks';
require_once __DIR__ . '/../includes/header.php';
requireStaff();

$schoolId = (int)(currentSchoolId() ?? 0);
if ($schoolId <= 0) { header('Location: ../dashboard.php'); exit; }

$assessmentId = (int)($_GET['assessment_id'] ?? $_POST['assessment_id'] ?? 0);
if ($assessmentId <= 0) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT a.*, c.course_name, c.course_code FROM assessments a JOIN courses c ON c.id = a.course_id WHERE a.id = ? AND a.school_id = ?");
$stmt->execute([$assessmentId, $schoolId]);
$assessment = $stmt->fetch();
if (!$assessment) { header('Location: index.php'); exit; }

// enrolled students
$stmt = $pdo->prepare("SELECT st.id, st.full_name, st.student_number FROM students st JOIN enrollments e ON e.student_id = st.id WHERE e.course_id = ? AND e.status = 'Enrolled' AND st.school_id = ? ORDER BY st.full_name ASC");
$stmt->execute([(int)$assessment['course_id'], $schoolId]);
$students = $stmt->fetchAll();

// existing marks
$marks = [];
$mStmt = $pdo->prepare("SELECT * FROM student_marks WHERE assessment_id = ?");
$mStmt->execute([$assessmentId]);
foreach ($mStmt->fetchAll() as $m) $marks[(int)$m['student_id']] = $m;

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form token.';
    } else {
        // H4: validate every mark against the assessment maximum BEFORE saving.
        $maxMarks = (float)$assessment['max_marks'];
        $toSave = [];
        foreach ($students as $st) {
            $sid = (int)$st['id'];
            $raw = trim((string)($_POST['marks'][$sid] ?? ''));
            $remarks = trim((string)($_POST['remarks'][$sid] ?? ''));
            if ($raw === '') {
                $toSave[$sid] = [null, $remarks !== '' ? $remarks : null];
                continue;
            }
            if (!is_numeric($raw)) {
                $errors[] = $st['full_name'] . ': marks must be a number.';
                continue;
            }
            $val = (float)$raw;
            if ($val < 0 || $val > $maxMarks) {
                $errors[] = $st['full_name'] . ': marks must be between 0 and ' . $maxMarks . '.';
                continue;
            }
            $toSave[$sid] = [$val, $remarks !== '' ? $remarks : null];
        }

        if (empty($errors)) {
            $pdo->beginTransaction();
            $saved = 0;
            foreach ($toSave as $sid => [$val, $remarks]) {
                if (recordMark($pdo, $schoolId, $assessmentId, (int)$sid, $val, $remarks, (int)($_SESSION['user_id'] ?? 0))) {
                    $saved++;
                }
            }
            $pdo->commit();
            logActivity($pdo, (int)($_SESSION['user_id'] ?? 0), 'enter_marks', 'assessment', $assessmentId, "Saved marks for $saved student(s) in assessment #$assessmentId");
            setFlashMessage('success', "Marks saved for {$saved} students.");
            header('Location: view.php?course_id=' . (int)$assessment['course_id']);
            exit;
        }
    }
}
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1"><i class="fas fa-pen-to-square me-2 text-primary"></i>Enter Marks</h2>
        <p class="text-muted mb-0 small"><?php echo e($assessment['title']); ?> · <?php echo e($assessment['course_code'] . ' — ' . $assessment['course_name']); ?> · Max <?php echo (float)$assessment['max_marks']; ?></p>
    </div>
    <a href="index.php?course_id=<?php echo (int)$assessment['course_id']; ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?php echo e($e); ?></div><?php endforeach; ?></div>
<?php endif; ?>

<?php if (empty($students)): ?>
    <div class="alert alert-info">No enrolled students in this course.</div>
<?php else: ?>
<form method="post">
    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
    <input type="hidden" name="assessment_id" value="<?php echo $assessmentId; ?>">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Student</th>
                            <th style="width:130px;">Student No.</th>
                            <th style="width:130px;">Marks (/<?php echo (float)$assessment['max_marks']; ?>)</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $i => $st): ?>
                            <?php $sid = (int)$st['id']; $m = $marks[$sid] ?? null; ?>
                            <tr>
                                <td class="text-muted small"><?php echo $i + 1; ?></td>
                                <td class="fw-semibold"><?php echo e($st['full_name']); ?></td>
                                <td class="text-muted small"><?php echo e($st['student_number'] ?? '—'); ?></td>
                                <td>
                                    <input type="number" step="0.01" min="0" max="<?php echo (float)$assessment['max_marks']; ?>" name="marks[<?php echo $sid; ?>]" class="form-control form-control-sm" value="<?php echo $m ? e($m['marks_obtained']) : ''; ?>">
                                </td>
                                <td>
                                    <input type="text" name="remarks[<?php echo $sid; ?>]" class="form-control form-control-sm" value="<?php echo $m ? e($m['remarks'] ?? '') : ''; ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-end">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Marks</button>
        </div>
    </div>
</form>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>