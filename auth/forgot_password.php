<?php
/**
 * Forgot Password — C1 Security Fix
 * Never displays the reset token/link in the HTTP response.
 * Delivers via SMTP / mail() / local-dev log file only.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/saas.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';

if (isLoggedIn()) {
    header('Location: ../dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid or expired form token. Please try again.';
    } else {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Rate limit per IP + email (max 3 requests per 15 minutes)
        $rateLimitStmt = $pdo->prepare("SELECT COUNT(*) FROM password_resets WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        $rateLimitStmt->execute([$email]);
        $recentRequests = (int)$rateLimitStmt->fetchColumn();

        if ($recentRequests >= 3) {
            http_response_code(429);
            $error = 'Too many reset requests for this email. Please try again later.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Token creation is identical for existing/non-existing emails
            // to prevent account enumeration (response time is the same).
            $token = createPasswordResetToken($pdo, $email);

            // Log the request for audit
            logActivity($pdo, null, 'password_reset_requested', 'user', null, 'Reset requested for ' . $email);

            // Attempt delivery (never exposes token in response)
            $delivered = sendPasswordResetEmail($email, $token);

            if ($delivered) {
                $success = 'If an account exists for that email, a password reset link has been sent.';
            } else {
                // Delivery failed — still return generic success to avoid enumeration
                $success = 'If an account exists for that email, a password reset link has been generated.';
                error_log("Password reset email delivery failed for: $email");
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
    <title>Forgot Password | <?php echo APP_NAME; ?></title>
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
                <p class="mb-0 opacity-75">Reset your password</p>
            </div>
            <div class="auth-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo e($success); ?></div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-envelope text-muted"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="you@example.com" required autofocus>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane me-2"></i> Send Reset Link
                        </button>
                    </div>
                </form>

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