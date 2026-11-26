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

// Alias for getUserRole
function getRole() {
    return getUserRole();
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
    return hasRole('super_admin');
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

// Require student access
function requireStudent() {
    requireAuth();
    if (!isStudent()) {
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
   In-app notifications
   ========================================================================= */

/**
 * Create an in-app notification for a user.
 *
 * The notifications table existed but nothing ever wrote to it, leaving the
 * bell/AJAX reader with an empty feed. This is the single write path so every
 * module (tasks, fees, messages, leave, announcements) notifies consistently.
 */
function notifyUser(PDO $pdo, int $userId, string $title, string $message, string $link = '', string $icon = 'fa-bell', string $color = 'primary'): bool
{
    try {
        $schoolId = null;
        $uStmt = $pdo->prepare("SELECT school_id FROM users WHERE id = ? LIMIT 1");
        $uStmt->execute([$userId]);
        $schoolId = $uStmt->fetchColumn();
        $schoolId = $schoolId === false || $schoolId === null ? currentSchoolId() : (int)$schoolId;

        $stmt = $pdo->prepare("INSERT INTO notifications (school_id, user_id, title, message, icon, color, link)
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        return (bool)$stmt->execute([
            $schoolId !== null ? (int)$schoolId : null,
            $userId,
            mb_substr($title, 0, 200),
            mb_substr($message, 0, 1000),
            $icon !== '' ? $icon : 'fa-bell',
            in_array($color, ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'], true) ? $color : 'primary',
            $link !== '' ? $link : null,
        ]);
    } catch (Throwable $e) {
        // A notification must never break the action that produced it.
        error_log('notifyUser failed: ' . $e->getMessage());
        return false;
    }
}

/** Notify every user of a role inside a school (announcements, alerts). */
function notifyRole(PDO $pdo, int $schoolId, string $role, string $title, string $message, string $link = '', string $icon = 'fa-bell', string $color = 'primary'): int
{
    $count = 0;
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE school_id = ? AND role = ? AND is_active = 1");
        $stmt->execute([$schoolId, $role]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            if (notifyUser($pdo, (int)$uid, $title, $message, $link, $icon, $color)) $count++;
        }
    } catch (Throwable $e) {
        error_log('notifyRole failed: ' . $e->getMessage());
    }
    return $count;
}

/* =========================================================================
   Attendance helpers
   ========================================================================= */
function getAttendanceStatuses(): array
{
    return ['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'excused' => 'Excused'];
}

function markAttendance(PDO $pdo, int $schoolId, int $studentId, string $date, string $status, ?string $remarks, ?int $markedBy): bool
{
    if (!array_key_exists($status, getAttendanceStatuses())
        || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date)
        || !checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4))) {
        return false;
    }
    $studentCheck = $pdo->prepare("SELECT id FROM students WHERE id = ? AND school_id = ? LIMIT 1");
    $studentCheck->execute([$studentId, $schoolId]);
    if (!$studentCheck->fetchColumn()) {
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
    if (!preg_match('/^[a-f0-9]{64}$/', $raw) || $newPassword === '') {
        return false;
    }

    $ownsTransaction = !$pdo->inTransaction();
    try {
        if ($ownsTransaction) $pdo->beginTransaction();

        // Lock the token row so concurrent requests cannot redeem it twice.
        $tokenStmt = $pdo->prepare(
            "SELECT id, email FROM password_resets
             WHERE token_hash = ? AND used = 0 AND expires_at > NOW()
             LIMIT 1 FOR UPDATE"
        );
        $tokenStmt->execute([hash('sha256', $raw)]);
        $token = $tokenStmt->fetch(PDO::FETCH_ASSOC);
        if (!$token) {
            if ($ownsTransaction) $pdo->rollBack();
            return false;
        }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($passwordHash === false) throw new RuntimeException('Password hashing failed.');

        $userStmt = $pdo->prepare(
            "UPDATE users SET password = ?, must_change_password = 0
             WHERE email = ? AND is_active = 1"
        );
        $userStmt->execute([$passwordHash, $token['email']]);
        if ($userStmt->rowCount() !== 1) {
            if ($ownsTransaction) $pdo->rollBack();
            return false;
        }

        // Invalidate every outstanding reset link for this account.
        $consumeStmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0");
        $consumeStmt->execute([$token['email']]);

        if ($ownsTransaction) $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        error_log('Password reset token consumption failed: ' . $e->getMessage());
        return false;
    }
}

/* =========================================================================
   Email helpers (C1: password reset delivery)
   ========================================================================= */

/**
 * Send a password reset email using the best available transport.
 *
 * Order of preference:
 *   1. SMTP (configured via MAIL_* env vars)
 *   2. PHP mail() wrapper
 *   3. Local dev fallback: append link to storage/logs/reset-links.log
 *
 * Never returns the raw token to the caller; the response is always generic.
 */
function sendPasswordResetEmail(string $email, string $token): bool
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return false;
    }

    // Reset links sent by email must be absolute and point to /auth/reset_password.php.
    // Prefer an explicitly configured canonical URL; never trust an arbitrary Host
    // header when constructing production password-reset links.
    $appUrl = trim((string)(getenv('APP_URL') ?: ''));
    if ($appUrl === '' && defined('APP_DOMAIN') && trim((string)APP_DOMAIN) !== '') {
        $domain = trim((string)APP_DOMAIN);
        $appUrl = preg_match('#^https?://#i', $domain) ? $domain : 'https://' . $domain;
    }
    if ($appUrl === '' && (!defined('IS_PRODUCTION') || !IS_PRODUCTION)) {
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        if (!preg_match('/^[a-z0-9.-]+(?::[0-9]{1,5})?$/i', $host)) return false;
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off');
        $scheme = $https ? 'https' : 'http';
        $scriptDir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/auth/forgot_password.php')));
        $basePath = rtrim(dirname($scriptDir), '/.');
        $appUrl = $scheme . '://' . $host . $basePath;
    }
    if ($appUrl === '' || !preg_match('#^https?://#i', $appUrl)) {
        error_log('Password reset delivery skipped: canonical APP_URL/APP_DOMAIN is not configured.');
        return false;
    }
    $resetLink = rtrim($appUrl, '/') . '/auth/reset_password.php?token=' . urlencode($token);

    $mailHost = trim((string)(getenv('MAIL_HOST') ?: ''));
    $mailPort = (int)(getenv('MAIL_PORT') ?: 587);
    $mailUser = (string)(getenv('MAIL_USERNAME') ?: '');
    $mailPass = (string)(getenv('MAIL_PASSWORD') ?: '');
    $mailFrom = trim((string)(getenv('MAIL_FROM_ADDRESS') ?: 'noreply@academics.app'));
    $mailFromName = trim((string)(getenv('MAIL_FROM_NAME') ?: 'Academics'));
    $mailEncrypt = strtolower(trim((string)(getenv('MAIL_ENCRYPTION') ?: 'tls')));
    $subject = 'Password Reset - Academics';
    $body = "Click the link below to reset your password:\n\n{$resetLink}\n\nThis link expires in 1 hour and can be used once.";

    // Use configured SMTP credentials when present. This is a real SMTP
    // transport (STARTTLS/SMTPS + AUTH LOGIN), not PHP mail() masquerading as SMTP.
    if ($mailHost !== '' && $mailPort > 0 && $mailPort <= 65535
        && filter_var($mailFrom, FILTER_VALIDATE_EMAIL)
        && !preg_match('/[\r\n]/', $mailHost)
        && in_array($mailEncrypt, ['tls', 'ssl', 'none'], true)) {
        $socket = null;
        try {
            $scheme = $mailEncrypt === 'ssl' ? 'ssl://' : 'tcp://';
            $sslContext = stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'peer_name' => $mailHost,
                    'SNI_enabled' => true,
                ],
            ]);
            $errno = 0;
            $errstr = '';
            $socket = @stream_socket_client(
                $scheme . $mailHost . ':' . $mailPort,
                $errno,
                $errstr,
                15,
                STREAM_CLIENT_CONNECT,
                $sslContext
            );
            if (!is_resource($socket)) {
                throw new RuntimeException('SMTP connection failed (' . $errno . ').');
            }
            stream_set_timeout($socket, 15);

            $readResponse = static function () use ($socket): array {
                $response = '';
                $line = '';
                do {
                    $line = fgets($socket, 515);
                    if ($line === false) throw new RuntimeException('SMTP server closed the connection.');
                    $response .= $line;
                } while (isset($line[3]) && $line[3] === '-');
                return [(int)substr($line, 0, 3), trim($response)];
            };
            $command = static function (string $command, array $expected) use ($socket, $readResponse): string {
                $written = fwrite($socket, $command . "\r\n");
                if ($written === false) throw new RuntimeException('Could not write SMTP command.');
                [$code, $response] = $readResponse();
                if (!in_array($code, $expected, true)) {
                    throw new RuntimeException('SMTP command rejected with code ' . $code . '.');
                }
                return $response;
            };

            [$greetingCode] = $readResponse();
            if ($greetingCode !== 220) throw new RuntimeException('SMTP server greeting was rejected.');

            $hello = preg_replace('/[^a-zA-Z0-9.-]/', '', (string)(gethostname() ?: 'localhost'));
            if ($hello === '') $hello = 'localhost';
            $command('EHLO ' . $hello, [250]);

            if ($mailEncrypt === 'tls') {
                $command('STARTTLS', [220]);
                $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($crypto !== true) throw new RuntimeException('SMTP STARTTLS negotiation failed.');
                $command('EHLO ' . $hello, [250]);
            }

            if ($mailUser !== '' || $mailPass !== '') {
                if ($mailUser === '' || $mailPass === '') {
                    throw new RuntimeException('Both SMTP username and password are required.');
                }
                $command('AUTH LOGIN', [334]);
                $command(base64_encode($mailUser), [334]);
                $command(base64_encode($mailPass), [235]);
            }

            $command('MAIL FROM:<' . $mailFrom . '>', [250]);
            $command('RCPT TO:<' . $email . '>', [250, 251]);
            $command('DATA', [354]);

            $safeName = str_replace(["\r", "\n"], '', $mailFromName);
            $encodedName = '=?UTF-8?B?' . base64_encode($safeName) . '?=';
            $fromDomain = substr(strrchr($mailFrom, '@') ?: '@localhost', 1);
            $headers = [
                'Date: ' . date(DATE_RFC2822),
                'From: ' . $encodedName . ' <' . $mailFrom . '>',
                'To: <' . $email . '>',
                'Reply-To: ' . $mailFrom,
                'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $fromDomain . '>',
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
                'X-Mailer: Academics',
            ];
            $message = implode("\r\n", $headers) . "\r\n\r\n";
            $bodyLines = preg_split('/\r\n|\r|\n/', $body) ?: [];
            foreach ($bodyLines as $line) {
                $message .= (str_starts_with($line, '.') ? '.' : '') . $line . "\r\n";
            }
            $written = fwrite($socket, $message . ".\r\n");
            if ($written === false) throw new RuntimeException('Could not write SMTP message.');
            [$dataCode] = $readResponse();
            if ($dataCode !== 250) throw new RuntimeException('SMTP server did not accept the message.');

            try { $command('QUIT', [221]); } catch (Throwable $ignored) {}
            fclose($socket);
            return true;
        } catch (Throwable $e) {
            if (is_resource($socket)) fclose($socket);
            error_log('SMTP password reset delivery failed: ' . $e->getMessage());
        }
    }

    // Fallback for hosts that expose PHP's configured mail transport.
    if (function_exists('mail') && filter_var($mailFrom, FILTER_VALIDATE_EMAIL)) {
        try {
            $safeName = str_replace(["\r", "\n"], '', $mailFromName);
            $headers = "From: {$safeName} <{$mailFrom}>\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            if (@mail($email, $subject, $body, $headers, '-f' . $mailFrom)) return true;
        } catch (Throwable $e) {
            error_log('PHP mail() password reset delivery failed: ' . $e->getMessage());
        }
    }

    // Local development fallback: the log directory is blocked by .htaccess.
    $isLocalDev = (getenv('APP_ENV') ?? 'local') === 'local'
        && in_array($_SERVER['HTTP_HOST'] ?? 'localhost', ['localhost', '127.0.0.1', '::1'], true);
    if ($isLocalDev) {
        try {
            $logDir = dirname(__DIR__) . '/storage/logs';
            if (!is_dir($logDir)) @mkdir($logDir, 0700, true);
            $logFile = $logDir . '/reset-links.log';
            $entry = date('Y-m-d H:i:s') . ' | ' . $email . ' | ' . $resetLink . "\n";
            file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
            return true;
        } catch (Throwable $e) {
            error_log('Local reset link logging failed: ' . $e->getMessage());
        }
    }

    return false;
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
    $courseCheck = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND school_id = ? LIMIT 1");
    $courseCheck->execute([$courseId, $schoolId]);
    if (!$courseCheck->fetchColumn() || $maxMarks <= 0 || $weight <= 0 || trim($title) === '') {
        return 0;
    }

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
    // H4: validate the mark against the assessment's max and confirm the
    // student is actually enrolled in the assessment's course.
    $aStmt = $pdo->prepare("SELECT a.max_marks, a.course_id FROM assessments a WHERE a.id = ? AND a.school_id = ?");
    $aStmt->execute([$assessmentId, $schoolId]);
    $assessment = $aStmt->fetch(PDO::FETCH_ASSOC);
    if (!$assessment) {
        return false;
    }

    $enrStmt = $pdo->prepare("SELECT st.id FROM students st
        JOIN enrollments e ON e.student_id = st.id AND e.school_id = st.school_id
        JOIN courses c ON c.id = e.course_id AND c.school_id = e.school_id
        WHERE st.id = ? AND st.school_id = ? AND e.school_id = ?
          AND e.course_id = ? AND e.status = 'Enrolled' LIMIT 1");
    $enrStmt->execute([$studentId, $schoolId, $schoolId, (int)$assessment['course_id']]);
    if (!$enrStmt->fetchColumn()) {
        return false;
    }

    $max = (float)$assessment['max_marks'];
    if ($marks !== null && ($marks < 0 || $marks > $max)) {
        return false;
    }

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
    $prefix = 'INV-' . str_pad((string)$schoolId, 3, '0', STR_PAD_LEFT) . '-';

    // Derive from the highest issued number, not COUNT(*): cancelled invoices
    // make the count drift and produce duplicates of a live number.
    $next = 1;
    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(invoice_number, '-', -1) AS UNSIGNED)) FROM invoices WHERE school_id = ? AND invoice_number LIKE ?");
    $stmt->execute([$schoolId, $prefix . '%']);
    $next = max(1, (int)$stmt->fetchColumn() + 1);

    // Unique key uq_invoice_number guards the race; step over collisions so a
    // concurrent request can never fail the whole invoice creation.
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $candidate = $prefix . str_pad((string)$next, 5, '0', STR_PAD_LEFT);
        $chk = $pdo->prepare("SELECT 1 FROM invoices WHERE invoice_number = ? LIMIT 1");
        $chk->execute([$candidate]);
        if (!$chk->fetchColumn()) {
            return $candidate;
        }
        $next++;
    }
    return $prefix . str_pad((string)($next + random_int(1, 999)), 5, '0', STR_PAD_LEFT);
}

