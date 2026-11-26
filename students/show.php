<?php
$page_title = 'Student Details';
require_once '../includes/header.php';
requireAuth();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { 
    header('Location: ' . (isStaff() ? 'index.php' : '../dashboard.php')); 
    exit; 
}

// Parents may only open children linked to their own account in their school.
if (isParent()) {
    $stmt = $pdo->prepare("SELECT 1 FROM parent_student WHERE parent_id = ? AND student_id = ? AND school_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id'], $id, currentSchoolId()]);
    if (!$stmt->fetchColumn()) {
        header('Location: ../parent/children.php?error=' . urlencode('That student is not linked to your account.'));
        exit;
    }
}

// Student can only view their own profile - gracefully redirect instead of 403
if (!isStaff() && !isParent()) {
    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $ownId = (int)$stmt->fetchColumn();
    if ($ownId && $id !== $ownId) {
        header('Location: show.php?id=' . $ownId);
        exit;
    } elseif (!$ownId) {
        header('Location: ../dashboard.php');
        exit;
    }
}

$stmt = $pdo->prepare("SELECT s.*, u.is_active as user_active, u.last_login FROM students s LEFT JOIN users u ON s.user_id = u.id WHERE s.id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();
if (!$student) { header('Location: index.php?error=' . urlencode('Student not found.')); exit; }
require_record_school($student);

$studentSchoolId = (int)$student['school_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isSchoolManagement()) {
        http_response_code(403);
        exit('Forbidden');
    }
    requirePost();
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid security token.');
        header('Location: show.php?id=' . $id);
        exit;
    }

    $action = (string)($_POST['action'] ?? '');
    $parentId = (int)($_POST['parent_id'] ?? 0);
    try {
        $pdo->beginTransaction();
        $studentLock = $pdo->prepare("SELECT id FROM students WHERE id = ? AND school_id = ? FOR UPDATE");
        $studentLock->execute([$id, $studentSchoolId]);
        if (!$studentLock->fetchColumn()) {
            throw new InvalidArgumentException('Student is no longer available in this school.');
        }

        if ($action === 'link_parent') {
            $relationship = trim((string)($_POST['relationship'] ?? 'Parent'));
            $allowedRelationships = ['Parent', 'Mother', 'Father', 'Guardian', 'Other'];
            if ($parentId <= 0 || !in_array($relationship, $allowedRelationships, true)) {
                throw new InvalidArgumentException('Choose a valid parent account and relationship.');
            }
            $parentLock = $pdo->prepare(
                "SELECT id FROM users
                 WHERE id = ? AND school_id = ? AND role = 'parent' AND is_active = 1
                 FOR UPDATE"
            );
            $parentLock->execute([$parentId, $studentSchoolId]);
            if (!$parentLock->fetchColumn()) {
                throw new InvalidArgumentException('Parent account must be active and belong to the same school.');
            }

            $existingLink = $pdo->prepare(
                "SELECT id FROM parent_student WHERE school_id = ? AND parent_id = ? AND student_id = ? FOR UPDATE"
            );
            $existingLink->execute([$studentSchoolId, $parentId, $id]);
            $linkId = $existingLink->fetchColumn();
            if ($linkId) {
                $pdo->prepare("UPDATE parent_student SET relationship = ? WHERE id = ? AND school_id = ?")
                    ->execute([$relationship, (int)$linkId, $studentSchoolId]);
                $activity = 'Updated parent link for student ID ' . $id;
            } else {
                $pdo->prepare(
                    "INSERT INTO parent_student (school_id, parent_id, student_id, relationship)
                     VALUES (?, ?, ?, ?)"
                )->execute([$studentSchoolId, $parentId, $id, $relationship]);
                $activity = 'Linked parent ID ' . $parentId . ' to student ID ' . $id;
            }
        } elseif ($action === 'unlink_parent') {
            if ($parentId <= 0) throw new InvalidArgumentException('Choose a valid parent account.');
            $delete = $pdo->prepare(
                "DELETE FROM parent_student WHERE school_id = ? AND parent_id = ? AND student_id = ?"
            );
            $delete->execute([$studentSchoolId, $parentId, $id]);
            if ($delete->rowCount() !== 1) {
                throw new InvalidArgumentException('Parent link was not found.');
            }
            $activity = 'Removed parent ID ' . $parentId . ' from student ID ' . $id;
        } else {
            throw new InvalidArgumentException('Invalid parent-link action.');
        }

        $pdo->commit();
        recordActivity($pdo, (int)$_SESSION['user_id'], 'manage_parent_link', 'student', $id, $activity);
        setFlashMessage('success', $action === 'link_parent' ? 'Parent linked successfully.' : 'Parent link removed.');
    } catch (InvalidArgumentException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        setFlashMessage('warning', $e->getMessage());
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Student parent-link update failed: ' . $e->getMessage());
        setFlashMessage('danger', 'Parent link could not be saved. Please try again.');
    }
    header('Location: show.php?id=' . $id);
    exit;
}

