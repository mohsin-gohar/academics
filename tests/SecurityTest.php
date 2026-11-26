<?php
require_once __DIR__ . '/../includes/tenant.php';
/**
 * Security, XSS, SQLi & CSV Injection Tests
 */
class SecurityTest {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function run() {
        $results = [];

        // 1. SQL Injection Immunity in Prepared Statements
        $sqliPayload = "admin' OR '1'='1";
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$sqliPayload]);
        $rows = $stmt->fetchAll();
        $results[] = [
            'name' => 'Prepared statement safely neutralizes classic SQLi payload (OR 1=1)',
            'pass' => (count($rows) === 0)
        ];

        // Second SQLi check: UNION injection
        $unionPayload = "' UNION SELECT 1, 'hacked', 'hacked@hack.com', 'pwd', 'admin', 1, NOW(), NOW(), NOW() -- ";
        $stmt->execute([$unionPayload]);
        $unionRows = $stmt->fetchAll();
        $results[] = [
            'name' => 'Prepared statement neutralizes UNION-based SQLi attempt',
            'pass' => (count($unionRows) === 0)
        ];

        // 2. Cross-Site Scripting (XSS) Sanitization
        $xssPayload = '<script>alert("XSS Attack!");</script>';
        $sanitized = e($xssPayload);
        $results[] = [
            'name' => 'e() helper escapes dangerous script tags to &lt;script&gt;',
            'pass' => (strpos($sanitized, '<script>') === false && strpos($sanitized, '&lt;script&gt;') !== false)
        ];

        $attrXss = '" onmouseover="alert(1)"';
        $sanitizedAttr = e($attrXss);
        $results[] = [
            'name' => 'e() helper escapes double quotes to prevent attribute breakout',
            'pass' => (strpos($sanitizedAttr, '&quot;') !== false)
        ];

        // 3. CSV Formula Injection Defense
        $formulaPayload1 = '=cmd|"/C calc"!A0';
        $formulaPayload2 = '+10+20';
        $formulaPayload3 = '-50';
        $formulaPayload4 = '@SUM(A1:A10)';
        $normalText = 'Standard Student Name';

        $escaped1 = safeCsvCell($formulaPayload1);
        $escaped2 = safeCsvCell($formulaPayload2);
        $escaped3 = safeCsvCell($formulaPayload3);
        $escaped4 = safeCsvCell($formulaPayload4);
        $escapedNormal = safeCsvCell($normalText);

        $results[] = [
            'name' => 'safeCsvCell prefixes "=" formula injection with a single quote',
            'pass' => ($escaped1 === "'=cmd|\"/C calc\"!A0")
        ];
        $results[] = [
            'name' => 'safeCsvCell neutralizes "+", "-", "@" spreadsheet triggers',
            'pass' => ($escaped2[0] === "'" && $escaped3[0] === "'" && $escaped4[0] === "'")
        ];
        $results[] = [
            'name' => 'safeCsvCell preserves normal safe alphanumeric text untouched',
            'pass' => ($escapedNormal === $normalText)
        ];

        // 4. Role Hierarchy Helpers Verification
        $_SESSION['user_role'] = 'admin';
        $adminStaff = isStaff();
        $adminAdmin = isAdmin();
        $adminStudent = isStudent();

        $_SESSION['user_role'] = 'teacher';
        $teacherStaff = isStaff();
        $teacherAdmin = isAdmin();
        $teacherStudent = isStudent();

        $_SESSION['user_role'] = 'student';
        $studentStaff = isStaff();
        $studentAdmin = isAdmin();
        $studentStudent = isStudent();

        $results[] = [
            'name' => 'Admin role correctly validates isAdmin=true, isStaff=true, isStudent=false',
            'pass' => ($adminStaff && $adminAdmin && !$adminStudent)
        ];
        $results[] = [
            'name' => 'Teacher role correctly validates isStaff=true, isAdmin=false, isStudent=false',
            'pass' => ($teacherStaff && !$teacherAdmin && !$teacherStudent)
        ];
        $results[] = [
            'name' => 'Student role correctly validates isStudent=true, isStaff=false, isAdmin=false',
            'pass' => ($studentStudent && !$studentStaff && !$studentAdmin)
        ];

        // 5. Tenant subscription/session access must remain valid after login.
        $now = strtotime('2026-11-26 12:00:00');
        $activeSchool = school_session_access_state([
            'status' => 'active', 'is_active' => 1, 'trial_ends_at' => null
        ], $now);
        $suspendedSchool = school_session_access_state([
            'status' => 'suspended', 'is_active' => 1, 'trial_ends_at' => null
        ], $now);
        $disabledSchool = school_session_access_state([
            'status' => 'active', 'is_active' => 0, 'trial_ends_at' => null
        ], $now);
        $expiredTrial = school_session_access_state([
            'status' => 'trial', 'is_active' => 1, 'trial_ends_at' => '2026-11-25 23:59:59'
        ], $now);
        $validTrial = school_session_access_state([
            'status' => 'trial', 'is_active' => 1, 'trial_ends_at' => '2026-11-27 00:00:00'
        ], $now);
        $results[] = [
            'name' => 'Active school remains accessible to authenticated sessions',
            'pass' => $activeSchool['allowed'] === true
        ];
        $results[] = [
            'name' => 'Suspended school is denied to existing authenticated sessions',
            'pass' => $suspendedSchool['allowed'] === false && $suspendedSchool['reason'] === 'school_suspended'
        ];
        $results[] = [
            'name' => 'Disabled school is denied to existing authenticated sessions',
            'pass' => $disabledSchool['allowed'] === false && $disabledSchool['reason'] === 'school_inactive'
        ];
        $results[] = [
            'name' => 'Expired trial is denied even when a session was already active',
            'pass' => $expiredTrial['allowed'] === false && $expiredTrial['reason'] === 'trial_expired'
        ];
        $results[] = [
            'name' => 'Unexpired trial remains accessible to authenticated sessions',
            'pass' => $validTrial['allowed'] === true
        ];

        // 6. Hard-delete guards discover every CASCADE relationship from schema metadata.
        $userDependencies = cascade_dependent_records($this->pdo, 'users', 5);
        $studentDependencies = cascade_dependent_records($this->pdo, 'students', 2);
        $courseDependencies = cascade_dependent_records($this->pdo, 'courses', 1);
        $results[] = [
            'name' => 'User deletion guard detects dependent task/message/history rows',
            'pass' => count($userDependencies) > 0
        ];
        $results[] = [
            'name' => 'Student deletion guard detects enrollment and invoice dependencies',
            'pass' => count($studentDependencies) > 0
        ];
        $results[] = [
            'name' => 'Course deletion guard detects enrollment and assessment dependencies',
            'pass' => count($courseDependencies) > 0
        ];

        // 7. A tenant with subscription records must have one currently active
        // subscription; cancelled/expired billing must revoke existing access.
        $subscriptionStates = [];
        $this->pdo->beginTransaction();
        try {
            $sub = $this->pdo->prepare(
                "INSERT INTO school_subscriptions
                    (school_id, plan, price, billing_cycle, start_date, end_date, status)
                 VALUES (2, 'basic', 0, 'monthly', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'active')"
            );
            $sub->execute();
            $subscriptionId = (int)$this->pdo->lastInsertId();
            $schoolTwo = school_by_id($this->pdo, 2);
            $subscriptionStates['active'] = school_session_access_state($schoolTwo, time(), $this->pdo);
            $subscriptionStates['active_saas'] = saas_school_access_state($schoolTwo, $this->pdo);
            $this->pdo->prepare("UPDATE school_subscriptions SET status = 'cancelled' WHERE id = ?")->execute([$subscriptionId]);
            $subscriptionStates['cancelled'] = school_session_access_state($schoolTwo, time(), $this->pdo);
            $this->pdo->prepare("UPDATE school_subscriptions SET status = 'active', end_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE id = ?")->execute([$subscriptionId]);
            $subscriptionStates['expired'] = school_session_access_state($schoolTwo, time(), $this->pdo);
            $this->pdo->rollBack();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
        $results[] = [
            'name' => 'Active subscription permits school access',
            'pass' => ($subscriptionStates['active']['allowed'] ?? false) === true
        ];
        $results[] = [
            'name' => 'Cancelled subscription revokes school access',
            'pass' => ($subscriptionStates['cancelled']['allowed'] ?? true) === false
                && ($subscriptionStates['cancelled']['reason'] ?? '') === 'subscription_inactive'
        ];
        $results[] = [
            'name' => 'Expired subscription date revokes school access',
            'pass' => ($subscriptionStates['expired']['allowed'] ?? true) === false
                && ($subscriptionStates['expired']['reason'] ?? '') === 'subscription_inactive'
        ];
        $results[] = [
            'name' => 'SaaS login access state uses the same subscription policy',
            'pass' => ($subscriptionStates['active_saas']['allowed'] ?? false) === true
        ];

        return $results;
    }
}
