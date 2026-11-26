<?php
$page_title = 'Student Help Desk';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';
requireStudent();
$schoolId = (int)(currentSchoolId() ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$studentStmt = $pdo->prepare('SELECT id FROM students WHERE user_id = ? AND school_id = ? LIMIT 1');
$studentStmt->execute([$userId, $schoolId]);
$studentId = (int)$studentStmt->fetchColumn();
if (!$schoolId || !$studentId) { http_response_code(404); exit('Student record not found.'); }
$allowed = ['issue','complaint','request','feedback','batch_change'];
$activeType = (string)($_GET['type'] ?? '');
if (!in_array($activeType, $allowed, true)) $activeType = '';
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    requirePost();
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(419); exit('Your session expired. Refresh and try again.');
    }
    $type = (string)($_POST['request_type'] ?? '');
    $subject = trim((string)($_POST['subject'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));
    if (!in_array($type, $allowed, true) || $subject === '' || mb_strlen($subject) > 180 || $message === '' || mb_strlen($message) > 10000) {
        $error = 'Please provide a valid category, subject (max 180 characters), and message (max 10,000 characters).';
    } else {
        $stmt = $pdo->prepare('INSERT INTO student_requests (school_id, student_id, user_id, request_type, subject, message) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$schoolId, $studentId, $userId, $type, $subject, $message]);
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Your request has been submitted to the school office.'];
        header('Location: index.php' . ($activeType !== '' ? '?type=' . rawurlencode($activeType) : '')); exit;
    }
}
$sql = 'SELECT id, request_type, subject, message, status, staff_response, responded_at, created_at FROM student_requests WHERE school_id = ? AND student_id = ?';
$params = [$schoolId, $studentId];
if ($activeType !== '') { $sql .= ' AND request_type = ?'; $params[] = $activeType; }
$sql .= ' ORDER BY created_at DESC LIMIT 100';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid px-3 px-lg-4 py-4">
 <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div><h2 class="mb-1"><?php echo $activeType !== '' ? e(ucwords(str_replace('_', ' ', $activeType))) : 'Student Help Desk'; ?></h2><p class="text-muted mb-0">Submit and track your student support records.</p></div>
  <span class="badge bg-primary"><?php echo count($requests); ?> submissions</span>
 </div>
 <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>
 <div class="row g-4">
  <div class="col-12 col-xl-5"><div class="card shadow-sm border-0"><div class="card-body">
   <h5 class="mb-3">Create a submission</h5>
   <form method="post">
    <?php echo csrfField(); ?>
    <div class="mb-3"><label class="form-label" for="request_type">Category</label>
     <select class="form-select" id="request_type" name="request_type" required>
      <option value="">Choose category</option><option value="issue" <?php echo $activeType === 'issue' ? 'selected' : ''; ?>>Technical / Portal Issue</option><option value="complaint" <?php echo $activeType === 'complaint' ? 'selected' : ''; ?>>Complaint</option><option value="request" <?php echo $activeType === 'request' ? 'selected' : ''; ?>>General Request</option><option value="batch_change" <?php echo $activeType === 'batch_change' ? 'selected' : ''; ?>>Batch Change Request</option><option value="feedback" <?php echo $activeType === 'feedback' ? 'selected' : ''; ?>>Feedback / Suggestion</option>
     </select>
    </div>
    <div class="mb-3"><label class="form-label" for="subject">Subject</label><input class="form-control" id="subject" name="subject" maxlength="180" required></div>
    <div class="mb-3"><label class="form-label" for="message">Details</label><textarea class="form-control" id="message" name="message" rows="5" maxlength="10000" required></textarea></div>
    <button class="btn btn-primary" type="submit"><i class="fas fa-paper-plane me-1"></i> Submit to school</button>
   </form>
  </div></div></div>
  <div class="col-12 col-xl-7"><h5 class="mb-3">My submissions</h5>
   <?php if (!$requests): ?><div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">You have not submitted anything yet.</div></div><?php endif; ?>
   <?php foreach ($requests as $item): ?>
    <article class="card border-0 shadow-sm mb-3"><div class="card-body">
     <div class="d-flex flex-wrap justify-content-between gap-2"><div><span class="badge bg-light text-dark border"><?php echo e(ucwords(str_replace('_',' ',$item['request_type']))); ?></span><h6 class="mt-2 mb-1"><?php echo e($item['subject']); ?></h6></div><span class="badge <?php echo $item['status']==='resolved'?'bg-success':($item['status']==='rejected'?'bg-danger':'bg-warning text-dark'); ?>"><?php echo e(ucwords(str_replace('_',' ',$item['status']))); ?></span></div>
     <p class="mb-2"><?php echo nl2br(e($item['message'])); ?></p>
     <?php if ($item['staff_response']): ?><div class="rounded bg-light p-3 mt-3"><strong>School response</strong><p class="mb-1 mt-1"><?php echo nl2br(e($item['staff_response'])); ?></p><small class="text-muted"><?php echo e($item['responded_at'] ?? ''); ?></small></div><?php endif; ?>
     <small class="text-muted">Submitted <?php echo e(date('d M Y, h:i A', strtotime($item['created_at']))); ?></small>
    </div></article>
   <?php endforeach; ?>
  </div>
 </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
