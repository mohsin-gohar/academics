<?php
/**
 * Academics SaaS Layer
 * --------------------
 * School self-registration, subscription plans, trial enforcement,
 * subdomain resolution and public contact intake.
 *
 * The existing multi-tenant core lives in includes/tenant.php. This file
 * deliberately builds on top of it instead of replacing it, so every
 * school-scoped query keeps using school_id.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/tenant.php';

if (!defined('SAAS_TRIAL_DAYS')) {
    define('SAAS_TRIAL_DAYS', 30);
}

/* -------------------------------------------------------------------------
 * Plans
 * ---------------------------------------------------------------------- */

function saas_plans(): array
{
    return [
        'basic' => [
            'label'         => 'Basic',
            'price'         => 50.00,
            'student_limit' => 100,
            'tagline'       => 'For small schools getting started',
            'features'      => [
                'Up to 100 students',
                'Student & course management',
                'Tasks, events & announcements',
                'Email support',
            ],
        ],
        'pro' => [
            'label'         => 'Pro',
            'price'         => 150.00,
            'student_limit' => 500,
            'tagline'       => 'For growing schools that need more',
            'features'      => [
                'Up to 500 students',
                'Everything in Basic',
                'Parent portal & progress reports',
                'Priority support',
            ],
        ],
        'enterprise' => [
            'label'         => 'Enterprise',
            'price'         => 500.00,
            'student_limit' => null,
            'tagline'       => 'For large institutions and groups',
            'features'      => [
                'Unlimited students',
                'Everything in Pro',
                'Custom branding & public page',
                'Dedicated onboarding',
            ],
        ],
    ];
}

function saas_valid_plan(string $plan): bool
{
    return array_key_exists($plan, saas_plans());
}

function saas_plan_price(string $plan): float
{
    $plans = saas_plans();
    return isset($plans[$plan]) ? (float)$plans[$plan]['price'] : (float)$plans['basic']['price'];
}

function saas_plan_label(string $plan): string
{
    $plans = saas_plans();
    return $plans[$plan]['label'] ?? ucfirst($plan);
}

function saas_plan_from_legacy(string $legacy): string
{
    return match (strtolower($legacy)) {
        'premium' => 'enterprise',
        'pro'     => 'pro',
        default   => 'basic',
    };
}

function saas_legacy_plan(string $plan): string
{
    return match (strtolower($plan)) {
        'enterprise' => 'premium',
        'pro'        => 'premium',
        default      => 'basic',
    };
}

/* -------------------------------------------------------------------------
 * Subdomains
 * ---------------------------------------------------------------------- */

function saas_reserved_subdomains(): array
{
    return ['www', 'admin', 'app', 'api', 'mail', 'ftp', 'login', 'register', 'public', 'school', 'schools', 'dashboard', 'auth', 'support', 'help'];
}

function saas_validate_subdomain(string $subdomain): bool
{
    $subdomain = strtolower(trim($subdomain));
    if (strlen($subdomain) < 3 || strlen($subdomain) > 50) {
        return false;
    }
    if (!preg_match('/^[a-z0-9][a-z0-9-]*[a-z0-9]$/', $subdomain)) {
        return false;
    }
    if (strpos($subdomain, '--') !== false) {
        return false;
    }
    return !in_array($subdomain, saas_reserved_subdomains(), true);
}

/**
 * Resolve the school subdomain from the request host.
 *
 * Set APP_DOMAIN in config/db_config.php (or the environment) for production,
 * e.g. "academics.com". When APP_DOMAIN is empty the function falls back to
 * the first label of any three-part hostname. *.localhost is supported for
 * local development.
 */
