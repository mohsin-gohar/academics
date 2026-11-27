<?php
/**
 * Sent Messages
 * EduSphere Enterprise School Management System
 */
$page_title = 'Sent Messages';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';
requireAuth();
// C4: validate live session (account/role/school)
enforceSession($pdo);

$userId = $_SESSION['user_id'];
$schoolId = currentSchoolId();

$sql = "SELECT m.*, u.full_name as receiver_name, u.role as receiver_role
        FROM messages m
        JOIN users u ON m.receiver_id = u.id
        WHERE m.sender_id = ?";
$params = [$userId];
if ($schoolId) {
    $sql .= " AND (m.school_id = ? OR u.role = 'super_admin')";
    $params[] = $schoolId;
}
$sql .= " ORDER BY m.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-white"><i class="fas fa-paper-plane text-primary me-2"></i>Sent Messages</h1>
            <p class="text-secondary small mb-0">History of messages you have sent to other users.</p>
        </div>
        <div>
            <a href="chat.php" class="btn btn-primary btn-sm me-1"><i class="fas fa-comment-dots me-1"></i>Chat View</a>
            <a href="received.php" class="btn btn-outline-info btn-sm"><i class="fas fa-inbox me-1"></i>Inbox</a>
        </div>
    </div>

    <div class="card bg-dark text-white border-secondary">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Recipient</th>
                        <th>Role</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Sent Date & Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($messages)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-secondary">No sent messages found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($messages as $m): ?>
                            <tr>
                                <td class="fw-bold"><?php echo e($m['receiver_name']); ?></td>
                                <td><span class="badge bg-secondary"><?php echo e(ucfirst($m['receiver_role'])); ?></span></td>
                                <td><?php echo e(mb_strimwidth($m['content'], 0, 80, '...')); ?></td>
                                <td>
                                    <?php if ($m['read_status']): ?>
                                        <span class="badge bg-success"><i class="fas fa-check-double me-1"></i>Read</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="fas fa-check me-1"></i>Sent</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-secondary"><?php echo e(date('M d, Y h:i A', strtotime($m['created_at']))); ?></td>
                                <td>
                                    <a href="chat.php?user_id=<?php echo (int)$m['receiver_id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-comment me-1"></i> Open Chat
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
