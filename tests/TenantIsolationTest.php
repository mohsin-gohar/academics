<?php
/**
 * Cross-school reads must not return another tenant's rows.
 */
class TenantIsolationTest
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function run(): array
    {
        $results = [];
        $marker = 'ISO-' . bin2hex(random_bytes(4));

        $leave = $this->pdo->prepare(
            "INSERT INTO leave_requests (school_id, user_id, leave_type, start_date, end_date, reason, status)
             VALUES (?, ?, 'Sick', CURDATE(), CURDATE(), ?, 'Pending')"
        );
        $leave->execute([2, 9, $marker . '-other']);
        $otherLeave = (int)$this->pdo->lastInsertId();
        $leave->execute([1, 6, $marker . '-own']);
        $ownLeave = (int)$this->pdo->lastInsertId();

        $visible = $this->pdo->prepare(
            "SELECT reason FROM leave_requests lr
             WHERE lr.school_id = ?
               AND EXISTS (
                    SELECT 1 FROM parent_student ps
                    JOIN students st ON st.id = ps.student_id AND st.school_id = ps.school_id
                    WHERE st.user_id = lr.user_id AND ps.parent_id = ? AND ps.school_id = lr.school_id
               )
               AND lr.reason LIKE ?"
        );
        $visible->execute([1, 7, $marker . '%']);
        $reasons = $visible->fetchAll(PDO::FETCH_COLUMN);

        $foreignStudents = $this->pdo->prepare(
            "SELECT COUNT(*) FROM students WHERE school_id = ? AND full_name LIKE ?"
        );
        $foreignStudents->execute([1, '%Second School Student%']);

        $results[] = [
            'name' => 'A parent sees a linked child leave request and not another school',
            'pass' => $reasons === [$marker . '-own'],
        ];
        $results[] = [
            'name' => 'School 1 student search cannot see the school 2 student name',
            'pass' => (int)$foreignStudents->fetchColumn() === 0,
        ];

        $this->pdo->prepare("DELETE FROM leave_requests WHERE id IN (?, ?)")->execute([$otherLeave, $ownLeave]);
        return $results;
    }
}