function createInvoice(PDO $pdo, int $schoolId, int $studentId, ?int $feeStructureId, string $title, float $amount, ?string $dueDate, ?int $createdBy): int
{
    if ($amount <= 0 || trim($title) === '') {
        return 0;
    }
    $studentCheck = $pdo->prepare("SELECT id FROM students WHERE id = ? AND school_id = ? LIMIT 1");
    $studentCheck->execute([$studentId, $schoolId]);
    if (!$studentCheck->fetchColumn()) {
        return 0;
    }
    if ($feeStructureId !== null) {
        $feeCheck = $pdo->prepare("SELECT id FROM fee_structures WHERE id = ? AND school_id = ? LIMIT 1");
        $feeCheck->execute([$feeStructureId, $schoolId]);
        if (!$feeCheck->fetchColumn()) {
            return 0;
        }
    }

    $num = generateInvoiceNumber($pdo, $schoolId);
    $stmt = $pdo->prepare("INSERT INTO invoices (school_id, student_id, fee_structure_id, invoice_number, title, amount, due_date, created_by) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$schoolId, $studentId, $feeStructureId, $num, $title, $amount, $dueDate, $createdBy]);
    return (int)$pdo->lastInsertId();
}

function recordPayment(PDO $pdo, int $schoolId, int $invoiceId, float $amount, string $date, ?string $method, ?string $reference, ?int $receivedBy, ?string $notes): bool
{
    // Validate all values at the shared write boundary, not only in the form.
    $method = $method === null ? null : strtolower(trim($method));
    $methodAliases = ['bank transfer' => 'bank', 'bank_transfer' => 'bank', 'credit card' => 'card'];
    if ($method !== null) $method = $methodAliases[$method] ?? $method;
    $validMethods = ['cash', 'bank', 'card', 'online', 'cheque'];
    if ($amount <= 0 || abs($amount - round($amount, 2)) > 0.00001
        || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date)
        || !checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4))
        || ($method !== null && !in_array($method, $validMethods, true))
        || ($reference !== null && mb_strlen($reference) > 100)) {
        return false;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT amount, status FROM invoices WHERE id = ? AND school_id = ? FOR UPDATE");
        $stmt->execute([$invoiceId, $schoolId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$invoice || in_array($invoice['status'], ['cancelled', 'paid'], true)) {
            $pdo->rollBack();
            return false;
        }

        $invAmount = (float)$invoice['amount'];
        $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id = ? AND school_id = ?");
        $paidStmt->execute([$invoiceId, $schoolId]);
        $alreadyPaid = (float)$paidStmt->fetchColumn();
        $outstanding = round($invAmount - $alreadyPaid, 2);
        if ($outstanding <= 0 || $amount > $outstanding + 0.005) {
            $pdo->rollBack();
            return false;
        }

        $ins = $pdo->prepare("INSERT INTO payments (school_id, invoice_id, amount, payment_date, method, reference, received_by, notes) VALUES (?,?,?,?,?,?,?,?)");
        $ins->execute([$schoolId, $invoiceId, $amount, $date, $method, $reference, $receivedBy, $notes]);

        $paidStmt2 = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id = ? AND school_id = ?");
        $paidStmt2->execute([$invoiceId, $schoolId]);
        $totalPaid = (float)$paidStmt2->fetchColumn();
        if ($totalPaid >= $invAmount - 0.005) {
            $pdo->prepare("UPDATE invoices SET status='paid', paid_at=NOW(), payment_method=?, transaction_ref=? WHERE id=? AND school_id=?")
                ->execute([$method, $reference, $invoiceId, $schoolId]);
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('recordPayment failed: ' . $e->getMessage());
        return false;
    }
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

/**
 * Consume the current one-time flash message.
 * Kept as a named alias for the shared page header.
 */
function consumeFlashMessage(): ?array
{
    $message = getFlashMessage();
    return is_array($message) ? $message : null;
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

// Alias for logActivity
function recordActivity($pdo, $userId, $action, $entityType = null, $entityId = null, $description = null) {
    return logActivity($pdo, $userId, $action, $entityType, $entityId, $description);
}

// Password hashing helper
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

// Password verification helper
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Check login rate limit
//
// H5: previously this used `WHERE (email = ? OR ip_address = ?)`, which meant
// 5 bad logins for RANDOM emails from one IP would lock out the real account
// holder of that IP (e.g. everyone behind a NAT/router, the admin's own
// office). Rate-limit the two dimensions separately:
//   - per email: 5 failures / 15 min  (protects a specific account)
//   - per IP:   25 failures / 15 min (protects against spraying many accounts)
function checkLoginRateLimit($pdo, $email) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $emailStmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts
        WHERE email = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $emailStmt->execute([$email]);
    if ((int)$emailStmt->fetchColumn() >= 5) {
        return false;
    }

    $ipStmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts
        WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $ipStmt->execute([$ip]);
    if ((int)$ipStmt->fetchColumn() >= 25) {
        return false;
    }

    return true;
}

// Record login attempt
function recordLoginAttempt($pdo, $email) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $pdo->prepare("INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)");
    $stmt->execute([$email, $ip]);
}

// Clear login attempts on success (only this account's failures for this IP)
function clearLoginAttempts($pdo, $email) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE email = ? AND ip_address = ?");
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

// Generate a unique student number scoped to the school (uq_students_number_school
// is per-school, so global numbering would collide across tenants and leak size).
function generateStudentNumber($pdo, ?int $schoolId = null) {
    if ($schoolId === null && function_exists('currentSchoolId')) {
        $schoolId = (int)(currentSchoolId() ?? 0);
    }
    $schoolId = max(0, (int)$schoolId);
    $year = (int)date('Y');
    $prefix = 'STU-' . $year . '-';

    $next = 1;
    try {
        $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(student_number, '-', -1) AS UNSIGNED)) FROM students WHERE school_id = ? AND student_number LIKE ?");
        $stmt->execute([$schoolId, $prefix . '%']);
        $next = max(1, (int)$stmt->fetchColumn() + 1);
    } catch (Exception $e) {
        $next = random_int(100, 999);
    }

    // Collision-safe: a concurrent request or a deleted record can otherwise
    // produce a duplicate number and fail the whole INSERT.
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $candidate = $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
        $chk = $pdo->prepare("SELECT 1 FROM students WHERE school_id = ? AND student_number = ? LIMIT 1");
        $chk->execute([$schoolId, $candidate]);
        if (!$chk->fetchColumn()) {
            return $candidate;
        }
        $next++;
    }
    return $prefix . str_pad((string)($next + random_int(1, 999)), 4, '0', STR_PAD_LEFT);
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
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

