<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../config/db.php';

requireStaff();
requirePost();

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    header('Location: index.php?error=' . urlencode('Invalid security token.'));
    exit;
}

$courseId = (int)($_POST['course_id'] ?? 0);
$studentId = (int)($_POST['student_id'] ?? 0);
$enrollmentDate = trim((string)($_POST['enrollment_date'] ?? date('Y-m-d')));
if ($courseId <= 0 || $studentId <= 0 || !isValidIsoDate($enrollmentDate)) {
    header('Location: index.php?error=' . urlencode('Enter a valid course, student and enrollment date.'));
    exit;
}

try {
    $pdo->beginTransaction();

    $courseStmt = $pdo->prepare("SELECT id, school_id, status FROM courses WHERE id = ? FOR UPDATE");
    $courseStmt->execute([$courseId]);
    $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
    $studentStmt = $pdo->prepare("SELECT id, school_id, status FROM students WHERE id = ? FOR UPDATE");
    $studentStmt->execute([$studentId]);
    $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$course || !$student) {
        throw new InvalidArgumentException('Course or student was not found.');
    }
    if (!can_access_school((int)$course['school_id']) || !can_access_school((int)$student['school_id'])) {
        $pdo->rollBack();
        http_response_code(403);
        header('Location: ../errors/403.php');
        exit;
    }
    if ((int)$course['school_id'] !== (int)$student['school_id']) {
        throw new InvalidArgumentException('That student belongs to another school.');
    }
    if ($course['status'] !== 'Active' || $student['status'] !== 'Active') {
        throw new InvalidArgumentException('Only active courses and active students can be enrolled.');
    }

    $existingStmt = $pdo->prepare(
        "SELECT id, status, grade FROM enrollments
         WHERE school_id = ? AND student_id = ? AND course_id = ? FOR UPDATE"
    );
    $existingStmt->execute([(int)$course['school_id'], $studentId, $courseId]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing && $existing['status'] === 'Enrolled') {
        throw new InvalidArgumentException('Student is already enrolled in this course.');
    }
    if ($existing && !in_array($existing['status'], ['Dropped', 'Withdrawn'], true)) {
        throw new InvalidArgumentException('This enrollment is closed and cannot be reactivated.');
    }

    if ($existing) {
        $marksStmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM student_marks sm
             JOIN assessments a ON a.id = sm.assessment_id AND a.school_id = sm.school_id
             WHERE sm.school_id = ? AND sm.student_id = ? AND a.course_id = ?"
        );
        $marksStmt->execute([(int)$course['school_id'], $studentId, $courseId]);
        if ((int)$marksStmt->fetchColumn() > 0 || !empty($existing['grade'])) {
            throw new InvalidArgumentException('This student has recorded academic results for this course. Create a separate course offering for a retake.');
        }

        $update = $pdo->prepare(
            "UPDATE enrollments SET status = 'Enrolled', enrollment_date = ?, updated_at = NOW()
             WHERE id = ? AND school_id = ?"
        );
        $update->execute([$enrollmentDate, (int)$existing['id'], (int)$course['school_id']]);
        $enrollmentId = (int)$existing['id'];
        $activity = 'Re-enrolled student ID ' . $studentId;
    } else {
        $insert = $pdo->prepare(
            "INSERT INTO enrollments (school_id, student_id, course_id, enrollment_date, status)
             VALUES (?, ?, ?, ?, 'Enrolled')"
        );
        $insert->execute([(int)$course['school_id'], $studentId, $courseId, $enrollmentDate]);
        $enrollmentId = (int)$pdo->lastInsertId();
        $activity = 'Enrolled student ID ' . $studentId;
    }

    $pdo->commit();
    logActivity($pdo, (int)$_SESSION['user_id'], 'enroll', 'course', $courseId, $activity);
    header('Location: show.php?id=' . $courseId . '&success=' . urlencode('Student enrolled successfully.'));
    exit;
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    header('Location: show.php?id=' . $courseId . '&error=' . urlencode($e->getMessage()));
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Course enrollment failed: ' . $e->getMessage());
    header('Location: show.php?id=' . $courseId . '&error=' . urlencode('Failed to enroll student.'));
    exit;
}
