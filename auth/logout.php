<?php
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';

// Logout changes authentication state and therefore must be a CSRF-protected POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(405);
    exit('Method not allowed.');
}

if (isLoggedIn()) {
    logActivity($pdo, (int)$_SESSION['user_id'], 'logout', 'user', (int)$_SESSION['user_id'], 'User logged out');
}

// Clear server-side session data and expire the hardened session cookie.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => !empty($params['secure']),
        'httponly' => !empty($params['httponly']),
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}
session_destroy();

header('Location: login.php?message=' . urlencode('You have been logged out successfully.'));
exit;
