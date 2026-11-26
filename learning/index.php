<?php
$page_title = 'Study Material & Lab Practice';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';
requireAuth();
$schoolId = (int)(currentSchoolId() ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$canManage = isStaff();
$audience = isStudent() ? 'students' : (isTeacher() ? 'teachers' : 'all');
if (!$schoolId || (!isStudent() && !isTeacher() && !isAdmin())) { http_response_code(403); exit('Access denied.'); }
$error = '';
if ($canManage && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    requirePost();
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(419); exit('Your session expired.'); }
    $action = (string)($_POST['action'] ?? '');
    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $courseId = (int)($_POST['course_id'] ?? 0);
    $target = (string)($_POST['audience'] ?? 'students');
    if ($title === '' || mb_strlen($title) > 180 || !in_array($target, ['all','students','teachers'], true)) {
        $error = 'Please provide a title and valid audience.';
    } else {
        if ($courseId > 0) {
            $check = $pdo->prepare('SELECT id FROM courses WHERE id = ? AND school_id = ?');
            $check->execute([$courseId, $schoolId]);
            if (!$check->fetchColumn()) $error = 'Selected course does not belong to this school.';
        }
        if ($error === '' && $action === 'resource') {
            $url = trim((string)($_POST['resource_url'] ?? ''));
            $type = (string)($_POST['resource_type'] ?? 'link');
            $parts = filter_var($url, FILTER_VALIDATE_URL) ? parse_url($url) : false;
            if (!$parts || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http','https'], true) || !empty($parts['user']) || !in_array($type, ['link','document','video','other'], true)) {
                $error = 'Provide a valid HTTP(S) resource URL and type.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO study_resources (school_id, course_id, title, description, resource_type, resource_url, audience, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$schoolId, $courseId ?: null, $title, $description ?: null, $type, $url, $target, $userId]);
            }
        } elseif ($error === '' && $action === 'lab') {
            $instructions = trim((string)($_POST['instructions'] ?? ''));
            $dueDate = trim((string)($_POST['due_date'] ?? ''));
            $dateParts = $dueDate === '' ? [] : explode('-', $dueDate);
            $dateValid = $dueDate === '' || (
                count($dateParts) === 3
                && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $dueDate)
                && checkdate((int)$dateParts[1], (int)$dateParts[2], (int)$dateParts[0])
            );
            if ($instructions === '' || mb_strlen($instructions) > 20000 || !$dateValid) {
                $error = 'Provide lab instructions and a valid optional due date.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO lab_practicals (school_id, course_id, title, instructions, due_date, audience, status, created_by) VALUES (?, ?, ?, ?, ?, ?, 'published', ?)");
                $stmt->execute([$schoolId, $courseId ?: null, $title, $instructions, $dueDate ?: null, $target, $userId]);
            }
        } else {
            $error = $error ?: 'Unknown action.';
        }
        if ($error === '') { $_SESSION['flash_message'] = ['type'=>'success','message'=>'Learning item published.']; header('Location: index.php'); exit; }
    }
}
$coursesStmt = $pdo->prepare('SELECT id, course_name, course_code FROM courses WHERE school_id = ? AND status = "Active" ORDER BY course_name');
$coursesStmt->execute([$schoolId]);
$courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);
$resourcesStmt = $pdo->prepare("SELECT r.*, c.course_name FROM study_resources r LEFT JOIN courses c ON c.id=r.course_id AND c.school_id=r.school_id WHERE r.school_id = ? AND r.is_published = 1 AND r.audience IN ('all', ?) ORDER BY r.created_at DESC LIMIT 100");
$resourcesStmt->execute([$schoolId, $audience]);
$resources = $resourcesStmt->fetchAll(PDO::FETCH_ASSOC);
$labsStmt = $pdo->prepare("SELECT l.*, c.course_name FROM lab_practicals l LEFT JOIN courses c ON c.id=l.course_id AND c.school_id=l.school_id WHERE l.school_id = ? AND l.status = 'published' AND l.audience IN ('all', ?) ORDER BY l.due_date IS NULL, l.due_date ASC, l.created_at DESC LIMIT 100");
$labsStmt->execute([$schoolId, $audience]);
$labs = $labsStmt->fetchAll(PDO::FETCH_ASSOC);
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid px-3 px-lg-4 py-4">
 <div class="mb-4"><h2 class="mb-1">Study Material &amp; Lab Practice</h2><p class="text-muted mb-0">School resources and practical exercises for your learning.</p></div>
 <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>
 <?php if ($canManage): ?><div class="row g-3 mb-4">
  <div class="col-12 col-xl-6"><div class="card border-0 shadow-sm"><div class="card-body"><h5>Add study material</h5><form method="post"><?php echo csrfField(); ?><input type="hidden" name="action" value="resource">
   <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" maxlength="180" required></div>
   <div class="mb-2"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
   <div class="row g-2"><div class="col"><label class="form-label">Type</label><select class="form-select" name="resource_type"><option value="link">Link</option><option value="document">Document</option><option value="video">Video</option><option value="other">Other</option></select></div><div class="col"><label class="form-label">Audience</label><select class="form-select" name="audience"><option value="students">Students</option><option value="teachers">Teachers</option><option value="all">Everyone</option></select></div></div>
   <div class="mb-2 mt-2"><label class="form-label">Resource URL (HTTP/S)</label><input type="url" class="form-control" name="resource_url" maxlength="2048" required></div>
   <div class="mb-3"><label class="form-label">Course (optional)</label><select class="form-select" name="course_id"><option value="0">General</option><?php foreach($courses as $course): ?><option value="<?php echo (int)$course['id']; ?>"><?php echo e($course['course_code'].' — '.$course['course_name']); ?></option><?php endforeach; ?></select></div>
   <button class="btn btn-primary">Publish material</button></form></div></div></div>
  <div class="col-12 col-xl-6"><div class="card border-0 shadow-sm"><div class="card-body"><h5>Add lab practical</h5><form method="post"><?php echo csrfField(); ?><input type="hidden" name="action" value="lab">
   <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" maxlength="180" required></div>
   <div class="mb-2"><label class="form-label">Instructions</label><textarea class="form-control" name="instructions" rows="3" maxlength="20000" required></textarea></div>
   <div class="row g-2"><div class="col"><label class="form-label">Due date</label><input type="date" class="form-control" name="due_date"></div><div class="col"><label class="form-label">Audience</label><select class="form-select" name="audience"><option value="students">Students</option><option value="teachers">Teachers</option><option value="all">Everyone</option></select></div></div>
   <div class="mt-2 mb-3"><label class="form-label">Course (optional)</label><select class="form-select" name="course_id"><option value="0">General</option><?php foreach($courses as $course): ?><option value="<?php echo (int)$course['id']; ?>"><?php echo e($course['course_code'].' — '.$course['course_name']); ?></option><?php endforeach; ?></select></div>
   <button class="btn btn-primary">Publish practical</button></form></div></div></div>
 </div><?php endif; ?>
 <div class="row g-4"><div class="col-12 col-xl-6"><h4 class="mb-3">Study material</h4>
 <?php if (!$resources): ?><div class="alert alert-light border">No study material published yet.</div><?php endif; ?>
 <?php foreach($resources as $r): ?><article class="card border-0 shadow-sm mb-3"><div class="card-body"><div class="d-flex justify-content-between gap-2"><h6><?php echo e($r['title']); ?></h6><span class="badge bg-info text-dark"><?php echo e(ucfirst($r['resource_type'])); ?></span></div><?php if($r['description']): ?><p class="text-muted"><?php echo nl2br(e($r['description'])); ?></p><?php endif; ?><small class="text-muted"><?php echo e($r['course_name'] ?? 'General resource'); ?></small><div class="mt-2"><a class="btn btn-sm btn-outline-primary" href="<?php echo e($r['resource_url']); ?>" target="_blank" rel="noopener noreferrer">Open resource <i class="fas fa-external-link-alt ms-1"></i></a></div></div></article><?php endforeach; ?>
 </div><div class="col-12 col-xl-6"><h4 class="mb-3">Lab practice</h4>
 <?php if (!$labs): ?><div class="alert alert-light border">No lab practicals published yet.</div><?php endif; ?>
 <?php foreach($labs as $lab): ?><article class="card border-0 shadow-sm mb-3"><div class="card-body"><div class="d-flex justify-content-between gap-2"><h6><?php echo e($lab['title']); ?></h6><?php if($lab['due_date']): ?><span class="badge bg-warning text-dark">Due <?php echo e(date('d M Y',strtotime($lab['due_date']))); ?></span><?php endif; ?></div><p><?php echo nl2br(e($lab['instructions'])); ?></p><small class="text-muted"><?php echo e($lab['course_name'] ?? 'General practical'); ?></small></div></article><?php endforeach; ?>
 </div></div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
