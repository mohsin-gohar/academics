<?php
/**
 * Helper Functions
 * Academics Student Management System
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
    return hasRole('admin') || hasRole('super_admin') || hasRole('school_admin');
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
    return isAdmin() || hasRole('teacher');
}

// Check if user is super admin
function isSuperAdmin() {
    if (hasRole('super_admin')) {
        return true;
    }
    // Legacy platform administrator: role "admin" with no school scope.
    return hasRole('admin') && getUserSchoolId() === null;
}

// Check if user is school admin
function isSchoolAdmin() {
    return hasRole('school_admin');
}

// Check if user is super admin or school admin (school-level management)
function isSchoolManagement() {
    return isSuperAdmin() || isSchoolAdmin() || isAdmin();
}

// Require super admin
function requireSuperAdmin() {
    requireAuth();
    if (!isSuperAdmin()) {
        http_response_code(403);
        header('Location: ' . getBasePath() . 'errors/403.php');
        exit;
    }
}

// Require school admin
function requireSchoolAdmin() {
    requireAuth();
    if (!isSchoolAdmin() && !isSuperAdmin()) {
        http_response_code(403);
        header('Location: ' . getBasePath() . 'errors/403.php');
        exit;
    }
}

// Require school management (super_admin, school_admin, or admin)
function requireSchoolManagement() {
    requireAuth();
    if (!isSchoolManagement()) {
        http_response_code(403);
        header('Location: ' . getBasePath() . 'errors/403.php');
        exit;
    }
}

// Get current user's school_id
function getUserSchoolId() {
    startSecureSession();
    return $_SESSION['school_id'] ?? null;
}

// Set user's school_id in session
function setUserSchoolId($schoolId) {
    startSecureSession();
    $_SESSION['school_id'] = (int)$schoolId;
}

// Get unread notification count
function getUnreadNotificationCount($pdo) {
    if (!isLoggedIn()) return 0;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

// Get active announcements for user's school and role
function getActiveAnnouncements($pdo, $limit = 5) {
    if (!isLoggedIn()) return [];
    try {
        $role = getUserRole();
        $schoolId = getUserSchoolId();
        $stmt = $pdo->prepare(
            "SELECT a.*, u.full_name as author_name FROM announcements a LEFT JOIN users u ON a.created_by = u.id WHERE a.school_id = ? AND a.is_active = 1 AND a.start_date <= NOW() AND (a.end_date IS NULL OR a.end_date >= NOW()) AND (a.target_roles = 'all' OR a.target_roles = ?) ORDER BY a.priority DESC, a.created_at DESC LIMIT ?"
        );
        $stmt->execute([$schoolId, $role, (int)$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Compute relative path from current script to project root

/* =========================================================================
   Attendance helpers
   ========================================================================= */
