<?php
/**
 * Add User — C2 Security Fix
 * - Only super_admin may create super_admin users.
 * - Non-super-admin users must have an explicit school_id (never NULL).
 * - School existence is validated before assignment.
 */
$page_title = 'Add User';
require_once '../includes/header.php';
requireAdmin();

$errors = [];
$form_data = [];
$schools = [];

// Load available schools for school-scoped admins
try {
    $schoolStmt = $pdo->query("SELECT id, school_name, school_code FROM schools WHERE is_active = 1 ORDER BY school_name");
    $schools = $schoolStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Failed to load schools: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }

    $form_data = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'email' => strtolower(trim($_POST['email'] ?? '')),
        'password' => $_POST['password'] ?? '',
        'role' => $_POST['role'] ?? 'student',
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'school_id' => isset($_POST['school_id']) ? (int)$_POST['school_id'] : null,
    ];

    $currentSchoolId = currentSchoolId();
    $isSuperAdmin = isSuperAdmin();

    if (mb_strlen($form_data['full_name']) < 2 || mb_strlen($form_data['full_name']) > 100) $errors[] = 'Full name must be between 2 and 100 characters.';
    if (empty($form_data['email'])) $errors[] = 'Email is required.';
    elseif (!isValidEmail($form_data['email'])) $errors[] = 'Invalid email.';
    if (empty($form_data['password'])) {
        $errors[] = 'Password is required.';
    } else {
        $pwErrors = validatePasswordStrength($form_data['password']);
        if ($pwErrors) $errors[] = implode(' ', $pwErrors);
    }
    if (!in_array($form_data['role'], ['super_admin','school_admin','admin','teacher','student','parent'])) $errors[] = 'Invalid role.';

    // Only super_admin can create super_admin users
    if ($form_data['role'] === 'super_admin' && !$isSuperAdmin) {
        $errors[] = 'Only platform super administrators can create super admin accounts.';
    }

    // School assignment rules
    if ($form_data['role'] === 'super_admin') {
        // Super admin users have no school scope
        $form_data['school_id'] = null;
    } elseif ($isSuperAdmin && $form_data['school_id'] === null) {
        // Super admin creating a school-scoped user without selecting a school — reject
        $errors[] = 'A school must be selected for this role.';
    } elseif (!$isSuperAdmin && $currentSchoolId === null) {
        // Non-super-admin with no school context cannot create users
        $errors[] = 'You must be associated with a school to create users.';
    } elseif (!$isSuperAdmin) {
        // School-scoped admin: user must belong to their own school
        $form_data['school_id'] = $currentSchoolId;
    } elseif ($isSuperAdmin && $form_data['school_id'] !== null) {
        // Only assign accounts to a currently accessible tenant.
        $schoolCheck = $pdo->prepare("SELECT * FROM schools WHERE id = ? LIMIT 1");
        $schoolCheck->execute([$form_data['school_id']]);
        $selectedSchool = $schoolCheck->fetch(PDO::FETCH_ASSOC);
        if (!$selectedSchool || !saas_school_is_accessible($selectedSchool)) {
            $errors[] = 'Selected school does not exist or is not currently accessible.';
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$form_data['email']]);
        if ($stmt->fetch()) $errors[] = 'Email already in use.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $hashed = password_hash($form_data['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, is_active, school_id, must_change_password) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([
                $form_data['full_name'],
                $form_data['email'],
                $hashed,
                $form_data['role'],
                $form_data['is_active'],
                $form_data['school_id'],
            ]);
            $uid = (int)$pdo->lastInsertId();

            if ($form_data['role'] === 'student') {
                if (empty($form_data['school_id'])) {
                    throw new RuntimeException('A student must belong to a school.');
                }
                $stuNum = generateStudentNumber($pdo, (int)$form_data['school_id']);
                $stmt = $pdo->prepare("INSERT INTO students (school_id, user_id, student_number, full_name, email, enrollment_date, status) VALUES (?, ?, ?, ?, ?, CURDATE(), ?)");
                $stmt->execute([
                    $form_data['school_id'], $uid, $stuNum, $form_data['full_name'], $form_data['email'],
                    $form_data['is_active'] === 1 ? 'Active' : 'Inactive'
                ]);
            }

            $pdo->commit();
            logActivity($pdo, (int)$_SESSION['user_id'], 'create', 'user', $uid, 'Created user: ' . $form_data['full_name'] . ' (' . $form_data['role'] . ')');
            header('Location: users.php?success=' . urlencode('User created successfully!'));
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
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
                        <input type="password" name="password" class="form-control" required minlength="10">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <select name="role" class="form-select" id="roleSelect">
                            <?php if (isSuperAdmin()): ?>
                                <option value="super_admin" <?php echo ($form_data['role'] ?? '') === 'super_admin' ? 'selected' : ''; ?>>Super Admin (Platform)</option>
                            <?php endif; ?>
                            <option value="school_admin" <?php echo ($form_data['role'] ?? '') === 'school_admin' ? 'selected' : ''; ?>>School Admin</option>
                            <option value="admin" <?php echo ($form_data['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                            <option value="teacher" <?php echo ($form_data['role'] ?? '') === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                            <option value="student" <?php echo ($form_data['role'] ?? 'student') === 'student' ? 'selected' : ''; ?>>Student</option>
                            <option value="parent" <?php echo ($form_data['role'] ?? '') === 'parent' ? 'selected' : ''; ?>>Parent / Guardian</option>
                        </select>
                    </div>
                    <div class="mb-3" id="schoolIdField" style="<?php echo (!isSuperAdmin() || ($form_data['role'] ?? '') === 'super_admin') ? 'display:none;' : ''; ?>">
                        <label class="form-label">School *</label>
                        <select name="school_id" class="form-select" required>
                            <option value="">Select a school</option>
                            <?php foreach ($schools as $school): ?>
                                <option value="<?php echo (int)$school['id']; ?>" <?php echo isset($form_data['school_id']) && $form_data['school_id'] === (int)$school['id'] ? 'selected' : ''; ?>><?php echo e($school['school_name'] . ' (' . $school['school_code'] . ')'); ?></option>
                            <?php endforeach; ?>
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