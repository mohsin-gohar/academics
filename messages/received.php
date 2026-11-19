<?php
/**
 * Received Messages (Inbox)
 * EduSphere Enterprise School Management System
 */
$page_title = 'Received Messages';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';
requireAuth();
// C4: validate live session (account/role/school)
enforceSession($pdo);

$userId = $_SESSION['user_id'];
$schoolId = currentSchoolId();

$stmt = $pdo->prepare("SELECT m.*, u.full_name as sender_name, u.role as sender_role
                       FROM messages m
                       JOIN users u ON m.sender_id = u.id
                       WHERE m.receiver_id = ?
                       ORDER BY m.created_at DESC");
$stmt->execute([$userId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-white"><i class="fas fa-inbox text-primary me-2"></i>Inbox</h1>
            <p class="text-secondary small mb-0">Messages sent to you from teachers, parents, and administration.</p>
        </div>
        <div>
            <a href="chat.php" class="btn btn-primary btn-sm me-1"><i class="fas fa-comment-dots me-1"></i>Chat View</a>
            <a href="sent.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-paper-plane me-1"></i>Sent Messages</a>
        </div>
    </div>

    <div class="card bg-dark text-white border-secondary">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Sender</th>
                        <th>Role</th>
                        <th>Message</th>
                        <th>Date & Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($messages)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-secondary">No received messages.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($messages as $m): ?>
                            <tr>
                                <td>
                                    <?php if ($m['read_status']): ?>
                                        <span class="badge bg-secondary">Read</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">New</span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold"><?php echo e($m['sender_name']); ?></td>
                                <td><span class="badge bg-info text-dark"><?php echo e(ucfirst($m['sender_role'])); ?></span></td>
                                <td><?php echo e(mb_strimwidth($m['content'], 0, 80, '...')); ?></td>
                                <td class="small text-secondary"><?php echo e(date('M d, Y h:i A', strtotime($m['created_at']))); ?></td>
                                <td>
                                    <a href="chat.php?user_id=<?php echo (int)$m['sender_id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-reply me-1"></i> Reply
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
