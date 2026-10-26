<?php
/**
 * Database Configuration - PDO Connection
 * EduSphere Student Management System
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'edusphere');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// App configuration
define('APP_NAME', 'EduSphere');
define('APP_VERSION', '1.0.0');
define('APP_TIMEZONE', 'Asia/Karachi');

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Connect to MySQL server (without selecting a database) so we can ensure the DB exists
try {
    $serverPdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        $options
    );
} catch (PDOException $e) {
    error_log("MySQL connection failed: " . $e->getMessage());
    http_response_code(500);
    die("Database connection failed. Please check your configuration.");
}

// Ensure the application database exists; create + import schema if missing or empty
try {
    $exists = $serverPdo->query(
        "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = " . $serverPdo->quote(DB_NAME)
    )->fetchColumn();

    $needImport = false;
    if (!$exists) {
        $needImport = true;
    } else {
        $tblExists = $serverPdo->query(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = " . $serverPdo->quote(DB_NAME) . " AND TABLE_NAME = 'users'"
        )->fetchColumn();
        if (!$tblExists) {
            $needImport = true;
        }
    }

    if ($needImport) {
        $sqlFile = __DIR__ . '/../database.sql';
        if (is_readable($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);
            $serverPdo->exec($sql);
        } else {
            $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
    }
} catch (PDOException $e) {
    error_log("Database bootstrap failed: " . $e->getMessage());
}

// Now connect to the application database
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    die("Database connection failed. Please check your configuration.");
}

// Self-heal demo accounts: ensure they exist with the documented passwords
try {
    $demoAccounts = [
        ['System Administrator', 'admin@edusphere.com', '$2y$10$mmx/UMwzNmkfhEKRGUYctuNSrl/xrRLp4f8o3R5M1ksN1c2J45tQu', 'admin'],
        ['Demo Teacher', 'teacher@edusphere.com', '$2y$10$QHDpb.tWT6fuT4va4VjzcO6SQfC1MTOl6kNAm4yjTytXQN3JJ/SXe', 'teacher'],
        ['Demo Student', 'student@edusphere.com', '$2y$10$k6JdjbAJbgtIGbf1I.fnE.wfB4TW1ditc8qgSGdj5VUIg9k/qlqzS', 'student'],
    ];
    foreach ($demoAccounts as $acc) {
        list($name, $email, $hash, $role) = $acc;
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $id = $stmt->fetchColumn();
        if ($id) {
            $upd = $pdo->prepare("UPDATE users SET password = ?, is_active = 1, role = ? WHERE id = ?");
            $upd->execute([$hash, $role, $id]);
        } else {
            $ins = $pdo->prepare("INSERT INTO users (full_name, email, password, role, is_active) VALUES (?, ?, ?, ?, 1)");
            $ins->execute([$name, $email, $hash, $role]);
        }
    }
} catch (PDOException $e) {
    error_log("Demo account self-heal failed: " . $e->getMessage());
}
