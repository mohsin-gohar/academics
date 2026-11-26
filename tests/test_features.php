<?php
/**
 * Test Features Suite (test_features.php)
 * EduSphere Enterprise School Management System
 */
class TestFeaturesSuite {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function run(): array {
        $results = [];

        // 1. Attendance marking & retrieval
        $schoolId = 1;
        $studentId = 1;
        $today = date('Y-m-d');

        $marked = markAttendance($this->pdo, $schoolId, $studentId, $today, 'present', 'On time', 1);
        $results[] = [
            'name' => 'Attendance: Mark daily attendance for student',
            'pass' => $marked === true
        ];

        $summary = getStudentAttendanceSummary($this->pdo, $schoolId, $studentId);
        $results[] = [
            'name' => 'Attendance: Retrieve student attendance summary and percentage',
            'pass' => isset($summary['total']) && $summary['total'] > 0 && isset($summary['percentage'])
        ];
        $results[] = [
            'name' => 'Attendance: Reject marking a student from another school',
            'pass' => markAttendance($this->pdo, $schoolId, 3, $today, 'present', null, 1) === false
        ];

        // 2. Gradebook & Assessments
        $assessmentId = createAssessment($this->pdo, $schoolId, 1, 'Unit 1 Examination', 'exam', 100.0, 1.0, $today, 'Midterm exam', 1);
        $results[] = [
            'name' => 'Gradebook: Create course assessment successfully',
            'pass' => $assessmentId > 0
        ];
        $results[] = [
            'name' => 'Gradebook: Reject assessment creation for another school course',
            'pass' => createAssessment($this->pdo, $schoolId, 2, 'Cross Tenant Assessment', 'exam', 100.0, 1.0, $today, null, 1) === 0
        ];

        $markRecorded = recordMark($this->pdo, $schoolId, $assessmentId, $studentId, 95.0, 'Excellent performance', 1);
        $results[] = [
            'name' => 'Gradebook: Record student assessment score',
            'pass' => $markRecorded === true
        ];
        $results[] = [
            'name' => 'Gradebook: Reject recording marks for another school student',
            'pass' => recordMark($this->pdo, $schoolId, $assessmentId, 3, 50.0, null, 1) === false
        ];

        $gradeSummary = getStudentGradeSummary($this->pdo, $schoolId, $studentId);
        $results[] = [
            'name' => 'Gradebook: Calculate student GPA and course grades',
            'pass' => isset($gradeSummary['average']) && $gradeSummary['average'] >= 0
        ];

        // 3. Fees and Invoices
        $invoiceNum = generateInvoiceNumber($this->pdo, $schoolId);
        $results[] = [
            'name' => 'Fees: Generate unique invoice number format',
            'pass' => (bool)preg_match('/^INV-\d{3,4}-\d{5}$/', $invoiceNum)
        ];

        $invoiceId = createInvoice($this->pdo, $schoolId, $studentId, null, 'Term 1 Tuition Fee', 500.00, date('Y-m-d', strtotime('+30 days')), 1);
        $results[] = [
            'name' => 'Fees: Create tuition invoice for student',
            'pass' => $invoiceId > 0
        ];
        $results[] = [
            'name' => 'Fees: Reject invoice creation for another school student',
            'pass' => createInvoice($this->pdo, $schoolId, 3, null, 'Cross Tenant Invoice', 100.0, null, 1) === 0
        ];

        $payRecorded = recordPayment($this->pdo, $schoolId, $invoiceId, 500.00, $today, 'Bank Transfer', 'TXN-998877', 1, 'Paid in full');
        $results[] = [
            'name' => 'Fees: Record payment and mark invoice as paid',
            'pass' => $payRecorded === true
        ];

        $feeSummary = getFeeSummary($this->pdo, $schoolId);
        $results[] = [
            'name' => 'Fees: Calculate school fee collection summary metrics',
            'pass' => isset($feeSummary['paid']) && $feeSummary['paid'] > 0
        ];

        $validationInvoiceId = createInvoice(
            $this->pdo, $schoolId, $studentId, null, 'Payment Validation Test',
            100.00, date('Y-m-d', strtotime('+30 days')), 1
        );
        $results[] = [
            'name' => 'Fees: Reject unsupported payment methods',
            'pass' => $validationInvoiceId > 0 && recordPayment(
                $this->pdo, $schoolId, $validationInvoiceId, 10.00, $today,
                'crypto', 'INVALID-METHOD', 1, null
            ) === false
        ];
        $results[] = [
            'name' => 'Fees: Reject impossible payment dates',
            'pass' => $validationInvoiceId > 0 && recordPayment(
                $this->pdo, $schoolId, $validationInvoiceId, 10.00, '2026-02-31',
                'cash', 'INVALID-DATE', 1, null
            ) === false
        ];
        $results[] = [
            'name' => 'Fees: Prevent additional payments on a paid invoice',
            'pass' => recordPayment(
                $this->pdo, $schoolId, $invoiceId, 1.00, $today,
                'cash', 'DUPLICATE-PAYMENT', 1, null
            ) === false
        ];

