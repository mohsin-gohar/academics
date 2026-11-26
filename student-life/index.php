<?php
$page_title = 'Student Portfolio & Activities';
require_once __DIR__ . '/../includes/header.php';
requireAuth();
if (isParent() || isSuperAdmin() && currentSchoolId() === null) {
    http_response_code(403);
    exit('This student workspace is not available for this account.');
}
$schoolId = (int)(currentSchoolId() ?? 0);
if ($schoolId <= 0) { http_response_code(403); exit('School context is required.'); }
$isStudentUser = isStudent();
$isManager = isSchoolManagement();
$studentId = $isStudentUser ? (int)($_SESSION['student_id'] ?? 0) : (int)($_GET['student_id'] ?? 0);
$types = [
    'eproject' => 'My eProject', 'cv' => 'My CV', 'job' => 'Jobs',
    'achievement' => 'Achievements', 'badge' => 'Badges', 'league' => 'League',
    'rank' => 'Rank', 'award' => 'Awards', 'volunteer' => 'Volunteer',
    'event' => 'Events', 'conduct' => 'Conduct'
];
$studentWritable = ['eproject','cv','job','volunteer'];
$section = (string)($_GET['section'] ?? 'eproject');
if (!isset($types[$section])) $section = 'eproject';
$students = [];
if ($isManager) {
    $s = $pdo->prepare('SELECT id, student_number, full_name FROM students WHERE school_id = ? ORDER BY full_name');
    $s->execute([$schoolId]);
    $students = $s->fetchAll(PDO::FETCH_ASSOC);
    if ($studentId > 0 && !array_filter($students, static fn($x) => (int)$x['id'] === $studentId)) $studentId = 0;
}
if ($isStudentUser) {
    $check = $pdo->prepare('SELECT id FROM students WHERE id = ? AND user_id = ? AND school_id = ? LIMIT 1');
    $check->execute([$studentId, (int)$_SESSION['user_id'], $schoolId]);
    if (!$check->fetchColumn()) { http_response_code(403); exit('Student record not found.'); }
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePost();
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(419); exit('Your session expired. Refresh and try again.'); }
    $action = (string)($_POST['action'] ?? 'create');
    try {
        if ($action === 'review' && $isManager) {
            $entryId = (int)($_POST['entry_id'] ?? 0);
            $status = (string)($_POST['status'] ?? '');
            $note = trim((string)($_POST['staff_note'] ?? ''));
            if ($entryId <= 0 || !in_array($status, ['approved','rejected','published'], true) || mb_strlen($note) > 4000) throw new InvalidArgumentException('Choose a valid review status and note.');
            $q = $pdo->prepare('UPDATE student_portfolio_entries SET status = ?, staff_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND school_id = ? AND entry_type = ?');
            $q->execute([$status, $note !== '' ? $note : null, (int)$_SESSION['user_id'], $entryId, $schoolId, $section]);
            if ($q->rowCount() < 1) throw new InvalidArgumentException('Entry was not found or already has this status.');
            recordActivity($pdo, (int)$_SESSION['user_id'], 'review_student_portfolio', 'student_portfolio_entry', $entryId, 'Reviewed student ' . $section . ' entry as ' . $status);
            setFlashMessage('success', 'Student entry review saved.');
        } elseif ($action === 'create') {
            $entryType = (string)($_POST['entry_type'] ?? $section);
            if (!isset($types[$entryType])) throw new InvalidArgumentException('Choose a valid section.');
            if ($isStudentUser) {
                if (!in_array($entryType, $studentWritable, true)) throw new InvalidArgumentException('This section is managed by your school.');
                $targetStudentId = $studentId;
            } elseif ($isManager) {
                $targetStudentId = (int)($_POST['student_id'] ?? 0);
                $valid = $pdo->prepare('SELECT id FROM students WHERE id = ? AND school_id = ?');
                $valid->execute([$targetStudentId, $schoolId]);
                if (!$valid->fetchColumn()) throw new InvalidArgumentException('Choose a student from this school.');
            } else {
                throw new InvalidArgumentException('You do not have permission to add entries.');
            }
            $title = trim((string)($_POST['title'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $url = trim((string)($_POST['external_url'] ?? ''));
            $date = trim((string)($_POST['event_date'] ?? ''));
            if ($title === '' || mb_strlen($title) > 180 || mb_strlen($description) > 12000) throw new InvalidArgumentException('Enter a title (max 180 characters) and keep details under 12,000 characters.');
            if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['https'], true))) throw new InvalidArgumentException('Links must be valid HTTPS URLs.');
            if ($date !== '' && (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date) || !checkdate((int)substr($date,5,2), (int)substr($date,8,2), (int)substr($date,0,4)))) throw new InvalidArgumentException('Choose a valid date.');
            $initialStatus = $isStudentUser ? 'pending' : (in_array($entryType, ['achievement','badge','league','rank','award','event','conduct'], true) ? 'published' : 'pending');
            $q = $pdo->prepare('INSERT INTO student_portfolio_entries (school_id, student_id, entry_type, title, description, external_url, event_date, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $q->execute([$schoolId, $targetStudentId, $entryType, $title, $description !== '' ? $description : null, $url !== '' ? $url : null, $date !== '' ? $date : null, $initialStatus, (int)$_SESSION['user_id']]);
            recordActivity($pdo, (int)$_SESSION['user_id'], 'create_student_portfolio_entry', 'student_portfolio_entry', (int)$pdo->lastInsertId(), 'Created student ' . $entryType . ' entry');
            setFlashMessage('success', $isStudentUser ? 'Your submission has been sent to the school for review.' : 'Student record published.');
        } else {
            throw new InvalidArgumentException('Invalid action.');
        }
    } catch (InvalidArgumentException $e) {
        setFlashMessage('warning', $e->getMessage());
    } catch (Throwable $e) {
        error_log('Student portfolio action failed: ' . $e->getMessage());
        setFlashMessage('danger', 'The entry could not be saved. Please try again.');
    }
    $redirect = 'index.php?section=' . rawurlencode($section);
    if ($isManager && $studentId > 0) $redirect .= '&student_id=' . $studentId;
    header('Location: ' . $redirect);
    exit;
}
$sql = 'SELECT p.*, s.full_name, s.student_number, u.full_name AS creator_name FROM student_portfolio_entries p JOIN students s ON s.id = p.student_id AND s.school_id = p.school_id LEFT JOIN users u ON u.id = p.created_by WHERE p.school_id = ? AND p.entry_type = ?';
$params = [$schoolId, $section];
if ($isStudentUser) { $sql .= ' AND p.student_id = ?'; $params[] = $studentId; }
elseif ($studentId > 0) { $sql .= ' AND p.student_id = ?'; $params[] = $studentId; }
elseif (!$isManager) { $sql .= ' AND p.status IN ("approved","published")'; }
$sql .= ' ORDER BY p.created_at DESC LIMIT 100';
$q = $pdo->prepare($sql); $q->execute($params); $entries = $q->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container-fluid px-3 px-lg-4 py-4">
 <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h4 mb-1">Student Portfolio & Activities</h2><p class="text-muted mb-0">Projects, career profile, achievements and campus activities</p></div></div>
 <div class="d-flex flex-wrap gap-2 mb-4">
 <?php foreach ($types as $key => $label): ?><a class="btn btn-sm <?php echo $section === $key ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="index.php?section=<?php echo e($key); ?><?php echo $isManager && $studentId ? '&student_id=' . $studentId : ''; ?>"><?php echo e($label); ?></a><?php endforeach; ?>
 </div>
 <?php if ($isManager): ?><form method="get" class="card card-body mb-3"><input type="hidden" name="section" value="<?php echo e($section); ?>"><label class="form-label" for="student_id">Filter student</label><div class="d-flex gap-2"><select class="form-select" id="student_id" name="student_id"><option value="0">All students</option><?php foreach ($students as $s): ?><option value="<?php echo (int)$s['id']; ?>" <?php echo $studentId === (int)$s['id'] ? 'selected' : ''; ?>><?php echo e($s['full_name'] . ' — ' . $s['student_number']); ?></option><?php endforeach; ?></select><button class="btn btn-outline-primary">Filter</button></div></form><?php endif; ?>
 <?php if ($isStudentUser || $isManager): ?>
 <div class="card mb-4"><div class="card-header fw-semibold"><?php echo $isStudentUser ? 'Submit ' . e($types[$section]) : 'Add ' . e($types[$section]); ?></div><div class="card-body">
 <form method="post" class="row g-3"><?php echo csrfField(); ?><input type="hidden" name="action" value="create"><input type="hidden" name="entry_type" value="<?php echo e($section); ?>">
 <?php if ($isManager): ?><div class="col-12 col-md-6"><label class="form-label" for="target_student">Student</label><select class="form-select" id="target_student" name="student_id" required><option value="">Select student</option><?php foreach ($students as $s): ?><option value="<?php echo (int)$s['id']; ?>" <?php echo $studentId === (int)$s['id'] ? 'selected' : ''; ?>><?php echo e($s['full_name'] . ' — ' . $s['student_number']); ?></option><?php endforeach; ?></select></div><?php endif; ?>
 <div class="col-12 <?php echo $isManager ? 'col-md-6' : ''; ?>"><label class="form-label" for="entry_title">Title</label><input class="form-control" id="entry_title" name="title" maxlength="180" required></div>
 <div class="col-12"><label class="form-label" for="entry_description">Details</label><textarea class="form-control" id="entry_description" name="description" rows="4" maxlength="12000"></textarea></div>
 <div class="col-12 col-md-8"><label class="form-label" for="entry_url">Project / profile / reference link (HTTPS)</label><input class="form-control" type="url" id="entry_url" name="external_url" placeholder="https://"></div>
 <div class="col-12 col-md-4"><label class="form-label" for="entry_date">Date (optional)</label><input class="form-control" type="date" id="entry_date" name="event_date"></div>
 <div class="col-12"><button class="btn btn-primary" type="submit"><?php echo $isStudentUser ? 'Submit for Review' : 'Save Entry'; ?></button></div>
 </form></div></div><?php endif; ?>
 <div class="card"><div class="card-header d-flex justify-content-between align-items-center"><span class="fw-semibold"><?php echo e($types[$section]); ?></span><span class="badge bg-secondary"><?php echo count($entries); ?> records</span></div>
 <div class="card-body"><?php if (!$entries): ?><div class="text-center text-muted py-5"><i class="fas fa-folder-open fa-2x mb-2"></i><p class="mb-0">No records in this section yet.</p></div><?php else: ?><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><?php if ($isManager): ?><th>Student</th><?php endif; ?><th>Title</th><th>Details</th><th>Date</th><th>Status</th><?php if ($isManager): ?><th>Review</th><?php endif; ?></tr></thead><tbody>
 <?php foreach ($entries as $entry): ?><tr><?php if ($isManager): ?><td><?php echo e($entry['full_name']); ?><small class="d-block text-muted"><?php echo e($entry['student_number']); ?></small></td><?php endif; ?><td class="fw-semibold"><?php echo e($entry['title']); ?><?php if ($entry['external_url']): ?><a class="d-block small" href="<?php echo e($entry['external_url']); ?>" target="_blank" rel="noopener noreferrer">Open link</a><?php endif; ?></td><td class="text-wrap" style="min-width:180px;max-width:360px"><?php echo nl2br(e($entry['description'] ?? '')); ?><?php if (!empty($entry['staff_note'])): ?><small class="d-block text-muted mt-1">School note: <?php echo e($entry['staff_note']); ?></small><?php endif; ?></td><td><?php echo $entry['event_date'] ? formatDate($entry['event_date']) : '—'; ?></td><td><span class="badge bg-<?php echo in_array($entry['status'], ['approved','published'], true) ? 'success' : ($entry['status'] === 'rejected' ? 'danger' : 'warning'); ?>"><?php echo e(ucfirst($entry['status'])); ?></span></td>
 <?php if ($isManager): ?><td><form method="post" class="d-flex flex-column gap-1"><?php echo csrfField(); ?><input type="hidden" name="action" value="review"><input type="hidden" name="entry_id" value="<?php echo (int)$entry['id']; ?>"><select name="status" class="form-select form-select-sm"><option value="approved">Approve</option><option value="published">Publish</option><option value="rejected">Reject</option></select><input name="staff_note" class="form-control form-control-sm" maxlength="4000" placeholder="Optional note"><button class="btn btn-sm btn-outline-primary">Save</button></form></td><?php endif; ?></tr><?php endforeach; ?>
 </tbody></table></div><?php endif; ?></div></div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
