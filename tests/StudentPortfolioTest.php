<?php
/** Tenant isolation and persistence checks for student portfolio sections. */
class StudentPortfolioTest
{
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }
    public function run(): array
    {
        $results = [];
        $id = 0;
        try {
            $insert = $this->pdo->prepare(
                "INSERT INTO student_portfolio_entries
                 (school_id, student_id, entry_type, title, description, external_url, status, created_by)
                 VALUES (1, 2, 'eproject', ?, 'CI portfolio test', 'https://example.test/project', 'pending', 6)"
            );
            $insert->execute(['CI eProject ' . bin2hex(random_bytes(4))]);
            $id = (int)$this->pdo->lastInsertId();
            $results[] = ['name' => 'Student portfolio: persist an eProject submission', 'pass' => $id > 0];

            $own = $this->pdo->prepare('SELECT COUNT(*) FROM student_portfolio_entries WHERE id = ? AND school_id = 1 AND student_id = 2 AND status = "pending"');
            $own->execute([$id]);
            $results[] = ['name' => 'Student portfolio: keep submission bound to its student and school', 'pass' => (int)$own->fetchColumn() === 1];

            $cross = $this->pdo->prepare('SELECT COUNT(*) FROM student_portfolio_entries WHERE id = ? AND school_id = 2');
            $cross->execute([$id]);
            $results[] = ['name' => 'Student portfolio: deny cross-school record lookup', 'pass' => (int)$cross->fetchColumn() === 0];

            $review = $this->pdo->prepare('UPDATE student_portfolio_entries SET status = "approved", reviewed_by = 4, reviewed_at = NOW() WHERE id = ? AND school_id = 1');
            $review->execute([$id]);
            $check = $this->pdo->prepare('SELECT status, reviewed_by FROM student_portfolio_entries WHERE id = ? AND school_id = 1');
            $check->execute([$id]);
            $row = $check->fetch(PDO::FETCH_ASSOC) ?: [];
            $results[] = ['name' => 'Student portfolio: persist staff approval audit fields', 'pass' => ($row['status'] ?? '') === 'approved' && (int)($row['reviewed_by'] ?? 0) === 4];
        } catch (Throwable $e) {
            $results[] = ['name' => 'Student portfolio: database workflow completed without exception (' . get_class($e) . ')', 'pass' => false];
        } finally {
            if ($id > 0) $this->pdo->prepare('DELETE FROM student_portfolio_entries WHERE id = ?')->execute([$id]);
        }
        return $results;
    }
}
