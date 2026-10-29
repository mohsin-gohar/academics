<?php
/**
 * Per-School Public Events Calendar
 * URL: /public/school_events.php?code=SCH001
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/saas.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';

$code = strtoupper(trim($_GET['code'] ?? ''));
$school = $code !== '' ? school_by_code($pdo, $code, false) : null;
if (!$school) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Not found</title><div style="font-family:system-ui;padding:60px;text-align:center"><h2>School not found</h2><a href="index.php">Back to directory</a></div>';
    exit;
}
$accessState = saas_school_access_state($school);
if (!$accessState['allowed']) {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><title>School portal unavailable</title><div style="font-family:system-ui;padding:60px;text-align:center;max-width:560px;margin:0 auto"><h2>School portal unavailable</h2><p style="color:#64748b">' . e(saas_access_message($accessState['reason'])) . '</p><a href="index.php">Back to directory</a></div>';
    exit;
}
$primary = tenant_safe_color($school['primary_color'] ?? null, '#1d3557');
$secondary = tenant_safe_color($school['secondary_color'] ?? null, '#f4b64c');

$tab = ($_GET['view'] ?? 'upcoming') === 'past' ? 'past' : 'upcoming';
$sql = $tab === 'past'
    ? "SELECT * FROM school_events WHERE school_id = ? AND status <> 'Cancelled' AND event_date < CURDATE() ORDER BY event_date DESC LIMIT 60"
    : "SELECT * FROM school_events WHERE school_id = ? AND status <> 'Cancelled' AND event_date >= CURDATE() ORDER BY event_date ASC LIMIT 60";
$stmt = $pdo->prepare($sql);
$stmt->execute([$school['id']]);
$events = $stmt->fetchAll();

$grouped = [];
foreach ($events as $ev) $grouped[date('F Y', strtotime($ev['event_date']))][] = $ev;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Events — <?php echo e($school['school_name']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{--p:<?php echo $primary; ?>;--s:<?php echo $secondary; ?>;--ink:#0f172a;--muted:#64748b;--line:#e2e8f0;--canvas:#f6f8fb}
body{font-family:'Inter',system-ui,sans-serif;background:var(--canvas);color:var(--ink);margin:0}
h1,h2,h3,.brand{font-family:'Plus Jakarta Sans';letter-spacing:-.02em}
.nav{position:sticky;top:0;z-index:20;background:rgba(255,255,255,.88);backdrop-filter:blur(12px);border-bottom:1px solid var(--line)}
.nav .wrap{max-width:1040px;margin:0 auto;padding:14px 20px;display:flex;align-items:center;gap:12px}
.mark{width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,var(--p),var(--s));color:#fff;display:grid;place-items:center;font-weight:800}
.name{font-weight:800;font-size:.98rem;line-height:1.1}
.sub{font-size:.66rem;letter-spacing:.14em;text-transform:uppercase;color:var(--muted);font-weight:700}
.back{margin-left:auto;text-decoration:none;color:var(--ink);border:1px solid var(--line);background:#fff;border-radius:999px;padding:9px 16px;font-size:.83rem;font-weight:600}
.hero{background:radial-gradient(700px 300px at 90% -10%,color-mix(in srgb,var(--s) 24%,transparent),transparent 65%),#fff;border-bottom:1px solid var(--line)}
.hero .wrap{max-width:1040px;margin:0 auto;padding:48px 20px 36px}
.hero h1{font-size:clamp(1.8rem,4vw,2.6rem);font-weight:800;margin:0 0 8px}
.hero p{color:var(--muted);margin:0}
.tabs{display:flex;gap:8px;margin-top:22px}
.tab{text-decoration:none;border-radius:999px;padding:8px 18px;font-size:.85rem;font-weight:600;border:1px solid var(--line);color:var(--ink);background:#fff}
.tab.active{background:var(--p);border-color:var(--p);color:#fff}
.wrap2{max-width:1040px;margin:0 auto;padding:34px 20px 60px}
.month{font-size:.78rem;text-transform:uppercase;letter-spacing:.14em;color:var(--muted);font-weight:800;margin:26px 0 12px}
.ev{background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px;display:flex;gap:16px;margin-bottom:12px;transition:.16s}
.ev:hover{box-shadow:0 14px 32px rgba(15,23,42,.08);transform:translateY(-1px)}
.ev-date{width:58px;flex-shrink:0;border-radius:12px;background:color-mix(in srgb,var(--p) 9%,#fff);color:var(--p);text-align:center;padding:9px 0}
.ev-date b{display:block;font-size:1.3rem;line-height:1;font-family:'Plus Jakarta Sans'}
.ev-date span{font-size:.64rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase}
.ev h3{font-size:1rem;font-weight:700;margin:0 0 2px}
.ev .meta{font-size:.78rem;color:var(--muted)}
.ev .desc{font-size:.86rem;color:#475569;margin-top:6px}
.badge-h{background:#fef3c7;color:#b45309;border-radius:999px;padding:3px 10px;font-size:.68rem;font-weight:700}
.empty{text-align:center;padding:60px 20px;color:var(--muted)}
</style>
</head>
<body>
<nav class="nav"><div class="wrap">
    <div class="mark"><?php echo e(strtoupper(substr(preg_replace('/[^A-Za-z]/','',$school['school_name']),0,2))); ?></div>
    <div><div class="name"><?php echo e($school['school_name']); ?></div><div class="sub"><?php echo e($school['school_code']); ?> · Events</div></div>
    <a class="back" href="school.php?code=<?php echo urlencode($school['school_code']); ?>"><i class="fas fa-arrow-left me-1"></i> School Home</a>
</div></nav>
<header class="hero"><div class="wrap">
    <h1>Events Calendar</h1>
    <p>Academic calendar, holidays, competitions and school functions.</p>
    <div class="tabs">
        <a class="tab <?php echo $tab === 'upcoming' ? 'active' : ''; ?>" href="?code=<?php echo urlencode($school['school_code']); ?>&view=upcoming">Upcoming</a>
        <a class="tab <?php echo $tab === 'past' ? 'active' : ''; ?>" href="?code=<?php echo urlencode($school['school_code']); ?>&view=past">Past Events</a>
    </div>
</div></header>
<main class="wrap2">
<?php foreach ($grouped as $month => $list): ?>
    <div class="month"><?php echo e($month); ?></div>
    <?php foreach ($list as $ev): ?>
    <article class="ev">
        <div class="ev-date"><b><?php echo date('d', strtotime($ev['event_date'])); ?></b><span><?php echo date('M', strtotime($ev['event_date'])); ?></span></div>
        <div>
            <h3><?php echo e($ev['title']); ?> <?php if ($ev['is_holiday']): ?><span class="badge-h">Holiday</span><?php endif; ?></h3>
            <div class="meta"><i class="far fa-clock me-1"></i><?php echo e($ev['event_type']); ?><?php if (!empty($ev['end_date']) && $ev['end_date'] !== $ev['event_date']): ?> · until <?php echo formatDate($ev['end_date']); ?><?php endif; ?></div>
            <?php if (!empty($ev['description'])): ?><div class="desc"><?php echo nl2br(e($ev['description'])); ?></div><?php endif; ?>
        </div>
    </article>
    <?php endforeach; ?>
<?php endforeach; ?>
<?php if (!$events): ?>
    <div class="empty"><i class="far fa-calendar fa-2x mb-3 d-block"></i><p>No <?php echo $tab; ?> events to display.</p></div>
<?php endif; ?>
</main>
</body>
</html>
