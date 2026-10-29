<?php
/**
 * Edit School Event or Gazetted Holiday
 * Academics Student Management System
 */
$page_title = 'Edit School Event / Holiday';
require_once '../includes/header.php';
requireStaff();

$user_id = $_SESSION['user_id'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM school_events WHERE id = ?");
$stmt->execute([$id]);
$event = $stmt->fetch();
if (!$event) { header('Location: index.php?error=' . urlencode('Event not found.')); exit; }
require_record_school($event);

$errors = [];
$form_data = $event;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }

    $form_data = [
        'title' => trim($_POST['title'] ?? ''),
        'event_type' => trim($_POST['event_type'] ?? 'Function'),
        'event_date' => trim($_POST['event_date'] ?? ''),
        'end_date' => !empty($_POST['end_date']) ? trim($_POST['end_date']) : null,
        'is_holiday' => isset($_POST['is_holiday']) ? 1 : 0,
        'status' => trim($_POST['status'] ?? 'Upcoming'),
        'description' => trim($_POST['description'] ?? '')
    ];

    if (empty($form_data['title'])) {
        $errors[] = 'Event title is required.';
    }
    if (empty($form_data['event_date'])) {
        $errors[] = 'Event date is required.';
    }

    if (empty($errors)) {
        try {
            $upStmt = $pdo->prepare("UPDATE school_events SET title = ?, event_type = ?, description = ?, event_date = ?, end_date = ?, is_holiday = ?, status = ? WHERE id = ?");
            $upStmt->execute([
                $form_data['title'],
                $form_data['event_type'],
                $form_data['description'],
                $form_data['event_date'],
                $form_data['end_date'],
                $form_data['is_holiday'],
                $form_data['status'],
                $id
            ]);

            logActivity($pdo, $user_id, 'edit_event', 'school_events', $id, 'Updated event: ' . $form_data['title']);
            setFlashMessage('success', 'School event updated successfully.');
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            error_log("Failed to update school event: " . $e->getMessage());
            $errors[] = 'Database error while saving changes.';
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0 fw-bold text-dark"><i class="fas fa-edit text-primary me-2"></i>Edit School Event / Holiday</h4>
            <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Back to Calendar</a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger shadow-sm border-0 mb-3">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php foreach ($errors as $e): ?>
                    <div><?php echo e($e); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Event Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" value="<?php echo e($form_data['title']); ?>" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Event Type <span class="text-danger">*</span></label>
                            <select name="event_type" id="eventTypeSelect" class="form-select" required>
                                <option value="Function" <?php echo $form_data['event_type'] === 'Function' ? 'selected' : ''; ?>>🎉 School Function / Gala</option>
                                <option value="Holiday" <?php echo $form_data['event_type'] === 'Holiday' ? 'selected' : ''; ?>>🏖️ Gazetted Holiday / Off Day</option>
                                <option value="Exam" <?php echo $form_data['event_type'] === 'Exam' ? 'selected' : ''; ?>>📝 Examination / Assessment</option>
                                <option value="Meeting" <?php echo $form_data['event_type'] === 'Meeting' ? 'selected' : ''; ?>>🤝 Parent-Teacher Meeting (PTM)</option>
                                <option value="Other" <?php echo $form_data['event_type'] === 'Other' ? 'selected' : ''; ?>>📌 Other Campus Activity</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="Upcoming" <?php echo ($form_data['status'] ?? '') === 'Upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                <option value="Ongoing" <?php echo ($form_data['status'] ?? '') === 'Ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                <option value="Completed" <?php echo ($form_data['status'] ?? '') === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="Cancelled" <?php echo ($form_data['status'] ?? '') === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Campus Off-Day?</label>
                            <div class="form-check form-switch pt-2">
                                <input class="form-check-input" type="checkbox" name="is_holiday" id="isHolidaySwitch" value="1" <?php echo $form_data['is_holiday'] ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold text-danger" for="isHolidaySwitch">
                                    School Off
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Event Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="event_date" class="form-control" value="<?php echo e($form_data['event_date']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Event End Date (Optional)</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo e($form_data['end_date'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Description & Instructions</label>
                        <textarea name="description" class="form-control" rows="4"><?php echo e($form_data['description']); ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> Update Event</button>
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
