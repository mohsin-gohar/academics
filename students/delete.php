<?php
require_once '../includes/functions.php';
require_once '../includes/tenant.php';
require_once '../config/db.php';
requireSchoolManagement();
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

    // Lock the student before checking history so concurrent attendance,
    // marks or invoices cannot slip in between the check and deletion.
    $lock = $pdo->prepare("SELECT id FROM students WHERE id = ? FOR UPDATE");
    $lock->execute([$id]);
    if (!$lock->fetchColumn()) {
        $pdo->rollBack();
        header('Location: index.php?error=' . urlencode('Student no longer exists.'));
        exit;
    }

    // Do not cascade-delete financial, attendance or grade history. Staff can
    // deactivate the account instead, preserving the student's permanent record.
    $historyChecks = [
        "SELECT COUNT(*) FROM attendance WHERE student_id = ?",
        "SELECT COUNT(*) FROM student_marks WHERE student_id = ?",
        "SELECT COUNT(*) FROM invoices WHERE student_id = ?",
        "SELECT COUNT(*) FROM enrollments WHERE student_id = ?",
        "SELECT COUNT(*) FROM tasks WHERE student_id = ?"
    ];
    foreach ($historyChecks as $sql) {
        $historyStmt = $pdo->prepare($sql);
        $historyStmt->execute([$id]);
        if ((int)$historyStmt->fetchColumn() > 0) {
            $pdo->rollBack();
            header('Location: index.php?error=' . urlencode('This student has academic or fee history and cannot be permanently deleted. Deactivate the account to preserve records.'));
            exit;
        }
    }

    $accountHistory = $pdo->prepare(
        "SELECT
            (SELECT COUNT(*) FROM leave_requests WHERE user_id = ?)
          + (SELECT COUNT(*) FROM messages WHERE sender_id = ? OR receiver_id = ?)"
    );
    $accountHistory->execute([(int)$student['user_id'], (int)$student['user_id'], (int)$student['user_id']]);
    if ((int)$accountHistory->fetchColumn() > 0) {
        $pdo->rollBack();
        header('Location: index.php?error=' . urlencode('This student has leave or message history. Deactivate the account instead.'));
        exit;
    }

    // No academic, financial, enrollment, task, leave or message history remains;
    // only now is permanent deletion safe.
    $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$id]);
    if ($student['user_id']) {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$student['user_id']]);
    }
    $pdo->commit();
    logActivity($pdo, $_SESSION['user_id'], 'delete', 'student', $id, 'Deleted student: ' . $student['full_name']);
    header('Location: index.php?success=' . urlencode('Student deleted successfully.'));
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Student delete failed: " . $e->getMessage());
    header('Location: index.php?error=' . urlencode('Failed to delete student.'));
    exit;
}
