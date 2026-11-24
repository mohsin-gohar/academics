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
// C4: validate live session (account/role/school)
enforceSession($pdo);

$schoolId = currentSchoolId();
if (!$schoolId) { header('Location: ../dashboard.php'); exit; }
$errors = [];
$success = '';

// Fetch active courses (strictly scoped to this school)
$courseStmt = $pdo->prepare("SELECT id, course_code, course_name FROM courses WHERE school_id = ? AND status = 'Active' ORDER BY course_name");
$courseStmt->execute([$schoolId]);
$courses = $courseStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch teachers (strictly scoped to this school)
$teacherStmt = $pdo->prepare("SELECT id, full_name FROM users WHERE school_id = ? AND role = 'teacher' AND is_active = 1 ORDER BY full_name");
$teacherStmt->execute([$schoolId]);
$teachers = $teacherStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $course_id = (int)($_POST['course_id'] ?? 0);
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);
        $day_of_week = trim((string)($_POST['day_of_week'] ?? ''));
        $start_time = trim((string)($_POST['start_time'] ?? ''));
        $end_time = trim((string)($_POST['end_time'] ?? ''));
        $room = trim((string)($_POST['room'] ?? ''));
        $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        if ($course_id <= 0 || $teacher_id <= 0 || $start_time === '' || $end_time === '') {
            $errors[] = 'Please fill in all required fields.';
        }
        if (!in_array($day_of_week, $validDays, true)) {
            $errors[] = 'Select a valid day of the week.';
        }
        if (!preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $start_time)
            || !preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $end_time)
            || $start_time >= $end_time) {
            $errors[] = 'Enter valid times and ensure the end time is after the start time.';
        }
        if (mb_strlen($room) > 50) {
            $errors[] = 'Room name cannot exceed 50 characters.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Lock and validate both linked records inside the transaction.
                $courseCheck = $pdo->prepare(
                    "SELECT id FROM courses
                     WHERE id = ? AND school_id = ? AND status = 'Active'
                     FOR UPDATE"
                );
                $courseCheck->execute([$course_id, $schoolId]);
                if (!$courseCheck->fetchColumn()) {
                    throw new InvalidArgumentException('Selected course is not active in this school.');
                }

                // Locking the teacher row serializes concurrent schedule writes
                // for this teacher, preventing overlapping slots from racing.
                $teacherCheck = $pdo->prepare(
                    "SELECT id FROM users
                     WHERE id = ? AND school_id = ? AND role = 'teacher' AND is_active = 1
                     FOR UPDATE"
                );
                $teacherCheck->execute([$teacher_id, $schoolId]);
                if (!$teacherCheck->fetchColumn()) {
                    throw new InvalidArgumentException('Selected teacher is not active in this school.');
                }

                $conflictStmt = $pdo->prepare(
                    "SELECT id FROM timetables
                     WHERE school_id = ? AND teacher_id = ? AND day_of_week = ? AND status = 'Active'
                       AND start_time < ? AND end_time > ?
                     LIMIT 1"
                );
                $conflictStmt->execute([$schoolId, $teacher_id, $day_of_week, $end_time, $start_time]);
                if ($conflictStmt->fetchColumn()) {
                    throw new InvalidArgumentException('Teacher already has a conflicting class scheduled during this time slot.');
                }

                $insert = $pdo->prepare(
                    "INSERT INTO timetables (school_id, teacher_id, course_id, day_of_week, start_time, end_time, room, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')"
                );
                $insert->execute([$schoolId, $teacher_id, $course_id, $day_of_week, $start_time, $end_time, $room !== '' ? $room : null]);
                $pdo->commit();
                redirect('index.php', 'success', 'Timetable slot created successfully.');
            } catch (InvalidArgumentException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = $e->getMessage();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Timetable creation failed: ' . $e->getMessage());
                $errors[] = 'Timetable slot could not be saved. Please try again.';
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
