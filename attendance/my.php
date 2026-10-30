<?php
/**
 * Student's own attendance view.
 */
$page_title = 'My Attendance';
require_once __DIR__ . '/../includes/header.php';
requireAuth();
if (!isStudent()) {
    header('Location: ../dashboard.php');
    exit;
}

$schoolId = (int)(currentSchoolId() ?? 0);
$studentId = (int)($_SESSION['student_id'] ?? 0);
if ($schoolId <= 0 || $studentId <= 0) {
    // resolve from user
    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
    $stmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
    $studentId = (int)$stmt->fetchColumn();
    $_SESSION['student_id'] = $studentId;
}
if ($schoolId <= 0 || $studentId <= 0) {
    header('Location: ../dashboard.php');
    exit;
}

$from = trim((string)($_GET['from'] ?? date('Y-m-d', strtotime('-30 days'))));
$to = trim((string)($_GET['to'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = date('Y-m-d');

$summary = getStudentAttendanceSummary($pdo, $schoolId, $studentId, $from, $to);
$records = getAttendanceRecords($pdo, $schoolId, $from, $to, $studentId);

$stmt = $pdo->prepare("SELECT full_name, student_number FROM students WHERE id = ?");
$stmt->execute([$studentId]);
$stu = $stmt->fetch();
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1"><i class="fas fa-calendar-check me-2 text-primary"></i>My Attendance</h2>
        <p class="text-muted mb-0 small"><?php echo e($stu['full_name'] ?? ''); ?> · <?php echo e($stu['student_number'] ?? ''); ?></p>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?php echo e($from); ?>">
            </div>
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?php echo e($to); ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary" type="submit"><i class="fas fa-filter me-1"></i>Apply</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="text-muted small">Total Days</div>
                <div class="fw-bold fs-4"><?php echo (int)$summary['total']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="text-muted small">Present</div>
                <div class="fw-bold fs-4 text-success"><?php echo (int)$summary['counts']['present']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="text-muted small">Absent</div>
                <div class="fw-bold fs-4 text-danger"><?php echo (int)$summary['counts']['absent']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="text-muted small">Attendance %</div>
                <div class="fw-bold fs-4 text-primary"><?php echo (float)$summary['percentage']; ?>%</div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4">No attendance records for this period.</td></tr>
                    <?php else: ?>
                        <?php foreach ($records as $r): ?>
                            <tr>
                                <td><?php echo e(formatDate($r['attendance_date'])); ?></td>
                                <td>
                                    <?php
                                    $badge = ['present' => 'success', 'absent' => 'danger', 'late' => 'warning', 'excused' => 'info'];
                                    $cls = $badge[$r['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $cls; ?> bg-opacity-75"><?php echo e(ucfirst($r['status'])); ?></span>
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