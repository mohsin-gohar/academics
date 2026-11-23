<?php
/**
 * Single-use auto-login endpoint.
 * URL: /auth/auto_login.php?token=<64 hex chars>
 *
 * The token is validated against a SHA-256 hash stored in user_redis_cache,
 * expires after 7 days and is invalidated on first use.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';

$token = $_GET['token'] ?? '';

// Rate limiting for auto-login attempts
if (!isset($_SESSION['auto_login_attempts'])) {
    $_SESSION['auto_login_attempts'] = 0;
}
$_SESSION['auto_login_attempts']++;
if ($_SESSION['auto_login_attempts'] > 5) {
    header('Location: login.php?error=' . urlencode('Too many auto-login attempts. Please sign in.'));
    exit;
}

// --- Validate token format (must be 64 hex chars = 256-bit hash) ---
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    header('Location: login.php?error=' . urlencode('Invalid auto-login token format.'));
    exit;
}

// Consume the single-use token only after the format check.
$row = consume_auto_login_token($pdo, $token);

if (!$row) {
    header('Location: login.php?error=' . urlencode('This auto-login link is invalid or has expired. Please sign in.'));
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id']    = (int)$row['user_id'];
$_SESSION['user_name']  = $row['full_name'];
$_SESSION['user_email'] = $row['email'];
$_SESSION['user_role']  = $row['role'];
$_SESSION['school_id']  = $row['school_id'] !== null ? (int)$row['school_id'] : null;
$_SESSION['logged_in']  = true;
$_SESSION['last_activity'] = time();
$_SESSION['remember_me'] = true;

$pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([(int)$row['user_id']]);
tenant_log($pdo, 'auto_login', 'user', (int)$row['user_id'], 'Signed in with a single-use auto-login token');

header('Location: ' . getBasePath() . tenant_home_for_role($row['role']) . '?success=' . urlencode('Welcome back, ' . $row['full_name'] . '!'));
exit;