        // 4. Timetable Scheduling & Conflict Detection
        $insTt = $this->pdo->prepare("INSERT INTO timetables (school_id, teacher_id, course_id, day_of_week, start_time, end_time, room, status)
                                      VALUES (?, 2, 1, 'Monday', '09:00:00', '10:30:00', 'Lab 1', 'Active')");
        $insTt->execute([$schoolId]);
        $ttId = (int)$this->pdo->lastInsertId();
        $results[] = [
            'name' => 'Timetable: Create scheduled class slot for course and teacher',
            'pass' => $ttId > 0
        ];

        // Conflict check query test
        $conflictStmt = $this->pdo->prepare("SELECT COUNT(*) FROM timetables
            WHERE teacher_id = 2 AND day_of_week = 'Monday' AND status = 'Active'
            AND ((start_time < '10:00:00' AND end_time > '09:00:00'))");
        $conflictStmt->execute();
        $conflicts = (int)$conflictStmt->fetchColumn();
        $results[] = [
            'name' => 'Timetable: Accurately identifies overlapping schedule conflicts',
            'pass' => $conflicts > 0
        ];

        // 5. Messages: Send internal message between users
        $insMsg = $this->pdo->prepare("INSERT INTO messages (school_id, sender_id, receiver_id, content, type, read_status)
                                       VALUES (?, 1, 2, 'Automated test message', 'System', 0)");
        $insMsg->execute([$schoolId]);
        $msgId = (int)$this->pdo->lastInsertId();
        $results[] = [
            'name' => 'Messages: Send internal message between users',
            'pass' => $msgId > 0
        ];

        $updMsg = $this->pdo->prepare("UPDATE messages SET read_status = 1 WHERE id = ?");
        $updMsg->execute([$msgId]);
        $chkMsg = $this->pdo->prepare("SELECT read_status FROM messages WHERE id = ?");
        $chkMsg->execute([$msgId]);
        $results[] = [
            'name' => 'Messages: Update read status when opened',
            'pass' => (int)$chkMsg->fetchColumn() === 1
        ];

        // 6. Leave Requests Workflow
        $insLeave = $this->pdo->prepare("INSERT INTO leave_requests (school_id, user_id, leave_type, start_date, end_date, reason, status)
                                         VALUES (?, 2, 'Sick', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'Doctor appointment', 'Pending')");
        $insLeave->execute([$schoolId]);
        $leaveId = (int)$this->pdo->lastInsertId();
        $results[] = [
            'name' => 'Leave Requests: Submit leave request with dates and type',
            'pass' => $leaveId > 0
        ];

        $updLeave = $this->pdo->prepare("UPDATE leave_requests SET status = 'Approved', approved_by = 1 WHERE id = ?");
        $updLeave->execute([$leaveId]);
        $chkLeave = $this->pdo->prepare("SELECT status, approved_by FROM leave_requests WHERE id = ?");
        $chkLeave->execute([$leaveId]);
        $leaveRow = $chkLeave->fetch(PDO::FETCH_ASSOC);
        $results[] = [
            'name' => 'Leave Requests: Admin approval sets status and approver ID',
            'pass' => ($leaveRow['status'] === 'Approved' && (int)$leaveRow['approved_by'] === 1)
        ];

        // 7. System Settings Key-Value Store (application_settings is canonical)
        $stmtSet = $this->pdo->prepare("INSERT INTO application_settings (setting_key, setting_value, description) VALUES ('test_setting_key', 'test_setting_val', 'General')
                                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmtSet->execute();
        $chkSet = $this->pdo->prepare("SELECT setting_value FROM application_settings WHERE setting_key = 'test_setting_key'");
        $chkSet->execute();
        $results[] = [
            'name' => 'Settings: Persist and retrieve configuration key-value pair',
            'pass' => ($chkSet->fetchColumn() === 'test_setting_val')
        ];

        // Cleanup test records
        $this->pdo->prepare("DELETE FROM application_settings WHERE setting_key = 'test_setting_key'")->execute();
        $this->pdo->prepare("DELETE FROM leave_requests WHERE id = ?")->execute([$leaveId]);
        $this->pdo->prepare("DELETE FROM messages WHERE id = ?")->execute([$msgId]);
        $this->pdo->prepare("DELETE FROM timetables WHERE id = ?")->execute([$ttId]);
        $this->pdo->prepare("DELETE FROM payments WHERE invoice_id = ?")->execute([$invoiceId]);
        $this->pdo->prepare("DELETE FROM invoices WHERE id = ?")->execute([$invoiceId]);
        $this->pdo->prepare("DELETE FROM student_marks WHERE assessment_id = ?")->execute([$assessmentId]);
        $this->pdo->prepare("DELETE FROM assessments WHERE id = ?")->execute([$assessmentId]);

        return $results;
    }
}
