<?php
$page_title = 'Edit User';
require_once '../includes/header.php';
requireAdmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: users.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) { header('Location: users.php?error=' . urlencode('User not found.')); exit; }
require_record_school($user);

$errors = [];
$form_data = $user;

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
    if (!empty($form_data['password']) && strlen($form_data['password']) < 6) $errors[] = 'Password must be at least 6 characters.';
    if (!in_array($form_data['role'], ['admin','teacher','student'])) $errors[] = 'Invalid role.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$form_data['email'], $id]);
        if ($stmt->fetch()) $errors[] = 'Email already in use.';
    }

    if (empty($errors)) {
        try {
            if (!empty($form_data['password'])) {
                $hashed = password_hash($form_data['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, password=?, role=?, is_active=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$form_data['full_name'], $form_data['email'], $hashed, $form_data['role'], $form_data['is_active'], $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, role=?, is_active=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$form_data['full_name'], $form_data['email'], $form_data['role'], $form_data['is_active'], $id]);
            }
            logActivity($pdo, $_SESSION['user_id'], 'update', 'user', $id, 'Updated user: ' . $form_data['full_name']);
            header('Location: users.php?success=' . urlencode('User updated successfully!'));
            exit;
        } catch (PDOException $e) {
            error_log("User update failed: " . $e->getMessage());
            $errors[] = 'Failed to update user.';
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-edit text-primary me-2"></i>Edit User</h5></div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo e($e); ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo e($form_data['full_name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" value="<?php echo e($form_data['email']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password (leave blank to keep)</label>
                        <input type="password" name="password" class="form-control" minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <select name="role" class="form-select">
                            <option value="student" <?php echo $form_data['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                            <option value="teacher" <?php echo $form_data['role'] === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                            <option value="admin" <?php echo $form_data['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="is_active" <?php echo $form_data['is_active'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="users.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
