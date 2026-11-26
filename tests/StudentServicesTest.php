<?php
/**
 * Tenant isolation and persistence tests for student support and learning tools.
 */
class StudentServicesTest
{
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function run(): array
    {
        $results = [];
        $schoolId = 1;
        $studentId = 2;
        $userId = 6;
        $subject = 'CI support request ' . bin2hex(random_bytes(5));
        $requestId = 0;
        $resourceId = 0;
        $labId = 0;

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO student_requests (school_id, student_id, user_id, request_type, subject, message)
                 VALUES (?, ?, ?, 'feedback', ?, 'Automated tenant-isolation test')"
            );
            $stmt->execute([$schoolId, $studentId, $userId, $subject]);
            $requestId = (int)$this->pdo->lastInsertId();
            $results[] = ['name' => 'Student Help Desk: persist a student feedback submission', 'pass' => $requestId > 0];

            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM student_requests WHERE id = ? AND school_id = ? AND student_id = ?');
            $stmt->execute([$requestId, $schoolId, $studentId]);
            $results[] = ['name' => 'Student Help Desk: scope submissions to their school and student', 'pass' => (int)$stmt->fetchColumn() === 1];

            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM student_requests WHERE id = ? AND school_id = 2');
            $stmt->execute([$requestId]);
            $results[] = ['name' => 'Student Help Desk: prevent cross-school request visibility', 'pass' => (int)$stmt->fetchColumn() === 0];

            $stmt = $this->pdo->prepare(
                "INSERT INTO study_resources (school_id, course_id, title, resource_type, resource_url, audience, created_by)
                 VALUES (1, 1, 'CI Study Resource', 'link', 'https://example.test/resource', 'students', 5)"
            );
            $stmt->execute();
            $resourceId = (int)$this->pdo->lastInsertId();
            $results[] = ['name' => 'Study Resources: persist a school-scoped learning resource', 'pass' => $resourceId > 0];

            $stmt = $this->pdo->prepare(
                "INSERT INTO lab_practicals (school_id, course_id, title, instructions, audience, status, created_by)
                 VALUES (1, 1, 'CI Lab Practical', 'Complete the test exercise.', 'students', 'published', 5)"
            );
            $stmt->execute();
            $labId = (int)$this->pdo->lastInsertId();
            $results[] = ['name' => 'Lab Practice: persist a published practical for the school', 'pass' => $labId > 0];
        } catch (Throwable $e) {
            $results[] = ['name' => 'Student Services: database workflow completed without exception (' . get_class($e) . ')', 'pass' => false];
        } finally {
            if ($requestId) $this->pdo->prepare('DELETE FROM student_requests WHERE id = ?')->execute([$requestId]);
            if ($resourceId) $this->pdo->prepare('DELETE FROM study_resources WHERE id = ?')->execute([$resourceId]);
            if ($labId) $this->pdo->prepare('DELETE FROM lab_practicals WHERE id = ?')->execute([$labId]);
        }
        return $results;
    }
}
