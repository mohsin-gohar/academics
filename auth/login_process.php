<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/saas.php';
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

// Resolve the school portal the visitor is signing in to. The host subdomain is
// authoritative; the posted id is only used for path-based local/staging logins.
$contextSchool = saas_bootstrap_school_context($pdo);
$postedContext = (int)($_POST['school_context'] ?? 0);
if (!$contextSchool && $postedContext > 0) {
    $contextSchool = school_by_id($pdo, $postedContext);
}

$stmt = $pdo->prepare("SELECT id, full_name, email, password, role, is_active, school_id FROM users WHERE email = ?");
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

// School users may only sign in while their school is active and in good standing.
$schoolId = $user['school_id'] !== null ? (int)$user['school_id'] : null;
if ($schoolId !== null) {
    $schoolRow = school_by_id($pdo, $schoolId);
    if (!$schoolRow) {
        header('Location: login.php?error=' . urlencode('Your school could not be found. Please contact the platform administrator.'));
        exit;
    }
    $schoolState = saas_school_access_state($schoolRow);
    if (!$schoolState['allowed']) {
        header('Location: login.php?error=' . urlencode(saas_access_message($schoolState['reason'])));
        exit;
    }
}

// A school subdomain is a tenant boundary: accounts from another school cannot
// sign in there. Platform administrators may still sign in to any portal.
$isPlatformAdmin = ($user['role'] === 'super_admin') || ($user['role'] === 'admin' && $schoolId === null);
if ($contextSchool && !$isPlatformAdmin && $schoolId !== (int)$contextSchool['id']) {
    header('Location: login.php?error=' . urlencode('This account belongs to a different school portal. Please use your own school link.'));
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
$_SESSION['must_change_password'] = ((int)$user['must_change_password'] === 1);
if ($schoolId !== null) {
    $_SESSION['school_id'] = $schoolId;
} else {
    unset($_SESSION['school_id']);
}

// Update last login
$stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
$stmt->execute([$user['id']]);

// Log activity
logActivity($pdo, $user['id'], 'login', 'user', $user['id'], 'User logged in');

// Keep the Redis-compatible user cache warm for auto-login links.
try {
    tenant_user_cache_upsert($pdo, $user);
} catch (Throwable $e) {
    error_log('user cache upsert failed: ' . $e->getMessage());
}

header('Location: ../' . tenant_home_for_role($user['role']) . '?success=' . urlencode('Welcome back, ' . $user['full_name'] . '!'));
exit;

