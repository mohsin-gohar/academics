<?php
/**
 * Shared public marketing navigation.
 * Optional: $publicNavPrefix ('' on the landing page, 'index.php' elsewhere)
 *           $publicNavActive ('home', 'contact', ...)
 */
$navPrefix = $publicNavPrefix ?? '';
$navActive = $publicNavActive ?? '';
?>
<nav class="navbar navbar-expand-lg learnify-nav learnify-nav-sticky">
    <div class="container py-1">
        <a class="learnify-brand d-flex align-items-center gap-2" href="index.php">
            <img class="learnify-brand-mark" src="assets/img/academics-mark.svg" alt="" width="38" height="38">
            <span class="learnify-brand-text" style="font-weight: 800; font-size: 1.72rem; letter-spacing: -0.04em;"><?php echo e(APP_NAME); ?></span>
        </a>

        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#learnifyNav" aria-controls="learnifyNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="learnifyNav">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0 fw-semibold">
                <li class="nav-item"><a class="nav-link <?php echo $navActive === 'home' ? 'active text-success' : 'text-secondary'; ?> px-3" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link text-secondary px-3" href="<?php echo e($navPrefix); ?>#features">Features</a></li>
                <li class="nav-item"><a class="nav-link text-secondary px-3" href="<?php echo e($navPrefix); ?>#pricing">Pricing</a></li>
                <li class="nav-item"><a class="nav-link text-secondary px-3" href="public/index.php">Schools</a></li>
                <li class="nav-item"><a class="nav-link text-secondary px-3" href="public_events.php">Events</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $navActive === 'contact' ? 'active text-success' : 'text-secondary'; ?> px-3" href="contact.php">Contact</a></li>
                <li class="nav-item"><a class="nav-link text-secondary px-3" href="auth/login.php">Login</a></li>
            </ul>

            <div class="d-flex align-items-center gap-2">
                <?php if (isLoggedIn()): ?>
                    <a href="dashboard.php" class="btn btn-outline-dark rounded-pill px-4 py-2 fw-semibold" style="border-color: #cbd5e1; font-size: 0.95rem;">
                        <i class="fas fa-th-large me-1"></i> Dashboard
                    </a>
                <?php else: ?>
                    <a href="register.php" class="btn btn-outline-dark rounded-pill px-4 py-2 fw-semibold" style="border-color: #cbd5e1; font-size: 0.95rem;">
                        Start 1 Month Free
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
