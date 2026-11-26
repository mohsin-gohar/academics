<?php
/**
 * Teacher Notes and Announcements Module
 * EduSphere Enterprise School Management System
 */
$page_title = 'Announcements & Notes';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';
requireAuth();
// C4: validate live session (account/role/school)
enforceSession($pdo);

$schoolId = currentSchoolId();
if (!$schoolId) { header('Location: ../dashboard.php'); exit; }
$userRole = getUserRole();
$targetRole = in_array($userRole, ['school_admin', 'admin', 'teacher'], true) ? 'teacher' : $userRole;

// Only active announcements addressed to this user's audience are visible.
$sql = "SELECT a.*, u.full_name as author_name
        FROM announcements a
        LEFT JOIN users u ON a.created_by = u.id
        WHERE a.school_id = ?
          AND a.is_active = 1
          AND a.start_date <= NOW()
          AND (a.end_date IS NULL OR a.end_date >= NOW())
          AND (a.target_roles = 'all' OR a.target_roles = ?)
        ORDER BY a.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$schoolId, $targetRole]);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-white"><i class="fas fa-sticky-note text-warning me-2"></i>Notes & Announcements</h1>
            <p class="text-secondary small mb-0">Broadcast notices and teacher updates to students, parents, and colleagues.</p>
        </div>
        <?php if (isStaff()): ?>
            <a href="create.php" class="btn btn-warning btn-sm text-dark fw-bold">
                <i class="fas fa-plus me-1"></i> Post Announcement
            </a>
        <?php endif; ?>
    </div>

    <div class="row g-3">
        <?php if (empty($notes)): ?>
            <div class="col-12 text-center text-secondary py-5">
                <i class="fas fa-bullhorn fa-3x mb-3 opacity-25"></i>
                <p>No active announcements or notes available.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notes as $n): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card bg-dark text-white border-secondary h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title text-white mb-0"><?php echo e($n['title']); ?></h5>
                                <span class="badge bg-primary"><?php echo e(ucfirst($n['target_roles'] ?? 'all')); ?></span>
                            </div>
                            <p class="card-text text-secondary small mb-3"><?php echo nl2br(e($n['content'])); ?></p>
                            <div class="d-flex justify-content-between align-items-center text-secondary small border-top border-secondary pt-2">
                                <span><i class="fas fa-user-edit me-1"></i><?php echo e($n['author_name'] ?? 'Staff'); ?></span>
                                <span><i class="fas fa-clock me-1"></i><?php echo e(date('M d, Y', strtotime($n['created_at']))); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
