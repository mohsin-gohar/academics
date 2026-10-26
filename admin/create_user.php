<?php
$page_title = 'Add User';
require_once '../includes/header.php';
requireAdmin();

$errors = [];
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }

    $form_data = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'role' => $_POST['role'] ?? 'student',
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];

    if (empty($form_data['full_name'])) $errors[] = 'Full name is required.';
    if (empty($form_data['email'])) $errors[] = 'Email is required.';
    elseif (!isValidEmail($form_data['email'])) $errors[] = 'Invalid email.';
    if (empty($form_data['password']) || strlen($form_data['password']) < 6) $errors[] = 'Password must be at least 6 characters.';
    if (!in_array($form_data['role'], ['admin','teacher','student'])) $errors[] = 'Invalid role.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$form_data['email']]);
        if ($stmt->fetch()) $errors[] = 'Email already in use.';
    }

    if (empty($errors)) {
        try {
            $hashed = password_hash($form_data['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$form_data['full_name'], $form_data['email'], $hashed, $form_data['role'], $form_data['is_active']]);
            $uid = $pdo->lastInsertId();

            if ($form_data['role'] === 'student') {
                $stuNum = generateStudentNumber($pdo);
                $stmt = $pdo->prepare("INSERT INTO students (user_id, student_number, full_name, email, enrollment_date, status) VALUES (?, ?, ?, ?, CURDATE(), 'Active')");
                $stmt->execute([$uid, $stuNum, $form_data['full_name'], $form_data['email']]);
            }

            logActivity($pdo, $_SESSION['user_id'], 'create', 'user', $uid, 'Created user: ' . $form_data['full_name']);
            header('Location: users.php?success=' . urlencode('User created successfully!'));
            exit;
        } catch (PDOException $e) {
            error_log("User create failed: " . $e->getMessage());
            $errors[] = 'Failed to create user.';
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-user-plus text-primary me-2"></i>Add User</h5></div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo e($e); ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo e($form_data['full_name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" value="<?php echo e($form_data['email'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <select name="role" class="form-select">
                            <option value="student" <?php echo ($form_data['role'] ?? 'student') === 'student' ? 'selected' : ''; ?>>Student</option>
                            <option value="teacher" <?php echo ($form_data['role'] ?? '') === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                            <option value="admin" <?php echo ($form_data['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="is_active" <?php echo !isset($form_data['is_active']) || $form_data['is_active'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="users.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
