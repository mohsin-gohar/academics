<?php
/**
 * Public School Calendar & Events Portal
 * Academics School Management Platform
 */
$page_title = 'Public Academic Calendar & Events';
require_once 'includes/header.php';

$activeAlert = getActiveEmergencyAlert($pdo);
$typeFilter = trim($_GET['type'] ?? '');

$where = ["status != 'Cancelled'"];
$params = [];

if (!empty($typeFilter)) {
    if ($typeFilter === 'Holiday') {
        $where[] = "is_holiday = 1";
    } else {
        $where[] = "event_type = ?";
        $params[] = $typeFilter;
    }
}

$whereClause = implode(' AND ', $where);
$stmt = $pdo->prepare("SELECT * FROM school_events WHERE $whereClause ORDER BY event_date ASC");
$stmt->execute($params);
$events = $stmt->fetchAll();

// Counts
$totalCount = $pdo->query("SELECT COUNT(*) FROM school_events WHERE status != 'Cancelled'")->fetchColumn();
$holidayCount = $pdo->query("SELECT COUNT(*) FROM school_events WHERE is_holiday = 1 AND status != 'Cancelled'")->fetchColumn();
$functionCount = $pdo->query("SELECT COUNT(*) FROM school_events WHERE event_type = 'Function' AND status != 'Cancelled'")->fetchColumn();
$examCount = $pdo->query("SELECT COUNT(*) FROM school_events WHERE event_type = 'Exam' AND status != 'Cancelled'")->fetchColumn();
?>

<!-- Public Navigation Bar -->
<nav class="navbar navbar-expand-lg glass-nav sticky-top py-3">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <img src="assets/img/academics-mark.svg" alt="" width="40" height="40" class="rounded-3 shadow-sm">
            <div>
                <span class="fw-bold fs-5 text-dark d-block lh-1"><?php echo e(APP_NAME); ?></span>
                <span class="text-muted small fw-semibold" style="font-size: 0.65rem;">Academic Schedule</span>
            </div>
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <a href="index.php" class="btn btn-outline-secondary btn-sm px-3 rounded-pill"><i class="fas fa-home me-1"></i> School Home</a>
            <?php if (isLoggedIn()): ?>
                <a href="dashboard.php" class="btn btn-primary btn-sm px-3 rounded-pill"><i class="fas fa-th-large me-1"></i> My Dashboard</a>
            <?php else: ?>
                <a href="auth/login.php" class="btn btn-primary btn-sm px-3 rounded-pill"><i class="fas fa-sign-in-alt me-1"></i> Portal Login</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Emergency Alert Banner if Active -->
