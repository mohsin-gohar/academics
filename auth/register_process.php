<?php
/**
 * Self-registration handler (H3 hardening)
 *
 * Security model:
 *  - Self-registration is globally gated by application_settings.registration_enabled.
 *  - The chosen school must exist, be accessible (not suspended/disabled/expired),
 *    and the request must not contradict an existing school context (subdomain/code).
 *  - Passwords must meet the platform strength policy.
 *  - New accounts are created INACTIVE (is_active = 0) pending school approval,
 *    so a public signup can never be "active immediately" with no review.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/saas.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

// CSRF validation
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    header('Location: register.php?error=' . urlencode('Invalid security token. Please try again.'));
    exit;
}

$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$school_id = (int)($_POST['school_id'] ?? 0);
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

$errors = [];

// 1. Global registration gate
$registrationEnabled = getSetting($pdo, 'registration_enabled', '1');
if ((string)$registrationEnabled !== '1') {
    $errors[] = 'Self-registration is currently disabled. Please contact your school administrator.';
}

// 2. School validation + context consistency
$schoolStmt = $pdo->prepare("SELECT * FROM schools WHERE id = ? LIMIT 1");
$schoolStmt->execute([$school_id]);
$selectedSchool = $schoolStmt->fetch() ?: null;
if (!$selectedSchool || !saas_school_is_accessible($selectedSchool)) {
    $errors[] = 'Please select an active school.';
} else {
    $contextSchool = saas_bootstrap_school_context($pdo);
    if ($contextSchool && (int)$contextSchool['id'] !== $school_id) {
        $errors[] = 'This registration page belongs to a different school. Please use your school’s own portal.';
    }
}

// 3. Name
if (empty($full_name)) {
    $errors[] = 'Full name is required.';
} elseif (strlen($full_name) < 2) {
    $errors[] = 'Full name must be at least 2 characters long.';
} elseif (!preg_match('/^[\p{L}\s\.\-\']+$/u', $full_name)) {
    $errors[] = 'Full name contains invalid characters.';
}

// 4. Email
if (empty($email)) {
    $errors[] = 'Email address is required.';
} elseif (!isValidEmail($email)) {
    $errors[] = 'Please enter a valid email address.';
}

// 5. Password — enforce the platform strength policy (no more 6-char passwords)
if (empty($password)) {
    $errors[] = 'Password is required.';
} else {
    $pwErrors = validatePasswordStrength($password);
    if ($pwErrors) {
        $errors[] = implode(' ', $pwErrors);
    }
}
if ($password !== $confirm_password) {
    $errors[] = 'Passwords do not match.';
}

if (!empty($errors)) {
    header('Location: register.php?error=' . urlencode($errors[0]));
    exit;
}

// 6. Duplicate email
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    header('Location: register.php?error=' . urlencode('Email address is already registered.'));
    exit;
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    $pdo->beginTransaction();

    // 7. Create INACTIVE account pending school approval (H3)
    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, school_id, is_active, must_change_password)
                           VALUES (?, ?, ?, 'student', ?, 0, 1)");
    $stmt->execute([$full_name, $email, $hashed_password, $school_id]);
    $userId = (int)$pdo->lastInsertId();

    // Student profile (still linked so admins can review the signup)
    $student_number = generateStudentNumber($pdo);
    $stmt = $pdo->prepare("INSERT INTO students (school_id, user_id, student_number, full_name, email, enrollment_date, status)
                           VALUES (?, ?, ?, ?, ?, CURDATE(), 'Active')");
    $stmt->execute([$school_id, $userId, $student_number, $full_name, $email]);

    logActivity($pdo, $userId, 'self_register', 'user', $userId,
        'Self-registration submitted for school #' . $school_id . ' (pending approval)');

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Registration failed: " . $e->getMessage());
    header('Location: register.php?error=' . urlencode('Registration failed. Please try again.'));
    exit;
}

header('Location: login.php?success=' . urlencode(
    'Your account has been created and is awaiting approval by your school administrator. You will be able to sign in once it is activated.'
));
exit;