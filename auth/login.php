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

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

// Resolve a school from the subdomain, or from ?school=subdomain / ?school=SCH001.
$contextSchool = saas_bootstrap_school_context($pdo);
$requestedSchool = strtolower(trim((string)($_GET['school'] ?? '')));
if ($requestedSchool !== '') {
    $candidate = saas_school_by_subdomain($pdo, $requestedSchool);
    if (!$candidate) {
        $stmt = $pdo->prepare("SELECT * FROM schools WHERE LOWER(school_code) = ? LIMIT 1");
        $stmt->execute([$requestedSchool]);
        $candidate = $stmt->fetch() ?: null;
    }
    if ($candidate) {
        $contextSchool = $candidate;
    } else {
        $error = $error ?: 'That school portal could not be found.';
    }
}

$contextState = $contextSchool ? saas_school_access_state($contextSchool) : null;
$contextWarning = ($contextState && !$contextState['allowed']) ? saas_access_message($contextState['reason']) : '';
$contextInitial = $contextSchool ? strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)$contextSchool['school_name']), 0, 2)) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/svg+xml" href="../assets/img/academics-mark.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/edustudy-theme.css">
    <script>
    (function(){try{var t=localStorage.getItem('es_theme');if(t==='dark')document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();
    </script>
    <style>
        .school-context-badge {
            display: inline-flex; align-items: center; gap: .55rem;
            background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.28);
            color: #fff; border-radius: 999px; padding: .4rem .8rem .4rem .4rem;
            font-size: .8rem; font-weight: 600;
        }
        .school-context-avatar {
            width: 26px; height: 26px; border-radius: 50%;
            display: grid; place-items: center; background: #fff; color: #4026e1;
            font-size: .66rem; font-weight: 800; letter-spacing: .02em;
        }
        .auth-context-warning { font-size: .8rem; }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <div class="brand-icon brand-icon-logo">
                    <img src="../assets/img/academics-mark.svg" alt="<?php echo e(APP_NAME); ?> logo" width="44" height="44">
                </div>
                <?php if ($contextSchool): ?>
                    <div class="mb-2">
                        <span class="school-context-badge">
                            <span class="school-context-avatar"><?php echo e($contextInitial ?: 'S'); ?></span>
                            <?php echo e($contextSchool['school_name']); ?>
                        </span>
                    </div>
                    <h3 class="mb-1"><?php echo APP_NAME; ?></h3>
                    <p class="mb-0 opacity-75">Sign in to your school portal</p>
                <?php else: ?>
                    <h3 class="mb-1"><?php echo APP_NAME; ?></h3>
                    <p class="mb-0 opacity-75">Sign in to your account</p>
                <?php endif; ?>
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
                <?php if ($contextWarning): ?>
                    <div class="alert alert-warning auth-context-warning">
                        <i class="fas fa-triangle-exclamation me-2"></i><?php echo e($contextWarning); ?>
                    </div>
                <?php endif; ?>

                <form action="login_process.php" method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <?php if ($contextSchool): ?>
                        <input type="hidden" name="school_context" value="<?php echo (int)$contextSchool['id']; ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-envelope text-muted"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="you@example.com" required autofocus>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-lock text-muted"></i></span>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-sign-in-alt me-2"></i> Sign In
                        </button>
                    </div>
                </form>

                <hr class="my-4">
                <div class="d-flex flex-column gap-2 text-center small">
                    <span><a href="forgot_password.php" class="text-primary text-decoration-none fw-medium">Forgot password?</a></span>
                    <span>Joining an existing school? <a href="register.php" class="text-primary text-decoration-none fw-medium">Create a user account</a></span>
                    <span>New to <?php echo e(APP_NAME); ?>? <a href="../register.php" class="text-primary text-decoration-none fw-medium">Register your school</a></span>
                    <span><a href="../index.php" class="text-muted text-decoration-none fw-medium"><i class="fas fa-arrow-left me-1"></i>Back to Home</a></span>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const pwd = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
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
