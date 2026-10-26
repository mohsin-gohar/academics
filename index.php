<?php
/**
 * EduSphere Academy — World-Class School Website & Academic Portal
 * Public homepage for students, parents, faculty, and prospective scholars.
 */
$page_title = 'EduSphere Academy — Premier Student Management & Academic Portal';
require_once 'includes/header.php';

// Fetch Active Emergency Alert
$activeAlert = getActiveEmergencyAlert($pdo);

// Fetch Upcoming Events & Functions
$events = getSchoolEventsList($pdo, 6, true);

// Fetch Next Gazetted Holidays
$holidayStmt = $pdo->query("SELECT * FROM school_events WHERE is_holiday = 1 AND event_date >= CURDATE() AND status != 'Cancelled' ORDER BY event_date ASC LIMIT 4");
$holidays = $holidayStmt->fetchAll();

// Fetch Active Courses Overview
$courseStmt = $pdo->query("SELECT c.*, COALESCE(u.full_name, 'Senior Faculty') as instructor_name, (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'Enrolled') as enrolled_students FROM courses c LEFT JOIN users u ON c.instructor_id = u.id WHERE c.status = 'Active' ORDER BY c.created_at DESC LIMIT 6");
$featuredCourses = $courseStmt->fetchAll();

// Live Institutional Statistics
$totalScholars = $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn() ?: 1250;
$totalFaculty = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('teacher', 'admin') AND is_active = 1")->fetchColumn() ?: 48;
$totalCoursesCount = $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'Active'")->fetchColumn() ?: 24;
?>

