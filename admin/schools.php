<?php
/**
 * Super Admin — Schools Management
 * Create, edit, activate/deactivate tenant schools, subdomains and plans.
 */
$page_title = 'Schools Management';
require_once '../includes/header.php';
require_once '../includes/saas.php';
requireSuperAdmin();

$errors = [];
$success = '';
$editSchool = null;
$plans = saas_plans();
$statusOptions = ['active' => 'Active', 'trial' => 'Trial', 'suspended' => 'Suspended'];

// ---------------------------------------------------------------- Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);

        if ($action === 'toggle' && $id) {
            $school = school_by_id($pdo, $id);
            if (!$school) {
                $errors[] = 'School not found.';
            } else {
                $newActive = (int)$school['is_active'] === 1 ? 0 : 1;
                $newStatus = $newActive ? 'active' : 'suspended';
                $st = $pdo->prepare("UPDATE schools SET is_active = ?, status = ? WHERE id = ?");
                $st->execute([$newActive, $newStatus, $id]);
                tenant_log($pdo, 'school_toggle', 'school', $id, 'School status changed to ' . $newStatus);
                header('Location: schools.php?success=' . urlencode('School status updated.'));
                exit;
            }
        }

        if ($action === 'save') {
            $name    = trim($_POST['school_name'] ?? '');
            $code    = strtoupper(trim($_POST['school_code'] ?? ''));
            $subdomain = strtolower(trim($_POST['subdomain'] ?? ''));
            $owner   = trim($_POST['owner_name'] ?? '');
            $email   = trim($_POST['school_email'] ?? '');
            $phone   = trim($_POST['school_phone'] ?? '');
            $address = trim($_POST['school_address'] ?? '');
            $motto   = trim($_POST['motto'] ?? '');
            $website = trim($_POST['website'] ?? '');
            $primary = trim($_POST['primary_color'] ?? '#4f46e5');
            $secondary = trim($_POST['secondary_color'] ?? '#06b6d4');
            $plan    = strtolower(trim($_POST['plan'] ?? 'basic'));
            $status  = strtolower(trim($_POST['status'] ?? 'active'));
            $trialEnds = trim($_POST['trial_ends_at'] ?? '');
            $active  = isset($_POST['is_active']) ? 1 : 0;

            if ($name === '') $errors[] = 'School name is required.';
            if (!preg_match('/^[A-Z0-9_-]{3,20}$/', $code)) $errors[] = 'School code must be 3–20 characters (A–Z, 0–9, _ or -).';
            if (!saas_validate_subdomain($subdomain)) $errors[] = 'Subdomain must be 3–50 lowercase letters, numbers or hyphens and cannot be reserved.';
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid school email.';
            if (!saas_valid_plan($plan)) $plan = 'basic';
            if (!isset($statusOptions[$status])) $status = 'active';
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $primary)) $primary = '#4f46e5';
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $secondary)) $secondary = '#06b6d4';
            if ($trialEnds !== '' && strtotime($trialEnds) === false) {
                $errors[] = 'Trial end date is not valid.';
            } elseif ($status === 'trial' && $trialEnds === '') {
                $trialEnds = date('Y-m-d', strtotime('+' . (defined('SAAS_TRIAL_DAYS') ? SAAS_TRIAL_DAYS : 30) . ' days'));
            }
            if ($status === 'trial' && $trialEnds !== '' && strtotime($trialEnds) < strtotime('today')) {
                $errors[] = 'A trial end date cannot be in the past.';
            }
            if ($status === 'active') {
                $trialEnds = '';
                $active = 1;
            } elseif ($status === 'suspended') {
                $active = 0;
            } else {
                $active = 1;
            }

            $dup = $pdo->prepare("SELECT id FROM schools WHERE school_code = ? AND id <> ?");
            $dup->execute([$code, $id]);
            if ($dup->fetchColumn()) $errors[] = 'That school code is already in use.';

            $dupSub = $pdo->prepare("SELECT id FROM schools WHERE subdomain = ? AND id <> ?");
            $dupSub->execute([$subdomain, $id]);
            if ($dupSub->fetchColumn()) $errors[] = 'That subdomain is already in use.';

            if (!$errors) {
                $legacyPlan = saas_legacy_plan($plan);
                if ($status === 'trial' && !isValidIsoDate($trialEnds)) {
                    $errors[] = 'A valid trial end date is required for trial schools.';
                } elseif ($trialEnds !== '' && !isValidIsoDate($trialEnds)) {
                    $errors[] = 'Trial end date is invalid.';
                }

                if (!$errors) {
                    $trialValue = $trialEnds !== '' ? $trialEnds . ' 23:59:59' : null;
                    try {
                        $pdo->beginTransaction();

                        if ($id) {
                            $st = $pdo->prepare(
                                "UPDATE schools
                                 SET school_name=?, school_code=?, subdomain=?, school_email=?, school_phone=?, school_address=?,
                                     owner_name=?, motto=?, website=?, primary_color=?, secondary_color=?,
                                     plan=?, status=?, trial_ends_at=?, is_active=?, subscription_plan=?
                                 WHERE id=?"
                            );
                            $st->execute([
                                $name, $code, $subdomain, $email ?: null, $phone ?: null, $address ?: null,
                                $owner ?: null, $motto ?: null, $website ?: null, $primary, $secondary,
                                $plan, $status, $trialValue, $active, $legacyPlan, $id,
                            ]);
                            tenant_log($pdo, 'school_update', 'school', $id, "Updated school $code");
                            $pdo->commit();
                            header('Location: schools.php?success=' . urlencode('School updated successfully.'));
                            exit;
                        }

                        $st = $pdo->prepare(
                            "INSERT INTO schools
                                (school_name, school_code, subdomain, school_email, school_phone, school_address,
                                 owner_name, motto, website, primary_color, secondary_color, plan, status,
                                 trial_ends_at, is_active, subscription_plan)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                        );
                        $st->execute([
                            $name, $code, $subdomain, $email ?: null, $phone ?: null, $address ?: null,
                            $owner ?: null, $motto ?: null, $website ?: null, $primary, $secondary,
                            $plan, $status, $trialValue, $active, $legacyPlan,
                        ]);
                        $newId = (int)$pdo->lastInsertId();

                        $pdo->prepare(
                            "INSERT INTO school_settings (school_id, public_page_title, public_page_description)
                             VALUES (?,?,?) ON DUPLICATE KEY UPDATE school_id = school_id"
                        )->execute([$newId, $name . ' — Official Portal', 'Welcome to ' . $name . '.']);

                        if ($status !== 'suspended') {
                            $start = date('Y-m-d');
                            $end = $status === 'trial' && $trialEnds !== ''
                                ? $trialEnds
                                : date('Y-m-d', strtotime('+1 year'));
                            $price = $status === 'trial' ? 0 : saas_plan_price($plan);
                            $payment = $status === 'trial' ? 'trial' : 'manual';
                            $pdo->prepare(
                                "INSERT INTO school_subscriptions
                                    (school_id, plan, price, billing_cycle, start_date, end_date, status, payment_method, transaction_id)
                                 VALUES (?,?,?, 'yearly', ?, ?, 'active', ?, ?)"
                            )->execute([$newId, $plan, $price, $start, $end, $payment, 'MANUAL-' . $code]);
                        }

                        tenant_log($pdo, 'school_create', 'school', $newId, "Created school $code");
                        $pdo->commit();
                        header('Location: schools.php?success=' . urlencode('School created successfully.'));
                        exit;
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        error_log('School save failed: ' . $e->getMessage());
                        $errors[] = 'School could not be saved. Please review the details and try again.';
                    }
                }
            }

            // Re-populate form on validation failure
            $editSchool = [
                'id' => $id, 'school_name' => $name, 'school_code' => $code, 'subdomain' => $subdomain,
                'school_email' => $email, 'school_phone' => $phone, 'school_address' => $address,
                'owner_name' => $owner, 'motto' => $motto, 'website' => $website,
                'primary_color' => $primary, 'secondary_color' => $secondary,
                'plan' => $plan, 'status' => $status, 'trial_ends_at' => $trialEnds, 'is_active' => $active,
            ];
        }
    }
}

