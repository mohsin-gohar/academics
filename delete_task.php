<?php
/**
 * Delete Task Handler
 * Deletes a task and redirects back to dashboard.
 */
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

$user_id = $_SESSION['user_id'];
$task_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($task_id <= 0) {
    header('Location: dashboard.php?error=' . urlencode('Invalid task ID.'));
    exit;
}

// Verify task belongs to user before deleting
$stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
$stmt->execute([$task_id, $user_id]);

if (!$stmt->fetch()) {
    header('Location: dashboard.php?error=' . urlencode('Task not found or you do not have permission to delete it.'));
    exit;
}

// Delete the task
$stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
$stmt->execute([$task_id, $user_id]);

header('Location: dashboard.php?success=' . urlencode('Task deleted successfully!'));
exit;