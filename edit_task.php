<?php
/**
 * Edit Task Page
 * Form to edit an existing task with validation.
 */
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$task_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($task_id <= 0) {
    header('Location: dashboard.php?error=' . urlencode('Invalid task ID.'));
    exit;
}

// Fetch existing task
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND user_id = ?");
$stmt->execute([$task_id, $user_id]);
$task = $stmt->fetch();

if (!$task) {
    header('Location: dashboard.php?error=' . urlencode('Task not found or you do not have permission to edit it.'));
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'Pending';
    $due_date = $_POST['due_date'] ?? '';

    $errors = [];

    if (empty($title)) {
        $errors[] = 'Task title is required.';
    } elseif (strlen($title) > 200) {
        $errors[] = 'Task title must not exceed 200 characters.';
    }

    if (!in_array($status, ['Pending', 'In Progress', 'Completed'])) {
        $errors[] = 'Invalid status selected.';
    }

    if (!empty($due_date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) {
        $errors[] = 'Please enter a valid due date.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE tasks SET title = ?, description = ?, status = ?, due_date = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
            $stmt->execute([$title, $description, $status, $due_date ?: null, $task_id, $user_id]);

            header('Location: dashboard.php?success=' . urlencode('Task updated successfully!'));
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Failed to update task. Please try again.';
        }
    }

    // Repopulate form with submitted data on error
    $task['title'] = $title;
    $task['description'] = $description;
    $task['status'] = $status;
    $task['due_date'] = $due_date;
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h4 class="mb-0"><i class="fas fa-edit text-primary me-2"></i>Edit Task</h4>
            </div>
            <div class="card-body p-4">
                <form action="edit_task.php?id=<?php echo $task_id; ?>" method="POST" novalidate>
                    <div class="mb-3">
                        <label class="form-label fw-bold"><i class="fas fa-heading me-2"></i>Task Title</label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($task['title']); ?>" placeholder="Enter task title" required maxlength="200">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold"><i class="fas fa-align-left me-2"></i>Description</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Enter task description"><?php echo htmlspecialchars($task['description']); ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold"><i class="fas fa-tag me-2"></i>Status</label>
                            <select name="status" class="form-select">
                                <option value="Pending" <?php echo $task['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="In Progress" <?php echo $task['status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="Completed" <?php echo $task['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold"><i class="fas fa-calendar me-2"></i>Due Date</label>
                            <input type="date" name="due_date" class="form-control" value="<?php echo htmlspecialchars($task['due_date']); ?>">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Update Task
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>