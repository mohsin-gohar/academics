<?php
/**
 * Apply for Leave
 */
$page_title = 'Apply for Leave';
require_once '../includes/header.php';
requireAuth();

$schoolId = currentSchoolId();
$userId = (int)$_SESSION['user_id'];
$role = getUserRole();
if ($schoolId === null || $schoolId <= 0) {
    http_response_code(403);
    header('Location: ../errors/403.php');
    exit;
}

$errors = [];
$old = ['leave_type' => 'Sick', 'start_date' => '', 'end_date' => '', 'reason' => ''];

$leaveTypes = ['Sick' => 'Sick', 'Casual' => 'Casual', 'Emergency' => 'Emergency', 'Maternity' => 'Maternity', 'Paternity' => 'Paternity', 'Other' => 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        foreach (array_keys($old) as $k) {
            if (isset($_POST[$k])) $old[$k] = trim((string)$_POST[$k]);
        }
        $old['leave_type'] = $_POST['leave_type'] ?? $old['leave_type'];

        if (!in_array($old['leave_type'], array_keys($leaveTypes), true)) $errors[] = 'Invalid leave type.';
        $validStartDate = preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $old['start_date'])
            && checkdate((int)substr($old['start_date'], 5, 2), (int)substr($old['start_date'], 8, 2), (int)substr($old['start_date'], 0, 4));
        $validEndDate = preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $old['end_date'])
            && checkdate((int)substr($old['end_date'], 5, 2), (int)substr($old['end_date'], 8, 2), (int)substr($old['end_date'], 0, 4));
        if (!$validStartDate) $errors[] = 'A valid start date is required.';
        if (!$validEndDate) $errors[] = 'A valid end date is required.';
        if ($old['reason'] === '') $errors[] = 'Reason is required.';
        elseif (mb_strlen($old['reason']) > 5000) $errors[] = 'Reason cannot exceed 5000 characters.';

        if (empty($errors) && $old['end_date'] < $old['start_date']) {
            $errors[] = 'End date must be on or after the start date.';
        }
        if (empty($errors)) {
            $stmt = $pdo->prepare("INSERT INTO leave_requests (school_id, user_id, leave_type, start_date, end_date, reason, status)
                                   VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
            $stmt->execute([$schoolId, $userId, $old['leave_type'], $old['start_date'], $old['end_date'], $old['reason']]);
            $leaveId = (int)$pdo->lastInsertId();

            recordActivity($pdo, $userId, 'create_leave_request', 'leave_request', $leaveId, "Applied for {$old['leave_type']} leave from {$old['start_date']} to {$old['end_date']}");

            // Notify school admins of the new request.
            if (function_exists('notifyRole')) {
                notifyRole($pdo, $schoolId, 'school_admin', 'New leave request',
                    e($old['leave_type']) . ' leave requested from ' . e(formatDate($old['start_date'])) . ' to ' . e(formatDate($old['end_date'])),
                    'leave/index.php', 'fa-calendar-times', 'warning');
                notifyRole($pdo, $schoolId, 'admin', 'New leave request',
                    e($old['leave_type']) . ' leave requested from ' . e(formatDate($old['start_date'])) . ' to ' . e(formatDate($old['end_date'])),
                    'leave/index.php', 'fa-calendar-times', 'warning');
            }

            setFlashMessage('success', 'Leave request submitted successfully.');
            header('Location: index.php');
            exit;
        }
    }
}
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1"><i class="fas fa-calendar-plus me-2 text-primary"></i>Apply for Leave</h2>
        <p class="text-muted mb-0 small">Submit a leave request for review by school administration.</p>
    </div>
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?php echo e($e); ?></div><?php endforeach; ?></div>
<?php endif;

$today = date('Y-m-d');
?>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="post">
            <?php echo csrfField(); ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Leave Type <span class="text-danger">*</span></label>
                    <select name="leave_type" class="form-select" required>
                        <?php foreach ($leaveTypes as $k => $v): ?>
                        <option value="<?php echo e($k); ?>" <?php echo $old['leave_type'] === $k ? 'selected' : ''; ?>><?php echo e($v); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo e($old['start_date']); ?>" min="<?php echo e($today); ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">End Date <span class="text-danger">*</span></label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo e($old['end_date']); ?>" min="<?php echo e($today); ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                    <textarea name="reason" class="form-control" rows="4" required><?php echo e($old['reason']); ?></textarea>
                </div>
            </div>
            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-calendar-plus me-2"></i> Submit Leave Request</button>
            </div>
        </form>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
