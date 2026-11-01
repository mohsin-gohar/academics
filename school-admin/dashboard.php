<?php
/**
 * School Admin Dashboard — strictly scoped to the admin's own school.
 */
$page_title = 'School Dashboard';
require_once '../includes/header.php';
requireSchoolManagement();

if (isSuperAdmin() || currentSchoolId() === null) {
    header('Location: ../dashboard.php');
    exit;
}

$schoolId = currentSchoolId();
$school = currentSchool($pdo);
if (!$school) {
    header('Location: ../dashboard.php');
    exit;
}

$activeAlert = getActiveEmergencyAlert($pdo, $schoolId);

// ---------------------------------------------------------------- KPIs
$stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id = ? AND status = 'Active'");
$stmt->execute([$schoolId]);
$totalStudents = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE school_id = ? AND role = 'teacher' AND is_active = 1");
$stmt->execute([$schoolId]);
$totalTeachers = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE school_id = ? AND role = 'parent' AND is_active = 1");
$stmt->execute([$schoolId]);
$totalParents = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE school_id = ? AND status <> 'Completed'");
$stmt->execute([$schoolId]);
$openTasks = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) AS total, SUM(status = 'Completed') AS done FROM tasks WHERE school_id = ?");
$stmt->execute([$schoolId]);
$taskRow = $stmt->fetch();
$completion = ((int)($taskRow['total'] ?? 0)) > 0 ? round(((int)$taskRow['done'] / (int)$taskRow['total']) * 100) : 0;

$todayAtt = getTodayAttendanceStats($pdo, $schoolId);

// ---------------------------------------------------------------- Lists
$stmt = $pdo->prepare("SELECT st.*, u.is_active FROM students st LEFT JOIN users u ON u.id = st.user_id WHERE st.school_id = ? ORDER BY st.created_at DESC LIMIT 6");
$stmt->execute([$schoolId]);
$recentStudents = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT e.* FROM school_events e WHERE e.school_id = ? AND e.event_date >= CURDATE() AND e.status <> 'Cancelled' ORDER BY e.event_date ASC LIMIT 5");
$stmt->execute([$schoolId]);
$upcomingEvents = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT t.status, COUNT(*) c FROM tasks t WHERE t.school_id = ? GROUP BY t.status");
$stmt->execute([$schoolId]);
$statusCounts = ['Pending' => 0, 'In Progress' => 0, 'Completed' => 0];
foreach ($stmt->fetchAll() as $row) $statusCounts[$row['status']] = (int)$row['c'];
$totalTaskCount = array_sum($statusCounts);

$stmt = $pdo->prepare("SELECT u.*, s.school_name FROM users u LEFT JOIN schools s ON s.id = u.school_id WHERE u.school_id = ? ORDER BY u.created_at DESC LIMIT 5");
$stmt->execute([$schoolId]);
$recentUsers = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1"><?php echo e($school['school_name']); ?></h2>
        <p class="text-muted mb-0 small"><i class="fas fa-id-badge me-1"></i><?php echo e($school['school_code']); ?> · <?php echo e($school['subscription_plan']); ?> plan · Session <?php echo (int)($school['session_timeout_minutes'] ?? 30); ?> min</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="../attendance/mark.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-calendar-check me-1"></i>Mark Attendance</a>
        <a href="../grades/index.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-clipboard-list me-1"></i>Grades</a>
        <a href="../fees/invoices.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-file-invoice me-1"></i>Fees</a>
        <a href="../admin/users.php?role=teacher" class="btn btn-outline-primary btn-sm"><i class="fas fa-chalkboard-teacher me-1"></i>Teachers</a>
        <a href="../students/create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Student</a>
    </div>
</div>

<?php
$alertManageUrl = '../admin/emergency_alert.php';
require __DIR__ . '/../includes/school_alert_modal.php';
?>


