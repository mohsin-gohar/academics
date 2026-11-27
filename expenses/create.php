<?php
/**
 * Add Expense — school management only.
 * Expenses index links here; the page was missing, so "Add Expense" 404ed.
 */
$page_title = 'Add Expense';
require_once '../includes/header.php';
requireSchoolManagement();

// GET renders the form; only a POST may write.

$schoolId = currentSchoolId();
$userId = (int)$_SESSION['user_id'];
$errors = [];
$form = [
    'title' => '',
    'description' => '',
    'amount' => '',
    'expense_date' => date('Y-m-d'),
    'category' => 'Supplies',
    'payment_method' => 'Cash',
    'reference' => '',
];

$categories = ['Supplies', 'Utilities', 'Maintenance', 'Salary', 'Transport', 'Events', 'Other'];
$methods = ['Cash', 'Bank Transfer', 'Card', 'Cheque', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }

    $form = [
        'title' => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'amount' => trim($_POST['amount'] ?? ''),
        'expense_date' => trim($_POST['expense_date'] ?? ''),
        'category' => $_POST['category'] ?? '',
        'payment_method' => $_POST['payment_method'] ?? '',
        'reference' => trim($_POST['reference'] ?? ''),
    ];

    if (mb_strlen($form['title']) < 3 || mb_strlen($form['title']) > 200) {
        $errors[] = 'Title must be between 3 and 200 characters.';
    }
    if (!is_numeric($form['amount']) || (float)$form['amount'] <= 0) {
        $errors[] = 'Amount must be a positive number.';
    } elseif ((float)$form['amount'] > 99999999.99) {
        $errors[] = 'Amount is too large.';
    }
    $date = DateTime::createFromFormat('Y-m-d', $form['expense_date']);
    if (!$date || $date->format('Y-m-d') !== $form['expense_date']) {
        $errors[] = 'Please provide a valid expense date.';
    }
    if (!in_array($form['category'], $categories, true)) {
        $errors[] = 'Please choose a valid category.';
    }
    if (!in_array($form['payment_method'], $methods, true)) {
        $errors[] = 'Please choose a valid payment method.';
    }
    if (mb_strlen($form['description']) > 2000) {
        $errors[] = 'Description must be 2000 characters or fewer.';
    }
    if (mb_strlen($form['reference']) > 100) {
        $errors[] = 'Reference must be 100 characters or fewer.';
    }
    if ($schoolId <= 0) {
        $errors[] = 'No school context is available for this account.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO expenses
                    (school_id, title, description, amount, expense_date, category, payment_method, reference, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)"
            );
            $stmt->execute([
                $schoolId,
                $form['title'],
                $form['description'] !== '' ? $form['description'] : null,
                number_format((float)$form['amount'], 2, '.', ''),
                $form['expense_date'],
                $form['category'],
                $form['payment_method'],
                $form['reference'] !== '' ? $form['reference'] : null,
                $userId,
            ]);
            $expenseId = (int)$pdo->lastInsertId();
            logActivity($pdo, $userId, 'create', 'expense', $expenseId, 'Recorded expense: ' . $form['title']);
            setFlashMessage('success', 'Expense recorded and submitted for approval.');
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            error_log('Expense create failed: ' . $e->getMessage());
            $errors[] = 'Could not save the expense. Please try again.';
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1"><i class="fas fa-plus-circle me-2 text-primary"></i>Add Expense</h2>
        <p class="text-muted mb-0 small">New expenses are submitted as pending for approval.</p>
    </div>
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Back to expenses</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
        <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
            <div class="row g-3">
                <div class="col-12 col-md-8">
                    <label class="form-label small fw-semibold">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" maxlength="200" required
                           value="<?php echo htmlspecialchars($form['title']); ?>">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold">Amount <span class="text-danger">*</span></label>
                    <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required
                           value="<?php echo htmlspecialchars((string)$form['amount']); ?>">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold">Date <span class="text-danger">*</span></label>
                    <input type="date" name="expense_date" class="form-control" required
                           value="<?php echo htmlspecialchars($form['expense_date']); ?>">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold">Category</label>
                    <select name="category" class="form-select">
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $form['category'] === $category ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold">Payment method</label>
                    <select name="payment_method" class="form-select">
                        <?php foreach ($methods as $method): ?>
                        <option value="<?php echo htmlspecialchars($method); ?>" <?php echo $form['payment_method'] === $method ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($method); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">Description</label>
                    <textarea name="description" class="form-control" rows="3" maxlength="2000"><?php echo htmlspecialchars($form['description']); ?></textarea>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label small fw-semibold">Reference / receipt no.</label>
                    <input type="text" name="reference" class="form-control" maxlength="100"
                           value="<?php echo htmlspecialchars($form['reference']); ?>">
                </div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save expense</button>
                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
