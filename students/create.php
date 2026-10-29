<?php
$page_title = 'Add Student';
require_once '../includes/header.php';
requireStaff();

$errors = [];
$form_data = [];

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
        'enrollment_date' => $_POST['enrollment_date'] ?? date('Y-m-d'),
        'status' => $_POST['status'] ?? 'Active'
    ];

    // Validation
    if (empty($form_data['full_name'])) $errors[] = 'Full name is required.';
    if (empty($form_data['email'])) $errors[] = 'Email is required.';
    elseif (!isValidEmail($form_data['email'])) $errors[] = 'Invalid email address.';
    if (empty($form_data['enrollment_date'])) $errors[] = 'Enrollment date is required.';

    // Check unique email
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ?");
        $stmt->execute([$form_data['email']]);
        if ($stmt->fetch()) $errors[] = 'Email already registered.';

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$form_data['email']]);
        if ($stmt->fetch()) $errors[] = 'Email already in use by a user account.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Create user account
            $default_password = password_hash('Student@123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, school_id) VALUES (?, ?, ?, 'student', ?)");
            $stmt->execute([$form_data['full_name'], $form_data['email'], $default_password, currentSchoolId()]);
            $user_id = $pdo->lastInsertId();

            // Create student profile
            $student_number = generateStudentNumber($pdo);
            $stmt = $pdo->prepare("INSERT INTO students (school_id, user_id, student_number, full_name, email, phone, date_of_birth, gender, address, enrollment_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                currentSchoolId(),
                $user_id, $student_number, $form_data['full_name'], $form_data['email'],
                $form_data['phone'] ?: null, $form_data['date_of_birth'] ?: null,
                $form_data['gender'] ?: null, $form_data['address'] ?: null,
                $form_data['enrollment_date'], $form_data['status']
            ]);
            $student_id = $pdo->lastInsertId();

            $pdo->commit();
            logActivity($pdo, $_SESSION['user_id'], 'create', 'student', $student_id, 'Created student: ' . $form_data['full_name']);

            header('Location: index.php?success=' . urlencode('Student added successfully! Default password: Student@123'));
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Student creation failed: " . $e->getMessage());
            $errors[] = 'Failed to create student. Please try again.';
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user-plus text-primary me-2"></i>Add New Student</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo e($e); ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" class="form-control" value="<?php echo e($form_data['full_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" value="<?php echo e($form_data['email'] ?? ''); ?>" required>
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
                            <input type="date" name="enrollment_date" class="form-control" value="<?php echo e($form_data['enrollment_date'] ?? date('Y-m-d')); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="Active" <?php echo ($form_data['status'] ?? 'Active') === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo ($form_data['status'] ?? '') === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="3"><?php echo e($form_data['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
