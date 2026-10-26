<?php
/**
 * Reports & Academic Transcripts
 * EduSphere Student Management System
 */
require_once '../includes/functions.php';
startSecureSession();
require_once '../config/db.php';
requireAuth();

$user_id = $_SESSION['user_id'];
$isStudent = isStudent();

// Handle Student CSV Transcript Export
if ($isStudent && isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stStmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
    $stStmt->execute([$user_id]);
    $student = $stStmt->fetch();
    if (!$student) { exit('Student record not found'); }

    $filename = 'transcript_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $student['student_number']) . '_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, ['EDUSPHERE ACADEMY - OFFICIAL STUDENT ACADEMIC TRANSCRIPT']);
    fputcsv($output, ['Student Name', $student['full_name'], 'Student Number', $student['student_number']]);
    fputcsv($output, ['Email', $student['email'], 'Enrollment Date', $student['enrollment_date']]);
    fputcsv($output, []);
    fputcsv($output, array_map('safeCsvCell', ['Course Code', 'Course Name', 'Credits', 'Instructor', 'Status', 'Grade']));

    $rows = $pdo->prepare("SELECT c.course_code, c.course_name, c.credits, COALESCE(u.full_name, 'Staff') as instructor, e.status, COALESCE(e.grade, '-') as grade FROM enrollments e JOIN courses c ON e.course_id = c.id LEFT JOIN users u ON c.instructor_id = u.id WHERE e.student_id = ? ORDER BY c.course_name");
    $rows->execute([$student['id']]);
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
        fputcsv($output, array_map('safeCsvCell', array_values($r)));
    }
    fclose($output);
    exit;
}

