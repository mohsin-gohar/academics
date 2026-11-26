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
        $accountHistory = $pdo->prepare(
            "SELECT
                (SELECT COUNT(*) FROM leave_requests WHERE user_id = ?)
              + (SELECT COUNT(*) FROM messages WHERE sender_id = ? OR receiver_id = ?)"
        );
        $accountHistory->execute([$id, $id, $id]);
        if ((int)$accountHistory->fetchColumn() > 0) {
            $pdo->rollBack();
            header('Location: users.php?error=' . urlencode('This student has leave or message history. Deactivate the account instead.'));
            exit;
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

    // Inspect every live FK that would cascade from this user row. The
    // explicit checks above provide friendly messages for common records;
    // this schema-driven guard also protects modules added in later migrations
    // (for example announcements, parent links, timetable ownership and invites).
    $cascadeRefs = $pdo->prepare(
        "SELECT DISTINCT k.TABLE_NAME, k.COLUMN_NAME
         FROM information_schema.KEY_COLUMN_USAGE k
         INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS r
           ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
          AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
          AND r.TABLE_NAME = k.TABLE_NAME
         WHERE k.REFERENCED_TABLE_SCHEMA = DATABASE()
           AND k.REFERENCED_TABLE_NAME = 'users'
           AND k.TABLE_SCHEMA = DATABASE()
           AND r.DELETE_RULE = 'CASCADE'"
    );
    $cascadeRefs->execute();
    foreach ($cascadeRefs->fetchAll(PDO::FETCH_ASSOC) as $reference) {
        $table = str_replace('`', '``', (string)$reference['TABLE_NAME']);
        $column = str_replace('`', '``', (string)$reference['COLUMN_NAME']);
        $dependency = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?");
        $dependency->execute([$id]);
        if ((int)$dependency->fetchColumn() > 0) {
            $pdo->rollBack();
            header('Location: users.php?error=' . urlencode('This account is referenced by other records. Deactivate it instead of permanently deleting its history.'));
            exit;
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
