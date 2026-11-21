<?php
ob_start();
/**
 * Header Template
 * Academics School Management Platform
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/saas.php';
startSecureSession();

// Include database connection
require_once __DIR__ . '/../config/db.php';

// Resolve a school subdomain (e.g. greenvalley.academics.com) when present.
$schoolContext = saas_bootstrap_school_context($pdo);

// Determine relative path prefix from current script to project root
$base = getBasePath();

// Script and routing detection
$current_script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF']);
$current_page = basename($current_script);

// Identify Auth pages (login, register, etc.)
$auth_pages = ['login.php', 'register.php', 'login_process.php', 'register_process.php'];
$is_auth = (strpos($current_script, '/auth/') !== false) || in_array($current_page, $auth_pages);

// Identify Public pages ONLY (Root index.php, public_events.php, errors)
// Subfolder index files (e.g. students/index.php, reports/index.php) MUST NOT be marked public!
$is_public_home = ($base === './' || $base === '') && ($current_page === 'index.php');
$is_public_contact = ($base === './' || $base === '') && ($current_page === 'contact.php');
$is_public_events = ($current_page === 'public_events.php');
$is_error_page = (strpos($current_script, '/errors/') !== false) || in_array($current_page, ['403.php', '404.php', '500.php']);

$is_public = $is_public_home || $is_public_contact || $is_public_events || $is_error_page;

// Redirect to login if not authenticated on internal pages
if (!$is_auth && !$is_public) {
    if (!isLoggedIn()) {
        header('Location: ' . $base . 'auth/login.php');
        exit;
    }
}

// Set session variables from DB if not set
if (isLoggedIn()) {
    if (!isset($_SESSION['user_role'])) {
        $stmt = $pdo->prepare("SELECT role, full_name, email, school_id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
        }
    }
    if (!array_key_exists('school_id', $_SESSION)) {
        $stmt = $pdo->prepare("SELECT school_id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $sid = $stmt->fetchColumn();
        if ($sid !== false && $sid !== null) {
            $_SESSION['school_id'] = (int)$sid;
        }
    }
    if (($_SESSION['user_role'] ?? '') === 'student' && !isset($_SESSION['student_id'])) {
        $st = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
        $st->execute([$_SESSION['user_id']]);
        $stuId = $st->fetchColumn();
        if ($stuId) {
            $_SESSION['student_id'] = (int)$stuId;
        }
    }
}

// Enforce session validation on every request (C4)
if (isLoggedIn() && !$is_auth && !$is_public) {
    enforceSession();
    // accounts created by admins / self-registration must set their own password
    enforcePasswordChange();
}

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($is_public): ?>
    <meta name="description" content="<?php echo e($page_description ?? 'Academics — modern school management platform for students, teachers, parents and administrators.'); ?>">
    <link rel="canonical" href="<?php echo e((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/')); ?>">
    <?php endif; ?>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="application-name" content="<?php echo e(APP_NAME); ?>">
    <title><?php echo isset($page_title) ? e($page_title) . ' | ' . APP_NAME : APP_NAME; ?></title>
    <link rel="icon" type="image/svg+xml" href="<?php echo $base; ?>assets/img/academics-mark.svg">
    <link rel="apple-touch-icon" href="<?php echo $base; ?>assets/img/academics-mark.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base; ?>assets/css/style.css">
    <link rel="stylesheet" href="<?php echo $base; ?>assets/css/edustudy-theme.css">
    <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
    <?php require_once __DIR__ . '/push.php'; $__vapid = push_ensure_vapid($pdo); ?>
    <meta name="academics-vapid-key" content="<?php echo e($__vapid['public']); ?>">
    <script>window.ACADEMICS_BASE = <?php echo json_encode($base); ?>; window.ACADEMICS_VAPID_PUBLIC_KEY = <?php echo json_encode($__vapid['public']); ?>;</script>
    <?php unset($__vapid); endif; ?>
    <script>
    // Apply the saved theme before first paint to avoid a light flash.
    (function () {
        try {
            if (!document.documentElement.getAttribute('data-theme')) {
                var t = localStorage.getItem('es_theme');
                if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
            }
        } catch (e) {}
    })();
    </script>
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
</head>
<body>
    <?php 
    // Wrap with ERP floating sidebar and main content canvas on all internal pages when logged in
    if (isLoggedIn() && !$is_auth && !$is_public): 
    ?>
    <div class="app-wrapper">
        <?php require_once __DIR__ . '/sidebar.php'; ?>
        <div class="main-content">
            <?php if ($current_page !== 'dashboard.php'): ?>
            <nav class="top-navbar">
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title mb-0"><?php echo isset($page_title) ? e($page_title) : 'Dashboard'; ?></h1>
                </div>
                <div class="navbar-actions d-flex align-items-center gap-2">
                    <?php if (isLoggedIn()): ?>
                    <?php $__unread = getUnreadNotificationCount($pdo); ?>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary position-relative" id="notificationBell" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications" aria-label="Notifications">
                            <i class="fas fa-bell"></i>
                            <?php if ($__unread > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notifUnreadBadge"><?php echo (int)$__unread; ?></span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationBell" style="min-width:340px;max-height:420px;overflow:auto">
                            <li class="dropdown-item d-flex justify-content-between align-items-center fw-semibold small">
                                <span><i class="fas fa-bell me-2 text-primary"></i>Notifications</span>
                                <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none" id="markAllReadBtn">Mark all read</button>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li id="notifList"><div class="dropdown-item text-muted small">Loading…</div></li>
                        </ul>
                    </div>
                    <?php unset($__unread); endif; ?>
                    <button class="theme-toggle" id="themeToggle" type="button" title="Toggle theme" aria-label="Toggle theme">
                        <i class="fas fa-moon"></i>
                    </button>
                    <a href="<?php echo $base; ?>index.php" target="_blank" class="btn btn-sm btn-outline-primary" title="View Public School Website">
                        <i class="fas fa-globe me-1"></i> Public Site
                    </a>
                    <a href="<?php echo $base; ?>auth/logout.php" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-sign-out-alt me-1"></i> Logout
                    </a>
                </div>
            </nav>
            <?php endif; ?>
            <div class="content-area">
    <?php endif; ?>
