<?php
/**
 * Academics SaaS Migration
 * ------------------------
 * Idempotent upgrade from the existing multi-school schema to the SaaS layer:
 *   - schools.subdomain / status / plan / trial_ends_at / owner_name
 *   - school_subscriptions table + seed records
 *   - contact_messages table
 *
 * Usage: php database/migrate_saas.php
 *
 * Safe to run multiple times. It never drops or rewrites existing rows.
 */

if (PHP_SAPI !== 'cli') {
    die('This migration must be run from the command line.');
}

require_once __DIR__ . '/../config/db.php';

function saas_q(PDO $pdo, string $sql, array $params = []): PDOStatement
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st;
}

function saas_table_exists(PDO $pdo, string $table): bool
{
    return (int)saas_q($pdo, "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?", [$table])->fetchColumn() > 0;
}

function saas_column_exists(PDO $pdo, string $table, string $column): bool
{
    return (int)saas_q($pdo, "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?", [$table, $column])->fetchColumn() > 0;
}

function saas_index_exists(PDO $pdo, string $table, string $index): bool
{
    return (int)saas_q($pdo, "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?", [$table, $index])->fetchColumn() > 0;
}

function saas_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim((string)$value, '-');
    $value = preg_replace('/-+/', '-', $value);
    return substr((string)$value, 0, 40);
}

echo "=== Academics SaaS Migration ===\n";

if (!saas_table_exists($pdo, 'schools')) {
    die("[error] schools table is missing. Run database/migrate_multi_school.php first.\n");
}

/* ------------------------------------------------------------------
 * 1. Extend schools
 * ------------------------------------------------------------------ */
$schoolColumns = [
    'subdomain'     => "VARCHAR(50) NULL AFTER school_code",
    'owner_name'    => "VARCHAR(100) NULL AFTER school_address",
    'status'        => "ENUM('active','suspended','trial') NOT NULL DEFAULT 'active' AFTER website",
    'plan'          => "ENUM('basic','pro','enterprise') NOT NULL DEFAULT 'basic' AFTER status",
    'trial_ends_at' => "DATETIME NULL AFTER plan",
];
foreach ($schoolColumns as $column => $definition) {
    if (!saas_column_exists($pdo, 'schools', $column)) {
        $pdo->exec("ALTER TABLE `schools` ADD COLUMN `$column` $definition");
        echo "[ok] schools.$column added\n";
    }
}

/* Backfill subdomains before adding the unique index. */
$schools = $pdo->query("SELECT id, school_name, school_code, subdomain, is_active, subscription_plan FROM schools ORDER BY id")->fetchAll();
$usedSubdomains = [];
foreach ($schools as $school) {
    $current = strtolower(trim((string)($school['subdomain'] ?? '')));
    if ($current !== '') {
        $usedSubdomains[$current] = (int)$school['id'];
    }
}
foreach ($schools as $school) {
    $current = strtolower(trim((string)($school['subdomain'] ?? '')));
    if ($current !== '') {
        continue;
    }

    $base = saas_slug((string)$school['school_name']);
    if ($base === '' || strlen($base) < 3) {
        $base = saas_slug((string)$school['school_code']);
    }
    if ($base === '' || strlen($base) < 3) {
        $base = 'school-' . (int)$school['id'];
    }

    $candidate = $base;
    $suffix = 1;
    while (isset($usedSubdomains[$candidate])) {
        $tail = '-' . $suffix++;
        $candidate = substr($base, 0, 50 - strlen($tail)) . $tail;
    }
    $usedSubdomains[$candidate] = (int)$school['id'];
    saas_q($pdo, "UPDATE schools SET subdomain = ? WHERE id = ?", [$candidate, (int)$school['id']]);
    echo "[ok] subdomain {$candidate} assigned to school #{$school['id']}\n";
}

