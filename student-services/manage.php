<?php
$page_title = 'Student Help Desk Management';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';
requireSchoolManagement();
$schoolId = (int)(currentSchoolId() ?? 0);
if (!$schoolId) { http_response_code(400); exit('Select a school before managing student submissions.'); }
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    requirePost();
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(419); exit('Your session expired.'); }
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $status = (string)($_POST['status'] ?? '');
    $response = trim((string)($_POST['staff_response'] ?? ''));
    if (!$id || !in_array($status, ['open','in_review','resolved','rejected'], true) || mb_strlen($response) > 10000) {
        $error = 'Invalid status, request, or response.';
    } else {
        $stmt = $pdo->prepare('UPDATE student_requests SET status = ?, staff_response = ?, responded_by = ?, responded_at = NOW() WHERE id = ? AND school_id = ?');
        $stmt->execute([$status, $response !== '' ? $response : null, (int)$_SESSION['user_id'], $id, $schoolId]);
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Student submission updated.'];
        header('Location: manage.php'); exit;
    }
}
$filter = (string)($_GET['status'] ?? '');
$where = $filter !== '' && in_array($filter, ['open','in_review','resolved','rejected'], true) ? ' AND r.status = ?' : '';
$sql = 'SELECT r.*, s.full_name AS student_name, s.student_number FROM student_requests r JOIN students s ON s.id=r.student_id AND s.school_id=r.school_id WHERE r.school_id = ?'.$where.' ORDER BY FIELD(r.status, "open", "in_review", "resolved", "rejected"), r.created_at DESC LIMIT 250';
$stmt = $pdo->prepare($sql);
$stmt->execute($where ? [$schoolId, $filter] : [$schoolId]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid px-3 px-lg-4 py-4">
 <div class="mb-4"><h2 class="mb-1">Student Help Desk</h2><p class="text-muted mb-0">Review and respond to school-scoped student submissions.</p></div>
 <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>
 <form method="get" class="mb-3 d-flex gap-2"><select class="form-select" name="status" style="max-width:220px"><option value="">All statuses</option><?php foreach (['open','in_review','resolved','rejected'] as $status): ?><option value="<?php echo e($status); ?>" <?php echo $filter===$status?'selected':''; ?>><?php echo e(ucwords(str_replace('_',' ',$status))); ?></option><?php endforeach; ?></select><button class="btn btn-outline-primary">Filter</button></form>
 <?php if (!$requests): ?><div class="alert alert-light border">No submissions match this filter.</div><?php endif; ?>
 <?php foreach ($requests as $item): ?><div class="card border-0 shadow-sm mb-3"><div class="card-body">
  <div class="d-flex flex-wrap justify-content-between gap-2"><div><span class="badge bg-light text-dark border"><?php echo e(ucwords(str_replace('_',' ',$item['request_type']))); ?></span><h5 class="mt-2 mb-1"><?php echo e($item['subject']); ?></h5><small class="text-muted"><?php echo e($item['student_name']); ?> · <?php echo e($item['student_number']); ?> · <?php echo e(date('d M Y H:i',strtotime($item['created_at']))); ?></small></div><span class="badge bg-secondary align-self-start"><?php echo e($item['status']); ?></span></div>
  <p class="mt-3"><?php echo nl2br(e($item['message'])); ?></p>
  <form method="post" class="border-top pt-3"><?php echo csrfField(); ?><input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
   <div class="row g-2"><div class="col-12 col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['open','in_review','resolved','rejected'] as $status): ?><option value="<?php echo e($status); ?>" <?php echo $item['status']===$status?'selected':''; ?>><?php echo e(ucwords(str_replace('_',' ',$status))); ?></option><?php endforeach; ?></select></div>
   <div class="col-12 col-md-7"><label class="form-label">Response to student</label><textarea class="form-control" rows="2" maxlength="10000" name="staff_response"><?php echo e($item['staff_response'] ?? ''); ?></textarea></div>
   <div class="col-12 col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Save response</button></div></div>
  </form>
 </div></div><?php endforeach; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
