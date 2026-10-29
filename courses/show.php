<?php
$page_title = 'Course Details';
require_once '../includes/header.php';
requireAuth();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT c.*, u.full_name as instructor_name FROM courses c LEFT JOIN users u ON c.instructor_id = u.id WHERE c.id = ?");
$stmt->execute([$id]);
$course = $stmt->fetch();
if (!$course) { header('Location: index.php?error=' . urlencode('Course not found.')); exit; }
require_record_school($course);

// Enrolled students
$stmt = $pdo->prepare("SELECT e.*, s.full_name, s.student_number FROM enrollments e JOIN students s ON e.student_id = s.id WHERE e.course_id = ? ORDER BY e.enrollment_date DESC");
$stmt->execute([$id]);
$enrollments = $stmt->fetchAll();

// Tasks for this course
$stmt = $pdo->prepare("SELECT t.*, s.full_name as student_name FROM tasks t LEFT JOIN students s ON t.student_id = s.id WHERE t.course_id = ? ORDER BY t.created_at DESC");
$stmt->execute([$id]);
$tasks = $stmt->fetchAll();

// Available students to enroll (same school only)
$studentSql = "SELECT id, full_name, student_number FROM students WHERE status = 'Active'";
$studentParams = [];
if (!isSuperAdmin()) {
    $studentSql .= " AND school_id = ?";
    $studentParams[] = currentSchoolId();
}
$studentSql .= " ORDER BY full_name";
$stmt = $pdo->prepare($studentSql);
$stmt->execute($studentParams);
$availableStudents = $stmt->fetchAll();
$enrolledIds = array_column($enrollments, 'student_id');
?>

<div class="row mb-3">
    <div class="col-12"><a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Back to Courses</a></div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h4 class="mb-1"><?php echo e($course['course_name']); ?></h4>
                <p class="text-muted mb-2"><code><?php echo e($course['course_code']); ?></code></p>
                <span class="badge bg-<?php echo $course['status'] === 'Active' ? 'success' : 'secondary'; ?> mb-3"><?php echo e($course['status']); ?></span>
                <hr>
                <p class="mb-1"><strong>Credits:</strong> <?php echo $course['credits'] ?? 3; ?></p>
                <p class="mb-1"><strong>Instructor:</strong> <?php echo e($course['instructor_name'] ?: '-'); ?></p>
                <p class="mb-1"><strong>Schedule:</strong> <?php echo e($course['schedule'] ?: '-'); ?></p>
                <?php if ($course['description']): ?>
                <p class="mb-1"><strong>Description:</strong> <?php echo e($course['description']); ?></p>
                <?php endif; ?>
                <?php if (isStaff()): ?>
                <hr>
                <div class="d-flex gap-2">
                    <a href="edit.php?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm"><i class="fas fa-edit me-1"></i>Edit</a>
                    <a href="delete.php?id=<?php echo $course['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this course?')"><i class="fas fa-trash me-1"></i>Delete</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <?php if (isStaff()): ?>
        <!-- Enroll Student Form -->
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-user-plus me-2 text-primary"></i>Enroll Student</div>
            <div class="card-body">
                <form method="POST" action="enroll.php" class="row g-2">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                    <div class="col-md-7">
                        <select name="student_id" class="form-select" required>
                            <option value="">Select student...</option>
                            <?php foreach ($availableStudents as $s): ?>
                                <?php if (!in_array($s['id'], $enrolledIds)): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo e($s['student_number'] . ' - ' . $s['full_name']); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="enrollment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus"></i></button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-users me-2 text-primary"></i>Enrolled Students (<?php echo count($enrollments); ?>)</div>
            <div class="card-body p-0">
                <?php if (empty($enrollments)): ?>
                    <div class="empty-state"><i class="fas fa-users"></i><h5>No students enrolled</h5></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Student</th><th>Enrolled</th><th>Status</th><th>Grade</th><?php if (isStaff()): ?><th>Action</th><?php endif; ?></tr></thead>
                            <tbody>
                                <?php foreach ($enrollments as $e): ?>
                                <tr>
                                    <td>
                                        <?php if (isStaff() || (isset($_SESSION['student_id']) && (int)$e['student_id'] === (int)$_SESSION['student_id'])): ?>
                                            <a href="../students/show.php?id=<?php echo $e['student_id']; ?>" class="text-decoration-none fw-medium"><?php echo e($e['student_number'] . ' - ' . $e['full_name']); ?></a>
                                        <?php else: ?>
                                            <span><?php echo e($e['student_number'] . ' - ' . $e['full_name']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatDate($e['enrollment_date']); ?></td>
                                    <td><span class="badge bg-<?php echo $e['status'] === 'Enrolled' ? 'primary' : ($e['status'] === 'Completed' ? 'success' : 'secondary'); ?>"><?php echo e($e['status']); ?></span></td>
                                    <td><?php echo e($e['grade'] ?: '-'); ?></td>
                                    <?php if (isStaff()): ?>
                                    <td>
                                        <a href="unenroll.php?id=<?php echo $e['id']; ?>&course=<?php echo $course['id']; ?>&csrf_token=<?php echo generateCsrfToken(); ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Unenroll this student?')"><i class="fas fa-times"></i></a>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header"><i class="fas fa-tasks me-2 text-primary"></i>Course Tasks (<?php echo count($tasks); ?>)</div>
            <div class="card-body p-0">
                <?php if (empty($tasks)): ?>
                    <div class="empty-state"><i class="fas fa-clipboard-list"></i><h5>No tasks</h5></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Title</th><th>Student</th><th>Status</th><th>Due</th></tr></thead>
                            <tbody>
                                <?php foreach ($tasks as $t): ?>
                                <tr>
                                    <td><?php echo e($t['title']); ?></td>
                                    <td><?php echo e($t['student_name'] ?: '-'); ?></td>
                                    <td><span class="badge bg-<?php echo $t['status'] === 'Completed' ? 'success' : ($t['status'] === 'In Progress' ? 'warning' : 'secondary'); ?>"><?php echo e($t['status']); ?></span></td>
                                    <td><?php echo $t['due_date'] ? formatDate($t['due_date']) : '-'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
