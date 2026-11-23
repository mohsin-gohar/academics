<?php
/**
 * Academics Database & Hosting Configuration
 * 
 * This file allows seamless switching between Localhost (XAMPP)
 * and Production Hosting (InfinityFree, cPanel, ByetHost, etc.).
 */

// Auto-detect environment: InfinityFree hostnames usually contain infinityfree, epizy, rf.gd, etc.
$httpHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
$isInfinityFree = false;
$ifDomains = ['infinityfreeapp.com', 'epizy.com', 'rf.gd', 'great-site.net', 'freecluster.eu', 'infinityfree.me'];
foreach ($ifDomains as $d) {
    if (stripos($httpHost, $d) !== false) {
        $isInfinityFree = true;
        break;
    }
}

// Set this to true manually if you want to FORCE production credentials:
// NEVER set FORCE_PRODUCTION=true in this file for local development.
// Use APP_ENV=production via .env or server environment instead.
$forceProduction = false; // Do not hardcode - use APP_ENV environment variable

if ($isInfinityFree || $forceProduction) {
    /**
     * ========================================================================
     * INFINITYFREE / PRODUCTION DATABASE CREDENTIALS
     * ========================================================================
     * In your InfinityFree Control Panel (vPanel) / Client Area, find:
     * 1. MySQL Hostname (e.g. sql105.infinityfree.com or sql200.epizy.com)
     * 2. MySQL Database Name (e.g. if0_12345678_edusphere)
     * 3. MySQL Username (e.g. if0_12345678)
     * 4. MySQL Password (Your InfinityFree account / vPanel password)
     */
    if (!defined('DB_HOST')) define('DB_HOST', 'sqlxxx.infinityfree.com'); // <-- Replace with your MySQL Hostname
    if (!defined('DB_NAME')) define('DB_NAME', 'if0_xxxxxxxx_edusphere');  // <-- Replace with your MySQL DB Name
    if (!defined('DB_USER')) define('DB_USER', 'if0_xxxxxxxx');            // <-- Replace with your MySQL Username
    if (!defined('DB_PASS')) define('DB_PASS', 'YOUR_INFINITYFREE_PASS');  // <-- Replace with your MySQL Password
    /**
     * SECURITY NOTICE:
     * - Never commit real DB_PASS values to version control
     * - Set actual credentials via .env file or server environment variables
     * - For production (INFINITYFREE): Use your vPanel MySQL credentials
     * - For local development: Export DB_USER, DB_PASS, DB_HOST, DB_NAME in your shell
     * - Rotate passwords regularly and never reuse credentials
     */
    if (!defined('DB_PORT')) define('DB_PORT', 3306);
    if (!defined('IS_PRODUCTION')) define('IS_PRODUCTION', true);
} else {
    /**
     * ========================================================================
     * LOCALHOST (XAMPP / LOCAL MYSQL)
     * ========================================================================
     * Leave untouched for automatic local database detection
     */
    if (!defined('IS_PRODUCTION')) define('IS_PRODUCTION', false);
}
