<?php
/**
 * Approve / Reject a leave request (school management only).
 */
require_once '../includes/header.php';
requireSchoolManagement();
requirePost();

$schoolId = currentSchoolId();
$userId = (int)$_SESSION['user_id'];

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Invalid security token.');
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$action = $_POST['action'] ?? '';
if ($id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    http_response_code(400);
    exit('Invalid request.');
}

// Ownership: only requests within the actor's school (super admin sees all).
$stmt = $pdo->prepare("SELECT id, user_id, status, leave_type, start_date, end_date FROM leave_requests WHERE id = ?");
$stmt->execute([$id]);
$leave = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$leave) {
    http_response_code(404);
    exit('Leave request not found.');
}
if (!isSuperAdmin() && (int)$leave['school_id'] !== $schoolId) {
    http_response_code(403);
    header('Location: ../errors/403.php');
    exit;
}
if ($leave['status'] !== 'Pending') {
    setFlashMessage('danger', 'This leave request has already been processed.');
    header('Location: index.php');
    exit;
}

$newStatus = $action === 'approve' ? 'Approved' : 'Rejected';
$stmt = $pdo->prepare("UPDATE leave_requests SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
$stmt->execute([$newStatus, $userId, $id]);

recordActivity($pdo, $userId, $action . '_leave_request', 'leave_request', (int)$id, "Leave request #{$id} {$newStatus}");

// Notify the requester.
$uStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$uStmt->execute([(int)$leave['user_id']]);
$requesterName = $uStmt->fetchColumn();
$title = 'Leave request ' . strtolower($newStatus);
$message = "Your {$leave['leave_type']} leave from " . formatDate($leave['start_date']) . " to " . formatDate($leave['end_date']) . " was {$newStatus} by " . e($_SESSION['user_name'] ?? 'an administrator') . '.';
notifyUser($pdo, (int)$leave['user_id'], $title, $message, 'leave/index.php', 'fa-calendar-times', $newStatus === 'Approved' ? 'success' : 'danger');

setFlashMessage('success', 'Leave request ' . strtolower($newStatus) . '.');
header('Location: index.php');
exit;
