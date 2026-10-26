<?php
require_once '../includes/functions.php';
startSecureSession();
require_once '../config/db.php';
requireStaff();

$report_type = $_GET['type'] ?? 'students';

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
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

$page_title = 'Reports';
require_once '../includes/header.php';

// Summary stats
$summary = [];
$summary['students'] = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='Active' THEN 1 ELSE 0 END) as active FROM students")->fetch();
$summary['courses'] = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='Active' THEN 1 ELSE 0 END) as active FROM courses")->fetch();
$summary['tasks'] = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN status='Completed' THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN due_date < CURDATE() AND status != 'Completed' THEN 1 ELSE 0 END) as overdue FROM tasks")->fetch();
$summary['enrollments'] = $pdo->query("SELECT COUNT(*) as total FROM enrollments WHERE status='Enrolled'")->fetch();
?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <a href="?type=students" class="card text-decoration-none <?php echo $report_type === 'students' ? 'border-primary' : ''; ?>">
            <div class="card-body text-center">
                <i class="fas fa-user-graduate fa-2x text-primary mb-2"></i>
                <h6>Students Report</h6>
                <small class="text-muted"><?php echo $summary['students']['total']; ?> records</small>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="?type=courses" class="card text-decoration-none <?php echo $report_type === 'courses' ? 'border-primary' : ''; ?>">
            <div class="card-body text-center">
                <i class="fas fa-book fa-2x text-success mb-2"></i>
                <h6>Courses Report</h6>
                <small class="text-muted"><?php echo $summary['courses']['total']; ?> records</small>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="?type=tasks" class="card text-decoration-none <?php echo $report_type === 'tasks' ? 'border-primary' : ''; ?>">
            <div class="card-body text-center">
                <i class="fas fa-tasks fa-2x text-warning mb-2"></i>
                <h6>Tasks Report</h6>
                <small class="text-muted"><?php echo $summary['tasks']['total']; ?> records</small>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="?type=enrollments" class="card text-decoration-none <?php echo $report_type === 'enrollments' ? 'border-primary' : ''; ?>">
            <div class="card-body text-center">
                <i class="fas fa-clipboard-list fa-2x text-info mb-2"></i>
                <h6>Enrollments Report</h6>
                <small class="text-muted"><?php echo $summary['enrollments']['total']; ?> records</small>
            </div>
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-chart-bar me-2 text-primary"></i><?php echo ucfirst($report_type); ?> Report</span>
        <a href="?type=<?php echo $report_type; ?>&export=csv" class="btn btn-success btn-sm"><i class="fas fa-download me-1"></i> Export CSV</a>
    </div>
    <div class="card-body p-0">
        <?php if ($report_type === 'students'): ?>
            <?php $rows = $pdo->query("SELECT student_number, full_name, email, phone, gender, enrollment_date, status FROM students ORDER BY full_name")->fetchAll(); ?>
            <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>#</th><th>Student #</th><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Enrolled</th></tr></thead><tbody>
            <?php foreach ($rows as $i => $r): ?>
            <tr><td><?php echo $i+1; ?></td><td><code><?php echo e($r['student_number']); ?></code></td><td><?php echo e($r['full_name']); ?></td><td><?php echo e($r['email']); ?></td><td><?php echo e($r['phone'] ?: '-'); ?></td><td><span class="badge bg-<?php echo $r['status']==='Active'?'success':'secondary'; ?>"><?php echo e($r['status']); ?></span></td><td><?php echo formatDate($r['enrollment_date']); ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php elseif ($report_type === 'courses'): ?>
            <?php $rows = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM enrollments e WHERE e.course_id=c.id AND e.status='Enrolled') as enrolled FROM courses c ORDER BY c.course_name")->fetchAll(); ?>
            <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Code</th><th>Name</th><th>Credits</th><th>Instructor</th><th>Status</th><th>Enrolled</th></tr></thead><tbody>
            <?php foreach ($rows as $r): ?>
            <tr><td><code><?php echo e($r['course_code']); ?></code></td><td><?php echo e($r['course_name']); ?></td><td><?php echo $r['credits']; ?></td><td><?php echo e($r['instructor'] ?: '-'); ?></td><td><span class="badge bg-<?php echo $r['status']==='Active'?'success':'secondary'; ?>"><?php echo e($r['status']); ?></span></td><td><?php echo $r['enrolled']; ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php elseif ($report_type === 'tasks'): ?>
            <?php $rows = $pdo->query("SELECT t.*, s.full_name as student, c.course_name FROM tasks t LEFT JOIN students s ON t.student_id=s.id LEFT JOIN courses c ON t.course_id=c.id ORDER BY t.created_at DESC")->fetchAll(); ?>
            <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Title</th><th>Student</th><th>Course</th><th>Priority</th><th>Status</th><th>Due</th></tr></thead><tbody>
            <?php foreach ($rows as $r): ?>
            <tr><td><?php echo e($r['title']); ?></td><td><?php echo e($r['student'] ?: '-'); ?></td><td><?php echo e($r['course_name'] ?: '-'); ?></td><td><span class="badge priority-<?php echo strtolower($r['priority']); ?>"><?php echo e($r['priority']); ?></span></td><td><span class="badge bg-<?php echo $r['status']==='Completed'?'success':($r['status']==='In Progress'?'warning':'secondary'); ?>"><?php echo e($r['status']); ?></span></td><td><?php echo $r['due_date'] ? formatDate($r['due_date']) : '-'; ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php elseif ($report_type === 'enrollments'): ?>
            <?php $rows = $pdo->query("SELECT s.student_number, s.full_name, c.course_code, c.course_name, e.enrollment_date, e.status, e.grade FROM enrollments e JOIN students s ON e.student_id=s.id JOIN courses c ON e.course_id=c.id ORDER BY e.enrollment_date DESC")->fetchAll(); ?>
            <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Student #</th><th>Student</th><th>Course</th><th>Enrolled</th><th>Status</th><th>Grade</th></tr></thead><tbody>
            <?php foreach ($rows as $r): ?>
            <tr><td><code><?php echo e($r['student_number']); ?></code></td><td><?php echo e($r['full_name']); ?></td><td><?php echo e($r['course_code'] . ' - ' . $r['course_name']); ?></td><td><?php echo formatDate($r['enrollment_date']); ?></td><td><span class="badge bg-<?php echo $r['status']==='Enrolled'?'primary':($r['status']==='Completed'?'success':'secondary'); ?>"><?php echo e($r['status']); ?></span></td><td><?php echo e($r['grade'] ?: '-'); ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
