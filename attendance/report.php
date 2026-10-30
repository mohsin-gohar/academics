<?php
/**
 * Attendance report + CSV export.
 */
$page_title = 'Attendance Report';
require_once __DIR__ . '/../includes/header.php';

$schoolId = (int)(currentSchoolId() ?? 0);
if ($schoolId <= 0 && !isSuperAdmin()) {
    header('Location: ../dashboard.php');
    exit;
}
if (isSuperAdmin() && $schoolId <= 0) {
    header('Location: ../dashboard.php');
    exit;
}

$from = trim((string)($_GET['from'] ?? date('Y-m-d', strtotime('-30 days'))));
$to = trim((string)($_GET['to'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = date('Y-m-d');
$studentId = isset($_GET['student_id']) && $_GET['student_id'] !== '' ? (int)$_GET['student_id'] : null;

$students = [];
$stmt = $pdo->prepare("SELECT id, full_name, student_number FROM students WHERE school_id = ? ORDER BY full_name ASC");
$stmt->execute([$schoolId]);
$students = $stmt->fetchAll();

// Per-student summary
$summaries = [];
foreach ($students as $st) {
    if ($studentId && (int)$st['id'] !== $studentId) continue;
    $s = getStudentAttendanceSummary($pdo, $schoolId, (int)$st['id'], $from, $to);
    $summaries[] = ['student' => $st, 'summary' => $s];
}

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'attendance_' . $from . '_to_' . $to . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student', 'Student No.', 'Total Days', 'Present', 'Absent', 'Late', 'Excused', 'Attendance %']);
    foreach ($summaries as $row) {
        $s = $row['summary'];
        fputcsv($out, [
            $row['student']['full_name'],
            $row['student']['student_number'] ?? '',
            $s['total'],
            $s['counts']['present'],
            $s['counts']['absent'],
            $s['counts']['late'],
            $s['counts']['excused'],
            $s['percentage'] . '%',
        ]);
    }
    fclose($out);
    exit;
}
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1"><i class="fas fa-chart-bar me-2 text-primary"></i>Attendance Report</h2>
        <p class="text-muted mb-0 small">Per-student attendance summary for the selected period.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-calendar-day me-1"></i>Daily View</a>
        <a href="mark.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-pen-to-square me-1"></i>Mark</a>
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
                <label class="form-label small fw-semibold mb-1">Student</label>
                <select name="student_id" class="form-select form-select-sm">
                    <option value="">All students</option>
                    <?php foreach ($students as $st): ?>
                        <option value="<?php echo (int)$st['id']; ?>" <?php echo $studentId === (int)$st['id'] ? 'selected' : ''; ?>><?php echo e($st['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary" type="submit"><i class="fas fa-filter me-1"></i>Apply</button>
            </div>
            <div class="col-auto ms-auto">
                <a href="report.php?from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>&student_id=<?php echo $studentId ? (int)$studentId : ''; ?>&export=csv" class="btn btn-sm btn-outline-success">
                    <i class="fas fa-file-csv me-1"></i>Export CSV
                </a>
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
                        <th>Student</th>
                        <th style="width:130px;">Student No.</th>
                        <th class="text-center">Total</th>
                        <th class="text-center">Present</th>
                        <th class="text-center">Absent</th>
                        <th class="text-center">Late</th>
                        <th class="text-center">Excused</th>
                        <th class="text-center">Attendance %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($summaries)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No data for this period.</td></tr>
                    <?php else: ?>
                        <?php foreach ($summaries as $row): ?>
                            <?php $s = $row['summary']; ?>
                            <tr>
                                <td class="fw-semibold"><?php echo e($row['student']['full_name']); ?></td>
                                <td class="text-muted small"><?php echo e($row['student']['student_number'] ?? '—'); ?></td>
                                <td class="text-center"><?php echo (int)$s['total']; ?></td>
                                <td class="text-center text-success"><?php echo (int)$s['counts']['present']; ?></td>
                                <td class="text-center text-danger"><?php echo (int)$s['counts']['absent']; ?></td>
                                <td class="text-center text-warning"><?php echo (int)$s['counts']['late']; ?></td>
                                <td class="text-center text-info"><?php echo (int)$s['counts']['excused']; ?></td>
                                <td class="text-center">
                                    <?php
                                    $pct = (float)$s['percentage'];
                                    $cls = $pct >= 90 ? 'success' : ($pct >= 75 ? 'warning' : 'danger');
                                    ?>
                                    <span class="badge bg-<?php echo $cls; ?>"><?php echo $pct; ?>%</span>
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