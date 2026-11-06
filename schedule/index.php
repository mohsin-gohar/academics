<?php
/**
 * Timetable & Class Scheduling Module
 * EduSphere Enterprise School Management System
 */
$page_title = 'Class Timetable';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';
requireAuth();

$schoolId = currentSchoolId();
$userRole = getUserRole();
$userId = $_SESSION['user_id'] ?? 0;

// Days of the week
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Fetch timetable entries
$sql = "SELECT t.*, c.course_name, c.course_code, u.full_name as teacher_name
        FROM timetables t
        JOIN courses c ON t.course_id = c.id
        JOIN users u ON t.teacher_id = u.id
        WHERE (t.school_id = ? OR t.school_id IS NULL)
        ORDER BY t.day_of_week, t.start_time";
$stmt = $pdo->prepare($sql);
$stmt->execute([$schoolId]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by day
$scheduleByDay = [];
foreach ($days as $d) {
    $scheduleByDay[$d] = [];
}
foreach ($entries as $e) {
    if (isset($scheduleByDay[$e['day_of_week']])) {
        $scheduleByDay[$e['day_of_week']][] = $e;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-white"><i class="fas fa-calendar-alt text-primary me-2"></i>Weekly Class Timetable</h1>
            <p class="text-secondary small mb-0">View and manage schedule slots, assigned courses, and classroom allocations.</p>
        </div>
        <?php if (isStaff()): ?>
            <a href="create.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> Add Timetable Slot
            </a>
        <?php endif; ?>
    </div>

    <div class="row g-3">
        <?php foreach ($days as $day): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm border-0 bg-dark text-white">
                    <div class="card-header bg-secondary bg-opacity-25 border-0 d-flex justify-content-between align-items-center">
                        <span class="fw-bold"><?php echo e($day); ?></span>
                        <span class="badge bg-primary rounded-pill"><?php echo count($scheduleByDay[$day]); ?> classes</span>
                    </div>
                    <div class="card-body p-3">
                        <?php if (empty($scheduleByDay[$day])): ?>
                            <div class="text-center py-4 text-secondary">
                                <i class="fas fa-bed fa-2x mb-2 opacity-50"></i>
                                <p class="small mb-0">No classes scheduled</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($scheduleByDay[$day] as $slot): ?>
                                    <div class="list-group-item bg-transparent text-white border-secondary px-0 py-2">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1 text-white fw-bold"><?php echo e($slot['course_code'] . ' - ' . $slot['course_name']); ?></h6>
                                                <div class="small text-secondary">
                                                    <i class="fas fa-clock me-1 text-info"></i>
                                                    <?php echo e(date('h:i A', strtotime($slot['start_time']))) . ' - ' . e(date('h:i A', strtotime($slot['end_time']))); ?>
                                                </div>
                                                <div class="small text-secondary">
                                                    <i class="fas fa-user-tie me-1 text-warning"></i>
                                                    <?php echo e($slot['teacher_name']); ?>
                                                    <?php if (!empty($slot['room'])): ?>
                                                        <span class="ms-2"><i class="fas fa-door-open me-1 text-success"></i><?php echo e($slot['room']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <span class="badge bg-success bg-opacity-75"><?php echo e($slot['status']); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
