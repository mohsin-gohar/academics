<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/saas.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';

if (isLoggedIn()) {
    header('Location: ../dashboard.php');
    exit;
}

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$email = $token !== '' ? validatePasswordResetToken($pdo, $token) : null;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid or expired form token. Please try again.';
    } elseif ($email === null) {
        $error = 'This reset link is invalid or has expired.';
    } else {
        $pw = (string)($_POST['password'] ?? '');
        $pw2 = (string)($_POST['confirm_password'] ?? '');
        if (strlen($pw) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($pw !== $pw2) {
            $error = 'Passwords do not match.';
        } else {
            if (consumePasswordResetToken($pdo, $token, $pw)) {
                $success = 'Your password has been reset. You can now sign in.';
            } else {
                $error = 'This reset link is invalid or has expired.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/svg+xml" href="../assets/img/academics-mark.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/edustudy-theme.css">
    <script>(function(){try{var t=localStorage.getItem('es_theme');if(t==='dark')document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <div class="brand-icon brand-icon-logo">
                    <img src="../assets/img/academics-mark.svg" alt="<?php echo e(APP_NAME); ?> logo" width="44" height="44">
                </div>
                <h3 class="mb-1"><?php echo APP_NAME; ?></h3>
                <p class="mb-0 opacity-75">Set a new password</p>
            </div>
            <div class="auth-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo e($success); ?></div>
                    <div class="text-center small mt-3"><a href="login.php" class="text-primary text-decoration-none fw-medium">Sign in</a></div>
                <?php elseif ($email === null): ?>
                    <div class="alert alert-warning"><i class="fas fa-triangle-exclamation me-2"></i>This reset link is invalid or has expired.</div>
                    <div class="text-center small mt-3"><a href="forgot_password.php" class="text-primary text-decoration-none fw-medium">Request a new link</a></div>
                <?php else: ?>
                    <form method="POST" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="token" value="<?php echo e($token); ?>">
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-lock text-muted"></i></span>
                                <input type="password" name="password" class="form-control" placeholder="At least 8 characters" required minlength="8">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-lock text-muted"></i></span>
                                <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required minlength="8">
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-key me-2"></i> Reset Password
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>