<?php
/**
 * Parent view of children's attendance.
 */
$page_title = 'Children Attendance';
require_once __DIR__ . '/../includes/header.php';
requireParent();

$parentId = (int)($_SESSION['user_id'] ?? 0);
$schoolId = (int)(currentSchoolId() ?? 0);

// linked children
$stmt = $pdo->prepare("SELECT s.id, s.full_name, s.student_number
                       FROM parent_student ps JOIN students s ON s.id = ps.student_id
                       WHERE ps.parent_id = ? AND ps.school_id = ?");
$params = [$parentId, $schoolId];
$stmt->execute($params);
$children = $stmt->fetchAll();

$from = trim((string)($_GET['from'] ?? date('Y-m-d', strtotime('-30 days'))));
$to = trim((string)($_GET['to'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = date('Y-m-d');
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1"><i class="fas fa-calendar-check me-2 text-primary"></i>Children Attendance</h2>
        <p class="text-muted mb-0 small">Attendance summary and recent records for your linked children.</p>
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

<?php if (empty($children)): ?>
    <div class="alert alert-info">No children are linked to your account yet.</div>
<?php else: ?>
    <?php foreach ($children as $child): ?>
        <?php
        $cid = (int)$child['id'];
        $cschool = $schoolId > 0 ? $schoolId : (int)($pdo->query("SELECT school_id FROM students WHERE id = $cid")->fetchColumn() ?: 0);
        $summary = getStudentAttendanceSummary($pdo, $cschool, $cid, $from, $to);
        $records = getAttendanceRecords($pdo, $cschool, $from, $to, $cid);
        $recent = array_slice($records, 0, 10);
        ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div class="fw-bold"><i class="fas fa-user-graduate me-2 text-primary"></i><?php echo e($child['full_name']); ?></div>
                <span class="badge bg-primary"><?php echo e($child['student_number'] ?? ''); ?></span>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-3"><div class="text-muted small">Total</div><div class="fw-bold fs-5"><?php echo (int)$summary['total']; ?></div></div>
                    <div class="col-6 col-md-3"><div class="text-muted small">Present</div><div class="fw-bold fs-5 text-success"><?php echo (int)$summary['counts']['present']; ?></div></div>
                    <div class="col-6 col-md-3"><div class="text-muted small">Absent</div><div class="fw-bold fs-5 text-danger"><?php echo (int)$summary['counts']['absent']; ?></div></div>
                    <div class="col-6 col-md-3"><div class="text-muted small">Attendance %</div><div class="fw-bold fs-5 text-primary"><?php echo (float)$summary['percentage']; ?>%</div></div>
                </div>
                <h6 class="fw-semibold small text-muted mb-2">Recent records</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Date</th><th>Status</th><th>Remarks</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-3">No records.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recent as $r): ?>
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
    <?php endforeach; ?>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>