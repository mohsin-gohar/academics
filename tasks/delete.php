<?php
require_once '../includes/functions.php';
require_once '../config/db.php';
requireAuth();

$user_id = $_SESSION['user_id'];
$token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;

if ($id <= 0 || !validateCsrfToken($token)) { 
    header('Location: index.php?error=' . urlencode('Invalid or missing security token.')); 
    exit; 
}

$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$id]);
$task = $stmt->fetch();
if (!$task) { header('Location: index.php?error=' . urlencode('Task not found.')); exit; }

if (isStudent()) { header('Location: ../errors/403.php'); exit; }
if (!isAdmin() && $task['user_id'] != $user_id) { header('Location: ../errors/403.php'); exit; }

try {
    $pdo->prepare("DELETE FROM tasks WHERE id = ?")->execute([$id]);
    logActivity($pdo, $user_id, 'delete', 'task', $id, 'Deleted task: ' . $task['title']);
    header('Location: index.php?success=' . urlencode('Task deleted successfully.'));
    exit;
} catch (PDOException $e) {
    error_log("Task delete failed: " . $e->getMessage());
    header('Location: index.php?error=' . urlencode('Failed to delete task.'));
    exit;
}

try {
    $pdo->prepare("DELETE FROM tasks WHERE id = ?")->execute([$id]);
    logActivity($pdo, $user_id, 'delete', 'task', $id, 'Deleted task: ' . $task['title']);
    header('Location: index.php?success=' . urlencode('Task deleted successfully.'));
    exit;
} catch (PDOException $e) {
    error_log("Task delete failed: " . $e->getMessage());
    header('Location: index.php?error=' . urlencode('Failed to delete task.'));
    exit;
}
