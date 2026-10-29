<?php
require_once '../includes/functions.php';
require_once '../includes/tenant.php';
require_once '../config/db.php';
requireStaff();

$token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;

if ($id <= 0 || !validateCsrfToken($token)) { 
    header('Location: index.php?error=' . urlencode('Invalid or missing security token.')); 
    exit; 
}

$guard = $pdo->prepare("SELECT school_id FROM courses WHERE id = ?");
$guard->execute([$id]);
$courseSchool = $guard->fetchColumn();
if ($courseSchool === false) { header('Location: index.php?error=' . urlencode('Course not found.')); exit; }
require_record_school(['school_id' => (int)$courseSchool]);

try {
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM enrollments WHERE course_id = ?")->execute([$id]);
    $pdo->prepare("UPDATE tasks SET course_id = NULL WHERE course_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM courses WHERE id = ?")->execute([$id]);
    $pdo->commit();
    logActivity($pdo, $_SESSION['user_id'], 'delete', 'course', $id, 'Deleted course');
    header('Location: index.php?success=' . urlencode('Course deleted successfully.'));
    exit;
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Course delete failed: " . $e->getMessage());
    header('Location: index.php?error=' . urlencode('Failed to delete course.'));
    exit;
}
