<?php
/**
 * EduSphere Academy — Public School Website & Portal
 * Public homepage for students, parents, faculty, and visitors.
 */
$page_title = 'EduSphere Academy — Premier Student Management & Academic Portal';
require_once 'includes/header.php';

// Fetch Active Emergency Alert
$activeAlert = getActiveEmergencyAlert($pdo);

// Fetch Upcoming Events & Functions (Limit 6)
$events = getSchoolEventsList($pdo, 6, true);

// Fetch Next Gazetted Holidays (Upcoming)
$holidayStmt = $pdo->query("SELECT * FROM school_events WHERE is_holiday = 1 AND event_date >= CURDATE() AND status != 'Cancelled' ORDER BY event_date ASC LIMIT 4");
$holidays = $holidayStmt->fetchAll();

// Fetch Active Courses Overview
$courseStmt = $pdo->query("SELECT c.*, COALESCE(u.full_name, 'Senior Faculty') as instructor_name, (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'Enrolled') as enrolled_students FROM courses c LEFT JOIN users u ON c.instructor_id = u.id WHERE c.status = 'Active' ORDER BY c.created_at DESC LIMIT 6");
$featuredCourses = $courseStmt->fetchAll();

// Overall Stats for Hero
$totalScholars = $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn() ?: 1250;
$totalFaculty = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('teacher', 'admin') AND is_active = 1")->fetchColumn() ?: 45;
$totalCoursesCount = $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'Active'")->fetchColumn() ?: 18;
?>

<!-- Public Top Information Bar -->
<div class="bg-dark text-white py-1 px-3 border-bottom border-secondary small">
    <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <span><i class="fas fa-phone-alt text-primary me-1"></i> +92 (21) 3456-7890</span>
            <span class="d-none d-md-inline"><i class="fas fa-envelope text-primary me-1"></i> info@edusphere.edu.pk</span>
            <span class="d-none d-lg-inline"><i class="fas fa-clock text-primary me-1"></i> Mon - Sat: 8:00 AM - 3:00 PM</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="public_events.php" class="text-white-50 text-decoration-none hover-white"><i class="fas fa-calendar-alt text-warning me-1"></i> Academic Calendar</a>
            <span class="text-secondary">|</span>
            <?php if (isLoggedIn()): ?>
                <a href="dashboard.php" class="text-info text-decoration-none fw-semibold"><i class="fas fa-th-large me-1"></i> My Dashboard (<?php echo e($_SESSION['user_name'] ?? 'User'); ?>)</a>
            <?php else: ?>
                <a href="auth/login.php" class="text-white-50 text-decoration-none hover-white"><i class="fas fa-lock me-1"></i> Portal Sign In</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Public Navigation Header -->
<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm py-3">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <div class="rounded-3 bg-primary text-white d-flex align-items-center justify-content-center shadow-sm" style="width: 42px; height: 42px; font-size: 1.25rem;">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div>
                <span class="fw-bold fs-4 text-dark d-block lh-1">EduSphere</span>
                <span class="text-muted small fw-semibold text-uppercase tracking-wider" style="font-size: 0.65rem; letter-spacing: 1px;">Academy & Student Portal</span>
            </div>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="publicNav">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0 fw-medium">
                <li class="nav-item"><a class="nav-link active text-primary" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="#events">School Functions</a></li>
                <li class="nav-item"><a class="nav-link" href="#holidays">Holidays & Off Days</a></li>
                <li class="nav-item"><a class="nav-link" href="#programs">Academic Programs</a></li>
                <li class="nav-item"><a class="nav-link" href="#portal">ERP Features</a></li>
                <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
            </ul>

            <div class="d-flex align-items-center gap-2">
                <?php if (isLoggedIn()): ?>
                    <a href="dashboard.php" class="btn btn-primary btn-sm px-3 shadow-sm">
                        <i class="fas fa-th-large me-1"></i> Go to Dashboard
                    </a>
                <?php else: ?>
                    <a href="auth/login.php" class="btn btn-outline-primary btn-sm px-3">
                        <i class="fas fa-sign-in-alt me-1"></i> Sign In
                    </a>
                    <a href="auth/register.php" class="btn btn-primary btn-sm px-3 shadow-sm">
                        <i class="fas fa-user-plus me-1"></i> Register
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- ACTIVE EMERGENCY CLOSURE NOTIFICATION BANNER (IF ACTIVE) -->
<?php if ($activeAlert): ?>
<div class="bg-danger text-white py-3 shadow-sm border-bottom border-danger position-relative" style="z-index: 1000;">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-warning text-dark px-3 py-2 fw-bold text-uppercase fs-6">
                    <i class="fas fa-bullhorn fa-shake me-1"></i> <?php echo e($activeAlert['alert_type']); ?>
                </span>
                <div>
                    <h5 class="mb-0 fw-bold"><?php echo e($activeAlert['title']); ?></h5>
                    <div class="small opacity-90 mt-1"><?php echo e($activeAlert['message']); ?></div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-white text-danger px-3 py-2 fw-bold">
                    Effective: <?php echo formatDate($activeAlert['effective_date']); ?>
                    <?php if (!empty($activeAlert['end_date'])): ?>
                        — Reopening: <?php echo formatDate($activeAlert['end_date']); ?>
                    <?php endif; ?>
                </span>
                <a href="#emergencyDetails" class="btn btn-outline-light btn-sm" data-bs-toggle="modal">Details</a>
            </div>
        </div>
    </div>
