<?php
require_once '../includes/functions.php';
require_once '../includes/tenant.php';
require_once '../config/db.php';
requireStaff();

$token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
$course_id = isset($_REQUEST['course']) ? (int)$_REQUEST['course'] : 0;

if ($id <= 0 || !validateCsrfToken($token)) { 
    header('Location: ' . ($course_id ? 'show.php?id=' . $course_id : 'index.php') . '&error=' . urlencode('Invalid security token.')); 
    exit; 
}

$guard = $pdo->prepare("SELECT school_id FROM enrollments WHERE id = ?");
$guard->execute([$id]);
$enrollmentSchool = $guard->fetchColumn();
if ($enrollmentSchool === false) {
    header('Location: ' . ($course_id ? 'show.php?id=' . $course_id : 'index.php') . '&error=' . urlencode('Enrollment not found.'));
    exit;
}
require_record_school(['school_id' => (int)$enrollmentSchool]);

try {
    $pdo->prepare("DELETE FROM enrollments WHERE id = ?")->execute([$id]);
    logActivity($pdo, $_SESSION['user_id'], 'unenroll', 'course', $course_id, 'Unenrolled student');
    header('Location: show.php?id=' . $course_id . '&success=' . urlencode('Student unenrolled.'));
    exit;
} catch (PDOException $e) {
    header('Location: show.php?id=' . $course_id . '&error=' . urlencode('Failed to unenroll.'));
    exit;
}
