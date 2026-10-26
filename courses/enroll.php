<?php
require_once '../includes/functions.php';
require_once '../config/db.php';
requireStaff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCsrfToken($_POST['csrf_token'] ?? '')) { 
    header('Location: index.php?error=' . urlencode('Invalid security token.')); 
    exit; 
}

$course_id = (int)($_POST['course_id'] ?? 0);
$student_id = (int)($_POST['student_id'] ?? 0);
$enrollment_date = $_POST['enrollment_date'] ?? date('Y-m-d');

if ($course_id <= 0 || $student_id <= 0) { header('Location: index.php?error=' . urlencode('Invalid data.')); exit; }

// Check duplicate
$stmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
$stmt->execute([$student_id, $course_id]);
if ($stmt->fetch()) { header('Location: show.php?id=' . $course_id . '&error=' . urlencode('Student already enrolled.')); exit; }

try {
    $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id, enrollment_date, status) VALUES (?, ?, ?, 'Enrolled')");
    $stmt->execute([$student_id, $course_id, $enrollment_date]);
    logActivity($pdo, $_SESSION['user_id'], 'enroll', 'course', $course_id, 'Enrolled student ID ' . $student_id);
    header('Location: show.php?id=' . $course_id . '&success=' . urlencode('Student enrolled successfully!'));
    exit;
} catch (PDOException $e) {
    error_log("Enroll failed: " . $e->getMessage());
    header('Location: show.php?id=' . $course_id . '&error=' . urlencode('Failed to enroll student.'));
    exit;
}
