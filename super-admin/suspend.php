<?php
/**
 * Super Admin — Activate/Suspend a school (POST only, CSRF protected).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/saas.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';
requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/schools.php');
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    header('Location: ../admin/schools.php?success=' . urlencode('Invalid security token.'));
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
$school = $id > 0 ? school_by_id($pdo, $id) : null;
if (!$school || !in_array($action, ['activate', 'suspend'], true)) {
    header('Location: ../admin/schools.php');
    exit;
}

if ($action === 'suspend') {
    $stmt = $pdo->prepare("UPDATE schools SET is_active = 0, status = 'suspended' WHERE id = ?");
    $stmt->execute([$id]);
    tenant_log($pdo, 'school_suspend', 'school', $id, 'School suspended by platform admin');
    $message = 'School suspended.';
} else {
    $stmt = $pdo->prepare("UPDATE schools SET is_active = 1, status = 'active', trial_ends_at = NULL WHERE id = ?");
    $stmt->execute([$id]);
    tenant_log($pdo, 'school_activate', 'school', $id, 'School activated by platform admin');
    $message = 'School activated.';
}

header('Location: school.php?id=' . $id . '&success=' . urlencode($message));
exit;