<!-- Public Top Utility Header -->
<div class="bg-dark text-white py-2 px-3 border-bottom border-secondary small">
    <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <span><i class="fas fa-phone-alt text-primary me-1"></i> +92 (21) 3456-7890</span>
            <span class="d-none d-md-inline"><i class="fas fa-envelope text-primary me-1"></i> info@edusphere.edu.pk</span>
            <span class="d-none d-lg-inline"><i class="fas fa-clock text-primary me-1"></i> Mon - Sat: 8:00 AM - 3:00 PM</span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="public_events.php" class="text-white-50 text-decoration-none hover-white">
                <i class="fas fa-calendar-alt text-warning me-1"></i> Academic Calendar
            </a>
            <span class="text-secondary">|</span>
            <?php if (isLoggedIn()): ?>
                <a href="dashboard.php" class="text-info text-decoration-none fw-semibold">
                    <i class="fas fa-th-large me-1"></i> My Dashboard (<?php echo e($_SESSION['user_name'] ?? 'User'); ?>)
                </a>
                <span class="text-secondary">|</span>
                <a href="auth/logout.php" class="text-danger text-decoration-none small">
                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
            <?php else: ?>
                <a href="auth/login.php" class="text-white-50 text-decoration-none hover-white">
                    <i class="fas fa-lock me-1"></i> Portal Sign In
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Public Main Sticky Navigation -->
<nav class="navbar navbar-expand-lg glass-nav sticky-top py-3">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-3" href="index.php">
            <div class="rounded-3 bg-primary text-white d-flex align-items-center justify-content-center shadow" style="width: 44px; height: 44px; font-size: 1.35rem;">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div>
                <span class="fw-bold fs-4 text-dark d-block lh-1" style="letter-spacing: -0.02em;">EduSphere</span>
                <span class="text-muted small fw-semibold text-uppercase tracking-wider" style="font-size: 0.65rem; letter-spacing: 1.2px;">Academy & Enterprise Portal</span>
            </div>
        </a>

        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="publicNav">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0 fw-semibold text-secondary">
                <li class="nav-item"><a class="nav-link active text-primary px-3" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link px-3" href="#events">School Functions</a></li>
                <li class="nav-item"><a class="nav-link px-3" href="#holidays">Holidays & Off Days</a></li>
                <li class="nav-item"><a class="nav-link px-3" href="#programs">Academic Programs</a></li>
                <li class="nav-item"><a class="nav-link px-3" href="#portal">ERP Features</a></li>
                <li class="nav-item"><a class="nav-link px-3" href="#contact">Contact</a></li>
            </ul>

            <div class="d-flex align-items-center gap-2">
                <?php if (isLoggedIn()): ?>
                    <a href="dashboard.php" class="btn btn-primary btn-sm px-4 py-2 rounded-pill shadow-sm fw-semibold">
                        <i class="fas fa-th-large me-1"></i> Go to Dashboard
                    </a>
                <?php else: ?>
                    <a href="auth/login.php" class="btn btn-outline-primary btn-sm px-3 py-2 rounded-pill fw-semibold">
                        <i class="fas fa-sign-in-alt me-1"></i> Sign In
                    </a>
                    <a href="auth/register.php" class="btn btn-primary btn-sm px-3 py-2 rounded-pill shadow-sm fw-semibold">
                        <i class="fas fa-user-plus me-1"></i> Register
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- ACTIVE EMERGENCY SCHOOL CLOSURE BROADCAST BANNER -->
<?php if ($activeAlert): ?>
<div class="bg-danger text-white py-3 shadow border-bottom border-danger position-relative">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-white text-danger px-3 py-2 fw-bold text-uppercase fs-6 shadow-sm">
                    <span class="pulse-dot text-danger me-1"></span> <?php echo e($activeAlert['alert_type']); ?>
                </span>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo e($activeAlert['title']); ?></h5>
                    <div class="small opacity-90 mt-1"><?php echo e($activeAlert['message']); ?></div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-black bg-opacity-25 text-white px-3 py-2 border border-white border-opacity-25 fw-semibold">
                    Effective: <?php echo formatDate($activeAlert['effective_date']); ?>
                    <?php if (!empty($activeAlert['end_date'])): ?>
                        — Reopening: <?php echo formatDate($activeAlert['end_date']); ?>
                    <?php endif; ?>
                </span>
                <button type="button" class="btn btn-outline-light btn-sm px-3" data-bs-toggle="modal" data-bs-target="#emergencyModal">
                    Notice Details
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Emergency Notice Modal -->
<div class="modal fade" id="emergencyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-3">
                <h5 class="modal-title fw-bold"><i class="fas fa-exclamation-triangle me-2"></i>Official Campus Closure Notice</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="badge bg-danger-subtle text-danger border border-danger mb-2 px-3 py-1"><?php echo e($activeAlert['alert_type']); ?></div>
                <h4 class="fw-bold text-dark mb-2"><?php echo e($activeAlert['title']); ?></h4>
                <div class="p-3 bg-light rounded-3 text-secondary border mb-3 small">
                    <div><strong>Effective Date:</strong> <?php echo formatDate($activeAlert['effective_date'], 'l, F j, Y'); ?></div>
                    <?php if (!empty($activeAlert['end_date'])): ?>
                        <div><strong>Expected Reopening Date:</strong> <?php echo formatDate($activeAlert['end_date'], 'l, F j, Y'); ?></div>
                    <?php endif; ?>
                </div>
                <div class="mb-3 text-secondary">
                    <?php echo nl2br(e($activeAlert['message'])); ?>
                </div>
                <div class="alert alert-info py-2 small mb-0">
                    <i class="fas fa-info-circle me-1"></i> All enrolled students must monitor their student portal for online class links and homework tasks.
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <a href="auth/login.php" class="btn btn-primary btn-sm">Student Portal Login</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- HERO SECTION -->
<section class="py-5 bg-mesh-gradient position-relative">
    <div class="container py-lg-5">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <div class="d-inline-flex align-items-center gap-2 bg-white px-3 py-2 rounded-pill shadow-sm mb-4 border">
                    <span class="badge bg-primary rounded-pill px-3 py-1">Aptech Academic Honors</span>
                    <span class="small fw-semibold text-secondary">Next-Generation School Management & SaaS ERP</span>
                </div>
                <h1 class="saas-hero-title mb-3">
                    Empowering Next-Gen Leaders with Rigorous Academics & Modern ERP
                </h1>
                <p class="lead text-secondary mb-4" style="font-size: 1.15rem; line-height: 1.7;">
                    Welcome to <strong>EduSphere Academy</strong> — an enterprise-grade academic ecosystem combining world-class curriculum, real-time parent transparency, interactive task manager, and instant emergency broadcast controls.
                </p>
                <div class="d-flex flex-wrap gap-3 mb-5">
                    <a href="auth/login.php" class="btn btn-primary btn-lg px-4 py-3 rounded-pill shadow fw-semibold">
                        <i class="fas fa-user-lock me-2"></i> Access Student & Parent Portal
                    </a>
                    <a href="#events" class="btn btn-outline-dark btn-lg px-4 py-3 rounded-pill fw-semibold">
                        <i class="fas fa-calendar-alt me-2"></i> View School Events
                    </a>
                </div>
                <div class="row g-4 pt-4 border-top">
                    <div class="col-4">
                        <div class="h2 fw-bold text-dark mb-0"><?php echo (int)$totalScholars; ?>+</div>
                        <small class="text-muted fw-semibold">Active Scholars</small>
                    </div>
                    <div class="col-4">
                        <div class="h2 fw-bold text-primary mb-0"><?php echo (int)$totalFaculty; ?>+</div>
                        <small class="text-muted fw-semibold">Certified Faculty</small>
                    </div>
                    <div class="col-4">
                        <div class="h2 fw-bold text-success mb-0">98.5%</div>
                        <small class="text-muted fw-semibold">Graduation Rate</small>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <!-- Live Noticeboard & Academic Preview Card -->
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden bg-white stat-glow">
                    <div class="card-header bg-dark text-white py-3 px-4 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <span class="pulse-dot text-danger"></span>
                            <span class="fw-bold">Campus Live Noticeboard</span>
                        </div>
                        <span class="badge bg-primary"><?php echo date('F Y'); ?></span>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($activeAlert): ?>
                            <div class="p-3 mb-3 rounded-3 bg-danger bg-opacity-10 border border-danger">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="badge bg-danger">CAMPUS ALERT</span>
                                    <small class="text-danger fw-bold"><?php echo formatDate($activeAlert['effective_date']); ?></small>
                                </div>
                                <h6 class="fw-bold text-danger mb-1"><?php echo e($activeAlert['title']); ?></h6>
                                <p class="small text-muted mb-0"><?php echo mb_strimwidth(e($activeAlert['message']), 0, 95, '...'); ?></p>
                            </div>
                        <?php endif; ?>

                        <h6 class="fw-bold text-dark mb-3"><i class="fas fa-calendar-check text-primary me-2"></i>Upcoming School Highlights</h6>
                        <div class="d-flex flex-column gap-2 mb-4">
                            <?php foreach (array_slice($events, 0, 3) as $ev): ?>
                            <div class="p-3 rounded-3 bg-light border d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold text-dark small"><?php echo e($ev['title']); ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;"><i class="fas fa-clock me-1 text-primary"></i><?php echo formatDate($ev['event_date']); ?></div>
                                </div>
                                <span class="badge bg-<?php echo $ev['is_holiday'] ? 'danger' : 'primary'; ?>-subtle text-<?php echo $ev['is_holiday'] ? 'danger' : 'primary'; ?> border"><?php echo e($ev['event_type']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <a href="public_events.php" class="btn btn-outline-primary w-100 py-2 rounded-pill fw-semibold">
                            Open Full Academic Calendar <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- SCHOOL FUNCTIONS & CAMPUS LIFE SHOWCASE -->
<section id="events" class="py-5 bg-white">
    <div class="container py-lg-4">
        <div class="d-flex justify-content-between align-items-end mb-5 flex-wrap gap-2">
            <div>
                <span class="saas-badge-pill bg-primary-subtle text-primary border border-primary mb-2">CAMPUS HAPPENINGS</span>
                <h2 class="display-6 fw-bold text-dark mb-1">School Functions & Co-Curricular Life</h2>
                <p class="text-muted mb-0">Upcoming athletic competitions, robotics fairs, exhibitions, and parent-teacher conferences.</p>
            </div>
            <a href="public_events.php" class="btn btn-outline-primary rounded-pill px-4">
                View Calendar <i class="fas fa-calendar-alt ms-1"></i>
            </a>
        </div>

        <?php if (empty($events)): ?>
            <div class="p-5 text-center text-muted bg-light rounded-4">
                <i class="fas fa-calendar-times fa-3x mb-2 text-secondary opacity-50"></i>
                <h5>No upcoming events currently listed.</h5>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($events as $ev): 
                    $today = date('Y-m-d');
                    $diff = (int)round((strtotime($ev['event_date']) - strtotime($today)) / 86400);
                    $tag = ($diff === 0) ? '<span class="badge bg-danger">TODAY</span>' : (($diff === 1) ? '<span class="badge bg-warning text-dark">TOMORROW</span>' : '<span class="badge bg-light text-dark border">In ' . $diff . ' days</span>');
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="saas-card h-100 p-4 d-flex flex-column border-top border-4 <?php echo $ev['is_holiday'] ? 'border-danger' : 'border-primary'; ?>">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="badge bg-<?php echo $ev['is_holiday'] ? 'danger' : 'primary'; ?> px-3 py-2"><?php echo e($ev['event_type']); ?></span>
                            <?php echo $tag; ?>
                        </div>
                        <h5 class="fw-bold text-dark mb-2"><?php echo e($ev['title']); ?></h5>
                        <div class="text-muted small mb-3">
                            <i class="fas fa-calendar-alt text-primary me-1"></i>
                            <strong><?php echo formatDate($ev['event_date'], 'l, F j, Y'); ?></strong>
                            <?php if (!empty($ev['end_date'])): ?>
                                <br><i class="fas fa-flag-checkered text-muted me-1"></i> Through: <?php echo formatDate($ev['end_date'], 'F j, Y'); ?>
                            <?php endif; ?>
                        </div>
                        <p class="text-secondary small mb-3 flex-grow-1">
                            <?php echo nl2br(e($ev['description'])); ?>
                        </p>
                        <?php if ($ev['is_holiday']): ?>
                            <div class="p-2 rounded bg-danger bg-opacity-10 text-danger small fw-semibold mt-auto">
                                <i class="fas fa-sun me-1"></i> School Off (Campus Closed)
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- SCHEDULED HOLIDAYS & DAYS OFF -->
<section id="holidays" class="py-5 bg-light">
    <div class="container py-lg-4">
        <div class="row align-items-center mb-4">
            <div class="col-md-8">
                <span class="saas-badge-pill bg-danger-subtle text-danger border border-danger mb-2">OFFICIAL CALENDAR</span>
                <h2 class="display-6 fw-bold text-dark mb-1">Gazetted Holidays & Scheduled Days Off</h2>
                <p class="text-muted mb-0">Official gazetted holidays, national observances, and seasonal vacations for students & parents.</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="public_events.php?type=Holiday" class="btn btn-outline-danger rounded-pill px-4">
                    <i class="fas fa-sun me-1"></i> View All Holidays
                </a>
            </div>
        </div>

        <div class="row g-3">
            <?php if (empty($holidays)): ?>
                <div class="col-12 text-center p-4 text-muted bg-white rounded-3 shadow-sm">
                    No scheduled upcoming holidays in the immediate schedule.
                </div>
            <?php else: ?>
                <?php foreach ($holidays as $h): 
                    $hDiff = (int)round((strtotime($h['event_date']) - strtotime(date('Y-m-d'))) / 86400);
                ?>
                <div class="col-md-6 col-lg-3">
                    <div class="saas-card h-100 p-3 bg-white">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-3 bg-danger text-white d-flex flex-column align-items-center justify-content-center p-2" style="width: 54px; height: 54px;">
                                <span class="fw-bold fs-6 lh-1"><?php echo date('d', strtotime($h['event_date'])); ?></span>
                                <span class="small text-uppercase" style="font-size: 0.65rem;"><?php echo date('M', strtotime($h['event_date'])); ?></span>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-1"><?php echo e($h['title']); ?></h6>
                                <span class="badge bg-light text-danger border">
                                    <?php echo $hDiff === 0 ? 'Today' : ($hDiff === 1 ? 'Tomorrow' : 'In ' . $hDiff . ' days'); ?>
                                </span>
                            </div>
                        </div>
                        <?php if (!empty($h['description'])): ?>
                            <p class="text-muted small mt-3 mb-0"><?php echo mb_strimwidth(e($h['description']), 0, 75, '...'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ACADEMIC PROGRAMS & CURRICULUM -->
<section id="programs" class="py-5 bg-white">
    <div class="container py-lg-4">
        <div class="text-center mb-5">
            <span class="saas-badge-pill bg-primary-subtle text-primary border border-primary mb-2">CURRICULUM DISCIPLINES</span>
            <h2 class="display-6 fw-bold text-dark mb-2">Academics Built for the Future</h2>
            <p class="text-muted mx-auto" style="max-width: 650px;">Rigorous STEM, software engineering, and business disciplines backed by practical projects, verified transcripts, and task management.</p>
        </div>

        <div class="row g-4">
            <?php foreach ($featuredCourses as $fc): ?>
            <div class="col-md-6 col-lg-4">
                <div class="saas-card h-100 p-4 d-flex flex-column bg-light rounded-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <code class="fs-6"><?php echo e($fc['course_code']); ?></code>
                        <span class="badge bg-success-subtle text-success border border-success px-3 py-1"><?php echo (int)($fc['credits'] ?? 3); ?> Credits</span>
                    </div>
                    <h5 class="fw-bold text-dark mb-2"><?php echo e($fc['course_name']); ?></h5>
                    <p class="text-muted small mb-4 flex-grow-1"><?php echo e($fc['description'] ?: 'Rigorous academic curriculum focusing on fundamental subject mastery and real-world project delivery.'); ?></p>
                    <div class="pt-3 border-top d-flex justify-content-between align-items-center small text-secondary">
                        <span><i class="fas fa-chalkboard-teacher me-1 text-primary"></i><?php echo e($fc['instructor_name']); ?></span>
                        <span><i class="fas fa-users me-1 text-success"></i><?php echo $fc['enrolled_students']; ?> Enrolled</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ENTERPRISE ERP ARCHITECTURE & DEMO SANDBOX -->
<section id="portal" class="py-5 bg-dark text-white">
    <div class="container py-lg-5">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="badge bg-primary px-3 py-2 rounded-pill fw-semibold mb-3">DIGITAL CAMPUS ERP 2.0</span>
                <h2 class="display-6 fw-bold mb-3">Enterprise SaaS Student Management System</h2>
                <p class="text-white-50 mb-4" style="line-height: 1.7;">
                    EduSphere provides a single source of truth for faculty, students, and administration. Built with pure PHP 8.2+, MySQL 8+, and PDO prepared statements with zero framework bloat.
                </p>
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex gap-3 align-items-start">
                        <div class="feature-icon-wrapper bg-primary text-white">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Mandatory Student Task Manager</h6>
                            <p class="small text-white-50 mb-0">Live AJAX search, debounced status changes, overdue highlights, priority filters.</p>
                        </div>
                    </div>
                    <div class="d-flex gap-3 align-items-start">
                        <div class="feature-icon-wrapper bg-danger text-white">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Administrative Emergency Closure Controls</h6>
                            <p class="small text-white-50 mb-0">Staff can toggle instant campus closure notices across portals with one click.</p>
                        </div>
                    </div>
                    <div class="d-flex gap-3 align-items-start">
                        <div class="feature-icon-wrapper bg-success text-white">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Official Student Academic Transcripts</h6>
                            <p class="small text-white-50 mb-0">Printable official report cards, course grades, credits, and completion standing.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <!-- Sandbox Credentials Box -->
                <div class="saas-card p-4 p-lg-5 bg-white text-dark rounded-4 shadow-lg">
                    <h5 class="fw-bold text-dark mb-1"><i class="fas fa-shield-alt text-primary me-2"></i>Portal Sign-In Demo Sandbox</h5>
                    <p class="small text-muted mb-4">Click to login directly with pre-configured role credentials:</p>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered table-sm small align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>Role</th><th>Email Account</th><th>Password</th></tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="badge bg-danger">Administrator</span></td>
                                    <td><code>admin@edusphere.com</code></td>
                                    <td><code>Admin@123</code></td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-warning text-dark">Teacher</span></td>
                                    <td><code>teacher@edusphere.com</code></td>
                                    <td><code>Teacher@123</code></td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-primary">Student</span></td>
                                    <td><code>student@edusphere.com</code></td>
                                    <td><code>Student@123</code></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <a href="auth/login.php" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow">
                        Sign In to EduSphere Portal <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer id="contact" class="bg-black text-white-50 py-5">
    <div class="container py-4">
        <div class="row g-4 mb-5">
            <div class="col-lg-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="fas fa-graduation-cap text-primary fs-3"></i>
                    <h5 class="fw-bold text-white mb-0">EduSphere Academy</h5>
                </div>
                <p class="small mb-4" style="line-height: 1.7;">
                    A premier educational institution delivering excellence in STEM, Computer Science, and Leadership. Committed to academic integrity, digital innovation, and transparent student success.
                </p>
                <div class="small">
                    <div><i class="fas fa-map-marker-alt text-primary me-2"></i> Plot 45-C, Academic Boulevard, Education District, Karachi</div>
                    <div class="mt-2"><i class="fas fa-phone-alt text-primary me-2"></i> +92 (21) 3456-7890</div>
                    <div class="mt-2"><i class="fas fa-envelope text-primary me-2"></i> admissions@edusphere.edu.pk</div>
                </div>
            </div>

            <div class="col-6 col-lg-2">
                <h6 class="fw-bold text-white mb-3">Quick Navigation</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                    <li><a href="index.php" class="text-white-50 text-decoration-none">Home</a></li>
                    <li><a href="public_events.php" class="text-white-50 text-decoration-none">Events & Functions</a></li>
                    <li><a href="public_events.php?type=Holiday" class="text-white-50 text-decoration-none">School Off Days</a></li>
                    <li><a href="auth/login.php" class="text-white-50 text-decoration-none">Portal Login</a></li>
                    <li><a href="auth/register.php" class="text-white-50 text-decoration-none">New Admission</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-3">
                <h6 class="fw-bold text-white mb-3">Student & Parent Desk</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                    <li><a href="auth/login.php" class="text-white-50 text-decoration-none">Task Submissions</a></li>
                    <li><a href="auth/login.php" class="text-white-50 text-decoration-none">Course Enrollments</a></li>
                    <li><a href="auth/login.php" class="text-white-50 text-decoration-none">Academic Transcripts</a></li>
                    <li><a href="public_events.php?type=Exam" class="text-white-50 text-decoration-none">Examination Date Sheet</a></li>
                </ul>
            </div>

            <div class="col-lg-3">
                <h6 class="fw-bold text-white mb-3">Accreditation & Standards</h6>
                <p class="small mb-3" style="line-height: 1.6;">
                    EduSphere Academy operates in compliance with Aptech academic standards, featuring full relational schema, CSRF-hardened PDO security, and session defense.
                </p>
                <span class="badge bg-secondary">PHP 8.2+ | MySQL 8+ | Bootstrap 5</span>
            </div>
        </div>

        <div class="pt-4 border-top border-secondary text-center small">
            &copy; <?php echo date('Y'); ?> EduSphere — Student Management System & Academy Portal. All rights reserved.
        </div>
    </div>
</footer>

<?php require_once 'includes/footer.php'; ?>
