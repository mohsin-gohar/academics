<?php
/**
 * Platform Super Admin Dashboard
 * Cross-school overview: tenants, plans, trials, revenue and recent activity.
 */
$page_title = 'Platform Overview';
require_once '../includes/header.php';
require_once '../includes/saas.php';
requireSuperAdmin();

$schools = $pdo->query("
    SELECT s.*,
        (SELECT COUNT(*) FROM users u WHERE u.school_id = s.id) AS user_count,
        (SELECT COUNT(*) FROM students st WHERE st.school_id = s.id) AS student_count,
        (SELECT COUNT(*) FROM tasks t WHERE t.school_id = s.id) AS task_count
    FROM schools s
    ORDER BY s.created_at DESC
")->fetchAll();

$totalSchools = count($schools);
$activeSchools = 0;
$trialSchools = 0;
$suspendedSchools = 0;
foreach ($schools as $school) {
    $status = $school['status'] ?? ((int)$school['is_active'] === 1 ? 'active' : 'suspended');
    if ($status === 'active') $activeSchools++;
    elseif ($status === 'trial') $trialSchools++;
    else $suspendedSchools++;
}

$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalTasks = (int)$pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
$activeSubscriptions = (int)$pdo->query("SELECT COUNT(*) FROM school_subscriptions WHERE status = 'active' AND end_date >= CURDATE()")->fetchColumn();
$mrr = saas_monthly_recurring_revenue($pdo);

$recentSchools = array_slice($schools, 0, 6);
$expiring = $pdo->query("
    SELECT ss.*, s.school_name, s.school_code
    FROM school_subscriptions ss
    JOIN schools s ON s.id = ss.school_id
    WHERE ss.status = 'active' AND ss.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY ss.end_date ASC
    LIMIT 6
")->fetchAll();
$recentMessages = $pdo->query("
    SELECT cm.*, s.school_name
    FROM contact_messages cm
    LEFT JOIN schools s ON s.id = cm.school_id
    ORDER BY cm.created_at DESC, cm.id DESC
    LIMIT 5
")->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h2 class="fw-bold mb-1" style="letter-spacing:-.03em;">Platform overview</h2>
        <p class="text-muted mb-0">Every school, subscription and trial in one place.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="../admin/schools.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Manage schools</a>
        <a href="../admin/subscriptions.php" class="btn btn-outline-primary"><i class="fas fa-file-invoice-dollar me-1"></i> Subscriptions</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-xl-3">
        <div class="kpi-card kpi-accent-indigo">
            <div class="kpi-icon icon-indigo"><i class="fas fa-school"></i></div>
            <div class="kpi-lbl">Schools</div>
            <div class="kpi-val"><?php echo $totalSchools; ?></div>
            <div class="kpi-sub"><?php echo $activeSchools; ?> active · <?php echo $trialSchools; ?> trial · <?php echo $suspendedSchools; ?> suspended</div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="kpi-card kpi-accent-cyan">
            <div class="kpi-icon icon-cyan"><i class="fas fa-users"></i></div>
            <div class="kpi-lbl">Users</div>
            <div class="kpi-val"><?php echo number_format($totalUsers); ?></div>
            <div class="kpi-sub">all platform accounts</div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="kpi-card kpi-accent-emerald">
            <div class="kpi-icon icon-emerald"><i class="fas fa-user-graduate"></i></div>
            <div class="kpi-lbl">Students</div>
            <div class="kpi-val"><?php echo number_format($totalStudents); ?></div>
            <div class="kpi-sub"><?php echo number_format($totalTasks); ?> tasks tracked</div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="kpi-card kpi-accent-amber">
            <div class="kpi-icon icon-amber"><i class="fas fa-sack-dollar"></i></div>
            <div class="kpi-lbl">Monthly Recurring Revenue</div>
            <div class="kpi-val">$<?php echo number_format($mrr, 0); ?></div>
            <div class="kpi-sub"><?php echo $activeSubscriptions; ?> active subscriptions</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-school me-2 text-primary"></i>Recent Schools</span>
                <a href="../admin/schools.php" class="btn btn-outline-secondary btn-sm">View all</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>School</th><th>Plan</th><th>Status</th><th class="text-center">Students</th><th class="text-end">Details</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentSchools as $school): ?>
                            <?php
                            $status = $school['status'] ?? 'active';
                            $badge = $status === 'active' ? 'success' : ($status === 'trial' ? 'warning' : 'secondary');
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo e($school['school_name']); ?></div>
                                    <div class="small text-muted"><?php echo e($school['school_code']); ?><?php echo !empty($school['subdomain']) ? ' · ' . e($school['subdomain']) : ''; ?></div>
                                </td>
                                <td><span class="badge bg-primary"><?php echo e(saas_plan_label($school['plan'] ?? 'basic')); ?></span></td>
                                <td><span class="badge bg-<?php echo $badge; ?>"><?php echo e(ucfirst($status)); ?></span></td>
                                <td class="text-center"><?php echo (int)$school['student_count']; ?></td>
                                <td class="text-end"><a href="school.php?id=<?php echo (int)$school['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$recentSchools): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No schools yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="card mb-4">
            <div class="card-header"><span><i class="fas fa-calendar-check me-2 text-primary"></i>Subscriptions Expiring Soon</span></div>
            <div class="card-body">
                <?php if ($expiring): ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($expiring as $sub): ?>
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <div>
                                    <div class="fw-semibold small"><?php echo e($sub['school_name']); ?></div>
                                    <div class="text-muted" style="font-size:.75rem;"><?php echo e(saas_plan_label($sub['plan'])); ?> · ends <?php echo e(date('M j, Y', strtotime($sub['end_date']))); ?></div>
                                </div>
                                <span class="badge bg-warning text-dark"><?php echo max(0, (int)floor((strtotime($sub['end_date']) - time()) / 86400)); ?>d</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0"><i class="fas fa-check-circle text-success me-1"></i> No subscriptions expire in the next 30 days.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-inbox me-2 text-primary"></i>Recent Messages</span>
                <a href="../admin/messages.php" class="btn btn-outline-secondary btn-sm">Inbox</a>
            </div>
            <div class="card-body">
                <?php if ($recentMessages): ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($recentMessages as $message): ?>
                            <div>
                                <div class="d-flex justify-content-between gap-2">
                                    <span class="fw-semibold small"><?php echo e($message['name']); ?></span>
                                    <span class="badge bg-<?php echo $message['status'] === 'new' ? 'warning' : 'secondary'; ?>"><?php echo e(ucfirst($message['status'])); ?></span>
                                </div>
                                <div class="text-muted" style="font-size:.75rem;"><?php echo e($message['subject']); ?><?php echo !empty($message['school_name']) ? ' · ' . e($message['school_name']) : ''; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">No messages yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
