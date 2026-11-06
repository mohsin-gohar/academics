<?php
/**
 * Create Timetable Slot
 * EduSphere Enterprise School Management System
 */
$page_title = 'Add Timetable Slot';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';
requireStaff();

$schoolId = currentSchoolId();
$errors = [];
$success = '';

// Fetch active courses
$courseStmt = $pdo->prepare("SELECT id, course_code, course_name FROM courses WHERE (school_id = ? OR school_id IS NULL) AND status = 'Active' ORDER BY course_name");
$courseStmt->execute([$schoolId]);
$courses = $courseStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch teachers
$teacherStmt = $pdo->prepare("SELECT id, full_name FROM users WHERE (school_id = ? OR school_id IS NULL) AND role = 'teacher' AND is_active = 1 ORDER BY full_name");
$teacherStmt->execute([$schoolId]);
$teachers = $teacherStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $course_id = (int)($_POST['course_id'] ?? 0);
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);
        $day_of_week = trim($_POST['day_of_week'] ?? '');
        $start_time = trim($_POST['start_time'] ?? '');
        $end_time = trim($_POST['end_time'] ?? '');
        $room = trim($_POST['room'] ?? '');

        if ($course_id <= 0 || $teacher_id <= 0 || empty($day_of_week) || empty($start_time) || empty($end_time)) {
            $errors[] = 'Please fill in all required fields.';
        } elseif (strtotime($start_time) >= strtotime($end_time)) {
            $errors[] = 'End time must be after start time.';
        } else {
            // Check conflicts for teacher at same day and overlapping time
            $conflictStmt = $pdo->prepare("SELECT COUNT(*) FROM timetables
                WHERE teacher_id = ? AND day_of_week = ? AND status = 'Active'
                AND ((start_time < ? AND end_time > ?) OR (start_time < ? AND end_time > ?))");
            $conflictStmt->execute([$teacher_id, $day_of_week, $end_time, $start_time, $start_time, $end_time]);
            if ($conflictStmt->fetchColumn() > 0) {
                $errors[] = 'Teacher already has a conflicting class scheduled during this time slot.';
            } else {
                $insert = $pdo->prepare("INSERT INTO timetables (school_id, teacher_id, course_id, day_of_week, start_time, end_time, room, status)
                                         VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')");
                $insert->execute([$schoolId, $teacher_id, $course_id, $day_of_week, $start_time, $end_time, $room]);
                redirect('index.php', 'success', 'Timetable slot created successfully.');
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-white">Add Timetable Slot</h1>
            <p class="text-secondary small mb-0">Schedule a new class period for an instructor and course.</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back to Timetable
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?php echo e($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card bg-dark text-white border-secondary">
        <div class="card-body p-4">
            <form method="POST" action="create.php">
                <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Course *</label>
                        <select name="course_id" class="form-select bg-dark text-white border-secondary" required>
                            <option value="">-- Select Course --</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['course_code'] . ' - ' . $c['course_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Teacher / Instructor *</label>
                        <select name="teacher_id" class="form-select bg-dark text-white border-secondary" required>
                            <option value="">-- Select Teacher --</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?php echo (int)$t['id']; ?>"><?php echo e($t['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Day of Week *</label>
                        <select name="day_of_week" class="form-select bg-dark text-white border-secondary" required>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Start Time *</label>
                        <input type="time" name="start_time" class="form-control bg-dark text-white border-secondary" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">End Time *</label>
                        <input type="time" name="end_time" class="form-control bg-dark text-white border-secondary" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Room / Hall</label>
                        <input type="text" name="room" class="form-control bg-dark text-white border-secondary" placeholder="e.g. Room 204 or Lab 3">
                    </div>
                </div>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save Schedule Slot
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
