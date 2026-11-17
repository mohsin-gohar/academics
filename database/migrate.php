#!/usr/bin/env php
<?php
/**
 * Academics Canonical Migration Runner
 * ------------------------------------
 * Applies ordered, versioned migrations from database/migrations/
 * Tracks applied migrations in schema_migrations table.
 * Safe for empty databases and production upgrades.
 */

// Bootstrap minimal environment - skip demo accounts during migration
define('MIGRATION_RUNNING', true);
require_once __DIR__ . '/../config/db.php';

const MIGRATIONS_DIR = __DIR__ . '/migrations';
const SCHEMA_TABLE = 'schema_migrations';

function get_migration_files(): array
{
    $files = glob(MIGRATIONS_DIR . '/*.sql');
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);
    return $files;
}

function ensure_schema_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `" . SCHEMA_TABLE . "` (
            `version` VARCHAR(255) NOT NULL PRIMARY KEY,
            `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `checksum` CHAR(64) NOT NULL,
            `execution_time_ms` INT UNSIGNED NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

function get_applied_versions(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("SELECT `version` FROM `" . SCHEMA_TABLE . "` ORDER BY `applied_at`");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

function compute_checksum(string $sql): string
{
    return hash('sha256', $sql);
}

function apply_migration(PDO $pdo, string $version, string $sql): bool
{
    $checksum = compute_checksum($sql);
    $start = microtime(true);

    try {
        $pdo->beginTransaction();

        // Execute the migration SQL (split by semicolon, respecting strings)
        $statements = split_sql_statements($sql);
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt !== '') {
                $pdo->exec($stmt);
            }
        }

        // Record migration
        $ins = $pdo->prepare("INSERT INTO `" . SCHEMA_TABLE . "` (`version`, `checksum`, `execution_time_ms`) VALUES (?, ?, ?)");
        $ins->execute([$version, $checksum, (int)round((microtime(true) - $start) * 1000)]);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Migration $version failed: " . $e->getMessage());
        return false;
    }
}

function split_sql_statements(string $sql): array
{
    $statements = [];
    $current = '';
    $inString = false;
    $stringChar = '';
    $len = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $char = $sql[$i];
        $next = ($i + 1 < $len) ? $sql[$i + 1] : '';

        $current .= $char;

        if (!$inString && ($char === '"' || $char === "'" || $char === '`')) {
            $inString = true;
            $stringChar = $char;
        } elseif ($inString && $char === $stringChar && $next !== $stringChar) {
            $inString = false;
            $stringChar = '';
        } elseif ($inString && $char === '\\' && $next === $stringChar) {
            // Escaped quote inside string
            $current .= $next;
            $i++;
        } elseif (!$inString && $char === ';') {
            $statements[] = $current;
            $current = '';
        }
    }

    if (trim($current) !== '') {
        $statements[] = $current;
    }

    return $statements;
}

function run_migrations(bool $dryRun = false): void
{
    global $pdo;

    echo "====================================================================\n";
    echo "  ACADEMICS MIGRATION RUNNER\n";
    echo "  " . date('Y-m-d H:i:s') . "\n";
    echo "====================================================================\n\n";

    ensure_schema_table($pdo);
    $applied = get_applied_versions($pdo);
    $files = get_migration_files();

    if (empty($files)) {
        echo "No migration files found in " . MIGRATIONS_DIR . "\n";
        return;
    }

    $pending = [];
    foreach ($files as $file) {
        $version = basename($file, '.sql');
        if (!in_array($version, $applied, true)) {
            $pending[] = [$version, $file];
        }
    }

    if (empty($pending)) {
        echo "All migrations already applied.\n";
        echo "Applied versions:\n";
        foreach ($applied as $v) {
            echo "  - $v\n";
        }
        return;
    }

    echo "Pending migrations: " . count($pending) . "\n\n";

    foreach ($pending as [$version, $file]) {
        $sql = file_get_contents($file);
        if ($sql === false) {
            echo "[ERROR] Could not read $file\n";
            exit(1);
        }

        // Remove UTF-8 BOM if present
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);

        echo "[$version] ";
        if ($dryRun) {
            echo "DRY RUN - would apply\n";
            continue;
        }

        $ok = apply_migration($pdo, $version, $sql);
        if ($ok) {
            echo "OK\n";
        } else {
            echo "FAILED\n";
            exit(1);
        }
    }

    echo "\nAll migrations applied successfully.\n";

    // Show final state
    $final = get_applied_versions($pdo);
    echo "Total applied: " . count($final) . "\n";
}

function rollback_last(int $count = 1): void
{
    global $pdo;

    ensure_schema_table($pdo);
    $applied = get_applied_versions($pdo);

    if (empty($applied)) {
        echo "No migrations to rollback.\n";
        return;
    }

    $toRollback = array_slice(array_reverse($applied), 0, $count);
    echo "Rolling back " . count($toRollback) . " migration(s)...\n";

    // Note: This requires down-migrations which we don't have yet.
    // For safety, only allow rollback if explicit down files exist.
    echo "ERROR: Down-migrations not implemented. Manual rollback required.\n";
    exit(1);
}

// CLI entry point
$dryRun = in_array('--dry-run', $argv);
$command = $argv[1] ?? 'up';

switch ($command) {
    case 'up':
        run_migrations($dryRun);
        break;
    case 'down':
        $count = isset($argv[2]) && is_numeric($argv[2]) ? (int)$argv[2] : 1;
        rollback_last($count);
        break;
    case 'status':
        global $pdo;
        ensure_schema_table($pdo);
        $applied = get_applied_versions($pdo);
        $files = get_migration_files();
        echo "Migration Status:\n";
        foreach ($files as $file) {
            $version = basename($file, '.sql');
            $status = in_array($version, $applied, true) ? '[APPLIED]' : '[PENDING]';
            echo "  $status $version\n";
        }
        break;
    default:
        echo "Usage: php database/migrate.php [up|down|status] [--dry-run]\n";
        exit(1);
}