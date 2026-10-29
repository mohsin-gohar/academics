<?php
/**
 * Admin — Public Contact Messages
 * Super admins see every message; school admins see their school only.
 */
$page_title = 'Contact Messages';
require_once '../includes/header.php';
require_once '../includes/saas.php';
requireAdmin();

$isSuper = isSuperAdmin();
$schoolId = currentSchoolId();
$errors = [];
$success = $_GET['success'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $status = strtolower(trim($_POST['status'] ?? 'read'));
        if (!in_array($status, ['new', 'read', 'archived'], true)) {
            $status = 'read';
        }
        if ($id > 0) {
            if ($isSuper) {
                $st = $pdo->prepare("UPDATE contact_messages SET status = ? WHERE id = ?");
                $st->execute([$status, $id]);
            } else {
                $st = $pdo->prepare("UPDATE contact_messages SET status = ? WHERE id = ? AND school_id = ?");
                $st->execute([$status, $id, $schoolId]);
            }
            header('Location: messages.php?success=' . urlencode('Message marked ' . $status . '.'));
            exit;
        }
    }
}

$where = '';
$params = [];
if (!$isSuper) {
    $where = 'WHERE cm.school_id = ?';
    $params[] = $schoolId;
}

$stmt = $pdo->prepare("
    SELECT cm.*, s.school_name, s.school_code
    FROM contact_messages cm
    LEFT JOIN schools s ON s.id = cm.school_id
    {$where}
    ORDER BY cm.created_at DESC, cm.id DESC
    LIMIT 200
");
$stmt->execute($params);
$messages = $stmt->fetchAll();

$newCount = 0;
$readCount = 0;
$archivedCount = 0;
foreach ($messages as $message) {
    if ($message['status'] === 'new') $newCount++;
    elseif ($message['status'] === 'read') $readCount++;
    else $archivedCount++;
}
?>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?php echo e($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo e($err); ?></div>
<?php endforeach; ?>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="kpi-card kpi-accent-indigo">
            <div class="kpi-icon icon-indigo"><i class="fas fa-envelope"></i></div>
            <div class="kpi-lbl">New Messages</div>
            <div class="kpi-val"><?php echo $newCount; ?></div>
            <div class="kpi-sub">waiting for a reply</div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="kpi-card kpi-accent-cyan">
            <div class="kpi-icon icon-cyan"><i class="fas fa-envelope-open"></i></div>
            <div class="kpi-lbl">Read</div>
            <div class="kpi-val"><?php echo $readCount; ?></div>
            <div class="kpi-sub">reviewed messages</div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="kpi-card kpi-accent-amber">
            <div class="kpi-icon icon-amber"><i class="fas fa-box-archive"></i></div>
            <div class="kpi-lbl">Archived</div>
            <div class="kpi-val"><?php echo $archivedCount; ?></div>
            <div class="kpi-sub">closed conversations</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-inbox me-2 text-primary"></i>Contact Messages (<?php echo count($messages); ?>)</span>
        <a href="<?php echo $isSuper ? 'schools.php' : '../dashboard.php'; ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Back</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>From</th>
                        <?php if ($isSuper): ?><th>School</th><?php endif; ?>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Received</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($messages as $message): ?>
                    <?php $badge = $message['status'] === 'new' ? 'warning' : ($message['status'] === 'read' ? 'info' : 'secondary'); ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?php echo e($message['name']); ?></div>
                            <div class="small text-muted"><a href="mailto:<?php echo e($message['email']); ?>" class="text-decoration-none"><?php echo e($message['email']); ?></a></div>
                        </td>
                        <?php if ($isSuper): ?>
                            <td>
                                <?php if (!empty($message['school_name'])): ?>
                                    <div class="small fw-semibold"><?php echo e($message['school_name']); ?></div>
                                    <div class="small text-muted"><?php echo e($message['school_code']); ?></div>
                                <?php else: ?>
                                    <span class="text-muted small">Platform enquiry</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                        <td>
                            <div class="fw-semibold"><?php echo e($message['subject']); ?></div>
                            <details class="small text-muted mt-1">
                                <summary style="cursor:pointer;">View message</summary>
                                <div class="mt-2 p-2 rounded" style="background:var(--surface-2,#f6f3ff);white-space:pre-wrap;"><?php echo e($message['message']); ?></div>
                            </details>
                        </td>
                        <td><span class="badge bg-<?php echo $badge; ?>"><?php echo e(ucfirst($message['status'])); ?></span></td>
                        <td class="small text-muted"><?php echo e(date('M j, Y g:i A', strtotime($message['created_at']))); ?></td>
                        <td class="text-end text-nowrap">
                            <?php if ($message['status'] !== 'read'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                    <input type="hidden" name="id" value="<?php echo (int)$message['id']; ?>">
                                    <input type="hidden" name="status" value="read">
                                    <button class="btn btn-sm btn-outline-primary" title="Mark read"><i class="fas fa-check"></i></button>
                                </form>
                            <?php endif; ?>
                            <?php if ($message['status'] !== 'archived'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                    <input type="hidden" name="id" value="<?php echo (int)$message['id']; ?>">
                                    <input type="hidden" name="status" value="archived">
                                    <button class="btn btn-sm btn-outline-secondary" title="Archive"><i class="fas fa-box-archive"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$messages): ?>
                    <tr><td colspan="<?php echo $isSuper ? 6 : 5; ?>" class="text-center text-muted py-4">No contact messages yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
