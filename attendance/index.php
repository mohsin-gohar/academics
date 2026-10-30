<?php
/**
 * Daily attendance view.
 */
$page_title = 'Attendance';
require_once __DIR__ . '/../includes/header.php';
requireStaff();

$schoolId = (int)(currentSchoolId() ?? 0);
if ($schoolId <= 0 && !isSuperAdmin()) {
    header('Location: ../dashboard.php');
    exit;
}
if (isSuperAdmin() && $schoolId <= 0) {
    // super admin without school context — show nothing useful
    header('Location: ../dashboard.php');
    exit;
}

$date = trim((string)($_GET['date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$students = [];
$stmt = $pdo->prepare("SELECT id, full_name, student_number, status FROM students WHERE school_id = ? ORDER BY full_name ASC");
$stmt->execute([$schoolId]);
$students = $stmt->fetchAll();
$records = getAttendanceForDate($pdo, $schoolId, $date);
$stats = getTodayAttendanceStats($pdo, $schoolId, $date);
$isStaff = isStaff();
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1"><i class="fas fa-calendar-check me-2 text-primary"></i>Daily Attendance</h2>
        <p class="text-muted mb-0 small">View and manage attendance for <?php echo e(formatDate($date)); ?>.</p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($isStaff): ?>
            <a href="mark.php?date=<?php echo urlencode($date); ?>" class="btn btn-primary btn-sm"><i class="fas fa-pen-to-square me-1"></i>Mark</a>
        <?php endif; ?>
        <a href="report.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-chart-bar me-1"></i>Reports</a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small fw-semibold mb-1">Date</label>
                <input type="date" name="date" class="form-control form-control-sm" value="<?php echo e($date); ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary" type="submit"><i class="fas fa-search me-1"></i>View</button>
            </div>
            <div class="col-auto ms-auto">
                <a href="index.php?date=<?php echo urlencode(date('Y-m-d', strtotime($date . ' -1 day'))); ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chevron-left"></i></a>
                <a href="index.php?date=<?php echo urlencode(date('Y-m-d')); ?>" class="btn btn-sm btn-outline-secondary">Today</a>
                <a href="index.php?date=<?php echo urlencode(date('Y-m-d', strtotime($date . ' +1 day'))); ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chevron-right"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['label' => 'Present', 'key' => 'present', 'icon' => 'fa-check', 'color' => 'success'],
        ['label' => 'Absent', 'key' => 'absent', 'icon' => 'fa-times', 'color' => 'danger'],
        ['label' => 'Late', 'key' => 'late', 'icon' => 'fa-clock', 'color' => 'warning'],
        ['label' => 'Excused', 'key' => 'excused', 'icon' => 'fa-user-shield', 'color' => 'info'],
    ];
    foreach ($cards as $c): ?>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-<?php echo $c['color']; ?> bg-opacity-10 text-<?php echo $c['color']; ?> d-flex align-items-center justify-content-center" style="width:42px;height:42px;">
                        <i class="fas <?php echo $c['icon']; ?>"></i>
                    </div>
                    <div>
                        <div class="text-muted small"><?php echo $c['label']; ?></div>
                        <div class="fw-bold fs-5"><?php echo (int)$stats[$c['key']]; ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Student</th>
                        <th style="width:150px;">Student No.</th>
                        <th style="width:120px;">Status</th>
                        <th>Remarks</th>
                        <?php if ($isStaff): ?><th style="width:90px;">Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr><td colspan="<?php echo $isStaff ? 6 : 5; ?>" class="text-center text-muted py-4">No students found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($students as $i => $st): ?>
                            <?php $sid = (int)$st['id']; $rec = $records[$sid] ?? null; $status = $rec['status'] ?? '—'; ?>
                            <tr>
                                <td class="text-muted small"><?php echo $i + 1; ?></td>
                                <td class="fw-semibold"><?php echo e($st['full_name']); ?></td>
                                <td class="text-muted small"><?php echo e($st['student_number'] ?? '—'); ?></td>
                                <td>
                                    <?php
                                    $badge = ['present' => 'success', 'absent' => 'danger', 'late' => 'warning', 'excused' => 'info'];
                                    $cls = $badge[$status] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $cls; ?> bg-opacity-75"><?php echo e(ucfirst($status)); ?></span>
                                </td>
                                <td class="text-muted small"><?php echo e($rec['remarks'] ?? '—'); ?></td>
                                <?php if ($isStaff): ?>
                                    <td>
                                        <a href="mark.php?date=<?php echo urlencode($date); ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fas fa-pen"></i></a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>