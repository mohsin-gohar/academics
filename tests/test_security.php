<?php
/**
 * Test Security Module (test_security.php)
 * EduSphere Enterprise School Management System
 */
class TestSecuritySuite {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function run(): array {
        $results = [];

        // 1. Prepared statements SQL injection protection
        $payloads = [
            "' OR '1'='1",
            "1; DROP TABLE users; --",
            "' UNION SELECT null, null, null, null, null, null, null, null --",
            "admin'--",
            "' OR ''='"
        ];

        $sqliSafe = true;
        foreach ($payloads as $p) {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$p]);
            if (count($stmt->fetchAll()) > 0) {
                $sqliSafe = false;
                break;
            }
        }
        $results[] = [
            'name' => 'SQLi attacks safely neutralized across parameter variations',
            'pass' => $sqliSafe
        ];

        // 2. XSS escaping e()
        $xssInputs = [
            '<script>alert(1)</script>' => '&lt;script&gt;alert(1)&lt;/script&gt;',
            '<img src=x onerror=alert(1)>' => '&lt;img src=x onerror=alert(1)&gt;',
            '" onfocus="alert(1)' => '&quot; onfocus=&quot;alert(1)',
            '\' onmouseover=\'alert(1)' => '&#039; onmouseover=&#039;alert(1)',
            '<b>Bold</b>' => '&lt;b&gt;Bold&lt;/b&gt;'
        ];

        $xssSafe = true;
        foreach ($xssInputs as $input => $expected) {
            if (e($input) !== $expected) {
                $xssSafe = false;
                break;
            }
        }
        $results[] = [
            'name' => 'e() HTML entity encoding neutralizes malicious script tags and attributes',
            'pass' => $xssSafe
        ];

        // 3. Formula injection safeCsvCell()
        $formulaInputs = ['=SUM(A1:A10)', '+cmd|/C calc', '-2+3', '@IMPORT'];
        $csvSafe = true;
        foreach ($formulaInputs as $fi) {
            $escaped = safeCsvCell($fi);
            if (substr($escaped, 0, 1) !== "'") {
                $csvSafe = false;
                break;
            }
        }
        $results[] = [
            'name' => 'safeCsvCell neutralizes formula injection triggers (=, +, -, @)',
            'pass' => $csvSafe
        ];

        $results[] = [
            'name' => 'safeCsvCell leaves safe alphanumeric strings unchanged',
            'pass' => (safeCsvCell('Student Number 100') === 'Student Number 100')
        ];

        // 4. Role authorization RBAC tests
        $_SESSION['user_role'] = 'admin';
        $results[] = [
            'name' => 'Admin role grants isAdmin and isStaff',
            'pass' => isAdmin() && isStaff() && !isStudent()
        ];

        $_SESSION['user_role'] = 'teacher';
        $results[] = [
            'name' => 'Teacher role grants isStaff and blocks isAdmin',
            'pass' => isStaff() && !isAdmin() && !isStudent()
        ];

        $_SESSION['user_role'] = 'student';
        $results[] = [
            'name' => 'Student role grants isStudent and blocks isStaff/isAdmin',
            'pass' => isStudent() && !isStaff() && !isAdmin()
        ];

        $_SESSION['user_role'] = 'parent';
        $results[] = [
            'name' => 'Parent role correctly validates isParent and blocks isStaff',
            'pass' => isParent() && !isStaff() && !isAdmin()
        ];

        // 5. Activity logging
        recordActivity($this->pdo, 1, 'security_test', 'system', 0, 'QA Security Test Log');
        $logStmt = $this->pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE action = 'security_test'");
        $logStmt->execute();
        $results[] = [
            'name' => 'recordActivity successfully records security audit logs',
            'pass' => (int)$logStmt->fetchColumn() > 0
        ];

        // Clean test log
        $this->pdo->exec("DELETE FROM activity_logs WHERE action = 'security_test'");

        return $results;
    }
}