function saas_subdomain_from_host(?string $host = null, ?string $baseDomain = null): ?string
{
    $host = strtolower(trim($host ?? ($_SERVER['HTTP_HOST'] ?? '')));
    if ($host === '') {
        return null;
    }
    $host = preg_replace('/:\d+$/', '', $host);
    if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
        return null;
    }

    $baseDomain = $baseDomain ?? (defined('APP_DOMAIN') ? (string)APP_DOMAIN : '');
    $baseDomain = strtolower(trim(preg_replace('#^https?://#', '', $baseDomain)));
    $baseDomain = rtrim(preg_replace('/:\d+$/', '', $baseDomain), '.');

    if ($baseDomain !== '') {
        if ($host === $baseDomain) {
            return null;
        }
        $suffix = '.' . $baseDomain;
        if (substr($host, -strlen($suffix)) !== $suffix) {
            return null;
        }
        $sub = substr($host, 0, -strlen($suffix));
    } elseif (substr($host, -strlen('.localhost')) === '.localhost') {
        $sub = substr($host, 0, -strlen('.localhost'));
    } else {
        $parts = explode('.', $host);
        if (count($parts) < 3) {
            return null;
        }
        $sub = $parts[0];
    }

    if ($sub === '' || strpos($sub, '.') !== false) {
        return null;
    }
    return saas_validate_subdomain($sub) ? $sub : null;
}

function saas_school_by_subdomain(PDO $pdo, string $subdomain): ?array
{
    $subdomain = strtolower(trim($subdomain));
    if (!saas_validate_subdomain($subdomain)) {
        return null;
    }
    try {
        $stmt = $pdo->prepare("SELECT * FROM schools WHERE subdomain = ? LIMIT 1");
        $stmt->execute([$subdomain]);
        $school = $stmt->fetch();
        return $school ?: null;
    } catch (PDOException $e) {
        // Pre-migration installs do not have the subdomain column yet.
        return null;
    }
}

function saas_bootstrap_school_context(PDO $pdo): ?array
{
    startSecureSession();
    $subdomain = saas_subdomain_from_host();
    if ($subdomain === null) {
        return null;
    }

    $school = saas_school_by_subdomain($pdo, $subdomain);
    if (!$school) {
        unset($_SESSION['school_subdomain'], $_SESSION['school_context_id']);
        return null;
    }

    $_SESSION['school_subdomain'] = $subdomain;
    $_SESSION['school_context_id'] = (int)$school['id'];
    return $school;
}

function saas_current_school_context(PDO $pdo): ?array
{
    if (empty($_SESSION['school_context_id'])) {
        return null;
    }
    return school_by_id($pdo, (int)$_SESSION['school_context_id']);
}

function saas_school_url(array $school, string $path = ''): string
{
    $path = ltrim($path, '/');
    $subdomain = strtolower(trim((string)($school['subdomain'] ?? '')));
    $domain = defined('APP_DOMAIN') ? trim((string)APP_DOMAIN) : '';

    if ($subdomain !== '' && $domain !== '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . $subdomain . '.' . $domain . ($path !== '' ? '/' . $path : '/');
    }

    return getBasePath() . $path;
}

function saas_school_public_url(array $school): string
{
    return saas_school_url($school, 'public/school.php?code=' . urlencode((string)$school['school_code']));
}

function saas_school_login_url(array $school): string
{
    $subdomain = strtolower(trim((string)($school['subdomain'] ?? '')));
    $domain = defined('APP_DOMAIN') ? trim((string)APP_DOMAIN) : '';
    if ($subdomain !== '' && $domain !== '') {
        return saas_school_url($school, 'auth/login.php');
    }
    return getBasePath() . 'auth/login.php?school=' . urlencode($subdomain !== '' ? $subdomain : (string)$school['school_code']);
}

/* -------------------------------------------------------------------------
 * Trials & access
 * ---------------------------------------------------------------------- */

function saas_school_access_state(array $school, ?PDO $pdo = null): array
{
    if (function_exists('school_session_access_state')) {
        $state = school_session_access_state($school, null, $pdo);
        $reasonMap = [
            'school_suspended' => 'suspended',
            'school_inactive' => 'disabled',
            'trial_expired' => 'trial_expired',
            'subscription_inactive' => 'subscription_inactive',
        ];
        return [
            'allowed' => (bool)$state['allowed'],
            'reason' => $reasonMap[$state['reason']] ?? (string)$state['reason'],
        ];
    }

    $status = strtolower((string)($school['status'] ?? 'active'));
    if ($status === 'suspended') return ['allowed' => false, 'reason' => 'suspended'];
    if ((int)($school['is_active'] ?? 1) !== 1) return ['allowed' => false, 'reason' => 'disabled'];
    if ($status === 'trial') {
        $ends = $school['trial_ends_at'] ?? null;
        if (!empty($ends) && strtotime((string)$ends) !== false && strtotime((string)$ends) < time()) {
            return ['allowed' => false, 'reason' => 'trial_expired'];
        }
    }
    return ['allowed' => true, 'reason' => ''];
}

