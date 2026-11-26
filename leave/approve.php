<?php
/**
 * Approve / reject a leave request within the current tenant.
 */
require_once '../includes/header.php';
requireSchoolManagement();
requirePost();

$schoolId = currentSchoolId();
$userId = (int) $_SESSION['user_id'];

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Invalid security token.');
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
$action = $_POST['action'] ?? '';
if ($id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    http_response_code(400);
    exit('Invalid request.');
}

try {
    $pdo->beginTransaction();

    $sql = "SELECT id, school_id, user_id, status, leave_type, start_date, end_date
            FROM leave_requests WHERE id = ? FOR UPDATE";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$leave) {
        $pdo->rollBack();
        http_response_code(404);
        exit('Leave request not found.');
    }

    if (!isSuperAdmin() && (int) $leave['school_id'] !== (int) $schoolId) {
        $pdo->rollBack();
        http_response_code(403);
        header('Location: ../errors/403.php');
        exit;
    }

    if ($leave['status'] !== 'Pending') {
        $pdo->rollBack();
        setFlashMessage('danger', 'This leave request has already been processed.');
        header('Location: index.php');
        exit;
    }

    $newStatus = $action === 'approve' ? 'Approved' : 'Rejected';
    $update = $pdo->prepare(
        "UPDATE leave_requests
         SET status = ?, approved_by = ?, approved_at = NOW()
         WHERE id = ? AND status = 'Pending'"
    );
    $update->execute([$newStatus, $userId, $id]);

    if ($update->rowCount() !== 1) {
        throw new RuntimeException('Leave request was changed by another request.');
    }

    recordActivity($pdo, $userId, $action . '_leave_request', 'leave_request', $id, "Leave request #{$id} {$newStatus}");
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Leave approval failed: ' . $e->getMessage());
    http_response_code(500);
    setFlashMessage('danger', 'Unable to process this leave request. Please try again.');
    header('Location: index.php');
    exit;
}

// Notifications are sent only after the state change commits.
try {
    $uStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ? AND school_id = ?");
    $uStmt->execute([(int) $leave['user_id'], (int) $leave['school_id']]);
    if ($uStmt->fetchColumn()) {
        $title = 'Leave request ' . strtolower($newStatus);
        $message = "Your {$leave['leave_type']} leave from " . formatDate($leave['start_date']) .
            " to " . formatDate($leave['end_date']) . " was {$newStatus} by " .
            e($_SESSION['user_name'] ?? 'an administrator') . '.';
        notifyUser($pdo, (int) $leave['user_id'], $title, $message, 'leave/index.php',
            'fa-calendar-times', $newStatus === 'Approved' ? 'success' : 'danger');
    }
} catch (Throwable $e) {
    error_log('Leave notification failed: ' . $e->getMessage());
}

setFlashMessage('success', 'Leave request ' . strtolower($newStatus) . '.');
header('Location: index.php');
exit;