if (!saas_index_exists($pdo, 'schools', 'uniq_school_subdomain')) {
    $pdo->exec("ALTER TABLE `schools` ADD UNIQUE KEY `uniq_school_subdomain` (`subdomain`)");
    echo "[ok] unique index on schools.subdomain\n";
}
if (!saas_index_exists($pdo, 'schools', 'idx_school_status')) {
    $pdo->exec("ALTER TABLE `schools` ADD INDEX `idx_school_status` (`status`)");
    echo "[ok] index on schools.status\n";
}

/* Map legacy subscription_plan values into the SaaS plan/status model. */
$pdo->exec("
    UPDATE schools
    SET status = CASE WHEN is_active = 1 THEN 'active' ELSE 'suspended' END
    WHERE status IS NULL OR status = ''
");
$pdo->exec("
    UPDATE schools
    SET plan = CASE
        WHEN subscription_plan = 'premium' THEN 'enterprise'
        ELSE 'basic'
    END
    WHERE plan IS NULL OR plan = '' OR plan = 'basic'
");
echo "[ok] existing schools mapped to status/plan\n";

/* ------------------------------------------------------------------
 * 2. Subscriptions
 * ------------------------------------------------------------------ */
$pdo->exec("
CREATE TABLE IF NOT EXISTS school_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL,
    plan ENUM('basic','pro','enterprise') NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    billing_cycle ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active','cancelled','expired') NOT NULL DEFAULT 'active',
    payment_method VARCHAR(50) NULL,
    transaction_id VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sub_school (school_id),
    INDEX idx_sub_status (status),
    INDEX idx_sub_end (end_date),
    CONSTRAINT fk_sub_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "[ok] school_subscriptions table\n";

$planPrices = ['basic' => 50.00, 'pro' => 150.00, 'enterprise' => 500.00];
foreach ($schools as $school) {
    $schoolId = (int)$school['id'];
    $exists = (int)saas_q($pdo, "SELECT COUNT(*) FROM school_subscriptions WHERE school_id = ?", [$schoolId])->fetchColumn();
    if ($exists > 0) {
        continue;
    }

    $plan = strtolower((string)($school['subscription_plan'] ?? 'basic'));
    $plan = $plan === 'premium' ? 'enterprise' : ($plan === 'pro' ? 'pro' : 'basic');
    $price = $planPrices[$plan] ?? 50.00;
    $createdAt = saas_q($pdo, "SELECT DATE(created_at) FROM schools WHERE id = ?", [$schoolId])->fetchColumn() ?: date('Y-m-d');

    saas_q($pdo, "
        INSERT INTO school_subscriptions
            (school_id, plan, price, billing_cycle, start_date, end_date, status, payment_method, transaction_id)
        VALUES (?, ?, ?, 'yearly', ?, DATE_ADD(?, INTERVAL 1 YEAR), 'active', 'migration', ?)
    ", [
        $schoolId,
        $plan,
        $price,
        $createdAt,
        $createdAt,
        'MIGRATED-' . $school['school_code'],
    ]);
    echo "[ok] subscription seeded for school #{$schoolId}\n";
}

/* ------------------------------------------------------------------
 * 3. Contact messages
 * ------------------------------------------------------------------ */
$pdo->exec("
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    subject VARCHAR(150) NOT NULL DEFAULT 'General enquiry',
    message TEXT NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    status ENUM('new','read','archived') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contact_school (school_id),
    INDEX idx_contact_status (status),
    INDEX idx_contact_created (created_at),
    CONSTRAINT fk_contact_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "[ok] contact_messages table\n";

/* ------------------------------------------------------------------
 * 4. Final report
 * ------------------------------------------------------------------ */
echo "\n=== SaaS migration complete ===\n";
echo "Schools:       " . saas_q($pdo, "SELECT COUNT(*) FROM schools")->fetchColumn() . "\n";
echo "Subscriptions: " . saas_q($pdo, "SELECT COUNT(*) FROM school_subscriptions")->fetchColumn() . "\n";
echo "Trials:        " . saas_q($pdo, "SELECT COUNT(*) FROM schools WHERE status = 'trial'")->fetchColumn() . "\n";
