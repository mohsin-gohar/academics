<?php
/**
 * Super Admin — Subscription Management
 */
$page_title = 'Subscriptions';
require_once '../includes/header.php';
require_once '../includes/saas.php';
requireSuperAdmin();

$errors = [];
$success = $_GET['success'] ?? '';
$plans = saas_plans();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);

        if ($action === 'create') {
            try {
                $subscriptionId = saas_create_subscription($pdo, (int)($_POST['school_id'] ?? 0), [
                    'plan'           => $_POST['plan'] ?? 'basic',
                    'price'          => $_POST['price'] ?? '',
                    'billing_cycle'  => $_POST['billing_cycle'] ?? 'monthly',
                    'start_date'     => $_POST['start_date'] ?? '',
                    'end_date'       => $_POST['end_date'] ?? '',
                    'status'         => $_POST['status'] ?? 'active',
                    'payment_method' => $_POST['payment_method'] ?? '',
                    'transaction_id' => $_POST['transaction_id'] ?? '',
                ]);
                tenant_log($pdo, 'subscription_create', 'subscription', $subscriptionId, 'Subscription created');
                header('Location: subscriptions.php?success=' . urlencode('Subscription created and the school plan was updated.'));
                exit;
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            } catch (Throwable $e) {
                error_log('Subscription create failed: ' . $e->getMessage());
                $errors[] = 'Could not create the subscription. Please try again.';
            }
        }

        if (($action === 'cancel' || $action === 'expire') && $id) {
            $newStatus = $action === 'cancel' ? 'cancelled' : 'expired';
            $st = $pdo->prepare("UPDATE school_subscriptions SET status = ? WHERE id = ?");
            $st->execute([$newStatus, $id]);
            tenant_log($pdo, 'subscription_' . $action, 'subscription', $id, 'Subscription marked ' . $newStatus);
            header('Location: subscriptions.php?success=' . urlencode('Subscription marked ' . $newStatus . '.'));
            exit;
        }
    }
}

