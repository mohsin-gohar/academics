<?php
/**
 * EduSphere Public Portal (Matching Reference Image 2 - Learnify)
 * High-End Luxury Education Platform & School Website
 */
$page_title = 'EduSphere — Take your time and learn from anywhere';
require_once 'includes/header.php';

// Fetch Active Emergency Alert
$activeAlert = getActiveEmergencyAlert($pdo);

// Fetch Upcoming Events
$events = getSchoolEventsList($pdo, 3, true);

// Fetch Next Gazetted Holidays
$holidayStmt = $pdo->query("SELECT * FROM school_events WHERE is_holiday = 1 AND event_date >= CURDATE() AND status != 'Cancelled' ORDER BY event_date ASC LIMIT 3");
$holidays = $holidayStmt->fetchAll();

// Counts for achievements
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn() ?: 50000;
?>

<div class="learnify-public-page">

    <!-- Top Navigation Header (Matching Learnify Navbar in Image 2) -->
    <nav class="navbar navbar-expand-lg learnify-nav">
        <div class="container">
            <a class="learnify-brand d-flex align-items-center gap-2" href="index.php">
                <span style="color: #1e293b; font-weight: 800; font-size: 1.75rem; letter-spacing: -0.03em;">EduSphere</span>
            </a>

            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#learnifyNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="learnifyNav">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0 fw-semibold text-secondary">
                    <li class="nav-item"><a class="nav-link active text-success px-3" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="public_events.php">Events</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="public_events.php?type=Holiday">Holidays</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="#testimonials">Reviews</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="auth/login.php">Login</a></li>
                </ul>

                <div class="d-flex align-items-center gap-2">
                    <?php if (isLoggedIn()): ?>
                        <a href="dashboard.php" class="btn btn-outline-dark rounded-pill px-4 py-2 fw-semibold">
                            My Dashboard
                        </a>
                    <?php else: ?>
                        <a href="auth/register.php" class="btn btn-outline-dark rounded-pill px-4 py-2 fw-semibold" style="border-color: #cbd5e1;">
                            Signup
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- LIVE EMERGENCY CLOSURE NOTIFICATION BANNER (IF ACTIVE) -->
    <?php if ($activeAlert): ?>
    <div class="bg-danger text-white py-3 shadow-sm border-bottom border-danger">
        <div class="container">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-white text-danger px-3 py-2 fw-bold text-uppercase fs-6">
                        <span class="pulse-dot text-danger me-1"></span> <?php echo e($activeAlert['alert_type']); ?>
                    </span>
                    <div>
                        <h5 class="mb-0 fw-bold"><?php echo e($activeAlert['title']); ?></h5>
                        <div class="small opacity-90 mt-1"><?php echo e($activeAlert['message']); ?></div>
                    </div>
                </div>
                <div>
                    <span class="badge bg-black bg-opacity-25 text-white px-3 py-2 border border-white border-opacity-25 fw-semibold">
                        Effective: <?php echo formatDate($activeAlert['effective_date']); ?>
                        <?php if (!empty($activeAlert['end_date'])): ?>
                            — Reopening: <?php echo formatDate($activeAlert['end_date']); ?>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- HERO SECTION (Matching Image 2 Top Section) -->
    <section class="learnify-hero-section">
        <div class="container py-lg-4">
            <div class="row align-items-center g-5">
                <!-- Left Title & Lead -->
                <div class="col-lg-6">
                    <h1 class="learnify-hero-title mb-4">
                        Take your time and learn from anywhere
                    </h1>
                    <p class="learnify-hero-lead mb-4">
                        The world's most trusted open-source learning management system. An easy tool helping educators efficiently manage and assess progress.
                    </p>
                    <a href="auth/login.php" class="learnify-btn-green">
                        Get started
                    </a>
                </div>

                <!-- Right Hero Image with 3 Floating Badges (Image 2) -->
                <div class="col-lg-6 position-relative text-center">
                    <div class="position-relative d-inline-block">
                        <!-- Floating Badge 1: Top Right (Course is started) -->
                        <div class="learnify-floating-badge" style="top: 25px; left: -10px;">
                            <span class="badge rounded-circle p-1" style="background-color: #84cc16; color: #fff;"><i class="fas fa-check fa-xs"></i></span>
                            <span>Course is started</span>
                        </div>

                        <!-- Floating Badge 2: Right Middle (Course Completed) -->
                        <div class="learnify-floating-badge" style="top: 110px; right: -20px;">
                            <span class="badge rounded-circle p-1" style="background-color: #06b6d4; color: #fff;"><i class="fas fa-graduation-cap fa-xs"></i></span>
                            <span>Course Completed</span>
                        </div>

                        <!-- Floating Badge 3: Left Bottom (Inbox / 3 Tasks) -->
                        <div class="learnify-floating-badge" style="bottom: 120px; left: -25px;">
                            <span class="badge rounded-circle p-1" style="background-color: #f97316; color: #fff;"><i class="fas fa-envelope fa-xs"></i></span>
                            <span>Inbox (3 Tasks)</span>
                        </div>

                        <!-- Main Student Image -->
                        <img src="https://images.unsplash.com/photo-1523240795612-9a054b0db644?auto=format&fit=crop&w=700&q=80" 
                             alt="Student learning" 
                             class="img-fluid rounded-4 shadow-lg" 
                             style="max-height: 440px; width: 100%; object-fit: cover; border: 4px solid #ffffff;">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION 2: OUR ACHIEVEMENTS (Matching Image 2) -->
    <section class="py-5 bg-white">
        <div class="container py-lg-4">
            <div class="text-center mb-5">
                <h2 class="fw-bold text-dark mb-2" style="font-size: 2.25rem;">Our Achievements</h2>
                <p class="text-muted">With our super powers we have reached this</p>
            </div>

            <div class="row g-4 justify-content-center">
                <!-- Card 1: 125+ University -->
                <div class="col-md-4">
                    <div class="achievement-card">
                        <div class="achievement-icon-wrap" style="background-color: #fef3c7; color: #d97706;">
                            <i class="fas fa-university"></i>
                        </div>
                        <div>
                            <div class="h2 fw-bold text-dark mb-0">125+</div>
                            <div class="text-muted fw-semibold">University Programs</div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: 304+ School -->
                <div class="col-md-4">
                    <div class="achievement-card">
                        <div class="achievement-icon-wrap" style="background-color: #fee2e2; color: #dc2626;">
                            <i class="fas fa-school"></i>
                        </div>
                        <div>
                            <div class="h2 fw-bold text-dark mb-0">304+</div>
                            <div class="text-muted fw-semibold">School Campuses</div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: 50K+ Students -->
                <div class="col-md-4">
                    <div class="achievement-card">
                        <div class="achievement-icon-wrap" style="background-color: #e0f2fe; color: #0284c7;">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div>
                            <div class="h2 fw-bold text-dark mb-0">50K+</div>
                            <div class="text-muted fw-semibold">Students Enrolled</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION 3: POWERFUL FEATURES (Matching Image 2 Alternating Feature Rows) -->
    <section id="features" class="py-5" style="background-color: #fafbfc;">
        <div class="container py-lg-4">
            <div class="text-center mb-5">
                <h2 class="fw-bold text-dark mb-2" style="font-size: 2.25rem;">Powerful Features</h2>
                <p class="text-muted mx-auto" style="max-width: 550px;">Special features which help you keep students on track and engaged during class time.</p>
            </div>

            <!-- Feature Row 1: View Student Screen -->
            <div class="row align-items-center g-5 mb-5 pb-lg-4">
                <div class="col-lg-6">
                    <div class="feature-img-box">
                        <img src="https://images.unsplash.com/photo-1577896851231-70ef18881754?auto=format&fit=crop&w=800&q=80" alt="View Student Screen">
                    </div>
                </div>
                <div class="col-lg-6 ps-lg-5">
                    <h3 class="fw-bold text-dark mb-3" style="font-size: 1.85rem;">View Student Screen</h3>
                    <p class="text-muted mb-4" style="line-height: 1.8;">
                        Teacher can see everything your students are doing on their computers. Be able to see at a glance who is on track and who needs extra help.
                    </p>
                    <a href="auth/login.php" class="learnify-btn-green">
                        Learn More
                    </a>
                </div>
            </div>

            <!-- Feature Row 2: Assignment & Quiz (Matching Image 2 Top Right) -->
            <div class="row align-items-center g-5 mb-5 pb-lg-4 flex-lg-row-reverse">
                <div class="col-lg-6">
                    <div class="feature-img-box">
                        <img src="https://images.unsplash.com/photo-1509062522246-3755977927d7?auto=format&fit=crop&w=800&q=80" alt="Assignment and Quiz">
                    </div>
                </div>
                <div class="col-lg-6 pe-lg-5">
                    <h3 class="fw-bold text-dark mb-3" style="font-size: 1.85rem;">Assignment & Quiz</h3>
                    <p class="text-muted mb-4" style="line-height: 1.8;">
                        Teacher can create a assignment or quiz or in Classroom. And also schedule it to post later, add a due date and time and add attachments.
                    </p>
                    <a href="auth/login.php" class="learnify-btn-green">
                        Learn More
                    </a>
                </div>
            </div>

            <!-- Feature Row 3: Automatically Grading (Matching Image 2) -->
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <div class="feature-img-box">
                        <img src="https://images.unsplash.com/photo-1503676260728-1c00da094a0b?auto=format&fit=crop&w=800&q=80" alt="Automatically Grading">
                    </div>
                </div>
                <div class="col-lg-6 ps-lg-5">
                    <h3 class="fw-bold text-dark mb-3" style="font-size: 1.85rem;">Automatically Grading</h3>
                    <p class="text-muted mb-4" style="line-height: 1.8;">
                        It automatically grade the quiz and import the grades into Classroom. Students can see their grades after teacher review their answers.
                    </p>
                    <a href="auth/login.php" class="learnify-btn-green">
                        Learn More
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION 4: SCHOOL FUNCTIONS & GAZETTED HOLIDAYS (Added value for parents & students) -->
    <section class="py-5 bg-white">
        <div class="container py-lg-4">
            <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
                <div>
                    <h2 class="fw-bold text-dark mb-1" style="font-size: 2.25rem;">Upcoming Events & Holidays</h2>
                    <p class="text-muted mb-0">What is happening at school and scheduled off days for parents and students.</p>
                </div>
                <a href="public_events.php" class="learnify-btn-green" style="padding: 0.5rem 1.4rem;">
                    Full Calendar <i class="fas fa-calendar-alt ms-1"></i>
                </a>
            </div>

            <div class="row g-4">
                <?php foreach ($events as $ev): ?>
                <div class="col-md-4">
                    <div class="p-4 rounded-4 border bg-light h-100 d-flex flex-column justify-content-between">
                        <div>
                            <span class="badge bg-<?php echo $ev['is_holiday'] ? 'danger' : 'primary'; ?> mb-2"><?php echo e($ev['event_type']); ?></span>
                            <h5 class="fw-bold text-dark mb-2"><?php echo e($ev['title']); ?></h5>
                            <p class="text-muted small mb-3"><?php echo mb_strimwidth(e($ev['description']), 0, 95, '...'); ?></p>
                        </div>
                        <div class="pt-2 border-top text-secondary small fw-semibold">
                            <i class="fas fa-calendar-day me-1 text-primary"></i> <?php echo formatDate($ev['event_date']); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- SECTION 5: THEY ALL ARE LOVED US (Testimonials matching Image 2) -->
    <section id="testimonials" class="py-5" style="background-color: #fafbfc;">
        <div class="container py-lg-4">
            <div class="text-center mb-5">
                <h2 class="fw-bold text-dark mb-2" style="font-size: 2.25rem;">They all are loved us</h2>
                <p class="text-muted">We have been working with clients around the world</p>
            </div>

            <div class="row g-4">
                <!-- Card 1: Easy Collaborating -->
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div>
                            <h5 class="fw-bold text-dark mb-3">Easy Collaborating</h5>
                            <p class="text-muted small" style="line-height: 1.8;">
                                "Its platform allows users to plan through many functions from video conferencing to creating."
                            </p>
                        </div>
                        <div class="d-flex align-items-center gap-3 pt-3 border-top mt-3">
                            <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=60&q=80" alt="Brandon" class="rounded-circle" style="width: 44px; height: 44px; object-fit: cover;">
                            <div>
                                <div class="fw-bold text-dark small">Brandon</div>
                                <div class="text-muted" style="font-size: 0.75rem;">Teacher, MIT University</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Very Efficient -->
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div>
                            <h5 class="fw-bold text-dark mb-3">Very Efficient</h5>
                            <p class="text-muted small" style="line-height: 1.8;">
                                "Learnify is done very effective works well and produces the results that were intended."
                            </p>
                        </div>
                        <div class="d-flex align-items-center gap-3 pt-3 border-top mt-3">
                            <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=60&q=80" alt="Cheyenne Geidt" class="rounded-circle" style="width: 44px; height: 44px; object-fit: cover;">
                            <div>
                                <div class="fw-bold text-dark small">Cheyenne Geidt</div>
                                <div class="text-muted" style="font-size: 0.75rem;">Principal, Science College</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Time Saving -->
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div>
                            <h5 class="fw-bold text-dark mb-3">Time Saving</h5>
                            <p class="text-muted small" style="line-height: 1.8;">
                                "It is help us to manage our all things in one platform, its saves almost 20 hours a week."
                            </p>
                        </div>
                        <div class="d-flex align-items-center gap-3 pt-3 border-top mt-3">
                            <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=60&q=80" alt="Phillip Donin" class="rounded-circle" style="width: 44px; height: 44px; object-fit: cover;">
                            <div>
                                <div class="fw-bold text-dark small">Phillip Donin</div>
                                <div class="text-muted" style="font-size: 0.75rem;">CEO at Online Hub Edu</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Green Pagination Dots (Image 2) -->
            <div class="d-flex justify-content-center gap-2 mt-4 pt-2">
                <span class="rounded-circle" style="width: 8px; height: 8px; background-color: var(--brand-green);"></span>
                <span class="rounded-circle" style="width: 8px; height: 8px; background-color: #cbd5e1;"></span>
                <span class="rounded-circle" style="width: 8px; height: 8px; background-color: #cbd5e1;"></span>
            </div>
        </div>
    </section>

    <!-- SECTION 6: READY CTA BANNER (Matching Image 2 Bottom Blue Card with Thumbs-Up Student) -->
    <section class="py-5 bg-white">
        <div class="container">
            <div class="ready-cta-banner">
                <div class="row align-items-center g-4">
                    <div class="col-md-7 ps-md-4">
                        <h2 class="fw-bold text-dark mb-3" style="font-size: 2.2rem; line-height: 1.25;">
                            Ready to be more productive with Learnify?
                        </h2>
                        <a href="auth/login.php" class="learnify-btn-green mt-2">
                            Get started
                        </a>
                    </div>
                    <div class="col-md-5 text-center text-md-end">
                        <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=400&q=80" 
                             alt="Happy Scholar" 
                             class="rounded-4 shadow" 
                             style="max-height: 240px; width: 100%; max-width: 320px; object-fit: cover;">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER (Matching Image 2 Dark Navy Footer) -->
    <footer class="learnify-footer">
        <div class="container">
            <div class="row g-4 mb-5">
                <div class="col-lg-4">
                    <h4 class="fw-bold text-white mb-3">EduSphere</h4>
                    <p class="small text-muted mb-4" style="max-width: 300px; line-height: 1.8;">
                        Get in Touch<br>
                        Questions or feedback?<br>
                        We'd love to hear from you.
                    </p>
                    <div class="d-flex gap-3 fs-5">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>

                <div class="col-6 col-lg-2">
                    <h6 class="fw-bold text-white mb-3 small text-uppercase">Products</h6>
                    <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                        <li><a href="#">Student Screen</a></li>
                        <li><a href="#">Remote Access</a></li>
                        <li><a href="#">Grading</a></li>
                        <li><a href="#">Monitoring</a></li>
                    </ul>
                </div>

                <div class="col-6 col-lg-2">
                    <h6 class="fw-bold text-white mb-3 small text-uppercase">Support</h6>
                    <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                        <li><a href="#">Help Desk</a></li>
                        <li><a href="#">Contact</a></li>
                        <li><a href="#">Privacy Center</a></li>
                        <li><a href="#">FAQ</a></li>
                    </ul>
                </div>

                <div class="col-lg-4">
                    <h6 class="fw-bold text-white mb-3 small text-uppercase">Available on</h6>
                    <div class="d-flex flex-column gap-2" style="max-width: 170px;">
                        <a href="#" class="btn btn-dark btn-sm text-start py-2 px-3 border border-secondary rounded-3">
                            <small class="d-block" style="font-size: 0.65rem;">Download on the</small>
                            <i class="fab fa-apple me-1"></i> <strong>App Store</strong>
                        </a>
                        <a href="#" class="btn btn-dark btn-sm text-start py-2 px-3 border border-secondary rounded-3">
                            <small class="d-block" style="font-size: 0.65rem;">GET IT ON</small>
                            <i class="fab fa-google-play me-1"></i> <strong>Google Play</strong>
                        </a>
                    </div>
                </div>
            </div>

            <div class="pt-4 border-top border-secondary text-center small text-muted">
                Designed with ❤️ for EduSphere — Enterprise Student Management System
            </div>
        </div>
    </footer>

</div>

<?php require_once 'includes/footer.php'; ?>
