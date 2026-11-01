<?php
/**
 * Helper Functions
 * Academics Student Management System
 */

// Start session with secure settings
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);

        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', 1);
        if ($isHttps) {
            ini_set('session.cookie_secure', 1);
        }
        session_start();

        // Idle timeout (default 30 minutes) to protect unattended sessions.
        $lifetime = (int)(getenv('SESSION_LIFETIME') ?: 1800);
        if ($lifetime > 0) {
            $now = time();
            if (!empty($_SESSION['last_activity']) && ($now - (int)$_SESSION['last_activity']) > $lifetime) {
                $_SESSION = [];
                if (ini_get('session.use_cookies')) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
                }
                session_destroy();
                session_start();
                session_regenerate_id(true);
            }
            $_SESSION['last_activity'] = $now;
        }
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

// Render a CSRF hidden input for forms.
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . e(generateCsrfToken()) . '">';
}

// Reject state-changing requests that did not arrive as POST.
function requirePost() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Method Not Allowed');
    }
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

/* =========================================================================
   Gradebook helpers
   ========================================================================= */
function getAssessmentTypes(): array
{
    return ['exam' => 'Exam', 'quiz' => 'Quiz', 'assignment' => 'Assignment', 'project' => 'Project', 'other' => 'Other'];
}

function createAssessment(PDO $pdo, int $schoolId, int $courseId, string $title, string $type, float $maxMarks, float $weight, ?string $dueDate, ?string $description, ?int $createdBy): int
{
    $stmt = $pdo->prepare("INSERT INTO assessments (school_id, course_id, title, assessment_type, max_marks, weight, due_date, description, created_by)
        VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$schoolId, $courseId, $title, $type, $maxMarks, $weight, $dueDate, $description, $createdBy]);
    return (int)$pdo->lastInsertId();
}

function getAssessmentsByCourse(PDO $pdo, int $schoolId, int $courseId): array
{
    $stmt = $pdo->prepare("SELECT a.*, u.full_name AS creator_name FROM assessments a LEFT JOIN users u ON u.id = a.created_by WHERE a.school_id = ? AND a.course_id = ? ORDER BY a.due_date IS NULL, a.due_date ASC, a.id ASC");
    $stmt->execute([$schoolId, $courseId]);
    return $stmt->fetchAll();
}

