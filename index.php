<?php
/**
 * Academics Public Portal
 * Premium school management SaaS landing page.
 */
require_once 'includes/functions.php';
require_once 'includes/tenant.php';
require_once 'includes/saas.php';
startSecureSession();
require_once 'config/db.php';

// School subdomains (and ?school= on local/staging) open that school's public page.
$subdomainSchool = saas_bootstrap_school_context($pdo);
if (!$subdomainSchool && !empty($_GET['school'])) {
    $subdomainSchool = saas_school_by_subdomain($pdo, (string)$_GET['school']);
}
if ($subdomainSchool) {
    header('Location: public/school.php?code=' . urlencode((string)$subdomainSchool['school_code']));
    exit;
}

$page_title = 'Run your school with clarity';
require_once 'includes/header.php';

$contactSuccess = $_GET['contact_success'] ?? '';
$contactError = $_GET['contact_error'] ?? '';
?>

<div class="learnify-public-page">

    <!-- TOP NAVIGATION (shared public navbar) -->
    <?php $publicNavActive = 'home'; require __DIR__ . '/includes/public_nav.php'; ?>

    <!-- SECTION 1: HERO SECTION (Pixel-Matched to Image 2) -->
    <section class="learnify-hero-section">
        <div class="container py-lg-5">
            <div class="row align-items-center g-5">
                <!-- Left Title & Lead -->
                <div class="col-lg-6">
                    <h1 class="learnify-hero-title mb-4" style="font-family: 'Plus Jakarta Sans', sans-serif;">
                        Run your school<br>with clarity<br>and confidence
                    </h1>
                    <p class="learnify-hero-lead mb-4" style="font-size: 1.15rem; line-height: 1.7; color: #64748b; max-width: 520px;">
                        The all-in-one school platform for admissions, attendance, courses, tasks and parent communication &mdash; one calm, secure workspace for your whole school.
                    </p>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <a href="register.php" class="learnify-btn-green">
                            Start 1 month free
                        </a>
                        <a href="auth/login.php" class="btn btn-outline-dark rounded-pill px-4 py-2 fw-semibold" style="border-color:#cbd5e1;">
                            Sign in
                        </a>
                    </div>
                </div>

                <!-- Right Hero Image with 3 Floating Badges (Image 2) -->
                <div class="col-lg-6 position-relative text-center">
                    <div class="position-relative d-inline-block" style="max-width: 520px; width: 100%;">
                        <!-- Floating Badge 1: Top Left (Course is started) -->
                        <div class="learnify-floating-badge badge-started">
                            <span class="badge-icon-bubble bg-green"><i class="fas fa-check"></i></span>
                            <span style="font-weight: 600; color: #1e293b;">Course is started</span>
                        </div>

                        <!-- Floating Badge 2: Top Right (Course Completed) -->
                        <div class="learnify-floating-badge badge-completed">
                            <span class="badge-icon-bubble bg-cyan"><i class="fas fa-graduation-cap"></i></span>
                            <span style="font-weight: 600; color: #1e293b;">Course Completed</span>
                        </div>

                        <!-- Floating Badge 3: Left Bottom (Inbox / 3 Tasks) -->
                        <div class="learnify-floating-badge badge-inbox">
                            <span class="badge-icon-bubble bg-orange"><i class="fas fa-envelope"></i></span>
                            <span style="font-weight: 600; color: #1e293b;">Inbox (3 Tasks)</span>
                        </div>

                        <!-- Main Student Image with High Fetch Priority for LCP -->
                        <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=800&q=80" 
                             alt="Student learning with laptop and books" 
                             fetchpriority="high"
                             class="img-fluid rounded-4 shadow-lg" 
                             style="max-height: 460px; width: 100%; object-fit: cover; border: 4px solid #ffffff;">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION 2: OUR ACHIEVEMENTS (Matching Image 2) -->
    <section class="py-5 bg-white">
        <div class="container py-lg-4">
            <div class="text-center mb-5">
                <h2 class="fw-bold mb-2" style="font-size: 2.4rem; letter-spacing: -0.03em; color: var(--learnify-heading, #0f172a); font-family: 'Plus Jakarta Sans', sans-serif;">Our Achievements</h2>
                <p class="text-muted" style="font-size: 1.05rem;">With our super powers we have reached this</p>
            </div>

            <div class="row g-4 justify-content-center">
                <!-- Card 1: 125+ University -->
                <div class="col-md-4">
                    <div class="achievement-card">
                        <div class="achievement-icon-wrap" style="background-color: #fef3c7; color: #d97706;">
                            <i class="fas fa-university"></i>
                        </div>
                        <div>
                            <div class="h2 fw-bold text-dark mb-0" style="font-size: 2.25rem;">125+</div>
                            <div class="text-muted fw-semibold" style="font-size: 0.95rem;">University</div>
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
                            <div class="h2 fw-bold text-dark mb-0" style="font-size: 2.25rem;">304+</div>
                            <div class="text-muted fw-semibold" style="font-size: 0.95rem;">School</div>
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
                            <div class="h2 fw-bold text-dark mb-0" style="font-size: 2.25rem;">50K+</div>
                            <div class="text-muted fw-semibold" style="font-size: 0.95rem;">Students</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION 3: POWERFUL FEATURES (Matching Image 2 Alternating Feature Rows) -->
    <section id="features" class="py-5 learnify-features-section">
        <div class="container py-lg-4">
            <div class="text-center mb-5">
                <h2 class="fw-bold mb-2" style="font-size: 2.4rem; letter-spacing: -0.03em; color: var(--learnify-heading, #0f172a); font-family: 'Plus Jakarta Sans', sans-serif;">Powerful Features</h2>
                <p class="text-muted mx-auto" style="max-width: 550px; font-size: 1.05rem;">Special features which help you keep students on track and engaged during class time.</p>
            </div>

            <!-- Feature Row 1: View Student Screen (Image 2) -->
            <div class="row align-items-center g-5 mb-5 pb-lg-4">
                <div class="col-lg-6">
                    <div class="feature-img-box">
                        <img src="https://images.unsplash.com/photo-1577896851231-70ef18881754?auto=format&fit=crop&w=800&q=80" 
                             alt="View Student Screen" 
                             loading="lazy">
                    </div>
                </div>
                <div class="col-lg-6 ps-lg-5">
                    <h3 class="fw-bold text-dark mb-3" style="font-size: 2.1rem; font-family: 'Plus Jakarta Sans', sans-serif;">View Student Screen</h3>
                    <p class="text-muted mb-4" style="line-height: 1.8; font-size: 1.1rem;">
                        Teacher can see everything your students are doing on their computers. Be able to see at a glance who is on track and who needs some extra help.
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
                        <img src="https://images.unsplash.com/photo-1509062522246-3755977927d7?auto=format&fit=crop&w=800&q=80" 
                             alt="Assignment & Quiz" 
                             loading="lazy">
                    </div>
                </div>
                <div class="col-lg-6 pe-lg-5">
                    <h3 class="fw-bold text-dark mb-3" style="font-size: 2.1rem; font-family: 'Plus Jakarta Sans', sans-serif;">Assignment & Quiz</h3>
                    <p class="text-muted mb-4" style="line-height: 1.8; font-size: 1.1rem;">
                        Teacher can create a assignment or quiz or in Classroom and also schedule it to post later, add a due date and time and add attachments.
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
                        <img src="https://images.unsplash.com/photo-1503676260728-1c00da094a0b?auto=format&fit=crop&w=800&q=80" 
                             alt="Automatically Grading" 
                             loading="lazy">
                    </div>
                </div>
                <div class="col-lg-6 ps-lg-5">
                    <h3 class="fw-bold text-dark mb-3" style="font-size: 2.1rem; font-family: 'Plus Jakarta Sans', sans-serif;">Automatically Grading</h3>
                    <p class="text-muted mb-4" style="line-height: 1.8; font-size: 1.1rem;">
                        It automatically grade the quiz and import the grades into Classroom. Students can see their grades after teacher review their answers.
                    </p>
                    <a href="auth/login.php" class="learnify-btn-green">
                        Learn More
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION 4: PRICING (single free-trial offer) -->
    <section id="pricing" class="es-pricing-section py-5">
        <div class="container py-lg-4">
            <div class="text-center mb-5">
                <span class="es-eyebrow">Simple start</span>
                <h2 class="fw-bold mb-2" style="font-size: 2.4rem; letter-spacing: -0.03em; color: var(--learnify-heading, #0f172a); font-family: 'Plus Jakarta Sans', sans-serif;">One month free. Everything included.</h2>
                <p class="text-muted mx-auto" style="max-width: 620px; font-size: 1.05rem;">Try the complete Academics platform with your school. No credit card, no setup fee and no commitment.</p>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-9 col-xl-8">
                    <div class="es-price-card es-trial-card is-featured">
                        <span class="es-price-badge">No credit card required</span>

                        <div class="es-trial-top">
                            <div class="es-trial-copy">
                                <h3>1 Month Free</h3>
                                <p class="es-price-tagline">Full access for your entire school. Explore every module before you decide what comes next.</p>
                            </div>
                            <div class="es-trial-price">
                                <span>Free</span>
                                <small>for 30 days</small>
                            </div>
                        </div>

                        <ul class="es-price-features es-trial-features">
                            <li><i class="fas fa-check"></i> Student, course and attendance management</li>
                            <li><i class="fas fa-check"></i> Tasks, events and school-wide announcements</li>
                            <li><i class="fas fa-check"></i> Parent portal with progress reports</li>
                            <li><i class="fas fa-check"></i> Role-based access with secure school isolation</li>
                            <li><i class="fas fa-check"></i> Branded public page and events calendar</li>
                            <li><i class="fas fa-check"></i> Personal onboarding and priority support</li>
                        </ul>

                        <a href="register.php" class="btn btn-primary w-100 rounded-pill py-3 fw-semibold">
                            Start my free month <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                        <p class="es-trial-footnote">
                            <i class="fas fa-handshake-angle"></i>
                            After 30 days, pricing is tailored to your school. We will never charge you automatically.
                        </p>
                    </div>
                </div>
            </div>

            <p class="text-center text-muted small mt-4 mb-0"><i class="fas fa-shield-alt me-1"></i> Every account includes secure school isolation, role-based access and the parent portal.</p>
        </div>
    </section>

    <!-- SECTION 5: THEY ALL ARE LOVED US (Testimonials matching Image 2) -->
    <section id="testimonials" class="py-5 bg-white">
        <div class="container py-lg-4">
            <div class="text-center mb-5">
                <h2 class="fw-bold mb-2" style="font-size: 2.4rem; letter-spacing: -0.03em; color: var(--learnify-heading, #0f172a); font-family: 'Plus Jakarta Sans', sans-serif;">Loved by schools everywhere</h2>
                <p class="text-muted" style="font-size: 1.05rem;">We have been working with educators around the world</p>
            </div>

            <div class="row g-4">
                <!-- Card 1: Easy Collaborating -->
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div>
                            <h5 class="fw-bold text-dark mb-3" style="font-size: 1.25rem;">Easy Collaborating</h5>
                            <p class="text-muted small" style="line-height: 1.8; font-size: 0.95rem;">
                                "Its platform allows users to plan through many functions from video conferencing to creating."
                            </p>
                        </div>
                        <div class="d-flex align-items-center gap-3 pt-3 border-top mt-3">
                            <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=60&q=80" 
                                 alt="Brandon" 
                                 loading="lazy" 
                                 class="rounded-circle" 
                                 style="width: 44px; height: 44px; object-fit: cover;">
                            <div>
                                <div class="fw-bold text-dark small">Brandon</div>
                                <div class="text-muted" style="font-size: 0.75rem;">Teacher, NYU University</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Very Efficient -->
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div>
                            <h5 class="fw-bold text-dark mb-3" style="font-size: 1.25rem;">Very Efficient</h5>
                            <p class="text-muted small" style="line-height: 1.8; font-size: 0.95rem;">
                                "Academics works exactly the way our team does &mdash; simple, fast and focused on the students."
                            </p>
                        </div>
                        <div class="d-flex align-items-center gap-3 pt-3 border-top mt-3">
                            <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=60&q=80" 
                                 alt="Cheyenne Geidt" 
                                 loading="lazy" 
                                 class="rounded-circle" 
                                 style="width: 44px; height: 44px; object-fit: cover;">
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
                            <h5 class="fw-bold text-dark mb-3" style="font-size: 1.25rem;">Time Saving</h5>
                            <p class="text-muted small" style="line-height: 1.8; font-size: 0.95rem;">
                                "It is help us to manage our all things in one platform. Its saves almost 20 hours a week."
                            </p>
                        </div>
                        <div class="d-flex align-items-center gap-3 pt-3 border-top mt-3">
                            <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=60&q=80" 
                                 alt="Phillip Donin" 
                                 loading="lazy" 
                                 class="rounded-circle" 
                                 style="width: 44px; height: 44px; object-fit: cover;">
                            <div>
                                <div class="fw-bold text-dark small">Phillip Donin</div>
                                <div class="text-muted" style="font-size: 0.75rem;">CEO at Online Hub Edu</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Green Pagination Dots (Image 2) -->
            <div class="d-flex justify-content-center gap-2 mt-4 pt-3">
                <span class="rounded-circle" style="width: 9px; height: 9px; background-color: var(--brand-green);"></span>
                <span class="rounded-circle" style="width: 9px; height: 9px; background-color: #cbd5e1;"></span>
                <span class="rounded-circle" style="width: 9px; height: 9px; background-color: #cbd5e1;"></span>
            </div>
        </div>
    </section>

    <!-- SECTION 6: CONTACT -->
    <section id="contact" class="es-contact-section py-5">
        <div class="container py-lg-4">
            <div class="row g-4 g-lg-5 align-items-center">
                <div class="col-lg-5">
                    <span class="es-eyebrow">Contact us</span>
                    <h2 class="fw-bold mb-3" style="font-size: 2.2rem; letter-spacing: -0.03em; color: var(--learnify-heading, #0f172a); font-family: 'Plus Jakarta Sans', sans-serif;">Talk to the Academics team</h2>
                    <p class="text-muted mb-4" style="line-height: 1.8;">Questions about onboarding or bringing your school onto Academics? Send us a message and we will get back to you.</p>
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="es-contact-icon"><i class="fas fa-envelope"></i></span>
                            <div><div class="fw-semibold">Email</div><div class="text-muted small"><?php echo e(defined('APP_SUPPORT_EMAIL') ? APP_SUPPORT_EMAIL : 'support@academics.app'); ?></div></div>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <span class="es-contact-icon"><i class="fas fa-clock"></i></span>
                            <div><div class="fw-semibold">Support hours</div><div class="text-muted small">Monday–Friday, 9:00–18:00 PKT</div></div>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <span class="es-contact-icon"><i class="fas fa-school"></i></span>
                            <div><div class="fw-semibold">Already have a school?</div><div class="text-muted small"><a href="auth/login.php" class="text-decoration-none">Sign in to your portal</a></div></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="es-contact-card">
                        <?php if ($contactSuccess): ?>
                            <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Thanks! Your message has been sent. We will reply soon.</div>
                        <?php endif; ?>
                        <?php if ($contactError): ?>
                            <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo e($contactError); ?></div>
                        <?php endif; ?>
                        <form action="contact_process.php" method="POST" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <input type="hidden" name="redirect" value="home">
                            <div style="position:absolute;left:-9999px;" aria-hidden="true">
                                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Your name</label>
                                    <input type="text" name="name" class="form-control" required maxlength="100" placeholder="Your name">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" required maxlength="150" placeholder="you@example.com">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Subject</label>
                                    <input type="text" name="subject" class="form-control" maxlength="150" placeholder="How can we help?">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Message</label>
                                    <textarea name="message" class="form-control" rows="4" required minlength="10" maxlength="5000" placeholder="Tell us a little about your school..."></textarea>
                                </div>
                                <div class="col-12 d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg rounded-pill"><i class="fas fa-paper-plane me-2"></i>Send message</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION 7: READY CTA BANNER (Matching Image 2 Bottom Blue Card with Thumbs-Up Student) -->
    <section class="py-5 learnify-cta-section">
        <div class="container py-lg-3">
            <div class="ready-cta-banner">
                <div class="row align-items-center g-4">
                    <div class="col-md-7 ps-md-4">
                        <h2 class="fw-bold text-dark mb-3" style="font-size: 2.45rem; line-height: 1.25; font-family: 'Plus Jakarta Sans', sans-serif;">
                            Ready to bring your school online with Academics?
                        </h2>
                        <a href="register.php" class="learnify-btn-green mt-2">
                            Start your free month
                        </a>
                    </div>
                    <div class="col-md-5 text-center text-md-end">
                        <img src="https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?auto=format&fit=crop&w=500&q=80" 
                             alt="Student giving two thumbs up" 
                             loading="lazy"
                             class="rounded-4 shadow" 
                             style="max-height: 250px; width: 100%; max-width: 330px; object-fit: cover;">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php require __DIR__ . '/includes/public_footer.php'; ?>

</div>

<?php require_once 'includes/footer.php'; ?>
