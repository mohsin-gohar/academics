<?php
/**
 * Parent Dashboard — read-only view of linked children.
 */
$page_title = 'Parent Dashboard';
require_once '../includes/header.php';
requireParent();

$schoolId = currentSchoolId();
$school = currentSchool($pdo);
$parentId = (int)$_SESSION['user_id'];
$activeAlert = getActiveEmergencyAlert($pdo, $schoolId ? (int)$schoolId : null);

$childrenStmt = $pdo->prepare("
    SELECT st.*, ps.relationship,
        (SELECT COUNT(*) FROM tasks t WHERE t.student_id = st.id AND t.school_id = st.school_id) AS task_total,
        (SELECT COUNT(*) FROM tasks t WHERE t.student_id = st.id AND t.school_id = st.school_id AND t.status = 'Completed') AS task_done,
        (SELECT COUNT(*) FROM tasks t WHERE t.student_id = st.id AND t.school_id = st.school_id AND t.status <> 'Completed' AND t.due_date < CURDATE()) AS task_overdue
    FROM parent_student ps
    JOIN students st ON st.id = ps.student_id
    WHERE ps.parent_id = ? AND ps.school_id = ?
    ORDER BY st.full_name
");
$childrenStmt->execute([$parentId, $schoolId]);
$children = $childrenStmt->fetchAll();

$totalOpen = 0; $totalDone = 0; $totalOverdue = 0;
foreach ($children as $c) {
    $totalOpen += max(0, (int)$c['task_total'] - (int)$c['task_done']);
    $totalDone += (int)$c['task_done'];
    $totalOverdue += (int)$c['task_overdue'];
}
$announcements = getActiveAnnouncements($pdo, 5);

$recentTasksStmt = $pdo->prepare("
    SELECT t.*, st.full_name AS student_name
    FROM tasks t JOIN students st ON st.id = t.student_id
    WHERE t.school_id = ? AND t.student_id IN (SELECT student_id FROM parent_student WHERE parent_id = ? AND school_id = ?)
    ORDER BY t.created_at DESC LIMIT 6
");
$recentTasksStmt->execute([$schoolId, $parentId, $schoolId]);
$recentTasks = $recentTasksStmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1">Welcome, <?php echo e($_SESSION['user_name']); ?></h2>
        <p class="text-muted mb-0 small">Here is how your children are doing at <strong><?php echo e($school['school_name'] ?? 'your school'); ?></strong>.</p>
    </div>
    <a href="children.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-user-graduate me-1"></i>My Children</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-xl-3">
        <div class="kpi-card kpi-accent-indigo">
            <div class="kpi-icon icon-indigo"><i class="fas fa-children"></i></div>
            <div class="kpi-lbl">Children</div>
            <div class="kpi-val"><?php echo count($children); ?></div>
            <div class="kpi-sub">linked to your account</div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="kpi-card kpi-accent-amber">
            <div class="kpi-icon icon-amber"><i class="fas fa-clipboard-list"></i></div>
            <div class="kpi-lbl">Open Tasks</div>
            <div class="kpi-val"><?php echo $totalOpen; ?></div>
            <div class="kpi-sub">across all children</div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="kpi-card kpi-accent-emerald">
            <div class="kpi-icon icon-emerald"><i class="fas fa-circle-check"></i></div>
            <div class="kpi-lbl">Completed</div>
            <div class="kpi-val"><?php echo $totalDone; ?></div>
            <div class="kpi-sub">tasks finished</div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="kpi-card kpi-accent-rose">
            <div class="kpi-icon icon-rose"><i class="fas fa-triangle-exclamation"></i></div>
            <div class="kpi-lbl">Overdue</div>
            <div class="kpi-val"><?php echo $totalOverdue; ?></div>
            <div class="kpi-sub">needs attention</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-7">
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-user-graduate me-2 text-primary"></i>My Children</div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($children as $c): ?>
                    <?php $pct = (int)$c['task_total'] > 0 ? round((int)$c['task_done'] / (int)$c['task_total'] * 100) : 0; ?>
                    <div class="col-12 col-md-6">
                        <div class="child-card h-100">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($c['full_name']); ?>&background=1d3557&color=fff&size=96" alt="<?php echo e($c['full_name']); ?>" class="child-avatar">
                                <div>
                                    <div class="fw-bold"><?php echo e($c['full_name']); ?></div>
                                    <div class="small text-muted"><?php echo e($c['student_number']); ?> · <?php echo e(ucfirst($c['relationship'])); ?></div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Task completion</span><span class="fw-semibold"><?php echo $pct; ?>%</span></div>
                            <div class="progress mb-3" style="height:6px;"><div class="progress-bar bg-success" style="width:<?php echo $pct; ?>%"></div></div>
                            <div class="d-flex justify-content-between small">
                                <span class="text-muted"><?php echo (int)$c['task_done']; ?> done · <?php echo max(0, (int)$c['task_total'] - (int)$c['task_done']); ?> open</span>
                                <?php if ((int)$c['task_overdue']): ?><span class="text-danger fw-semibold"><?php echo (int)$c['task_overdue']; ?> overdue</span><?php endif; ?>
                            </div>
                            <a href="../students/show.php?id=<?php echo (int)$c['id']; ?>" class="btn btn-sm btn-outline-primary w-100 mt-3"><i class="fas fa-id-card me-1"></i>View Profile</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!$children): ?>
                    <div class="col-12"><div class="empty-state text-center py-4"><i class="fas fa-user-graduate fa-2x text-muted mb-2 d-block"></i><p class="text-muted mb-0">No children are linked to your account yet. Please contact the school office.</p></div></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-list-check me-2 text-primary"></i>Recent Tasks</span>
                <a href="tasks.php" class="btn btn-sm btn-outline-secondary">View all</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Task</th><th>Child</th><th>Due</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentTasks as $t): ?>
                            <tr>
                                <td class="fw-semibold small"><?php echo e($t['title']); ?></td>
                                <td class="small text-muted"><?php echo e($t['student_name']); ?></td>
                                <td class="small text-muted"><?php echo $t['due_date'] ? formatDate($t['due_date']) : '—'; ?></td>
                                <td><span class="badge bg-<?php echo $t['status'] === 'Completed' ? 'success' : ($t['status'] === 'In Progress' ? 'info' : 'warning'); ?>"><?php echo e($t['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$recentTasks): ?><tr><td colspan="4" class="text-center text-muted py-4">No tasks yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="card">
            <div class="card-header"><i class="fas fa-bullhorn me-2 text-primary"></i>School Announcements</div>
            <div class="card-body">
                <?php foreach ($announcements as $a): ?>
                <div class="announcement-item pb-3 mb-3 border-bottom border-light">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="fw-semibold small mb-1"><?php echo e($a['title']); ?></div>
                        <span class="badge bg-<?php echo $a['priority'] === 'high' ? 'danger' : ($a['priority'] === 'medium' ? 'warning' : 'secondary'); ?> ms-2"><?php echo e($a['priority']); ?></span>
                    </div>
                    <p class="text-muted small mb-1"><?php echo nl2br(e(mb_substr($a['content'], 0, 180))); ?><?php echo mb_strlen($a['content']) > 180 ? '…' : ''; ?></p>
                    <div class="text-muted" style="font-size:.72rem;"><?php echo formatDateTime($a['created_at']); ?></div>
                </div>
                <?php endforeach; ?>
                <?php if (!$announcements): ?>
                <div class="text-center py-4"><i class="fas fa-inbox fa-2x text-muted mb-2 d-block"></i><p class="text-muted small mb-0">No active announcements right now.</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/school_alert_modal.php'; ?>
<?php require_once '../includes/footer.php'; ?>
