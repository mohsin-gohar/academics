<?php
$page_title = 'My Photo';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';
requireStudent();
$schoolId = (int)(currentSchoolId() ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$stmt = $pdo->prepare('SELECT id, profile_image FROM students WHERE user_id = ? AND school_id = ? LIMIT 1');
$stmt->execute([$userId, $schoolId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$student) { http_response_code(404); exit('Student record not found.'); }
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    requirePost();
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(419); exit('Your session expired. Refresh and try again.'); }
    $file = $_FILES['photo'] ?? null;
    if (!$file || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Choose a valid image to upload.';
    } elseif ((int)$file['size'] <= 0 || (int)$file['size'] > 3 * 1024 * 1024) {
        $error = 'Photo must be no larger than 3 MB.';
    } else {
        $tmp = (string)$file['tmp_name'];
        $info = @getimagesize($tmp);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $types = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if (!$info || !isset($types[$mime]) || (int)$info[0] < 80 || (int)$info[1] < 80 || (int)$info[0] > 6000 || (int)$info[1] > 6000) {
            $error = 'Only valid JPEG, PNG or WebP images between 80px and 6000px are accepted.';
        } else {
            $relativeDir = 'storage/student-profiles/' . $schoolId;
            $absoluteDir = dirname(__DIR__) . '/' . $relativeDir;
            if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0700, true) && !is_dir($absoluteDir)) {
                $error = 'Photo storage is unavailable. Contact your school administrator.';
            } else {
                $filename = bin2hex(random_bytes(24)) . '.' . $types[$mime];
                $absolutePath = $absoluteDir . '/' . $filename;
                if (!move_uploaded_file($tmp, $absolutePath)) {
                    $error = 'The photo could not be saved. Please try again.';
                } else {
                    chmod($absolutePath, 0600);
                    $relativePath = $relativeDir . '/' . $filename;
                    $update = $pdo->prepare('UPDATE students SET profile_image = ? WHERE id = ? AND school_id = ? AND user_id = ?');
                    $update->execute([$relativePath, (int)$student['id'], $schoolId, $userId]);
                    if ($update->rowCount() === 0) {
                        $verify = $pdo->prepare('SELECT profile_image FROM students WHERE id = ? AND school_id = ? AND user_id = ?');
                        $verify->execute([(int)$student['id'], $schoolId, $userId]);
                        if ($verify->fetchColumn() !== $relativePath) { @unlink($absolutePath); $error = 'Unable to update your student profile.'; }
                    }
                    if ($error === '') {
                        $old = (string)($student['profile_image'] ?? '');
                        if (preg_match('#^storage/student-profiles/' . $schoolId . '/[a-f0-9]{48}\.(jpg|png|webp)$#', $old)) {
                            $oldPath = dirname(__DIR__) . '/' . $old;
                            if (is_file($oldPath) && realpath(dirname($oldPath)) === realpath($absoluteDir)) @unlink($oldPath);
                        }
                        $_SESSION['flash_message'] = ['type'=>'success','message'=>'Your profile photo has been updated.'];
                        header('Location: photo.php'); exit;
                    }
                }
            }
        }
    }
}
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid px-3 px-lg-4 py-4"><div class="row justify-content-center"><div class="col-12 col-md-7 col-lg-5">
 <div class="card border-0 shadow-sm"><div class="card-body text-center p-4"><h2 class="h4 mb-2">My Photo</h2><p class="text-muted">Upload a clear profile photo (JPEG, PNG or WebP, maximum 3 MB).</p>
 <?php if($error): ?><div class="alert alert-danger text-start"><?php echo e($error); ?></div><?php endif; ?>
 <?php if(!empty($student['profile_image'])): ?><img src="photo_view.php" alt="Your profile photo" class="rounded-circle mb-3" style="width:144px;height:144px;object-fit:cover"><?php else: ?><div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width:144px;height:144px"><i class="fas fa-user fa-4x text-secondary"></i></div><?php endif; ?>
 <form method="post" enctype="multipart/form-data" class="text-start"><?php echo csrfField(); ?><label class="form-label" for="photo">Choose photo</label><input class="form-control mb-3" type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" required><button class="btn btn-primary w-100">Upload photo</button></form>
 </div></div></div></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
