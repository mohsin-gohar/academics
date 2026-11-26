<?php
$page_title = 'Edit Student';
require_once '../includes/header.php';
requireStaff();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();
if (!$student) { header('Location: index.php?error=' . urlencode('Student not found.')); exit; }
require_record_school($student);

$errors = [];
$form_data = $student;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }

    $form_data = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'date_of_birth' => $_POST['date_of_birth'] ?? '',
        'gender' => $_POST['gender'] ?? '',
        'address' => trim($_POST['address'] ?? ''),
        'enrollment_date' => $_POST['enrollment_date'] ?? '',
        'status' => $_POST['status'] ?? 'Active'
    ];

    if (empty($form_data['full_name'])) $errors[] = 'Full name is required.';
    if (empty($form_data['email'])) $errors[] = 'Email is required.';
    elseif (!isValidEmail($form_data['email'])) $errors[] = 'Invalid email address.';
    if (!in_array($form_data['status'], ['Active', 'Inactive', 'Graduated', 'Suspended'], true)) {
        $errors[] = 'Invalid student status.';
    }
    if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $form_data['enrollment_date'])
        || !checkdate((int)substr($form_data['enrollment_date'], 5, 2), (int)substr($form_data['enrollment_date'], 8, 2), (int)substr($form_data['enrollment_date'], 0, 4))) {
        $errors[] = 'A valid enrollment date is required.';
    }
    if ($form_data['date_of_birth'] !== ''
        && (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $form_data['date_of_birth'])
            || !checkdate((int)substr($form_data['date_of_birth'], 5, 2), (int)substr($form_data['date_of_birth'], 8, 2), (int)substr($form_data['date_of_birth'], 0, 4)))) {
        $errors[] = 'Date of birth must be a valid date.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ? AND id != ? AND school_id = ?");
        $stmt->execute([$form_data['email'], $id, (int)($student['school_id'] ?? 0)]);
        if ($stmt->fetch()) $errors[] = 'Email already in use by another student.';

        $userEmailCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
        $userEmailCheck->execute([$form_data['email'], (int)$student['user_id']]);
        if ($userEmailCheck->fetchColumn()) $errors[] = 'Email address is already used by another account.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE students SET full_name=?, email=?, phone=?, date_of_birth=?, gender=?, address=?, enrollment_date=?, status=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([
                $form_data['full_name'], $form_data['email'], $form_data['phone'] ?: null,
                $form_data['date_of_birth'] ?: null, $form_data['gender'] ?: null,
                $form_data['address'] ?: null, $form_data['enrollment_date'], $form_data['status'], $id
            ]);
            // Update linked user
            $accountActive = in_array($form_data['status'], ['Active', 'Graduated'], true) ? 1 : 0;
            $stmt = $pdo->prepare(
                "UPDATE users SET full_name=?, email=?, is_active=?, updated_at=NOW()
                 WHERE id=? AND school_id = ?"
            );
            $stmt->execute([
                $form_data['full_name'], $form_data['email'], $accountActive,
                $student['user_id'], (int)$student['school_id']
            ]);

            $pdo->commit();
            logActivity($pdo, $_SESSION['user_id'], 'update', 'student', $id, 'Updated student: ' . $form_data['full_name']);
            header('Location: show.php?id=' . $id . '&success=' . urlencode('Student updated successfully!'));
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Student update failed: " . $e->getMessage());
            $errors[] = 'Failed to update student.';
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-edit text-primary me-2"></i>Edit Student</h5></div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo e($e); ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success"><?php echo e($_GET['success']); ?></div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" class="form-control" value="<?php echo e($form_data['full_name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" value="<?php echo e($form_data['email']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo e($form_data['phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control" value="<?php echo e($form_data['date_of_birth'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="">Prefer not to say</option>
                                <option value="Male" <?php echo ($form_data['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($form_data['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($form_data['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Enrollment Date *</label>
                            <input type="date" name="enrollment_date" class="form-control" value="<?php echo e($form_data['enrollment_date']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="Active" <?php echo $form_data['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo $form_data['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="Graduated" <?php echo $form_data['status'] === 'Graduated' ? 'selected' : ''; ?>>Graduated</option>
                                <option value="Suspended" <?php echo $form_data['status'] === 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="3"><?php echo e($form_data['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="show.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Update Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
