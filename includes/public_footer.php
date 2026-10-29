<?php
/**
 * Shared public marketing footer.
 */
$supportEmail = defined('APP_SUPPORT_EMAIL') ? APP_SUPPORT_EMAIL : 'support@academics.app';
?>
<footer class="ac-footer">
    <span class="ac-footer-orb ac-footer-orb-one" aria-hidden="true"></span>
    <span class="ac-footer-orb ac-footer-orb-two" aria-hidden="true"></span>

    <div class="container position-relative">
        <div class="ac-footer-cta">
            <div class="ac-footer-cta-copy">
                <span class="ac-footer-kicker"><i class="fas fa-gift"></i> Simple start</span>
                <h2>Your first month is on us.</h2>
                <p>Every module unlocked, every classroom connected. No credit card and no long-term commitment.</p>
            </div>
            <div class="ac-footer-cta-action">
                <a href="register.php" class="ac-footer-btn">
                    Start 1 month free <i class="fas fa-arrow-right"></i>
                </a>
                <span class="ac-footer-cta-note"><i class="fas fa-shield-halved"></i> 30 days of full platform access</span>
            </div>
        </div>

        <div class="row g-4 g-lg-5 ac-footer-grid">
            <div class="col-lg-4">
                <a href="index.php" class="ac-footer-brand">
                    <img src="assets/img/academics-mark.svg" alt="" width="46" height="46">
                    <span>Academics</span>
                </a>
                <p class="ac-footer-about">
                    The all-in-one school platform for admissions, attendance, courses, tasks and parent communication &mdash; built to keep every classroom in sync.
                </p>

                <div class="ac-footer-contact">
                    <a href="mailto:<?php echo e($supportEmail); ?>">
                        <i class="fas fa-envelope"></i>
                        <span><?php echo e($supportEmail); ?></span>
                    </a>
                    <span>
                        <i class="fas fa-clock"></i>
                        <span>Monday&ndash;Friday, 9:00&ndash;18:00 PKT</span>
                    </span>
                </div>

                <div class="ac-footer-trust">
                    <span><i class="fas fa-building-columns"></i> Multi-school ready</span>
                    <span><i class="fas fa-lock"></i> Role-based access</span>
                </div>
            </div>

            <div class="col-6 col-lg-2">
                <h6 class="ac-footer-heading">Platform</h6>
                <ul class="ac-footer-links">
                    <li><a href="index.php#features">Features</a></li>
                    <li><a href="index.php#pricing">Pricing</a></li>
                    <li><a href="public/index.php">Schools</a></li>
                    <li><a href="public_events.php">Events</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <h6 class="ac-footer-heading">Get started</h6>
                <ul class="ac-footer-links">
                    <li><a href="register.php">Start free month</a></li>
                    <li><a href="auth/login.php">Sign in</a></li>
                    <li><a href="auth/register.php">Create user account</a></li>
                    <li><a href="contact.php">Contact us</a></li>
                </ul>
            </div>

            <div class="col-lg-4">
                <div class="ac-footer-note">
                    <span class="ac-footer-note-icon"><i class="fas fa-comments"></i></span>
                    <h6>What happens after the trial?</h6>
                    <p>We&rsquo;ll talk to your team and tailor a plan around your school. No automatic charges, ever.</p>
                    <a href="contact.php">Talk to our team <i class="fas fa-arrow-up-right-from-square"></i></a>
                </div>
            </div>
        </div>

        <div class="ac-footer-bottom">
            <span class="ac-footer-copyright">&copy; <?php echo date('Y'); ?> Academics. Built for modern schools.</span>
            <div class="ac-footer-bottom-links">
                <a href="index.php#features">Features</a>
                <a href="index.php#pricing">Pricing</a>
                <a href="contact.php">Support</a>
            </div>
            <button type="button" class="ac-footer-top" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="Back to top">
                <i class="fas fa-arrow-up"></i>
            </button>
        </div>
    </div>
</footer>
