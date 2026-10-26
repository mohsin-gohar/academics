<?php
$page_title = 'Settings';
require_once '../includes/header.php';
requireAdmin();

$success = '';
$errors = [];

// Fetch current settings from DB
$settingsRows = $pdo->query("SELECT setting_key, setting_value FROM application_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$appName = $settingsRows['app_name'] ?? APP_NAME;
$recordsPerPage = $settingsRows['records_per_page'] ?? '10';
$timezone = $settingsRows['timezone'] ?? APP_TIMEZONE;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $newAppName = trim($_POST['app_name'] ?? '');
        $newRecordsPerPage = max(5, min(100, (int)($_POST['records_per_page'] ?? 10)));
        $newTimezone = trim($_POST['timezone'] ?? 'Asia/Karachi');

        if (empty($newAppName)) {
            $errors[] = 'Application Name is required.';
        }

        if (empty($errors)) {
            try {
                $upd = $pdo->prepare("INSERT INTO application_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
                $upd->execute(['app_name', $newAppName]);
                $upd->execute(['records_per_page', (string)$newRecordsPerPage]);
                $upd->execute(['timezone', $newTimezone]);

                $appName = $newAppName;
                $recordsPerPage = (string)$newRecordsPerPage;
                $timezone = $newTimezone;

                $success = 'Application settings updated successfully!';
                logActivity($pdo, $_SESSION['user_id'], 'settings', 'system', null, 'Updated application settings');
            } catch (PDOException $e) {
                error_log("Settings update failed: " . $e->getMessage());
                $errors[] = 'Failed to save settings.';
            }
        }
    }
}
?>

<div class="card">
    <div class="card-header"><i class="fas fa-cog me-2 text-primary"></i>Application Settings</div>
    <div class="card-body">
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo e($success); ?></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?php echo e($err); ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <div class="mb-3">
                <label class="form-label">Application Name *</label>
                <input type="text" name="app_name" class="form-control" value="<?php echo e($appName); ?>" required>
                <small class="text-muted">Displayed in header, emails, and brand identity.</small>
            </div>
            <div class="mb-3">
                <label class="form-label">Default Records Per Page</label>
                <input type="number" name="records_per_page" class="form-control" value="<?php echo e($recordsPerPage); ?>" min="5" max="100">
                <small class="text-muted">Pagination limit for tasks, students, and courses.</small>
            </div>
            <div class="mb-3">
                <label class="form-label">System Timezone</label>
                <input type="text" name="timezone" class="form-control" value="<?php echo e($timezone); ?>">
                <small class="text-muted">Current PHP timezone: <?php echo date_default_timezone_get(); ?></small>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Settings</button>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><i class="fas fa-info-circle me-2 text-info"></i>System Information & Health</div>
    <div class="card-body">
        <p class="mb-1"><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
        <p class="mb-1"><strong>Web Server:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Apache/XAMPP'; ?></p>
        <p class="mb-1"><strong>Database Engine:</strong> MySQL / MariaDB (InnoDB, utf8mb4)</p>
        <p class="mb-1"><strong>Active Timezone:</strong> <?php echo date_default_timezone_get(); ?></p>
        <p class="mb-0"><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
