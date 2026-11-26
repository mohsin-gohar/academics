<?php
/**
 * Approve or reject a pending expense within the actor's authorized scope.
 */
require_once __DIR__ . '/../includes/header.php';
requireAdmin();
requirePost();

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    setFlashMessage('danger', 'Invalid security token.');
    header('Location: index.php');
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
$action = (string)($_POST['action'] ?? '');
if ($id <= 0 || !in_array($action, ['approve', 'reject', 'pay'], true)) {
    http_response_code(400);
    setFlashMessage('danger', 'Invalid expense action.');
    header('Location: index.php');
    exit;
}

$schoolId = currentSchoolId();
$userId = (int)$_SESSION['user_id'];

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT id, school_id, created_by, title, status FROM expenses WHERE id = ? FOR UPDATE");
    $stmt->execute([$id]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$expense) {
        $pdo->rollBack();
        http_response_code(404);
        setFlashMessage('danger', 'Expense not found.');
        header('Location: index.php');
        exit;
    }

    if (!isSuperAdmin() && (int)$expense['school_id'] !== (int)$schoolId) {
        $pdo->rollBack();
        http_response_code(403);
        header('Location: ../errors/403.php');
        exit;
    }

    $expectedStatus = $action === 'pay' ? 'approved' : 'pending';
    if ($expense['status'] !== $expectedStatus) {
        $pdo->rollBack();
        setFlashMessage('warning', $action === 'pay'
            ? 'Only an approved expense can be marked as paid.'
            : 'Only a pending expense can be approved or rejected.');
        header('Location: index.php');
        exit;
    }

    $newStatus = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'paid');
    if ($action === 'pay') {
        $update = $pdo->prepare(
            "UPDATE expenses SET status = 'paid', updated_at = NOW()
             WHERE id = ? AND school_id = ? AND status = 'approved'"
        );
        $update->execute([$id, (int)$expense['school_id']]);
    } else {
        $update = $pdo->prepare(
            "UPDATE expenses
             SET status = ?, approved_by = ?, approved_at = NOW(), updated_at = NOW()
             WHERE id = ? AND school_id = ? AND status = 'pending'"
        );
        $update->execute([$newStatus, $userId, $id, (int)$expense['school_id']]);
    }
    if ($update->rowCount() !== 1) {
        throw new RuntimeException('Expense was changed by another request.');
    }

    recordActivity($pdo, $userId, $action . '_expense', 'expense', $id, ucfirst($newStatus) . ' expense: ' . $expense['title']);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Expense approval failed: ' . $e->getMessage());
    setFlashMessage('danger', 'Unable to process this expense. Please try again.');
    header('Location: index.php');
    exit;
}

// Notify the submitter only after the status update has committed.
try {
    if ((int)$expense['created_by'] !== $userId) {
        notifyUser(
            $pdo,
            (int)$expense['created_by'],
            'Expense ' . $newStatus,
            'Your expense "' . $expense['title'] . '" was ' . $newStatus . '.',
            'expenses/index.php',
            'fa-money-bill-wave',
            $newStatus === 'approved' ? 'success' : 'warning'
        );
    }
} catch (Throwable $e) {
    error_log('Expense notification failed: ' . $e->getMessage());
}

setFlashMessage('success', 'Expense ' . $newStatus . '.');
header('Location: index.php');
exit;
