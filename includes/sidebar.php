<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class="fas fa-graduation-cap"></i>
        </div>
        <span class="brand-text"><?php echo APP_NAME; ?></span>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Main</div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
        </div>

        <div class="nav-section">Academic</div>
        <?php if (isStaff()): ?>
        <div class="nav-item">
            <a href="<?php echo $base; ?>students/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'students/index.php') !== false ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate"></i>
                <span>Students</span>
            </a>
        </div>
        <?php else: ?>
        <?php
        $myStuId = $_SESSION['student_id'] ?? null;
        if (!$myStuId && isset($_SESSION['user_id']) && isset($pdo)) {
            $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $myStuId = $stmt->fetchColumn();
            $_SESSION['student_id'] = $myStuId;
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
            <a href="<?php echo $base; ?>courses/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'courses/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-book"></i>
                <span>Courses</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>tasks/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'tasks/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-tasks"></i>
                <span>Tasks</span>
            </a>
        </div>

        <?php if (isStaff()): ?>
        <div class="nav-section">Reports</div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>reports/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'reports/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (isAdmin()): ?>
        <div class="nav-section">Administration</div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>admin/users.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'admin/') !== false ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i>
                <span>User Management</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>admin/settings.php" class="nav-link">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="<?php echo $base; ?>admin/activity_log.php" class="nav-link">
                <i class="fas fa-history"></i>
                <span>Activity Log</span>
            </a>
        </div>
        <?php endif; ?>
    </nav>

    <div class="sidebar-user">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
            </div>
            <div>
                <div class="user-name"><?php echo e($_SESSION['user_name'] ?? 'User'); ?></div>
                <div class="user-role"><?php echo e($_SESSION['user_role'] ?? 'student'); ?></div>
            </div>
        </div>
    </div>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

