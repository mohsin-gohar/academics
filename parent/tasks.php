<?php
/**
 * Parent task overview — only tasks assigned to children linked to this parent.
 */
$page_title = 'Children Tasks';
require_once __DIR__ . '/../includes/header.php';
requireParent();

$schoolId = (int)(currentSchoolId() ?? 0);
$parentId = (int)$_SESSION['user_id'];
if ($schoolId <= 0) {
    header('Location: dashboard.php');
    exit;
}

$stmt = $pdo->prepare("SELECT t.id, t.title, t.description, t.status, t.priority, t.due_date,
        st.id AS student_id, st.full_name AS student_name, st.student_number,
        c.course_name
    FROM tasks t
    JOIN students st ON st.id = t.student_id AND st.school_id = t.school_id
    JOIN parent_student ps ON ps.student_id = st.id AND ps.school_id = st.school_id
    LEFT JOIN courses c ON c.id = t.course_id AND c.school_id = t.school_id
    WHERE t.school_id = ? AND ps.parent_id = ?
    ORDER BY (t.due_date IS NULL), t.due_date ASC, t.created_at DESC");
$stmt->execute([$schoolId, $parentId]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h2 class="page-title mb-1"><i class="fas fa-list-check me-2 text-primary"></i>Children's Tasks</h2>
        <p class="text-muted mb-0 small">Tasks for children linked to your parent account.</p>
    </div>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
</div>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Task</th><th>Child</th><th>Course</th><th>Due date</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($tasks as $task): ?>
                <tr>
                    <td><div class="fw-semibold"><?php echo e($task['title']); ?></div><small class="text-muted"><?php echo e(mb_strimwidth((string)$task['description'], 0, 90, '…')); ?></small></td>
                    <td><?php echo e($task['student_name']); ?><small class="d-block text-muted"><?php echo e($task['student_number']); ?></small></td>
                    <td><?php echo e($task['course_name'] ?? '—'); ?></td>
                    <td><?php echo !empty($task['due_date']) ? e(formatDate($task['due_date'])) : '—'; ?></td>
                    <td><span class="badge bg-<?php echo $task['status'] === 'Completed' ? 'success' : ($task['status'] === 'Cancelled' ? 'secondary' : 'primary'); ?>"><?php echo e($task['status']); ?></span></td>
                    <td><a class="btn btn-sm btn-outline-primary" href="../tasks/show.php?id=<?php echo (int)$task['id']; ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$tasks): ?><tr><td colspan="6" class="text-center text-muted py-4">No tasks found for linked children.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
