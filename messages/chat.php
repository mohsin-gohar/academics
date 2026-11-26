<?php
/**
 * Internal Messaging / Chat Module
 * EduSphere Enterprise School Management System
 */
$page_title = 'Messages';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';
requireAuth();
// C4: validate the live session (account active, role, school active)
enforceSession($pdo);

$userId = $_SESSION['user_id'];
$schoolId = currentSchoolId();
$isPlatformAdmin = isSuperAdmin();
$errors = [];

/**
 * H2: messaging must be confined to a single school. A school-scoped user can
 * only reach other active users of the SAME school; a platform super admin can
 * reach school administrators. No `OR school_id IS NULL` widening.
 */
function chat_can_message(PDO $pdo, int $fromId, int $toId, bool $isPlatformAdmin): bool
{
    if ($fromId === $toId) {
        return false;
    }
    $stmt = $pdo->prepare("SELECT id, is_active, school_id, role FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$toId]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$target || (int)$target['is_active'] !== 1) {
        return false;
    }
    if ($isPlatformAdmin) {
        return $target['role'] === 'school_admin';
    }
    $me = $pdo->prepare("SELECT school_id FROM users WHERE id = ? LIMIT 1");
    $me->execute([$fromId]);
    $mySchool = $me->fetchColumn();
    return $mySchool !== false && $mySchool !== null && (int)$target['school_id'] === (int)$mySchool;
}

$activeContactId = (int)($_GET['user_id'] ?? 0);

// Handle new message post
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $receiver_id = (int)($_POST['receiver_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $type = in_array(getUserRole(), ['parent', 'student']) ? 'Parent' : 'Teacher';

        if ($receiver_id <= 0 || empty($content)) {
            $errors[] = 'Message content and recipient are required.';
        } elseif (!chat_can_message($pdo, $userId, $receiver_id, $isPlatformAdmin)) {
            http_response_code(403);
            $errors[] = 'You cannot message that user.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO messages (school_id, sender_id, receiver_id, content, type, read_status)
                                   VALUES (?, ?, ?, ?, ?, 0)");
            $stmt->execute([$isPlatformAdmin ? null : $schoolId, $userId, $receiver_id, $content, $type]);
            logActivity($pdo, $userId, 'send_message', 'message', null, 'Message sent to user #' . $receiver_id);
            redirect("chat.php?user_id=" . $receiver_id);
        }
    }
}

// Contacts list — always scoped to the caller's reachable set.
if ($isPlatformAdmin) {
    $contactsStmt = $pdo->prepare("SELECT id, full_name, role, email FROM users WHERE id != ? AND is_active = 1 AND role = 'school_admin' ORDER BY full_name ASC");
    $contactsStmt->execute([$userId]);
} else {
    $contactsStmt = $pdo->prepare("SELECT id, full_name, role, email FROM users WHERE id != ? AND is_active = 1 AND school_id = ? ORDER BY full_name ASC");
    $contactsStmt->execute([$userId, $schoolId]);
}
$contacts = $contactsStmt->fetchAll(PDO::FETCH_ASSOC);

// H2: a manually supplied ?user_id= must be reachable; otherwise ignore it.
if ($activeContactId > 0 && !chat_can_message($pdo, $userId, $activeContactId, $isPlatformAdmin)) {
    $activeContactId = 0;
}

if ($activeContactId === 0 && !empty($contacts)) {
    $activeContactId = (int)$contacts[0]['id'];
}