function recordMark(PDO $pdo, int $schoolId, int $assessmentId, int $studentId, ?float $marks, ?string $remarks, ?int $gradedBy): bool
{
    $stmt = $pdo->prepare("INSERT INTO student_marks (school_id, assessment_id, student_id, marks_obtained, remarks, graded_by, graded_at)
        VALUES (?,?,?,?,?,?,NOW())
        ON DUPLICATE KEY UPDATE marks_obtained=VALUES(marks_obtained), remarks=VALUES(remarks), graded_by=VALUES(graded_by), graded_at=NOW(), updated_at=NOW()");
    return $stmt->execute([$schoolId, $assessmentId, $studentId, $marks, $remarks, $gradedBy]);
}

function getCourseGradebook(PDO $pdo, int $schoolId, int $courseId): array
{
    $assessments = getAssessmentsByCourse($pdo, $schoolId, $courseId);
    $stmt = $pdo->prepare("SELECT st.id, st.full_name, st.student_number FROM students st JOIN enrollments e ON e.student_id = st.id WHERE e.course_id = ? AND e.status = 'Enrolled' AND st.school_id = ? AND e.school_id = ? ORDER BY st.full_name ASC");
    $stmt->execute([$courseId, $schoolId, $schoolId]);
    $students = $stmt->fetchAll();
    $marks = [];
    if ($assessments) {
        $ids = implode(',', array_map('intval', array_column($assessments, 'id')));
        $mStmt = $pdo->prepare("SELECT * FROM student_marks WHERE assessment_id IN ($ids) AND school_id = ?");
        $mStmt->execute([$schoolId]);
        foreach ($mStmt->fetchAll() as $m) {
            $marks[(int)$m['student_id']][(int)$m['assessment_id']] = $m;
        }
    }
    return ['assessments' => $assessments, 'students' => $students, 'marks' => $marks];
}

function getStudentGradeSummary(PDO $pdo, int $schoolId, int $studentId): array
{
    $stmt = $pdo->prepare("SELECT a.title, a.assessment_type, a.max_marks, a.weight, a.due_date, c.course_name, c.course_code, sm.marks_obtained, sm.remarks
        FROM student_marks sm
        JOIN assessments a ON a.id = sm.assessment_id
        JOIN courses c ON c.id = a.course_id
        WHERE sm.student_id = ? AND sm.school_id = ?
        ORDER BY c.course_name, a.due_date");
    $stmt->execute([$studentId, $schoolId]);
    $rows = $stmt->fetchAll();
    $totalWeighted = 0; $totalWeight = 0;
    foreach ($rows as $r) {
        if ($r['marks_obtained'] === null || (float)$r['max_marks'] <= 0) continue;
        $pct = ((float)$r['marks_obtained'] / (float)$r['max_marks']) * 100;
        $w = (float)$r['weight'];
        $totalWeighted += $pct * $w;
        $totalWeight += $w;
    }
    $avg = $totalWeight > 0 ? round($totalWeighted / $totalWeight, 1) : 0;
    return ['rows' => $rows, 'average' => $avg];
}

/* =========================================================================
   Fees helpers
   ========================================================================= */
function getFeeFrequencies(): array
{
    return ['one-time' => 'One-time', 'monthly' => 'Monthly', 'termly' => 'Termly', 'yearly' => 'Yearly'];
}

function getInvoiceStatuses(): array
{
    return ['unpaid' => 'Unpaid', 'paid' => 'Paid', 'overdue' => 'Overdue', 'cancelled' => 'Cancelled'];
}

function generateInvoiceNumber(PDO $pdo, int $schoolId): string
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE school_id = ?");
    $stmt->execute([$schoolId]);
    $n = (int)$stmt->fetchColumn() + 1;
    return 'INV-' . str_pad((string)$schoolId, 3, '0', STR_PAD_LEFT) . '-' . str_pad((string)$n, 5, '0', STR_PAD_LEFT);
}

function createInvoice(PDO $pdo, int $schoolId, int $studentId, ?int $feeStructureId, string $title, float $amount, ?string $dueDate, ?int $createdBy): int
{
    $num = generateInvoiceNumber($pdo, $schoolId);
    $stmt = $pdo->prepare("INSERT INTO invoices (school_id, student_id, fee_structure_id, invoice_number, title, amount, due_date, created_by) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$schoolId, $studentId, $feeStructureId, $num, $title, $amount, $dueDate, $createdBy]);
    return (int)$pdo->lastInsertId();
}

function recordPayment(PDO $pdo, int $schoolId, int $invoiceId, float $amount, string $date, ?string $method, ?string $reference, ?int $receivedBy, ?string $notes): bool
{
    $stmt = $pdo->prepare("INSERT INTO payments (school_id, invoice_id, amount, payment_date, method, reference, received_by, notes) VALUES (?,?,?,?,?,?,?,?)");
    $ok = $stmt->execute([$schoolId, $invoiceId, $amount, $date, $method, $reference, $receivedBy, $notes]);
    if ($ok) {
        // mark invoice paid if fully covered
        $stmt = $pdo->prepare("SELECT amount FROM invoices WHERE id = ? AND school_id = ?");
        $stmt->execute([$invoiceId, $schoolId]);
        $invAmount = (float)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id = ? AND school_id = ?");
        $stmt->execute([$invoiceId, $schoolId]);
        $paid = (float)$stmt->fetchColumn();
        if ($paid >= $invAmount - 0.005) {
            $pdo->prepare("UPDATE invoices SET status='paid', paid_at=NOW(), payment_method=?, transaction_ref=? WHERE id=? AND school_id=?")
                ->execute([$method, $reference, $invoiceId, $schoolId]);
        }
    }
    return $ok;
}

function getInvoices(PDO $pdo, int $schoolId, ?int $studentId = null, ?string $status = null): array
{
    $where = "i.school_id = ?";
    $params = [$schoolId];
    if ($studentId) { $where .= " AND i.student_id = ?"; $params[] = $studentId; }
    if ($status) { $where .= " AND i.status = ?"; $params[] = $status; }
    $sql = "SELECT i.*, st.full_name, st.student_number FROM invoices i JOIN students st ON st.id = i.student_id WHERE $where ORDER BY i.issued_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getFeeSummary(PDO $pdo, int $schoolId): array
{
    $stmt = $pdo->prepare("SELECT status, COUNT(*) c, COALESCE(SUM(amount),0) amt FROM invoices WHERE school_id = ? GROUP BY status");
    $stmt->execute([$schoolId]);
    $out = ['unpaid' => 0, 'paid' => 0, 'overdue' => 0, 'cancelled' => 0, 'unpaid_amt' => 0, 'paid_amt' => 0, 'overdue_amt' => 0];
    foreach ($stmt->fetchAll() as $r) {
        $out[$r['status']] = (int)$r['c'];
        $out[$r['status'] . '_amt'] = (float)$r['amt'];
    }
    // mark overdue
    $pdo->prepare("UPDATE invoices SET status='overdue' WHERE school_id = ? AND status='unpaid' AND due_date IS NOT NULL AND due_date < CURDATE()")->execute([$schoolId]);
    return $out;
}

function getStudentFeeSummary(PDO $pdo, int $schoolId, int $studentId): array
{
    $pdo->prepare("UPDATE invoices SET status='overdue' WHERE school_id = ? AND student_id = ? AND status='unpaid' AND due_date IS NOT NULL AND due_date < CURDATE()")->execute([$schoolId, $studentId]);
    $stmt = $pdo->prepare("SELECT status, COUNT(*) c, COALESCE(SUM(amount),0) amt FROM invoices WHERE school_id = ? AND student_id = ? GROUP BY status");
    $stmt->execute([$schoolId, $studentId]);
    $out = ['unpaid' => 0, 'paid' => 0, 'overdue' => 0, 'cancelled' => 0, 'total' => 0, 'paid_amt' => 0, 'due_amt' => 0];
    foreach ($stmt->fetchAll() as $r) {
        $out[$r['status']] = (int)$r['c'];
        if ($r['status'] === 'paid') $out['paid_amt'] += (float)$r['amt'];
        else $out['due_amt'] += (float)$r['amt'];
        $out['total'] += (int)$r['c'];
    }
    return $out;
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
// $schoolId null => platform-wide alerts only (school_id IS NULL)
function getActiveEmergencyAlert($pdo, $schoolId = null) {
    try {
        if ($schoolId === null) {
            $stmt = $pdo->prepare("SELECT * FROM emergency_alerts
                WHERE is_active = 1 AND school_id IS NULL
                  AND effective_date <= CURDATE()
                  AND (end_date IS NULL OR end_date >= CURDATE())
                ORDER BY id DESC LIMIT 1");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("SELECT * FROM emergency_alerts
                WHERE is_active = 1
                  AND (school_id = ? OR school_id IS NULL)
                  AND effective_date <= CURDATE()
                  AND (end_date IS NULL OR end_date >= CURDATE())
                ORDER BY (school_id = ?) DESC, id DESC LIMIT 1");
            $stmt->execute([(int)$schoolId, (int)$schoolId]);
        }
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


