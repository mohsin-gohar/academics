#!/usr/bin/env php
<?php
/**
 * Academics Canonical Migration Runner
 * ------------------------------------
 * Applies ordered, versioned migrations from database/migrations/
 * Tracks applied migrations in schema_migrations table.
 * Safe for empty databases and production upgrades.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

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

/**
 * Prepare the legacy single-school users table before migration 0001 seeds
 * tenant-aware accounts. CREATE TABLE IF NOT EXISTS does not add columns to
 * an existing schema.sql installation, so this preflight is required before
 * the first migration executes.
 */
function prepare_legacy_users_schema(PDO $pdo): void
{
    $table = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'"
    );
    $table->execute();
    if ((int)$table->fetchColumn() === 0) {
        return;
    }

    $columns = $pdo->prepare(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'"
    );
    $columns->execute();
    $existing = array_fill_keys($columns->fetchAll(PDO::FETCH_COLUMN), true);

    if (!isset($existing['school_id'])) {
        $pdo->exec("ALTER TABLE users ADD COLUMN school_id INT NULL AFTER id");
    }
    if (!isset($existing['must_change_password'])) {
        $pdo->exec("ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0");
    }

    $roleQuery = $pdo->prepare(
        "SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_SET_NAME, COLLATION_NAME
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'
         LIMIT 1"
    );
    $roleQuery->execute();
    $role = $roleQuery->fetch(PDO::FETCH_ASSOC);
    if (!$role || stripos((string)$role['COLUMN_TYPE'], 'enum(') !== 0) {
        return;
    }

    preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", (string)$role['COLUMN_TYPE'], $matches);
    $labels = $matches[1] ?? [];
    $required = ['super_admin', 'school_admin', 'parent'];
    $missing = array_values(array_diff($required, $labels));
    if (!$missing) {
        return;
    }

    $enumType = substr((string)$role['COLUMN_TYPE'], 0, -1);
    foreach ($missing as $label) {
        $enumType .= ',' . $pdo->quote($label);
    }
    $enumType .= ')';
    $nullSql = ($role['IS_NULLABLE'] === 'YES') ? ' NULL' : ' NOT NULL';
    $defaultSql = $role['COLUMN_DEFAULT'] === null
        ? ''
        : ' DEFAULT ' . $pdo->quote((string)$role['COLUMN_DEFAULT']);
    $charsetSql = '';
    if (preg_match('/^[A-Za-z0-9_]+$/', (string)$role['CHARACTER_SET_NAME'])
        && preg_match('/^[A-Za-z0-9_]+$/', (string)$role['COLLATION_NAME'])) {
        $charsetSql = ' CHARACTER SET ' . $role['CHARACTER_SET_NAME']
            . ' COLLATE ' . $role['COLLATION_NAME'];
    }

    $pdo->exec(
        "ALTER TABLE users MODIFY COLUMN role {$enumType}{$charsetSql}{$nullSql}{$defaultSql}"
    );
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

function get_applied_migration_checksums(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("SELECT version, checksum FROM `" . SCHEMA_TABLE . "`");
        $records = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $records[(string)$row['version']] = (string)$row['checksum'];
        }
        return $records;
    } catch (PDOException $e) {
        return [];
    }
}

function apply_migration(PDO $pdo, string $version, string $sql): bool
{
    $checksum = compute_checksum($sql);
    $start = microtime(true);

    // IMPORTANT: MySQL/MariaDB implicitly commit on DDL (CREATE/ALTER/DROP TABLE),
    // so wrapping migrations in a PDO transaction is invalid — the final commit()
    // throws "There is no active transaction". Run each migration file in autocommit
    // mode as its own unit and record success only if every statement succeeded.
    try {
        $statements = split_sql_statements($sql);
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt !== '') {
                // Use query() + fetchAll() so statements that return result sets
                // (e.g. SET @x = (SELECT ...)) are drained instead of leaving an
                // active unbuffered query that breaks subsequent exec() calls.
                $res = $pdo->query($stmt);
                if ($res instanceof PDOStatement) {
                    $res->fetchAll();
                    $res->closeCursor();
                }
            }
        }

        // Record migration only after all statements succeeded.
        $ins = $pdo->prepare("INSERT INTO `" . SCHEMA_TABLE . "` (`version`, `checksum`, `execution_time_ms`) VALUES (?, ?, ?)");
        $ins->execute([$version, $checksum, (int)round((microtime(true) - $start) * 1000)]);
        return true;
    } catch (Throwable $e) {
        error_log("Migration $version failed: " . $e->getMessage());
        return false;
    }
}

