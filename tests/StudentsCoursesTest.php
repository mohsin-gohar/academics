<?php
/**
 * Students, Courses, and Enrollments Integrity Tests
 */
class StudentsCoursesTest {
    private $pdo;
    private $schoolId = 1;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function run() {
        $results = [];

        // Ensure test school exists
        $this->ensureTestSchool();

        // 1. Unique Student Number Generation
        $num1 = generateStudentNumber($this->pdo);
        $results[] = [
            'name' => 'generateStudentNumber returns standard STU-YYYY-XXXX format',
            'pass' => (bool)preg_match('/^STU-\d{4}-\d{4}$/', $num1)
        ];

        // 2. Student CRUD & Unique Constraints
        $testEmail = 'student_test_' . uniqid() . '@edusphere.com';
        $testNum = 'STU-TEST-' . rand(10000, 99999);
        $ins = $this->pdo->prepare("INSERT INTO students (school_id, user_id, student_number, full_name, email, enrollment_date, status) VALUES (?, 2, ?, 'Test Student', ?, CURDATE(), 'Active')");
        $ins->execute([$this->schoolId, $testNum, $testEmail]);
        $stuId = (int)$this->pdo->lastInsertId();

        $results[] = [
            'name' => 'Student record can be created successfully',
            'pass' => ($stuId > 0)
        ];

        // Unique student email constraint check (school-scoped)
        $dupCaught = false;
        try {
            $insDup = $this->pdo->prepare("INSERT INTO students (school_id, user_id, student_number, full_name, email, enrollment_date, status) VALUES (?, 3, 'STU-DIFF', 'Dup Student', ?, CURDATE(), 'Active')");
            $insDup->execute([$this->schoolId, $testEmail]);
        } catch (PDOException $e) {
            $dupCaught = true;
        }
        $results[] = [
            'name' => 'Database enforces UNIQUE constraint on student email (school-scoped)',
            'pass' => $dupCaught
        ];

        // 3. Course CRUD & Unique Code Constraint (school-scoped)
        $testCode = 'TEST' . rand(100, 999);
        $insCourse = $this->pdo->prepare("INSERT INTO courses (school_id, course_code, course_name, credits, status, created_by) VALUES (?, ?, 'Test Course Engineering', 3, 'Active', 1)");
        $insCourse->execute([$this->schoolId, $testCode]);
        $courseId = (int)$this->pdo->lastInsertId();

        $results[] = [
            'name' => 'Course record created with code, credits, and creator',
            'pass' => ($courseId > 0)
        ];

        $dupCourseCodeCaught = false;
        try {
            $insCourseDup = $this->pdo->prepare("INSERT INTO courses (school_id, course_code, course_name, credits, status, created_by) VALUES (?, ?, 'Duplicate Course', 3, 'Active', 1)");
            $insCourseDup->execute([$this->schoolId, $testCode]);
        } catch (PDOException $e) {
            $dupCourseCodeCaught = true;
        }
        $results[] = [
            'name' => 'Database enforces UNIQUE constraint on course code (school-scoped)',
            'pass' => $dupCourseCodeCaught
        ];

        // 4. Enrollment Many-to-Many Relationship
        $insEnr = $this->pdo->prepare("INSERT INTO enrollments (school_id, student_id, course_id, enrollment_date, status) VALUES (?, ?, ?, CURDATE(), 'Enrolled')");
        $insEnr->execute([$this->schoolId, $stuId, $courseId]);
        $enrId = (int)$this->pdo->lastInsertId();

        $results[] = [
            'name' => 'Student successfully enrolled into course',
            'pass' => ($enrId > 0)
        ];

        // Unique enrollment pair constraint
        $dupEnrollCaught = false;
        try {
            $insEnrDup = $this->pdo->prepare("INSERT INTO enrollments (school_id, student_id, course_id, enrollment_date, status) VALUES (?, ?, ?, CURDATE(), 'Enrolled')");
            $insEnrDup->execute([$this->schoolId, $stuId, $courseId]);
        } catch (PDOException $e) {
            $dupEnrollCaught = true;
        }
        $results[] = [
            'name' => 'Database prevents duplicate enrollment of same student in same course',
            'pass' => $dupEnrollCaught
        ];

        // Cleanup test student and course
        $this->pdo->prepare("DELETE FROM enrollments WHERE id = ?")->execute([$enrId]);
        $this->pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$stuId]);
        $this->pdo->prepare("DELETE FROM courses WHERE id = ?")->execute([$courseId]);

        return $results;
    }

    private function ensureTestSchool(): void {
        $stmt = $this->pdo->prepare("SELECT id FROM schools WHERE id = ?");
        $stmt->execute([$this->schoolId]);
        if (!$stmt->fetch()) {
            $this->pdo->prepare("INSERT INTO schools (id, school_name, school_code, school_email, owner_name, status, plan, is_active) VALUES (?, 'Test School', 'TEST001', 'test@school.com', 'Test Admin', 'active', 'pro', 1)")
                ->execute([$this->schoolId]);
        }
    }
}