// Handle Staff CSV Export
if (!$isStudent && isset($_GET['export']) && $_GET['export'] === 'csv') {
    requireStaff();
    $report_type = $_GET['type'] ?? 'students';
    $filename = 'edusphere_' . $report_type . '_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel

    if ($report_type === 'students') {
        fputcsv($output, array_map('safeCsvCell', ['Student #', 'Full Name', 'Email', 'Phone', 'Gender', 'Enrollment Date', 'Status']));
        $rows = $pdo->query("SELECT student_number, full_name, email, phone, gender, enrollment_date, status FROM students ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) fputcsv($output, array_map('safeCsvCell', array_values($r)));
    } elseif ($report_type === 'courses') {
        fputcsv($output, array_map('safeCsvCell', ['Course Code', 'Course Name', 'Credits', 'Instructor', 'Schedule', 'Status', 'Enrolled Count']));
        $rows = $pdo->query("SELECT c.course_code, c.course_name, c.credits, COALESCE(u.full_name, 'None') as instructor, c.schedule, c.status, (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status='Enrolled') as enrolled FROM courses c LEFT JOIN users u ON c.instructor_id = u.id ORDER BY c.course_name")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) fputcsv($output, array_map('safeCsvCell', array_values($r)));
    } elseif ($report_type === 'tasks') {
        fputcsv($output, array_map('safeCsvCell', ['Title', 'Student', 'Course', 'Priority', 'Status', 'Due Date', 'Created']));
        $rows = $pdo->query("SELECT t.title, COALESCE(s.full_name, 'Unassigned') as student, COALESCE(c.course_name, 'General') as course, t.priority, t.status, COALESCE(t.due_date, '-') as due_date, t.created_at FROM tasks t LEFT JOIN students s ON t.student_id=s.id LEFT JOIN courses c ON t.course_id=c.id ORDER BY t.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) fputcsv($output, array_map('safeCsvCell', array_values($r)));
    } elseif ($report_type === 'enrollments') {
        fputcsv($output, array_map('safeCsvCell', ['Student #', 'Student Name', 'Course Code', 'Course Name', 'Enrollment Date', 'Status', 'Grade']));
        $rows = $pdo->query("SELECT s.student_number, s.full_name, c.course_code, c.course_name, e.enrollment_date, e.status, COALESCE(e.grade, '-') as grade FROM enrollments e JOIN students s ON e.student_id=s.id JOIN courses c ON e.course_id=c.id ORDER BY e.enrollment_date DESC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) fputcsv($output, array_map('safeCsvCell', array_values($r)));
    }
    fclose($output);
    exit;
}

$page_title = $isStudent ? 'My Academic Report & Transcript' : 'Reports & Analytics';
require_once '../includes/header.php';

if ($isStudent):
    // Fetch Student Record & Performance
    $stStmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
    $stStmt->execute([$user_id]);
    $student = $stStmt->fetch();

    $studentId = $student ? (int)$student['id'] : 0;

    // Enrolled Courses
    $enrStmt = $pdo->prepare("SELECT e.*, c.course_code, c.course_name, c.credits, c.schedule, u.full_name as instructor_name FROM enrollments e JOIN courses c ON e.course_id = c.id LEFT JOIN users u ON c.instructor_id = u.id WHERE e.student_id = ? ORDER BY c.course_name");
    $enrStmt->execute([$studentId]);
    $myEnrollments = $enrStmt->fetchAll();

    // Tasks
    $taskStmt = $pdo->prepare("SELECT t.*, c.course_name FROM tasks t LEFT JOIN courses c ON t.course_id = c.id WHERE t.student_id = ? ORDER BY t.created_at DESC");
    $taskStmt->execute([$studentId]);
    $myTasks = $taskStmt->fetchAll();

    $totalTasks = count($myTasks);
    $completedTasks = 0;
    $pendingTasks = 0;
    $inProgressTasks = 0;
    foreach ($myTasks as $t) {
        if ($t['status'] === 'Completed') $completedTasks++;
        elseif ($t['status'] === 'Pending') $pendingTasks++;
        elseif ($t['status'] === 'In Progress') $inProgressTasks++;
    }
    $completionPct = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
?>

<!-- Print-Friendly Style -->
<style>
@media print {
    .sidebar, .topbar, .btn, .breadcrumb, footer, .card-header .btn { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; width: 100% !important; }
    .card { border: 1px solid #ddd !important; box-shadow: none !important; }
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 text-dark fw-bold"><i class="fas fa-file-invoice text-primary me-2"></i>Official Student Progress Transcript</h4>
        <p class="text-muted small mb-0">Verified academic record, course enrollments, and performance metrics.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="?export=csv" class="btn btn-outline-success btn-sm"><i class="fas fa-file-excel me-1"></i> Export CSV</a>
        <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fas fa-print me-1"></i> Print Transcript</button>
    </div>
</div>

<!-- Student Identity & High-Level Summary Card -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="row align-items-center g-3">
            <div class="col-md-2 text-center">
                <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center shadow-sm" style="width:72px; height:72px; font-size:1.8rem; font-weight:700;">
                    <?php echo strtoupper(substr($student['full_name'] ?? 'S', 0, 1)); ?>
                </div>
            </div>
            <div class="col-md-6">
                <h4 class="fw-bold mb-1 text-dark"><?php echo e($student['full_name'] ?? 'Student'); ?></h4>
                <div class="text-muted small mb-2">
                    <span class="me-3"><strong>Student ID:</strong> <code><?php echo e($student['student_number'] ?? 'N/A'); ?></code></span>
                    <span><strong>Email:</strong> <?php echo e($student['email'] ?? 'N/A'); ?></span>
                </div>
                <div>
                    <span class="badge bg-<?php echo ($student['status'] ?? '') === 'Active' ? 'success' : 'secondary'; ?> me-2"><?php echo e($student['status'] ?? 'Active'); ?></span>
                    <span class="badge bg-light text-dark border">Enrolled: <?php echo formatDate($student['enrollment_date'] ?? ''); ?></span>
                </div>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="p-3 bg-light rounded-3 text-center d-inline-block w-100" style="max-width: 240px;">
                    <div class="small text-muted fw-semibold">Task Completion Rate</div>
                    <div class="h3 fw-bold text-primary mb-1"><?php echo $completionPct; ?>%</div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-primary" style="width: <?php echo $completionPct; ?>%;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Academic KPI Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card shadow-sm border-0">
            <div class="card-body text-center p-3">
                <span class="text-muted small d-block">Enrolled Courses</span>
                <span class="h4 mb-0 fw-bold text-dark"><?php echo count($myEnrollments); ?></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card shadow-sm border-0">
            <div class="card-body text-center p-3">
                <span class="text-muted small d-block">Total Tasks</span>
                <span class="h4 mb-0 fw-bold text-dark"><?php echo $totalTasks; ?></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card shadow-sm border-0">
            <div class="card-body text-center p-3">
                <span class="text-muted small d-block">Completed</span>
                <span class="h4 mb-0 fw-bold text-success"><?php echo $completedTasks; ?></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card shadow-sm border-0">
            <div class="card-body text-center p-3">
                <span class="text-muted small d-block">In Progress / Pending</span>
                <span class="h4 mb-0 fw-bold text-warning"><?php echo ($inProgressTasks + $pendingTasks); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Enrolled Courses & Grades Table -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-graduation-cap text-primary me-2"></i>Enrolled Academic Courses & Current Standing</h6>
        <span class="badge bg-light text-dark border"><?php echo count($myEnrollments); ?> Registered</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($myEnrollments)): ?>
            <div class="p-4 text-center text-muted">No courses currently registered.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>Credits</th>
                            <th>Instructor</th>
                            <th>Schedule</th>
                            <th>Enrollment Date</th>
                            <th>Status</th>
                            <th>Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myEnrollments as $en): ?>
                        <tr>
                            <td><code><?php echo e($en['course_code']); ?></code></td>
                            <td class="fw-semibold text-dark"><?php echo e($en['course_name']); ?></td>
                            <td><?php echo (int)($en['credits'] ?? 3); ?> Cr</td>
                            <td><?php echo e($en['instructor_name'] ?: 'Department Faculty'); ?></td>
                            <td><small class="text-muted"><?php echo e($en['schedule'] ?: 'TBA'); ?></small></td>
                            <td><?php echo formatDate($en['enrollment_date']); ?></td>
                            <td><span class="badge bg-<?php echo $en['status'] === 'Enrolled' ? 'primary' : 'success'; ?>"><?php echo e($en['status']); ?></span></td>
                            <td><span class="fw-bold <?php echo !empty($en['grade']) ? 'text-success' : 'text-muted'; ?>"><?php echo e($en['grade'] ?: 'In Evaluation'); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Task Performance Breakdown -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-tasks text-success me-2"></i>Assigned Academic Tasks & Assignments</h6>
        <a href="../tasks/index.php" class="btn btn-sm btn-outline-primary">Manage Tasks</a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($myTasks)): ?>
            <div class="p-4 text-center text-muted">No tasks assigned yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Task Title</th>
                            <th>Course</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Due Date</th>
                            <th>Completion Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myTasks as $tk): ?>
                        <tr>
                            <td class="fw-semibold text-dark"><?php echo e($tk['title']); ?></td>
                            <td><?php echo e($tk['course_name'] ?: 'General'); ?></td>
                            <td><span class="badge priority-<?php echo strtolower($tk['priority']); ?>"><?php echo e($tk['priority']); ?></span></td>
                            <td><span class="badge bg-<?php echo $tk['status'] === 'Completed' ? 'success' : ($tk['status'] === 'In Progress' ? 'warning' : 'secondary'); ?>"><?php echo e($tk['status']); ?></span></td>
                            <td><?php echo $tk['due_date'] ? formatDate($tk['due_date']) : '-'; ?></td>
                            <td><?php echo $tk['completed_at'] ? formatDateTime($tk['completed_at']) : '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
