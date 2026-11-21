<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

if (isLoggedIn()) {
    logActivity($pdo, $_SESSION['user_id'], 'logout', 'user', $_SESSION['user_id'], 'User logged out');
}

// Unset all session variables
$_SESSION = [];

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header('Location: login.php?message=' . urlencode('You have been logged out successfully.'));
exit;
