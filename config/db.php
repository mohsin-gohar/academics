<?php
/**
 * Database Configuration - PDO Connection
 * EduSphere Student Management System
 * Production Multi-Engine Self-Healing Database Layer
 */

// App configuration
if (!defined('APP_NAME')) {
    define('APP_NAME', 'EduSphere');
}
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.0');
}
if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', 'Asia/Karachi');
}
date_default_timezone_set(APP_TIMEZONE);

// Database configuration defaults
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_NAME', getenv('DB_NAME') ?: 'edusphere');
define('DB_CHARSET', 'utf8mb4');

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_TIMEOUT            => 2,
];

// Potential targets: XAMPP MariaDB (port 3306) and Oracle MySQL (port 3307)
$targets = [
    ['host' => '127.0.0.1', 'port' => 3306, 'pass' => 'root'],
    ['host' => '127.0.0.1', 'port' => 3306, 'pass' => ''],
    ['host' => '127.0.0.1', 'port' => 3307, 'pass' => ''],
    ['host' => '127.0.0.1', 'port' => 3307, 'pass' => 'root'],
    ['host' => 'localhost', 'port' => 3306, 'pass' => 'root'],
    ['host' => 'localhost', 'port' => 3306, 'pass' => ''],
    ['host' => 'localhost', 'port' => 3307, 'pass' => ''],
];

// If custom environment variables provided, prioritize them
if (getenv('DB_PORT') || getenv('DB_HOST') || getenv('DB_PASS') !== false) {
    array_unshift($targets, [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('DB_PORT') ?: 3306),
        'pass' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'root',
    ]);
}

$serverPdo = null;
$workingHost = '127.0.0.1';
$workingPort = 3306;
$workingPass = 'root';
$connectionErrors = [];

// Try connection to MySQL server
foreach ($targets as $target) {
    try {
        $serverPdo = new PDO(
            "mysql:host={$target['host']};port={$target['port']};charset=" . DB_CHARSET,
            DB_USER,
            $target['pass'],
            $options
        );
        $workingHost = $target['host'];
        $workingPort = $target['port'];
        $workingPass = $target['pass'];
        break;
    } catch (PDOException $e) {
        $connectionErrors[] = "{$target['host']}:{$target['port']} (pw:'{$target['pass']}'): " . $e->getMessage();
    }
}

// Self-healing: If MySQL is not running at all, attempt to start XAMPP mysqld on Windows
if (!$serverPdo && PHP_OS_FAMILY === 'Windows') {
    $mysqldPath = 'F:\\Xampp\\mysql\\bin\\mysqld.exe';
    $myIniPath = 'F:\\Xampp\\mysql\\bin\\my.ini';
    if (file_exists($mysqldPath)) {
        @pclose(@popen("start /B \"\" \"{$mysqldPath}\" --defaults-file=\"{$myIniPath}\"", "r"));
        usleep(1500000); // Wait 1.5s for initialization

        // Retry connection
        foreach ($targets as $target) {
            try {
                $serverPdo = new PDO(
                    "mysql:host={$target['host']};port={$target['port']};charset=" . DB_CHARSET,
                    DB_USER,
                    $target['pass'],
                    $options
                );
                $workingHost = $target['host'];
                $workingPort = $target['port'];
                $workingPass = $target['pass'];
                break;
            } catch (PDOException $e) {
                // Keep trying
            }
        }
    }
}

if (!$serverPdo) {
    error_log("MySQL connection failed across all candidate targets: " . implode(" | ", $connectionErrors));
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Database Service Unavailable — EduSphere</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Inter', sans-serif; background: #0F172A; color: #F8FAFC; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
            .db-card { background: #1E293B; border-radius: 20px; border: 1px solid #334155; padding: 40px; max-width: 650px; width: 90%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
            .btn-accent { background: #65A30D; color: white; font-weight: 600; padding: 12px 28px; border-radius: 12px; border: none; text-decoration: none; display: inline-block; }
            .btn-accent:hover { background: #4D7C0F; color: white; }
        </style>
    </head>
    <body>
        <div class="db-card text-center">
            <div style="font-size: 3.5rem; margin-bottom: 1rem;">🔌</div>
            <h2 class="fw-bold mb-2">MySQL Server is Not Running</h2>
            <p class="text-secondary mb-4">EduSphere cannot connect to MySQL on Port 3306 or Port 3307. Please ensure your XAMPP MySQL module is started.</p>
            <div class="p-3 rounded-3 text-start mb-4" style="background: #090D16; font-family: monospace; font-size: 0.82rem; color: #EF4444; overflow-x: auto;">
                <strong>Attempted Diagnostics:</strong><br>
                <?php foreach ($connectionErrors as $err): ?>
                    • <?= htmlspecialchars($err) ?><br>
                <?php endforeach; ?>
            </div>
            <a href="javascript:location.reload();" class="btn-accent">🔄 Reconnect to Database</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if (!defined('DB_HOST')) define('DB_HOST', $workingHost);
if (!defined('DB_PORT')) define('DB_PORT', $workingPort);
if (!defined('DB_PASS')) define('DB_PASS', $workingPass);

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
    error_log("Database bootstrap notice: " . $e->getMessage());
}

// Connect directly to the application database
$dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    die("Database connection failed. Please check your configuration.");
}

// Self-heal demo accounts: ensure they exist with documented passwords
try {
    $demoAccounts = [
        ['System Administrator', 'admin@edusphere.com', '$2y$10$mmx/UMwzNmkfhEKRGUYctuNSrl/xrRLp4f8o3R5M1ksN1c2J45tQu', 'admin'],
        ['Demo Teacher', 'teacher@edusphere.com', '$2y$10$QHDpb.tWT6fuT4va4VjzcO6SQfC1MTOl6kNAm4yjTytXQN3JJ/SXe', 'teacher'],
        ['Demo Student', 'student@edusphere.com', '$2y$10$k6JdjbAJbgtIGbf1I.fnE.wfB4TW1ditc8qgSGdj5VUIg9k/qlqzS', 'student'],
    ];
    foreach ($demoAccounts as $acc) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$acc[1]]);
        if (!$stmt->fetch()) {
            $ins = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, 'active')");
            $ins->execute([$acc[0], $acc[1], $acc[2], $acc[3]]);
        }
    }
} catch (PDOException $e) {
    error_log("Demo accounts self-heal notice: " . $e->getMessage());
}
