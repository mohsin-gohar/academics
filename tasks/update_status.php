<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../config/db.php';

requireAuth();
if (!isStaff() && !isStudent()) {
    http_response_code(403);
    header('Location: ../errors/403.php');
    exit;
}
requirePost();

$userId = (int)$_SESSION['user_id'];
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    header('Location: index.php?error=' . urlencode('Invalid security token.'));
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
$status = trim((string)($_POST['status'] ?? ''));
$allowed = ['Pending', 'In Progress', 'Completed', 'Cancelled'];
if ($id <= 0 || !in_array($status, $allowed, true)) {
    header('Location: index.php?error=' . urlencode('Invalid task or status value.'));
    exit;
}

try {
    $pdo->beginTransaction();

    // Lock the task while validating ownership and changing its state.
    $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ? FOR UPDATE');
    $stmt->execute([$id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$task) {
        $pdo->rollBack();
        header('Location: index.php?error=' . urlencode('Task not found.'));
        exit;
    }

    if (!can_access_school($task['school_id'] !== null ? (int)$task['school_id'] : null)) {
        $pdo->rollBack();
        http_response_code(403);
        header('Location: ../errors/403.php');
        exit;
    }

    if (isStudent()) {
        $studentStmt = $pdo->prepare('SELECT id FROM students WHERE user_id = ? AND school_id = ? LIMIT 1');
        $studentStmt->execute([$userId, currentSchoolId()]);
        $studentId = (int)$studentStmt->fetchColumn();
        if ($studentId <= 0 || (int)$task['student_id'] !== $studentId) {
            $pdo->rollBack();
            http_response_code(403);
            header('Location: ../errors/403.php');
            exit;
        }
    } elseif (!isAdmin() && (int)$task['user_id'] !== $userId) {
        $pdo->rollBack();
        http_response_code(403);
        header('Location: ../errors/403.php');
        exit;
    }

    $update = $pdo->prepare(
        "UPDATE tasks
         SET status = ?, completed_at = CASE WHEN ? = 'Completed' THEN COALESCE(completed_at, NOW()) ELSE NULL END,
             updated_at = NOW()
         WHERE id = ? AND school_id <=> ?"
    );
    $update->execute([$status, $status, $id, $task['school_id']]);

    $pdo->commit();
    logActivity($pdo, $userId, 'update_status', 'task', $id, 'Status changed to ' . $status);
    header('Location: show.php?id=' . $id . '&success=' . urlencode('Status updated successfully!'));
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Task status update failed: ' . $e->getMessage());
    header('Location: show.php?id=' . $id . '&error=' . urlencode('Failed to update status.'));
    exit;
}