function split_sql_statements(string $sql): array
{
    $statements = [];
    $current = '';
    $hasCode = false;
    $inString = false;
    $stringChar = '';
    $lineComment = false;
    $blockComment = false;
    $len = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $char = $sql[$i];
        $next = ($i + 1 < $len) ? $sql[$i + 1] : '';
        $afterNext = ($i + 2 < $len) ? $sql[$i + 2] : '';

        if ($lineComment) {
            $current .= $char;
            if ($char === "\n" || $char === "\r") $lineComment = false;
            continue;
        }

        if ($blockComment) {
            $current .= $char;
            if ($char === '*' && $next === '/') {
                $current .= $next;
                $i++;
                $blockComment = false;
            }
            continue;
        }

        if ($inString) {
            $current .= $char;
            if ($char === $stringChar && $next === $stringChar) {
                $current .= $next;
                $i++;
            } elseif ($char === $stringChar) {
                $inString = false;
                $stringChar = '';
            } elseif ($char === '\\' && $next === $stringChar) {
                $current .= $next;
                $i++;
            }
            continue;
        }

        // MySQL line comments: -- must be followed by whitespace/control.
        if ($char === '-' && $next === '-' && ($afterNext === '' || ctype_space($afterNext))) {
            $current .= '--';
            $i++;
            $lineComment = true;
            continue;
        }
        if ($char === '#') {
            $current .= $char;
            $lineComment = true;
            continue;
        }
        if ($char === '/' && $next === '*') {
            $current .= '/*';
            $i++;
            $blockComment = true;
            continue;
        }

        if ($char === '"' || $char === "'" || $char === '`') {
            $inString = true;
            $stringChar = $char;
            $hasCode = true;
            $current .= $char;
            continue;
        }

        if ($char === ';') {
            if ($hasCode) $statements[] = $current;
            $current = '';
            $hasCode = false;
            continue;
        }

        if (!ctype_space($char)) $hasCode = true;
        $current .= $char;
    }

    // Ignore trailing comment-only text; PDO rejects an empty query.
    if ($hasCode) $statements[] = $current;

    return $statements;
}

function run_migrations(bool $dryRun = false): void
{
    global $pdo;

    echo "====================================================================\n";
    echo "  ACADEMICS MIGRATION RUNNER\n";
    echo "  " . date('Y-m-d H:i:s') . "\n";
    echo "====================================================================\n\n";

    if (!$dryRun) {
        prepare_legacy_users_schema($pdo);
    }
    ensure_schema_table($pdo);
    $appliedChecksums = get_applied_migration_checksums($pdo);
    $applied = array_keys($appliedChecksums);
    $files = get_migration_files();

    if (empty($files)) {
        echo "No migration files found in " . MIGRATIONS_DIR . "\n";
        return;
    }

    $pending = [];
    foreach ($files as $file) {
        $version = basename($file, '.sql');
        if (isset($appliedChecksums[$version])) {
            $existingSql = file_get_contents($file);
            if ($existingSql === false) {
                echo "[ERROR] Could not read $file\n";
                exit(1);
            }
            $existingSql = preg_replace('/^\\xEF\\xBB\\xBF/', '', $existingSql);
            $currentChecksum = compute_checksum($existingSql);
            if (!hash_equals($appliedChecksums[$version], $currentChecksum)) {
                // Never re-run an edited migration against an existing database.
                // Warn and continue so new forward-only migrations can still apply.
                echo "[CHECKSUM WARNING] $version differs from the version already applied. It will be skipped; add a new migration for follow-up changes.\n";
            }
            continue;
        }
        $pending[] = [$version, $file];
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
    case 'verify':
        global $pdo;
        ensure_schema_table($pdo);
        $appliedChecksums = get_applied_migration_checksums($pdo);
        $pending = [];
        $drifted = [];
        foreach (get_migration_files() as $file) {
            $version = basename($file, '.sql');
            if (!isset($appliedChecksums[$version])) {
                $pending[] = $version;
                continue;
            }
            $sql = file_get_contents($file);
            $sql = $sql === false ? '' : preg_replace('/^\xEF\xBB\xBF/', '', $sql);
            if (!hash_equals($appliedChecksums[$version], compute_checksum($sql))) {
                $drifted[] = $version;
            }
        }
        if ($pending) {
            echo "PENDING: " . implode(', ', $pending) . "\n";
        }
        if ($drifted) {
            echo "DRIFTED: " . implode(', ', $drifted) . "\n";
        }
        if ($pending || $drifted) {
            exit(1);
        }
        echo "All migrations applied and checksums verified.\n";
        break;
    case 'down':
        $count = isset($argv[2]) && is_numeric($argv[2]) ? (int)$argv[2] : 1;
        rollback_last($count);
        break;
    case 'status':
        global $pdo;
        ensure_schema_table($pdo);
        $appliedChecksums = get_applied_migration_checksums($pdo);
        $files = get_migration_files();
        echo "Migration Status:\n";
        foreach ($files as $file) {
            $version = basename($file, '.sql');
            if (!isset($appliedChecksums[$version])) {
                $status = '[PENDING]';
            } else {
                $sql = file_get_contents($file);
                $sql = $sql === false ? '' : preg_replace('/^\\xEF\\xBB\\xBF/', '', $sql);
                $status = hash_equals($appliedChecksums[$version], compute_checksum($sql))
                    ? '[APPLIED]'
                    : '[CHECKSUM MISMATCH]';
            }
            echo "  $status $version\n";
        }
        break;
    default:
        echo "Usage: php database/migrate.php [up|down|status|verify] [--dry-run]\n";
        exit(1);
}