function getAttendanceStatuses(): array
{
    return ['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'excused' => 'Excused'];
}

function markAttendance(PDO $pdo, int $schoolId, int $studentId, string $date, string $status, ?string $remarks, ?int $markedBy): bool
{
    if (!array_key_exists($status, getAttendanceStatuses())) {
        return false;
    }
    $stmt = $pdo->prepare("INSERT INTO attendance (school_id, student_id, attendance_date, status, remarks, marked_by)
        VALUES (?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE status=VALUES(status), remarks=VALUES(remarks), marked_by=VALUES(marked_by), updated_at=NOW()");
    return $stmt->execute([$schoolId, $studentId, $date, $status, $remarks, $markedBy]);
}

function getAttendanceForDate(PDO $pdo, int $schoolId, string $date): array
{
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE school_id = ? AND attendance_date = ?");
    $stmt->execute([$schoolId, $date]);
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[(int)$r['student_id']] = $r;
    }
    return $out;
}

function getStudentAttendanceSummary(PDO $pdo, int $schoolId, int $studentId, ?string $from = null, ?string $to = null): array
{
    $where = "school_id = ? AND student_id = ?";
    $params = [$schoolId, $studentId];
    if ($from) { $where .= " AND attendance_date >= ?"; $params[] = $from; }
    if ($to)   { $where .= " AND attendance_date <= ?"; $params[] = $to; }
    $stmt = $pdo->prepare("SELECT status, COUNT(*) c FROM attendance WHERE $where GROUP BY status");
    $stmt->execute($params);
    $counts = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
    $total = 0;
    foreach ($stmt->fetchAll() as $r) {
        $counts[$r['status']] = (int)$r['c'];
        $total += (int)$r['c'];
    }
    $presentLike = $counts['present'] + $counts['late'] + $counts['excused'];
    $pct = $total > 0 ? round(($presentLike / $total) * 100, 1) : 0;
    return ['total' => $total, 'counts' => $counts, 'percentage' => $pct];
}

function getTodayAttendanceStats(PDO $pdo, int $schoolId, ?string $date = null): array
{
    $date = $date ?: date('Y-m-d');
    $stmt = $pdo->prepare("SELECT status, COUNT(*) c FROM attendance WHERE school_id = ? AND attendance_date = ? GROUP BY status");
    $stmt->execute([$schoolId, $date]);
    $out = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0];
    foreach ($stmt->fetchAll() as $r) {
        $out[$r['status']] = (int)$r['c'];
        $out['total'] += (int)$r['c'];
    }
    return $out;
}

function getAttendanceRecords(PDO $pdo, int $schoolId, string $from, string $to, ?int $studentId = null): array
{
    $where = "a.school_id = ? AND a.attendance_date BETWEEN ? AND ?";
    $params = [$schoolId, $from, $to];
    if ($studentId) { $where .= " AND a.student_id = ?"; $params[] = $studentId; }
    $sql = "SELECT a.*, st.full_name, st.student_number
            FROM attendance a
            JOIN students st ON st.id = a.student_id
            WHERE $where
            ORDER BY a.attendance_date DESC, st.full_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/* =========================================================================
   Password reset helpers
   ========================================================================= */
function createPasswordResetToken(PDO $pdo, string $email): ?string
{
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    if (!$stmt->fetchColumn()) {
        return null;
    }
    $pdo->prepare("DELETE FROM password_resets WHERE email = ? OR expires_at < NOW()")->execute([$email]);
    $raw = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    $stmt = $pdo->prepare("INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?,?,?)");
    $stmt->execute([$email, $hash, $expires]);
    return $raw;
}

function validatePasswordResetToken(PDO $pdo, string $raw): ?string
{
    $hash = hash('sha256', $raw);
    $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token_hash = ? AND used = 0 AND expires_at > NOW()");
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    return $row ? (string)$row['email'] : null;
}

function consumePasswordResetToken(PDO $pdo, string $raw, string $newPassword): bool
{
    $email = validatePasswordResetToken($pdo, $raw);
    if ($email === null) {
        return false;
    }
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->execute([$hash, $email]);
    $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token_hash = ?")->execute([hash('sha256', $raw)]);
    return true;
}
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
    requireAuth();
    if (!isAdmin()) {
        http_response_code(403);
        header('Location: ' . getBasePath() . 'errors/403.php');
        exit;
    }
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
        $schoolId = isset($_SESSION['school_id']) ? (int)$_SESSION['school_id'] : null;
        $stmt = $pdo->prepare("INSERT INTO activity_logs (school_id, user_id, action, entity_type, entity_id, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $schoolId,
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

// Get active emergency closure alert (if any)
function getActiveEmergencyAlert($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM emergency_alerts WHERE is_active = 1 AND effective_date <= CURDATE() AND (end_date IS NULL OR end_date >= CURDATE()) ORDER BY id DESC LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

// Get upcoming school events and holidays
function getSchoolEventsList($pdo, $limit = 6, $onlyUpcoming = true) {
    try {
        $where = $onlyUpcoming ? "WHERE event_date >= CURDATE() AND status != 'Cancelled'" : "";
        $stmt = $pdo->prepare("SELECT e.*, u.full_name as author_name FROM school_events e LEFT JOIN users u ON e.created_by = u.id $where ORDER BY e.event_date ASC LIMIT ?");
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}


