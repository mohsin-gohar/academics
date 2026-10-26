<?php
require_once '../includes/functions.php';
require_once '../config/db.php';
requireAuth();

$user_id = $_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? '')) { 
    header('Location: index.php?error=' . urlencode('Invalid security token.')); 
    exit; 
}

$id = (int)($_POST['id'] ?? 0);
$status = $_POST['status'] ?? 'Pending';
if ($id <= 0) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$id]);
$task = $stmt->fetch();
if (!$task) { header('Location: index.php?error=' . urlencode('Task not found.')); exit; }

// Authorization check
if (isStudent()) {
    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $st = $stmt->fetch();
    if (!$st || $task['student_id'] != $st['id']) { 
        header('Location: ../errors/403.php'); 
        exit; 
    }
} elseif (!isAdmin() && $task['user_id'] != $user_id) {
    header('Location: ../errors/403.php'); 
    exit;
}

$allowed = ['Pending','In Progress','Completed','Cancelled'];
if (!in_array($status, $allowed)) $status = 'Pending';

try {
    if ($status === 'Completed') {
        $pdo->prepare("UPDATE tasks SET status = ?, completed_at = NOW(), updated_at = NOW() WHERE id = ?")->execute([$status, $id]);
    } else {
        $pdo->prepare("UPDATE tasks SET status = ?, completed_at = NULL, updated_at = NOW() WHERE id = ?")->execute([$status, $id]);
    }
    logActivity($pdo, $user_id, 'update_status', 'task', $id, 'Status changed to ' . $status);
    header('Location: show.php?id=' . $id . '&success=' . urlencode('Status updated successfully!'));
    exit;
} catch (PDOException $e) {
    header('Location: show.php?id=' . $id . '&error=' . urlencode('Failed to update status.'));
    exit;
}
