<?php
/**
 * AJAX Endpoint - Update Task Status
 * Academics Student Management System
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

// C4: re-validate the live session (account active, role, school active)
enforceSession($pdo);

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

    if (!can_access_school($task['school_id'] !== null ? (int)$task['school_id'] : null)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'This task belongs to another school.']);
        exit;
    }

    // Authorization with school_id tenant isolation
    // - Students can only update their own assigned tasks (same student_id)
    // - Non-admin users can update tasks only within their school context
    // - NULL school_id (legacy/global tasks) are restricted to admins only
    // - Cross-tenant updates are prevented by school_id filtering

    // Get current user's school context
    $userSchoolId = null;
    if (isLoggedIn()) {
        // Check session school_id first
        if (isset($_SESSION['school_id']) && is_numeric($_SESSION['school_id'])) {
            $userSchoolId = (int)$_SESSION['school_id'];
        }
        // Fall back to user record school_id
        if ($userSchoolId === null) {
            $userSchoolStmt = $pdo->prepare("SELECT school_id FROM users WHERE id = ? LIMIT 1");
            $userSchoolStmt->execute([$user_id]);
            $userSchoolRow = $userSchoolStmt->fetch();
            if ($userSchoolRow && $userSchoolRow['school_id'] !== null) {
                $userSchoolId = $userSchoolRow['school_id'];
            }
        }
    }

    // Check task school_id for tenant isolation
    $taskSchoolId = $task['school_id'] ?? null;

    if (isStudent()) {
        $stStmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
        $stStmt->execute([$user_id]);
        $studentId = $stStmt->fetchColumn();
        if (!$studentId || (int)$task['student_id'] !== (int)$studentId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You can only update tasks assigned to you.']);
            exit;
        }
        // Students' tasks must have valid school context
        if ($taskSchoolId !== null && $userSchoolId !== (int)$taskSchoolId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Cross-tenant access denied.']);
            exit;
        }
    } elseif (!isAdmin()) {
        // Non-admin: must have matching school_id
        if ($taskSchoolId !== null) {
            // Task has school context - verify user's school matches
            if ($userSchoolId === null || $userSchoolId !== (int)$taskSchoolId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'You do not have permission to update this task.']);
                exit;
            }
        } else {
            // Task has NULL school_id (legacy/global) - restrict to admins only
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have permission to update this task.']);
            exit;
        }
        // Also check user_id ownership for non-admin, non-student roles
        if ((int)$task['user_id'] !== (int)$user_id) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have permission to update this task.']);
            exit;
        }
    } elseif (isAdmin()) {
        // Admin: can update any task, but still verify school context if set
        if ($taskSchoolId !== null && ($userSchoolId === null || $userSchoolId !== (int)$taskSchoolId)) {
            // School admin trying to access a different school's task
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have permission to update this task.']);
            exit;
        }
    }

    // Null-safe tenant comparison keeps legacy/global tasks distinct from
    // school-owned tasks and avoids silently converting NULL to school 0.
    $sql = $new_status === 'Completed'
        ? "UPDATE tasks SET status = ?, completed_at = COALESCE(completed_at, NOW()), updated_at = NOW() WHERE id = ? AND school_id <=> ?"
        : "UPDATE tasks SET status = ?, completed_at = NULL, updated_at = NOW() WHERE id = ? AND school_id <=> ?";
    $upd = $pdo->prepare($sql);
    $upd->execute([$new_status, $task_id, $task['school_id']]);
    if ($upd->rowCount() !== 1 && (string)$task['status'] !== $new_status) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Task changed before the status update could be saved.']);
        exit;
    }

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
