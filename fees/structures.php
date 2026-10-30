<?php
/**
 * Manage fee structures.
 */
$page_title = 'Fee Structures';
require_once __DIR__ . '/../includes/header.php';
requireSchoolManagement();

$schoolId = (int)(currentSchoolId() ?? 0);
if ($schoolId <= 0) { header('Location: ../dashboard.php'); exit; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form token.';
    } elseif ($_POST['action'] === 'create') {
        $title = trim((string)($_POST['title'] ?? ''));
        $amount = trim((string)($_POST['amount'] ?? ''));
        $freq = (string)($_POST['frequency'] ?? 'one-time');
        $desc = trim((string)($_POST['description'] ?? ''));
        if ($title === '') $errors[] = 'Title is required.';
        if (!is_numeric($amount) || (float)$amount <= 0) $errors[] = 'Amount must be positive.';
        if (!array_key_exists($freq, getFeeFrequencies())) $errors[] = 'Invalid frequency.';
        if (empty($errors)) {
            $stmt = $pdo->prepare("INSERT INTO fee_structures (school_id, title, amount, frequency, description, created_by) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$schoolId, $title, $amount, $freq, $desc !== '' ? $desc : null, (int)($_SESSION['user_id'] ?? 0)]);
            setFlashMessage('success', 'Fee structure created.');
            header('Location: structures.php');
            exit;
        }
    } elseif ($_POST['action'] === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE fee_structures SET is_active = 1 - is_active WHERE id = ? AND school_id = ?")->execute([$id, $schoolId]);
        setFlashMessage('success', 'Updated.');
        header('Location: structures.php');
        exit;
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM fee_structures WHERE id = ? AND school_id = ?")->execute([$id, $schoolId]);
        setFlashMessage('success', 'Deleted.');
        header('Location: structures.php');
        exit;
    }
}

$stmt = $pdo->prepare("SELECT * FROM fee_structures WHERE school_id = ? ORDER BY created_at DESC");
$stmt->execute([$schoolId]);
$structures = $stmt->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1"><i class="fas fa-money-bill-wave me-2 text-primary"></i>Fee Structures</h2>
        <p class="text-muted mb-0 small">Define reusable fee templates for your school.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="invoices.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-file-invoice me-1"></i>Invoices</a>
        <a href="create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>New Invoice</a>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?php echo e($e); ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Create Fee Structure</div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Amount</label>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Frequency</label>
                            <select name="frequency" class="form-select">
                                <?php foreach (getFeeFrequencies() as $k => $v): ?>
                                    <option value="<?php echo e($k); ?>"><?php echo e($v); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-2"></i>Create</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Existing Structures</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Title</th><th>Amount</th><th>Frequency</th><th>Status</th><th class="text-center">Action</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($structures)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No fee structures yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($structures as $fs): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo e($fs['title']); ?></td>
                                        <td><?php echo number_format((float)$fs['amount'], 2); ?></td>
                                        <td><span class="badge bg-secondary bg-opacity-75"><?php echo e(getFeeFrequencies()[$fs['frequency']] ?? $fs['frequency']); ?></span></td>
                                        <td><span class="badge bg-<?php echo $fs['is_active'] ? 'success' : 'secondary'; ?>"><?php echo $fs['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                                        <td class="text-center">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?php echo (int)$fs['id']; ?>">
                                                <button class="btn btn-sm btn-outline-secondary" title="Toggle"><i class="fas fa-toggle-on"></i></button>
                                            </form>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this structure?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo (int)$fs['id']; ?>">
                                                <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>