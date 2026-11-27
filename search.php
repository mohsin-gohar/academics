<?php
$page_title = 'Search';
require_once 'includes/header.php';
requireAuth();

$q = trim($_GET['q'] ?? '');
$results = ['students' => [], 'courses' => [], 'tasks' => []];

if ($q !== '') {
    $like = '%' . $q . '%';
    $sid = currentSchoolId();
    $hasSchool = $sid !== null && (int)$sid > 0;

    if (isStaff() && $hasSchool) {
        $st = $pdo->prepare("SELECT id, full_name, student_number, email, status FROM students WHERE school_id = ? AND (full_name LIKE ? OR student_number LIKE ? OR email LIKE ?) ORDER BY full_name LIMIT 20");
        $st->execute([(int)$sid, $like, $like, $like]);
        $results['students'] = $st->fetchAll();
    }

    if ($hasSchool) {
        $cs = $pdo->prepare("SELECT id, course_code, course_name, status FROM courses WHERE school_id = ? AND (course_code LIKE ? OR course_name LIKE ?) ORDER BY course_name LIMIT 20");
        $cs->execute([(int)$sid, $like, $like]);
        $results['courses'] = $cs->fetchAll();
    }

    if (isStaff() && $hasSchool) {
        $ts = $pdo->prepare("SELECT t.id, t.title, t.status, t.priority, s.full_name AS student_name FROM tasks t LEFT JOIN students s ON t.student_id = s.id AND s.school_id = t.school_id WHERE t.school_id = ? AND (t.title LIKE ? OR t.description LIKE ?) ORDER BY t.created_at DESC LIMIT 20");
        $ts->execute([(int)$sid, $like, $like]);
        $results['tasks'] = $ts->fetchAll();
    } elseif (!isStaff() && $hasSchool && isStudent()) {
        $ts = $pdo->prepare("SELECT t.id, t.title, t.status, t.priority, s.full_name AS student_name FROM tasks t LEFT JOIN students s ON t.student_id = s.id WHERE t.school_id = ? AND (t.title LIKE ? OR t.description LIKE ?) AND t.student_id = (SELECT id FROM students WHERE user_id = ? AND school_id = ? LIMIT 1) ORDER BY t.created_at DESC LIMIT 20");
        $ts->execute([(int)$sid, $like, $like, (int)$_SESSION['user_id'], (int)$sid]);
        $results['tasks'] = $ts->fetchAll();
    }
}
?>
<div class="mb-4">
    <form class="search-pill-wrapper" style="max-width:100%;" method="GET" action="search.php">
        <i class="fas fa-search search-pill-icon"></i>
        <input type="text" name="q" class="search-pill-input" placeholder="Search students, courses, tasks..." value="<?php echo e($q); ?>" autofocus>
    </form>
</div>

<?php if ($q === ''): ?>
    <div class="empty-state"><i class="fas fa-search"></i><h5>Start searching</h5><p>Type a keyword to search across the system.</p></div>
<?php else: ?>
    <div class="row g-4">
        <?php if (isStaff() && !empty($results['students'])): ?>
        <div class="col-lg-4">
            <div class="card p-3">
                <h6 class="fw-bold mb-3"><i class="fas fa-user-graduate me-2 text-primary"></i>Students (<?php echo count($results['students']); ?>)</h6>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($results['students'] as $s): ?>
                    <li class="py-2 border-bottom d-flex justify-content-between align-items-center">
                        <div>
                            <a href="students/show.php?id=<?php echo (int)$s['id']; ?>" class="fw-semibold text-dark"><?php echo e($s['full_name']); ?></a>
                            <div class="small text-muted"><?php echo e($s['student_number']); ?> · <?php echo e($s['email']); ?></div>
                        </div>
                        <span class="badge bg-<?php echo $s['status']==='Active'?'success':'secondary'; ?>"><?php echo e($s['status']); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($results['courses'])): ?>
        <div class="col-lg-4">
            <div class="card p-3">
                <h6 class="fw-bold mb-3"><i class="fas fa-book-open me-2 text-success"></i>Courses (<?php echo count($results['courses']); ?>)</h6>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($results['courses'] as $c): ?>
                    <li class="py-2 border-bottom d-flex justify-content-between align-items-center">
                        <div>
                            <a href="courses/show.php?id=<?php echo (int)$c['id']; ?>" class="fw-semibold text-dark"><?php echo e($c['course_name']); ?></a>
                            <div class="small text-muted"><?php echo e($c['course_code']); ?></div>
                        </div>
                        <span class="badge bg-<?php echo $c['status']==='Active'?'success':'secondary'; ?>"><?php echo e($c['status']); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($results['tasks'])): ?>
        <div class="col-lg-4">
            <div class="card p-3">
                <h6 class="fw-bold mb-3"><i class="fas fa-tasks me-2 text-warning"></i>Tasks (<?php echo count($results['tasks']); ?>)</h6>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($results['tasks'] as $t): ?>
                    <li class="py-2 border-bottom">
                        <a href="tasks/show.php?id=<?php echo (int)$t['id']; ?>" class="fw-semibold text-dark"><?php echo e($t['title']); ?></a>
                        <div class="small text-muted"><?php echo e($t['student_name'] ?? 'Unassigned'); ?> · <?php echo e($t['status']); ?></div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
    $total = count($results['students']) + count($results['courses']) + count($results['tasks']);
    if ($total === 0): ?>
        <div class="empty-state mt-4"><i class="fas fa-search"></i><h5>No results</h5><p>No matches found for "<?php echo e($q); ?>".</p></div>
    <?php endif; ?>
<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>