// Mark messages as read from active contact
if ($activeContactId > 0) {
    $markRead = $pdo->prepare("UPDATE messages SET read_status = 1 WHERE receiver_id = ? AND sender_id = ?");
    $markRead->execute([$userId, $activeContactId]);

    // Fetch conversation messages
    $msgStmt = $pdo->prepare("SELECT m.*, u.full_name as sender_name
                              FROM messages m
                              JOIN users u ON m.sender_id = u.id
                              WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
                              ORDER BY m.created_at ASC");
    $msgStmt->execute([$userId, $activeContactId, $activeContactId, $userId]);
    $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

    // Active contact details
    $activeUserStmt = $pdo->prepare("SELECT full_name, role FROM users WHERE id = ?");
    $activeUserStmt->execute([$activeContactId]);
    $activeContact = $activeUserStmt->fetch(PDO::FETCH_ASSOC);
} else {
    $messages = [];
    $activeContact = null;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4 py-3">
    <div class="row g-3">
        <!-- Contacts list -->
        <div class="col-md-4 col-lg-3">
            <div class="card bg-dark text-white border-secondary h-100">
                <div class="card-header border-secondary bg-secondary bg-opacity-25 fw-bold">
                    <i class="fas fa-users me-2 text-primary"></i>Contacts
                </div>
                <div class="list-group list-group-flush overflow-auto" style="max-height: 600px;">
                    <?php if (empty($contacts)): ?>
                        <div class="p-3 text-secondary text-center small">No other users found</div>
                    <?php else: ?>
                        <?php foreach ($contacts as $c): ?>
                            <a href="<?php echo e($base); ?>messages/chat.php?user_id=<?php echo (int)$c['id']; ?>" class="list-group-item list-group-item-action bg-transparent text-white border-secondary <?php echo ($activeContactId === (int)$c['id']) ? 'active bg-primary' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold"><?php echo e($c['full_name']); ?></span>
                                    <span class="badge bg-secondary"><?php echo e(ucfirst($c['role'])); ?></span>
                                </div>
                                <small class="text-secondary opacity-75"><?php echo e($c['email']); ?></small>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Conversation area -->
        <div class="col-md-8 col-lg-9">
            <div class="card bg-dark text-white border-secondary h-100 d-flex flex-column">
                <div class="card-header border-secondary bg-secondary bg-opacity-25 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fw-bold"><?php echo e($activeContact['full_name'] ?? 'Select a Contact'); ?></span>
                        <?php if ($activeContact): ?>
                            <span class="badge bg-info ms-2"><?php echo e(ucfirst($activeContact['role'])); ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <a href="<?php echo e($base); ?>messages/received.php" class="btn btn-outline-info btn-sm me-1"><i class="fas fa-inbox me-1"></i>Inbox</a>
                        <a href="<?php echo e($base); ?>messages/sent.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-paper-plane me-1"></i>Sent</a>
                    </div>
                </div>

                <div class="card-body flex-grow-1 overflow-auto p-3" style="max-height: 480px; min-height: 380px;">
                    <?php if (empty($messages)): ?>
                        <div class="text-center text-secondary py-5">
                            <i class="fas fa-comments fa-3x mb-3 opacity-25"></i>
                            <p>No messages yet. Send a message to start the conversation.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $m): ?>
                            <?php $isMine = ((int)$m['sender_id'] === $userId); ?>
                            <div class="d-flex mb-3 <?php echo $isMine ? 'justify-content-end' : 'justify-content-start'; ?>">
                                <div class="p-3 rounded-3 <?php echo $isMine ? 'bg-primary text-white' : 'bg-secondary bg-opacity-50 text-white'; ?>" style="max-width: 75%;">
                                    <div class="small fw-bold opacity-75 mb-1"><?php echo e($m['sender_name']); ?></div>
                                    <div><?php echo nl2br(e($m['content'])); ?></div>
                                    <div class="text-end small opacity-50 mt-1" style="font-size: 0.75rem;">
                                        <?php echo e(date('M d, h:i A', strtotime($m['created_at']))); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if ($activeContactId > 0): ?>
                    <div class="card-footer border-secondary bg-transparent p-3">
                        <form method="POST" action="chat.php?user_id=<?php echo $activeContactId; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                            <input type="hidden" name="receiver_id" value="<?php echo $activeContactId; ?>">
                            <div class="input-group">
                                <input type="text" name="content" class="form-control bg-dark text-white border-secondary" placeholder="Type your message..." required autocomplete="off">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-paper-plane me-1"></i> Send
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
