<?php
/**
 * Students, Courses, and Enrollments Integrity Tests
 */
class StudentsCoursesTest {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function run() {
        $results = [];

        // 1. Unique Student Number Generation
        $num1 = generateStudentNumber($this->pdo);
        $results[] = [
            'name' => 'generateStudentNumber returns standard STU-YYYY-XXXX format',
            'pass' => (bool)preg_match('/^STU-\d{4}-\d{4}$/', $num1)
        ];

        // 2. Student CRUD & Unique Constraints
        $testEmail = 'student_test_' . uniqid() . '@edusphere.com';
        $testNum = 'STU-TEST-' . rand(10000, 99999);
        $ins = $this->pdo->prepare("INSERT INTO students (user_id, student_number, full_name, email, enrollment_date, status) VALUES (1, ?, 'Test Student', ?, CURDATE(), 'Active')");
        $ins->execute([$testNum, $testEmail]);
        $stuId = (int)$this->pdo->lastInsertId();

        $results[] = [
            'name' => 'Student record can be created successfully',
            'pass' => ($stuId > 0)
        ];

        // Unique student email constraint check
        $dupCaught = false;
        try {
            $insDup = $this->pdo->prepare("INSERT INTO students (user_id, student_number, full_name, email, enrollment_date, status) VALUES (1, 'STU-DIFF', 'Dup Student', ?, CURDATE(), 'Active')");
            $insDup->execute([$testEmail]);
        } catch (PDOException $e) {
            $dupCaught = true;
        }
        $results[] = [
            'name' => 'Database enforces UNIQUE constraint on student email',
            'pass' => $dupCaught
        ];

        // 3. Course CRUD & Unique Code Constraint
        $testCode = 'TEST' . rand(100, 999);
        $insCourse = $this->pdo->prepare("INSERT INTO courses (course_code, course_name, credits, status, created_by) VALUES (?, 'Test Course Engineering', 3, 'Active', 1)");
        $insCourse->execute([$testCode]);
        $courseId = (int)$this->pdo->lastInsertId();

        $results[] = [
            'name' => 'Course record created with code, credits, and creator',
            'pass' => ($courseId > 0)
        ];

        $dupCourseCodeCaught = false;
        try {
            $insCourseDup = $this->pdo->prepare("INSERT INTO courses (course_code, course_name, credits, status, created_by) VALUES (?, 'Duplicate Course', 3, 'Active', 1)");
            $insCourseDup->execute([$testCode]);
        } catch (PDOException $e) {
            $dupCourseCodeCaught = true;
        }
        $results[] = [
            'name' => 'Database enforces UNIQUE constraint on course code',
            'pass' => $dupCourseCodeCaught
        ];

        // 4. Enrollment Many-to-Many Relationship
        $insEnr = $this->pdo->prepare("INSERT INTO enrollments (student_id, course_id, enrollment_date, status) VALUES (?, ?, CURDATE(), 'Enrolled')");
        $insEnr->execute([$stuId, $courseId]);
        $enrId = (int)$this->pdo->lastInsertId();

        $results[] = [
            'name' => 'Student successfully enrolled into course',
            'pass' => ($enrId > 0)
        ];

        // Unique enrollment pair constraint
        $dupEnrollCaught = false;
        try {
            $insEnrDup = $this->pdo->prepare("INSERT INTO enrollments (student_id, course_id, enrollment_date, status) VALUES (?, ?, CURDATE(), 'Enrolled')");
            $insEnrDup->execute([$stuId, $courseId]);
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
}
