<?php
/**
 * Header Template
 * EduSphere Student Management System
 */
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();

// Include database connection
require_once __DIR__ . '/../config/db.php';

// Determine relative path prefix from current script to project root
$base = getBasePath();

// Redirect to login if not authenticated (except for auth and public portal pages)
$current_page = basename($_SERVER['PHP_SELF']);
$auth_pages = ['login.php', 'register.php', 'login_process.php', 'register_process.php'];
$public_pages = ['403.php', '404.php', '500.php', 'index.php', 'public_events.php'];

if (!in_array($current_page, $auth_pages) && !in_array($current_page, $public_pages)) {
    if (!isLoggedIn()) {
        header('Location: ' . $base . 'auth/login.php');
        exit;
    }
}

// Set session variables from DB if not set
if (isLoggedIn()) {
    if (!isset($_SESSION['user_role'])) {
        $stmt = $pdo->prepare("SELECT role, full_name, email FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
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

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo isset($page_title) ? e($page_title) . ' | ' . APP_NAME : APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base; ?>assets/css/style.css">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
</head>
<body>
    <?php 
    // Only wrap with backend ERP sidebar and topbar if logged in AND NOT on a public portal page
    $is_public = in_array($current_page, $public_pages);
    if (isLoggedIn() && !in_array($current_page, $auth_pages) && !$is_public): 
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




