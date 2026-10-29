<?php
/**
 * Emergency School Closure & Notices Management
 * Academics Student Management System
 */
$page_title = 'Emergency Closure & Alert Manager';
require_once '../includes/header.php';
requireStaff();

$user_id = $_SESSION['user_id'];
$alertSchoolId = isSuperAdmin() ? null : currentSchoolId();
$errors = [];
$success = '';

// Fetch this school's alert (platform-wide for the super admin).
$fetchAlert = function () use ($pdo, $alertSchoolId) {
    if ($alertSchoolId !== null) {
        $stmt = $pdo->prepare("SELECT * FROM emergency_alerts WHERE school_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$alertSchoolId]);
    } else {
        $stmt = $pdo->query("SELECT * FROM emergency_alerts WHERE school_id IS NULL ORDER BY id DESC LIMIT 1");
    }
    return $stmt->fetch();
};
$alert = $fetchAlert();

// If no alert exists yet, insert a default placeholder
if (!$alert) {
    $ins = $pdo->prepare("INSERT INTO emergency_alerts (school_id, title, message, alert_type, effective_date, end_date, is_active, updated_by) VALUES (?, 'School Closure Notice', 'School will remain closed until further notice.', 'Emergency Closure', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 0, ?)");
    $ins->execute([$alertSchoolId, $user_id]);
    $alert = $fetchAlert();
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? 'update';

        if ($action === 'toggle_status') {
            $newStatus = (int)($alert['is_active'] ? 0 : 1);
            $upStmt = $pdo->prepare("UPDATE emergency_alerts SET is_active = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
            $upStmt->execute([$newStatus, $user_id, $alert['id']]);
            
            logActivity($pdo, $user_id, 'toggle_alert', 'emergency_alert', $alert['id'], ($newStatus ? 'Activated' : 'Deactivated') . ' emergency closure notice');
            setFlashMessage('success', 'Emergency closure alert ' . ($newStatus ? 'ACTIVATED and is now LIVE across the website & portal.' : 'DEACTIVATED. Normal school operations restored.'));
            header('Location: emergency_alert.php');
            exit;
        } elseif ($action === 'update') {
            $title = trim($_POST['title'] ?? '');
            $alert_type = trim($_POST['alert_type'] ?? 'Emergency Closure');
            $message = trim($_POST['message'] ?? '');
            $effective_date = trim($_POST['effective_date'] ?? date('Y-m-d'));
            $end_date = !empty($_POST['end_date']) ? trim($_POST['end_date']) : null;
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (empty($title)) {
                $errors[] = 'Alert title / headline is required.';
            }
            if (empty($message)) {
                $errors[] = 'Alert message and instructions for parents/students are required.';
            }
            if (empty($effective_date)) {
                $errors[] = 'Effective date is required.';
            }

            if (empty($errors)) {
                try {
                    $upStmt = $pdo->prepare("UPDATE emergency_alerts SET title = ?, message = ?, alert_type = ?, effective_date = ?, end_date = ?, is_active = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
                    $upStmt->execute([$title, $message, $alert_type, $effective_date, $end_date, $is_active, $user_id, $alert['id']]);

                    logActivity($pdo, $user_id, 'update_alert', 'emergency_alert', $alert['id'], "Updated emergency alert: $title");
                    setFlashMessage('success', 'Emergency notice updated successfully.');
                    header('Location: emergency_alert.php');
                    exit;
                } catch (PDOException $e) {
                    error_log("Failed to update emergency alert: " . $e->getMessage());
                    $errors[] = 'Database error while saving changes.';
                }
            }
        }
    }
}

// Refresh alert data after potential changes
$stmt = $pdo->prepare("SELECT a.*, u.full_name as author_name FROM emergency_alerts a LEFT JOIN users u ON a.updated_by = u.id WHERE a.id = ?");
$stmt->execute([$alert['id']]);
$alert = $stmt->fetch();
$isActive = (bool)$alert['is_active'];
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-7">
        <h4 class="mb-1 text-dark fw-bold"><i class="fas fa-bullhorn text-warning me-2"></i>Emergency School Off & Closure Manager</h4>
        <p class="text-muted small mb-0">Control campus closure announcements, weather advisories, and administrative off-days broadcasted to students, parents, and public.</p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <form method="POST" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="action" value="toggle_status">
            <?php if ($isActive): ?>
                <button type="submit" class="btn btn-outline-danger btn-lg shadow-sm" onclick="return confirm('Deactivate Emergency Closure notice and resume normal school status?')">
                    <i class="fas fa-power-off me-2"></i> Deactivate Closure (Turn OFF)
                </button>
            <?php else: ?>
                <button type="submit" class="btn btn-danger btn-lg shadow-sm" onclick="return confirm('Activate Emergency Closure notice? This will broadcast the alert on the website and portals immediately.')">
                    <i class="fas fa-exclamation-triangle me-2"></i> ACTIVATE Emergency Closure (Turn ON)
                </button>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm border-0 mb-4">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php foreach ($errors as $e): ?>
            <div><?php echo e($e); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- System Status Indicator Card -->
<div class="card mb-4 border-0 shadow-sm <?php echo $isActive ? 'bg-danger text-white' : 'bg-light border'; ?>">
    <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center <?php echo $isActive ? 'bg-white text-danger' : 'bg-secondary text-white'; ?>" style="width: 56px; height: 56px; font-size: 1.5rem;">
                    <i class="fas <?php echo $isActive ? 'fa-bell fa-shake' : 'fa-check'; ?>"></i>
                </div>
                <div>
                    <h5 class="mb-1 fw-bold <?php echo $isActive ? 'text-white' : 'text-dark'; ?>">
                        Status: <?php echo $isActive ? 'EMERGENCY CLOSURE ACTIVE (Broadcasting Live)' : 'NORMAL SCHOOL OPERATIONS (Alert Inactive)'; ?>
                    </h5>
                    <p class="mb-0 small <?php echo $isActive ? 'text-white-50' : 'text-muted'; ?>">
                        <?php if ($isActive): ?>
                            The emergency alert banner is currently displayed across the Public Website, Student Portal, and Dashboard.
                        <?php else: ?>
                            Campus is open. No active closure alerts are visible to parents or students.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div>
                <span class="badge <?php echo $isActive ? 'bg-white text-danger' : 'bg-success'; ?> px-3 py-2 fs-6">
                    <i class="fas fa-circle fa-xs me-1 <?php echo $isActive ? 'text-danger' : 'text-white'; ?>"></i>
                    <?php echo $isActive ? 'LIVE ON SITE' : 'OFFLINE'; ?>
                </span>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Configuration Form -->
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-edit text-primary me-2"></i>Edit Emergency Closure Notice Details</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="action" value="update">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Alert Type <span class="text-danger">*</span></label>
                        <select name="alert_type" class="form-select" required>
                            <option value="Emergency Closure" <?php echo $alert['alert_type'] === 'Emergency Closure' ? 'selected' : ''; ?>>🚨 Emergency Closure (Campus Closed)</option>
                            <option value="Severe Weather" <?php echo $alert['alert_type'] === 'Severe Weather' ? 'selected' : ''; ?>>🌧️ Severe Weather / Rain / Smog Advisory</option>
                            <option value="Administrative Off" <?php echo $alert['alert_type'] === 'Administrative Off' ? 'selected' : ''; ?>>🏛️ Administrative Off / Special Holiday</option>
                            <option value="General Alert" <?php echo $alert['alert_type'] === 'General Alert' ? 'selected' : ''; ?>>📢 General Urgent Announcement</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notice Headline / Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" value="<?php echo e($alert['title']); ?>" placeholder="e.g., URGENT: Campus Closed Due to Torrential Rain" required>
                        <small class="text-muted">Keep it concise and clear for parents and students.</small>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Effective Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="effective_date" class="form-control" value="<?php echo e($alert['effective_date']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Expected Reopening Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo e($alert['end_date'] ?? ''); ?>">
                            <small class="text-muted">Leave blank if reopening date is yet to be announced.</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Detailed Instructions & Explanation <span class="text-danger">*</span></label>
                        <textarea name="message" class="form-control" rows="5" required placeholder="Provide clear instructions for students and parents (e.g., classes will be held online via Zoom/LMS, exams postponed, emergency helpline number)..."><?php echo e($alert['message']); ?></textarea>
                    </div>

                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActiveSwitch" value="1" <?php echo $isActive ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-semibold" for="isActiveSwitch">
                            Broadcast this notice immediately (Set status to Active)
                        </label>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> Save Changes</button>
                        <a href="emergency_alert.php" class="btn btn-outline-secondary">Reset Form</a>
                    </div>
                </form>
            </div>
            <div class="card-footer bg-light py-2 text-muted small">
                Last updated by: <strong><?php echo e($alert['author_name'] ?? 'Administrator'); ?></strong> on <?php echo formatDateTime($alert['updated_at']); ?>
            </div>
        </div>
    </div>

    <!-- Live Preview Card -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-eye text-info me-2"></i>Live Portal & Website Preview</h6>
                <span class="badge bg-light text-dark border">Real-Time Simulation</span>
            </div>
            <div class="card-body p-3">
                <p class="small text-muted mb-2">This is exactly how students, parents, and website visitors see the alert banner:</p>

                <!-- Preview Banner Box -->
                <div class="p-3 rounded-3 border <?php echo $isActive ? 'bg-danger text-white border-danger shadow-sm' : 'bg-light text-muted border-dashed'; ?>">
                    <div class="d-flex align-items-start gap-2">
                        <i class="fas fa-exclamation-triangle fs-4 mt-1 <?php echo $isActive ? 'text-warning' : 'text-muted'; ?>"></i>
                        <div>
                            <div class="badge <?php echo $isActive ? 'bg-warning text-dark' : 'bg-secondary'; ?> mb-1">
                                <?php echo e($alert['alert_type']); ?>
                            </div>
                            <h6 class="fw-bold mb-1 <?php echo $isActive ? 'text-white' : 'text-dark'; ?>">
                                <?php echo e($alert['title']); ?>
                            </h6>
                            <p class="small mb-2 <?php echo $isActive ? 'text-white-50' : 'text-muted'; ?>">
                                <strong>Effective:</strong> <?php echo formatDate($alert['effective_date']); ?>
                                <?php if (!empty($alert['end_date'])): ?>
                                    — <strong>Reopening:</strong> <?php echo formatDate($alert['end_date']); ?>
                                <?php endif; ?>
                            </p>
                            <div class="small p-2 rounded <?php echo $isActive ? 'bg-black bg-opacity-25 text-white' : 'bg-white border text-dark'; ?>">
                                <?php echo nl2br(e($alert['message'])); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 p-3 bg-light rounded-3 border">
                    <h6 class="fw-bold small text-dark mb-2"><i class="fas fa-shield-alt text-success me-1"></i>Security & Permissions Note:</h6>
                    <ul class="small text-muted mb-0 ps-3">
                        <li>Only authorized faculty and administration staff can modify or toggle emergency notices.</li>
                        <li>Students and parents have read-only visibility on the public website and portal.</li>
                        <li>All status changes and modifications are logged in the system audit trail.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
