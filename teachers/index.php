<?php
/**
 * Teachers Directory / Management
 * EduSphere Enterprise School Management System
 */
$page_title = 'Teachers Directory';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';
requireAuth();
// C4 + H6: the teachers directory is internal — require staff and validate the
// live session; never widen with `OR school_id IS NULL`.
enforceSession($pdo);
requireStaff();

$schoolId = currentSchoolId();
if (!$schoolId) { header('Location: ../dashboard.php'); exit; }
$canManage = isAdmin();

$search = trim($_GET['search'] ?? '');
$query = "SELECT u.*,
            (SELECT COUNT(*) FROM courses c WHERE c.instructor_id = u.id AND c.school_id = ?) as course_count
          FROM users u
          WHERE u.role = 'teacher' AND u.school_id = ?";
$params = [$schoolId, $schoolId];

if (!empty($search)) {
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY u.full_name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-white"><i class="fas fa-chalkboard-teacher text-primary me-2"></i>Teachers Directory</h1>
            <p class="text-secondary small mb-0">View faculty members, assigned courses, and department roles.</p>
        </div>
        <?php if ($canManage): ?>
            <a href="create.php" class="btn btn-primary btn-sm">
                <i class="fas fa-user-plus me-1"></i> Add New Teacher
            </a>
        <?php endif; ?>
    </div>

    <!-- Filter Bar -->
    <div class="card bg-dark text-white border-secondary mb-4">
        <div class="card-body p-3">
            <form method="GET" class="row g-2">
                <div class="col-md-9">
                    <input type="text" name="search" class="form-control bg-dark text-white border-secondary" placeholder="Search teacher by name or email..." value="<?php echo e($search); ?>">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Teachers Cards Grid -->
    <div class="row g-3">
        <?php if (empty($teachers)): ?>
            <div class="col-12 text-center text-secondary py-5">
                <i class="fas fa-user-graduate fa-3x mb-3 opacity-25"></i>
                <p>No teachers found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($teachers as $t): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card bg-dark text-white border-secondary h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="rounded-circle bg-primary bg-opacity-25 p-3 text-primary fs-4">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                </div>
                                <div>
                                    <h5 class="card-title text-white mb-0"><?php echo e($t['full_name']); ?></h5>
                                    <small class="text-secondary"><?php echo e($t['email']); ?></small>
                                </div>
                            </div>
                            <div class="mt-auto pt-3 border-top border-secondary d-flex justify-content-between align-items-center">
                                <span class="badge bg-secondary"><?php echo (int)$t['course_count']; ?> Courses</span>
                                <span class="badge <?php echo $t['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $t['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                                <?php if ($canManage): ?>
                                    <a href="edit.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-outline-warning">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
