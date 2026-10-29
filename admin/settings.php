<?php
/**
 * Settings — School branding + platform configuration.
 * School managers edit their own school only; the platform admin sees
 * application-wide settings and can manage schools from admin/schools.php.
 */
$page_title = 'Settings';
require_once '../includes/header.php';
requireSchoolManagement();

$schoolId = currentSchoolId();
$school   = $schoolId ? currentSchool($pdo) : null;
$isPlatformAdmin = isSuperAdmin();
$success = '';
$errors  = [];

/* ------------------------------------------------------------------ POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $section = $_POST['section'] ?? ($schoolId ? 'school' : 'platform');

        /* ---------------- School branding ---------------- */
        if ($section === 'school' && $schoolId) {
            $name      = trim($_POST['school_name'] ?? '');
            $email     = trim($_POST['school_email'] ?? '');
            $phone     = trim($_POST['school_phone'] ?? '');
            $address   = trim($_POST['school_address'] ?? '');
            $motto     = trim($_POST['motto'] ?? '');
            $website   = trim($_POST['website'] ?? '');
            $primary   = trim($_POST['primary_color'] ?? '#4f46e5');
            $secondary = trim($_POST['secondary_color'] ?? '#06b6d4');
            $pubTitle  = trim($_POST['public_page_title'] ?? '');
            $pubDesc   = trim($_POST['public_page_description'] ?? '');
            $timeout   = max(5, min(240, (int)($_POST['session_timeout_minutes'] ?? 30)));
            $allowAuto = isset($_POST['allow_parent_auto_login']) ? 1 : 0;

            if ($name === '') $errors[] = 'School name is required.';
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid school email.';
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $primary))   $primary = '#4f46e5';
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $secondary)) $secondary = '#06b6d4';

            if (empty($errors)) {
                try {
                    $st = $pdo->prepare("UPDATE schools SET school_name=?, school_email=?, school_phone=?, school_address=?, motto=?, website=?, primary_color=?, secondary_color=? WHERE id=?");
                    $st->execute([$name, $email ?: null, $phone ?: null, $address ?: null, $motto ?: null, $website ?: null, $primary, $secondary, $schoolId]);

                    $st = $pdo->prepare("INSERT INTO school_settings
                        (school_id, public_page_title, public_page_description, allow_parent_auto_login, session_timeout_minutes)
                        VALUES (?,?,?,?,?)
                        ON DUPLICATE KEY UPDATE public_page_title=VALUES(public_page_title),
                            public_page_description=VALUES(public_page_description),
                            allow_parent_auto_login=VALUES(allow_parent_auto_login),
                            session_timeout_minutes=VALUES(session_timeout_minutes)");
                    $st->execute([$schoolId, $pubTitle ?: null, $pubDesc ?: null, $allowAuto, $timeout]);

                    tenant_log($pdo, 'school_settings_update', 'school', $schoolId, 'Updated school branding and public page');
                    $success = 'School settings updated successfully!';
                    $school = currentSchool($pdo); // refresh (static cache may hold old row)
                } catch (PDOException $e) {
                    error_log('School settings update failed: ' . $e->getMessage());
                    $errors[] = 'Failed to save school settings.';
                }
            }
        }

        /* ---------------- Platform settings (super admin) ---------------- */
        if ($section === 'platform' && $isPlatformAdmin) {
            $newAppName = trim($_POST['app_name'] ?? '');
            $newRecords = max(5, min(100, (int)($_POST['records_per_page'] ?? 10)));
            $newTz      = trim($_POST['timezone'] ?? 'Asia/Karachi');
            if ($newAppName === '') $errors[] = 'Application name is required.';
            if (empty($errors)) {
                try {
                    $upd = $pdo->prepare("INSERT INTO application_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
                    $upd->execute(['app_name', $newAppName]);
                    $upd->execute(['records_per_page', (string)$newRecords]);
                    $upd->execute(['timezone', $newTz]);
                    $success = 'Application settings updated successfully!';
                    logActivity($pdo, $_SESSION['user_id'], 'settings', 'system', null, 'Updated application settings');
                } catch (PDOException $e) {
                    error_log('Settings update failed: ' . $e->getMessage());
                    $errors[] = 'Failed to save settings.';
                }
            }
        }
    }
}

