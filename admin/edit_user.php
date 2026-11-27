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
// Platform operators manage accounts in every school. School staff cannot.
if (!isSuperAdmin()) {
    require_record_school($user);
}

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
        'email' => strtolower(trim($_POST['email'] ?? '')),
        'send_setup_link' => isset($_POST['send_setup_link']),
        'role' => (string)($_POST['role'] ?? 'student'),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'school_id' => isset($_POST['school_id']) && $_POST['school_id'] !== ''
            ? (int)$_POST['school_id']
            : ($isSuperAdmin ? null : $currentSchoolId),
    ];

    if (mb_strlen($form_data['full_name']) < 2 || mb_strlen($form_data['full_name']) > 100) {
        $errors[] = 'Full name must be between 2 and 100 characters.';
    }
    if (!isValidEmail($form_data['email'])) {
        $errors[] = 'A valid email address is required.';
    }
    if (!in_array($form_data['role'], ['super_admin', 'school_admin', 'admin', 'teacher', 'student', 'parent'], true)) {
        $errors[] = 'Invalid role.';
    }
    if ($form_data['role'] === 'super_admin' && !$isSuperAdmin) {
        $errors[] = 'Only platform super administrators can assign super admin role.';
    }
    if ((int)$id === (int)($_SESSION['user_id'] ?? 0)
        && ($form_data['role'] !== (string)$user['role'] || $form_data['is_active'] !== 1)) {
        $errors[] = 'You cannot change your own role or deactivate your own account.';
    }

    if ($form_data['role'] === 'super_admin') {
        $form_data['school_id'] = null;
    } elseif (!$isSuperAdmin) {
        $form_data['school_id'] = $currentSchoolId;
    } elseif (empty($form_data['school_id'])) {
        $errors[] = 'A school must be selected for this role.';
    } else {
        $schoolCheck = $pdo->prepare("SELECT id FROM schools WHERE id = ? AND is_active = 1 LIMIT 1");
        $schoolCheck->execute([$form_data['school_id']]);
        if (!$schoolCheck->fetchColumn()) $errors[] = 'Selected school does not exist or is inactive.';
    }

    if (empty($errors)) {
        $duplicate = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
        $duplicate->execute([$form_data['email'], $id]);
        if ($duplicate->fetchColumn()) $errors[] = 'Email already in use.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $lockUser = $pdo->prepare("SELECT id, role, is_active, school_id FROM users WHERE id = ? FOR UPDATE");
            $lockUser->execute([$id]);
            $lockedUser = $lockUser->fetch(PDO::FETCH_ASSOC);
            if (!$lockedUser) throw new InvalidArgumentException('User no longer exists.');

            // Do not silently move existing school data across tenants. A
            // transfer needs a dedicated workflow that moves every dependent
            // academic, financial, attendance and messaging record together.
            $oldSchoolId = $lockedUser['school_id'] !== null ? (int)$lockedUser['school_id'] : null;
            $newSchoolId = $form_data['school_id'] !== null ? (int)$form_data['school_id'] : null;
            if ($oldSchoolId !== null && $newSchoolId !== null && $oldSchoolId !== $newSchoolId) {
                throw new InvalidArgumentException('School transfers are not supported from this screen.');
            }

            // Lock all active platform administrators before the last-admin
            // check, preventing two concurrent requests from removing both.
            if ($lockedUser['role'] === 'super_admin' && (int)$lockedUser['is_active'] === 1
                && ($form_data['role'] !== 'super_admin' || $form_data['is_active'] !== 1)) {
                $activeSupers = $pdo->query(
                    "SELECT id FROM users WHERE role = 'super_admin' AND is_active = 1 FOR UPDATE"
                )->fetchAll(PDO::FETCH_COLUMN);
                if (count($activeSupers) <= 1) {
                    throw new InvalidArgumentException('Cannot demote or deactivate the last active super administrator.');
                }
            }
            if ($lockedUser['role'] === 'school_admin' && (int)$lockedUser['is_active'] === 1
                && ($form_data['role'] !== 'school_admin' || $form_data['is_active'] !== 1)
                && $lockedUser['school_id'] !== null) {
                $schoolAdmins = $pdo->prepare(
                    "SELECT id FROM users WHERE role = 'school_admin' AND is_active = 1 AND school_id = ? FOR UPDATE"
                );
                $schoolAdmins->execute([(int)$lockedUser['school_id']]);
                if (count($schoolAdmins->fetchAll(PDO::FETCH_COLUMN)) <= 1) {
                    throw new InvalidArgumentException('Cannot demote or deactivate the last active school administrator.');
                }
            }

            $studentLock = $pdo->prepare("SELECT id, school_id FROM students WHERE user_id = ? LIMIT 1 FOR UPDATE");
            $studentLock->execute([$id]);
            $studentProfile = $studentLock->fetch(PDO::FETCH_ASSOC);

            if ($studentProfile && $form_data['role'] === 'student'
                && (int)$studentProfile['school_id'] !== (int)$form_data['school_id']) {
                throw new InvalidArgumentException('A student profile cannot be moved to another school from this screen.');
            }

            $versionSql = '';
            $resetPassword = !empty($form_data['send_setup_link']);
            if ($resetPassword
                || (string)$lockedUser['role'] !== (string)$form_data['role']
                || (int)$lockedUser['is_active'] !== (int)$form_data['is_active']) {
                if (dbTableHasColumn($pdo, 'users', 'session_version')) {
                    $versionSql = ', session_version = session_version + 1';
                }
            }
            if ($resetPassword) {
                $hashed = hashUnusablePassword();
                $updateUser = $pdo->prepare(
                    "UPDATE users SET full_name = ?, email = ?, password = ?, role = ?, is_active = ?,
                     school_id = ?, must_change_password = 1, updated_at = NOW(){$versionSql} WHERE id = ?"
                );
                $updateUser->execute([
                    $form_data['full_name'], $form_data['email'], $hashed, $form_data['role'],
                    $form_data['is_active'], $form_data['school_id'], $id
                ]);
            } else {
                $updateUser = $pdo->prepare(
                    "UPDATE users SET full_name = ?, email = ?, role = ?, is_active = ?,
                     school_id = ?, updated_at = NOW(){$versionSql} WHERE id = ?"
                );
                $updateUser->execute([
                    $form_data['full_name'], $form_data['email'], $form_data['role'],
                    $form_data['is_active'], $form_data['school_id'], $id
                ]);
            }

            if ($form_data['role'] === 'student') {
                if (!$studentProfile) {
                    $studentNumber = generateStudentNumber($pdo, (int)$form_data['school_id']);
                    $createStudent = $pdo->prepare(
                        "INSERT INTO students (school_id, user_id, student_number, full_name, email, enrollment_date, status)
                         VALUES (?, ?, ?, ?, ?, CURDATE(), ?)"
                    );
                    $createStudent->execute([
                        (int)$form_data['school_id'], $id, $studentNumber,
                        $form_data['full_name'], $form_data['email'],
                        $form_data['is_active'] === 1 ? 'Active' : 'Inactive'
                    ]);
                } else {
                    $syncStudent = $pdo->prepare(
                        "UPDATE students
                         SET full_name = ?, email = ?,
                             status = CASE WHEN ? = 0 THEN 'Inactive' WHEN status = 'Inactive' THEN 'Active' ELSE status END,
                             updated_at = NOW()
                         WHERE id = ?"
                    );
                    $syncStudent->execute([
                        $form_data['full_name'], $form_data['email'],
                        $form_data['is_active'], (int)$studentProfile['id']
                    ]);
                }
            } elseif ($studentProfile) {
                // Preserve academic history while removing the profile from
                // active student lists after a role change.
                $pdo->prepare("UPDATE students SET status = 'Inactive', updated_at = NOW() WHERE id = ? AND status = 'Active'")
                    ->execute([(int)$studentProfile['id']]);
            }

            $pdo->commit();
            $sent = false;
            if ($resetPassword && (int)$form_data['is_active'] === 1) {
                $sent = deliverAccountSetupLink($pdo, $form_data['email']);
            }
            logActivity(
                $pdo,
                $_SESSION['user_id'],
                'update',
                'user',
                $id,
                'Updated user: ' . $form_data['full_name'] . ' (' . $form_data['role'] . ')'
            );
            if ($resetPassword) {
                setFlashMessage('success', accountProvisionedMessage($form_data['email'], $sent));
                header('Location: users.php');
            } else {
                header('Location: users.php?success=' . urlencode('User updated successfully!'));
            }
            exit;
        } catch (InvalidArgumentException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $e->getMessage();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
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
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="send_setup_link" class="form-check-input" id="send_setup_link" <?php echo !empty($form_data['send_setup_link']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="send_setup_link">Email a new setup link and sign out their other sessions</label>
                        <div class="form-text">This replaces the current password. The new password is never shown.</div>
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
