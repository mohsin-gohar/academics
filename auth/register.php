<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

if (isLoggedIn()) {
    header('Location: ../dashboard.php');
    exit;
}

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
$schools = $pdo->query("SELECT id, school_name, school_code FROM schools
    WHERE is_active = 1
      AND (status IS NULL OR status <> 'suspended')
      AND (status <> 'trial' OR trial_ends_at IS NULL OR trial_ends_at >= NOW())
    ORDER BY school_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/svg+xml" href="../assets/img/academics-mark.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/edustudy-theme.css">
    <script>
    (function(){try{var t=localStorage.getItem('es_theme');if(t==='dark')document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();
    </script>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <div class="brand-icon brand-icon-logo">
                    <img src="../assets/img/academics-mark.svg" alt="<?php echo e(APP_NAME); ?> logo" width="44" height="44">
                </div>
                <h3 class="mb-1"><?php echo APP_NAME; ?></h3>
                <p class="mb-0 opacity-75">Create your account</p>
            </div>
            <div class="auth-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo e($success); ?>
                    </div>
                <?php endif; ?>

                <form action="register_process.php" method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-user text-muted"></i></span>
                            <input type="text" name="full_name" class="form-control" placeholder="Enter your full name" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">School</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-school text-muted"></i></span>
                            <select name="school_id" class="form-select" required>
                                <option value="">Select your school</option>
                                <?php foreach ($schools as $school): ?>
                                    <option value="<?php echo (int)$school['id']; ?>"><?php echo e($school['school_name']); ?> (<?php echo e($school['school_code']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if (!$schools): ?>
                            <small class="text-danger">Registration is currently closed. Please contact your school administrator.</small>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-envelope text-muted"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-lock text-muted"></i></span>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Create a strong password" required minlength="6">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                <i class="fas fa-eye" id="toggleIcon1"></i>
                            </button>
                        </div>
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-lock text-muted"></i></span>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm your password" required minlength="6">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye" id="toggleIcon2"></i>
                            </button>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg" <?php echo !$schools ? 'disabled' : ''; ?>>
                            <i class="fas fa-user-plus me-2"></i> Create Account
                        </button>
                    </div>
                </form>

                <hr class="my-4">
                <div class="d-flex flex-column gap-2 text-center small">
                    <span>Already have an account? <a href="login.php" class="text-primary text-decoration-none fw-medium">Sign in</a></span>
                    <span>New school? <a href="../register.php" class="text-primary text-decoration-none fw-medium">Register your school</a></span>
                    <span><a href="../index.php" class="text-muted text-decoration-none fw-medium"><i class="fas fa-arrow-left me-1"></i>Back to Home</a></span>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(fieldId) {
            const pwd = document.getElementById(fieldId);
            const iconId = fieldId === 'password' ? 'toggleIcon1' : 'toggleIcon2';
            const icon = document.getElementById(iconId);
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                pwd.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>
