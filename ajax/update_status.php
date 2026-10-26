<?php
/**
 * AJAX Endpoint - Update Task Status
 * EduSphere Student Management System
 */
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

// Parse request payload (JSON or Form URL Encoded)
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}

// CSRF check: verify header or token in payload
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$csrfToken = $csrfHeader ?: ($input['csrf_token'] ?? '');
if (!validateCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security token invalid or expired. Please refresh.']);
    exit;
}

$task_id = (int)($input['task_id'] ?? 0);
$new_status = trim($input['status'] ?? '');

$allowedStatuses = ['Pending', 'In Progress', 'Completed', 'Cancelled'];
if ($task_id <= 0 || !in_array($new_status, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid task ID or status value.']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Fetch task
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();

    if (!$task) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Task not found.']);
        exit;
    }

    // Authorization
    if (isStudent()) {
        $stStmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
        $stStmt->execute([$user_id]);
        $studentId = $stStmt->fetchColumn();
        if (!$studentId || (int)$task['student_id'] !== (int)$studentId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You can only update tasks assigned to you.']);
            exit;
        }
    } elseif (!isAdmin() && (int)$task['user_id'] !== (int)$user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to update this task.']);
        exit;
    }

    // Update status and completion timestamp
    if ($new_status === 'Completed') {
        $upd = $pdo->prepare("UPDATE tasks SET status = ?, completed_at = NOW(), updated_at = NOW() WHERE id = ?");
    } else {
        $upd = $pdo->prepare("UPDATE tasks SET status = ?, completed_at = NULL, updated_at = NOW() WHERE id = ?");
    }
    $upd->execute([$new_status, $task_id]);

    logActivity($pdo, $user_id, 'ajax_update_status', 'task', $task_id, 'Quick status changed to: ' . $new_status);

    $badgeClass = match($new_status) {
        'Completed' => 'bg-success',
        'In Progress' => 'bg-warning text-dark',
        'Cancelled' => 'bg-danger',
        default => 'bg-secondary'
    };

    echo json_encode([
        'success' => true,
        'message' => "Task status updated to {$new_status}",
        'task_id' => $task_id,
        'new_status' => $new_status,
        'badge_class' => $badgeClass
    ]);
} catch (PDOException $e) {
    error_log("AJAX update_status error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
}
