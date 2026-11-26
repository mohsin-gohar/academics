<?php
/**
 * Public School Registration
 * Creates a tenant school, its school_admin account, settings and trial subscription.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/tenant.php';
require_once __DIR__ . '/includes/saas.php';
startSecureSession();
require_once __DIR__ . '/config/db.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$old = [
    'school_name' => '',
    'subdomain'   => '',
    'full_name'   => '',
    'email'       => '',
    'phone'       => '',
    'address'     => '',
    'plan'        => 'pro',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($old) as $key) {
        if (isset($_POST[$key])) {
            $old[$key] = trim((string)$_POST[$key]);
        }
    }
    $old['subdomain'] = strtolower($old['subdomain']);
    // Public signup always starts on the full-access trial. Commercial plans
    // are agreed personally with each school after the free month.
    $old['plan'] = 'pro';

    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    } elseif (!empty($_POST['website'])) {
        // Honeypot: real users never fill this hidden field.
        $errors[] = 'Registration could not be completed. Please try again.';
    } elseif (!empty($_SESSION['school_register_last']) && (time() - (int)$_SESSION['school_register_last']) < 45) {
        $errors[] = 'Please wait a few seconds before submitting another registration.';
    } else {
        $_SESSION['school_register_last'] = time();
        try {
            $result = saas_register_school($pdo, [
                'school_name'      => $old['school_name'],
                'subdomain'        => $old['subdomain'],
                'full_name'        => $old['full_name'],
                'email'            => $old['email'],
                'phone'            => $old['phone'],
                'address'          => $old['address'],
                'password'         => (string)($_POST['password'] ?? ''),
                'confirm_password' => (string)($_POST['confirm_password'] ?? ''),
                'plan'             => $old['plan'],
            ]);

            $newSchool = school_by_id($pdo, (int)$result['school_id']);
            $loginUrl = $newSchool
                ? saas_school_login_url($newSchool)
                : 'auth/login.php?school=' . urlencode($result['subdomain']);
            $loginUrl .= (strpos($loginUrl, '?') === false ? '?' : '&') . 'success=' . urlencode('Your school is ready! Sign in with your administrator email to start your 1-month free trial.');
            header('Location: ' . $loginUrl);
            exit;
        } catch (InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        } catch (Throwable $e) {
            error_log('School registration failed: ' . $e->getMessage());
            $errors[] = 'We could not create your school right now. Please try again or contact support.';
        }
    }
}

$page_title = 'Register Your School';
require_once __DIR__ . '/includes/header.php';

$domainLabel = (defined('APP_DOMAIN') && APP_DOMAIN !== '') ? APP_DOMAIN : 'your-domain.com';
?>

<style>
    .school-register-card { max-width: 820px; }
    .school-register-card .auth-header { padding-bottom: 1.25rem; }
    .reg-section-title {
        font-size: .74rem;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: var(--es-primary, #4026e1);
        margin: 1.35rem 0 .8rem;
        display: flex;
        align-items: center;
        gap: .5rem;
    }
    .reg-section-title:first-of-type { margin-top: 0; }
    .trial-offer {
        display: flex;
        align-items: flex-start;
        gap: .95rem;
        padding: 1rem 1.05rem;
        border: 1.5px solid rgba(64, 38, 225, .28);
        border-radius: 16px;
        background: linear-gradient(135deg, #f7f5ff 0%, #ffffff 100%);
        box-shadow: 0 14px 34px -26px rgba(64, 38, 225, .55);
    }
    .trial-offer-icon {
        width: 44px;
        height: 44px;
        flex-shrink: 0;
        display: grid;
        place-items: center;
        border-radius: 13px;
        color: #fff;
        background: linear-gradient(135deg, var(--es-primary, #4026e1), #7c68ff);
        box-shadow: 0 10px 22px -12px rgba(64, 38, 225, .7);
    }
    .trial-offer-title {
        display: flex;
        align-items: center;
        gap: .5rem;
        flex-wrap: wrap;
        font-weight: 800;
        font-size: 1.02rem;
        color: var(--text-main, #0f172a);
    }
    .trial-offer-title span {
        font-size: .6rem;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--es-primary, #4026e1);
        background: rgba(64, 38, 225, .1);
        border-radius: 999px;
        padding: .2rem .55rem;
    }
    .trial-offer p {
        margin: .3rem 0 0;
        font-size: .78rem;
        line-height: 1.55;
        color: #6b7280;
    }
    [data-theme="dark"] .trial-offer { background: rgba(87, 60, 255, .12); border-color: rgba(139, 124, 255, .3); }
    [data-theme="dark"] .trial-offer-title { color: #F4F3FF; }
    [data-theme="dark"] .trial-offer p { color: #a9b4c5; }
    @media (max-width: 575.98px) {
        .school-register-card .auth-body { padding: 1.25rem; }
    }
</style>

<div class="auth-wrapper py-4">
    <div class="auth-card school-register-card">
        <div class="auth-header">
            <div class="brand-icon brand-icon-logo"><img src="assets/img/academics-mark.svg" alt="<?php echo e(APP_NAME); ?> logo" width="44" height="44"></div>
            <h3 class="mb-1">Register your school</h3>
            <p class="mb-0 opacity-75">Start 1 month free. No credit card required.</p>
        </div>
        <div class="auth-body">
            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo e(implode(' ', $errors)); ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <div style="position:absolute;left:-9999px;" aria-hidden="true">
                    <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>

                <div class="reg-section-title"><i class="fas fa-school"></i> School details</div>
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label">School name <span class="text-danger">*</span></label>
                        <input type="text" name="school_name" class="form-control" required maxlength="150"
                               value="<?php echo e($old['school_name']); ?>" placeholder="e.g. Green Valley High School">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">School subdomain <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="subdomain" id="subdomain" class="form-control" required
                                   pattern="[a-z0-9][a-z0-9-]{1,48}[a-z0-9]" maxlength="50"
                                   value="<?php echo e($old['subdomain']); ?>" placeholder="greenvalley"
                                   autocomplete="off">
                            <span class="input-group-text">.<?php echo e($domainLabel); ?></span>
                        </div>
                        <div class="form-text">Lowercase letters, numbers and hyphens only.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">School email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required maxlength="150"
                               value="<?php echo e($old['email']); ?>" placeholder="admin@yourschool.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" maxlength="30"
                               value="<?php echo e($old['phone']); ?>" placeholder="+92 300 1234567">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control" maxlength="255"
                               value="<?php echo e($old['address']); ?>" placeholder="School street address">
                    </div>
                </div>

                <div class="reg-section-title"><i class="fas fa-user-shield"></i> Administrator account</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Full name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" required maxlength="100"
                               value="<?php echo e($old['full_name']); ?>" placeholder="Your full name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Login email <span class="text-danger">*</span></label>
                        <input type="email" name="email_login" class="form-control" value="<?php echo e($old['email']); ?>" disabled>
                        <div class="form-text">The school email above is also your login email.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="password" id="password" class="form-control" required minlength="10"
                                   placeholder="Create a strong password" autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button" onclick="toggleRegPassword('password','toggleIcon1')">
                                <i class="fas fa-eye" id="toggleIcon1"></i>
                            </button>
                        </div>
                        <div class="form-text">At least 10 characters with upper case, lower case, a number and a special character.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Confirm password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required minlength="10"
                                   placeholder="Repeat your password" autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button" onclick="toggleRegPassword('confirm_password','toggleIcon2')">
                                <i class="fas fa-eye" id="toggleIcon2"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="reg-section-title"><i class="fas fa-gift"></i> Your free month</div>
                <div class="trial-offer">
                    <span class="trial-offer-icon"><i class="fas fa-gift"></i></span>
                    <div>
                        <div class="trial-offer-title">1 Month Free <span>Full access</span></div>
                        <p>Every module is unlocked for 30 days &mdash; no credit card and no setup fee. After your free month, we will tailor a plan around your school.</p>
                    </div>
                </div>
                <input type="hidden" name="plan" value="pro">

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-rocket me-2"></i> Create my school
                    </button>
                </div>
            </form>

            <hr class="my-4">
            <div class="d-flex flex-wrap justify-content-center gap-3 small">
                <span>Already have an account? <a href="auth/login.php" class="text-primary text-decoration-none fw-medium">Sign in</a></span>
                <span class="text-muted">•</span>
                <span>Joining an existing school? <a href="auth/register.php" class="text-primary text-decoration-none fw-medium">Create a user account</a></span>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleRegPassword(fieldId, iconId) {
        var field = document.getElementById(fieldId);
        var icon = document.getElementById(iconId);
        if (!field || !icon) return;
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
    (function () {
        var schoolName = document.querySelector('input[name="school_name"]');
        var subdomain = document.getElementById('subdomain');
        if (!schoolName || !subdomain) return;
        var touched = false;
        subdomain.addEventListener('input', function () { touched = true; });
        schoolName.addEventListener('input', function () {
            if (touched || subdomain.value) return;
            subdomain.value = schoolName.value.toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '')
                .slice(0, 50);
        });
    })();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
