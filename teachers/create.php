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
// C4: validate live session before handling the POST (this page has an early
// POST branch that runs before includes/header.php).
enforceSession($pdo);

$schoolId = currentSchoolId();
if (!$schoolId && !isSuperAdmin()) {
    http_response_code(403);
    exit('School context is required to add teachers.');
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        // C5: no default password. If the admin did not supply one, generate a
        // strong random one-time password and force a change on first login.
        $supplied = trim((string)($_POST['password'] ?? ''));
        $generated = $supplied === '';
        $password = $generated ? generateTemporaryPassword(16) : $supplied;

        if (empty($full_name) || empty($email)) {
            $errors[] = 'Name and email are required.';
        } elseif (!isValidEmail($email)) {
            $errors[] = 'Invalid email address.';
        } else {
            $pwErrors = validatePasswordStrength($password);
            if ($pwErrors) {
                $errors[] = implode(' ', $pwErrors);
            } else {
                // Check email uniqueness
                $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $chk->execute([$email]);
                if ($chk->fetch()) {
                    $errors[] = 'An account with this email already exists.';
                } else {
                    $hash = hashPassword($password);
                    $ins = $pdo->prepare("INSERT INTO users (school_id, full_name, email, password, role, is_active, must_change_password)
                                         VALUES (?, ?, ?, ?, 'teacher', 1, ?)");
                    $ins->execute([$schoolId, $full_name, $email, $hash, $generated ? 1 : 0]);
                    $teacherId = (int)$pdo->lastInsertId();
                    recordActivity($pdo, $_SESSION['user_id'], 'create_teacher', 'user', $teacherId, "Added new teacher: {$full_name}");

                    if ($generated) {
                        setFlashMessage('success',
                            'Teacher created. One-time password for ' . $email . ': ' . $password .
                            ' — share securely; they must change it on first login.');
                    } else {
                        setFlashMessage('success', 'Teacher created successfully.');
                    }
                    $redirectBase = isSuperAdmin() ? '../admin/users.php' : 'index.php';
                    header('Location: ' . $redirectBase);
                    exit;
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
                        <label class="form-label">Initial Password (optional)</label>
                        <input type="password" name="password" class="form-control bg-dark text-white border-secondary" placeholder="Leave blank to auto-generate a secure password" autocomplete="new-password">
                        <div class="form-text text-secondary">If left blank, a random one-time password is generated and shown to you once. The teacher must change it on first login.</div>
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
