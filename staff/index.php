<?php
/**
 * Staff Directory & Profiles
 * EduSphere Enterprise School Management System
 */
$page_title = 'Staff Profiles';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';
requireAuth();

$schoolId = currentSchoolId();
$stmt = $pdo->prepare("SELECT id, full_name, email, role, is_active, created_at, last_login
                       FROM users
                       WHERE role IN ('admin', 'school_admin', 'teacher')
                         AND (school_id = ? OR school_id IS NULL)
                       ORDER BY role, full_name ASC");
$stmt->execute([$schoolId]);
$staffMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-white"><i class="fas fa-id-badge text-primary me-2"></i>Staff Profiles</h1>
            <p class="text-secondary small mb-0">Overview of administrators, teachers, and school operations personnel.</p>
        </div>
        <?php if (isAdmin()): ?>
            <a href="../admin/create_user.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> Add Staff User
            </a>
        <?php endif; ?>
    </div>

    <div class="card bg-dark text-white border-secondary">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Staff Member</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staffMembers)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-secondary">No staff records found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($staffMembers as $s): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-secondary bg-opacity-50 p-2 text-white">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <span class="fw-bold"><?php echo e($s['full_name']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo e($s['email']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo ($s['role'] === 'admin' || $s['role'] === 'school_admin') ? 'danger' : 'primary'; ?>">
                                        <?php echo e(ucwords(str_replace('_', ' ', $s['role']))); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $s['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $s['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="small text-secondary"><?php echo $s['last_login'] ? e(date('M d, Y', strtotime($s['last_login']))) : 'Never'; ?></td>
                                <td class="small text-secondary"><?php echo e(date('M d, Y', strtotime($s['created_at']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
