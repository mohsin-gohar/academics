<?php
require_once '../includes/functions.php';
require_once '../includes/tenant.php';
require_once '../config/db.php';
requireAdmin();
requirePost();

$token = $_POST['csrf_token'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0 || $id == $_SESSION['user_id'] || !validateCsrfToken($token)) { 
    header('Location: users.php?error=' . urlencode('Invalid request or security token.')); 
    exit; 
}

$guard = $pdo->prepare("SELECT school_id FROM users WHERE id = ?");
$guard->execute([$id]);
$target = $guard->fetch();
if (!$target) { header('Location: users.php?error=' . urlencode('User not found.')); exit; }
require_record_school($target);

$stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
$stmt->execute([$id]);
$u = $stmt->fetch();
if ($u) {
    $new = $u['is_active'] ? 0 : 1;
    $pdo->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?")->execute([$new, $id]);
    logActivity($pdo, $_SESSION['user_id'], 'toggle', 'user', $id, 'Toggled user status');
}
header('Location: users.php?success=' . urlencode('User status updated.'));
exit;
