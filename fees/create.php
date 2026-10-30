<?php
/**
 * Create a single invoice for a student.
 */
$page_title = 'Create Invoice';
require_once __DIR__ . '/../includes/header.php';
requireSchoolManagement();

$schoolId = (int)(currentSchoolId() ?? 0);
if ($schoolId <= 0) { header('Location: ../dashboard.php'); exit; }

$stmt = $pdo->prepare("SELECT id, full_name, student_number FROM students WHERE school_id = ? AND status = 'Active' ORDER BY full_name ASC");
$stmt->execute([$schoolId]);
$students = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT id, title, amount, frequency FROM fee_structures WHERE school_id = ? AND is_active = 1 ORDER BY title ASC");
$stmt->execute([$schoolId]);
$structures = $stmt->fetchAll();

$errors = [];
$old = ['student_id' => '', 'fee_structure_id' => '', 'title' => '', 'amount' => '', 'due_date' => '', 'notes' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form token.';
    } else {
        foreach (array_keys($old) as $k) { if (isset($_POST[$k])) $old[$k] = trim((string)$_POST[$k]); }
        if ((int)$old['student_id'] <= 0) $errors[] = 'Student is required.';
        if ($old['title'] === '') $errors[] = 'Title is required.';
        if (!is_numeric($old['amount']) || (float)$old['amount'] <= 0) $errors[] = 'Amount must be positive.';
        // server-side ownership checks
        if (empty($errors)) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM students WHERE id = ? AND school_id = ?");
            $chk->execute([(int)$old['student_id'], $schoolId]);
            if ((int)$chk->fetchColumn() === 0) $errors[] = 'Selected student does not belong to this school.';
            if ($old['fee_structure_id'] !== '') {
                $chk2 = $pdo->prepare("SELECT COUNT(*) FROM fee_structures WHERE id = ? AND school_id = ?");
                $chk2->execute([(int)$old['fee_structure_id'], $schoolId]);
                if ((int)$chk2->fetchColumn() === 0) $errors[] = 'Selected fee structure does not belong to this school.';
            }
        }
        if (empty($errors)) {
            $fsId = $old['fee_structure_id'] !== '' ? (int)$old['fee_structure_id'] : null;
            $id = createInvoice($pdo, $schoolId, (int)$old['student_id'], $fsId, $old['title'], (float)$old['amount'], $old['due_date'] !== '' ? $old['due_date'] : null, (int)($_SESSION['user_id'] ?? 0));
            // notes
            if ($old['notes'] !== '') {
                $pdo->prepare("UPDATE invoices SET notes = ? WHERE id = ? AND school_id = ?")->execute([$old['notes'], $id, $schoolId]);
            }
            setFlashMessage('success', 'Invoice created.');
            header('Location: invoices.php');
            exit;
        }
    }
}
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1"><i class="fas fa-file-invoice me-2 text-primary"></i>Create Invoice</h2>
        <p class="text-muted mb-0 small">Issue a fee invoice to a student.</p>
    </div>
    <a href="invoices.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?php echo e($e); ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Student <span class="text-danger">*</span></label>
                    <select name="student_id" class="form-select" required>
                        <option value="">Select student</option>
                        <?php foreach ($students as $st): ?>
                            <option value="<?php echo (int)$st['id']; ?>" <?php echo (int)$old['student_id'] === (int)$st['id'] ? 'selected' : ''; ?>><?php echo e($st['full_name'] . ' (' . ($st['student_number'] ?? '') . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Fee Structure (optional)</label>
                    <select name="fee_structure_id" id="fsSelect" class="form-select">
                        <option value="">Custom</option>
                        <?php foreach ($structures as $fs): ?>
                            <option value="<?php echo (int)$fs['id']; ?>" data-title="<?php echo e($fs['title']); ?>" data-amount="<?php echo (float)$fs['amount']; ?>"><?php echo e($fs['title'] . ' — ' . number_format((float)$fs['amount'], 2)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="invTitle" class="form-control" value="<?php echo e($old['title']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="invAmount" class="form-control" value="<?php echo e($old['amount']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="<?php echo e($old['due_date']); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"><?php echo e($old['notes']); ?></textarea>
                </div>
            </div>
            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-file-invoice me-2"></i> Create Invoice</button>
            </div>
        </form>
    </div>
</div>
<script>
document.getElementById('fsSelect').addEventListener('change', function () {
    var opt = this.options[this.selectedIndex];
    if (opt.value) {
        document.getElementById('invTitle').value = opt.dataset.title || '';
        document.getElementById('invAmount').value = opt.dataset.amount || '';
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>