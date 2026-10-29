<?php
require_once '../includes/functions.php';
require_once '../includes/tenant.php';
require_once '../config/db.php';
requireAdmin();

$token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;

if ($id <= 0 || $id == $_SESSION['user_id'] || !validateCsrfToken($token)) { 
    header('Location: users.php?error=' . urlencode('Invalid request or security token.')); 
    exit; 
}

$guard = $pdo->prepare("SELECT school_id FROM users WHERE id = ?");
$guard->execute([$id]);
$target = $guard->fetch();
if (!$target) { header('Location: users.php?error=' . urlencode('User not found.')); exit; }
require_record_school($target);

try {
    $pdo->beginTransaction();
    // Delete linked student if exists
    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->execute([$id]);
    $st = $stmt->fetch();
    if ($st) {
        $pdo->prepare("DELETE FROM tasks WHERE student_id = ?")->execute([$st['id']]);
        $pdo->prepare("DELETE FROM enrollments WHERE student_id = ?")->execute([$st['id']]);
        $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$st['id']]);
    }
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    $pdo->commit();
    logActivity($pdo, $_SESSION['user_id'], 'delete', 'user', $id, 'Deleted user');
    header('Location: users.php?success=' . urlencode('User deleted successfully.'));
    exit;
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("User delete failed: " . $e->getMessage());
    header('Location: users.php?error=' . urlencode('Failed to delete user.'));
    exit;
}