if (isset($_GET['success'])) $success = $_GET['success'];
if (!$editSchool && isset($_GET['edit'])) {
    $editSchool = school_by_id($pdo, (int)$_GET['edit']);
    if (!$editSchool) $errors[] = 'School not found.';
}

// ---------------------------------------------------------------- Data
$schools = $pdo->query("
    SELECT s.*,
        (SELECT COUNT(*) FROM users u WHERE u.school_id = s.id) AS user_count,
        (SELECT COUNT(*) FROM users u WHERE u.school_id = s.id AND u.role = 'teacher') AS teacher_count,
        (SELECT COUNT(*) FROM students st WHERE st.school_id = s.id) AS student_count,
        (SELECT COUNT(*) FROM tasks t WHERE t.school_id = s.id) AS task_count,
        (SELECT ss.end_date FROM school_subscriptions ss
            WHERE ss.school_id = s.id AND ss.status = 'active'
            ORDER BY ss.end_date DESC, ss.id DESC LIMIT 1) AS subscription_end
    FROM schools s
    ORDER BY s.created_at ASC
")->fetchAll();

$totSchools = count($schools);
$totActive  = count(array_filter($schools, fn($s) => (int)$s['is_active'] === 1));
$totTrials  = count(array_filter($schools, fn($s) => ($s['status'] ?? '') === 'trial'));
$totStudents = array_sum(array_column($schools, 'student_count'));
$totUsers = array_sum(array_column($schools, 'user_count'))
    + (int)$pdo->query("SELECT COUNT(*) FROM users WHERE school_id IS NULL")->fetchColumn();
$mrr = saas_monthly_recurring_revenue($pdo);
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
            <div class="kpi-icon icon-indigo"><i class="fas fa-school"></i></div>
            <div class="kpi-lbl">Total Schools</div>
            <div class="kpi-val"><?php echo $totSchools; ?></div>
            <div class="kpi-sub"><?php echo $totActive; ?> active · <?php echo $totTrials; ?> on trial</div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="kpi-card kpi-accent-cyan">
            <div class="kpi-icon icon-cyan"><i class="fas fa-users"></i></div>
            <div class="kpi-lbl">Platform Users</div>
            <div class="kpi-val"><?php echo number_format($totUsers); ?></div>
            <div class="kpi-sub">across all schools</div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="kpi-card kpi-accent-emerald">
            <div class="kpi-icon icon-emerald"><i class="fas fa-user-graduate"></i></div>
            <div class="kpi-lbl">Students</div>
            <div class="kpi-val"><?php echo number_format($totStudents); ?></div>
            <div class="kpi-sub">enrolled platform-wide</div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="kpi-card kpi-accent-amber">
            <div class="kpi-icon icon-amber"><i class="fas fa-sack-dollar"></i></div>
            <div class="kpi-lbl">Monthly Recurring Revenue</div>
            <div class="kpi-val">$<?php echo number_format($mrr, 0); ?></div>
            <div class="kpi-sub"><a href="subscriptions.php" class="text-decoration-none">Manage subscriptions <i class="fas fa-arrow-right ms-1"></i></a></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="fas fa-school me-2 text-primary"></i>Schools (<?php echo $totSchools; ?>)</span>
                <div class="d-flex gap-2">
                    <a href="../public/index.php" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fas fa-globe me-1"></i> Directory</a>
                    <a href="schools.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-sync-alt me-1"></i> Refresh</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr><th>School</th><th>Plan</th><th>Status</th><th class="text-center">Users / Students</th><th class="text-end">Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($schools as $s): ?>
                            <?php
                            $status = $s['status'] ?? ((int)$s['is_active'] === 1 ? 'active' : 'suspended');
                            $statusClass = $status === 'active' ? 'success' : ($status === 'trial' ? 'warning' : 'secondary');
                            $planClass = ($s['plan'] ?? 'basic') === 'enterprise' ? 'primary' : (($s['plan'] ?? 'basic') === 'pro' ? 'info' : 'secondary');
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="school-dot" style="background:<?php echo e(tenant_safe_color($s['primary_color'])); ?>"></span>
                                        <div>
                                            <div class="fw-semibold"><?php echo e($s['school_name']); ?></div>
                                            <div class="small text-muted">
                                                <?php echo e($s['school_code']); ?>
                                                <?php if (!empty($s['subdomain'])): ?>
                                                    · <a href="../public/school.php?code=<?php echo urlencode($s['school_code']); ?>" target="_blank" class="text-decoration-none"><?php echo e($s['subdomain']); ?></a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-<?php echo $planClass; ?>"><?php echo e(saas_plan_label($s['plan'] ?? 'basic')); ?></span></td>
                                <td>
                                    <span class="badge bg-<?php echo $statusClass; ?>"><?php echo e(ucfirst($status)); ?></span>
                                    <?php if ($status === 'trial' && !empty($s['trial_ends_at'])): ?>
                                        <div class="small text-muted">ends <?php echo e(date('M j, Y', strtotime($s['trial_ends_at']))); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center text-nowrap"><?php echo (int)$s['user_count']; ?> / <?php echo (int)$s['student_count']; ?></td>
                                <td class="text-end text-nowrap">
                                    <a href="../super-admin/school.php?id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Details"><i class="fas fa-eye"></i></a>
                                    <a href="../public/school.php?code=<?php echo urlencode($s['school_code']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Public page"><i class="fas fa-external-link-alt"></i></a>
                                    <a href="schools.php?edit=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Change status for <?php echo e($s['school_name']); ?>?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                                        <button class="btn btn-sm btn-outline-warning" title="Toggle status"><i class="fas fa-power-off"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$schools): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No schools yet. Create the first one on the right.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="card">
            <div class="card-header">
                <span><i class="fas <?php echo $editSchool ? 'fa-edit' : 'fa-plus-circle'; ?> me-2 text-primary"></i><?php echo $editSchool ? 'Edit School' : 'Create School'; ?></span>
            </div>
            <div class="card-body">
                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?php echo (int)($editSchool['id'] ?? 0); ?>">

                    <div class="mb-3">
                        <label class="form-label">School Name <span class="text-danger">*</span></label>
                        <input type="text" name="school_name" class="form-control" required value="<?php echo e($editSchool['school_name'] ?? ''); ?>" placeholder="e.g. Green Valley High School">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">School Code <span class="text-danger">*</span></label>
                            <input type="text" name="school_code" class="form-control text-uppercase" required value="<?php echo e($editSchool['school_code'] ?? ''); ?>" placeholder="SCH003" maxlength="20">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Subdomain <span class="text-danger">*</span></label>
                            <input type="text" name="subdomain" class="form-control" required value="<?php echo e($editSchool['subdomain'] ?? ''); ?>" placeholder="greenvalley" maxlength="50">
                        </div>
                    </div>
                    <div class="form-text mb-3">Subdomain is used for the school portal, e.g. greenvalley.<?php echo defined('APP_DOMAIN') && APP_DOMAIN !== '' ? e(APP_DOMAIN) : 'your-domain.com'; ?>.</div>

                    <div class="mb-3">
                        <label class="form-label">Owner / Principal</label>
                        <input type="text" name="owner_name" class="form-control" value="<?php echo e($editSchool['owner_name'] ?? ''); ?>" placeholder="Full name">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="school_email" class="form-control" value="<?php echo e($editSchool['school_email'] ?? ''); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="school_phone" class="form-control" value="<?php echo e($editSchool['school_phone'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="school_address" class="form-control" rows="2"><?php echo e($editSchool['school_address'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Motto</label>
                        <input type="text" name="motto" class="form-control" value="<?php echo e($editSchool['motto'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Website</label>
                        <input type="url" name="website" class="form-control" value="<?php echo e($editSchool['website'] ?? ''); ?>" placeholder="https://...">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Primary Colour</label>
                            <input type="color" name="primary_color" class="form-control form-control-color w-100" value="<?php echo e($editSchool['primary_color'] ?? '#4f46e5'); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Secondary Colour</label>
                            <input type="color" name="secondary_color" class="form-control form-control-color w-100" value="<?php echo e($editSchool['secondary_color'] ?? '#06b6d4'); ?>">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Plan</label>
                            <select name="plan" class="form-select">
                                <?php foreach ($plans as $planKey => $plan): ?>
                                    <option value="<?php echo e($planKey); ?>" <?php echo ($editSchool['plan'] ?? 'basic') === $planKey ? 'selected' : ''; ?>><?php echo e($plan['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <?php foreach ($statusOptions as $statusKey => $statusLabel): ?>
                                    <option value="<?php echo e($statusKey); ?>" <?php echo ($editSchool['status'] ?? 'active') === $statusKey ? 'selected' : ''; ?>><?php echo e($statusLabel); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Trial Ends</label>
                        <input type="date" name="trial_ends_at" class="form-control" value="<?php echo e(!empty($editSchool['trial_ends_at']) ? date('Y-m-d', strtotime($editSchool['trial_ends_at'])) : ''); ?>">
                        <div class="form-text">Only used when status is Trial. Leave blank for a 30-day trial.</div>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" <?php echo !isset($editSchool['is_active']) || $editSchool['is_active'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="isActive">School is active</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill"><i class="fas fa-save me-1"></i><?php echo $editSchool ? 'Save Changes' : 'Create School'; ?></button>
                        <?php if ($editSchool): ?><a href="schools.php" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.school-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; flex-shrink: 0; box-shadow: 0 0 0 3px rgba(29,53,87,.12); }
</style>

<?php require_once '../includes/footer.php'; ?>