function saas_school_is_accessible(array $school, ?PDO $pdo = null): bool
{
    return (bool)saas_school_access_state($school, $pdo)['allowed'];
}

function saas_access_message(string $reason): string
{
    return match ($reason) {
        'suspended'     => 'This school portal has been suspended. Please contact the platform administrator.',
        'trial_expired' => 'This school trial has expired. Please contact the platform administrator to activate a subscription.',
        'disabled'      => 'This school portal is currently disabled. Please contact the platform administrator.',
        default         => 'This school portal is not available right now.',
    };
}

/* -------------------------------------------------------------------------
 * School registration
 * ---------------------------------------------------------------------- */

function saas_generate_school_code(PDO $pdo, string $subdomain): string
{
    $base = strtoupper(preg_replace('/[^a-z0-9]/i', '', $subdomain));
    if ($base === '') {
        $base = 'SCHOOL';
    }
    if (strlen($base) < 3) {
        $base = 'SCH' . $base;
    }
    $base = substr($base, 0, 20);

    $code = $base;
    $suffix = 1;
    $check = $pdo->prepare("SELECT id FROM schools WHERE school_code = ? LIMIT 1");
    while (true) {
        $check->execute([$code]);
        if (!$check->fetchColumn()) {
            return $code;
        }
        $tail = (string)$suffix++;
        $code = substr($base, 0, 20 - strlen($tail)) . $tail;
    }
}

/**
 * Create a school, its school_admin account, settings and a 30-day trial
 * subscription in a single transaction.
 *
 * @throws InvalidArgumentException on validation failure
 * @throws Throwable on database failure
 */
