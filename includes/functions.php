<?php
/**
 * Helper Functions
 * EduSphere Student Management System
 */

// Start session with secure settings
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', 1);
        session_start();
    }
}

// Generate CSRF token
function generateCsrfToken() {
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF token
function validateCsrfToken($token) {
    startSecureSession();
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Check if user is logged in
function isLoggedIn() {
    startSecureSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Get current user role
function getUserRole() {
    startSecureSession();
    return $_SESSION['user_role'] ?? 'student';
}

// Check if user has role
function hasRole($role) {
    return getUserRole() === $role;
}

// Check if user is admin
function isAdmin() {
    return hasRole('admin');
}

// Check if user is teacher
function isTeacher() {
    return hasRole('teacher');
}

// Check if user is student
function isStudent() {
    return hasRole('student');
}

// Check if user is staff (admin or teacher)
function isStaff() {
    return hasRole('admin') || hasRole('teacher');
}

// Compute relative path from current script to project root
function getBasePath() {
    $scriptDir = dirname($_SERVER['SCRIPT_FILENAME']);
    $projectRoot = dirname(__DIR__);
    $relative = trim(str_replace('\\', '/', substr($scriptDir, strlen($projectRoot))), '/');
    $depth = $relative === '' ? 0 : substr_count($relative, '/') + 1;
    return str_repeat('../', $depth);
}

// Require authentication
function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: ' . getBasePath() . 'auth/login.php');
        exit;
    }
}

// Require specific role
function requireRole($role) {
    requireAuth();
    if (!hasRole($role)) {
        http_response_code(403);
        header('Location: ' . getBasePath() . 'errors/403.php');
        exit;
    }
}

// Require staff (admin or teacher)
function requireStaff() {
    requireAuth();
    if (!isStaff()) {
        http_response_code(403);
        header('Location: ' . getBasePath() . 'errors/403.php');
        exit;
    }
}

// Require admin
function requireAdmin() {
    requireRole('admin');
}

// Sanitize output (XSS prevention)
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Redirect with message
function redirect($url, $type = null, $message = null) {
    if ($type && $message) {
        $separator = (strpos($url, '?') !== false) ? '&' : '?';
        $url .= $separator . $type . '=' . urlencode($message);
    }
    header("Location: $url");
    exit;
}

// Get flash message
function getFlashMessage() {
    startSecureSession();
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $msg;
    }
    return null;
}

// Set flash message
function setFlashMessage($type, $message) {
    startSecureSession();
    $_SESSION['flash_message'] = ['type' => $type, 'text' => $message];
}

// Log activity
function logActivity($pdo, $userId, $action, $entityType = null, $entityId = null, $description = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255)
        ]);
    } catch (PDOException $e) {
        error_log("Activity log failed: " . $e->getMessage());
    }
}

// Check login rate limit
function checkLoginRateLimit($pdo, $email) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM login_attempts WHERE (email = ? OR ip_address = ?) AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute([$email, $ip]);
    $result = $stmt->fetch();
    return ($result['attempts'] ?? 0) < 5;
}

// Record login attempt
function recordLoginAttempt($pdo, $email) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $pdo->prepare("INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)");
    $stmt->execute([$email, $ip]);
}

// Clear login attempts on success
function clearLoginAttempts($pdo, $email) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE email = ? OR ip_address = ?");
    $stmt->execute([$email, $ip]);
}

// Format date
function formatDate($date, $format = 'M d, Y') {
    if (empty($date)) return '-';
    return date($format, strtotime($date));
}

// Format date and time
function formatDateTime($date, $format = 'M d, Y h:i A') {
    if (empty($date)) return '-';
    return date($format, strtotime($date));
}

// Safe CSV cell escaping (prevents spreadsheet formula injection: = + - @ \t \r)
function safeCsvCell($val) {
    if ($val === null) return '';
    $val = (string)$val;
    if (isset($val[0]) && in_array($val[0], ['=', '+', '-', '@', "\t", "\r"])) {
        return "'" . $val;
    }
    return $val;
}

// Get setting value
function getSetting($pdo, $key, $default = null) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM application_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

// Pagination helper
function getPagination($total, $perPage, $currentPage) {
    $totalPages = ceil($total / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

// Validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Generate unique student number
function generateStudentNumber($pdo) {
    $year = date('Y');
    try {
        $stmt = $pdo->prepare("SELECT student_number FROM students WHERE student_number LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute(['STU-' . $year . '-%']);
        $last = $stmt->fetchColumn();
        if ($last && preg_match('/STU-\d{4}-(\d+)/', $last, $matches)) {
            $next = intval($matches[1]) + 1;
        } else {
            $stmtCount = $pdo->query("SELECT COUNT(*) as count FROM students WHERE YEAR(created_at) = $year");
            $next = ($stmtCount->fetch()['count'] ?? 0) + 1;
        }
    } catch (Exception $e) {
        $next = rand(100, 999);
    }
    return 'STU-' . $year . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
}

