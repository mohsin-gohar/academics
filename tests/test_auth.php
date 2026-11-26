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
        $results[] = [
            'name' => 'A consumed password reset token cannot be reused',
            'pass' => consumePasswordResetToken($this->pdo, $resetToken, 'AnotherPass@123') === false
        ];

        $rateTable = $this->pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'password_reset_attempts'"
        )->fetchColumn();
        $results[] = [
            'name' => 'Account-independent password reset rate-limit table exists',
            'pass' => (int)$rateTable === 1
        ];

        // Re-run the idempotent retirement migration against a probe account
        // carrying a known legacy default hash.
        $defaultHash = '$2y$10$Ro246yHuqUPebBbJbl.9MeB9nETD76fg14wh2/VkT3XSxMY13eZn.';
        $probeEmail = 'migration-probe@academics.invalid';
        $this->pdo->prepare(
            "INSERT INTO users (full_name, email, password, role, is_active, must_change_password, school_id)
             VALUES ('Migration Probe', ?, ?, 'super_admin', 1, 1, NULL)"
        )->execute([$probeEmail, $defaultHash]);
        $migrationSql = file_get_contents(__DIR__ . '/../database/migrations/0011_disable_public_default_super_admin.sql');
        if ($migrationSql === false) throw new RuntimeException('Could not read default admin retirement migration.');
        $this->pdo->exec($migrationSql);
        $probe = $this->pdo->prepare("SELECT is_active, must_change_password, password FROM users WHERE email = ?");
        $probe->execute([$probeEmail]);
        $probeRow = $probe->fetch(PDO::FETCH_ASSOC);
        $results[] = [
            'name' => 'Migration disables known default super-admin password hashes',
            'pass' => $probeRow && (int)$probeRow['is_active'] === 0
                && (int)$probeRow['must_change_password'] === 1
                && !hash_equals($defaultHash, (string)$probeRow['password'])
        ];
        $this->pdo->prepare("DELETE FROM users WHERE email = ?")->execute([$probeEmail]);

        // Restore admin password
        $this->pdo->prepare("UPDATE users SET password = ? WHERE email = ?")->execute([
            hashPassword('Admin@123'),
            $resetEmail
        ]);

        return $results;
    }
}
