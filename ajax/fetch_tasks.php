<?php
/**
 * AJAX Endpoint - Fetch Tasks with Search, Filters & Pagination
 * EduSphere Student Management System
 */
require_once __DIR__ . '/../includes/functions.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = getUserRole();

// Inputs
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$priority = trim($_GET['priority'] ?? '');
$student_filter = (int)($_GET['student'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(5, min(50, (int)($_GET['per_page'] ?? 10)));
$offset = ($page - 1) * $perPage;

// Determine base scope according to role
if (isAdmin()) {
    $baseWhere = '1=1';
    $baseParams = [];
} elseif (isTeacher()) {
    $baseWhere = 't.user_id = ?';
    $baseParams = [$user_id];
} else {
    // Student sees their assigned tasks
    $stStmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $stStmt->execute([$user_id]);
    $stId = $stStmt->fetchColumn();
    if ($stId) {
        $baseWhere = 't.student_id = ?';
        $baseParams = [(int)$stId];
    } else {
        $baseWhere = 't.user_id = ?';
        $baseParams = [$user_id];
    }
}

$where = [$baseWhere];
$params = $baseParams;

if ($search !== '') {
    $where[] = '(t.title LIKE ? OR t.description LIKE ?)';
    $sp = '%' . $search . '%';
    $params[] = $sp;
    $params[] = $sp;
}
if ($status !== '' && in_array($status, ['Pending', 'In Progress', 'Completed', 'Cancelled'])) {
    $where[] = 't.status = ?';
    $params[] = $status;
}
if ($priority !== '' && in_array($priority, ['Low', 'Medium', 'High', 'Urgent'])) {
    $where[] = 't.priority = ?';
    $params[] = $priority;
}
if ($student_filter > 0 && isStaff()) {
    $where[] = 't.student_id = ?';
    $params[] = $student_filter;
}

$whereClause = implode(' AND ', $where);

try {
    // Count total matching tasks
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks t WHERE $whereClause");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $perPage));
    $currentPage = min($page, $totalPages);

    // Fetch tasks
    $sql = "SELECT t.*, s.full_name as student_name, s.student_number, c.course_name, c.course_code 
            FROM tasks t 
            LEFT JOIN students s ON t.student_id = s.id 
            LEFT JOIN courses c ON t.course_id = c.id 
            WHERE $whereClause 
            ORDER BY t.created_at DESC 
            LIMIT $perPage OFFSET $offset";
    $taskStmt = $pdo->prepare($sql);
    $taskStmt->execute($params);
    $rows = $taskStmt->fetchAll();

    $tasks = [];
    $today = date('Y-m-d');
    foreach ($rows as $r) {
        $isOverdue = (!empty($r['due_date']) && $r['due_date'] < $today && $r['status'] !== 'Completed');
        
        $badgeClass = match($r['status']) {
            'Completed' => 'bg-success',
            'In Progress' => 'bg-warning text-dark',
            'Cancelled' => 'bg-danger',
            default => 'bg-secondary'
        };

        $priorityClass = match($r['priority']) {
            'Urgent' => 'bg-danger',
            'High' => 'bg-warning text-dark',
            'Low' => 'bg-info text-dark',
            default => 'bg-primary'
        };

        $canEdit = isAdmin() || (isTeacher() && (int)$r['user_id'] === (int)$user_id);
        $canDelete = $canEdit;
        $canChangeStatus = isAdmin() || isTeacher() || (isStudent() && !empty($stId) && (int)$r['student_id'] === (int)$stId);

        $tasks[] = [
            'id' => (int)$r['id'],
            'title' => htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8'),
            'description' => htmlspecialchars($r['description'] ?? '', ENT_QUOTES, 'UTF-8'),
            'priority' => $r['priority'],
            'priority_badge' => $priorityClass,
            'status' => $r['status'],
            'status_badge' => $badgeClass,
            'student_name' => $r['student_name'] ? htmlspecialchars($r['student_name'], ENT_QUOTES, 'UTF-8') : '-',
            'student_number' => $r['student_number'] ?? null,
            'course_name' => $r['course_name'] ? htmlspecialchars($r['course_name'], ENT_QUOTES, 'UTF-8') : '-',
            'course_code' => $r['course_code'] ?? null,
            'due_date' => $r['due_date'] ? date('M d, Y', strtotime($r['due_date'])) : '-',
            'raw_due_date' => $r['due_date'],
            'is_overdue' => $isOverdue,
            'completed_at' => $r['completed_at'],
            'created_at' => date('M d, Y', strtotime($r['created_at'])),
            'can_edit' => $canEdit,
            'can_delete' => $canDelete,
            'can_change_status' => $canChangeStatus
        ];
    }

    // Live statistics based on user's scope
    $statSql = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN due_date < CURDATE() AND status != 'Completed' THEN 1 ELSE 0 END) as overdue
        FROM tasks t WHERE $baseWhere";
    $statStmt = $pdo->prepare($statSql);
    $statStmt->execute($baseParams);
    $statRow = $statStmt->fetch();

    $stats = [
        'total' => (int)($statRow['total'] ?? 0),
        'completed' => (int)($statRow['completed'] ?? 0),
        'pending' => (int)($statRow['pending'] ?? 0),
        'in_progress' => (int)($statRow['in_progress'] ?? 0),
        'overdue' => (int)($statRow['overdue'] ?? 0)
    ];

    echo json_encode([
        'success' => true,
        'tasks' => $tasks,
        'pagination' => [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'has_prev' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages
        ],
        'stats' => $stats
    ]);
} catch (PDOException $e) {
    error_log("fetch_tasks failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database query error']);
}
