<?php
$page_title = 'My Profile';
require_once 'includes/header.php';
requireAuth();

$userId = (int)$_SESSION['user_id'];
$success = '';
$error = '';

// Fetch user
$stmt = $pdo->prepare("SELECT id, full_name, email, role, created_at, last_login FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please provide a valid name and email.';
            } else {
                // Check email uniqueness
                $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
                $chk->execute([$email, $userId]);
                if ($chk->fetch()) {
                    $error = 'Email already in use by another account.';
                } else {
                    $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?")->execute([$name, $email, $userId]);
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    logActivity($pdo, $userId, 'update', 'user', $userId, 'Profile updated');
                    $success = 'Profile updated successfully.';
                    $stmt->execute([$userId]); $user = $stmt->fetch();
                }
            }
        } elseif ($action === 'change_password') {
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            $row = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $row->execute([$userId]);
            $hash = $row->fetchColumn();
            if (!password_verify($current, $hash)) {
                $error = 'Current password is incorrect.';
            } elseif (strlen($new) < 8) {
                $error = 'New password must be at least 8 characters.';
            } elseif ($new !== $confirm) {
                $error = 'New passwords do not match.';
            } else {
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
                logActivity($pdo, $userId, 'update', 'user', $userId, 'Password changed');
                $success = 'Password changed successfully.';
            }
        }
    }
}

// Student profile if applicable
$student = null;
if (isStudent()) {
    $st = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
    $st->execute([$userId]);
    $student = $st->fetch();
}
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card p-4 text-center">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['full_name']); ?>&background=4f46e5&color=fff&size=128" alt="Avatar" class="user-profile-avatar mx-auto mb-3" style="width:96px;height:96px;">
            <h4 class="mb-1"><?php echo e($user['full_name']); ?></h4>
            <p class="text-muted small mb-2"><?php echo e($user['email']); ?></p>
            <span class="badge bg-primary text-capitalize"><?php echo e($user['role']); ?></span>
            <hr class="my-3">
            <div class="small text-muted">
                <div><i class="fas fa-calendar me-2"></i>Joined <?php echo formatDate($user['created_at']); ?></div>
                <div class="mt-1"><i class="fas fa-clock me-2"></i>Last login <?php echo $user['last_login'] ? formatDateTime($user['last_login']) : 'Never'; ?></div>
            </div>
            <?php if ($student): ?>
            <hr class="my-3">
            <a href="students/show.php?id=<?php echo (int)$student['id']; ?>" class="btn btn-outline-primary btn-sm w-100">
                <i class="fas fa-id-card me-1"></i> View Academic Profile
            </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-8">
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible"><i class="fas fa-check-circle me-2"></i><?php echo e($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible"><i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?></div>
        <?php endif; ?>

        <div class="card p-4 mb-4">
            <h5 class="mb-3"><i class="fas fa-user-edit me-2 text-primary"></i>Edit Profile</h5>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="update_profile">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo e($user['full_name']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?php echo e($user['email']); ?>" required>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Changes</button>
                </div>
            </form>
        </div>

        <div class="card p-4">
            <h5 class="mb-3"><i class="fas fa-lock me-2 text-warning"></i>Change Password</h5>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="change_password">
                <div class="mb-3">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" minlength="8" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-warning text-white"><i class="fas fa-key me-1"></i> Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