</div>

<!-- Emergency Details Modal -->
<div class="modal fade" id="emergencyDetails" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-exclamation-triangle me-2"></i>Official Emergency School Notice</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="badge bg-danger mb-2"><?php echo e($activeAlert['alert_type']); ?></div>
                <h4 class="fw-bold text-dark mb-2"><?php echo e($activeAlert['title']); ?></h4>
                <p class="text-muted small mb-3">
                    <strong>Effective Date:</strong> <?php echo formatDate($activeAlert['effective_date'], 'l, F j, Y'); ?><br>
                    <?php if (!empty($activeAlert['end_date'])): ?>
                        <strong>Expected Campus Reopening:</strong> <?php echo formatDate($activeAlert['end_date'], 'l, F j, Y'); ?>
                    <?php endif; ?>
                </p>
                <div class="p-3 bg-light rounded-3 text-secondary border mb-3">
                    <?php echo nl2br(e($activeAlert['message'])); ?>
                </div>
                <div class="alert alert-info py-2 small mb-0">
                    <i class="fas fa-info-circle me-1"></i> Students are requested to check their personal tasks portal for assigned online assignments.
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close Notice</button>
                <a href="auth/login.php" class="btn btn-primary btn-sm">Log in to Student Portal</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Hero Section -->
<section class="py-5 bg-light position-relative overflow-hidden" style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);">
    <div class="container py-4">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <div class="d-inline-flex align-items-center gap-2 bg-white px-3 py-1 rounded-pill shadow-sm mb-3 border">
                    <span class="badge bg-primary rounded-pill">Aptech Certified</span>
                    <span class="small fw-semibold text-secondary">Next-Generation School Management & SaaS ERP</span>
                </div>
                <h1 class="display-4 fw-bold text-dark mb-3 lh-sm">
                    Empowering Scholars with Academic Excellence & Modern Digital Tools
                </h1>
                <p class="lead text-secondary mb-4">
                    Welcome to <strong>EduSphere Academy</strong>. Experience a modern, transparent learning ecosystem where students, parents, and faculty stay seamlessly connected with real-time academic updates, school functions, and instant emergency alerts.
                </p>
                <div class="d-flex flex-wrap gap-3 mb-4">
                    <a href="auth/login.php" class="btn btn-primary btn-lg px-4 shadow">
                        <i class="fas fa-user-lock me-2"></i> Student / Parent Portal
                    </a>
                    <a href="#events" class="btn btn-outline-dark btn-lg px-4">
                        <i class="fas fa-calendar-alt me-2"></i> View School Events
                    </a>
                </div>
                <div class="row g-3 pt-3 border-top">
                    <div class="col-4">
                        <div class="h3 fw-bold text-primary mb-0"><?php echo (int)$totalScholars; ?>+</div>
                        <small class="text-muted fw-semibold">Active Scholars</small>
                    </div>
                    <div class="col-4">
                        <div class="h3 fw-bold text-success mb-0"><?php echo (int)$totalFaculty; ?>+</div>
                        <small class="text-muted fw-semibold">Faculty & Staff</small>
                    </div>
                    <div class="col-4">
                        <div class="h3 fw-bold text-warning mb-0">98.5%</div>
                        <small class="text-muted fw-semibold">Graduation Rate</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <!-- Academic Card Showcase -->
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden bg-white">
                    <div class="card-header bg-primary text-white py-3 px-4 d-flex justify-content-between align-items-center">
                        <span class="fw-bold"><i class="fas fa-bell me-2"></i>Campus Live Noticeboard</span>
                        <span class="badge bg-light text-primary"><?php echo date('F Y'); ?></span>
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
                        <div class="list-group list-group-flush mb-3">
                            <?php foreach (array_slice($events, 0, 3) as $ev): ?>
                            <div class="list-group-item px-0 py-2 border-bottom-0 d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold text-dark small"><?php echo e($ev['title']); ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;"><i class="fas fa-clock me-1"></i><?php echo formatDate($ev['event_date']); ?></div>
                                </div>
                                <span class="badge bg-<?php echo $ev['is_holiday'] ? 'danger' : 'primary'; ?>-subtle text-<?php echo $ev['is_holiday'] ? 'danger' : 'primary'; ?> border"><?php echo e($ev['event_type']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <a href="public_events.php" class="btn btn-outline-primary w-100 btn-sm">
                            View Full Academic Calendar <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- SCHOOL FUNCTIONS & EVENTS SHOWCASE -->
<section id="events" class="py-5 bg-white">
    <div class="container py-3">
        <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
            <div>
                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-semibold mb-2">CAMPUS LIFE & HAPPENINGS</span>
                <h2 class="fw-bold text-dark mb-1">What's Happening at EduSphere Academy</h2>
                <p class="text-muted small mb-0">Upcoming functions, sports galas, academic fairs, and co-curricular milestones.</p>
            </div>
            <a href="public_events.php" class="btn btn-outline-primary btn-sm">
                View All Events Calendar <i class="fas fa-calendar-alt ms-1"></i>
            </a>
        </div>

        <?php if (empty($events)): ?>
            <div class="p-5 text-center text-muted bg-light rounded-4">
                <i class="fas fa-calendar-times fa-3x mb-2 text-secondary opacity-50"></i>
                <h5>No upcoming events scheduled right now.</h5>
                <p class="small">Check back soon for latest announcements.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($events as $ev): 
                    $today = date('Y-m-d');
                    $diff = (int)round((strtotime($ev['event_date']) - strtotime($today)) / 86400);
                    $tag = ($diff === 0) ? '<span class="badge bg-danger">TODAY</span>' : (($diff === 1) ? '<span class="badge bg-warning text-dark">TOMORROW</span>' : '<span class="badge bg-light text-dark border">In ' . $diff . ' days</span>');
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm border-0 border-top border-4 <?php echo $ev['is_holiday'] ? 'border-danger' : 'border-primary'; ?> rounded-3">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-<?php echo $ev['is_holiday'] ? 'danger' : 'primary'; ?>"><?php echo e($ev['event_type']); ?></span>
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
                                    <i class="fas fa-sun me-1"></i> School Off Day (Campus Closed)
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- SCHEDULED HOLIDAYS & DAYS OFF -->
<section id="holidays" class="py-5 bg-light">
    <div class="container py-3">
        <div class="row align-items-center mb-4">
            <div class="col-md-8">
                <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill fw-semibold mb-2">SCHEDULED DAYS OFF</span>
                <h2 class="fw-bold text-dark mb-1">Official School Holidays & Breaks</h2>
                <p class="text-muted small mb-0">Scheduled gazetted holidays, national observances, and seasonal vacations for students & parents.</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="public_events.php?type=Holiday" class="btn btn-outline-danger btn-sm">
                    <i class="fas fa-sun me-1"></i> All Holidays List
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
                    <div class="card h-100 shadow-sm border-0 bg-white p-3">
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

<!-- ACADEMIC PROGRAMS & COURSES -->
<section id="programs" class="py-5 bg-white">
    <div class="container py-3">
        <div class="text-center mb-5">
            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-semibold mb-2">CURRICULUM & DISCIPLINES</span>
            <h2 class="fw-bold text-dark mb-2">World-Class Academic Programs</h2>
            <p class="text-muted mx-auto" style="max-width: 600px;">Comprehensive curriculum designed to bridge foundational academics with digital skills and practical project implementation.</p>
        </div>

        <div class="row g-4">
            <?php foreach ($featuredCourses as $fc): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm border-0 bg-light p-4 rounded-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <code><?php echo e($fc['course_code']); ?></code>
                        <span class="badge bg-success"><?php echo (int)($fc['credits'] ?? 3); ?> Credits</span>
                    </div>
                    <h5 class="fw-bold text-dark mb-2"><?php echo e($fc['course_name']); ?></h5>
                    <p class="text-muted small mb-3 flex-grow-1"><?php echo e($fc['description'] ?: 'In-depth academic module covering comprehensive subject foundations and practical skill execution.'); ?></p>
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

<!-- SAAS ERP & CRM ADVANTAGE -->
<section id="portal" class="py-5 bg-dark text-white">
    <div class="container py-4">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="badge bg-primary px-3 py-2 rounded-pill fw-semibold mb-3">DIGITAL CAMPUS ECOSYSTEM</span>
                <h2 class="display-6 fw-bold mb-3">Complete Student ERP & Task Management Experience</h2>
                <p class="text-white-50 mb-4">
                    EduSphere is not just an informational website — it is a full-featured Education SaaS & Academic CRM platform.
                </p>
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex gap-3 align-items-start">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center p-2" style="width: 36px; height: 36px;">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Mandatory Student Task Manager</h6>
                            <p class="small text-white-50 mb-0">Asynchronous task search, live status updates, overdue tracking, and priority filters.</p>
                        </div>
                    </div>
                    <div class="d-flex gap-3 align-items-start">
                        <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center p-2" style="width: 36px; height: 36px;">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Administrative Emergency Closure Controls</h6>
                            <p class="small text-white-50 mb-0">Authorized school staff can toggle instant weather / emergency closure notices across portals with one click.</p>
                        </div>
                    </div>
                    <div class="d-flex gap-3 align-items-start">
                        <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center p-2" style="width: 36px; height: 36px;">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Verified Academic Progress Transcripts</h6>
                            <p class="small text-white-50 mb-0">Students can access and print certified academic report cards, course grades, and performance metrics.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 text-center">
                <div class="p-4 bg-white text-dark rounded-4 shadow-lg text-start">
                    <h5 class="fw-bold text-dark mb-3"><i class="fas fa-key text-primary me-2"></i>Quick Portal Login Demo Access</h5>
                    <p class="small text-muted mb-3">Evaluate all user roles with the pre-configured credentials below:</p>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm small mb-4">
                            <thead class="table-light">
                                <tr><th>Role</th><th>Email</th><th>Password</th></tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="badge bg-danger">Admin</span></td>
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

                    <a href="auth/login.php" class="btn btn-primary w-100 py-2 fw-semibold">
                        Sign In to EduSphere Portal <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CONTACT & FOOTER SECTION -->
<footer id="contact" class="bg-black text-white-50 py-5">
    <div class="container py-3">
        <div class="row g-4 mb-4">
            <div class="col-lg-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="fas fa-graduation-cap text-primary fs-3"></i>
                    <h5 class="fw-bold text-white mb-0">EduSphere Academy</h5>
                </div>
                <p class="small mb-3">
                    A premier educational institution delivering excellence in STEM, Computer Science, and Leadership. Committed to academic integrity and digital innovation.
                </p>
                <div class="small">
                    <div><i class="fas fa-map-marker-alt text-primary me-2"></i> Plot 45-C, Academic Boulevard, Education District, Karachi</div>
                    <div class="mt-1"><i class="fas fa-phone-alt text-primary me-2"></i> +92 (21) 3456-7890</div>
                    <div class="mt-1"><i class="fas fa-envelope text-primary me-2"></i> admissions@edusphere.edu.pk</div>
                </div>
            </div>

            <div class="col-6 col-lg-2">
                <h6 class="fw-bold text-white mb-3">Quick Links</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                    <li><a href="index.php" class="text-white-50 text-decoration-none">Home</a></li>
                    <li><a href="public_events.php" class="text-white-50 text-decoration-none">Events & Holidays</a></li>
                    <li><a href="public_events.php?type=Holiday" class="text-white-50 text-decoration-none">School Off Days</a></li>
                    <li><a href="auth/login.php" class="text-white-50 text-decoration-none">Portal Login</a></li>
                    <li><a href="auth/register.php" class="text-white-50 text-decoration-none">New Registration</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-3">
                <h6 class="fw-bold text-white mb-3">Student & Parent Desk</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                    <li><a href="auth/login.php" class="text-white-50 text-decoration-none">Task Submissions</a></li>
                    <li><a href="auth/login.php" class="text-white-50 text-decoration-none">Course Enrollments</a></li>
                    <li><a href="auth/login.php" class="text-white-50 text-decoration-none">Academic Transcripts</a></li>
                    <li><a href="public_events.php?type=Exam" class="text-white-50 text-decoration-none">Exam Date Sheet</a></li>
                </ul>
            </div>

            <div class="col-lg-3">
                <h6 class="fw-bold text-white mb-3">Academic Accreditation</h6>
                <p class="small mb-2">
                    EduSphere Academy operates in compliance with high-tier Aptech academic standards, featuring full relational schema, CSRF-hardened PDO security, and session defense.
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
