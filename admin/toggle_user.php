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

    $newStatus = (int)$target['is_active'] === 1 ? 0 : 1;
    if ($target['role'] === 'super_admin' && $newStatus === 0) {
        $admins = $pdo->query("SELECT id FROM users WHERE role = 'super_admin' AND is_active = 1 FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
        if (count($admins) <= 1) {
            $pdo->rollBack();
            header('Location: users.php?error=' . urlencode('Cannot deactivate the last active platform super administrator.'));
            exit;
        }
    }
    if ($target['role'] === 'school_admin' && (int)$target['is_active'] === 1 && $newStatus === 0 && $target['school_id'] !== null) {
        $schoolAdmins = $pdo->prepare(
            "SELECT id FROM users WHERE role = 'school_admin' AND is_active = 1 AND school_id = ? FOR UPDATE"
        );
        $schoolAdmins->execute([(int)$target['school_id']]);
        if (count($schoolAdmins->fetchAll(PDO::FETCH_COLUMN)) <= 1) {
            $pdo->rollBack();
            header('Location: users.php?error=' . urlencode('Cannot deactivate the last active school administrator.'));
            exit;
        }
    }

    $update = $pdo->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?");
    $update->execute([$newStatus, $id]);

    // Keep student profile visibility aligned with account approval/activation.
    if ($target['role'] === 'student') {
        $studentProfile = $pdo->prepare("SELECT id, status FROM students WHERE user_id = ? LIMIT 1 FOR UPDATE");
        $studentProfile->execute([$id]);
        $student = $studentProfile->fetch(PDO::FETCH_ASSOC);
        if ($student) {
            if ($newStatus === 0 && $student['status'] === 'Active') {
                $pdo->prepare("UPDATE students SET status = 'Inactive', updated_at = NOW() WHERE id = ?")
                    ->execute([(int)$student['id']]);
            } elseif ($newStatus === 1 && $student['status'] === 'Inactive') {
                $pdo->prepare("UPDATE students SET status = 'Active', updated_at = NOW() WHERE id = ?")
                    ->execute([(int)$student['id']]);
            }
        }
    }

    $pdo->commit();

    logActivity($pdo, (int)$_SESSION['user_id'], 'toggle', 'user', $id, 'Toggled user status');
    header('Location: users.php?success=' . urlencode('User status updated.'));
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('User status update failed: ' . $e->getMessage());
    header('Location: users.php?error=' . urlencode('Failed to update user status.'));
    exit;
}
