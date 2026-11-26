<!-- Sidebar (Matching Reference Image 1) -->
<?php
$sidebarSchool = currentSchool($pdo);
$sidebarBrandName = trim((string)($sidebarSchool['school_name'] ?? '')) ?: APP_NAME;
$sidebarPortalLabel = isStudent() ? 'STUDENT PORTAL'
    : (isTeacher() ? 'TEACHER PORTAL'
    : (isParent() ? 'PARENT PORTAL'
    : (isSuperAdmin() ? 'PLATFORM ADMIN' : 'SCHOOL PORTAL')));
?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <a href="<?php echo $base; ?>dashboard.php" class="text-decoration-none d-flex align-items-center gap-2">
            <img class="brand-logo-img" src="<?php echo $base; ?>assets/img/academics-mark.svg" alt="" width="40" height="40">
            <span class="brand-copy">
                <span class="brand-title"><?php echo e($sidebarBrandName); ?></span>
                <span class="brand-tagline"><?php echo e($sidebarPortalLabel); ?></span>
            </span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <?php
        // Each role lands on its own dashboard. Super admins use the dedicated
        // "Platform Overview" item below instead of a second dashboard link.
        $selfPath = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
        $dashFile = isParent()
            ? 'parent/dashboard.php'
            : (isSchoolAdmin() ? 'school-admin/dashboard.php' : 'dashboard.php');
        $dashActive = substr($selfPath, -strlen('/' . $dashFile)) === '/' . $dashFile;
        ?>
        <?php if (!isSuperAdmin()): ?>
        <div class="nav-item">
            <a href="<?php echo $base . $dashFile; ?>" class="nav-link <?php echo $dashActive ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (isParent()): ?>
        <div class="nav-section mt-2">Parent Portal</div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>parent/children.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'parent/children.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate"></i>
                <span>My Children</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>parent/attendance.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'parent/attendance.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i>
                <span>Attendance</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>parent/grades.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'parent/grades.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-list"></i>
                <span>Grades</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>parent/fees.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'parent/fees.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Fees</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>parent/progress.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'parent/progress.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span>Progress</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>parent/announcements.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'parent/announcements.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-bullhorn"></i>
                <span>Announcements</span>
            </a>
        </div>
        <?php endif; ?>

        <?php $showSchoolNav = !isSuperAdmin() || currentSchoolId() !== null; ?>
        <?php if ($showSchoolNav): ?>
        <?php if (isStaff()): ?>
        <div class="nav-item">
            <a href="<?php echo $base; ?>attendance/mark.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'attendance/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i>
                <span>Attendance</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>grades/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'grades/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-list"></i>
                <span>Grades</span>
            </a>
        </div>
        <?php if (isAdmin()): ?>
        <div class="nav-item">
            <a href="<?php echo $base; ?>fees/invoices.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'fees/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Fees</span>
            </a>
        </div>
        <?php endif; ?>
        <div class="nav-item">
            <a href="<?php echo $base; ?>students/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'students/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate"></i>
                <span>Students</span>
            </a>
        </div>
        <?php if (isAdmin()): ?>
        <div class="nav-item">
            <a href="<?php echo $base; ?>admin/users.php?role=teacher" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'role=teacher') !== false ? 'active' : ''; ?>">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Teachers</span>
            </a>
        </div>
        <?php endif; ?>
        <?php if (isAdmin()): ?>
        <div class="nav-item">
            <a href="<?php echo $base; ?>student-services/manage.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'student-services/manage.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-inbox"></i>
                <span>Student Help Desk</span>
            </a>
        </div>
        <?php endif; ?>
        <?php if (isAdmin()): ?>
        <div class="nav-item">
            <a href="<?php echo $base; ?>student-life/index.php?section=achievement" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'student-life/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-award"></i>
                <span>Student Portfolio</span>
            </a>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <?php
        $myStuId = $_SESSION['student_id'] ?? null;
        if (!$myStuId && isset($_SESSION['user_id']) && isset($pdo)) {
            $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $myStuId = $stmt->fetchColumn();
            if ($myStuId) {
                $_SESSION['student_id'] = (int)$myStuId;
            }
        }
        ?>
        <?php if ($myStuId): ?>
        <div class="nav-item">
            <a href="<?php echo $base; ?>students/show.php?id=<?php echo $myStuId; ?>" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'students/show.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-id-card"></i>
                <span>My Profile</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>students/photo.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'students/photo.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-camera"></i>
                <span>My Photo</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>notes/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'notes/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-bullhorn"></i>
                <span>Notice Board</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>courses/my.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'courses/my.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-book-open"></i>
                <span>My Courses</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>attendance/my.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'attendance/my.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i>
                <span>My Attendance</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>grades/my.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'grades/my.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-list"></i>
                <span>My Exams &amp; Results</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>fees/my.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'fees/my.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>My Fees</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>student-services/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'student-services/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-life-ring"></i>
                <span>Help Desk &amp; Requests</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>learning/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'learning/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-flask"></i>
                <span>Study Material &amp; Labs</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>student-life/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'student-life/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-award"></i>
                <span>My eProject &amp; Activities</span>
            </a>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (!isParent() && !isStudent()): ?>
        <div class="nav-item">
            <a href="<?php echo $base; ?>learning/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'learning/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-flask"></i><span>Study Material &amp; Labs</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>reports/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'reports/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-user-check"></i>
                <span><?php echo isStudent() ? 'My Report Card' : 'Attendance & Reports'; ?></span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (!isParent() && !isStudent()): ?>
        <div class="nav-item">
            <a href="<?php echo $base; ?>courses/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'courses/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-book-open"></i>
                <span>Courses</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="<?php echo $base; ?>tasks/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'tasks/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-check"></i>
                <span>Tasks & Exams</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (!isStudent()): ?>
        <div class="nav-item">
            <a href="<?php echo $base; ?>events/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'events/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>Events Calendar</span>
            </a>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (isSuperAdmin() || (isSchoolManagement() && currentSchoolId() !== null)): ?>
        <div class="nav-item">
            <a href="<?php echo $base; ?>admin/emergency_alert.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'emergency_alert.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-bullhorn text-warning"></i>
                <span><?php echo isSuperAdmin() ? 'Platform Alerts' : 'Emergency Off'; ?></span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (isSuperAdmin()): ?>
        <div class="nav-section mt-2">Platform</div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>super-admin/dashboard.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'super-admin/dashboard.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-chart-pie"></i>
                <span>Platform Overview</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>admin/schools.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'admin/schools.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-school"></i>
                <span>Schools</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>admin/subscriptions.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'admin/subscriptions.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Subscriptions</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (isAdmin()): ?>
        <div class="nav-section mt-2">Admin Tools</div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>admin/users.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'admin/users.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i>
                <span>User Access</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>admin/activity_log.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'admin/activity_log.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-history"></i>
                <span>Activity Log</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>admin/messages.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'admin/messages.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-inbox"></i>
                <span>Contact Messages</span>
            </a>
        </div>
        <?php endif; ?>
    </nav>

    <!-- Bottom Pinned Actions (Settings & Logout) -->
    <div class="sidebar-bottom">
        <?php if (isAdmin()): ?>
        <div class="nav-item mb-1">
            <a href="<?php echo $base; ?>admin/settings.php" class="nav-link py-2 <?php echo strpos($_SERVER['PHP_SELF'], 'admin/settings.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </div>
        <?php endif; ?>
        <div class="nav-item">
            <form action="<?php echo $base; ?>auth/logout.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">
                <button type="submit" class="nav-link py-2 text-danger border-0 bg-transparent w-100 text-start">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </div>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
