<?php
/**
 * Parent — Child Progress & Report
 */
$page_title = 'Child Progress';
require_once '../includes/header.php';
requireParent();

$schoolId = currentSchoolId();
$parentId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT st.*, ps.relationship
    FROM parent_student ps
    JOIN students st ON st.id = ps.student_id
    WHERE ps.parent_id = ? AND ps.school_id = ?
    ORDER BY st.full_name
");
$stmt->execute([$parentId, $schoolId]);
$children = $stmt->fetchAll();

$selectedId = (int)($_GET['student_id'] ?? ($children[0]['id'] ?? 0));
$selected = null;
foreach ($children as $c) {
    if ((int)$c['id'] === $selectedId) { $selected = $c; break; }
}

if (!$selected && $children) {
    $selected = $children[0];
    $selectedId = (int)$selected['id'];
}

$tasks = [];
$enrollments = [];
$stats = ['total' => 0, 'done' => 0, 'open' => 0, 'overdue' => 0, 'pct' => 0];
if ($selected) {
    $stmt = $pdo->prepare("SELECT t.*, c.course_name FROM tasks t LEFT JOIN courses c ON c.id = t.course_id WHERE t.student_id = ? AND t.school_id = ? ORDER BY t.created_at DESC LIMIT 50");
    $stmt->execute([$selectedId, $schoolId]);
    $tasks = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT e.*, c.course_name, c.course_code FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE e.student_id = ? AND e.school_id = ? ORDER BY e.created_at DESC");
    $stmt->execute([$selectedId, $schoolId]);
    $enrollments = $stmt->fetchAll();

    foreach ($tasks as $t) {
        $stats['total']++;
        if ($t['status'] === 'Completed') $stats['done']++;
        else {
            $stats['open']++;
            if ($t['due_date'] && $t['due_date'] < date('Y-m-d')) $stats['overdue']++;
        }
    }
    $stats['pct'] = $stats['total'] ? round($stats['done'] / $stats['total'] * 100) : 0;
}
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1">Child Progress</h2>
        <p class="text-muted mb-0 small">Read-only academic progress for your children.</p>
    </div>
    <a href="children.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>My Children</a>
</div>

<?php if (!$children): ?>
<div class="card"><div class="card-body text-center py-5"><i class="fas fa-user-graduate fa-3x text-muted mb-3 d-block"></i><p class="text-muted mb-0">No children linked to your account.</p></div></div>
<?php else: ?>

<div class="row g-2 mb-4">
    <?php foreach ($children as $c): ?>
    <div class="col-12 col-md-4">
        <a href="progress.php?student_id=<?php echo (int)$c['id']; ?>" class="child-selector <?php echo (int)$c['id'] === $selectedId ? 'active' : ''; ?>">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($c['full_name']); ?>&background=1d3557&color=fff&size=64" alt="">
            <div>
                <div class="fw-semibold small"><?php echo e($c['full_name']); ?></div>
                <div class="text-muted" style="font-size:.75rem;"><?php echo e($c['student_number']); ?></div>
            </div>
            <?php if ((int)$c['id'] === $selectedId): ?><i class="fas fa-check-circle text-primary ms-auto"></i><?php endif; ?>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="mini-stat"><div class="mini-stat-label">Total Tasks</div><div class="mini-stat-value"><?php echo $stats['total']; ?></div></div></div>
    <div class="col-6 col-md-3"><div class="mini-stat"><div class="mini-stat-label">Completed</div><div class="mini-stat-value text-success"><?php echo $stats['done']; ?></div></div></div>
    <div class="col-6 col-md-3"><div class="mini-stat"><div class="mini-stat-label">Open</div><div class="mini-stat-value text-warning"><?php echo $stats['open']; ?></div></div></div>
    <div class="col-6 col-md-3"><div class="mini-stat"><div class="mini-stat-label">Overdue</div><div class="mini-stat-value text-danger"><?php echo $stats['overdue']; ?></div></div></div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-semibold">Overall completion</span>
            <span class="fw-bold text-primary"><?php echo $stats['pct']; ?>%</span>
        </div>
        <div class="progress" style="height:10px;"><div class="progress-bar bg-success" style="width:<?php echo $stats['pct']; ?>%"></div></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="card">
            <div class="card-header"><i class="fas fa-list-check me-2 text-primary"></i>Tasks</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Task</th><th>Course</th><th>Due</th><th>Priority</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($tasks as $t): ?>
                            <tr>
                                <td class="fw-semibold small"><?php echo e($t['title']); ?></td>
                                <td class="small text-muted"><?php echo e($t['course_name'] ?: '—'); ?></td>
                                <td class="small <?php echo ($t['status'] !== 'Completed' && $t['due_date'] && $t['due_date'] < date('Y-m-d')) ? 'text-danger fw-semibold' : 'text-muted'; ?>"><?php echo $t['due_date'] ? formatDate($t['due_date']) : '—'; ?></td>
                                <td><span class="badge bg-<?php echo $t['priority'] === 'High' ? 'danger' : ($t['priority'] === 'Medium' ? 'warning' : 'secondary'); ?>"><?php echo e($t['priority']); ?></span></td>
                                <td><span class="badge bg-<?php echo $t['status'] === 'Completed' ? 'success' : ($t['status'] === 'In Progress' ? 'info' : 'warning'); ?>"><?php echo e($t['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$tasks): ?><tr><td colspan="5" class="text-center text-muted py-4">No tasks assigned yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-book-open me-2 text-primary"></i>Enrolled Courses</div>
            <div class="card-body">
                <?php foreach ($enrollments as $en): ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-light">
                    <div>
                        <div class="fw-semibold small"><?php echo e($en['course_name']); ?></div>
                        <div class="text-muted" style="font-size:.75rem;"><?php echo e($en['course_code']); ?></div>
                    </div>
                    <span class="badge bg-<?php echo $en['status'] === 'Completed' ? 'success' : 'primary'; ?>"><?php echo e($en['status']); ?></span>
                </div>
                <?php endforeach; ?>
                <?php if (!$enrollments): ?><p class="text-muted small mb-0">No course enrollments.</p><?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
