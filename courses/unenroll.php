<?php
require_once '../includes/functions.php';
require_once '../config/db.php';
requireStaff();

$token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
$course_id = isset($_REQUEST['course']) ? (int)$_REQUEST['course'] : 0;

if ($id <= 0 || !validateCsrfToken($token)) { 
    header('Location: ' . ($course_id ? 'show.php?id=' . $course_id : 'index.php') . '&error=' . urlencode('Invalid security token.')); 
    exit; 
}

try {
    $pdo->prepare("DELETE FROM enrollments WHERE id = ?")->execute([$id]);
    logActivity($pdo, $_SESSION['user_id'], 'unenroll', 'course', $course_id, 'Unenrolled student');
    header('Location: show.php?id=' . $course_id . '&success=' . urlencode('Student unenrolled.'));
    exit;
} catch (PDOException $e) {
    header('Location: show.php?id=' . $course_id . '&error=' . urlencode('Failed to unenroll.'));
    exit;
}
