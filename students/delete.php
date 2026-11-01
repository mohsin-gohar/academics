<?php
require_once '../includes/functions.php';
require_once '../includes/tenant.php';
require_once '../config/db.php';
requireStaff();
requirePost();

$token = $_POST['csrf_token'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0 || !validateCsrfToken($token)) { 
    header('Location: index.php?error=' . urlencode('Invalid or missing security token.')); 
    exit; 
}

$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();
if (!$student) { header('Location: index.php?error=' . urlencode('Student not found.')); exit; }
require_record_school($student);

try {
    $pdo->beginTransaction();
    // Delete related tasks/enrollments
    $pdo->prepare("DELETE FROM tasks WHERE student_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM enrollments WHERE student_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$id]);
    if ($student['user_id']) {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$student['user_id']]);
    }
    $pdo->commit();
    logActivity($pdo, $_SESSION['user_id'], 'delete', 'student', $id, 'Deleted student: ' . $student['full_name']);
    header('Location: index.php?success=' . urlencode('Student deleted successfully.'));
    exit;
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Student delete failed: " . $e->getMessage());
    header('Location: index.php?error=' . urlencode('Failed to delete student.'));
    exit;
}
