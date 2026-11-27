<?php
/**
 * Database Configuration - PDO Connection
 * Academics School Management Platform
 * Production Multi-Engine Self-Healing Database Layer
 */

// Minimal .env loader (no external dependency)
function load_env(?string $path = null): void
{
    // __DIR__ is config/, so the project-root .env is one level up.
    $path = $path ?: __DIR__ . '/../.env';
    if (!is_file($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"' ");
        if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
load_env();

// App configuration
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Academics');
}
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.0');
}
if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', 'Asia/Karachi');
}
if (!defined('APP_DOMAIN')) {
    $appDomainEnv = getenv('APP_DOMAIN');
    define('APP_DOMAIN', $appDomainEnv !== false ? trim((string)$appDomainEnv) : '');
}
if (!defined('APP_SUPPORT_EMAIL')) {
    $appSupportEnv = getenv('APP_SUPPORT_EMAIL');
    define('APP_SUPPORT_EMAIL', $appSupportEnv !== false && trim((string)$appSupportEnv) !== ''
        ? trim((string)$appSupportEnv)
        : 'support@academics.app');
}
date_default_timezone_set(APP_TIMEZONE);

// Environment detection
if (!defined('APP_ENV')) {
    $appEnv = getenv('APP_ENV');
    define('APP_ENV', $appEnv !== false && trim((string)$appEnv) !== '' ? strtolower(trim((string)$appEnv)) : 'local');
}

if (!defined('APP_DEBUG')) {
    $debugEnv = getenv('APP_DEBUG');
    $debugDefault = in_array(APP_ENV, ['local', 'development'], true);
    define('APP_DEBUG', $debugEnv !== false
        ? filter_var($debugEnv, FILTER_VALIDATE_BOOLEAN)
        : $debugDefault);
}
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', APP_DEBUG ? '1' : '0');
$requestHost = strtolower((string)parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), PHP_URL_HOST));
$isLocalDev = (APP_ENV === 'local' || APP_ENV === 'development')
    && (PHP_SAPI === 'cli' || in_array($requestHost, ['localhost', '127.0.0.1', '::1'], true));
$isTestEnv = (APP_ENV === 'test' || APP_ENV === 'testing');

// Load external hosting configuration if available
if (file_exists(__DIR__ . '/db_config.php')) {
    require_once __DIR__ . '/db_config.php';
}

if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Database name selection: test environment gets isolated database
if (!defined('DB_NAME')) {
    $dbNameEnv = getenv('DB_NAME');
    if ($isTestEnv && getenv('DB_NAME_TEST')) {
        define('DB_NAME', getenv('DB_NAME_TEST'));
    } elseif ($dbNameEnv !== false) {
        define('DB_NAME', $dbNameEnv);
    } else {
        define('DB_NAME', 'edusphere');
    }
}

if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');

// Production / InfinityFree Direct Connection
if (defined('IS_PRODUCTION') && IS_PRODUCTION) {
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5,
    ];

    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        error_log("InfinityFree DB Connection Error: " . $e->getMessage());
        http_response_code(503);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Service Unavailable — Academics</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { background: #0F172A; color: #F8FAFC; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: sans-serif; }
                .card-box { background: #1E293B; border-radius: 16px; padding: 32px; max-width: 600px; border: 1px solid #334155; }
            </style>
        </head>
        <body>
            <div class="card-box text-center">
                <h3 class="fw-bold mb-3">Service Unavailable</h3>
                <p class="text-secondary small mb-3">Academics cannot connect to its database right now. Please try again in a moment.</p>
                <p class="text-muted small mb-0">If you are the site administrator, check the database credentials and confirm migrations have been applied.</p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    return;
}

// Database configuration defaults (Localhost)
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    // Short connect timeout: a wrong port must not stall every page load.
    PDO::ATTR_TIMEOUT            => 1,
];

// Candidate endpoints. Guessing local passwords is a developer convenience and
// must never run against a real deployment: a hosted install gets exactly the
// one endpoint it is configured with, so a wrong password fails loudly.
$targets = [];
if (defined('IS_PRODUCTION') && IS_PRODUCTION) {
    $targets[] = [
        'host' => defined('DB_HOST') ? DB_HOST : '127.0.0.1',
        'port' => defined('DB_PORT') ? (int)DB_PORT : 3306,
        'pass' => defined('DB_PASS') ? (string)DB_PASS : '',
    ];
} elseif ($isLocalDev || $isTestEnv) {
    $targets = [
        ['host' => '127.0.0.1', 'port' => 3306, 'pass' => 'root'],
        ['host' => '127.0.0.1', 'port' => 3306, 'pass' => ''],
        ['host' => '127.0.0.1', 'port' => 3307, 'pass' => ''],
        ['host' => '127.0.0.1', 'port' => 3307, 'pass' => 'root'],
        ['host' => 'localhost', 'port' => 3306, 'pass' => 'root'],
        ['host' => 'localhost', 'port' => 3306, 'pass' => ''],
        ['host' => 'localhost', 'port' => 3307, 'pass' => ''],
    ];
} else {
    $targets[] = [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('DB_PORT') ?: 3306),
        'pass' => getenv('DB_PASS') !== false ? (string)getenv('DB_PASS') : '',
    ];
}

// If custom environment variables provided, prioritize them
if (getenv('DB_PORT') || getenv('DB_HOST') || getenv('DB_PASS') !== false) {
    array_unshift($targets, [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('DB_PORT') ?: 3306),
        'pass' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
    ]);
}

