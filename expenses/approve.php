<?php
/**
 * Approve / Reject an expense (school management only).
 * Academics Student Management System
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

// Fetch expense with school scoping
$stmt = $pdo->prepare("SELECT id, title, category, amount, status, school_id, approved_by, approved_at FROM expenses WHERE id = ?");
$stmt->execute([$id]);
$expense = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$expense) {
    http_response_code(404);
    exit('Expense not found.');
}

// School scoping: only within the actor's school (super admin sees all)
if (!isSuperAdmin() && (int)$expense['school_id'] !== $schoolId) {
    http_response_code(403);
    header('Location: ../errors/403.php');
    exit;
}

if ($expense['status'] !== 'Pending') {
    setFlashMessage('danger', 'This expense has already been processed.');
    header('Location: index.php');
    exit;
}

$newStatus = $action === 'approve' ? 'Approved' : 'Rejected';
$stmt = $pdo->prepare("UPDATE expenses SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
$stmt->execute([$newStatus, $userId, $id]);

recordActivity($pdo, $userId, $action . '_expense', 'expense', (int)$id, "Expense #{$id} {$newStatus}");

setFlashMessage('success', 'Expense ' . strtolower($newStatus) . '.');
header('Location: index.php');
exit;