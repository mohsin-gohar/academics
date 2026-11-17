<?php
/**
 * Edit User — C2 Security Fix
 * - Only super_admin may change role to super_admin
 * - Non-super-admin users cannot change their own school_id
 * - School existence is validated before assignment
 */
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

$currentSchoolId = currentSchoolId();
$isSuperAdmin = isSuperAdmin();

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
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'school_id' => isset($_POST['school_id']) ? (int)$_POST['school_id'] : $user['school_id'],
    ];

    if (empty($form_data['full_name'])) $errors[] = 'Full name is required.';
    if (empty($form_data['email'])) $errors[] = 'Email is required.';
    elseif (!isValidEmail($form_data['email'])) $errors[] = 'Invalid email.';
    if (!empty($form_data['password']) && strlen($form_data['password']) < 10) $errors[] = 'Password must be at least 10 characters.';
    if (!in_array($form_data['role'], ['super_admin','school_admin','admin','teacher','student','parent'])) $errors[] = 'Invalid role.';

    // Only super_admin can change role to super_admin
    if ($form_data['role'] === 'super_admin' && !$isSuperAdmin) {
        $errors[] = 'Only platform super administrators can assign super admin role.';
    }
    // Prevent demoting the last super_admin
    if ($user['role'] === 'super_admin' && $form_data['role'] !== 'super_admin') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND is_active = 1");
        $stmt->execute();
        if ((int)$stmt->fetchColumn() <= 1) {
            $errors[] = 'Cannot demote the last active super administrator.';
        }
    }

    // School assignment rules
    if ($form_data['role'] === 'super_admin') {
        // Super admin users have no school scope
        $form_data['school_id'] = null;
    } elseif ($isSuperAdmin && $form_data['school_id'] === null) {
        // Super admin editing a school-scoped user without selecting a school — reject
        $errors[] = 'A school must be selected for this role.';
    } elseif (!$isSuperAdmin) {
        // School-scoped admin: user must belong to their own school
        $form_data['school_id'] = $currentSchoolId;
    } elseif ($isSuperAdmin && $form_data['school_id'] !== null) {
        // Validate school exists
        $schoolCheck = $pdo->prepare("SELECT id FROM schools WHERE id = ? LIMIT 1");
        $schoolCheck->execute([$form_data['school_id']]);
        if (!$schoolCheck->fetch()) {
            $errors[] = 'Selected school does not exist.';
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$form_data['email'], $id]);
        if ($stmt->fetch()) $errors[] = 'Email already in use.';
    }

    if (empty($errors)) {
        try {
            if (!empty($form_data['password'])) {
                $hashed = password_hash($form_data['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, password=?, role=?, is_active=?, school_id=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$form_data['full_name'], $form_data['email'], $hashed, $form_data['role'], $form_data['is_active'], $form_data['school_id'] !== null ? $form_data['school_id'] : null, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, role=?, is_active=?, school_id=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$form_data['full_name'], $form_data['email'], $form_data['role'], $form_data['is_active'], $form_data['school_id'] !== null ? $form_data['school_id'] : null, $id]);
            }
            logActivity($pdo, $_SESSION['user_id'], 'update', 'user', $id, 'Updated user: ' . $form_data['full_name'] . ' (' . $form_data['role'] . ')');
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
                        <input type="password" name="password" class="form-control" minlength="10">
                        <div class="form-text">Must be at least 10 characters with upper, lower, number, and special character</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <select name="role" class="form-select" id="roleSelect">
                            <?php if (isSuperAdmin()): ?>
                                <option value="super_admin" <?php echo $form_data['role'] === 'super_admin' ? 'selected' : ''; ?>>Super Admin (Platform)</option>
                            <?php endif; ?>
                            <option value="school_admin" <?php echo $form_data['role'] === 'school_admin' ? 'selected' : ''; ?>>School Admin</option>
                            <option value="admin" <?php echo $form_data['role'] === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                            <option value="teacher" <?php echo $form_data['role'] === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                            <option value="student" <?php echo $form_data['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                            <option value="parent" <?php echo $form_data['role'] === 'parent' ? 'selected' : ''; ?>>Parent / Guardian</option>
                        </select>
                    </div>
                    <div class="mb-3" id="schoolIdField" style="<?php echo (!isSuperAdmin() || $form_data['role'] === 'super_admin') ? 'display:none;' : ''; ?>">
                        <label class="form-label">School *</label>
                        <select name="school_id" class="form-select" required>
                            <option value="">Select a school</option>
                            <?php 
                            $schools = [];
                            try {
                                $schoolStmt = $pdo->query("SELECT id, school_name, school_code FROM schools WHERE is_active = 1 ORDER BY school_name");
                                $schools = $schoolStmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (PDOException $e) {
                                error_log("Failed to load schools: " . $e->getMessage());
                            }
                            foreach ($schools as $school): ?>
                                <option value="<?php echo (int)$school['id']; ?>" <?php echo isset($form_data['school_id']) && $form_data['school_id'] === (int)$school['id'] ? 'selected' : ''; ?>><?php echo e($school['school_name'] . ' (' . $school['school_code'] . ')'); ?></option>
                            <?php endforeach; ?>
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

<script>
document.getElementById('roleSelect').addEventListener('change', function () {
    const schoolField = document.getElementById('schoolIdField');
    const isSuperAdmin = this.value === 'super_admin';
    const showSchool = !isSuperAdmin;
    schoolField.style.display = showSchool ? '' : 'none';
    if (!showSchool) {
        schoolField.querySelector('select').required = false;
    } else {
        schoolField.querySelector('select').required = true;
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
