<?php
/**
 * Edit Teacher Account
 * EduSphere Enterprise School Management System
 */
$page_title = 'Edit Teacher';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';
requireAdmin();

$schoolId = currentSchoolId();
$teacherId = (int)($_GET['id'] ?? 0);
$errors = [];

// Fetch teacher
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'teacher' AND (school_id = ? OR school_id IS NULL)");
$stmt->execute([$teacherId, $schoolId]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    redirect('index.php', 'error', 'Teacher not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $password = $_POST['password'] ?? '';

        if (empty($full_name) || empty($email)) {
            $errors[] = 'Name and email are required.';
        } elseif (!isValidEmail($email)) {
            $errors[] = 'Invalid email address.';
        } else {
            // Check email uniqueness excluding self
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $chk->execute([$email, $teacherId]);
            if ($chk->fetch()) {
                $errors[] = 'Another user with this email address already exists.';
            } else {
                if (!empty($password)) {
                    if (strlen($password) < 8) {
                        $errors[] = 'Password must be at least 8 characters.';
                    } else {
                        $upd = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, password = ?, is_active = ? WHERE id = ?");
                        $upd->execute([$full_name, $email, hashPassword($password), $is_active, $teacherId]);
                    }
                } else {
                    $upd = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, is_active = ? WHERE id = ?");
                    $upd->execute([$full_name, $email, $is_active, $teacherId]);
                }

                if (empty($errors)) {
                    recordActivity($pdo, $_SESSION['user_id'], 'edit_teacher', 'user', $teacherId, "Updated teacher: {$full_name}");
                    redirect('index.php', 'success', 'Teacher updated successfully.');
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-white">Edit Teacher</h1>
            <p class="text-secondary small mb-0">Update profile details and account status for faculty member.</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back to Teachers
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?php echo e($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card bg-dark text-white border-secondary">
        <div class="card-body p-4">
            <form method="POST" action="edit.php?id=<?php echo $teacherId; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" class="form-control bg-dark text-white border-secondary" value="<?php echo e($teacher['full_name']); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email" class="form-control bg-dark text-white border-secondary" value="<?php echo e($teacher['email']); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">New Password (leave blank to keep current)</label>
                        <input type="password" name="password" class="form-control bg-dark text-white border-secondary" placeholder="••••••••">
                    </div>

                    <div class="col-md-6 d-flex align-items-center mt-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?php echo $teacher['is_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label text-white" for="isActive">Account Active</label>
                        </div>
                    </div>
                </div>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
