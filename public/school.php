<?php
/**
 * Per-School Public Homepage
 * URL: /public/school.php?code=SCH001
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/saas.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';

$code = strtoupper(trim($_GET['code'] ?? ''));
$contextSchool = saas_bootstrap_school_context($pdo);
$school = $code !== '' ? school_by_code($pdo, $code, false) : $contextSchool;

if ($school && $code === '') {
    $stmt = $pdo->prepare("SELECT public_page_title, public_page_description FROM school_settings WHERE school_id = ? LIMIT 1");
    $stmt->execute([(int)$school['id']]);
    $settings = $stmt->fetch() ?: [];
    $school = array_merge($settings, $school);
}

if (!$school) {
    http_response_code(404);
    $page_title = 'School Not Found';
    ?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>School Not Found</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="d-flex align-items-center justify-content-center" style="min-height:100vh;background:#eef2f7;"><div class="text-center"><div class="display-1 fw-bold text-primary">404</div><h4>School not found</h4><p class="text-muted">The school code <strong><?php echo e($code ?: '(empty)'); ?></strong> does not exist or is inactive.</p><a class="btn btn-primary" href="../index.php">Back to Academics</a></div></body></html><?php
    exit;
}

$accessState = saas_school_access_state($school);
if (!$accessState['allowed']) {
    http_response_code(403);
    ?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>School Portal Unavailable</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="d-flex align-items-center justify-content-center" style="min-height:100vh;background:#eef2f7;"><div class="text-center" style="max-width:560px;padding:24px;"><div class="display-5 fw-bold text-warning"><i class="fas fa-lock"></i></div><h4 class="mt-3">School portal unavailable</h4><p class="text-muted"><?php echo e(saas_access_message($accessState['reason'])); ?></p><a class="btn btn-primary" href="../index.php">Back to Academics</a></div></body></html><?php
    exit;
}

$primary = tenant_safe_color($school['primary_color'] ?? null, '#1d3557');
$secondary = tenant_safe_color($school['secondary_color'] ?? null, '#f4b64c');

$stmt = $pdo->prepare("SELECT * FROM school_events WHERE school_id = ? AND status <> 'Cancelled' AND event_date >= CURDATE() ORDER BY event_date ASC LIMIT 6");
$stmt->execute([$school['id']]);
$events = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id = ? AND status = 'Active'");
$stmt->execute([$school['id']]);
$studentCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE school_id = ? AND role = 'teacher' AND is_active = 1");
$stmt->execute([$school['id']]);
$teacherCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE school_id = ?");
$stmt->execute([$school['id']]);
$courseCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT * FROM announcements WHERE school_id = ? AND is_active = 1 AND target_roles = 'all' AND start_date <= NOW() AND (end_date IS NULL OR end_date >= NOW()) ORDER BY priority DESC, created_at DESC LIMIT 3");
$stmt->execute([$school['id']]);
$news = $stmt->fetchAll();

$initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $school['school_name']), 0, 2));
$activeAlert = getActiveEmergencyAlert($pdo, (int)$school['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="../assets/img/academics-mark.svg">
    <title><?php echo e($school['school_name']); ?> — Official Website</title>
    <meta name="description" content="<?php echo e($school['public_page_description'] ?: $school['school_name'] . ' official public portal'); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root { --p: <?php echo $primary; ?>; --s: <?php echo $secondary; ?>; --ink: #0f172a; --muted: #64748b; --canvas: #f6f8fb; --line: #e2e8f0; }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, sans-serif; color: var(--ink); background: var(--canvas); margin: 0; }
        h1,h2,h3,h4,.brand { font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -.02em; }
        .pub-nav { position: sticky; top: 0; z-index: 50; background: rgba(255,255,255,.86); backdrop-filter: blur(14px); border-bottom: 1px solid var(--line); }
        .pub-nav .wrap { max-width: 1140px; margin: 0 auto; padding: 14px 20px; display: flex; align-items: center; gap: 14px; }
        .brand-mark { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; color: #fff; font-weight: 800; background: linear-gradient(135deg, var(--p), var(--s)); box-shadow: 0 8px 20px rgba(15,23,42,.16); flex-shrink: 0; }
        .brand-name { font-size: 1.02rem; font-weight: 800; line-height: 1.15; }
        .brand-sub { font-size: .68rem; text-transform: uppercase; letter-spacing: .14em; color: var(--muted); font-weight: 700; }
        .nav-actions { margin-left: auto; display: flex; gap: 10px; }
        .btn-brand { background: var(--p); color: #fff; border: 0; border-radius: 999px; padding: 10px 20px; font-weight: 600; font-size: .88rem; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-brand:hover { color: #fff; filter: brightness(1.06); }
        .btn-ghost { border: 1px solid var(--line); background: #fff; color: var(--ink); border-radius: 999px; padding: 10px 18px; font-weight: 600; font-size: .88rem; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .hero { position: relative; overflow: hidden; background:
            radial-gradient(900px 420px at 85% -10%, color-mix(in srgb, var(--s) 28%, transparent), transparent 65%),
            radial-gradient(800px 500px at 0% 0%, color-mix(in srgb, var(--p) 20%, transparent), transparent 60%),
            #fff;
            border-bottom: 1px solid var(--line); }
        .hero .wrap { max-width: 1140px; margin: 0 auto; padding: 68px 20px 56px; display: grid; grid-template-columns: 1.1fr .9fr; gap: 40px; align-items: center; }
        .eyebrow { display: inline-flex; align-items: center; gap: 8px; background: color-mix(in srgb, var(--p) 10%, #fff); color: var(--p); border: 1px solid color-mix(in srgb, var(--p) 22%, #fff); padding: 6px 12px; border-radius: 999px; font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; }
        .hero h1 { font-size: clamp(2rem, 4.6vw, 3.35rem); font-weight: 800; line-height: 1.06; margin: 18px 0 14px; }
        .hero p { color: var(--muted); font-size: 1.02rem; line-height: 1.7; max-width: 540px; }
        .hero-card { background: rgba(255,255,255,.75); border: 1px solid var(--line); border-radius: 22px; padding: 22px; box-shadow: 0 24px 60px rgba(15,23,42,.12); backdrop-filter: blur(10px); }
        .stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .stat { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 14px; text-align: center; }
        .stat b { display: block; font-size: 1.45rem; font-family: 'Plus Jakarta Sans'; }
        .stat span { font-size: .7rem; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); font-weight: 700; }
        .section { max-width: 1140px; margin: 0 auto; padding: 56px 20px; }
        .section-head { display: flex; align-items: end; justify-content: space-between; gap: 16px; margin-bottom: 22px; flex-wrap: wrap; }
        .section-head h2 { font-size: 1.6rem; font-weight: 800; margin: 0; }
        .section-head p { color: var(--muted); margin: 4px 0 0; font-size: .9rem; }
        .event-card { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 18px; display: flex; gap: 14px; align-items: flex-start; height: 100%; transition: .18s ease; }
        .event-card:hover { transform: translateY(-2px); box-shadow: 0 16px 36px rgba(15,23,42,.09); }
        .event-date { width: 54px; border-radius: 12px; background: color-mix(in srgb, var(--p) 9%, #fff); color: var(--p); text-align: center; padding: 8px 0; flex-shrink: 0; }
        .event-date b { display: block; font-size: 1.2rem; line-height: 1; }
        .event-date span { font-size: .64rem; text-transform: uppercase; font-weight: 800; letter-spacing: .08em; }
        .info-card { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 22px; height: 100%; }
        .info-card i { color: var(--p); }
        .footer { background: var(--ink); color: #cbd5e1; padding: 34px 20px; }
        .footer .wrap { max-width: 1140px; margin: 0 auto; display: flex; justify-content: space-between; gap: 18px; flex-wrap: wrap; align-items: center; font-size: .84rem; }
        @media (max-width: 860px) { .hero .wrap { grid-template-columns: 1fr; padding-top: 46px; } .stat-grid { grid-template-columns: repeat(3,1fr); } }
        @media (max-width: 520px) { .nav-actions .btn-ghost { display: none; } .hero h1 { font-size: 2rem; } .stat-grid { grid-template-columns: 1fr 1fr; } .brand-name { font-size: .92rem; } }
    </style>
    <link rel="stylesheet" href="../assets/css/edustudy-theme.css">
</head>
<body class="school-public-page">
    <nav class="pub-nav">
        <div class="wrap">
            <div class="brand-mark"><?php echo e($initials); ?></div>
            <div>
                <div class="brand-name"><?php echo e($school['school_name']); ?></div>
                <div class="brand-sub"><?php echo e($school['school_code']); ?> · <?php echo e($school['motto'] ?: 'Official Portal'); ?></div>
            </div>
            <div class="nav-actions">
                <a class="btn-ghost" href="index.php"><i class="fas fa-building-columns"></i> All Schools</a>
                <a class="btn-brand" href="../auth/login.php"><i class="fas fa-right-to-bracket"></i> Portal Login</a>
            </div>
        </div>
    </nav>

    <?php
    $alertManageUrl = null;
    require __DIR__ . '/../includes/school_alert_modal.php';
    ?>

    <header class="hero">
        <div class="wrap">
            <div>
                <span class="eyebrow"><i class="fas fa-star"></i> <?php echo e(ucfirst($school['subscription_plan'])); ?> Member School</span>
                <h1><?php echo e($school['school_name']); ?></h1>
                <p><?php echo e($school['public_page_description'] ?: 'A place where curiosity is encouraged, character is built, and every learner is prepared for a changing world.'); ?></p>
                <div class="d-flex gap-2 flex-wrap mt-4">
                    <a class="btn-brand" href="../auth/login.php"><i class="fas fa-right-to-bracket"></i> Student &amp; Parent Login</a>
                    <a class="btn-ghost" href="school_events.php?code=<?php echo urlencode($school['school_code']); ?>"><i class="far fa-calendar-days"></i> Events Calendar</a>
                </div>
            </div>
            <div class="hero-card">
                <div class="stat-grid mb-3">
                    <div class="stat"><b><?php echo number_format($studentCount); ?></b><span>Students</span></div>
                    <div class="stat"><b><?php echo number_format($teacherCount); ?></b><span>Teachers</span></div>
                    <div class="stat"><b><?php echo number_format($courseCount); ?></b><span>Courses</span></div>
                </div>
                <div class="d-flex align-items-center gap-2 small text-muted"><i class="fas fa-location-dot" style="color:var(--p)"></i><?php echo e($school['school_address'] ?: 'Address not published'); ?></div>
                <?php if ($school['school_phone']): ?><div class="d-flex align-items-center gap-2 small text-muted mt-1"><i class="fas fa-phone" style="color:var(--p)"></i><?php echo e($school['school_phone']); ?></div><?php endif; ?>
                <?php if ($school['school_email']): ?><div class="d-flex align-items-center gap-2 small text-muted mt-1"><i class="fas fa-envelope" style="color:var(--p)"></i><?php echo e($school['school_email']); ?></div><?php endif; ?>
            </div>
        </div>
    </header>

    <?php if ($news): ?>
    <section class="section pb-0">
        <div class="section-head"><div><h2>Latest Announcements</h2><p>News from the school administration</p></div></div>
        <div class="row g-3">
            <?php foreach ($news as $n): ?>
            <div class="col-12 col-md-4">
                <div class="info-card">
                    <span class="badge mb-2" style="background: color-mix(in srgb, var(--p) 12%, #fff); color: var(--p);"><?php echo e(ucfirst($n['priority'])); ?></span>
                    <h6 class="fw-bold mb-1"><?php echo e($n['title']); ?></h6>
                    <p class="small text-muted mb-0"><?php echo nl2br(e(mb_substr($n['content'], 0, 160))); ?><?php echo mb_strlen($n['content']) > 160 ? '…' : ''; ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="section">
        <div class="section-head">
            <div><h2>Upcoming Events</h2><p>What's happening at <?php echo e($school['school_name']); ?></p></div>
            <a class="btn-ghost" href="school_events.php?code=<?php echo urlencode($school['school_code']); ?>">View all <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="row g-3">
            <?php foreach ($events as $ev): ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="event-card">
                    <div class="event-date"><b><?php echo date('d', strtotime($ev['event_date'])); ?></b><span><?php echo date('M', strtotime($ev['event_date'])); ?></span></div>
                    <div>
                        <div class="fw-bold small"><?php echo e($ev['title']); ?></div>
                        <div class="text-muted small mb-1"><?php echo e($ev['event_type']); ?><?php echo ($ev['is_holiday'] && $ev['event_type'] !== 'Holiday') ? ' · Holiday' : ''; ?></div>
                        <div class="text-muted small"><?php echo e(mb_substr($ev['description'] ?? '', 0, 90)); ?><?php echo mb_strlen($ev['description'] ?? '') > 90 ? '…' : ''; ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (!$events): ?>
            <div class="col-12"><div class="info-card text-center py-5"><i class="far fa-calendar-xmark fa-2x mb-2 d-block"></i><p class="text-muted mb-0">No upcoming events scheduled. Please check back soon.</p></div></div>
            <?php endif; ?>
        </div>
    </section>

    <footer class="footer">
        <div class="wrap">
            <div><strong style="color:#fff;"><?php echo e($school['school_name']); ?></strong><br><span class="small"><?php echo e($school['motto'] ?: 'Excellence in education'); ?></span></div>
            <div class="text-md-end small">
                <?php if ($school['website']): ?><a href="<?php echo e($school['website']); ?>" target="_blank" rel="noopener" style="color:#94a3b8;text-decoration:none;"><i class="fas fa-globe me-1"></i><?php echo e($school['website']); ?></a><br><?php endif; ?>
                © <?php echo date('Y'); ?> <?php echo e($school['school_name']); ?>. Powered by
                <a href="../index.php" style="color:#cbd5e1;text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:5px;vertical-align:middle;">
                    <img src="../assets/img/academics-mark.svg" alt="" width="18" height="18" style="border-radius:5px;"> Academics
                </a>.
            </div>
        </div>
    </footer>
</body>
</html>
