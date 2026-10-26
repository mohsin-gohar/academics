<?php
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// CSRF validation
$csrf_token = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrf_token)) {
    header('Location: login.php?error=' . urlencode('Invalid security token. Please try again.'));
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    header('Location: login.php?error=' . urlencode('Please enter both email and password.'));
    exit;
}

// Rate limiting check
if (!checkLoginRateLimit($pdo, $email)) {
    header('Location: login.php?error=' . urlencode('Too many login attempts. Please try again in 15 minutes.'));
    exit;
}

$stmt = $pdo->prepare("SELECT id, full_name, email, password, role, is_active FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    recordLoginAttempt($pdo, $email);
    // Generic error - don't reveal if account exists
    header('Location: login.php?error=' . urlencode('Invalid email or password.'));
    exit;
}

if (!$user['is_active']) {
    header('Location: login.php?error=' . urlencode('Your account has been deactivated. Please contact administrator.'));
    exit;
}

// Clear login attempts on success
clearLoginAttempts($pdo, $email);

// Regenerate session ID to prevent session fixation
session_regenerate_id(true);

$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['full_name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['logged_in'] = true;

// Update last login
$stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
$stmt->execute([$user['id']]);

// Log activity
logActivity($pdo, $user['id'], 'login', 'user', $user['id'], 'User logged in');

header('Location: ../dashboard.php?success=' . urlencode('Welcome back, ' . $user['full_name'] . '!'));
exit;

