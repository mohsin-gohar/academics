<?php
require_once '../includes/functions.php';
require_once '../includes/tenant.php';
require_once '../config/db.php';
requireAdmin();
requirePost();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
if ($id <= 0 || $id === (int)($_SESSION['user_id'] ?? 0) || !validateCsrfToken($_POST['csrf_token'] ?? '')) {
    header('Location: users.php?error=' . urlencode('Invalid request or security token.'));
    exit;
}

try {
    $pdo->beginTransaction();

    $guard = $pdo->prepare("SELECT id, school_id, role, is_active FROM users WHERE id = ? FOR UPDATE");
    $guard->execute([$id]);
    $target = $guard->fetch(PDO::FETCH_ASSOC);
    if (!$target) {
        $pdo->rollBack();
        header('Location: users.php?error=' . urlencode('User not found.'));
        exit;
    }
    require_record_school($target);

    if ($target['role'] === 'super_admin' && (int)$target['is_active'] === 1) {
        $admins = $pdo->query("SELECT id FROM users WHERE role = 'super_admin' AND is_active = 1 FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
        if (count($admins) <= 1) {
            $pdo->rollBack();
            header('Location: users.php?error=' . urlencode('Cannot delete the last active platform super administrator.'));
            exit;
        }
    }

    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? FOR UPDATE");
    $stmt->execute([$id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($student) {
        $studentId = (int)$student['id'];
        $pdo->prepare("DELETE FROM tasks WHERE student_id = ?")->execute([$studentId]);
        $pdo->prepare("DELETE FROM enrollments WHERE student_id = ?")->execute([$studentId]);
        $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$studentId]);
    }

    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    $pdo->commit();

    logActivity($pdo, (int)$_SESSION['user_id'], 'delete', 'user', $id, 'Deleted user');
    header('Location: users.php?success=' . urlencode('User deleted successfully.'));
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('User delete failed: ' . $e->getMessage());
    header('Location: users.php?error=' . urlencode('Failed to delete user.'));
    exit;
}
