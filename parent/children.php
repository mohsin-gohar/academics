<?php
/**
 * Parent — My Children
 */
$page_title = 'My Children';
require_once '../includes/header.php';
requireParent();

$schoolId = currentSchoolId();
$school = currentSchool($pdo);
$parentId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT st.*, ps.relationship,
        (SELECT COUNT(*) FROM tasks t WHERE t.student_id = st.id AND t.school_id = st.school_id) AS task_total,
        (SELECT COUNT(*) FROM tasks t WHERE t.student_id = st.id AND t.school_id = st.school_id AND t.status = 'Completed') AS task_done,
        (SELECT COUNT(*) FROM enrollments e WHERE e.student_id = st.id AND e.school_id = st.school_id) AS course_count
    FROM parent_student ps
    JOIN students st ON st.id = ps.student_id
    WHERE ps.parent_id = ? AND ps.school_id = ?
    ORDER BY st.full_name
");
$stmt->execute([$parentId, $schoolId]);
$children = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1">My Children</h2>
        <p class="text-muted mb-0 small">Students linked to your parent account at <?php echo e($school['school_name'] ?? ''); ?>.</p>
    </div>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
</div>

<div class="row g-4">
    <?php foreach ($children as $c): ?>
    <?php $pct = (int)$c['task_total'] > 0 ? round((int)$c['task_done'] / (int)$c['task_total'] * 100) : 0; ?>
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($c['full_name']); ?>&background=1d3557&color=fff&size=96" alt="<?php echo e($c['full_name']); ?>" class="child-avatar">
                    <div>
                        <h5 class="mb-1"><?php echo e($c['full_name']); ?></h5>
                        <div class="small text-muted"><?php echo e($c['student_number']); ?> · <?php echo e(ucfirst($c['relationship'])); ?></div>
                        <span class="badge bg-<?php echo $c['status'] === 'Active' ? 'success' : 'secondary'; ?> mt-1"><?php echo e($c['status']); ?></span>
                    </div>
                </div>
                <div class="row g-2 small mb-3">
                    <div class="col-6"><span class="text-muted d-block">Email</span><span class="fw-medium"><?php echo e($c['email']); ?></span></div>
                    <div class="col-6"><span class="text-muted d-block">Phone</span><span class="fw-medium"><?php echo e($c['phone'] ?: '—'); ?></span></div>
                    <div class="col-6"><span class="text-muted d-block">Enrolled</span><span class="fw-medium"><?php echo $c['enrollment_date'] ? formatDate($c['enrollment_date']) : '—'; ?></span></div>
                    <div class="col-6"><span class="text-muted d-block">Courses</span><span class="fw-medium"><?php echo (int)$c['course_count']; ?></span></div>
                </div>
                <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Task completion</span><span class="fw-semibold"><?php echo $pct; ?>%</span></div>
                <div class="progress mb-3" style="height:6px;"><div class="progress-bar bg-success" style="width:<?php echo $pct; ?>%"></div></div>
                <div class="d-flex gap-2">
                    <a href="progress.php?student_id=<?php echo (int)$c['id']; ?>" class="btn btn-sm btn-primary flex-fill"><i class="fas fa-chart-line me-1"></i>Progress</a>
                    <a href="../students/show.php?id=<?php echo (int)$c['id']; ?>" class="btn btn-sm btn-outline-secondary flex-fill"><i class="fas fa-id-card me-1"></i>Profile</a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$children): ?>
    <div class="col-12">
        <div class="card"><div class="card-body text-center py-5">
            <i class="fas fa-user-graduate fa-3x text-muted mb-3 d-block"></i>
            <h5>No children linked yet</h5>
            <p class="text-muted mb-0">Please contact your school administrator to link your child's profile to this account.</p>
        </div></div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
