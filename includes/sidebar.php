<!-- Sidebar (Matching Reference Image 1) -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <a href="<?php echo $base; ?>dashboard.php" class="text-decoration-none d-flex align-items-center gap-2">
            <img class="brand-logo-img" src="<?php echo $base; ?>assets/img/academics-mark.svg" alt="" width="40" height="40">
            <span class="brand-copy">
                <span class="brand-title">Academics</span>
                <span class="brand-tagline">School Platform</span>
            </span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-item">
            <a href="<?php echo $base; ?><?php echo isParent() ? 'parent/dashboard.php' : 'dashboard.php'; ?>" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
        </div>

        <?php if (isParent()): ?>
        <div class="nav-section mt-2">Parent Portal</div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>parent/children.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'parent/children.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate"></i>
                <span>My Children</span>
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

        <?php if (isStaff()): ?>
        <div class="nav-item">
            <a href="<?php echo $base; ?>students/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'students/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate"></i>
                <span>Students</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>admin/users.php?role=teacher" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'role=teacher') !== false ? 'active' : ''; ?>">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Teachers</span>
            </a>
        </div>
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
        <?php endif; ?>
        <?php endif; ?>

        <?php if (!isParent()): ?>
        <div class="nav-item">
            <a href="<?php echo $base; ?>reports/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'reports/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-user-check"></i>
                <span><?php echo isStudent() ? 'My Report Card' : 'Attendance & Reports'; ?></span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (!isParent()): ?>
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

        <div class="nav-item">
            <a href="<?php echo $base; ?>events/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'events/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>Events Calendar</span>
            </a>
        </div>

        <?php if (isStaff()): ?>
        <div class="nav-item">
            <a href="<?php echo $base; ?>admin/emergency_alert.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'emergency_alert.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-bullhorn text-warning"></i>
                <span>Emergency Off</span>
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
            <a href="<?php echo $base; ?>auth/logout.php" class="nav-link py-2 text-danger">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