function saas_register_school(PDO $pdo, array $input): array
{
    $schoolName = trim((string)($input['school_name'] ?? ''));
    $subdomain  = strtolower(trim((string)($input['subdomain'] ?? '')));
    $fullName   = trim((string)($input['full_name'] ?? ''));
    $email      = strtolower(trim((string)($input['email'] ?? '')));
    $phone      = trim((string)($input['phone'] ?? ''));
    $address    = trim((string)($input['address'] ?? ''));
    $password   = (string)($input['password'] ?? '');
    $plan       = strtolower(trim((string)($input['plan'] ?? 'pro')));

    if (!saas_valid_plan($plan)) {
        $plan = 'pro';
    }

    $errors = [];
    if (mb_strlen($schoolName) < 2 || mb_strlen($schoolName) > 150) {
        $errors[] = 'School name must be between 2 and 150 characters.';
    }
    if (!saas_validate_subdomain($subdomain)) {
        $errors[] = 'Choose a subdomain with 3–50 lowercase letters, numbers or hyphens. Reserved names such as www and admin are not allowed.';
    }
    if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
        $errors[] = 'Enter the administrator’s full name.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid administrator email address.';
    }
    $passwordErrors = validatePasswordStrength($password);
    foreach ($passwordErrors as $passwordError) {
        $errors[] = $passwordError;
    }
    if (!empty($input['confirm_password']) && !hash_equals($password, (string)$input['confirm_password'])) {
        $errors[] = 'Password confirmation does not match.';
    }
    if ($errors) {
        throw new InvalidArgumentException(implode(' ', $errors));
    }

    $stmt = $pdo->prepare("SELECT id FROM schools WHERE subdomain = ? LIMIT 1");
    $stmt->execute([$subdomain]);
    if ($stmt->fetchColumn()) {
        throw new InvalidArgumentException('That subdomain is already taken. Please choose another one.');
    }

    $stmt = $pdo->prepare("SELECT id FROM schools WHERE school_email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn()) {
        throw new InvalidArgumentException('A school with that email address already exists.');
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn()) {
        throw new InvalidArgumentException('An account with that email address already exists. Please sign in instead.');
    }

    $schoolCode = saas_generate_school_code($pdo, $subdomain);
    $trialEnds  = date('Y-m-d H:i:s', strtotime('+' . SAAS_TRIAL_DAYS . ' days'));
    $legacyPlan = saas_legacy_plan($plan);
    $ip         = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua         = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 255) : null;

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO schools
                (school_name, school_code, subdomain, school_email, school_phone, school_address,
                 owner_name, status, plan, trial_ends_at, is_active, subscription_plan)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'trial', ?, ?, 1, ?)
        ");
        $stmt->execute([
            $schoolName, $schoolCode, $subdomain, $email, $phone ?: null, $address ?: null,
            $fullName, $plan, $trialEnds, $legacyPlan,
        ]);
        $schoolId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare("
            INSERT INTO school_settings (school_id, public_page_title, public_page_description, allow_parent_auto_login, session_timeout_minutes)
            VALUES (?, ?, ?, 1, 30)
            ON DUPLICATE KEY UPDATE public_page_title = VALUES(public_page_title), public_page_description = VALUES(public_page_description)
        ");
        $stmt->execute([
            $schoolId,
            $schoolName . ' — Official Portal',
            'Welcome to ' . $schoolName . '. Explore our events, achievements and academic programs.',
        ]);

        $stmt = $pdo->prepare("
            INSERT INTO users (school_id, full_name, email, password, role, is_active)
            VALUES (?, ?, ?, ?, 'school_admin', 1)
        ");
        $stmt->execute([$schoolId, $fullName, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $userId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare("
            INSERT INTO school_subscriptions
                (school_id, plan, price, billing_cycle, start_date, end_date, status, payment_method, transaction_id)
            VALUES (?, ?, 0, 'monthly', CURDATE(), DATE(?), 'active', 'trial', NULL)
        ");
        $stmt->execute([$schoolId, $plan, $trialEnds]);

        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (school_id, user_id, action, entity_type, entity_id, description, ip_address, user_agent)
            VALUES (?, ?, 'school_register', 'school', ?, ?, ?, ?)
        ");
        $stmt->execute([
            $schoolId, $userId, $schoolId,
            'School registered with a 30-day ' . saas_plan_label($plan) . ' trial.',
            $ip, $ua,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return [
        'school_id'     => $schoolId,
        'user_id'       => $userId,
        'school_code'   => $schoolCode,
        'subdomain'     => $subdomain,
        'plan'          => $plan,
        'trial_ends_at' => $trialEnds,
    ];
}

/* -------------------------------------------------------------------------
 * Subscriptions
 * ---------------------------------------------------------------------- */

function saas_create_subscription(PDO $pdo, int $schoolId, array $data): int
{
    $school = school_by_id($pdo, $schoolId);
    if (!$school) {
        throw new InvalidArgumentException('School not found.');
    }

    $plan = strtolower(trim((string)($data['plan'] ?? 'basic')));
    if (!saas_valid_plan($plan)) {
        throw new InvalidArgumentException('Choose a valid subscription plan.');
    }

    $rawPrice = $data['price'] ?? '';
    $price = ($rawPrice === '') ? saas_plan_price($plan) : (is_numeric($rawPrice) ? (float)$rawPrice : -1);
    if ($price < 0 || $price > 99999999.99 || abs($price - round($price, 2)) > 0.00001) {
        throw new InvalidArgumentException('Subscription price must be non-negative with no more than two decimal places.');
    }

    $billing = strtolower(trim((string)($data['billing_cycle'] ?? 'monthly')));
    if (!in_array($billing, ['monthly', 'yearly'], true)) {
        throw new InvalidArgumentException('Choose a valid billing cycle.');
    }

    $startInput = trim((string)($data['start_date'] ?? ''));
    if ($startInput === '') {
        $start = date('Y-m-d');
    } elseif (isValidIsoDate($startInput)) {
        $start = $startInput;
    } else {
        throw new InvalidArgumentException('Subscription start date is invalid.');
    }

    $endInput = trim((string)($data['end_date'] ?? ''));
    if ($endInput === '') {
        $end = date('Y-m-d', strtotime($start . ($billing === 'yearly' ? ' +1 year' : ' +1 month')));
    } elseif (isValidIsoDate($endInput)) {
        $end = $endInput;
    } else {
        throw new InvalidArgumentException('Subscription end date is invalid.');
    }
    if ($end < $start) {
        throw new InvalidArgumentException('Subscription end date cannot be before the start date.');
    }

    $status = strtolower(trim((string)($data['status'] ?? 'active')));
    if (!in_array($status, ['active', 'cancelled', 'expired'], true)) {
        throw new InvalidArgumentException('Choose a valid subscription status.');
    }

    $payment = trim((string)($data['payment_method'] ?? ''));
    $transaction = trim((string)($data['transaction_id'] ?? ''));
    if (mb_strlen($payment) > 50 || mb_strlen($transaction) > 100) {
        throw new InvalidArgumentException('Payment method or transaction reference is too long.');
    }

    try {
        $pdo->beginTransaction();

        if ($status === 'active') {
            $overlapStmt = $pdo->prepare(
                "SELECT id, price, payment_method
                 FROM school_subscriptions
                 WHERE school_id = ? AND status = 'active'
                   AND start_date <= ? AND end_date >= ?
                 FOR UPDATE"
            );
            $overlapStmt->execute([$schoolId, $end, $start]);
            foreach ($overlapStmt->fetchAll(PDO::FETCH_ASSOC) as $existingSubscription) {
                // A paid upgrade replaces the current free trial. Never overlap
                // two paid periods, which would inflate MRR and confuse access.
                if ((float)$existingSubscription['price'] <= 0
                    || strtolower((string)$existingSubscription['payment_method']) === 'trial') {
                    $pdo->prepare("UPDATE school_subscriptions SET status = 'cancelled' WHERE id = ?")
                        ->execute([(int)$existingSubscription['id']]);
                } else {
                    throw new InvalidArgumentException(
                        'An active paid subscription already covers these dates. Cancel it or choose a non-overlapping start date.'
                    );
                }
            }
        }

        $stmt = $pdo->prepare(
            "INSERT INTO school_subscriptions
                (school_id, plan, price, billing_cycle, start_date, end_date, status, payment_method, transaction_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $schoolId, $plan, $price, $billing, $start, $end, $status,
            $payment !== '' ? $payment : null,
            $transaction !== '' ? $transaction : null,
        ]);
        $subscriptionId = (int)$pdo->lastInsertId();

        if ($status === 'active') {
            $legacy = saas_legacy_plan($plan);
            $stmt = $pdo->prepare(
                "UPDATE schools
                 SET plan = ?, status = 'active', trial_ends_at = NULL, is_active = 1, subscription_plan = ?
                 WHERE id = ?"
            );
            $stmt->execute([$plan, $legacy, $schoolId]);
        }

        $pdo->commit();
        return $subscriptionId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function saas_active_subscription(PDO $pdo, int $schoolId): ?array
{
    $stmt = $pdo->prepare("
        SELECT * FROM school_subscriptions
        WHERE school_id = ? AND status = 'active' AND start_date <= CURDATE() AND end_date >= CURDATE()
        ORDER BY end_date DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute([$schoolId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function saas_monthly_recurring_revenue(PDO $pdo): float
{
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(
            CASE WHEN billing_cycle = 'yearly' THEN price / 12 ELSE price END
        ), 0)
        FROM school_subscriptions
        WHERE status = 'active' AND start_date <= CURDATE() AND end_date >= CURDATE()
    ");
    return (float)$stmt->fetchColumn();
}

/* -------------------------------------------------------------------------
 * Public contact intake
 * ---------------------------------------------------------------------- */

function saas_contact_store(PDO $pdo, array $data): int
{
    $name    = trim((string)($data['name'] ?? ''));
    $email   = strtolower(trim((string)($data['email'] ?? '')));
    $subject = trim((string)($data['subject'] ?? 'General enquiry'));
    $message = trim((string)($data['message'] ?? ''));

    $errors = [];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors[] = 'Enter your name.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (mb_strlen($subject) < 2 || mb_strlen($subject) > 150) {
        $subject = 'General enquiry';
    }
    if (mb_strlen($message) < 10 || mb_strlen($message) > 5000) {
        $errors[] = 'Your message must be between 10 and 5000 characters.';
    }
    if ($errors) {
        throw new InvalidArgumentException(implode(' ', $errors));
    }

    $schoolId = isset($data['school_id']) && (int)$data['school_id'] > 0 ? (int)$data['school_id'] : null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 255) : null;

    $stmt = $pdo->prepare("
        INSERT INTO contact_messages (school_id, name, email, subject, message, ip_address, user_agent, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'new')
    ");
    $stmt->execute([$schoolId, $name, $email, $subject, $message, $ip, $ua]);
    return (int)$pdo->lastInsertId();
}
