<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../config/db.php';

requireStaff();
requirePost();

$token = $_POST['csrf_token'] ?? '';
$id = (int)($_POST['id'] ?? 0);
$courseId = (int)($_POST['course'] ?? 0);
if ($id <= 0 || !validateCsrfToken($token)) {
    header('Location: ' . ($courseId > 0 ? 'show.php?id=' . $courseId : 'index.php') . '&error=' . urlencode('Invalid security token.'));
    exit;
}

try {
    $pdo->beginTransaction();
    $guard = $pdo->prepare(
        "SELECT id, school_id, student_id, course_id, status
         FROM enrollments WHERE id = ? FOR UPDATE"
    );
    $guard->execute([$id]);
    $enrollment = $guard->fetch(PDO::FETCH_ASSOC);
    if (!$enrollment) {
        $pdo->rollBack();
        header('Location: ' . ($courseId > 0 ? 'show.php?id=' . $courseId : 'index.php') . '&error=' . urlencode('Enrollment not found.'));
        exit;
    }
    if (!can_access_school((int)$enrollment['school_id'])) {
        $pdo->rollBack();
        http_response_code(403);
        header('Location: ../errors/403.php');
        exit;
    }

    $courseId = (int)$enrollment['course_id'];
    if ($enrollment['status'] !== 'Enrolled') {
        $pdo->rollBack();
        header('Location: show.php?id=' . $courseId . '&error=' . urlencode('This enrollment is already closed.'));
        exit;
    }

    $marksStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM student_marks sm
         JOIN assessments a ON a.id = sm.assessment_id AND a.school_id = sm.school_id
         WHERE sm.school_id = ? AND sm.student_id = ? AND a.course_id = ?"
    );
    $marksStmt->execute([(int)$enrollment['school_id'], (int)$enrollment['student_id'], $courseId]);
    $hasMarks = (int)$marksStmt->fetchColumn() > 0;

    if ($hasMarks) {
        // Retain the enrollment row so existing marks remain visible in
        // transcripts and audit history.
        $pdo->prepare(
            "UPDATE enrollments SET status = 'Dropped', updated_at = NOW()
             WHERE id = ? AND school_id = ? AND status = 'Enrolled'"
        )->execute([$id, (int)$enrollment['school_id']]);
        $message = 'Student withdrawn. Existing academic results have been preserved.';
    } else {
        $pdo->prepare("DELETE FROM enrollments WHERE id = ? AND school_id = ?")->execute([$id, (int)$enrollment['school_id']]);
        $message = 'Student unenrolled.';
    }

    $pdo->commit();
    logActivity($pdo, (int)$_SESSION['user_id'], 'unenroll', 'course', $courseId, 'Unenrolled student ID ' . (int)$enrollment['student_id']);
    header('Location: show.php?id=' . $courseId . '&success=' . urlencode($message));
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Course unenrollment failed: ' . $e->getMessage());
    header('Location: ' . ($courseId > 0 ? 'show.php?id=' . $courseId : 'index.php') . '&error=' . urlencode('Failed to unenroll student.'));
    exit;
}
