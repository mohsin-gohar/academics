<?php
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
$csrf_token = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrf_token)) {
    header('Location: register.php?error=' . urlencode('Invalid security token. Please try again.'));
    exit;
}

$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$school_id = (int)($_POST['school_id'] ?? 0);
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

$errors = [];

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

if (empty($full_name)) {
    $errors[] = 'Full name is required.';
} elseif (strlen($full_name) < 2) {
    $errors[] = 'Full name must be at least 2 characters long.';
} elseif (!preg_match('/^[a-zA-Z\s]+$/', $full_name)) {
    $errors[] = 'Full name can only contain letters and spaces.';
}

if (empty($email)) {
    $errors[] = 'Email address is required.';
} elseif (!isValidEmail($email)) {
    $errors[] = 'Please enter a valid email address.';
}

if (empty($password)) {
    $errors[] = 'Password is required.';
} elseif (strlen($password) < 6) {
    $errors[] = 'Password must be at least 6 characters long.';
}

if ($password !== $confirm_password) {
    $errors[] = 'Passwords do not match.';
}

if (!empty($errors)) {
    header('Location: register.php?error=' . urlencode($errors[0]));
    exit;
}

// Check duplicate email
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    header('Location: register.php?error=' . urlencode('Email address is already registered.'));
    exit;
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, school_id, created_at) VALUES (?, ?, ?, 'student', ?, NOW())");
    $stmt->execute([$full_name, $email, $hashed_password, $school_id]);
    $userId = $pdo->lastInsertId();

    // Create student profile
    $student_number = generateStudentNumber($pdo);
    $stmt = $pdo->prepare("INSERT INTO students (user_id, school_id, student_number, full_name, email, enrollment_date, status) VALUES (?, ?, ?, ?, ?, CURDATE(), 'Active')");
    $stmt->execute([$userId, $school_id, $student_number, $full_name, $email]);

    logActivity($pdo, $userId, 'register', 'user', $userId, 'New user registered');

    header('Location: login.php?success=' . urlencode('Account created successfully! You can now log in.'));
    exit;
} catch (PDOException $e) {
    error_log("Registration failed: " . $e->getMessage());
    header('Location: register.php?error=' . urlencode('Registration failed. Please try again.'));
    exit;
}
