<?php
/**
 * Main Application Dashboard — EduStudy-inspired, role-aware.
 * All metrics are scoped to the current school unless the viewer is a
 * platform super admin. Task visibility follows the same rules as tasks/index.php.
 */
$page_title = 'Dashboard';
require_once 'includes/header.php';
requireAuth();

$user_id       = (int)$_SESSION['user_id'];
$role          = getUserRole();
$isSuper       = isSuperAdmin();
$isSchoolAdmin = isSchoolAdmin() || $role === 'admin';
$isTeacher     = isTeacher();
$isStudentUser = isStudent();
$isParentUser  = isParent();
$isStaffUser   = isStaff();
$schoolId      = currentSchoolId();
$scopeSchoolId = $schoolId ? (int)$schoolId : 0;
$school        = $scopeSchoolId ? currentSchool($pdo) : null;

/* -------------------------------------------------------------------------
 * Active emergency alert (school-scoped for school users)
 * ---------------------------------------------------------------------- */
$activeAlert = null;
if ($scopeSchoolId) {
    $stmt = $pdo->prepare("SELECT * FROM emergency_alerts
        WHERE school_id = ? AND is_active = 1
          AND effective_date <= CURDATE()
          AND (end_date IS NULL OR end_date >= CURDATE())
        ORDER BY id DESC LIMIT 1");
    $stmt->execute([$scopeSchoolId]);
    $activeAlert = $stmt->fetch() ?: null;
} else {
    $activeAlert = getActiveEmergencyAlert($pdo);
}

/* -------------------------------------------------------------------------
 * Upcoming events (school-scoped; super admin sees the platform feed)
 * ---------------------------------------------------------------------- */
if ($scopeSchoolId) {
    $stmt = $pdo->prepare("SELECT * FROM school_events
        WHERE school_id = ? AND event_date >= CURDATE() AND status <> 'Cancelled'
        ORDER BY event_date ASC LIMIT 4");
    $stmt->execute([$scopeSchoolId]);
    $upcomingEvents = $stmt->fetchAll();
} else {
    $stmt = $pdo->query("SELECT e.*, s.school_name FROM school_events e
        LEFT JOIN schools s ON s.id = e.school_id
        WHERE e.event_date >= CURDATE() AND e.status <> 'Cancelled'
        ORDER BY e.event_date ASC LIMIT 4");
    $upcomingEvents = $stmt->fetchAll();
}

/* -------------------------------------------------------------------------
 * Role context: student profile and linked children
 * ---------------------------------------------------------------------- */
$studentId = 0;
if ($isStudentUser) {
    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $studentId = (int)($stmt->fetchColumn() ?: 0);
}

$children = [];
if ($isParentUser && $scopeSchoolId) {
    $stmt = $pdo->prepare("SELECT st.*, ps.relationship,
            (SELECT COUNT(*) FROM tasks t WHERE t.student_id = st.id) AS task_total,
            (SELECT COUNT(*) FROM tasks t WHERE t.student_id = st.id AND t.status = 'Completed') AS task_done,
            (SELECT COUNT(*) FROM tasks t WHERE t.student_id = st.id AND t.status <> 'Completed' AND t.due_date < CURDATE()) AS task_overdue
        FROM parent_student ps
        JOIN students st ON st.id = ps.student_id
        WHERE ps.parent_id = ? AND ps.school_id = ?
        ORDER BY st.full_name");
    $stmt->execute([$user_id, $scopeSchoolId]);
    $children = $stmt->fetchAll();
}

$myCourses = [];
if ($isStudentUser && $studentId) {
    $stmt = $pdo->prepare("SELECT c.course_code, c.course_name, e.status, e.grade
        FROM enrollments e JOIN courses c ON c.id = e.course_id
        WHERE e.student_id = ? ORDER BY c.course_name LIMIT 4");
    $stmt->execute([$studentId]);
    $myCourses = $stmt->fetchAll();
}

/* -------------------------------------------------------------------------
 * Task scope
 * ---------------------------------------------------------------------- */
$taskWhere  = [];
$taskParams = [];

if ($isSuper) {
    $taskWhere[] = '1=1';
} elseif ($isParentUser) {
    $taskWhere[] = 't.school_id = ?';
    $taskParams[] = $scopeSchoolId;
    $taskWhere[] = 't.student_id IN (SELECT ps.student_id FROM parent_student ps WHERE ps.parent_id = ? AND ps.school_id = ?)';
    $taskParams[] = $user_id;
    $taskParams[] = $scopeSchoolId;
} elseif ($isStudentUser) {
    if ($studentId) {
        $taskWhere[] = 't.student_id = ?';
        $taskParams[] = $studentId;
    } else {
        $taskWhere[] = '1=0';
    }
} else {
    $taskWhere[] = 't.school_id = ?';
    $taskParams[] = $scopeSchoolId;
    if ($isTeacher) {
        $taskWhere[] = '(t.user_id = ? OR t.assigned_to_user_id = ? OR t.created_by_user_id = ?)';
        $taskParams[] = $user_id;
        $taskParams[] = $user_id;
        $taskParams[] = $user_id;
    }
}
$taskScopeSql = implode(' AND ', $taskWhere);

/* -------------------------------------------------------------------------
 * KPI counts
 * ---------------------------------------------------------------------- */
$countRows = function (string $table, string $where = '1=1', array $params = []) use ($pdo): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
};

$totalStudents = $activeStudents = $totalTeachers = $totalParents = 0;
$totalCourses = $totalEnrollments = 0;

if ($isSuper) {
    $totalStudents    = $countRows('students', "status = 'Active'");
    $activeStudents   = $totalStudents;
    $totalTeachers    = $countRows('users', "role = 'teacher' AND is_active = 1");
    $totalParents     = $countRows('users', "role = 'parent' AND is_active = 1");
    $totalCourses     = $countRows('courses', "status = 'Active'");
    $totalEnrollments = $countRows('enrollments', '1=1');
} elseif ($scopeSchoolId) {
    $totalStudents    = $countRows('students', "status = 'Active' AND school_id = ?", [$scopeSchoolId]);
    $activeStudents   = $totalStudents;
    $totalTeachers    = $countRows('users', "role = 'teacher' AND is_active = 1 AND school_id = ?", [$scopeSchoolId]);
    $totalParents     = $countRows('users', "role = 'parent' AND is_active = 1 AND school_id = ?", [$scopeSchoolId]);
    $totalCourses     = $countRows('courses', "status = 'Active' AND school_id = ?", [$scopeSchoolId]);
    $totalEnrollments = $countRows('enrollments', 'school_id = ?', [$scopeSchoolId]);
}

$stmt = $pdo->prepare("SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'Completed'), 0) AS completed,
        COALESCE(SUM(status = 'In Progress'), 0) AS in_progress,
        COALESCE(SUM(status = 'Pending'), 0) AS pending,
        COALESCE(SUM(status <> 'Completed' AND due_date IS NOT NULL AND due_date < CURDATE()), 0) AS overdue
    FROM tasks t WHERE {$taskScopeSql}");
$stmt->execute($taskParams);
$taskStats = $stmt->fetch() ?: [];

$totalTasks  = (int)($taskStats['total'] ?? 0);
$completed   = (int)($taskStats['completed'] ?? 0);
$inProgress  = (int)($taskStats['in_progress'] ?? 0);
$pending     = (int)($taskStats['pending'] ?? 0);
$overdue     = (int)($taskStats['overdue'] ?? 0);
$completionRate = $totalTasks > 0 ? (int)round(($completed / $totalTasks) * 100) : 0;

/* -------------------------------------------------------------------------
 * Charts: monthly assigned / completed and enrollments (staff only)
 * ---------------------------------------------------------------------- */
$currentYear = (int)date('Y');
$monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$monthlyAssigned  = array_fill(0, 12, 0);
$monthlyCompleted = array_fill(0, 12, 0);
$enrollMonthly    = array_fill(0, 12, 0);

$stmt = $pdo->prepare("SELECT MONTH(t.created_at) AS m, COUNT(*) AS c
    FROM tasks t WHERE {$taskScopeSql} AND YEAR(t.created_at) = ?
    GROUP BY MONTH(t.created_at)");
$stmt->execute(array_merge($taskParams, [$currentYear]));
foreach ($stmt->fetchAll() as $row) {
    $idx = (int)$row['m'] - 1;
    if ($idx >= 0 && $idx < 12) $monthlyAssigned[$idx] = (int)$row['c'];
}

$stmt = $pdo->prepare("SELECT MONTH(t.completed_at) AS m, COUNT(*) AS c
    FROM tasks t WHERE {$taskScopeSql} AND t.status = 'Completed'
      AND t.completed_at IS NOT NULL AND YEAR(t.completed_at) = ?
    GROUP BY MONTH(t.completed_at)");
$stmt->execute(array_merge($taskParams, [$currentYear]));
foreach ($stmt->fetchAll() as $row) {
    $idx = (int)$row['m'] - 1;
    if ($idx >= 0 && $idx < 12) $monthlyCompleted[$idx] = (int)$row['c'];
}

if ($isStaffUser) {
    $sql = "SELECT MONTH(created_at) AS m, COUNT(*) AS c FROM enrollments WHERE YEAR(created_at) = ?";
    $params = [$currentYear];
    if ($scopeSchoolId) { $sql .= " AND school_id = ?"; $params[] = $scopeSchoolId; }
    $sql .= " GROUP BY MONTH(created_at)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $row) {
        $idx = (int)$row['m'] - 1;
        if ($idx >= 0 && $idx < 12) $enrollMonthly[$idx] = (int)$row['c'];
    }
}

$trendTitle = $isStaffUser ? 'Enrollments Trend' : 'Completed Tasks Trend';
$trendSub   = $isStaffUser ? 'Enrollments per month' : 'Tasks completed per month';
$trendData  = $isStaffUser ? $enrollMonthly : $monthlyCompleted;

/* -------------------------------------------------------------------------
 * Recent tasks
 * ---------------------------------------------------------------------- */
$stmt = $pdo->prepare("SELECT t.*, st.full_name AS student_name, c.course_name
    FROM tasks t
    LEFT JOIN students st ON st.id = t.student_id
    LEFT JOIN courses c ON c.id = t.course_id
    WHERE {$taskScopeSql}
    ORDER BY t.updated_at DESC, t.id DESC LIMIT 5");
$stmt->execute($taskParams);
$recentTasks = $stmt->fetchAll();

/* -------------------------------------------------------------------------
 * Top performers (school-scoped)
 * ---------------------------------------------------------------------- */
$perfSql = "SELECT s.id, s.full_name, s.student_number,
        (SELECT COUNT(*) FROM tasks t WHERE t.student_id = s.id AND t.status = 'Completed') AS done,
        (SELECT COUNT(*) FROM tasks t WHERE t.student_id = s.id) AS total
    FROM students s WHERE s.status = 'Active'";
$perfParams = [];
if ($scopeSchoolId) {
    $perfSql .= " AND s.school_id = ?";
    $perfParams[] = $scopeSchoolId;
}
$perfSql .= " ORDER BY done DESC, total ASC, s.full_name ASC LIMIT 4";
$stmt = $pdo->prepare($perfSql);
$stmt->execute($perfParams);
$topPerformers = $stmt->fetchAll();

/* -------------------------------------------------------------------------
 * Calendar helpers
 * ---------------------------------------------------------------------- */
$monthName     = date('F Y');
$daysInMonth   = (int)date('t');
$firstDayOfWeek = (int)date('w', strtotime(date('Y-m-01')));
$todayDay      = (int)date('j');
$eventDays     = [];
foreach ($upcomingEvents as $ev) {
    if (date('Y-m', strtotime($ev['event_date'])) === date('Y-m')) {
        $eventDays[(int)date('j', strtotime($ev['event_date']))] = $ev['event_type'] ?? 'Other';
    }
}

$eventColors = [
    'Holiday'  => 'var(--brand-amber)',
    'Exam'     => 'var(--brand-rose)',
    'Function' => 'var(--brand-indigo)',
    'Meeting'  => 'var(--brand-cyan)',
    'Other'    => 'var(--chart-slate)',
];
$statusColors = [
    'Completed'   => 'var(--brand-emerald)',
    'In Progress' => 'var(--brand-amber)',
    'Pending'     => 'var(--brand-indigo)',
];
$statusBadges = [
    'Completed'   => 'success',
    'In Progress' => 'warning',
    'Pending'     => 'info',
];

$heroDescription = $isParentUser
    ? "Follow your children's progress, tasks and school updates in one calm view."
    : ($isStudentUser
        ? 'Stay on top of your assignments, courses and deadlines from one focused workspace.'
        : 'Track tasks, courses and campus activity from one calm, focused workspace built for your whole school.');
?>
<!-- Topbar -->
<div class="top-dashboard-header">
    <div>
        <h2 class="dashboard-title">Dashboard</h2>
        <p class="text-muted small mb-0 d-flex align-items-center gap-2 flex-wrap">Here's what's happening<?php echo $school ? ' at ' . e($school['school_name']) : ' across your campus'; ?> today.
            <span class="date-chip"><i class="fas fa-calendar-day"></i><?php echo date('D, j M Y'); ?></span>
        </p>
    </div>
    <div class="search-pill-wrapper">
        <i class="fas fa-search search-pill-icon"></i>
        <input type="text" class="search-pill-input" placeholder="Search students, courses, tasks..." id="dashSearch">
        <span class="search-kbd">Ctrl+K</span>
    </div>
    <div class="header-user-actions">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle" title="Menu"><i class="fas fa-bars"></i></button>
        <button class="theme-toggle" id="themeToggle" type="button" title="Toggle theme" aria-label="Toggle theme"><i class="fas fa-moon"></i></button>
        <div class="dropdown">
            <button class="bell-btn" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                <i class="far fa-bell"></i>
                <?php if ($recentTasks): ?><span class="dot-alert"></span><?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end notification-dropdown p-2" style="min-width: 320px;">
                <li class="px-2 py-1 fw-semibold small">Recent Activity</li>
                <?php foreach ($recentTasks as $t): ?>
                    <li>
                        <a class="dropdown-item notification-item" href="tasks/show.php?id=<?php echo (int)$t['id']; ?>">
                            <div class="small">
                                <div class="notif-title fw-semibold text-truncate"><?php echo e($t['title']); ?></div>
                                <div class="notif-time text-muted"><?php echo e($t['student_name'] ?: 'Unassigned'); ?> · <?php echo formatDate($t['updated_at']); ?></div>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
                <?php if (!$recentTasks): ?><li><span class="dropdown-item-text text-muted small">No recent activity.</span></li><?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-center small fw-semibold" href="tasks/index.php">View all tasks</a></li>
            </ul>
        </div>
        <div class="dropdown">
            <button class="btn p-0 border-0 bg-transparent" data-bs-toggle="dropdown" aria-expanded="false" title="My account">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name'] ?? 'User'); ?>&background=4026E1&color=fff&size=64" alt="Avatar" class="user-profile-avatar">
            </button>
            <ul class="dropdown-menu dropdown-menu-end profile-dropdown">
                <li><h6 class="dropdown-header"><?php echo e($_SESSION['user_name'] ?? 'User'); ?></h6></li>
                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- EduStudy-inspired welcome hero -->
<div class="dash-hero">
    <div class="dash-hero-content">
        <span class="dash-hero-eyebrow"><i class="fas fa-wand-magic-sparkles"></i> <?php echo e(APP_NAME); ?> Workspace</span>
        <h2>Welcome back, <?php echo e($_SESSION['user_name'] ?? 'User'); ?></h2>
        <p><?php echo e($heroDescription); ?></p>
        <div class="dash-hero-actions">
            <?php if ($isParentUser): ?>
                <a href="parent/children.php" class="btn-hero-light"><i class="fas fa-user-group"></i> My Children</a>
                <a href="parent/progress.php" class="btn-hero-ghost"><i class="fas fa-chart-line"></i> Progress</a>
                <a href="parent/announcements.php" class="btn-hero-ghost"><i class="fas fa-bullhorn"></i> Announcements</a>
            <?php elseif ($isStudentUser): ?>
                <a href="tasks/index.php" class="btn-hero-light"><i class="fas fa-list-check"></i> My Tasks</a>
                <a href="courses/index.php" class="btn-hero-ghost"><i class="fas fa-book-open"></i> My Courses</a>
                <a href="reports/index.php" class="btn-hero-ghost"><i class="fas fa-file-lines"></i> Report Card</a>
            <?php else: ?>
                <a href="tasks/create.php" class="btn-hero-light"><i class="fas fa-plus"></i> New Task</a>
                <a href="students/index.php" class="btn-hero-ghost"><i class="fas fa-user-graduate"></i> Students</a>
                <a href="reports/index.php" class="btn-hero-ghost"><i class="fas fa-chart-line"></i> Reports</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="dash-hero-panel">
        <div class="dash-hero-stat">
            <span class="label"><?php echo $isParentUser ? 'Open' : 'Pending'; ?></span>
            <span class="value"><?php echo number_format($isParentUser ? max(0, $totalTasks - $completed) : $pending); ?></span>
        </div>
        <div class="dash-hero-stat">
            <span class="label">Completed</span>
            <span class="value"><?php echo number_format($completed); ?></span>
        </div>
        <div class="dash-hero-stat">
            <span class="label">Overdue</span>
            <span class="value"><?php echo number_format($overdue); ?></span>
        </div>
    </div>
</div>

<?php
$alertManageUrl = $isStaffUser ? 'admin/emergency_alert.php' : null;
require __DIR__ . '/includes/school_alert_modal.php';
?>

<!-- KPI cards -->
<div class="row g-3 mb-4">
    <?php if ($isParentUser): ?>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="kpi-card kpi-accent-indigo">
                <div class="kpi-icon icon-indigo"><i class="fas fa-children"></i></div>
                <div class="kpi-lbl">Children</div>
                <div class="kpi-val"><?php echo count($children); ?></div>
                <div class="kpi-sub">linked to your account</div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="kpi-card kpi-accent-amber">
                <div class="kpi-icon icon-amber"><i class="fas fa-clipboard-list"></i></div>
                <div class="kpi-lbl">Open Tasks</div>
                <div class="kpi-val"><?php echo max(0, $totalTasks - $completed); ?></div>
                <div class="kpi-sub">across all children</div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="kpi-card kpi-accent-emerald">
                <div class="kpi-icon icon-emerald"><i class="fas fa-circle-check"></i></div>
                <div class="kpi-lbl">Completed</div>
                <div class="kpi-val"><?php echo $completed; ?></div>
                <div class="kpi-sub">tasks finished</div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="kpi-card kpi-accent-rose">
                <div class="kpi-icon icon-rose"><i class="fas fa-triangle-exclamation"></i></div>
                <div class="kpi-lbl">Overdue</div>
                <div class="kpi-val"><?php echo $overdue; ?></div>
                <div class="kpi-sub">needs attention</div>
            </div>
        </div>
    <?php elseif ($isStudentUser): ?>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="kpi-card kpi-accent-indigo">
                <div class="kpi-icon icon-indigo"><i class="fas fa-list-check"></i></div>
                <div class="kpi-lbl">My Tasks</div>
                <div class="kpi-val"><?php echo $totalTasks; ?></div>
                <div class="kpi-sub">assigned to you</div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="kpi-card kpi-accent-emerald">
                <div class="kpi-icon icon-emerald"><i class="fas fa-circle-check"></i></div>
                <div class="kpi-lbl">Completed</div>
                <div class="kpi-val"><?php echo $completed; ?></div>
                <div class="kpi-sub">tasks finished</div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="kpi-card kpi-accent-amber">
                <div class="kpi-icon icon-amber"><i class="fas fa-spinner"></i></div>
                <div class="kpi-lbl">In Progress</div>
                <div class="kpi-val"><?php echo $inProgress; ?></div>
                <div class="kpi-sub">currently working</div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="kpi-card kpi-accent-rose">
                <div class="kpi-icon icon-rose"><i class="fas fa-triangle-exclamation"></i></div>
                <div class="kpi-lbl">Overdue</div>
                <div class="kpi-val"><?php echo $overdue; ?></div>
                <div class="kpi-sub">needs attention</div>
            </div>
        </div>
    <?php else: ?>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="kpi-card kpi-accent-indigo">
                <div class="kpi-icon icon-indigo"><i class="fas fa-user-graduate"></i></div>
                <div class="kpi-lbl">Students</div>
                <div class="kpi-val"><?php echo number_format($totalStudents); ?></div>
                <div class="kpi-sub"><?php echo $isSuper ? 'active across all schools' : 'active in this school'; ?></div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="kpi-card kpi-accent-cyan">
                <div class="kpi-icon icon-cyan"><i class="fas fa-chalkboard-teacher"></i></div>
                <div class="kpi-lbl">Teachers</div>
                <div class="kpi-val"><?php echo number_format($totalTeachers); ?></div>
                <div class="kpi-sub"><?php echo number_format($totalParents); ?> <?php echo $totalParents === 1 ? 'parent linked' : 'parents linked'; ?></div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="kpi-card kpi-accent-emerald">
                <div class="kpi-icon icon-emerald"><i class="fas fa-book-open"></i></div>
                <div class="kpi-lbl">Courses</div>
                <div class="kpi-val"><?php echo number_format($totalCourses); ?></div>
                <div class="kpi-sub"><?php echo number_format($totalEnrollments); ?> enrollments</div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="kpi-card kpi-accent-amber">
                <div class="kpi-icon icon-amber"><i class="fas fa-list-check"></i></div>
                <div class="kpi-lbl">Tasks</div>
                <div class="kpi-val"><?php echo number_format($totalTasks); ?></div>
                <div class="kpi-sub"><?php echo $pending; ?> pending · <?php echo $overdue; ?> overdue</div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Charts -->
<div class="row g-4 mb-4">
    <div class="col-12 col-xl-8">
        <div class="chart-card h-100">
            <div class="section-head">
                <div class="sh-left">
                    <span class="head-icon indigo"><i class="fas fa-chart-column"></i></span>
                    <div><h6 class="chart-title">Tasks Overview</h6><span class="chart-sub">Monthly distribution · <?php echo $currentYear; ?></span></div>
                </div>
                <span class="badge bg-primary">12 Months</span>
            </div>
            <div style="height: 300px; position: relative;">
                <canvas id="monthlyTasksChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="chart-card h-100">
            <div class="section-head">
                <div class="sh-left">
                    <span class="head-icon emerald"><i class="fas fa-circle-half-stroke"></i></span>
                    <div><h6 class="chart-title">Completion</h6><span class="chart-sub">Overall rate</span></div>
                </div>
            </div>
            <div style="position: relative; height: 220px; max-width: 220px; margin: 0 auto;">
                <canvas id="completionChart"></canvas>
                <div style="position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none;">
                    <span style="font-size: 2rem; font-weight: 700; color: var(--text-main); line-height: 1;"><?php echo $completionRate; ?>%</span>
                    <span class="text-muted small">completed</span>
                </div>
            </div>
            <div class="d-flex flex-wrap justify-content-center gap-3 small mt-3">
                <span><i class="fas fa-circle me-1" style="color: var(--brand-indigo); font-size: .6rem;"></i>Completed <?php echo $completed; ?></span>
                <span><i class="fas fa-circle me-1" style="color: var(--brand-amber); font-size: .6rem;"></i>Pending <?php echo $pending; ?></span>
                <span><i class="fas fa-circle me-1" style="color: var(--chart-slate-light); font-size: .6rem;"></i>In Progress <?php echo $inProgress; ?></span>
            </div>
            <hr class="my-3">
            <div class="text-center">
                <div class="text-muted small">Overdue Tasks</div>
                <div class="h4 fw-bold mb-2" style="color: var(--brand-rose);"><?php echo $overdue; ?></div>
                <a href="tasks/index.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-arrow-right me-1"></i> Review tasks</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-xl-8">
        <div class="chart-card h-100">
            <div class="section-head">
                <div class="sh-left">
                    <span class="head-icon cyan"><i class="fas fa-chart-line"></i></span>
                    <div><h6 class="chart-title"><?php echo $trendTitle; ?></h6><span class="chart-sub"><?php echo $trendSub; ?></span></div>
                </div>
                <span class="badge bg-info">Trend</span>
            </div>
            <div style="height: 260px; position: relative;">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="chart-card h-100">
            <?php if ($isParentUser): ?>
                <div class="section-head">
                    <div class="sh-left">
                        <span class="head-icon indigo"><i class="fas fa-user-group"></i></span>
                        <div><h6 class="chart-title">My Children</h6><span class="chart-sub">Completion at a glance</span></div>
                    </div>
                </div>
                <?php foreach ($children as $c): ?>
                    <?php $pct = (int)$c['task_total'] > 0 ? (int)round(((int)$c['task_done'] / (int)$c['task_total']) * 100) : 0; ?>
                    <div class="d-flex align-items-center gap-2 py-2 border-bottom border-light">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($c['full_name']); ?>&background=4026E1&color=fff&size=64" alt="" class="performer-avatar-thumb">
                        <div class="flex-grow-1" style="min-width: 0;">
                            <div class="fw-semibold small text-truncate"><?php echo e($c['full_name']); ?></div>
                            <div class="text-muted" style="font-size: .72rem;"><?php echo e($c['student_number']); ?> · <?php echo e(ucfirst($c['relationship'])); ?></div>
                        </div>
                        <div class="text-end" style="min-width: 64px;">
                            <div class="fw-bold small" style="color: var(--brand-indigo);"><?php echo $pct; ?>%</div>
                            <div class="progress-thin"><div class="progress-thin-bar" style="width: <?php echo $pct; ?>%"></div></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$children): ?><div class="empty-state text-center py-4"><i class="fas fa-user-graduate"></i><p class="small mb-0">No children linked yet.</p></div><?php endif; ?>
                <a href="parent/children.php" class="btn btn-sm btn-outline-primary w-100 mt-3"><i class="fas fa-arrow-right me-1"></i> View all children</a>
            <?php elseif ($isStudentUser): ?>
                <div class="section-head">
                    <div class="sh-left">
                        <span class="head-icon indigo"><i class="fas fa-book-open"></i></span>
                        <div><h6 class="chart-title">My Courses</h6><span class="chart-sub">Current enrollments</span></div>
                    </div>
                </div>
                <?php foreach ($myCourses as $c): ?>
                    <div class="d-flex align-items-center justify-content-between gap-2 py-2 border-bottom border-light">
                        <div style="min-width: 0;">
                            <div class="fw-semibold small text-truncate"><?php echo e($c['course_name']); ?></div>
                            <div class="text-muted" style="font-size: .72rem;"><?php echo e($c['course_code']); ?></div>
                        </div>
                        <span class="badge bg-<?php echo $c['status'] === 'Completed' ? 'success' : 'primary'; ?>"><?php echo e($c['status']); ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (!$myCourses): ?><div class="empty-state text-center py-4"><i class="fas fa-book-open"></i><p class="small mb-0">No courses enrolled yet.</p></div><?php endif; ?>
                <a href="courses/index.php" class="btn btn-sm btn-outline-primary w-100 mt-3"><i class="fas fa-arrow-right me-1"></i> Browse courses</a>
            <?php else: ?>
                <div class="section-head">
                    <div class="sh-left">
                        <span class="head-icon amber"><i class="fas fa-bolt"></i></span>
                        <div><h6 class="chart-title">Quick Actions</h6><span class="chart-sub">Shortcuts for your day</span></div>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <a href="tasks/create.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Create Task</a>
                    <a href="students/create.php" class="btn btn-outline-primary"><i class="fas fa-user-plus me-2"></i>Add Student</a>
                    <a href="reports/index.php" class="btn btn-outline-secondary"><i class="fas fa-chart-line me-2"></i>View Reports</a>
                    <a href="events/index.php" class="btn btn-outline-secondary"><i class="fas fa-calendar-days me-2"></i>Manage Events</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Events Calendar -->
    <div class="col-12 col-xl-4">
        <div class="events-calendar-card h-100">
            <div class="section-head">
                <div class="sh-left">
                    <span class="head-icon amber"><i class="fas fa-calendar-days"></i></span>
                    <div><h6 class="chart-title">Events Calendar</h6><span class="chart-sub">Upcoming this month</span></div>
                </div>
                <a href="events/index.php" class="text-muted"><i class="fas fa-ellipsis-h"></i></a>
            </div>
            <?php if ($upcomingEvents): foreach (array_slice($upcomingEvents, 0, 2) as $ev): ?>
                <a href="events/index.php" class="mini-event-pill">
                    <div class="small" style="min-width: 0;">
                        <span class="mini-event-bullet" style="background-color: <?php echo $eventColors[$ev['event_type']] ?? 'var(--brand-indigo)'; ?>;"></span>
                        <span class="text-muted"><?php echo formatDate($ev['event_date'], 'd M, Y'); ?></span>
                        <div class="fw-bold mt-1 text-dark text-truncate" style="font-size: .85rem;"><?php echo e($ev['title']); ?></div>
                    </div>
                    <i class="fas fa-arrow-right text-muted small"></i>
                </a>
            <?php endforeach; else: ?>
                <div class="empty-state text-center py-3"><i class="fas fa-calendar-xmark"></i><p class="small mb-0">No upcoming events.</p></div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-2 px-1 mt-3">
                <span class="text-muted small fw-semibold"><i class="fas fa-chevron-left fa-xs"></i></span>
                <span class="fw-bold small text-dark"><?php echo $monthName; ?></span>
                <span class="text-muted small fw-semibold"><i class="fas fa-chevron-right fa-xs"></i></span>
            </div>
            <table class="mini-calendar-table">
                <thead><tr><th>SUN</th><th>MON</th><th>TUE</th><th>WED</th><th>THU</th><th>FRI</th><th>SAT</th></tr></thead>
                <tbody>
                    <?php $currentDay = 1; for ($row = 0; $row < 5; $row++): ?>
                    <tr>
                        <?php for ($col = 0; $col < 7; $col++):
                            if ($row === 0 && $col < $firstDayOfWeek) {
                                echo '<td></td>';
                            } elseif ($currentDay <= $daysInMonth) {
                                $cls = '';
                                if ($currentDay === $todayDay) {
                                    $cls = 'active-today';
                                } elseif (isset($eventDays[$currentDay])) {
                                    $cls = 'active-cyan';
                                }
                                echo '<td><span class="cal-day-cell ' . $cls . '">' . $currentDay . '</span></td>';
                                $currentDay++;
                            } else {
                                echo '<td></td>';
                            }
                        endfor; ?>
                    </tr>
                    <?php if ($currentDay > $daysInMonth) break; endfor; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="col-12 col-xl-4">
        <div class="chart-card h-100">
            <div class="section-head">
                <div class="sh-left">
                    <span class="head-icon indigo"><i class="fas fa-bolt"></i></span>
                    <div><h6 class="chart-title">Recent Activity</h6><span class="chart-sub">Latest task updates</span></div>
                </div>
                <a href="tasks/index.php" class="text-muted"><i class="fas fa-ellipsis-h"></i></a>
            </div>
            <?php if ($recentTasks): foreach ($recentTasks as $t): ?>
                <a href="tasks/show.php?id=<?php echo (int)$t['id']; ?>" class="mini-event-pill">
                    <div class="small" style="min-width: 0;">
                        <span class="mini-event-bullet" style="background-color: <?php echo $statusColors[$t['status']] ?? 'var(--brand-indigo)'; ?>;"></span>
                        <span class="text-muted"><?php echo e($t['student_name'] ?: 'Unassigned'); ?></span>
                        <div class="fw-bold mt-1 text-dark text-truncate" style="font-size: .85rem;"><?php echo e($t['title']); ?></div>
                    </div>
                    <span class="badge bg-<?php echo $statusBadges[$t['status']] ?? 'secondary'; ?>"><?php echo e($t['status']); ?></span>
                </a>
            <?php endforeach; else: ?>
                <div class="empty-state text-center py-3"><i class="fas fa-inbox"></i><p class="small mb-0">No recent tasks.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Performers -->
    <div class="col-12 col-xl-4">
        <div class="top-performer-card h-100">
            <div class="section-head">
                <div class="sh-left">
                    <span class="head-icon rose"><i class="fas fa-trophy"></i></span>
                    <div><h6 class="chart-title">Top Performers</h6><span class="chart-sub">Best completion rates</span></div>
                </div>
                <a href="reports/index.php" class="text-muted"><i class="fas fa-ellipsis-h"></i></a>
            </div>
            <?php if ($topPerformers): foreach ($topPerformers as $p): ?>
                <?php $pct = (int)$p['total'] > 0 ? (int)round(((int)$p['done'] / (int)$p['total']) * 100) : 0; ?>
                <div class="d-flex align-items-center gap-2 py-2 border-bottom border-light">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($p['full_name']); ?>&background=4026E1&color=fff&size=64" alt="" class="performer-avatar-thumb">
                    <div class="flex-grow-1" style="min-width: 0;">
                        <div class="fw-semibold small text-truncate"><?php echo e($p['full_name']); ?></div>
                        <div class="text-muted" style="font-size: .72rem;"><?php echo e($p['student_number']); ?></div>
                    </div>
                    <div class="text-end" style="min-width: 64px;">
                        <div class="fw-bold small" style="color: var(--brand-indigo);"><?php echo $pct; ?>%</div>
                        <div class="progress-thin"><div class="progress-thin-bar" style="width: <?php echo $pct; ?>%"></div></div>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div class="empty-state text-center py-3"><i class="fas fa-trophy"></i><p class="small mb-0">No performer data yet.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$extra_js = '
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const monthLabels = ' . json_encode($monthLabels) . ';
    const monthlyAssigned = ' . json_encode(array_values($monthlyAssigned)) . ';
    const monthlyCompleted = ' . json_encode(array_values($monthlyCompleted)) . ';
    const trendData = ' . json_encode(array_values($trendData)) . ';
    const completion = ' . (int)$completionRate . ';
    const completed = ' . (int)$completed . ';
    const pending = ' . (int)$pending . ';
    const inProgress = ' . (int)$inProgress . ';
    const trendLabel = ' . json_encode($isStaffUser ? 'Enrollments' : 'Completed tasks') . ';

    // Chart.js cannot resolve CSS variables inside canvas; read the live palette.
    const rootStyles = getComputedStyle(document.documentElement);
    const chartNavy = rootStyles.getPropertyValue("--chart-navy").trim() || "#4026E1";
    const chartAmber = rootStyles.getPropertyValue("--chart-amber").trim() || "#F6C065";
    const chartSlate = rootStyles.getPropertyValue("--chart-slate").trim() || "#74748C";
    const chartSlateLight = rootStyles.getPropertyValue("--chart-slate-light").trim() || "#B9B4D4";
    const chartGrid = rootStyles.getPropertyValue("--chart-grid").trim() || "rgba(64,38,225,.10)";

    const ctxB = document.getElementById("monthlyTasksChart");
    if (ctxB) {
        new Chart(ctxB, {
            type: "bar",
            data: {
                labels: monthLabels,
                datasets: [
                    { label: "Assigned", data: monthlyAssigned, backgroundColor: chartNavy, maxBarThickness: 18, borderRadius: 4, barPercentage: .55, categoryPercentage: .62 },
                    { label: "Completed", data: monthlyCompleted, backgroundColor: chartAmber, maxBarThickness: 18, borderRadius: 4, barPercentage: .55, categoryPercentage: .62 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: "top", align: "end", labels: { boxWidth: 10, font: { size: 11 }, color: chartSlate } } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: chartSlate, font: { size: 10 } } },
                    y: { beginAtZero: true, ticks: { precision: 0, color: chartSlateLight }, grid: { color: chartGrid, borderDash: [4, 4] } }
                }
            }
        });
    }

    const ctxC = document.getElementById("completionChart");
    if (ctxC) {
        new Chart(ctxC, {
            type: "doughnut",
            data: { datasets: [{ data: [completed, pending, inProgress], backgroundColor: [chartNavy, chartAmber, chartSlateLight], borderWidth: 0 }] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "76%",
                plugins: { legend: { display: false }, tooltip: { enabled: false } }
            }
        });
    }

    const ctxT = document.getElementById("trendChart");
    if (ctxT) {
        new Chart(ctxT, {
            type: "line",
            data: {
                labels: monthLabels,
                datasets: [{
                    label: trendLabel,
                    data: trendData,
                    borderColor: chartNavy,
                    borderWidth: 2,
                    fill: true,
                    tension: .42,
                    pointRadius: 3,
                    pointBackgroundColor: chartNavy,
                    backgroundColor: (c) => {
                        const { ctx, chartArea } = c.chart;
                        if (!chartArea) return "rgba(64,38,225,.08)";
                        const g = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                        g.addColorStop(0, "rgba(64,38,225,.22)");
                        g.addColorStop(1, "rgba(64,38,225,0)");
                        return g;
                    }
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: chartSlate, font: { size: 10 } } },
                    y: { beginAtZero: true, ticks: { precision: 0, color: chartSlateLight }, grid: { color: chartGrid, borderDash: [4, 4] } }
                }
            }
        });
    }
});
</script>
';
require_once 'includes/footer.php';
?>