/* --------------------------------------------------------------- Fetch */
$settingsRows    = $pdo->query("SELECT setting_key, setting_value FROM application_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$appName         = $settingsRows['app_name'] ?? APP_NAME;
$recordsPerPage  = $settingsRows['records_per_page'] ?? '10';
$timezone        = $settingsRows['timezone'] ?? APP_TIMEZONE;
?>

<p class="text-muted small mb-4"><i class="fas fa-sliders me-1"></i>Manage <?php echo $school ? e($school['school_name']) : 'platform'; ?> branding, public page and system preferences.</p>

<?php if ($success): ?>
    <div class="alert alert-success border-0 shadow-sm rounded-4"><i class="fas fa-circle-check me-2"></i><?php echo e($success); ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger border-0 shadow-sm rounded-4"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?php echo e($err); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if ($school): ?>
<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="chart-card">
            <div class="section-head">
                <div class="sh-left">
                    <span class="head-icon indigo"><i class="fas fa-school"></i></span>
                    <div><h6 class="chart-title">School Branding</h6><span class="chart-sub">Identity shown across the portal</span></div>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="section" value="school">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">School Name *</label>
                        <input type="text" name="school_name" class="form-control" value="<?php echo e($school['school_name']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">School Code</label>
                        <input type="text" class="form-control" value="<?php echo e($school['school_code']); ?>" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">School Email</label>
                        <input type="email" name="school_email" class="form-control" value="<?php echo e($school['school_email']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">School Phone</label>
                        <input type="text" name="school_phone" class="form-control" value="<?php echo e($school['school_phone']); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <input type="text" name="school_address" class="form-control" value="<?php echo e($school['school_address']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Motto</label>
                        <input type="text" name="motto" class="form-control" value="<?php echo e($school['motto']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Website</label>
                        <input type="url" name="website" class="form-control" value="<?php echo e($school['website']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Primary Color</label>
                        <input type="color" name="primary_color" class="form-control form-control-color w-100" value="<?php echo e($school['primary_color'] ?: '#4f46e5'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Secondary Color</label>
                        <input type="color" name="secondary_color" class="form-control form-control-color w-100" value="<?php echo e($school['secondary_color'] ?: '#06b6d4'); ?>">
                    </div>
                </div>

                <hr class="my-4">
                <h6 class="fw-bold mb-3"><i class="fas fa-globe me-2 text-primary"></i>Public Page</h6>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Public Page Title</label>
                        <input type="text" name="public_page_title" class="form-control" value="<?php echo e($school['public_page_title'] ?? ''); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Public Page Description</label>
                        <textarea name="public_page_description" class="form-control" rows="3"><?php echo e($school['public_page_description'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Session Timeout (minutes)</label>
                        <input type="number" name="session_timeout_minutes" class="form-control" min="5" max="240" value="<?php echo (int)($school['session_timeout_minutes'] ?? 30); ?>">
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="allow_parent_auto_login" id="allowParentAuto" value="1" <?php echo !empty($school['allow_parent_auto_login']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="allowParentAuto">Allow parent auto-login links</label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-4"><i class="fas fa-save me-1"></i> Save School Settings</button>
            </form>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="chart-card mb-4">
            <div class="section-head">
                <div class="sh-left">
                    <span class="head-icon amber"><i class="fas fa-circle-info"></i></span>
                    <div><h6 class="chart-title">System Information</h6><span class="chart-sub">Runtime health</span></div>
                </div>
            </div>
            <p class="mb-1 small"><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
            <p class="mb-1 small"><strong>Web Server:</strong> <?php echo e($_SERVER['SERVER_SOFTWARE'] ?? 'Apache/XAMPP'); ?></p>
            <p class="mb-1 small"><strong>Database:</strong> MySQL / MariaDB (InnoDB, utf8mb4)</p>
            <p class="mb-1 small"><strong>Timezone:</strong> <?php echo e(date_default_timezone_get()); ?></p>
            <p class="mb-0 small"><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
        <div class="chart-card">
            <div class="section-head">
                <div class="sh-left">
                    <span class="head-icon emerald"><i class="fas fa-link"></i></span>
                    <div><h6 class="chart-title">Quick Links</h6><span class="chart-sub">School management</span></div>
                </div>
            </div>
            <div class="d-grid gap-2">
                <a href="../public/school.php?code=<?php echo urlencode($school['school_code']); ?>" target="_blank" class="btn btn-outline-primary"><i class="fas fa-arrow-up-right-from-square me-2"></i>View Public Page</a>
                <a href="users.php" class="btn btn-outline-secondary"><i class="fas fa-users me-2"></i>Manage Users</a>
                <a href="../events/index.php" class="btn btn-outline-secondary"><i class="fas fa-calendar-days me-2"></i>Manage Events</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($isPlatformAdmin): ?>
<div class="chart-card mt-4">
    <div class="section-head">
        <div class="sh-left">
            <span class="head-icon cyan"><i class="fas fa-sliders"></i></span>
            <div><h6 class="chart-title">Platform Settings</h6><span class="chart-sub">Application-wide defaults</span></div>
        </div>
    </div>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        <input type="hidden" name="section" value="platform">
        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label">Application Name *</label>
                <input type="text" name="app_name" class="form-control" value="<?php echo e($appName); ?>" required>
                <small class="text-muted">Displayed in header, emails and brand identity.</small>
            </div>
            <div class="col-md-3">
                <label class="form-label">Records Per Page</label>
                <input type="number" name="records_per_page" class="form-control" min="5" max="100" value="<?php echo e($recordsPerPage); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">System Timezone</label>
                <input type="text" name="timezone" class="form-control" value="<?php echo e($timezone); ?>">
                <small class="text-muted">PHP timezone: <?php echo e(date_default_timezone_get()); ?></small>
            </div>
        </div>
        <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-save me-1"></i> Save Platform Settings</button>
    </form>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
