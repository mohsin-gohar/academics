<?php
/**
 * Auth & Session Security Tests
 */
class AuthTest {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function run() {
        $results = [];

        // 1. Password Hashing & Verification
        $plain = 'Aptech@Secret2026!';
        $hash = password_hash($plain, PASSWORD_DEFAULT);
        $results[] = [
            'name' => 'Password hashing creates valid bcrypt hash',
            'pass' => password_verify($plain, $hash) && !password_verify('WrongPassword', $hash)
        ];

        // 2. Demo User Passwords in Database
        $stmt = $this->pdo->prepare("SELECT email, password FROM users WHERE email = ?");
        $stmt->execute(['admin@edusphere.com']);
        $admin = $stmt->fetch();
        $results[] = [
            'name' => 'Admin demo account authenticates with Admin@123',
            'pass' => $admin && password_verify('Admin@123', $admin['password'])
        ];

        $stmt->execute(['teacher@edusphere.com']);
        $teacher = $stmt->fetch();
        $results[] = [
            'name' => 'Teacher demo account authenticates with Teacher@123',
            'pass' => $teacher && password_verify('Teacher@123', $teacher['password'])
        ];

        $stmt->execute(['student@edusphere.com']);
        $student = $stmt->fetch();
        $results[] = [
            'name' => 'Student demo account authenticates with Student@123',
            'pass' => $student && password_verify('Student@123', $student['password'])
        ];

        // 3. Duplicate Email Rejection
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute(['admin@edusphere.com']);
        $results[] = [
            'name' => 'Existing email recognized for duplicate registration rejection',
            'pass' => ((int)$stmt->fetchColumn() >= 1)
        ];

        // 4. Rate Limiting Logic Check
        $testEmail = 'ratelimit_test_' . time() . '@test.com';
        $ip = '127.0.0.1';
        $ins = $this->pdo->prepare("INSERT INTO login_attempts (email, ip_address, attempted_at) VALUES (?, ?, NOW())");
        for ($i = 0; $i < 5; $i++) {
            $ins->execute([$testEmail, $ip]);
        }
        $allowed = checkLoginRateLimit($this->pdo, $testEmail);
        clearLoginAttempts($this->pdo, $testEmail);
        $results[] = [
            'name' => 'Login rate limit triggers after 5 failed attempts within window',
            'pass' => ($allowed === false)
        ];

        // 5. CSRF Token Generation & Validation
        $token = generateCsrfToken();
        $results[] = [
            'name' => 'CSRF token is generated with 64 hex characters (32 bytes)',
            'pass' => !empty($token) && strlen($token) === 64
        ];
        $results[] = [
            'name' => 'validateCsrfToken accepts matching token',
            'pass' => validateCsrfToken($token)
        ];
        $results[] = [
            'name' => 'validateCsrfToken rejects forged or tampered token',
            'pass' => !validateCsrfToken('forged_token_value_xyz123')
        ];

        return $results;
    }
}
