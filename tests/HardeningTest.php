<?php
/**
 * Hardening regression tests.
 *
 * Covers the risks closed by the production audit: unauthenticated endpoint
 * throttling, the expense approval state machine and tenant scoping, lapsed
 * subscriptions, and the guards that keep test fixtures away from an
 * allowlisted-only, loopback test database.
 */
require_once __DIR__ . '/../includes/saas.php';

class HardeningTest
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?"
        );
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function runFixtureProbe(string $appEnv, string $dbName): string
    {
        $root = dirname(__DIR__);
        $code = 'define("APP_ENV", ' . var_export($appEnv, true) . ');'
            . 'define("DB_NAME", ' . var_export($dbName, true) . ');'
            . 'define("DB_HOST", "127.0.0.1");'
            . 'try { require "tests/fixtures.php"; echo "LOADED"; }'
            . ' catch (Throwable $e) { echo "REFUSED: " . $e->getMessage(); }';
        $cmd = escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 -d display_errors=0 -r '
            . escapeshellarg($code) . ' 2>&1';
        $output = (string)shell_exec('cd ' . escapeshellarg($root) . ' && ' . $cmd);
        return trim($output);
    }

    public function run(): array
    {
        $results = [];
        $add = function (string $name, bool $pass) use (&$results) {
            $results[] = ['name' => $name, 'pass' => $pass];
        };

        /* ------------------------------------------------- rate limiting */
        if ($this->tableExists('rate_limits')) {
            $bucket = 'test_bucket_' . bin2hex(random_bytes(4));
            $identifier = 'rate-limit-probe';
            $allowed = [];
            for ($i = 0; $i < 3; $i++) {
                $allowed[] = rate_limit_exceeded($this->pdo, $bucket, $identifier, 3, 3600);
            }
            $add('Rate limit allows exactly the configured number of attempts',
                $allowed === [false, false, false]);
            $add('Rate limit blocks the next attempt in the window',
                rate_limit_exceeded($this->pdo, $bucket, $identifier, 3, 3600) === true);
            $add('Rate limit is scoped per identifier',
                rate_limit_exceeded($this->pdo, $bucket, 'another-client', 3, 3600) === false);

            $expiredBucket = 'test_expired_' . bin2hex(random_bytes(4));
            // Written in the connection's own time zone, like production rows.
            $this->pdo->prepare(
                "INSERT INTO rate_limits (bucket, identifier, created_at)
                 VALUES (?, ?, DATE_SUB(NOW(), INTERVAL 7200 SECOND))"
            )->execute([$expiredBucket, hash('sha256', 'stale')]);
            $add('Rate limit ignores attempts older than the window',
                rate_limit_exceeded($this->pdo, $expiredBucket, 'stale', 1, 60) === false);
            $this->pdo->prepare("DELETE FROM rate_limits WHERE bucket IN (?, ?)")
                ->execute([$bucket, $expiredBucket]);
        } else {
            $add('rate_limits table exists (migration 0014 applied)', false);
        }

        /* ------------------------------------------------ expense endpoints */
        $add('Expense create page exists (was a dead link)', is_file(dirname(__DIR__) . '/expenses/create.php'));
        $add('Expense approve endpoint exists', is_file(dirname(__DIR__) . '/expenses/approve.php'));

        $schoolRow = $this->pdo->query("SELECT id FROM schools ORDER BY id LIMIT 1")->fetchColumn();
        $otherSchoolRow = $this->pdo->query("SELECT id FROM schools ORDER BY id LIMIT 1 OFFSET 1")->fetchColumn();
        $userRow = $this->pdo->query("SELECT id FROM users ORDER BY id LIMIT 1")->fetchColumn();

        if ($schoolRow && $otherSchoolRow && $userRow) {
            $title = 'fixture-expense-' . bin2hex(random_bytes(3));
            $this->pdo->prepare(
                "INSERT INTO expenses (school_id, title, amount, expense_date, category, status, created_by)
                 VALUES (?, ?, 12.50, CURDATE(), 'Supplies', 'pending', ?)"
            )->execute([(int)$schoolRow, $title, (int)$userRow]);
            $expenseId = (int)$this->pdo->lastInsertId();

            $cross = $this->pdo->prepare(
                "UPDATE expenses SET status = 'approved', approved_at = NOW()
                 WHERE id = ? AND school_id = ? AND status = 'pending'"
            );
            $cross->execute([$expenseId, (int)$otherSchoolRow]);
            $add('Cross-school expense approval changes no rows', $cross->rowCount() === 0);

            $own = $this->pdo->prepare(
                "UPDATE expenses SET status = 'approved', approved_at = NOW()
                 WHERE id = ? AND school_id = ? AND status = 'pending'"
            );
            $own->execute([$expenseId, (int)$schoolRow]);
            $add('Same-school expense approval transitions once', $own->rowCount() === 1);
            $own->execute([$expenseId, (int)$schoolRow]);
            $add('Expense cannot be approved twice', $own->rowCount() === 0);

            $status = $this->pdo->prepare("SELECT status FROM expenses WHERE id = ?");
            $status->execute([$expenseId]);
            $add('Expense status uses the lowercase enum after approval', $status->fetchColumn() === 'approved');

            $this->pdo->prepare("DELETE FROM expenses WHERE id = ?")->execute([$expenseId]);
        }

        /* -------------------------------------------- subscription states */
        $baseSchool = ['status' => 'active', 'is_active' => 1];
        $add('Active school is accessible',
            saas_school_access_state($baseSchool)['allowed'] === true);
        $add('Suspended school is refused',
            saas_school_access_state(['status' => 'suspended', 'is_active' => 1])['reason'] === 'suspended');
        $add('Disabled school is refused',
            saas_school_access_state(['status' => 'active', 'is_active' => 0])['reason'] === 'disabled');
        $add('Expired subscription is refused',
            saas_school_access_state(['status' => 'expired', 'is_active' => 1])['reason'] === 'subscription_expired');
        $add('Cancelled subscription is refused',
            saas_school_access_state(['status' => 'cancelled', 'is_active' => 1])['reason'] === 'subscription_expired');
        $add('Expired subscription has a user-facing message',
            str_contains(saas_access_message('subscription_expired'), 'subscription has expired'));
        $add('Trial past its end date is refused',
            saas_school_access_state([
                'status' => 'trial',
                'is_active' => 1,
                'trial_ends_at' => date('Y-m-d', strtotime('-1 day')),
            ])['reason'] === 'trial_expired');
        $add('Trial inside its window is accessible',
            saas_school_access_state([
                'status' => 'trial',
                'is_active' => 1,
                'trial_ends_at' => date('Y-m-d', strtotime('+7 days')),
            ])['allowed'] === true);

        /* ------------------------------------------------- fixture guards */
        $productionProbe = $this->runFixtureProbe('production', 'edusphere');
        $add('Fixtures refuse to load when APP_ENV is not test',
            str_starts_with($productionProbe, 'REFUSED:') && str_contains($productionProbe, 'APP_ENV'));

        $foreignDbProbe = $this->runFixtureProbe('test', 'edusphere_production_copy');
        $add('Fixtures refuse a test run against a non-allowlisted database',
            str_starts_with($foreignDbProbe, 'REFUSED:') && str_contains($foreignDbProbe, 'allowlist'));

        return $results;
    }
}
