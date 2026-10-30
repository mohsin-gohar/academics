<?php
/**
 * Mark daily attendance (school staff).
 */
$page_title = 'Mark Attendance';
require_once __DIR__ . '/../includes/header.php';
requireStaff();

$schoolId = (int)(currentSchoolId() ?? 0);
if ($schoolId <= 0) {
    header('Location: ../dashboard.php');
    exit;
}

$date = trim((string)($_GET['date'] ?? $_POST['date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$stmt = $pdo->prepare("SELECT id, full_name, student_number FROM students WHERE school_id = ? AND status = 'Active' ORDER BY full_name ASC");
$stmt->execute([$schoolId]);
$students = $stmt->fetchAll();
$existing = getAttendanceForDate($pdo, $schoolId, $date);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid or expired form token. Please try again.';
    } else {
        $marked = 0;
        foreach ($students as $st) {
            $sid = (int)$st['id'];
            $status = (string)($_POST['status'][$sid] ?? 'present');
            $remarks = trim((string)($_POST['remarks'][$sid] ?? ''));
            if (markAttendance($pdo, $schoolId, $sid, $date, $status, $remarks !== '' ? $remarks : null, (int)($_SESSION['user_id'] ?? 0))) {
                $marked++;
            }
        }
        setFlashMessage('success', "Attendance saved for {$marked} of " . count($students) . " students on " . formatDate($date) . ".");
        header('Location: index.php?date=' . urlencode($date));
        exit;
    }
}
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1"><i class="fas fa-calendar-check me-2 text-primary"></i>Mark Attendance</h2>
        <p class="text-muted mb-0 small">Record daily presence for every active student in your school.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php?date=<?php echo urlencode($date); ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-list me-1"></i>View Day</a>
        <a href="report.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-chart-bar me-1"></i>Reports</a>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?><div><?php echo e($e); ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1">Date</label>
                <input type="date" name="date" class="form-control form-control-sm" value="<?php echo e($date); ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary" type="submit"><i class="fas fa-calendar-day me-1"></i>Load</button>
            </div>
            <div class="col-auto ms-auto">
                <span class="text-muted small">Students: <strong><?php echo count($students); ?></strong></span>
            </div>
        </form>
    </div>
</div>

<?php if (empty($students)): ?>
    <div class="alert alert-info">No active students found in this school. Add students first.</div>
<?php else: ?>
<form method="post" action="mark.php?date=<?php echo urlencode($date); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
    <input type="hidden" name="date" value="<?php echo e($date); ?>">

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Student</th>
                            <th style="width:150px;">Student No.</th>
                            <th style="width:170px;">Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $i => $st): ?>
                            <?php $sid = (int)$st['id']; $cur = $existing[$sid]['status'] ?? 'present'; ?>
                            <tr>
                                <td class="text-muted small"><?php echo $i + 1; ?></td>
                                <td class="fw-semibold"><?php echo e($st['full_name']); ?></td>
                                <td class="text-muted small"><?php echo e($st['student_number'] ?? '—'); ?></td>
                                <td>
                                    <select name="status[<?php echo $sid; ?>]" class="form-select form-select-sm">
                                        <?php foreach (getAttendanceStatuses() as $key => $label): ?>
                                            <option value="<?php echo e($key); ?>" <?php echo $cur === $key ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="remarks[<?php echo $sid; ?>]" class="form-control form-control-sm"
                                           value="<?php echo e($existing[$sid]['remarks'] ?? ''); ?>" placeholder="Optional note">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted small">
                <i class="fas fa-info-circle me-1"></i>Default is Present. Change only exceptions.
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('select[name^=status]').forEach(s=>s.value='present')">
                    <i class="fas fa-check-double me-1"></i>All Present
                </button>
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fas fa-save me-1"></i>Save Attendance
                </button>
            </div>
        </div>
    </div>
</form>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>