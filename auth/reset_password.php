<?php
/**
 * Reset Password — C1 Security Fix
 * Validates token, enforces strong password, consumes token.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../config/db.php';

startSecureSession();

if (isLoggedIn()) {
    header('Location: ../dashboard.php');
    exit;
}

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if (!$token) {
    $error = 'Invalid or missing reset token.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid or expired form token. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        // Validate password strength
        $strengthErrors = validatePasswordStrength($password);
        if (!empty($strengthErrors)) {
            $error = implode('<br>', $strengthErrors);
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            // Capture the account for audit logging before the token is consumed.
            $resetEmail = validatePasswordResetToken($pdo, $token);
            if ($resetEmail !== null && consumePasswordResetToken($pdo, $token, $password)) {
                logActivity($pdo, null, 'password_reset_completed', 'user', null, 'Password reset completed for ' . $resetEmail);
                $success = 'Your password has been reset successfully. You can now <a href="login.php">sign in</a>.';
                $token = ''; // Prevent re-use
            } else {
                $error = 'Invalid or expired reset token. Please request a new one.';
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
                <p class="mb-0 opacity-75">Set your new password</p>
            </div>
            <div class="auth-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?></div>
                <?php endif; ?>

                <?php if ($token && !$success): ?>
                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <div class="mb-3">
                        <label class="form-label">New Password *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-lock text-muted"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="Enter new password" required minlength="10">
                        </div>
                        <div class="form-text">Must be at least 10 characters with upper, lower, number, and special character</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-lock text-muted"></i></span>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required minlength="10">
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-key me-2"></i> Reset Password
                        </button>
                    </div>
                </form>
                <?php endif; ?>

                <hr class="my-4">
                <div class="text-center small">
                    <a href="login.php" class="text-primary text-decoration-none fw-medium">Back to sign in</a>
                    <span class="ms-2"><a href="../index.php" class="text-muted text-decoration-none fw-medium"><i class="fas fa-arrow-left me-1"></i>Home</a></span>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>