$schools = $pdo->query("SELECT id, school_name, school_code, plan, status FROM schools ORDER BY school_name")->fetchAll();
$subscriptions = $pdo->query("
    SELECT ss.*, s.school_name, s.school_code, s.status AS school_status
    FROM school_subscriptions ss
    JOIN schools s ON s.id = ss.school_id
    ORDER BY ss.created_at DESC, ss.id DESC
    LIMIT 200
")->fetchAll();

$activeCount = 0;
$trialCount = 0;
$expiringCount = 0;
$mrr = saas_monthly_recurring_revenue($pdo);
foreach ($subscriptions as $sub) {
    if ($sub['status'] === 'active' && strtotime($sub['end_date']) >= strtotime('today')) {
        $activeCount++;
        if (strtotime($sub['end_date']) <= strtotime('+30 days')) {
            $expiringCount++;
        }
    }
}
foreach ($schools as $school) {
    if (($school['status'] ?? '') === 'trial') $trialCount++;
}
?>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?php echo e($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo e($err); ?></div>
<?php endforeach; ?>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-xl-3">
        <div class="kpi-card kpi-accent-indigo">
            <div class="kpi-icon icon-indigo"><i class="fas fa-file-invoice-dollar"></i></div>
            <div class="kpi-lbl">Active Subscriptions</div>
            <div class="kpi-val"><?php echo $activeCount; ?></div>
            <div class="kpi-sub">currently billing</div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="kpi-card kpi-accent-emerald">
            <div class="kpi-icon icon-emerald"><i class="fas fa-sack-dollar"></i></div>
            <div class="kpi-lbl">Monthly Recurring Revenue</div>
            <div class="kpi-val">$<?php echo number_format($mrr, 0); ?></div>
            <div class="kpi-sub">normalised monthly</div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="kpi-card kpi-accent-amber">
            <div class="kpi-icon icon-amber"><i class="fas fa-hourglass-half"></i></div>
            <div class="kpi-lbl">Trials Running</div>
            <div class="kpi-val"><?php echo $trialCount; ?></div>
            <div class="kpi-sub">30-day school trials</div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="kpi-card kpi-accent-cyan">
            <div class="kpi-icon icon-cyan"><i class="fas fa-calendar-check"></i></div>
            <div class="kpi-lbl">Expiring Soon</div>
            <div class="kpi-val"><?php echo $expiringCount; ?></div>
            <div class="kpi-sub">within 30 days</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="fas fa-file-invoice-dollar me-2 text-primary"></i>Subscription History</span>
                <a href="schools.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-school me-1"></i> Schools</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr><th>School</th><th>Plan</th><th>Price</th><th>Period</th><th>Status</th><th class="text-end">Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($subscriptions as $sub): ?>
                            <?php
                            $status = $sub['status'];
                            $badge = $status === 'active' ? 'success' : ($status === 'cancelled' ? 'secondary' : 'danger');
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo e($sub['school_name']); ?></div>
                                    <div class="small text-muted"><?php echo e($sub['school_code']); ?> · <?php echo e(ucfirst($sub['school_status'] ?? 'active')); ?></div>
                                </td>
                                <td><span class="badge bg-primary"><?php echo e(saas_plan_label($sub['plan'])); ?></span></td>
                                <td>
                                    <div class="fw-semibold">$<?php echo number_format((float)$sub['price'], 2); ?></div>
                                    <div class="small text-muted"><?php echo e(ucfirst($sub['billing_cycle'])); ?></div>
                                </td>
                                <td>
                                    <div class="small"><?php echo e(date('M j, Y', strtotime($sub['start_date']))); ?></div>
                                    <div class="small text-muted">to <?php echo e(date('M j, Y', strtotime($sub['end_date']))); ?></div>
                                </td>
                                <td><span class="badge bg-<?php echo $badge; ?>"><?php echo e(ucfirst($status)); ?></span></td>
                                <td class="text-end text-nowrap">
                                    <?php if ($status === 'active'): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Cancel this subscription?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="id" value="<?php echo (int)$sub['id']; ?>">
                                            <button class="btn btn-sm btn-outline-warning" title="Cancel"><i class="fas fa-ban"></i></button>
                                        </form>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Mark this subscription expired?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                            <input type="hidden" name="action" value="expire">
                                            <input type="hidden" name="id" value="<?php echo (int)$sub['id']; ?>">
                                            <button class="btn btn-sm btn-outline-danger" title="Expire"><i class="fas fa-clock"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$subscriptions): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No subscriptions yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="card">
            <div class="card-header"><span><i class="fas fa-plus-circle me-2 text-primary"></i>Add Subscription</span></div>
            <div class="card-body">
                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">School <span class="text-danger">*</span></label>
                        <select name="school_id" class="form-select" required>
                            <option value="">Select a school</option>
                            <?php foreach ($schools as $school): ?>
                                <option value="<?php echo (int)$school['id']; ?>"><?php echo e($school['school_name']); ?> (<?php echo e($school['school_code']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label">Plan</label>
                            <select name="plan" id="subPlan" class="form-select">
                                <?php foreach ($plans as $planKey => $plan): ?>
                                    <option value="<?php echo e($planKey); ?>" data-price="<?php echo (float)$plan['price']; ?>"><?php echo e($plan['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-5">
                            <label class="form-label">Price</label>
                            <input type="number" name="price" id="subPrice" class="form-control" min="0" step="0.01" value="<?php echo (float)$plans['basic']['price']; ?>">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Billing</label>
                            <select name="billing_cycle" class="form-select">
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active">Active</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="expired">Expired</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Payment Method</label>
                            <input type="text" name="payment_method" class="form-control" placeholder="bank / card / cash" maxlength="50">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Transaction ID</label>
                            <input type="text" name="transaction_id" class="form-control" placeholder="Optional" maxlength="100">
                        </div>
                    </div>
                    <div class="alert alert-info small py-2">
                        <i class="fas fa-info-circle me-1"></i> Creating an active subscription activates the school and clears its trial end date.
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Save Subscription</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var plan = document.getElementById('subPlan');
        var price = document.getElementById('subPrice');
        if (!plan || !price) return;
        plan.addEventListener('change', function () {
            var option = plan.options[plan.selectedIndex];
            if (option && option.dataset.price) price.value = option.dataset.price;
        });
    })();
</script>

<?php require_once '../includes/footer.php'; ?>
