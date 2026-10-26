<!-- Sidebar (Matching Reference Image 1) -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <a href="<?php echo $base; ?>dashboard.php" class="text-decoration-none d-flex align-items-center gap-2">
            <div class="brand-logo-icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <span class="brand-title">EduSphere</span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-item">
            <a href="<?php echo $base; ?>dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
        </div>

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

        <div class="nav-item">
            <a href="<?php echo $base; ?>reports/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'reports/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-user-check"></i>
                <span><?php echo isStudent() ? 'My Report Card' : 'Attendance & Reports'; ?></span>
            </a>
        </div>

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
