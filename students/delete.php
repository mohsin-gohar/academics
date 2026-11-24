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

// Do not cascade-delete financial, attendance or grade history. Staff can
// deactivate the account instead, preserving the student's permanent record.
$historyChecks = [
    ["SELECT COUNT(*) FROM attendance WHERE student_id = ?", [$id]],
    ["SELECT COUNT(*) FROM student_marks WHERE student_id = ?", [$id]],
    ["SELECT COUNT(*) FROM invoices WHERE student_id = ?", [$id]],
];
foreach ($historyChecks as [$sql, $params]) {
    $historyStmt = $pdo->prepare($sql);
    $historyStmt->execute($params);
    if ((int)$historyStmt->fetchColumn() > 0) {
        header('Location: index.php?error=' . urlencode('This student has academic or fee history and cannot be permanently deleted. Deactivate the account to preserve records.'));
        exit;
    }
}

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