// Get upcoming school events and holidays
// $schoolId null = platform-wide feed (super admin); otherwise tenant-scoped.
function getSchoolEventsList($pdo, $limit = 6, $onlyUpcoming = true, ?int $schoolId = null) {
    try {
        $where = $onlyUpcoming ? "event_date >= CURDATE() AND status != 'Cancelled'" : "1=1";
        $params = [];
        if ($schoolId !== null) {
            $where .= " AND e.school_id = ?";
            $params[] = (int)$schoolId;
        }
        $stmt = $pdo->prepare("SELECT e.*, u.full_name as author_name FROM school_events e LEFT JOIN users u ON e.created_by = u.id WHERE $where ORDER BY e.event_date ASC LIMIT ?");
        foreach ($params as $i => $p) $stmt->bindValue($i + 1, $p, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 1, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Enforce session validation on every request (C4: live session invalidation)
/**
 * Re-validate the authenticated session against the database on every request.
 *
 * Unlike a pure structural check, this re-reads:
 *   - users.is_active   (deactivated account -> immediate logout)
 *   - users.role        (role changed -> force re-login so perms match the UI)
 *   - users.school_id   (must be present for school-scoped roles)
 *   - students.id       (must exist and be scoped to the user's school)
 *   - schools.is_active / schools.status (suspended/disabled -> immediate logout)
 *
 * Results are cached for at most 60 seconds per session, keyed to a fingerprint
 * of user_id + role + school_id, so a normal page load doesn't hit the DB beyond
 * once a minute but a suspension/deactivation is enforced within 60s.
 *
 * Safe to call from AJAX/JSON endpoints: responds with 401 JSON instead of an
 * HTML redirect when the request is an XHR / expects JSON.
 */
function enforceSession(?PDO $pdo = null): void
{
    if (!isLoggedIn()) {
        return; // requireAuth handles the redirect for pages that need auth
    }

    // Resolve a PDO handle: prefer the caller's, else the bootstrap global.
    if (!$pdo) {
        global $pdo;
        $pdo = (isset($pdo) && $pdo instanceof PDO) ? $pdo : null;
    }

    $isAjax = (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest')
        || (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false)
        || (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false)
        || (strpos($_SERVER['REQUEST_URI'] ?? '', '/ajax/') !== false);

    $fail = static function (string $reason, bool $isAjax): void {
        // Clear the session and its cookie before responding.
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], !empty($params['secure']), !empty($params['httponly']));
        }
        @session_destroy();

        if ($isAjax) {
            http_response_code(401);
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'success' => false,
                'reason'  => $reason,
                'message' => 'Your session is no longer valid. Please sign in again.',
            ]);
            exit;
        }

        if (!headers_sent()) {
            header('Location: ' . getBasePath() . 'auth/login.php?' . http_build_query([$reason => 1]));
        }
        exit;
    };

    // 1. Structural session keys
    foreach (['user_id', 'user_role', 'user_name', 'user_email'] as $key) {
        if (!isset($_SESSION[$key])) {
            $fail('session_expired', $isAjax);
        }
    }

    // 2. Role must be one the codebase actually knows about
    $validRoles = ['super_admin', 'school_admin', 'admin', 'teacher', 'student', 'parent'];
    if (!in_array((string)$_SESSION['user_role'], $validRoles, true)) {
        $fail('invalid_role', $isAjax);
    }

    // 3. Without a DB handle, only the structural checks above can run
    //    (e.g. during the login POST before $pdo is available).
    if (!$pdo) {
        return;
    }

    // 4. Up to 60s of caching keyed to the session fingerprint.
    $now = time();
    $validatedAt = (int)($_SESSION['_validated_at'] ?? 0);
    $fingerprint = hash('sha256',
        (string)($_SESSION['user_id'] ?? '') . '|' .
        (string)($_SESSION['user_role'] ?? '') . '|' .
        (string)($_SESSION['school_id'] ?? '')
    );
    if ($validatedAt > 0 && ($now - $validatedAt) < 60 && ($_SESSION['_validated_fp'] ?? '') === $fingerprint) {
        return;
    }

    // 5. Re-read the user row and check account state
    $stmt = $pdo->prepare("SELECT id, role, is_active, school_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || (int)$user['is_active'] !== 1) {
        $fail('account_disabled', $isAjax);
    }
    if ((string)$user['role'] !== (string)$_SESSION['user_role']) {
        // Role was changed by an administrator: force a fresh login so the
        // server-side and UI permissions can never drift out of sync.
        $fail('role_changed', $isAjax);
    }

    // 6. School-scoped roles must have a live, non-suspended school
    $schoolScoped = in_array((string)$user['role'], ['school_admin', 'admin', 'teacher', 'student', 'parent'], true);
    if ($schoolScoped) {
        if (empty($user['school_id'])) {
            $fail('school_id_missing', $isAjax);
        }
        $sStmt = $pdo->prepare("SELECT id, status, is_active FROM schools WHERE id = ? LIMIT 1");
        $sStmt->execute([(int)$user['school_id']]);
        $school = $sStmt->fetch(PDO::FETCH_ASSOC);
        if (!$school || (int)$school['is_active'] !== 1) {
            $fail('school_inactive', $isAjax);
        }
        if (in_array(strtolower((string)$school['status']), ['suspended', 'disabled'], true)) {
            $fail('school_suspended', $isAjax);
        }
        $_SESSION['school_id'] = (int)$user['school_id'];
    } else {
        // super_admin is platform-level; never carry a school scope
        unset($_SESSION['school_id']);
    }

    // 7. Students must resolve to a student record inside their own school
    if ((string)$user['role'] === 'student') {
        $stStmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? AND school_id = ? LIMIT 1");
        $stStmt->execute([(int)$_SESSION['user_id'], (int)$user['school_id']]);
        $studentId = $stStmt->fetchColumn();
        if (!$studentId) {
            $fail('student_id_missing', $isAjax);
        }
        $_SESSION['student_id'] = (int)$studentId;
    }

    // 8. Cache the successful validation for up to 60 seconds
    $_SESSION['_validated_at'] = $now;
    $_SESSION['_validated_fp'] = hash('sha256',
        (string)$_SESSION['user_id'] . '|' .
        (string)$_SESSION['user_role'] . '|' .
        (string)($_SESSION['school_id'] ?? '')
    );
}