<?php if ($activeAlert): ?>
<div class="bg-danger text-white py-3 shadow-sm border-bottom border-danger">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-warning text-dark px-2 py-1 fs-6"><i class="fas fa-bullhorn me-1"></i> URGENT NOTICE</span>
                <div>
                    <strong class="fs-5"><?php echo e($activeAlert['title']); ?></strong>
                    <div class="small opacity-90"><?php echo e($activeAlert['message']); ?></div>
                </div>
            </div>
            <div>
                <span class="badge bg-white text-danger px-3 py-2 fw-bold">
                    Effective: <?php echo formatDate($activeAlert['effective_date']); ?>
                    <?php if (!empty($activeAlert['end_date'])): ?>
                        — Reopening: <?php echo formatDate($activeAlert['end_date']); ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="container py-5">
    <div class="text-center mb-5">
        <span class="saas-badge-pill bg-primary-subtle text-primary border border-primary mb-2">OFFICIAL ACADEMIC SCHEDULE</span>
        <h2 class="display-6 fw-bold text-dark mb-2">School Functions, Examinations & Gazetted Holidays</h2>
        <p class="text-muted mx-auto" style="max-width: 650px;">Stay up-to-date with all upcoming co-curricular events, student competitions, parent-teacher conferences, and official school off-days.</p>
        
        <!-- Filter Pills -->
        <div class="d-flex gap-2 justify-content-center flex-wrap mt-4">
            <a href="public_events.php" class="btn btn-sm <?php echo empty($typeFilter) ? 'btn-primary' : 'btn-light border'; ?> rounded-pill px-3 py-2">
                All Scheduled Dates (<?php echo (int)$totalCount; ?>)
            </a>
            <a href="public_events.php?type=Holiday" class="btn btn-sm <?php echo $typeFilter === 'Holiday' ? 'btn-danger' : 'btn-light border'; ?> rounded-pill px-3 py-2">
                <i class="fas fa-sun me-1"></i> Gazetted Holidays & School Off (<?php echo (int)$holidayCount; ?>)
            </a>
            <a href="public_events.php?type=Function" class="btn btn-sm <?php echo $typeFilter === 'Function' ? 'btn-primary' : 'btn-light border'; ?> rounded-pill px-3 py-2">
                <i class="fas fa-trophy me-1"></i> Functions & Galas (<?php echo (int)$functionCount; ?>)
            </a>
            <a href="public_events.php?type=Exam" class="btn btn-sm <?php echo $typeFilter === 'Exam' ? 'btn-warning text-dark' : 'btn-light border'; ?> rounded-pill px-3 py-2">
                <i class="fas fa-file-alt me-1"></i> Examinations (<?php echo (int)$examCount; ?>)
            </a>
        </div>
    </div>

    <?php if (empty($events)): ?>
        <div class="saas-card py-5 text-center">
            <div class="card-body">
                <i class="fas fa-calendar-check fa-3x text-muted mb-3 opacity-50"></i>
                <h5>No events currently listed</h5>
                <p class="text-muted small">Check back later or contact administration office for details.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($events as $ev): 
                $today = date('Y-m-d');
                $evDate = $ev['event_date'];
                $diff = (int)round((strtotime($evDate) - strtotime($today)) / 86400);

                if ($diff === 0) {
                    $tag = '<span class="badge bg-danger">HAPPENING TODAY</span>';
                } elseif ($diff === 1) {
                    $tag = '<span class="badge bg-warning text-dark">TOMORROW</span>';
                } elseif ($diff > 1) {
                    $tag = '<span class="badge bg-light text-dark border">In ' . $diff . ' days</span>';
                } else {
                    $tag = '<span class="badge bg-secondary">Past Event</span>';
                }

                $badgeBg = match($ev['event_type']) {
                    'Holiday' => 'bg-danger',
                    'Function' => 'bg-primary',
                    'Exam' => 'bg-warning text-dark',
                    'Meeting' => 'bg-info text-dark',
                    default => 'bg-secondary'
                };
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="saas-card h-100 p-4 d-flex flex-column border-top border-4 <?php echo $ev['is_holiday'] ? 'border-danger' : 'border-primary'; ?>">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge <?php echo $badgeBg; ?> px-3 py-2"><?php echo e($ev['event_type']); ?></span>
                        <?php echo $tag; ?>
                    </div>

                    <h5 class="fw-bold text-dark mb-2"><?php echo e($ev['title']); ?></h5>

                    <div class="text-muted small mb-3">
                        <i class="fas fa-calendar-alt text-primary me-1"></i>
                        <strong><?php echo formatDate($ev['event_date'], 'l, F j, Y'); ?></strong>
                        <?php if (!empty($ev['end_date'])): ?>
                            <br><i class="fas fa-arrow-right text-muted me-1"></i> Ending: <?php echo formatDate($ev['end_date'], 'F j, Y'); ?>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($ev['description'])): ?>
                        <p class="text-secondary small mb-3 flex-grow-1">
                            <?php echo nl2br(e($ev['description'])); ?>
                        </p>
                    <?php endif; ?>

                    <?php if ($ev['is_holiday']): ?>
                        <div class="p-2 rounded bg-danger bg-opacity-10 text-danger small fw-semibold mt-auto">
                            <i class="fas fa-sun me-1"></i> <strong>School Holiday:</strong> Campus Closed
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Parent Guidelines Box -->
    <div class="mt-5 p-4 rounded-4 bg-light border shadow-sm">
        <div class="row align-items-center g-3">
            <div class="col-md-8">
                <h5 class="fw-bold text-dark mb-1"><i class="fas fa-info-circle text-primary me-2"></i>Note for Parents & Guardians</h5>
                <p class="text-muted small mb-0">Gazetted holiday notifications are synchronized with provincial government advisories. In case of unexpected weather disruptions or emergency campus closures, verified notifications will be broadcasted live on this portal.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="auth/login.php" class="btn btn-primary btn-sm px-4 py-2 rounded-pill"><i class="fas fa-user-lock me-1"></i> Student Portal Sign In</a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
