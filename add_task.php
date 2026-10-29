<?php
/**
 * Add Task Page
 * Form to add a new task with validation.
 */
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
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
            $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, description, status, due_date, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$_SESSION['user_id'], $title, $description, $status, $due_date ?: null]);

            header('Location: dashboard.php?success=' . urlencode('Task added successfully!'));
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Failed to add task. Please try again.';
        }
    }

    // If errors, pass them along (stored in session or just display)
    $_SESSION['task_errors'] = $errors;
    $_SESSION['task_form_data'] = ['title' => $title, 'description' => $description, 'status' => $status, 'due_date' => $due_date];
    header('Location: add_task.php');
    exit;
}

// Get form data from session if redirect back due to errors
$form_data = $_SESSION['task_form_data'] ?? ['title' => '', 'description' => '', 'status' => 'Pending', 'due_date' => ''];
$form_errors = $_SESSION['task_errors'] ?? [];
unset($_SESSION['task_form_data'], $_SESSION['task_errors']);
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h4 class="mb-0"><i class="fas fa-plus-circle text-primary me-2"></i>Add New Task</h4>
            </div>
            <div class="card-body p-4">
                
                <?php if (!empty($form_errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($form_errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="add_task.php" method="POST" novalidate>
                    <div class="mb-3">
                        <label class="form-label fw-bold"><i class="fas fa-heading me-2"></i>Task Title</label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($form_data['title']); ?>" placeholder="Enter task title" required maxlength="200">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold"><i class="fas fa-align-left me-2"></i>Description</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Enter task description"><?php echo htmlspecialchars($form_data['description']); ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold"><i class="fas fa-tag me-2"></i>Status</label>
                            <select name="status" class="form-select">
                                <option value="Pending" <?php echo $form_data['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="In Progress" <?php echo $form_data['status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="Completed" <?php echo $form_data['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold"><i class="fas fa-calendar me-2"></i>Due Date</label>
                            <input type="date" name="due_date" class="form-control" value="<?php echo htmlspecialchars($form_data['due_date']); ?>">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Save Task
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>