// Force a first-login password change when users.must_change_password is set.
// Called from includes/header.php on every internal page. Pages that must stay
// reachable during the change (profile, logout, lightweight AJAX) are allowed.
function enforcePasswordChange(): void
{
    if (!isLoggedIn() || empty($_SESSION['must_change_password'])) {
        return;
    }
    $here = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $allowed = ['profile.php', 'logout.php', 'notifications.php', 'save_push_subscription.php'];
    if (!in_array($here, $allowed, true)) {
        if (!headers_sent()) {
            header('Location: ' . getBasePath() . 'profile.php?must_change=1');
        }
        exit;
    }
}

// Password strength validation (H5: rate limiting + security)
function validatePasswordStrength(string $password): array
{
    $errors = [];
    if (strlen($password) < 10) {
        $errors[] = 'Password must be at least 10 characters';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }
    if (!preg_match('/\d/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must contain at least one special character';
    }
    return $errors;
}

// Generate secure invite token (C5: default passwords)
function generateInviteToken(): string
{
    return bin2hex(random_bytes(32));
}

/**
 * Generate a strong, random, one-time initial password for a newly provisioned
 * account. Guarantees at least one of each required character class.
 *
 * The caller MUST set users.must_change_password = 1 so the password must be
 * changed on first login. Never log or email this value.
 */
function generateTemporaryPassword(int $length = 16): string
{
    $upper   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower   = 'abcdefghijkmnopqrstuvwxyz';
    $digits  = '23456789';
    $symbols = '!@#$%^&*-_=+?';

    $length = max(12, $length);
    $classes = [$upper, $lower, $digits, $symbols];
    $chars = [];
    foreach ($classes as $class) {
        $chars[] = $class[random_int(0, strlen($class) - 1)];
    }
    $all = $upper . $lower . $digits . $symbols;
    for ($i = count($chars); $i < $length; $i++) {
        $chars[] = $all[random_int(0, strlen($all) - 1)];
    }
    for ($i = count($chars) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
    }
    return implode('', $chars);
}

function createUserInvite(PDO $pdo, int $schoolId, string $email, string $role, int $invitedBy): ?string
{
    $token = generateInviteToken();
    $hash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
    
    $stmt = $pdo->prepare("INSERT INTO user_invites (school_id, email, role, token_hash, expires_at, invited_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$schoolId, $email, $role, $hash, $expires, $invitedBy]);
    
    return $token;
}

function validateUserInvite(PDO $pdo, string $token): ?array
{
    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare("SELECT * FROM user_invites WHERE token_hash = ? AND used = 0 AND expires_at > NOW()");
    $stmt->execute([$hash]);
    return $stmt->fetch() ?: null;
}

function consumeUserInvite(PDO $pdo, string $token): bool
{
    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare("UPDATE user_invites SET used = 1 WHERE token_hash = ?");
    return $stmt->execute([$hash]);
}


