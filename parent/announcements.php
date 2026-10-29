<?php
/**
 * Parent — School Announcements
 */
$page_title = 'Announcements';
require_once '../includes/header.php';
requireParent();

$schoolId = currentSchoolId();
$school = currentSchool($pdo);
$announcements = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.*, u.full_name AS author_name
        FROM announcements a
        LEFT JOIN users u ON u.id = a.created_by
        WHERE a.school_id = ? AND a.is_active = 1
          AND a.start_date <= NOW() AND (a.end_date IS NULL OR a.end_date >= NOW())
          AND (a.target_roles = 'all' OR a.target_roles = 'parent')
        ORDER BY a.priority DESC, a.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$schoolId]);
    $announcements = $stmt->fetchAll();
} catch (Throwable $e) {
    $announcements = [];
}
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1">School Announcements</h2>
        <p class="text-muted mb-0 small">Updates from <?php echo e($school['school_name'] ?? 'your school'); ?>.</p>
    </div>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
</div>

<div class="row g-3">
    <?php foreach ($announcements as $a): ?>
    <div class="col-12">
        <div class="card announcement-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div>
                        <h5 class="mb-1"><?php echo e($a['title']); ?></h5>
                        <div class="small text-muted mb-2">
                            <i class="fas fa-user me-1"></i><?php echo e($a['author_name'] ?: 'School Administration'); ?>
                            · <?php echo formatDateTime($a['created_at']); ?>
                        </div>
                    </div>
                    <span class="badge bg-<?php echo $a['priority'] === 'high' ? 'danger' : ($a['priority'] === 'medium' ? 'warning' : 'secondary'); ?>"><?php echo e(ucfirst($a['priority'])); ?> priority</span>
                </div>
                <p class="mb-0 text-body"><?php echo nl2br(e($a['content'])); ?></p>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$announcements): ?>
    <div class="col-12"><div class="card"><div class="card-body text-center py-5"><i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i><h5>No announcements</h5><p class="text-muted mb-0">There are no active announcements for parents right now.</p></div></div></div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
