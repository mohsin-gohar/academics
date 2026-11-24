<?php
/**
 * Create Announcement / Teacher Note
 * EduSphere Enterprise School Management System
 */
$page_title = 'Post Announcement';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';
requireStaff();

$schoolId = currentSchoolId();
$userId = $_SESSION['user_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $target_roles = trim($_POST['target_roles'] ?? 'all');
        if (!in_array($target_roles, ['all', 'student', 'parent', 'teacher'], true)) {
            $target_roles = 'all';
        }

        if ($title === '' || $message === '') {
            $errors[] = 'Title and message are required.';
        } elseif (mb_strlen($title) > 200) {
            $errors[] = 'Title cannot exceed 200 characters.';
        } elseif (mb_strlen($message) > 10000) {
            $errors[] = 'Message is too long (maximum 10,000 characters).';
        } else {
            $stmt = $pdo->prepare("INSERT INTO announcements (school_id, title, content, target_roles, is_active, created_by)
                                   VALUES (?, ?, ?, ?, 1, ?)");
            $stmt->execute([$schoolId, $title, $message, $target_roles, $userId]);
            recordActivity($pdo, $userId, 'create_announcement', 'announcement', (int)$pdo->lastInsertId(), "Created announcement: {$title}");

            // Push the announcement into the notification feed of the targeted roles.
            $rolesToNotify = $target_roles === 'all'
                ? ['school_admin', 'admin', 'teacher', 'student', 'parent']
                : ($target_roles === 'teacher' ? ['school_admin', 'admin', 'teacher'] : ($target_roles === 'student' ? ['student'] : ['parent']));
            foreach ($rolesToNotify as $notifyRole) {
                notifyRole($pdo, $schoolId, $notifyRole, 'Announcement: ' . $title, mb_substr((string)$message, 0, 200), 'notes/index.php', 'fa-bullhorn', 'info');
            }
            setFlashMessage('success', 'Announcement posted successfully.');
            redirect('index.php');
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-white">Post Announcement</h1>
            <p class="text-secondary small mb-0">Publish an announcement or note for school community members.</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back to Announcements
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?php echo e($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card bg-dark text-white border-secondary">
        <div class="card-body p-4">
            <form method="POST" action="create.php">
                <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">

                <div class="mb-3">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-control bg-dark text-white border-secondary" placeholder="e.g. Science Fair Submission Guidelines" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Target Audience *</label>
                    <select name="target_roles" class="form-select bg-dark text-white border-secondary">
                        <option value="all">Everyone (Students, Teachers, Parents)</option>
                        <option value="student">Students Only</option>
                        <option value="parent">Parents Only</option>
                        <option value="teacher">Teachers & Staff Only</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Message *</label>
                    <textarea name="message" class="form-control bg-dark text-white border-secondary" rows="5" placeholder="Write announcement details..." required></textarea>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-warning text-dark fw-bold">
                        <i class="fas fa-paper-plane me-1"></i> Post Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
