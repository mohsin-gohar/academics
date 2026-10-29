<?php
/**
 * Super Admin — School Detail
 */
$page_title = 'School Details';
require_once '../includes/header.php';
require_once '../includes/saas.php';
requireSuperAdmin();

$id = (int)($_GET['id'] ?? 0);
$success = $_GET['success'] ?? '';
$school = $id > 0 ? school_by_id($pdo, $id) : null;
if (!$school) {
    header('Location: ../admin/schools.php?success=' . urlencode('School not found.'));
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM school_settings WHERE school_id = ? LIMIT 1");
$stmt->execute([$id]);
$settings = $stmt->fetch() ?: [];

$counts = [];
foreach ([
    'users'    => "SELECT COUNT(*) FROM users WHERE school_id = ?",
    'students' => "SELECT COUNT(*) FROM students WHERE school_id = ?",
    'courses'  => "SELECT COUNT(*) FROM courses WHERE school_id = ?",
    'tasks'    => "SELECT COUNT(*) FROM tasks WHERE school_id = ?",
    'events'   => "SELECT COUNT(*) FROM school_events WHERE school_id = ?",
] as $key => $sql) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $counts[$key] = (int)$stmt->fetchColumn();
}

$stmt = $pdo->prepare("
    SELECT ss.*, u.full_name AS created_by_name
    FROM school_subscriptions ss
    LEFT JOIN users u ON u.id = (
        SELECT al.user_id FROM activity_logs al
        WHERE al.entity_type = 'subscription' AND al.entity_id = ss.id
        ORDER BY al.id ASC LIMIT 1
    )
    WHERE ss.school_id = ?
    ORDER BY ss.created_at DESC, ss.id DESC
");
$stmt->execute([$id]);
$subscriptions = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT id, full_name, email, role, is_active, last_login, created_at FROM users WHERE school_id = ? ORDER BY created_at DESC LIMIT 12");
$stmt->execute([$id]);
$users = $stmt->fetchAll();

$activeSubscription = saas_active_subscription($pdo, $id);
$access = saas_school_access_state($school);
$status = $school['status'] ?? ((int)$school['is_active'] === 1 ? 'active' : 'suspended');
$statusBadge = $status === 'active' ? 'success' : ($status === 'trial' ? 'warning' : 'secondary');
?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?php echo e($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <a href="../admin/schools.php" class="text-decoration-none small text-muted"><i class="fas fa-arrow-left me-1"></i>Back to schools</a>
        <h2 class="fw-bold mt-2 mb-1" style="letter-spacing:-.03em;"><?php echo e($school['school_name']); ?></h2>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="badge bg-<?php echo $statusBadge; ?>"><?php echo e(ucfirst($status)); ?></span>
            <span class="badge bg-primary"><?php echo e(saas_plan_label($school['plan'] ?? 'basic')); ?></span>
            <span class="text-muted small"><?php echo e($school['school_code']); ?><?php echo !empty($school['subdomain']) ? ' · ' . e($school['subdomain']) : ''; ?></span>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?php echo e(saas_school_public_url($school)); ?>" target="_blank" class="btn btn-outline-secondary"><i class="fas fa-globe me-1"></i> Public page</a>
        <a href="../admin/schools.php?edit=<?php echo (int)$school['id']; ?>" class="btn btn-outline-primary"><i class="fas fa-edit me-1"></i> Edit</a>
        <form method="POST" action="suspend.php" onsubmit="return confirm('<?php echo $status === 'suspended' ? 'Activate' : 'Suspend'; ?> this school?');">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="id" value="<?php echo (int)$school['id']; ?>">
            <input type="hidden" name="action" value="<?php echo $status === 'suspended' ? 'activate' : 'suspend'; ?>">
            <button type="submit" class="btn btn-<?php echo $status === 'suspended' ? 'success' : 'outline-warning'; ?>">
                <i class="fas <?php echo $status === 'suspended' ? 'fa-check' : 'fa-ban'; ?> me-1"></i>
                <?php echo $status === 'suspended' ? 'Activate' : 'Suspend'; ?>
            </button>
        </form>
    </div>
</div>

<?php if (!$access['allowed']): ?>
    <div class="alert alert-warning"><i class="fas fa-triangle-exclamation me-2"></i><?php echo e(saas_access_message($access['reason'])); ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['Users', $counts['users'], 'fa-users', 'indigo'],
        ['Students', $counts['students'], 'fa-user-graduate', 'cyan'],
        ['Courses', $counts['courses'], 'fa-book-open', 'emerald'],
        ['Tasks', $counts['tasks'], 'fa-clipboard-check', 'amber'],
    ] as [$label, $value, $icon, $color]): ?>
        <div class="col-6 col-xl-3">
            <div class="kpi-card kpi-accent-<?php echo $color; ?>">
                <div class="kpi-icon icon-<?php echo $color; ?>"><i class="fas <?php echo $icon; ?>"></i></div>
                <div class="kpi-lbl"><?php echo e($label); ?></div>
                <div class="kpi-val"><?php echo number_format($value); ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-5">
        <div class="card mb-4">
            <div class="card-header"><span><i class="fas fa-circle-info me-2 text-primary"></i>School Information</span></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">School code</dt><dd class="col-7 fw-semibold"><?php echo e($school['school_code']); ?></dd>
                    <dt class="col-5 text-muted">Subdomain</dt><dd class="col-7"><?php echo e($school['subdomain'] ?: '—'); ?></dd>
                    <dt class="col-5 text-muted">Owner</dt><dd class="col-7"><?php echo e($school['owner_name'] ?: '—'); ?></dd>
                    <dt class="col-5 text-muted">Email</dt><dd class="col-7"><?php echo e($school['school_email'] ?: '—'); ?></dd>
                    <dt class="col-5 text-muted">Phone</dt><dd class="col-7"><?php echo e($school['school_phone'] ?: '—'); ?></dd>
                    <dt class="col-5 text-muted">Address</dt><dd class="col-7"><?php echo e($school['school_address'] ?: '—'); ?></dd>
                    <dt class="col-5 text-muted">Website</dt><dd class="col-7"><?php echo $school['website'] ? '<a href="' . e($school['website']) . '" target="_blank" rel="noopener">' . e($school['website']) . '</a>' : '—'; ?></dd>
                    <dt class="col-5 text-muted">Trial ends</dt><dd class="col-7"><?php echo !empty($school['trial_ends_at']) ? e(date('M j, Y', strtotime($school['trial_ends_at']))) : '—'; ?></dd>
                    <dt class="col-5 text-muted">Created</dt><dd class="col-7"><?php echo e(date('M j, Y', strtotime($school['created_at']))); ?></dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-file-invoice-dollar me-2 text-primary"></i>Subscriptions</span>
                <a href="../admin/subscriptions.php" class="btn btn-outline-secondary btn-sm">Manage</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Plan</th><th>Period</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($subscriptions as $sub): ?>
                            <tr>
                                <td><span class="fw-semibold small"><?php echo e(saas_plan_label($sub['plan'])); ?></span><div class="text-muted" style="font-size:.7rem;">$<?php echo number_format((float)$sub['price'], 2); ?> <?php echo e($sub['billing_cycle']); ?></div></td>
                                <td class="small"><?php echo e(date('M j, Y', strtotime($sub['start_date']))); ?><div class="text-muted" style="font-size:.7rem;">to <?php echo e(date('M j, Y', strtotime($sub['end_date']))); ?></div></td>
                                <td><span class="badge bg-<?php echo $sub['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo e(ucfirst($sub['status'])); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$subscriptions): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">No subscriptions recorded.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="card mb-4">
            <div class="card-header"><span><i class="fas fa-users me-2 text-primary"></i>Recent Users</span></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Name</th><th>Role</th><th>Status</th><th>Last login</th></tr></thead>
                        <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><div class="fw-semibold small"><?php echo e($user['full_name']); ?></div><div class="text-muted" style="font-size:.72rem;"><?php echo e($user['email']); ?></div></td>
                                <td><span class="badge bg-light text-dark"><?php echo e(tenant_role_label($user['role'])); ?></span></td>
                                <td><span class="badge bg-<?php echo (int)$user['is_active'] === 1 ? 'success' : 'secondary'; ?>"><?php echo (int)$user['is_active'] === 1 ? 'Active' : 'Inactive'; ?></span></td>
                                <td class="small text-muted"><?php echo $user['last_login'] ? e(date('M j, Y g:i A', strtotime($user['last_login']))) : 'Never'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$users): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No users in this school yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span><i class="fas fa-rectangle-ad me-2 text-primary"></i>Public Page Settings</span></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-4 text-muted">Page title</dt><dd class="col-8"><?php echo e($settings['public_page_title'] ?? '—'); ?></dd>
                    <dt class="col-4 text-muted">Description</dt><dd class="col-8"><?php echo e($settings['public_page_description'] ?? '—'); ?></dd>
                    <dt class="col-4 text-muted">Parent auto-login</dt><dd class="col-8"><?php echo !empty($settings['allow_parent_auto_login']) ? 'Enabled' : 'Disabled'; ?></dd>
                    <dt class="col-4 text-muted">Session timeout</dt><dd class="col-8"><?php echo (int)($settings['session_timeout_minutes'] ?? 30); ?> minutes</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
