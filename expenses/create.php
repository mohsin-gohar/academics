<?php
/**
 * Create a school expense. Expenses remain pending until an administrator
 * approves or rejects them.
 */
$page_title = 'Add Expense';
require_once __DIR__ . '/../includes/header.php';
requireSchoolManagement();

$isPlatformAdmin = isSuperAdmin();
$schoolId = currentSchoolId();
$schools = [];
if ($isPlatformAdmin) {
    $schools = $pdo->query(
        "SELECT id, school_name FROM schools
         WHERE is_active = 1 AND (status IS NULL OR status NOT IN ('suspended', 'disabled'))
         ORDER BY school_name"
    )->fetchAll(PDO::FETCH_ASSOC);
} elseif (!$schoolId || $schoolId <= 0) {
    http_response_code(403);
    header('Location: ../errors/403.php');
    exit;
}

$categories = ['Utilities', 'Supplies', 'Maintenance', 'Travel', 'Salaries', 'Other'];
$methods = ['cash' => 'Cash', 'bank' => 'Bank transfer', 'card' => 'Card', 'online' => 'Online', 'cheque' => 'Cheque'];
$old = [
    'school_id' => $schoolId ? (string)$schoolId : '',
    'title' => '',
    'description' => '',
    'amount' => '',
    'expense_date' => date('Y-m-d'),
    'category' => 'Other',
    'payment_method' => 'cash',
    'reference' => '',
    'received_by' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($old) as $key) {
        if (isset($_POST[$key])) $old[$key] = trim((string)$_POST[$key]);
    }

    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $targetSchoolId = $isPlatformAdmin ? (int)$old['school_id'] : (int)$schoolId;
        if ($targetSchoolId <= 0) {
            $errors[] = 'Select a valid school.';
        } else {
            $schoolStmt = $pdo->prepare("SELECT id, is_active, status, trial_ends_at FROM schools WHERE id = ? LIMIT 1");
            $schoolStmt->execute([$targetSchoolId]);
            $targetSchool = $schoolStmt->fetch(PDO::FETCH_ASSOC);
            if (!$targetSchool || (int)$targetSchool['is_active'] !== 1
                || in_array(strtolower((string)$targetSchool['status']), ['suspended', 'disabled'], true)) {
                $errors[] = 'The selected school is not currently active.';
            }
        }

        if (mb_strlen($old['title']) < 2 || mb_strlen($old['title']) > 200) {
            $errors[] = 'Title must be between 2 and 200 characters.';
        }
        if (!is_numeric($old['amount']) || (float)$old['amount'] <= 0
            || (float)$old['amount'] > 99999999.99
            || abs((float)$old['amount'] - round((float)$old['amount'], 2)) > 0.00001) {
            $errors[] = 'Enter a positive amount with no more than two decimal places.';
        }
        if (!isValidIsoDate($old['expense_date'])) {
            $errors[] = 'Enter a valid expense date.';
        }
        if (!in_array($old['category'], $categories, true)) {
            $errors[] = 'Select a valid expense category.';
        }
        if (!array_key_exists($old['payment_method'], $methods)) {
            $errors[] = 'Select a valid payment method.';
        }
        if (mb_strlen($old['description']) > 5000 || mb_strlen($old['reference']) > 100) {
            $errors[] = 'Description or reference exceeds the allowed length.';
        }

        $receivedBy = $old['received_by'] !== '' ? (int)$old['received_by'] : null;
        if ($receivedBy !== null) {
            $receiver = $pdo->prepare("SELECT id FROM users WHERE id = ? AND school_id = ? AND is_active = 1 LIMIT 1");
            $receiver->execute([$receivedBy, $targetSchoolId]);
            if (!$receiver->fetchColumn()) $errors[] = 'The selected recipient is not an active user in this school.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                $insert = $pdo->prepare(
                    "INSERT INTO expenses
                        (school_id, title, description, amount, expense_date, category, payment_method, reference, received_by, status, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)"
                );
                $insert->execute([
                    $targetSchoolId,
                    $old['title'],
                    $old['description'] !== '' ? $old['description'] : null,
                    round((float)$old['amount'], 2),
                    $old['expense_date'],
                    $old['category'],
                    $old['payment_method'],
                    $old['reference'] !== '' ? $old['reference'] : null,
                    $receivedBy,
                    (int)$_SESSION['user_id'],
                ]);
                $expenseId = (int)$pdo->lastInsertId();
                recordActivity($pdo, (int)$_SESSION['user_id'], 'create_expense', 'expense', $expenseId, 'Submitted expense: ' . $old['title']);
                $pdo->commit();
                setFlashMessage('success', 'Expense submitted and is awaiting approval.');
                header('Location: index.php');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Expense creation failed: ' . $e->getMessage());
                $errors[] = 'Expense could not be saved. Please try again.';
            }
        }
    }
}

