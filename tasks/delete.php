<?php
require_once '../includes/functions.php';
require_once '../includes/tenant.php';
require_once '../config/db.php';
requireAuth();
requireStaff();

$user_id = $_SESSION['user_id'];
$token = $_POST['csrf_token'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

if ($id <= 0 || $token === '' || !validateCsrfToken($token)) {
    header('Location: index.php?error=' . urlencode('Invalid or missing security token.'));
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?" . (isSuperAdmin() ? "" : " AND school_id = ?"));
$params = isSuperAdmin() ? [$id] : [$id, (int)currentSchoolId()];
$stmt->execute($params);
$task = $stmt->fetch();
if (!$task) { header('Location: index.php?error=' . urlencode('Task not found.')); exit; }

if (!isSuperAdmin() && (int)$task['school_id'] !== (int)currentSchoolId()) {
    header('Location: index.php?error=' . urlencode('You do not have permission to delete this task.'));
    exit;
}

// Permission: admin can delete any, staff can delete own
if (!isAdmin() && (int)$task['created_by_user_id'] !== (int)$user_id && (int)$task['user_id'] !== (int)$user_id) {
    header('Location: index.php?error=' . urlencode('You do not have permission to delete this task.'));
    exit;
}

try {
    $del = isSuperAdmin()
        ? $pdo->prepare("DELETE FROM tasks WHERE id = ?")
        : $pdo->prepare("DELETE FROM tasks WHERE id = ? AND school_id = ?");
    $delParams = isSuperAdmin() ? [$id] : [$id, (int)currentSchoolId()];
    $del->execute($delParams);
    logActivity($pdo, $user_id, 'delete', 'task', $id, 'Deleted task: ' . $task['title']);
    header('Location: index.php?success=' . urlencode('Task deleted successfully.'));
    exit;
} catch (PDOException $e) {
    error_log("Task delete failed: " . $e->getMessage());
    header('Location: index.php?error=' . urlencode('Failed to delete task.'));
    exit;
}
