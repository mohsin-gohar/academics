<?php
require_once '../includes/functions.php';
require_once '../includes/tenant.php';
require_once '../config/db.php';
requireSchoolManagement();
requirePost();

$token = $_POST['csrf_token'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0 || !validateCsrfToken($token)) {
    header('Location: index.php?error=' . urlencode('Invalid or missing security token.'));
    exit;
}

try {
    $pdo->beginTransaction();

    $guard = $pdo->prepare("SELECT id, school_id FROM courses WHERE id = ? FOR UPDATE");
    $guard->execute([$id]);
    $course = $guard->fetch(PDO::FETCH_ASSOC);
    if (!$course) {
        $pdo->rollBack();
        header('Location: index.php?error=' . urlencode('Course not found.'));
        exit;
    }
    require_record_school($course);

    // Course deletion cascades to assessments, marks, enrollments and
    // timetable rows. Preserve academic history: archive/deactivate a course
    // once it has ever been used instead of deleting those records.
    $historyChecks = [
        ["SELECT COUNT(*) FROM enrollments WHERE course_id = ?", [$id]],
        ["SELECT COUNT(*) FROM assessments WHERE course_id = ?", [$id]],
        ["SELECT COUNT(*) FROM timetables WHERE course_id = ?", [$id]],
    ];
    foreach ($historyChecks as [$sql, $params]) {
        $check = $pdo->prepare($sql);
        $check->execute($params);
        if ((int)$check->fetchColumn() > 0) {
            $pdo->rollBack();
            header('Location: index.php?error=' . urlencode('This course has enrollment, grade or timetable history. Change its status to Archived instead of permanently deleting it.'));
            exit;
        }
    }

    // Keep tasks but remove their optional reference to this course.
    $pdo->prepare("UPDATE tasks SET course_id = NULL WHERE course_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM courses WHERE id = ? AND school_id <=> ?")->execute([$id, $course['school_id']]);
    $pdo->commit();

    logActivity($pdo, (int)$_SESSION['user_id'], 'delete', 'course', $id, 'Deleted course');
    header('Location: index.php?success=' . urlencode('Course deleted successfully.'));
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Course delete failed: " . $e->getMessage());
    header('Location: index.php?error=' . urlencode('Failed to delete course.'));
    exit;
}
