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
    if ($target['role'] === 'school_admin' && (int)$target['is_active'] === 1 && $target['school_id'] !== null) {
        $schoolAdmins = $pdo->prepare(
            "SELECT id FROM users WHERE role = 'school_admin' AND is_active = 1 AND school_id = ? FOR UPDATE"
        );
        $schoolAdmins->execute([(int)$target['school_id']]);
        if (count($schoolAdmins->fetchAll(PDO::FETCH_COLUMN)) <= 1) {
            $pdo->rollBack();
            header('Location: users.php?error=' . urlencode('Cannot delete the last active school administrator.'));
            exit;
        }
    }

    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? FOR UPDATE");
    $stmt->execute([$id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($student) {
        $studentId = (int)$student['id'];
        foreach ([
            "SELECT COUNT(*) FROM attendance WHERE student_id = ?",
            "SELECT COUNT(*) FROM student_marks WHERE student_id = ?",
            "SELECT COUNT(*) FROM invoices WHERE student_id = ?",
            "SELECT COUNT(*) FROM enrollments WHERE student_id = ?",
            "SELECT COUNT(*) FROM tasks WHERE student_id = ?"
        ] as $historySql) {
            $historyStmt = $pdo->prepare($historySql);
            $historyStmt->execute([$studentId]);
            if ((int)$historyStmt->fetchColumn() > 0) {
                $pdo->rollBack();
                header('Location: users.php?error=' . urlencode('This student has academic or fee history. Deactivate the account instead of permanently deleting it.'));
                exit;
            }
        }
        // All student history checks passed; delete only the now-unreferenced
        // profile and preserve the account's foreign-key integrity.
        $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$studentId]);
    } else {
        // Deleting staff with owned work would cascade-delete courses, tasks,
        // leave history or conversations. Require deactivation instead.
        $ownedHistory = [
            ["SELECT COUNT(*) FROM courses WHERE created_by = ?", [$id]],
            ["SELECT COUNT(*) FROM tasks WHERE user_id = ?", [$id]],
            ["SELECT COUNT(*) FROM leave_requests WHERE user_id = ?", [$id]],
            ["SELECT COUNT(*) FROM messages WHERE sender_id = ? OR receiver_id = ?", [$id, $id]],
        ];
        foreach ($ownedHistory as [$historySql, $params]) {
            $historyStmt = $pdo->prepare($historySql);
            $historyStmt->execute($params);
            if ((int)$historyStmt->fetchColumn() > 0) {
                $pdo->rollBack();
                header('Location: users.php?error=' . urlencode('This account owns historical records. Deactivate it instead of permanently deleting it.'));
                exit;
            }
        }
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
