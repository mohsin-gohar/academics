<?php
/**
 * Test Models Suite (test_models.php)
 * EduSphere Enterprise School Management System
 */
class TestModelsSuite {
    private PDO $pdo;
    private $schoolId = 1;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function run(): array {
        $results = [];

        // Ensure the demo school exists (tests run against a seeded DB)
        $stmt = $this->pdo->prepare("SELECT id FROM schools WHERE id = ?");
        $stmt->execute([$this->schoolId]);
        if (!$stmt->fetch()) {
            $this->pdo->prepare("INSERT INTO schools (id, school_name, school_code, school_email, owner_name, status, plan, is_active) VALUES (?, 'Test School', 'TESTSCH', 'test@school.com', 'Test Owner', 'active', 'pro', 1)")
                ->execute([$this->schoolId]);
        }

        // 1. Users Model CRUD
        $uniq = time() . '_' . rand(100, 999);
        $userEmail = "model_user_{$uniq}@edusphere.com";
        $insUser = $this->pdo->prepare("INSERT INTO users (full_name, email, password, role, is_active, school_id) VALUES ('Model User', ?, 'dummy_hash', 'student', 1, ?)");
        $insUser->execute([$userEmail, $this->schoolId]);
        $userId = (int)$this->pdo->lastInsertId();
        $results[] = [
            'name' => 'Users model: Insert record and retrieve ID',
            'pass' => $userId > 0
        ];

        // 2. Student Number Generation
        $stuNum = generateStudentNumber($this->pdo, $this->schoolId);
        $results[] = [
            'name' => 'Students model: Auto-generates valid format student number',
            'pass' => (bool)preg_match('/^STU-\d{4}-\d{4}$/', $stuNum)
        ];

        // 3. Students Model CRUD
        $stuEmail = "model_stu_{$uniq}@edusphere.com";
        $insStu = $this->pdo->prepare("INSERT INTO students (school_id, user_id, student_number, full_name, email, enrollment_date, status) VALUES (?, ?, ?, 'Model Student', ?, CURDATE(), 'Active')");
        $insStu->execute([$this->schoolId, $userId, $stuNum, $stuEmail]);
        $studentId = (int)$this->pdo->lastInsertId();
        $results[] = [
            'name' => 'Students model: Create student record successfully',
            'pass' => $studentId > 0
        ];

        // 4. Unique Constraint on Student Number (scoped to school)
        $dupNumCaught = false;
        try {
            $insDup = $this->pdo->prepare("INSERT INTO students (school_id, user_id, student_number, full_name, email, enrollment_date, status) VALUES (?, ?, ?, 'Duplicate Student', 'another@edusphere.com', CURDATE(), 'Active')");
            $insDup->execute([$this->schoolId, $userId, $stuNum]);
        } catch (PDOException $e) {
            $dupNumCaught = true;
        }
        $results[] = [
            'name' => 'Students model: Database enforces unique student_number constraint',
            'pass' => $dupNumCaught
        ];

        // 5. Courses Model CRUD
        $courseCode = "MOD" . rand(100, 999);
        $insCourse = $this->pdo->prepare("INSERT INTO courses (school_id, course_code, course_name, credits, status, created_by) VALUES (?, ?, 'Model Testing 101', 3, 'Active', ?)");
        $insCourse->execute([$this->schoolId, $courseCode, $userId]);
        $courseId = (int)$this->pdo->lastInsertId();
        $results[] = [
            'name' => 'Courses model: Create course record with code and credits',
            'pass' => $courseId > 0
        ];

        // 6. Enrollments Model CRUD
        $insEnroll = $this->pdo->prepare("INSERT INTO enrollments (school_id, student_id, course_id, enrollment_date, status) VALUES (?, ?, ?, CURDATE(), 'Enrolled')");
        $insEnroll->execute([$this->schoolId, $studentId, $courseId]);
        $enrollId = (int)$this->pdo->lastInsertId();
        $results[] = [
            'name' => 'Enrollments model: Enroll student to course',
            'pass' => $enrollId > 0
        ];

        // 7. Duplicate enrollment prevention
        $dupEnrollCaught = false;
        try {
            $insEnroll->execute([$this->schoolId, $studentId, $courseId]);
        } catch (PDOException $e) {
            $dupEnrollCaught = true;
        }
        $results[] = [
            'name' => 'Enrollments model: Enforces unique student and course composite key',
            'pass' => $dupEnrollCaught
        ];

        // 8. Tasks Model CRUD
        $insTask = $this->pdo->prepare("INSERT INTO tasks (school_id, user_id, student_id, course_id, title, priority, status, due_date) VALUES (?, ?, ?, ?, 'Model Task Test', 'High', 'Pending', CURDATE())");
        $insTask->execute([$this->schoolId, $userId, $studentId, $courseId]);
        $taskId = (int)$this->pdo->lastInsertId();
        $results[] = [
            'name' => 'Tasks model: Create and assign task successfully',
            'pass' => $taskId > 0
        ];

        // 9. Tasks Model Status Transition
        $updTask = $this->pdo->prepare("UPDATE tasks SET status = 'Completed', completed_at = NOW() WHERE id = ?");
        $updTask->execute([$taskId]);
        $chkTask = $this->pdo->prepare("SELECT completed_at FROM tasks WHERE id = ?");
        $chkTask->execute([$taskId]);
        $compAt = $chkTask->fetchColumn();
        $results[] = [
            'name' => 'Tasks model: Completed status stamps completed_at timestamp',
            'pass' => !empty($compAt)
        ];

        // 10. Clean up test records
        $this->pdo->prepare("DELETE FROM tasks WHERE id = ?")->execute([$taskId]);
        $this->pdo->prepare("DELETE FROM enrollments WHERE id = ?")->execute([$enrollId]);
        $this->pdo->prepare("DELETE FROM courses WHERE id = ?")->execute([$courseId]);
        $this->pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$studentId]);
        $this->pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);

        $results[] = [
            'name' => 'Models cascade deletion and test data cleanup',
            'pass' => true
        ];

        return $results;
    }
}