else: 
    // STAFF & ADMIN VIEW
    $report_type = $_GET['type'] ?? 'students';

    // Summary stats
    $summary = [];
    $summary['students'] = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='Active' THEN 1 ELSE 0 END) as active FROM students")->fetch();
    $summary['courses'] = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='Active' THEN 1 ELSE 0 END) as active FROM courses")->fetch();
    $summary['tasks'] = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='Completed' THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN due_date < CURDATE() AND status != 'Completed' THEN 1 ELSE 0 END) as overdue FROM tasks")->fetch();
    $summary['enrollments'] = $pdo->query("SELECT COUNT(*) as total FROM enrollments WHERE status='Enrolled'")->fetch();
?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <a href="?type=students" class="card text-decoration-none h-100 <?php echo $report_type === 'students' ? 'border-primary shadow-sm' : ''; ?>">
            <div class="card-body text-center p-3">
                <i class="fas fa-user-graduate fa-2x text-primary mb-2"></i>
                <h6 class="fw-semibold text-dark mb-1">Students Report</h6>
                <small class="text-muted"><?php echo $summary['students']['total']; ?> records</small>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="?type=courses" class="card text-decoration-none h-100 <?php echo $report_type === 'courses' ? 'border-primary shadow-sm' : ''; ?>">
            <div class="card-body text-center p-3">
                <i class="fas fa-book fa-2x text-success mb-2"></i>
                <h6 class="fw-semibold text-dark mb-1">Courses Report</h6>
                <small class="text-muted"><?php echo $summary['courses']['total']; ?> records</small>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="?type=tasks" class="card text-decoration-none h-100 <?php echo $report_type === 'tasks' ? 'border-primary shadow-sm' : ''; ?>">
            <div class="card-body text-center p-3">
                <i class="fas fa-tasks fa-2x text-warning mb-2"></i>
                <h6 class="fw-semibold text-dark mb-1">Tasks Report</h6>
                <small class="text-muted"><?php echo $summary['tasks']['total']; ?> records</small>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="?type=enrollments" class="card text-decoration-none h-100 <?php echo $report_type === 'enrollments' ? 'border-primary shadow-sm' : ''; ?>">
            <div class="card-body text-center p-3">
                <i class="fas fa-clipboard-list fa-2x text-info mb-2"></i>
                <h6 class="fw-semibold text-dark mb-1">Enrollments Report</h6>
                <small class="text-muted"><?php echo $summary['enrollments']['total']; ?> records</small>
            </div>
        </a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <span><i class="fas fa-chart-bar me-2 text-primary"></i><strong><?php echo ucfirst($report_type); ?> Institutional Report</strong></span>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print me-1"></i> Print</button>
            <a href="?type=<?php echo $report_type; ?>&export=csv" class="btn btn-success btn-sm"><i class="fas fa-download me-1"></i> Export CSV</a>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if ($report_type === 'students'): ?>
            <?php $rows = $pdo->query("SELECT student_number, full_name, email, phone, gender, enrollment_date, status FROM students ORDER BY full_name")->fetchAll(); ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>#</th><th>Student #</th><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Enrolled</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $i => $r): ?>
                    <tr><td><?php echo $i+1; ?></td><td><code><?php echo e($r['student_number']); ?></code></td><td class="fw-medium text-dark"><?php echo e($r['full_name']); ?></td><td><?php echo e($r['email']); ?></td><td><?php echo e($r['phone'] ?: '-'); ?></td><td><span class="badge bg-<?php echo $r['status']==='Active'?'success':'secondary'; ?>"><?php echo e($r['status']); ?></span></td><td><?php echo formatDate($r['enrollment_date']); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($report_type === 'courses'): ?>
            <?php $rows = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM enrollments e WHERE e.course_id=c.id AND e.status='Enrolled') as enrolled FROM courses c ORDER BY c.course_name")->fetchAll(); ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Code</th><th>Name</th><th>Credits</th><th>Instructor</th><th>Status</th><th>Enrolled</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr><td><code><?php echo e($r['course_code']); ?></code></td><td class="fw-medium text-dark"><?php echo e($r['course_name']); ?></td><td><?php echo $r['credits'] ?? 3; ?></td><td><?php echo e($r['instructor_id'] ?: '-'); ?></td><td><span class="badge bg-<?php echo $r['status']==='Active'?'success':'secondary'; ?>"><?php echo e($r['status']); ?></span></td><td><?php echo $r['enrolled']; ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($report_type === 'tasks'): ?>
            <?php $rows = $pdo->query("SELECT t.*, s.full_name as student, c.course_name FROM tasks t LEFT JOIN students s ON t.student_id=s.id LEFT JOIN courses c ON t.course_id=c.id ORDER BY t.created_at DESC")->fetchAll(); ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Title</th><th>Student</th><th>Course</th><th>Priority</th><th>Status</th><th>Due</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr><td class="fw-medium text-dark"><?php echo e($r['title']); ?></td><td><?php echo e($r['student'] ?: '-'); ?></td><td><?php echo e($r['course_name'] ?: 'General'); ?></td><td><span class="badge priority-<?php echo strtolower($r['priority']); ?>"><?php echo e($r['priority']); ?></span></td><td><span class="badge bg-<?php echo $r['status']==='Completed'?'success':($r['status']==='In Progress'?'warning':'secondary'); ?>"><?php echo e($r['status']); ?></span></td><td><?php echo $r['due_date'] ? formatDate($r['due_date']) : '-'; ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($report_type === 'enrollments'): ?>
            <?php $rows = $pdo->query("SELECT s.student_number, s.full_name, c.course_code, c.course_name, e.enrollment_date, e.status, e.grade FROM enrollments e JOIN students s ON e.student_id=s.id JOIN courses c ON e.course_id=c.id ORDER BY e.enrollment_date DESC")->fetchAll(); ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Student #</th><th>Student</th><th>Course</th><th>Enrolled</th><th>Status</th><th>Grade</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr><td><code><?php echo e($r['student_number']); ?></code></td><td class="fw-medium text-dark"><?php echo e($r['full_name']); ?></td><td><?php echo e($r['course_code'] . ' - ' . $r['course_name']); ?></td><td><?php echo formatDate($r['enrollment_date']); ?></td><td><span class="badge bg-<?php echo $r['status']==='Enrolled'?'primary':($r['status']==='Completed'?'success':'secondary'); ?>"><?php echo e($r['status']); ?></span></td><td><?php echo e($r['grade'] ?: '-'); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
endif;
require_once '../includes/footer.php'; 
?>
