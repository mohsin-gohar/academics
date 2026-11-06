<?php
/**
 * Create Teacher Account
 * EduSphere Enterprise School Management System
 */
$page_title = 'Add Teacher';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';
requireAdmin();

$schoolId = currentSchoolId();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? 'Teacher@123';

        if (empty($full_name) || empty($email)) {
            $errors[] = 'Name and email are required.';
        } elseif (!isValidEmail($email)) {
            $errors[] = 'Invalid email address.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } else {
            // Check email uniqueness
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $chk->execute([$email]);
            if ($chk->fetch()) {
                $errors[] = 'An account with this email already exists.';
            } else {
                $hash = hashPassword($password);
                $ins = $pdo->prepare("INSERT INTO users (school_id, full_name, email, password, role, is_active)
                                     VALUES (?, ?, ?, ?, 'teacher', 1)");
                $ins->execute([$schoolId, $full_name, $email, $hash]);
                $teacherId = (int)$pdo->lastInsertId();
                recordActivity($pdo, $_SESSION['user_id'], 'create_teacher', 'user', $teacherId, "Added new teacher: {$full_name}");
                redirect('index.php', 'success', 'Teacher created successfully.');
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-white">Add Teacher</h1>
            <p class="text-secondary small mb-0">Create a faculty account for course instruction and grading.</p>
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
            <form method="POST" action="create.php">
                <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" class="form-control bg-dark text-white border-secondary" placeholder="e.g. Dr. Robert Vance" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email" class="form-control bg-dark text-white border-secondary" placeholder="teacher@school.com" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Initial Password (Default: Teacher@123)</label>
                        <input type="password" name="password" class="form-control bg-dark text-white border-secondary" value="Teacher@123" required>
                    </div>
                </div>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Create Teacher Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
