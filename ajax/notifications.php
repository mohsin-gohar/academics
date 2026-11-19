<?php
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// C4: re-validate the live session (account active, role, school active)
enforceSession($pdo);

$userId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    if ($action === 'list') {
        $stmt = $pdo->prepare("SELECT id, title, message, icon, color, icon_color, is_read, link, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 15");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['time'] = date('M d, h:i A', strtotime($r['created_at']));
        }
        $unread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $unread->execute([$userId]);
        echo json_encode(['success' => true, 'items' => $rows, 'unread' => (int)$unread->fetchColumn()]);
        exit;
    }

    if ($action === 'mark_all' || $action === 'mark_one') {
        // State-changing actions must be POST + CSRF protected.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid security token']);
            exit;
        }

        if ($action === 'mark_all') {
            $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$userId]);
            echo json_encode(['success' => true]);
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
