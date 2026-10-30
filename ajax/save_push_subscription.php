<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/push.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// CSRF from header or body
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) { $input = $_POST; }
if ($csrf === '') { $csrf = (string)($input['csrf_token'] ?? ''); }

if (!validateCsrfToken($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$schoolId = currentSchoolId();
if ($schoolId === null) { $schoolId = 0; }
$schoolId = $schoolId > 0 ? (int)$schoolId : null;

$ok = push_save_subscription($pdo, $userId, $schoolId, $input);
if ($ok) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid subscription']);
}
