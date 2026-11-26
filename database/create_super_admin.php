<?php
/**
 * One-time/CLI tool to provision a platform super administrator.
 *
 * Run from the project root after database migrations:
 *   php database/create_super_admin.php
 *
 * This script intentionally refuses all web requests.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

function promptLine(string $label): string
{
    fwrite(STDOUT, $label);
    return trim((string)fgets(STDIN));
}

function promptSecret(string $label): string
{
    fwrite(STDOUT, $label);
    $canHide = DIRECTORY_SEPARATOR !== '\\' && function_exists('shell_exec');
    if ($canHide) {
        @shell_exec('stty -echo');
    }
    $value = trim((string)fgets(STDIN));
    if ($canHide) {
        @shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
    }
    return $value;
}

try {
    $name = promptLine('Super Admin full name: ');
    $email = strtolower(promptLine('Super Admin email: '));
    $password = promptSecret('Password (10+ chars, upper/lower/number/symbol): ');
    $confirm = promptSecret('Confirm password: ');

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        throw new InvalidArgumentException('Name must be between 2 and 100 characters.');
    }
    if (!isValidEmail($email)) {
        throw new InvalidArgumentException('Enter a valid email address.');
    }
    if (!hash_equals($password, $confirm)) {
        throw new InvalidArgumentException('Passwords do not match.');
    }
    $passwordErrors = validatePasswordStrength($password);
    if ($passwordErrors) {
        throw new InvalidArgumentException(implode(' ', $passwordErrors));
    }

    $duplicate = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $duplicate->execute([$email]);
    if ($duplicate->fetchColumn()) {
        throw new InvalidArgumentException('That email address is already registered.');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($hash === false) {
        throw new RuntimeException('Password hashing failed.');
    }

    $pdo->beginTransaction();
    $insert = $pdo->prepare(
        "INSERT INTO users
            (full_name, email, password, role, is_active, must_change_password, school_id)
         VALUES (?, ?, ?, 'super_admin', 1, 0, NULL)"
    );
    $insert->execute([$name, $email, $hash]);
    $newId = (int)$pdo->lastInsertId();
    $pdo->commit();

    fwrite(STDOUT, "Created platform super administrator (user ID {$newId}).\n");
    fwrite(STDOUT, "Keep the credentials private and remove any temporary terminal history if required.\n");
    exit(0);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Super Admin provisioning failed: " . $e->getMessage() . "\n");
    exit(1);
}
