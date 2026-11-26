<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';
requireAuth();
$id = (int)($_GET['id'] ?? ($_SESSION['student_id'] ?? 0));
$schoolId = (int)(currentSchoolId() ?? 0);
if ($id <= 0 || !$schoolId) { http_response_code(404); exit; }
$stmt = $pdo->prepare('SELECT id, user_id, school_id, profile_image FROM students WHERE id = ? AND school_id = ? LIMIT 1');
$stmt->execute([$id, $schoolId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$student) { http_response_code(404); exit; }
if (isStudent() && (int)$student['user_id'] !== (int)$_SESSION['user_id']) { http_response_code(403); exit; }
if (isParent()) {
    $link = $pdo->prepare('SELECT 1 FROM parent_student WHERE parent_id = ? AND student_id = ? AND school_id = ? LIMIT 1');
    $link->execute([(int)$_SESSION['user_id'], $id, $schoolId]);
    if (!$link->fetchColumn()) { http_response_code(403); exit; }
}
$relative = (string)($student['profile_image'] ?? '');
if (!preg_match('#^storage/student-profiles/' . $schoolId . '/[a-f0-9]{48}\.(jpg|png|webp)$#', $relative)) { http_response_code(404); exit; }
$root = realpath(dirname(__DIR__) . '/storage/student-profiles/' . $schoolId);
$file = realpath(dirname(__DIR__) . '/' . $relative);
if (!$root || !$file || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) { http_response_code(404); exit; }
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) { http_response_code(415); exit; }
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($file);
