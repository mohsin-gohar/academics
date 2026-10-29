<?php
/**
 * Public Contact Page
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/tenant.php';
require_once __DIR__ . '/includes/saas.php';
startSecureSession();
require_once __DIR__ . '/config/db.php';

$contextSchool = saas_bootstrap_school_context($pdo);
$page_title = 'Contact Us';
require_once __DIR__ . '/includes/header.php';

$success = $_GET['contact_success'] ?? '';
$error = $_GET['contact_error'] ?? '';
?>

<div class="learnify-public-page">
<?php $publicNavPrefix = 'index.php'; $publicNavActive = 'contact'; require __DIR__ . '/includes/public_nav.php'; ?>

<section class="es-contact-section py-5">
    <div class="container py-lg-4">
        <div class="row g-4 g-lg-5 align-items-center">
            <div class="col-lg-5">
                <span class="es-eyebrow">Contact us</span>
                <h1 class="fw-bold mb-3" style="font-size: clamp(2rem, 4vw, 2.8rem); letter-spacing: -0.03em; color: var(--text-main);">We’d love to hear from you</h1>
                <p class="text-muted mb-4" style="line-height: 1.8;">Questions about onboarding, data isolation or bringing your school onto Academics? Send us a message and the team will get back to you.</p>

                <?php if ($contextSchool): ?>
                    <div class="alert alert-info small">
                        <i class="fas fa-school me-2"></i>You are contacting <strong><?php echo e($contextSchool['school_name']); ?></strong>. Your message will be routed to that school’s administrator.
                    </div>
                <?php endif; ?>

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
                        <span class="es-contact-icon"><i class="fas fa-rocket"></i></span>
                        <div><div class="fw-semibold">Ready to start?</div><div class="text-muted small"><a href="register.php" class="text-decoration-none">Start your free month</a></div></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="es-contact-card" id="contact-form">
                    <?php if ($success): ?>
                        <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Thanks! Your message has been sent. We will reply soon.</div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?></div>
                    <?php endif; ?>

                    <form action="contact_process.php" method="POST" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="redirect" value="contact">
                        <div style="position:absolute;left:-9999px;" aria-hidden="true">
                            <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Your name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required maxlength="100" placeholder="Your name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" required maxlength="150" placeholder="you@example.com">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Subject</label>
                                <input type="text" name="subject" class="form-control" maxlength="150" placeholder="How can we help?">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Message <span class="text-danger">*</span></label>
                                <textarea name="message" class="form-control" rows="6" required minlength="10" maxlength="5000" placeholder="Tell us a little about your school..."></textarea>
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

<?php require __DIR__ . '/includes/public_footer.php'; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
