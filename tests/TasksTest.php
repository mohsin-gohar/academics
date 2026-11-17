<?php
/**
 * Tasks Module & CRUD Tests
 */
class TasksTest {
    private $pdo;
    private $schoolId = 1;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function run() {
        $results = [];

        // Ensure test school and student exist
        $this->ensureTestSchool();

        // 1. Create a Task
        $title = 'Test Task ' . uniqid();
        $ins = $this->pdo->prepare("INSERT INTO tasks (school_id, user_id, student_id, course_id, title, description, priority, status, due_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([$this->schoolId, 1, 1, 1, $title, 'Test task description', 'High', 'Pending', date('Y-m-d', strtotime('+3 days'))]);
        $taskId = (int)$this->pdo->lastInsertId();

        $results[] = [
            'name' => 'Task can be successfully created in database',
            'pass' => ($taskId > 0)
        ];

        // 2. Read / View Task
        $sel = $this->pdo->prepare("SELECT * FROM tasks WHERE id = ?");
        $sel->execute([$taskId]);
        $fetched = $sel->fetch();

        $results[] = [
            'name' => 'Created task can be retrieved with correct attributes',
            'pass' => ($fetched && $fetched['title'] === $title && $fetched['priority'] === 'High')
        ];

        // 3. Status Transition to Completed sets completed_at
        $upd = $this->pdo->prepare("UPDATE tasks SET status = 'Completed', completed_at = NOW() WHERE id = ?");
        $upd->execute([$taskId]);

        $sel->execute([$taskId]);
        $completedTask = $sel->fetch();
        $results[] = [
            'name' => 'Completing a task records completion timestamp',
            'pass' => ($completedTask && $completedTask['status'] === 'Completed' && !empty($completedTask['completed_at']))
        ];

        // 4. Status Transition back to In Progress clears completed_at
        $updReset = $this->pdo->prepare("UPDATE tasks SET status = 'In Progress', completed_at = NULL WHERE id = ?");
        $updReset->execute([$taskId]);
        $sel->execute([$taskId]);
        $resetTask = $sel->fetch();
        $results[] = [
            'name' => 'Moving task back to In Progress clears completed_at',
            'pass' => ($resetTask && $resetTask['status'] === 'In Progress' && empty($resetTask['completed_at']))
        ];

        // 5. Overdue Detection Logic
        $pastDate = date('Y-m-d', strtotime('-5 days'));
        $updPast = $this->pdo->prepare("UPDATE tasks SET due_date = ?, status = 'Pending' WHERE id = ?");
        $updPast->execute([$pastDate, $taskId]);

        $overdueCheck = $this->pdo->prepare("SELECT COUNT(*) FROM tasks WHERE id = ? AND due_date < CURDATE() AND status != 'Completed'");
        $overdueCheck->execute([$taskId]);
        $results[] = [
            'name' => 'Overdue tasks are correctly identified by query logic',
            'pass' => ((int)$overdueCheck->fetchColumn() === 1)
        ];

        // 6. Delete Task
        $del = $this->pdo->prepare("DELETE FROM tasks WHERE id = ?");
        $del->execute([$taskId]);
        $sel->execute([$taskId]);
        $results[] = [
            'name' => 'Task can be deleted and removed from database',
            'pass' => ($sel->fetch() === false)
        ];

        return $results;
    }

    private function ensureTestSchool(): void {
        $stmt = $this->pdo->prepare("SELECT id FROM schools WHERE id = ?");
        $stmt->execute([$this->schoolId]);
        if (!$stmt->fetch()) {
            $this->pdo->prepare("INSERT INTO schools (id, school_name, school_code, school_email, owner_name, status, plan, is_active) VALUES (?, 'Test School', 'TEST001', 'test@school.com', 'Test Admin', 'active', 'pro', 1)")
                ->execute([$this->schoolId]);
        }

        // Ensure test student exists
        $stmt = $this->pdo->prepare("SELECT id FROM students WHERE id = ?");
        $stmt->execute([1]);
        if (!$stmt->fetch()) {
            $this->pdo->prepare("INSERT INTO students (id, school_id, user_id, student_number, full_name, email, enrollment_date, status) VALUES (?, 1, 1, 'STU-TEST-0001', 'Test Student', 'test@student.com', CURDATE(), 'Active')")
                ->execute([1]);
        }

        // Ensure test user exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([1]);
        if (!$stmt->fetch()) {
            $this->pdo->prepare("INSERT INTO users (id, full_name, email, password, role, is_active, school_id) VALUES (?, 'Test User', 'test@user.com', 'password', 'admin', 1, 1)")
                ->execute([1]);
        }

        // Ensure test course exists
        $stmt = $this->pdo->prepare("SELECT id FROM courses WHERE id = ?");
        $stmt->execute([1]);
        if (!$stmt->fetch()) {
            $this->pdo->prepare("INSERT INTO courses (id, school_id, course_code, course_name, credits, status, created_by) VALUES (?, 1, 'TEST101', 'Test Course', 3, 'Active', 1)")
                ->execute([1]);
        }
    }
}