<div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-xl">
        <div class="kpi-card kpi-accent-indigo">
            <div class="kpi-icon icon-indigo"><i class="fas fa-user-graduate"></i></div>
            <div class="kpi-lbl">Students</div>
            <div class="kpi-val"><?php echo number_format($totalStudents); ?></div>
            <div class="kpi-sub">active in this school</div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl">
        <div class="kpi-card kpi-accent-cyan">
            <div class="kpi-icon icon-cyan"><i class="fas fa-chalkboard-teacher"></i></div>
            <div class="kpi-lbl">Teachers</div>
            <div class="kpi-val"><?php echo number_format($totalTeachers); ?></div>
            <div class="kpi-sub"><?php echo number_format($totalParents); ?> <?php echo $totalParents === 1 ? 'parent linked' : 'parents linked'; ?></div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl">
        <div class="kpi-card kpi-accent-amber">
            <div class="kpi-icon icon-amber"><i class="fas fa-clipboard-check"></i></div>
            <div class="kpi-lbl">Open Tasks</div>
            <div class="kpi-val"><?php echo number_format($openTasks); ?></div>
            <div class="kpi-sub">pending or in progress</div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl">
        <div class="kpi-card kpi-accent-emerald">
            <div class="kpi-icon icon-emerald"><i class="fas fa-check-double"></i></div>
            <div class="kpi-lbl">Completion</div>
            <div class="kpi-val"><?php echo $completion; ?>%</div>
            <div class="kpi-sub">of all school tasks</div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl">
        <div class="kpi-card kpi-accent-cyan">
            <div class="kpi-icon icon-cyan"><i class="fas fa-calendar-check"></i></div>
            <div class="kpi-lbl">Today's Attendance</div>
            <div class="kpi-val"><?php echo (int)$todayAtt['present']; ?>/<?php echo (int)$todayAtt['total']; ?></div>
            <div class="kpi-sub"><?php echo (int)$todayAtt['absent']; ?> absent · <?php echo (int)$todayAtt['late']; ?> late</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-user-graduate me-2 text-primary"></i>Recently Added Students</span>
                <a href="../students/index.php" class="btn btn-sm btn-outline-secondary">View all</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Student</th><th>Number</th><th>Status</th><th class="text-end">Joined</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentStudents as $st): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo e($st['full_name']); ?></div>
                                    <div class="small text-muted"><?php echo e($st['email']); ?></div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?php echo e($st['student_number']); ?></span></td>
                                <td><span class="badge bg-<?php echo $st['status'] === 'Active' ? 'success' : 'secondary'; ?>"><?php echo e($st['status']); ?></span></td>
                                <td class="text-end small text-muted"><?php echo formatDate($st['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$recentStudents): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No students yet. <a href="../students/create.php">Add the first student</a>.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-chart-pie me-2 text-primary"></i>Task Status</div>
            <div class="card-body">
                <?php foreach ($statusCounts as $label => $count): ?>
                <?php $pct = $totalTaskCount ? round($count / $totalTaskCount * 100) : 0; ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1"><span class="fw-semibold"><?php echo e($label); ?></span><span class="text-muted"><?php echo $count; ?> · <?php echo $pct; ?>%</span></div>
                    <div class="progress" style="height:6px;">
                        <div class="progress-bar bg-<?php echo $label === 'Completed' ? 'success' : ($label === 'In Progress' ? 'info' : 'warning'); ?>" style="width:<?php echo $pct; ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (!$totalTaskCount): ?><p class="text-muted small mb-0">No tasks created yet.</p><?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-calendar-alt me-2 text-primary"></i>Upcoming Events</span>
                <a href="../events/index.php" class="btn btn-sm btn-outline-secondary">All</a>
            </div>
            <div class="card-body">
                <?php foreach ($upcomingEvents as $ev): ?>
                <div class="d-flex gap-3 py-2 border-bottom border-light">
                    <div class="event-date-chip">
                        <span class="d"><?php echo date('d', strtotime($ev['event_date'])); ?></span>
                        <span class="m"><?php echo date('M', strtotime($ev['event_date'])); ?></span>
                    </div>
                    <div>
                        <div class="fw-semibold small"><?php echo e($ev['title']); ?></div>
                        <div class="text-muted small"><?php echo e($ev['event_type']); ?><?php echo ($ev['is_holiday'] && $ev['event_type'] !== 'Holiday') ? ' · Holiday' : ''; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (!$upcomingEvents): ?><p class="text-muted small mb-0">No upcoming events.</p><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
