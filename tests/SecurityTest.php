<?php
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

        return $results;
    }
}
