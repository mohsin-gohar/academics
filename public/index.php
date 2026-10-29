<?php
/**
 * Public School Directory
 * Lists every active school on the platform.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
startSecureSession();
require_once __DIR__ . '/../config/db.php';

$q = trim($_GET['q'] ?? '');
$sql = "SELECT s.*,
        (SELECT COUNT(*) FROM students st WHERE st.school_id = s.id AND st.status='Active') AS student_count,
        (SELECT COUNT(*) FROM users u WHERE u.school_id = s.id AND u.role='teacher' AND u.is_active=1) AS teacher_count
        FROM schools s
        WHERE s.is_active = 1
          AND (s.status IS NULL OR s.status <> 'suspended')
          AND (s.status <> 'trial' OR s.trial_ends_at IS NULL OR s.trial_ends_at >= NOW())";
$params = [];
if ($q !== '') {
    $sql .= " AND (s.school_name LIKE ? OR s.school_code LIKE ? OR s.school_address LIKE ?)";
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
$sql .= " ORDER BY s.school_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$schools = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Schools Directory — Academics</title>
<link rel="icon" type="image/svg+xml" href="../assets/img/academics-mark.svg">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
body{font-family:'Inter',system-ui,sans-serif;background:#f6f8fb;color:#0f172a;margin:0}
h1,h2,h3,.brand{font-family:'Plus Jakarta Sans';letter-spacing:-.02em}
.top{background:radial-gradient(800px 380px at 80% -20%,rgba(79,70,229,.18),transparent 65%),radial-gradient(600px 300px at 0% 0%,rgba(6,182,212,.14),transparent 60%),#fff;border-bottom:1px solid #e2e8f0;padding:56px 20px 42px}
.top .wrap{max-width:1080px;margin:0 auto}
.brandline{display:flex;align-items:center;gap:10px;font-weight:800;color:#4f46e5}
h1{font-size:clamp(1.9rem,4vw,2.8rem);font-weight:800;margin:14px 0 8px}
p.lead{color:#64748b;max-width:620px;margin:0}
.searchbar{margin-top:24px;background:#fff;border:1px solid #e2e8f0;border-radius:999px;padding:6px;display:flex;max-width:520px;box-shadow:0 12px 30px rgba(15,23,42,.06)}
.searchbar input{border:0;outline:0;flex:1;padding:10px 16px;background:transparent;font-size:.95rem}
.searchbar button{border:0;background:#1d3557;color:#fff;border-radius:999px;padding:10px 20px;font-weight:600}
.wrap2{max-width:1080px;margin:0 auto;padding:40px 20px 70px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:20px}
.school-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:22px;transition:.18s ease;height:100%;display:flex;flex-direction:column}
.school-card:hover{transform:translateY(-3px);box-shadow:0 20px 44px rgba(15,23,42,.1)}
.school-head{display:flex;align-items:center;gap:12px;margin-bottom:14px}
.avatar{width:52px;height:52px;border-radius:14px;display:grid;place-items:center;color:#fff;font-weight:800;font-size:1.1rem;flex-shrink:0}
.school-card h3{font-size:1.02rem;font-weight:800;margin:0}
.code{font-size:.7rem;letter-spacing:.12em;text-transform:uppercase;color:#64748b;font-weight:800}
.school-card p{color:#64748b;font-size:.86rem;line-height:1.6;flex:1}
.meta{display:flex;gap:16px;font-size:.78rem;color:#475569;margin:12px 0 16px}
.meta i{color:#4f46e5;margin-right:4px}
.actions{display:flex;gap:8px}
.btn-main{flex:1;background:#1d3557;color:#fff;text-decoration:none;border-radius:10px;padding:10px;font-size:.84rem;font-weight:600;text-align:center}
.btn-main:hover{color:#fff;filter:brightness(1.1)}
.btn-sec{border:1px solid #e2e8f0;color:#0f172a;text-decoration:none;border-radius:10px;padding:10px 14px;font-size:.84rem;font-weight:600;background:#fff}
.empty{text-align:center;padding:70px 20px;color:#64748b}
</style>
<link rel="stylesheet" href="../assets/css/edustudy-theme.css">
</head>
<body>
<header class="top"><div class="wrap">
    <div class="brandline"><img src="../assets/img/academics-mark.svg" alt="" width="28" height="28"> Academics Multi-School Platform</div>
    <h1>Find your school</h1>
    <p class="lead">Every school has its own secure portal, public page and academic workspace. Choose a school below to visit its public site or sign in.</p>
    <form class="searchbar" method="GET">
        <input type="text" name="q" value="<?php echo e($q); ?>" placeholder="Search by school name or code…">
        <button type="submit"><i class="fas fa-search me-1"></i> Search</button>
    </form>
</div></header>
<main class="wrap2">
    <div class="grid">
        <?php foreach ($schools as $s): ?>
        <?php $p = tenant_safe_color($s['primary_color'], '#4f46e5'); $sec = tenant_safe_color($s['secondary_color'], '#06b6d4'); ?>
        <div class="school-card">
            <div class="school-head">
                <div class="avatar" style="background:linear-gradient(135deg,<?php echo e($p); ?>,<?php echo e($sec); ?>);"><?php echo e(strtoupper(substr(preg_replace('/[^A-Za-z]/','',$s['school_name']),0,2))); ?></div>
                <div><h3><?php echo e($s['school_name']); ?></h3><span class="code"><?php echo e($s['school_code']); ?></span></div>
            </div>
            <p><?php echo e($s['motto'] ?: ($s['school_address'] ?: 'A proud member of the Academics network.')); ?></p>
            <div class="meta">
                <span><i class="fas fa-user-graduate"></i><?php echo (int)$s['student_count']; ?> students</span>
                <span><i class="fas fa-chalkboard-teacher"></i><?php echo (int)$s['teacher_count']; ?> teachers</span>
            </div>
            <div class="actions">
                <a class="btn-main" href="school.php?code=<?php echo urlencode($s['school_code']); ?>">Visit School</a>
                <a class="btn-sec" href="school_events.php?code=<?php echo urlencode($s['school_code']); ?>"><i class="far fa-calendar"></i></a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if (!$schools): ?>
    <div class="empty"><i class="fas fa-school fa-3x mb-3 d-block"></i><h4>No schools found</h4><p>Try a different search term.</p></div>
    <?php endif; ?>
</main>
<footer style="border-top:1px solid #e2e8f0;background:#fff;padding:22px 20px;color:#64748b;font-size:.82rem;">
    <div style="max-width:1080px;margin:0 auto;display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;align-items:center;">
        <span>&copy; <?php echo date('Y'); ?> Academics. Every school, one secure platform.</span>
        <span><a href="../index.php" style="color:#4f46e5;text-decoration:none;font-weight:600;">Back to Academics <i class="fas fa-arrow-right"></i></a></span>
    </div>
</footer>
</body>
</html>
