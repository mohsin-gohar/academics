<?php
/**
 * SaaS Layer Tests
 * Plans, subdomains, school self-registration, trials, subscriptions,
 * contact intake and tenant isolation.
 */
require_once __DIR__ . '/../includes/saas.php';

class SaasTest {
    private $pdo;
    private $cleanupSchoolIds = [];
    private $cleanupMessageIds = [];

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    private function tableExists(string $table): bool {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function columnExists(string $table, string $column): bool {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function registerFixture(string $subdomain, string $email, string $plan = 'pro'): array {
        $result = saas_register_school($this->pdo, [
            'school_name'      => 'QA SaaS Test School',
            'subdomain'        => $subdomain,
            'full_name'        => 'QA SaaS Admin',
            'email'            => $email,
            'phone'            => '+92 300 0000000',
            'address'          => 'QA Test Address',
            'password'         => 'QaTestPass123',
            'confirm_password' => 'QaTestPass123',
            'plan'             => $plan,
        ]);
        $this->cleanupSchoolIds[] = (int)$result['school_id'];
        return $result;
    }

    private function cleanup(): void {
        foreach ($this->cleanupMessageIds as $messageId) {
            $this->pdo->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([$messageId]);
        }
        foreach ($this->cleanupSchoolIds as $schoolId) {
            foreach ([
                "DELETE FROM activity_logs WHERE school_id = ?",
                "DELETE FROM contact_messages WHERE school_id = ?",
                "DELETE FROM school_subscriptions WHERE school_id = ?",
                "DELETE FROM school_settings WHERE school_id = ?",
                "DELETE FROM users WHERE school_id = ?",
            ] as $sql) {
                try {
                    $this->pdo->prepare($sql)->execute([$schoolId]);
                } catch (Throwable $e) {
                    // Best-effort cleanup; a missing optional table must not fail the suite.
                }
            }
            try {
                $this->pdo->prepare("DELETE FROM schools WHERE id = ?")->execute([$schoolId]);
            } catch (Throwable $e) {
                // Ignore cleanup failures.
            }
        }
        $this->cleanupSchoolIds = [];
        $this->cleanupMessageIds = [];
    }

    public function run() {
        $results = [];
        $suffix = bin2hex(random_bytes(4));
        $subdomain = 'qa-saas-' . $suffix;
        $email = $subdomain . '@example.com';
        $fixture = null;

        try {
            // 1. SaaS schema
            $requiredColumns = ['subdomain', 'owner_name', 'status', 'plan', 'trial_ends_at'];
            $missingColumns = array_values(array_filter($requiredColumns, fn($column) => !$this->columnExists('schools', $column)));
            $results[] = [
                'name' => 'SaaS migration adds subdomain, status, plan and trial columns to schools',
                'pass' => $missingColumns === [],
            ];

            $results[] = [
                'name' => 'Subscription and contact message tables exist',
                'pass' => $this->tableExists('school_subscriptions') && $this->tableExists('contact_messages'),
            ];

            // 2. Plans
            $plans = saas_plans();
            $results[] = [
                'name' => 'Three SaaS plans are defined with Basic $50, Pro $150 and Enterprise $500 pricing',
                'pass' => array_keys($plans) === ['basic', 'pro', 'enterprise']
                    && saas_plan_price('basic') === 50.0
                    && saas_plan_price('pro') === 150.0
                    && saas_plan_price('enterprise') === 500.0,
            ];
            $results[] = [
                'name' => 'Legacy subscription plans map onto the SaaS plan model',
                'pass' => saas_plan_from_legacy('premium') === 'enterprise'
                    && saas_plan_from_legacy('pro') === 'pro'
                    && saas_plan_from_legacy('basic') === 'basic'
                    && saas_legacy_plan('enterprise') === 'premium',
            ];

            // 3. Subdomain validation
            $results[] = [
                'name' => 'Subdomain validation accepts normal tenant names and rejects reserved or malformed names',
                'pass' => saas_validate_subdomain('green-valley')
                    && !saas_validate_subdomain('www')
                    && !saas_validate_subdomain('admin')
                    && !saas_validate_subdomain('ab')
                    && !saas_validate_subdomain('-leading')
                    && !saas_validate_subdomain('bad--double')
                    && !saas_validate_subdomain('bad_name'),
            ];

            // 4. Host resolution
            $results[] = [
                'name' => 'Subdomain resolution works against the configured base domain',
                'pass' => saas_subdomain_from_host('greenvalley.academics.com', 'academics.com') === 'greenvalley'
                    && saas_subdomain_from_host('academics.com', 'academics.com') === null
                    && saas_subdomain_from_host('www.academics.com', 'academics.com') === null
                    && saas_subdomain_from_host('a.b.academics.com', 'academics.com') === null,
            ];
            $results[] = [
                'name' => 'Subdomain resolution supports *.localhost and ignores localhost or raw IP hosts',
                'pass' => saas_subdomain_from_host('greenvalley.localhost', '') === 'greenvalley'
                    && saas_subdomain_from_host('localhost', '') === null
                    && saas_subdomain_from_host('127.0.0.1', '') === null
                    && saas_subdomain_from_host('greenvalley.localhost:8091', '') === 'greenvalley',
            ];

            // 5. School self-registration
            $fixture = $this->registerFixture($subdomain, $email, 'pro');
            $school = school_by_id($this->pdo, (int)$fixture['school_id']);
            $trialSeconds = $school ? strtotime((string)$school['trial_ends_at']) - time() : 0;
            $results[] = [
                'name' => 'School self-registration creates a 30-day trial tenant with a unique code',
                'pass' => $school
                    && $school['subdomain'] === $subdomain
                    && $school['status'] === 'trial'
                    && $school['plan'] === 'pro'
                    && (int)$school['is_active'] === 1
                    && $school['school_code'] !== ''
                    && $trialSeconds > 29 * 86400
                    && $trialSeconds <= 31 * 86400,
            ];

            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $adminUser = $stmt->fetch();
            $results[] = [
                'name' => 'School registration creates an active school_admin login with a hashed password',
                'pass' => $adminUser
                    && $adminUser['role'] === 'school_admin'
                    && (int)$adminUser['school_id'] === (int)$fixture['school_id']
                    && (int)$adminUser['is_active'] === 1
                    && password_verify('QaTestPass123', $adminUser['password'])
                    && !password_verify('wrong-password', $adminUser['password']),
            ];

            $stmt = $this->pdo->prepare("SELECT * FROM school_subscriptions WHERE school_id = ? AND payment_method = 'trial' LIMIT 1");
            $stmt->execute([(int)$fixture['school_id']]);
            $trialSubscription = $stmt->fetch();
            $results[] = [
                'name' => 'School registration records a zero-price trial subscription ending with the trial',
                'pass' => $trialSubscription
                    && $trialSubscription['plan'] === 'pro'
                    && (float)$trialSubscription['price'] === 0.0
                    && $trialSubscription['status'] === 'active'
                    && $trialSubscription['end_date'] === date('Y-m-d', strtotime((string)$school['trial_ends_at'])),
            ];

            // 6. Duplicate subdomain protection
            $duplicateRejected = false;
            try {
                saas_register_school($this->pdo, [
                    'school_name'      => 'Duplicate School',
                    'subdomain'        => $subdomain,
                    'full_name'        => 'Duplicate Admin',
                    'email'            => 'duplicate-' . $suffix . '@example.com',
                    'password'         => 'QaTestPass123',
                    'confirm_password' => 'QaTestPass123',
                    'plan'             => 'basic',
                ]);
            } catch (InvalidArgumentException $e) {
                $duplicateRejected = stripos($e->getMessage(), 'subdomain') !== false;
            }
            $results[] = [
                'name' => 'Registering a duplicate subdomain is rejected',
                'pass' => $duplicateRejected,
            ];

            // 7. Trial / access states
            $trialExpired = ['is_active' => 1, 'status' => 'trial', 'trial_ends_at' => date('Y-m-d H:i:s', strtotime('-1 day'))];
            $trialActive = ['is_active' => 1, 'status' => 'trial', 'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+1 day'))];
            $suspended = ['is_active' => 1, 'status' => 'suspended', 'trial_ends_at' => null];
            $active = ['is_active' => 1, 'status' => 'active', 'trial_ends_at' => null];
            $results[] = [
                'name' => 'Expired trials and suspended schools are blocked while active schools are allowed',
                'pass' => saas_school_access_state($trialExpired) === ['allowed' => false, 'reason' => 'trial_expired']
                    && saas_school_access_state($suspended) === ['allowed' => false, 'reason' => 'suspended']
                    && saas_school_access_state($trialActive) === ['allowed' => true, 'reason' => '']
                    && saas_school_access_state($active) === ['allowed' => true, 'reason' => ''],
            ];

            // 8. Subscriptions
            $mrrBefore = saas_monthly_recurring_revenue($this->pdo);
            $subscriptionId = saas_create_subscription($this->pdo, (int)$fixture['school_id'], [
                'plan'           => 'enterprise',
                'billing_cycle'  => 'monthly',
                'start_date'     => date('Y-m-d'),
                'end_date'       => date('Y-m-d', strtotime('+1 month')),
                'status'         => 'active',
                'payment_method' => 'qa-test',
                'transaction_id' => 'QA-' . strtoupper($suffix),
            ]);
            $schoolAfter = school_by_id($this->pdo, (int)$fixture['school_id']);
            $activeSubscription = saas_active_subscription($this->pdo, (int)$fixture['school_id']);
            $mrrAfter = saas_monthly_recurring_revenue($this->pdo);
            $results[] = [
                'name' => 'An active paid subscription activates the school, clears the trial and is found as current',
                'pass' => $subscriptionId > 0
                    && $schoolAfter
                    && $schoolAfter['plan'] === 'enterprise'
                    && $schoolAfter['status'] === 'active'
                    && $schoolAfter['trial_ends_at'] === null
                    && (int)$schoolAfter['is_active'] === 1
                    && $activeSubscription
                    && (int)$activeSubscription['id'] === $subscriptionId
                    && (float)$activeSubscription['price'] === 500.0
                    && $mrrAfter >= $mrrBefore + 499.0,
            ];

            // 9. Tenant isolation
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE school_id = ?");
            $stmt->execute([(int)$fixture['school_id']]);
            $tenantUserCount = (int)$stmt->fetchColumn();
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE school_id = ? AND email = ?");
            $stmt->execute([(int)$fixture['school_id'], $email]);
            $scopedUserCount = (int)$stmt->fetchColumn();
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE school_id <> ? AND email = ?");
            $stmt->execute([(int)$fixture['school_id'], $email]);
            $leakedUserCount = (int)$stmt->fetchColumn();
            $results[] = [
                'name' => 'New school users are isolated to their own school_id',
                'pass' => $tenantUserCount === 1 && $scopedUserCount === 1 && $leakedUserCount === 0,
            ];

            // 10. Contact intake
            $contactId = saas_contact_store($this->pdo, [
                'school_id' => (int)$fixture['school_id'],
                'name'      => 'QA Contact',
                'email'     => 'qa-contact-' . $suffix . '@example.com',
                'subject'   => 'QA subscription question',
                'message'   => 'This is an automated SaaS contact intake test message.',
            ]);
            $this->cleanupMessageIds[] = $contactId;
            $stmt = $this->pdo->prepare("SELECT * FROM contact_messages WHERE id = ?");
            $stmt->execute([$contactId]);
            $message = $stmt->fetch();
            $results[] = [
                'name' => 'Contact intake stores the message with school scoping and new status',
                'pass' => $contactId > 0
                    && $message
                    && (int)$message['school_id'] === (int)$fixture['school_id']
                    && $message['status'] === 'new'
                    && str_contains($message['message'], 'automated SaaS contact'),
            ];
        } catch (Throwable $e) {
            $results[] = [
                'name' => 'SaaS suite completed without unexpected errors',
                'pass' => false,
            ];
            error_log('SaasTest error: ' . $e->getMessage());
        } finally {
            $this->cleanup();
        }

        return $results;
    }
}
