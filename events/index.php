<?php
/**
 * School Events, Functions & Gazetted Holidays
 * Academics Student Management System
 */
$page_title = 'School Events & Holidays';
require_once '../includes/header.php';
requireAuth();

$typeFilter = trim($_GET['type'] ?? '');
$isStaff = isStaff();

$where = ["status != 'Cancelled'"];
$params = [];

if (!isSuperAdmin()) {
    $where[] = 'e.school_id = ?';
    $params[] = currentSchoolId();
}

if (!empty($typeFilter)) {
    if ($typeFilter === 'Holiday') {
        $where[] = "is_holiday = 1";
    } else {
        $where[] = "event_type = ?";
        $params[] = $typeFilter;
    }
}

$whereClause = implode(' AND ', $where);

// Fetch events ordered by event_date
$stmt = $pdo->prepare("SELECT e.*, u.full_name as author_name FROM school_events e LEFT JOIN users u ON e.created_by = u.id WHERE $whereClause ORDER BY e.event_date ASC");
$stmt->execute($params);
$events = $stmt->fetchAll();

// Counts for filter pills
$countScope = isSuperAdmin() ? '' : ' AND school_id = ' . (int)currentSchoolId();
$totalCount = $pdo->query("SELECT COUNT(*) FROM school_events WHERE status != 'Cancelled'$countScope")->fetchColumn();
$holidayCount = $pdo->query("SELECT COUNT(*) FROM school_events WHERE is_holiday = 1 AND status != 'Cancelled'$countScope")->fetchColumn();
$functionCount = $pdo->query("SELECT COUNT(*) FROM school_events WHERE event_type = 'Function' AND status != 'Cancelled'$countScope")->fetchColumn();
$examCount = $pdo->query("SELECT COUNT(*) FROM school_events WHERE event_type = 'Exam' AND status != 'Cancelled'$countScope")->fetchColumn();
$meetingCount = $pdo->query("SELECT COUNT(*) FROM school_events WHERE event_type = 'Meeting' AND status != 'Cancelled'$countScope")->fetchColumn();
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1 text-dark fw-bold"><i class="fas fa-calendar-alt text-primary me-2"></i>School Events, Functions & Holidays</h4>
        <p class="text-muted small mb-0">Official academic calendar including upcoming school functions, sports galas, examinations, and gazetted days off.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="../public_events.php" target="_blank" class="btn btn-outline-info btn-sm">
            <i class="fas fa-globe me-1"></i> Public Calendar View
        </a>
        <?php if ($isStaff): ?>
        <a href="create.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Add Event / Holiday
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filter Pills -->
<div class="d-flex gap-2 mb-4 overflow-auto pb-2">
    <a href="index.php" class="btn btn-sm <?php echo empty($typeFilter) ? 'btn-primary' : 'btn-light border'; ?> rounded-pill px-3">
        All Events (<?php echo (int)$totalCount; ?>)
    </a>
    <a href="index.php?type=Holiday" class="btn btn-sm <?php echo $typeFilter === 'Holiday' ? 'btn-danger' : 'btn-light border'; ?> rounded-pill px-3">
        <i class="fas fa-sun me-1"></i> Holidays & Off Days (<?php echo (int)$holidayCount; ?>)
    </a>
    <a href="index.php?type=Function" class="btn btn-sm <?php echo $typeFilter === 'Function' ? 'btn-primary' : 'btn-light border'; ?> rounded-pill px-3">
        <i class="fas fa-trophy me-1"></i> Functions & Galas (<?php echo (int)$functionCount; ?>)
    </a>
    <a href="index.php?type=Exam" class="btn btn-sm <?php echo $typeFilter === 'Exam' ? 'btn-warning text-dark' : 'btn-light border'; ?> rounded-pill px-3">
        <i class="fas fa-edit me-1"></i> Exams (<?php echo (int)$examCount; ?>)
    </a>
    <a href="index.php?type=Meeting" class="btn btn-sm <?php echo $typeFilter === 'Meeting' ? 'btn-info' : 'btn-light border'; ?> rounded-pill px-3">
        <i class="fas fa-users me-1"></i> Meetings & PTM (<?php echo (int)$meetingCount; ?>)
    </a>
</div>

<?php if (empty($events)): ?>
    <div class="card shadow-sm border-0 py-5 text-center">
        <div class="card-body">
            <i class="fas fa-calendar-times fa-3x text-muted mb-3 opacity-50"></i>
            <h5 class="text-dark">No Events Found</h5>
            <p class="text-muted small mb-3">There are no school events matching the selected category.</p>
            <?php if ($isStaff): ?>
                <a href="create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> Add First Event</a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($events as $ev): 
            $today = date('Y-m-d');
            $evDate = $ev['event_date'];
            $daysDiff = (int)round((strtotime($evDate) - strtotime($today)) / 86400);

            if ($daysDiff === 0) {
                $timeTag = '<span class="badge bg-danger">TODAY</span>';
            } elseif ($daysDiff === 1) {
                $timeTag = '<span class="badge bg-warning text-dark">TOMORROW</span>';
            } elseif ($daysDiff > 1) {
                $timeTag = '<span class="badge bg-light text-dark border">In ' . $daysDiff . ' days</span>';
            } else {
                $timeTag = '<span class="badge bg-secondary">Completed</span>';
            }

            $badgeColor = match($ev['event_type']) {
                'Holiday' => 'bg-danger',
                'Function' => 'bg-primary',
                'Exam' => 'bg-warning text-dark',
                'Meeting' => 'bg-info text-dark',
                default => 'bg-secondary'
            };
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm border-0 position-relative <?php echo $ev['is_holiday'] ? 'border-top border-4 border-danger' : 'border-top border-4 border-primary'; ?>">
                <div class="card-body p-4 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge <?php echo $badgeColor; ?>"><?php echo e($ev['event_type']); ?></span>
                        <?php echo $timeTag; ?>
                    </div>

                    <h5 class="fw-bold text-dark mb-2"><?php echo e($ev['title']); ?></h5>

                    <div class="text-muted small mb-3">
                        <i class="fas fa-calendar-day text-primary me-1"></i>
                        <strong><?php echo formatDate($ev['event_date'], 'l, F j, Y'); ?></strong>
                        <?php if (!empty($ev['end_date'])): ?>
                            <br><i class="fas fa-calendar-check text-muted me-1"></i> Until: <?php echo formatDate($ev['end_date'], 'F j, Y'); ?>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($ev['description'])): ?>
                        <p class="text-secondary small mb-3 flex-grow-1">
                            <?php echo nl2br(e($ev['description'])); ?>
                        </p>
                    <?php endif; ?>

                    <?php if ($ev['is_holiday']): ?>
                        <div class="p-2 mb-3 rounded bg-danger bg-opacity-10 text-danger small fw-semibold">
                            <i class="fas fa-sun me-1"></i> Campus will remain closed on this day.
                        </div>
                    <?php endif; ?>

                    <?php if ($isStaff): ?>
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top mt-auto">
                        <small class="text-muted">By: <?php echo e($ev['author_name'] ?: 'Staff'); ?></small>
                        <div class="d-flex gap-1">
                            <a href="edit.php?id=<?php echo $ev['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="post" action="delete.php" class="d-inline" onsubmit="return confirm('Delete this school event?');">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="id" value="<?php echo (int)$ev['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
