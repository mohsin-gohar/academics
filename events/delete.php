<?php
/**
 * Delete School Event / Holiday
 * Academics Student Management System
 */
require_once '../includes/functions.php';
require_once '../includes/tenant.php';
startSecureSession();
require_once '../config/db.php';
requireStaff();
requirePost();

$user_id = $_SESSION['user_id'];
$token = $_POST['csrf_token'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0 || !validateCsrfToken($token)) {
    header('Location: index.php?error=' . urlencode('Invalid security token or event ID.'));
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM school_events WHERE id = ?");
$stmt->execute([$id]);
$event = $stmt->fetch();

if (!$event) {
    header('Location: index.php?error=' . urlencode('Event not found.'));
    exit;
}
require_record_school($event);

try {
    $pdo->prepare("DELETE FROM school_events WHERE id = ?")->execute([$id]);
    logActivity($pdo, $user_id, 'delete_event', 'school_events', $id, 'Deleted event: ' . $event['title']);
    setFlashMessage('success', 'Event removed successfully.');
    header('Location: index.php');
    exit;
} catch (PDOException $e) {
    error_log("Failed to delete event: " . $e->getMessage());
    header('Location: index.php?error=' . urlencode('Failed to delete event.'));
    exit;
}