$recipientUsers = [];
$recipientSchoolId = $isPlatformAdmin ? (int)$old['school_id'] : (int)$schoolId;
if ($recipientSchoolId > 0) {
    $recipientStmt = $pdo->prepare("SELECT id, full_name, role FROM users WHERE school_id = ? AND is_active = 1 ORDER BY full_name");
    $recipientStmt->execute([$recipientSchoolId]);
    $recipientUsers = $recipientStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1"><i class="fas fa-money-bill-wave me-2 text-primary"></i>Add Expense</h2>
        <p class="text-muted mb-0 small">Submit a school expense for administrative approval.</p>
    </div>
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Expenses</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $error): ?><div><?php echo e($error); ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm" style="max-width: 850px;">
    <div class="card-body">
        <form method="post" action="create.php">
            <?php echo csrfField(); ?>
            <?php if ($isPlatformAdmin): ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">School <span class="text-danger">*</span></label>
                    <select name="school_id" class="form-select" required>
                        <option value="">Select school</option>
                        <?php foreach ($schools as $school): ?>
                            <option value="<?php echo (int)$school['id']; ?>" <?php echo (int)$old['school_id'] === (int)$school['id'] ? 'selected' : ''; ?>><?php echo e($school['school_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="row g-3">
                <div class="col-12 col-md-8">
                    <label class="form-label fw-semibold">Expense title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" maxlength="200" value="<?php echo e($old['title']); ?>" required>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                    <input type="number" name="amount" class="form-control" min="0.01" max="99999999.99" step="0.01" value="<?php echo e($old['amount']); ?>" required>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold">Expense date <span class="text-danger">*</span></label>
                    <input type="date" name="expense_date" class="form-control" value="<?php echo e($old['expense_date']); ?>" required>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                    <select name="category" class="form-select" required>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo e($category); ?>" <?php echo $old['category'] === $category ? 'selected' : ''; ?>><?php echo e($category); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold">Payment method</label>
                    <select name="payment_method" class="form-select">
                        <?php foreach ($methods as $value => $label): ?>
                            <option value="<?php echo e($value); ?>" <?php echo $old['payment_method'] === $value ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">Received by</label>
                    <select name="received_by" class="form-select">
                        <option value="">Not specified</option>
                        <?php foreach ($recipientUsers as $recipient): ?>
                            <option value="<?php echo (int)$recipient['id']; ?>" <?php echo (int)$old['received_by'] === (int)$recipient['id'] ? 'selected' : ''; ?>><?php echo e($recipient['full_name'] . ' (' . tenant_role_label($recipient['role']) . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">Reference</label>
                    <input type="text" name="reference" class="form-control" maxlength="100" value="<?php echo e($old['reference']); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Description / notes</label>
                    <textarea name="description" class="form-control" rows="4" maxlength="5000"><?php echo e($old['description']); ?></textarea>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-4">
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i>Submit expense</button>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