$linkedParents = [];
$availableParents = [];
if (isSchoolManagement()) {
    $linkedStmt = $pdo->prepare(
        "SELECT ps.parent_id, ps.relationship, ps.is_primary, u.full_name, u.email
         FROM parent_student ps
         JOIN users u ON u.id = ps.parent_id AND u.school_id = ps.school_id AND u.role = 'parent'
         WHERE ps.school_id = ? AND ps.student_id = ?
         ORDER BY ps.is_primary DESC, u.full_name"
    );
    $linkedStmt->execute([$studentSchoolId, $id]);
    $linkedParents = $linkedStmt->fetchAll(PDO::FETCH_ASSOC);

    $availableStmt = $pdo->prepare(
        "SELECT u.id, u.full_name, u.email
         FROM users u
         WHERE u.school_id = ? AND u.role = 'parent' AND u.is_active = 1
           AND NOT EXISTS (
               SELECT 1 FROM parent_student ps
               WHERE ps.school_id = u.school_id AND ps.parent_id = u.id AND ps.student_id = ?
           )
         ORDER BY u.full_name"
    );
    $availableStmt->execute([$studentSchoolId, $id]);
    $availableParents = $availableStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Enrollments
$stmt = $pdo->prepare("SELECT e.*, c.course_code, c.course_name FROM enrollments e JOIN courses c ON e.course_id = c.id AND c.school_id = e.school_id WHERE e.student_id = ? AND e.school_id = ? ORDER BY e.enrollment_date DESC");
$stmt->execute([$id, (int)$student['school_id']]);
$enrollments = $stmt->fetchAll();

// Tasks
$stmt = $pdo->prepare("SELECT t.*, c.course_name FROM tasks t LEFT JOIN courses c ON t.course_id = c.id AND c.school_id = t.school_id WHERE t.student_id = ? AND t.school_id = ? ORDER BY t.created_at DESC");
$stmt->execute([$id, (int)$student['school_id']]);
$tasks = $stmt->fetchAll();

// Task stats
$taskStats = ['total' => 0, 'completed' => 0, 'pending' => 0, 'in_progress' => 0];
foreach ($tasks as $t) {
    $taskStats['total']++;
    if ($t['status'] === 'Completed') $taskStats['completed']++;
    elseif ($t['status'] === 'Pending') $taskStats['pending']++;
    elseif ($t['status'] === 'In Progress') $taskStats['in_progress']++;
}
?>

<div class="row mb-3">
    <div class="col-12">
        <a href="<?php echo isStaff() ? 'index.php' : '../dashboard.php'; ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> <?php echo isStaff() ? 'Back to Students' : 'Back to Dashboard'; ?>
        </a>
    </div>
</div>

<div class="row g-3">
    <!-- Profile Card -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="mb-3">
                    <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" style="width:80px;height:80px;font-size:2rem;">
                        <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                    </div>
                </div>
                <h4 class="mb-1"><?php echo e($student['full_name']); ?></h4>
                <p class="text-muted mb-2"><?php echo e($student['student_number']); ?></p>
                <span class="badge bg-<?php echo $student['status'] === 'Active' ? 'success' : 'secondary'; ?> mb-3"><?php echo e($student['status']); ?></span>
                
                <hr>
                <div class="text-start">
                    <p class="mb-1"><i class="fas fa-envelope me-2 text-muted"></i><?php echo e($student['email']); ?></p>
                    <?php if ($student['phone']): ?>
                    <p class="mb-1"><i class="fas fa-phone me-2 text-muted"></i><?php echo e($student['phone']); ?></p>
                    <?php endif; ?>
                    <?php if ($student['date_of_birth']): ?>
                    <p class="mb-1"><i class="fas fa-birthday-cake me-2 text-muted"></i><?php echo formatDate($student['date_of_birth']); ?></p>
                    <?php endif; ?>
                    <?php if ($student['gender']): ?>
                    <p class="mb-1"><i class="fas fa-venus-mars me-2 text-muted"></i><?php echo e($student['gender']); ?></p>
                    <?php endif; ?>
                    <p class="mb-1"><i class="fas fa-calendar me-2 text-muted"></i>Enrolled: <?php echo formatDate($student['enrollment_date']); ?></p>
                    <?php if ($student['address']): ?>
                    <p class="mb-1"><i class="fas fa-map-marker-alt me-2 text-muted"></i><?php echo e($student['address']); ?></p>
                    <?php endif; ?>
                </div>
                
                <?php if (isStaff()): ?>
                <hr>
                <div class="d-flex gap-2 justify-content-center">
                    <a href="edit.php?id=<?php echo $student['id']; ?>" class="btn btn-primary btn-sm"><i class="fas fa-edit me-1"></i>Edit</a>
                    <form method="post" action="delete.php" class="d-inline" onsubmit="return confirm('Delete this student?');">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="id" value="<?php echo (int)$student['id']; ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>Delete</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Task Stats -->
        <div class="card mt-3">
            <div class="card-header"><i class="fas fa-chart-pie me-2"></i>Task Summary</div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2"><span>Total</span><strong><?php echo $taskStats['total']; ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span>Completed</span><strong class="text-success"><?php echo $taskStats['completed']; ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span>Pending</span><strong class="text-secondary"><?php echo $taskStats['pending']; ?></strong></div>
                <div class="d-flex justify-content-between"><span>In Progress</span><strong class="text-info"><?php echo $taskStats['in_progress']; ?></strong></div>
            </div>
        </div>
    </div>
    
    <!-- Enrollments & Tasks -->
    <div class="col-lg-8">
        <?php if (isSchoolManagement()): ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-user-shield me-2 text-primary"></i>Parent / Guardian Access</span>
                <span class="badge bg-secondary"><?php echo count($linkedParents); ?> linked</span>
            </div>
            <div class="card-body">
                <?php if ($linkedParents): ?>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Parent</th><th>Relationship</th><th>Contact</th><th class="text-end">Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($linkedParents as $linkedParent): ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo e($linkedParent['full_name']); ?><?php if ((int)$linkedParent['is_primary'] === 1): ?> <span class="badge bg-primary">Primary</span><?php endif; ?></td>
                                    <td><?php echo e($linkedParent['relationship']); ?></td>
                                    <td><small><?php echo e($linkedParent['email']); ?></small></td>
                                    <td class="text-end">
                                        <form method="post" action="show.php?id=<?php echo (int)$id; ?>" class="d-inline" onsubmit="return confirm('Remove this parent’s access to the student portal?');">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="action" value="unlink_parent">
                                            <input type="hidden" name="parent_id" value="<?php echo (int)$linkedParent['parent_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-unlink me-1"></i>Unlink</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-3">No parent or guardian account is linked to this student yet.</p>
                <?php endif; ?>

                <?php if ($availableParents): ?>
                    <form method="post" action="show.php?id=<?php echo (int)$id; ?>" class="row g-2 align-items-end">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="link_parent">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold">Parent account</label>
                            <select name="parent_id" class="form-select form-select-sm" required>
                                <option value="">Select parent account</option>
                                <?php foreach ($availableParents as $availableParent): ?>
                                    <option value="<?php echo (int)$availableParent['id']; ?>"><?php echo e($availableParent['full_name'] . ' — ' . $availableParent['email']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-8 col-md-4">
                            <label class="form-label small fw-semibold">Relationship</label>
                            <select name="relationship" class="form-select form-select-sm" required>
                                <option value="Parent">Parent</option>
                                <option value="Mother">Mother</option>
                                <option value="Father">Father</option>
                                <option value="Guardian">Guardian</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-4 col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-link me-1"></i>Link</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="small text-muted">No unlinked active parent accounts are available. <a href="../admin/users.php">Create a parent account</a> first.</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-book me-2 text-primary"></i>Enrolled Courses (<?php echo count($enrollments); ?>)</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($enrollments)): ?>
                    <div class="empty-state"><i class="fas fa-book-open"></i><h5>No enrollments</h5></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Course</th><th>Enrolled</th><th>Status</th><th>Grade</th></tr></thead>
                            <tbody>
                                <?php foreach ($enrollments as $e): ?>
                                <tr>
                                    <td><strong><?php echo e($e['course_code']); ?></strong> - <?php echo e($e['course_name']); ?></td>
                                    <td><?php echo formatDate($e['enrollment_date']); ?></td>
                                    <td><span class="badge bg-<?php echo $e['status'] === 'Enrolled' ? 'primary' : ($e['status'] === 'Completed' ? 'success' : 'secondary'); ?>"><?php echo e($e['status']); ?></span></td>
                                    <td><?php echo e($e['grade'] ?: '-'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header"><i class="fas fa-tasks me-2 text-primary"></i>Assigned Tasks (<?php echo count($tasks); ?>)</div>
            <div class="card-body p-0">
                <?php if (empty($tasks)): ?>
                    <div class="empty-state"><i class="fas fa-clipboard-list"></i><h5>No tasks assigned</h5></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Title</th><th>Priority</th><th>Status</th><th>Due Date</th></tr></thead>
                            <tbody>
                                <?php foreach ($tasks as $t): ?>
                                <tr>
                                    <td><?php echo e($t['title']); ?></td>
                                    <td><span class="badge priority-<?php echo strtolower($t['priority']); ?>"><?php echo e($t['priority']); ?></span></td>
                                    <td><span class="badge bg-<?php echo $t['status'] === 'Completed' ? 'success' : ($t['status'] === 'In Progress' ? 'warning' : 'secondary'); ?>"><?php echo e($t['status']); ?></span></td>
                                    <td><?php echo $t['due_date'] ? formatDate($t['due_date']) : '-'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
