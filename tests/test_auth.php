<?php
/**
 * Test Auth Module (test_auth.php)
 * EduSphere Enterprise School Management System
 */
class TestAuthSuite {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function run(): array {
        $results = [];

        // 1. Password hashing
        $pwd = 'SecurePassword@123';
        $hashed = hashPassword($pwd);
        $results[] = [
            'name' => 'hashPassword creates valid bcrypt signature',
            'pass' => password_verify($pwd, $hashed) && !password_verify('WrongPwd', $hashed)
        ];

        // 2. verifyPassword helper
        $results[] = [
            'name' => 'verifyPassword helper returns true for valid password',
            'pass' => verifyPassword($pwd, $hashed)
        ];

        $results[] = [
            'name' => 'verifyPassword helper returns false for invalid password',
            'pass' => !verifyPassword('InvalidPassword', $hashed)
        ];

        // 3. CSRF token generation
        $csrf = generateCsrfToken();
        $results[] = [
            'name' => 'generateCsrfToken produces 64-char hexadecimal string',
            'pass' => is_string($csrf) && strlen($csrf) === 64 && ctype_xdigit($csrf)
        ];

        // 4. CSRF token validation
        $results[] = [
            'name' => 'validateCsrfToken validates session token correctly',
            'pass' => validateCsrfToken($csrf)
        ];

        $results[] = [
            'name' => 'validateCsrfToken rejects empty or invalid token',
            'pass' => !validateCsrfToken('') && !validateCsrfToken('forged_token')
        ];

        // 5. Rate limit tracking (checkLoginRateLimit returns false when limit exceeded)
        $testEmail = 'rate_auth_test_' . time() . '@test.com';
        clearLoginAttempts($this->pdo, $testEmail);
        for ($i = 0; $i < 5; $i++) {
            recordLoginAttempt($this->pdo, $testEmail);
        }
        $results[] = [
            'name' => 'checkLoginRateLimit triggers after 5 failed attempts',
            'pass' => checkLoginRateLimit($this->pdo, $testEmail) === false
        ];

        // 6. Rate limit clearing
        clearLoginAttempts($this->pdo, $testEmail);
        $results[] = [
            'name' => 'clearLoginAttempts resets rate limit counter',
            'pass' => checkLoginRateLimit($this->pdo, $testEmail) === true
        ];

        // 7. Email validation
        $results[] = [
            'name' => 'isValidEmail accepts valid formats and rejects invalid formats',
            'pass' => isValidEmail('user@school.edu') && !isValidEmail('invalid-email') && !isValidEmail('@missinguser.com')
        ];

        // 8. Session and one-time flash-message helpers
        $results[] = [
            'name' => 'getRole returns valid role string',
            'pass' => is_string(getRole())
        ];
        setFlashMessage('success', 'Flash helper test');
        $flash = consumeFlashMessage();
        $results[] = [
            'name' => 'consumeFlashMessage returns the one-time session message',
            'pass' => is_array($flash) && ($flash['type'] ?? '') === 'success' && ($flash['text'] ?? '') === 'Flash helper test'
        ];
        $results[] = [
            'name' => 'consumeFlashMessage clears the message after first read',
            'pass' => consumeFlashMessage() === null
        ];

        // 9. Password reset token creation and consumption
        $resetEmail = 'admin@edusphere.com';
        $resetToken = createPasswordResetToken($this->pdo, $resetEmail);
        $results[] = [
            'name' => 'createPasswordResetToken generates valid reset token',
            'pass' => !empty($resetToken)
        ];

        $validatedEmail = validatePasswordResetToken($this->pdo, $resetToken);
        $results[] = [
            'name' => 'validatePasswordResetToken returns matching email address',
            'pass' => ($validatedEmail === $resetEmail)
        ];

        $consumed = consumePasswordResetToken($this->pdo, $resetToken, 'NewPass@123');
        $results[] = [
            'name' => 'consumePasswordResetToken updates user password successfully',
            'pass' => $consumed === true
        ];

        // Restore admin password
        $this->pdo->prepare("UPDATE users SET password = ? WHERE email = ?")->execute([
            hashPassword('Admin@123'),
            $resetEmail
        ]);

        return $results;
    }
}