$serverPdo = null;
$workingHost = '127.0.0.1';
$workingPort = 3306;
$workingPass = 'root';
$connectionErrors = [];

// Remember the working local target across requests. Without this, an install
// whose MySQL listens on a non-default port (e.g. XAMPP on 3307) pays the
// connect timeout for every dead candidate on every single page load.
$dbTargetCache = dirname(__DIR__) . '/storage/cache/db_target.json';
if ($isLocalDev && !$isTestEnv && is_file($dbTargetCache)) {
    $cachedTarget = json_decode((string)@file_get_contents($dbTargetCache), true);
    if (is_array($cachedTarget) && !empty($cachedTarget['host']) && !empty($cachedTarget['port'])) {
        array_unshift($targets, [
            'host' => (string)$cachedTarget['host'],
            'port' => (int)$cachedTarget['port'],
            // The password is never cached; the configured one is used instead.
            'pass' => defined('DB_PASS') ? (string)DB_PASS : (getenv('DB_PASS') !== false ? (string)getenv('DB_PASS') : ''),
        ]);
    }
}

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
        $connectionErrors[] = "{$target['host']}:{$target['port']}: " . $e->getMessage();
    }
}

// Self-healing (local CLI only): if MySQL is not running, try to start XAMPP mysqld.
// Never do this from a web request: starting processes from HTTP is unsafe.
if (!$serverPdo && $isLocalDev && PHP_SAPI === 'cli' && PHP_OS_FAMILY === 'Windows') {
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

if ($serverPdo && $isLocalDev && !$isTestEnv) {
    // Persist the working local endpoint so the next request connects first-try.
    @file_put_contents(
        $dbTargetCache,
        json_encode(['host' => $workingHost, 'port' => $workingPort], JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

if (!$serverPdo) {
    error_log("MySQL connection failed across all candidate targets: " . implode(" | ", $connectionErrors));
    http_response_code(503);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Service Unavailable - Academics</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { font-family: system-ui, sans-serif; background: #0F172A; color: #F8FAFC; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
            .db-card { background: #1E293B; border-radius: 20px; border: 1px solid #334155; padding: 40px; max-width: 560px; width: 90%; }
        </style>
    </head>
    <body>
        <div class="db-card text-center">
            <h2 class="fw-bold mb-2">Database service unavailable</h2>
            <p class="text-secondary mb-4">Academics cannot connect to its database right now. Please try again in a moment.</p>
            <a href="javascript:location.reload();" class="btn btn-primary">Retry</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if (!defined('DB_HOST')) define('DB_HOST', $workingHost);
if (!defined('DB_PORT')) define('DB_PORT', $workingPort);
if (!defined('DB_PASS')) define('DB_PASS', $workingPass);

// Ensure the application database exists; create if missing (do NOT auto-import schema in production)
try {
    $exists = $serverPdo->query(
        "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = " . $serverPdo->quote(DB_NAME)
    )->fetchColumn();

    if (!$exists && ($isLocalDev || $isTestEnv || getenv('DB_ALLOW_CREATE') === '1')) {
        $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        error_log("Database '$DB_NAME' created. Run 'php database/migrate.php' to apply schema.");
    } elseif (!$exists) {
        error_log("Database '$DB_NAME' does not exist and automatic creation is disabled. Create it and run 'php database/migrate.php'.");
        http_response_code(503);
        exit('Database is not configured for this installation.');
    } else {
        // Check if schema_migrations table exists (canonical migrations applied)
        $tblExists = $serverPdo->query(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = " . $serverPdo->quote(DB_NAME) . " AND TABLE_NAME = 'schema_migrations'"
        )->fetchColumn();
        if (!$tblExists && !$isTestEnv && !$isLocalDev) {
            error_log("Database '$DB_NAME' exists but canonical migrations not applied. Run 'php database/migrate.php'.");
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
    http_response_code(503);
    die("Database connection failed. Please check your configuration and ensure migrations have been applied.");
}

// Known demo passwords are not inserted automatically.
// Opt in only on a private localhost lab: ALLOW_DEMO_ACCOUNTS=1
if ($isLocalDev && !defined('MIGRATION_RUNNING') && getenv('ALLOW_DEMO_ACCOUNTS') === '1') {
    try {
        $demoAccounts = [
            ['System Administrator', 'admin@edusphere.com', '$2y$10$mmx/UMwzNmkfhEKRGUYctuNSrl/xrRLp4f8o3R5M1ksN1c2J45tQu', 'super_admin'],
            ['Demo Teacher', 'teacher@edusphere.com', '$2y$10$QHDpb.tWT6fuT4va4VjzcO6SQfC1MTOl6kNAm4yjTytXQN3JJ/SXe', 'teacher'],
            ['Demo Student', 'student@edusphere.com', '$2y$10$k6JdjbAJbgtIGbf1I.fnE.wfB4TW1ditc8qgSGdj5VUIg9k/qlqzS', 'student'],
        ];
        foreach ($demoAccounts as $acc) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$acc[1]]);
            if (!$stmt->fetch()) {
                $ins = $pdo->prepare("INSERT INTO users (full_name, email, password, role, is_active) VALUES (?, ?, ?, ?, 1)");
                $ins->execute([$acc[0], $acc[1], $acc[2], $acc[3]]);
            }
        }
    } catch (PDOException $e) {
        error_log("Demo accounts self-heal notice: " . $e->getMessage());
    }
}