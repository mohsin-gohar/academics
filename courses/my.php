<?php
/**
 * Student-only course list. Enrollment is the source of truth: students never
 * see every course offered by their school, only courses linked to their own
 * student record.
 */
$page_title = 'My Courses';
require_once __DIR__ . '/../includes/header.php';
requireStudent();

$schoolId = (int) (currentSchoolId() ?? 0);
$userId = (int) ($_SESSION['user_id'] ?? 0);

$studentStmt = $pdo->prepare(
    'SELECT id, school_id, full_name, student_number
     FROM students
     WHERE user_id = ? AND school_id = ?
     LIMIT 1'
);
$studentStmt->execute([$userId, $schoolId]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);

if (!$student || $schoolId <= 0) {
    http_response_code(404);
    echo '<div class="alert alert-warning m-4">Your student record could not be found. Please contact your school administrator.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$courseStmt = $pdo->prepare(
    "SELECT c.id, c.course_code, c.course_name, c.description, c.credits,
            c.schedule, c.start_date, c.end_date, c.status AS course_status,
            e.status AS enrollment_status, e.enrollment_date, e.grade,
            u.full_name AS instructor_name
     FROM enrollments e
     INNER JOIN courses c
        ON c.id = e.course_id AND c.school_id = e.school_id
     LEFT JOIN users u ON u.id = c.instructor_id
     WHERE e.school_id = ? AND e.student_id = ?
       AND e.status IN ('Enrolled', 'Completed')
     ORDER BY CASE WHEN e.status = 'Enrolled' THEN 0 ELSE 1 END,
              c.start_date DESC, c.course_name ASC"
);
$courseStmt->execute([$schoolId, (int) $student['id']]);
$courses = $courseStmt->fetchAll(PDO::FETCH_ASSOC);

$activeCount = 0;
$completedCount = 0;
foreach ($courses as $course) {
    if ($course['enrollment_status'] === 'Enrolled') {
        $activeCount++;
    } elseif ($course['enrollment_status'] === 'Completed') {
        $completedCount++;
    }
}
?>

<div class="container-fluid px-3 px-lg-4 py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h2 class="page-title mb-1"><i class="fas fa-book-open me-2 text-primary"></i>My Courses</h2>
            <p class="text-muted mb-0 small">Courses assigned to your student account.</p>
        </div>
        <span class="badge bg-light text-dark border"><?php echo e($student['student_number']); ?></span>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Total courses</div>
                <div class="fs-3 fw-bold"><?php echo count($courses); ?></div>
            </div></div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">In progress</div>
                <div class="fs-3 fw-bold text-primary"><?php echo $activeCount; ?></div>
            </div></div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Completed</div>
                <div class="fs-3 fw-bold text-success"><?php echo $completedCount; ?></div>
            </div></div>
        </div>
    </div>

    <?php if (!$courses): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-book-open fa-3x text-muted opacity-50 mb-3"></i>
                <h5>No courses assigned yet</h5>
                <p class="text-muted mb-0">Your enrolled courses will appear here once your school adds your enrollment.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($courses as $course): ?>
                <?php $isCompleted = $course['enrollment_status'] === 'Completed'; ?>
                <div class="col-12 col-lg-6">
                    <article class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div>
                                    <div class="small text-muted"><?php echo e($course['course_code']); ?></div>
                                    <h5 class="mb-1"><?php echo e($course['course_name']); ?></h5>
                                </div>
                                <span class="badge <?php echo $isCompleted ? 'bg-success' : 'bg-primary'; ?>">
                                    <?php echo e($course['enrollment_status']); ?>
                                </span>
                            </div>
                            <?php if (!empty($course['description'])): ?>
                                <p class="text-muted small mb-3"><?php echo nl2br(e($course['description'])); ?></p>
                            <?php endif; ?>
                            <div class="small d-grid gap-2">
                                <?php if (!empty($course['instructor_name'])): ?>
                                    <div><i class="fas fa-chalkboard-teacher text-muted me-2"></i><?php echo e($course['instructor_name']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($course['schedule'])): ?>
                                    <div><i class="fas fa-clock text-muted me-2"></i><?php echo e($course['schedule']); ?></div>
                                <?php endif; ?>
                                <div><i class="fas fa-calendar-alt text-muted me-2"></i>
                                    <?php echo !empty($course['start_date']) ? e(date('d M Y', strtotime($course['start_date']))) : 'Start date not set'; ?>
                                    <?php if (!empty($course['end_date'])): ?> — <?php echo e(date('d M Y', strtotime($course['end_date']))); ?><?php endif; ?>
                                </div>
                                <div><i class="fas fa-award text-muted me-2"></i><?php echo (int) $course['credits']; ?> credits
                                    <?php if ($course['grade'] !== null && $course['grade'] !== ''): ?> · Grade: <?php echo e($course['grade']); ?><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
