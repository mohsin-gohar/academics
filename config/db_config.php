<?php
/**
 * Academics Database & Hosting Configuration
 *
 * Credentials must come from the .env file or server environment variables
 * (see config/db.php). No database secrets belong in version control.
 */

$appEnv = strtolower(trim((string)(getenv('APP_ENV') ?: 'local')));

if (!defined('IS_PRODUCTION')) {
    define('IS_PRODUCTION', in_array($appEnv, ['production', 'prod', 'live'], true));
}

if (IS_PRODUCTION) {
    // Production must never silently fall back to development credentials.
    $required = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
    $missing = [];
    foreach ($required as $key) {
        $value = getenv($key);
        if ($value === false || trim((string)$value) === '') {
            $missing[] = $key;
        }
    }
    if ($missing) {
        error_log('Academics production database configuration missing: ' . implode(', ', $missing));
        http_response_code(503);
        exit('Service temporarily unavailable. Database configuration is incomplete.');
    }

    if (!defined('DB_HOST')) {
        define('DB_HOST', getenv('DB_HOST') !== false ? trim((string)getenv('DB_HOST')) : 'localhost');
    }
    if (!defined('DB_PORT')) {
        define('DB_PORT', (int)(getenv('DB_PORT') !== false ? getenv('DB_PORT') : 3306));
    }
    if (!defined('DB_NAME')) {
        define('DB_NAME', getenv('DB_NAME') !== false ? trim((string)getenv('DB_NAME')) : 'edusphere');
    }
    if (!defined('DB_USER')) {
        define('DB_USER', getenv('DB_USER') !== false ? trim((string)getenv('DB_USER')) : 'root');
    }
    if (!defined('DB_PASS')) {
        define('DB_PASS', getenv('DB_PASS') !== false ? (string)getenv('DB_PASS') : '');
    }
}
