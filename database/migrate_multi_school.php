<?php
/**
 * Academics Multi-School Migration Runner
 * ---------------------------------------
 * Idempotent migration that upgrades a single-school Academics install
 * to a multi-tenant (multi-school) architecture WITHOUT dropping data.
 *
 * Usage:  php database/migrate_multi_school.php
 *
 * It is safe to run multiple times.
 */

if (PHP_SAPI !== 'cli') {
    die('This migration must be run from the command line.');
}

require_once __DIR__ . '/../config/db.php';

function q(PDO $pdo, string $sql, array $params = []): PDOStatement {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st;
}

function tableExists(PDO $pdo, string $table): bool {
    $st = q($pdo, "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?", [$table]);
    return (int)$st->fetchColumn() > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    $st = q($pdo, "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?", [$table, $column]);
    return (int)$st->fetchColumn() > 0;
}

function indexExists(PDO $pdo, string $table, string $index): bool {
    $st = q($pdo, "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?", [$table, $index]);
    return (int)$st->fetchColumn() > 0;
}

echo "=== Academics Multi-School Migration ===\n";

/* ------------------------------------------------------------------
 * 1. Core tenant tables
 * ------------------------------------------------------------------ */
$pdo->exec("
CREATE TABLE IF NOT EXISTS schools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_name VARCHAR(255) NOT NULL,
    school_code VARCHAR(50) NOT NULL UNIQUE,
    school_email VARCHAR(255) NULL,
    school_phone VARCHAR(30) NULL,
    school_address TEXT NULL,
    school_logo VARCHAR(255) NULL,
    primary_color VARCHAR(20) NOT NULL DEFAULT '#4f46e5',
    secondary_color VARCHAR(20) NOT NULL DEFAULT '#06b6d4',
    motto VARCHAR(255) NULL,
    website VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    subscription_plan ENUM('free','basic','premium') NOT NULL DEFAULT 'free',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_school_code (school_code),
    INDEX idx_school_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "[ok] schools table\n";

$pdo->exec("
CREATE TABLE IF NOT EXISTS school_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT NOT NULL UNIQUE,
    public_page_title VARCHAR(255) NULL,
    public_page_description TEXT NULL,
    allow_parent_auto_login TINYINT(1) NOT NULL DEFAULT 1,
    session_timeout_minutes INT NOT NULL DEFAULT 30,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_school_settings_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "[ok] school_settings table\n";

$pdo->exec("
CREATE TABLE IF NOT EXISTS parent_student (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT NOT NULL,
    student_id INT NOT NULL,
    school_id INT NOT NULL,
    relationship ENUM('father','mother','guardian') NOT NULL DEFAULT 'guardian',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_parent_student (parent_id, student_id),
    INDEX idx_ps_school (school_id),
    CONSTRAINT fk_ps_parent FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ps_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_ps_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "[ok] parent_student table\n";

$pdo->exec("
CREATE TABLE IF NOT EXISTS user_redis_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    school_id INT NULL,
    email VARCHAR(255) NOT NULL,
    role VARCHAR(30) NOT NULL,
    cache_key VARCHAR(191) NOT NULL UNIQUE,
    token_hash CHAR(64) NULL UNIQUE,
    token_expires_at DATETIME NULL,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_urc_user (user_id),
    INDEX idx_urc_school (school_id),
    CONSTRAINT fk_urc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_urc_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "[ok] user_redis_cache table\n";

/* ------------------------------------------------------------------
 * 2. Widen role enum (keeps legacy 'admin' rows valid)
 * ------------------------------------------------------------------ */
$pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin','super_admin','school_admin','teacher','student','parent') NOT NULL DEFAULT 'student'");
echo "[ok] users.role enum widened\n";

/* ------------------------------------------------------------------
 * 3. Add school_id / tenant columns where missing
 * ------------------------------------------------------------------ */
$columns = [
    'users'           => ['school_id' => "INT NULL AFTER id"],
    'students'        => ['school_id' => "INT NULL AFTER id"],
    'courses'         => ['school_id' => "INT NULL AFTER id"],
    'tasks'           => [
        'school_id'            => "INT NULL AFTER id",
        'created_by_user_id'   => "INT NULL AFTER student_id",
        'assigned_to_user_id'  => "INT NULL AFTER created_by_user_id",
        'visibility'           => "ENUM('school','class','individual') NOT NULL DEFAULT 'school' AFTER assigned_to_user_id",
    ],
    'school_events'   => ['school_id' => "INT NULL AFTER id"],
    'enrollments'     => ['school_id' => "INT NULL AFTER id"],
    'emergency_alerts'=> ['school_id' => "INT NULL AFTER id"],
    'activity_logs'   => ['school_id' => "INT NULL AFTER id"],
];
foreach ($columns as $table => $cols) {
    if (!tableExists($pdo, $table)) { echo "[skip] $table missing\n"; continue; }
    foreach ($cols as $col => $definition) {
        if (!columnExists($pdo, $table, $col)) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $definition");
            echo "[ok] $table.$col added\n";
        }
    }
}

$indexes = [
    'users' => ['idx_users_school' => 'school_id'],
    'students' => ['idx_students_school' => 'school_id'],
    'courses' => ['idx_courses_school' => 'school_id'],
    'tasks' => ['idx_tasks_school' => 'school_id', 'idx_tasks_assigned' => 'assigned_to_user_id'],
    'school_events' => ['idx_events_school' => 'school_id'],
    'enrollments' => ['idx_enroll_school' => 'school_id'],
    'activity_logs' => ['idx_logs_school' => 'school_id'],
];
foreach ($indexes as $table => $idxList) {
    foreach ($idxList as $idx => $col) {
        if (tableExists($pdo, $table) && columnExists($pdo, $table, $col) && !indexExists($pdo, $table, $idx)) {
            $pdo->exec("ALTER TABLE `$table` ADD INDEX `$idx` (`$col`)");
            echo "[ok] index $idx on $table\n";
        }
    }
}

/* ------------------------------------------------------------------
 * 4. Seed schools (idempotent)
 * ------------------------------------------------------------------ */
$schools = [
    [
        'school_name' => 'Academics International School',
        'school_code' => 'SCH001',
        'school_email' => 'info@sch001.edu',
        'school_phone' => '+92 300 111 2233',
        'school_address' => '12-A, Knowledge Avenue, Lahore',
        'primary_color' => '#4f46e5',
        'secondary_color' => '#06b6d4',
        'motto' => 'Learn. Lead. Inspire.',
        'website' => 'https://sch001.example.edu',
        'subscription_plan' => 'premium',
    ],
    [
        'school_name' => 'Beaconhouse Model School',
        'school_code' => 'SCH002',
        'school_email' => 'info@sch002.edu',
        'school_phone' => '+92 300 444 5566',
        'school_address' => '88-B, Garden Town, Karachi',
        'primary_color' => '#0f766e',
        'secondary_color' => '#f59e0b',
        'motto' => 'Excellence in every step',
        'website' => 'https://sch002.example.edu',
        'subscription_plan' => 'basic',
    ],
];
foreach ($schools as $s) {
    $exists = q($pdo, "SELECT id FROM schools WHERE school_code = ?", [$s['school_code']])->fetchColumn();
    if (!$exists) {
        q($pdo, "INSERT INTO schools (school_name, school_code, school_email, school_phone, school_address, primary_color, secondary_color, motto, website, subscription_plan, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,1)",
            [$s['school_name'], $s['school_code'], $s['school_email'], $s['school_phone'], $s['school_address'], $s['primary_color'], $s['secondary_color'], $s['motto'], $s['website'], $s['subscription_plan']]);
        echo "[ok] seeded {$s['school_code']}\n";
    }
}
$school1 = (int)q($pdo, "SELECT id FROM schools WHERE school_code='SCH001'")->fetchColumn();
$school2 = (int)q($pdo, "SELECT id FROM schools WHERE school_code='SCH002'")->fetchColumn();

/* School settings defaults */
foreach ([$school1, $school2] as $sid) {
    q($pdo, "INSERT INTO school_settings (school_id, public_page_title, public_page_description, allow_parent_auto_login, session_timeout_minutes)
             SELECT ?, CONCAT(school_name, ' — Official Portal'), CONCAT('Welcome to ', school_name, '. Explore our events, achievements and academic programs.'), 1, 30
             FROM schools WHERE id = ?
             ON DUPLICATE KEY UPDATE school_id = school_id", [$sid, $sid]);
}
echo "[ok] school settings defaults\n";

/* ------------------------------------------------------------------
 * 5. Backfill legacy data into School 1
 * ------------------------------------------------------------------ */
q($pdo, "UPDATE users SET school_id = ? WHERE school_id IS NULL AND role <> 'admin' AND role <> 'super_admin'", [$school1]);
q($pdo, "UPDATE users SET role = 'super_admin', school_id = NULL WHERE role = 'admin'");
q($pdo, "UPDATE students SET school_id = ? WHERE school_id IS NULL", [$school1]);
q($pdo, "UPDATE courses SET school_id = ? WHERE school_id IS NULL", [$school1]);
q($pdo, "UPDATE tasks SET school_id = ? WHERE school_id IS NULL", [$school1]);
q($pdo, "UPDATE school_events SET school_id = ? WHERE school_id IS NULL", [$school1]);
q($pdo, "UPDATE enrollments e JOIN students s ON s.id = e.student_id SET e.school_id = s.school_id WHERE e.school_id IS NULL");
q($pdo, "UPDATE activity_logs l JOIN users u ON u.id = l.user_id SET l.school_id = u.school_id WHERE l.school_id IS NULL");
q($pdo, "UPDATE emergency_alerts SET school_id = ? WHERE school_id IS NULL", [$school1]);
echo "[ok] legacy rows backfilled to School 1\n";

/* ------------------------------------------------------------------
 * 6. Demo accounts for every role / school
 * ------------------------------------------------------------------ */
function ensureUser(PDO $pdo, array $u): int {
    $st = q($pdo, "SELECT id FROM users WHERE email = ?", [$u['email']]);
    $id = $st->fetchColumn();
    if ($id) {
        // Keep credential + role in sync for demo accounts
        q($pdo, "UPDATE users SET full_name=?, password=?, role=?, school_id=?, is_active=1 WHERE id=?",
            [$u['full_name'], password_hash($u['password'], PASSWORD_DEFAULT), $u['role'], $u['school_id'], $id]);
        return (int)$id;
    }
    q($pdo, "INSERT INTO users (full_name, email, password, role, school_id, is_active) VALUES (?,?,?,?,?,1)",
        [$u['full_name'], $u['email'], password_hash($u['password'], PASSWORD_DEFAULT), $u['role'], $u['school_id']]);
    return (int)$pdo->lastInsertId();
}

$demoUsers = [
    ['full_name' => 'System Administrator', 'email' => 'admin@edusphere.com', 'password' => 'Admin@123', 'role' => 'super_admin', 'school_id' => null],
    ['full_name' => 'Sarah Khan (School Admin)', 'email' => 'schooladmin@sch001.com', 'password' => 'School@123', 'role' => 'school_admin', 'school_id' => $school1],
    ['full_name' => 'Imran Ali (Teacher)', 'email' => 'teacher@sch001.com', 'password' => 'Teacher@123', 'role' => 'teacher', 'school_id' => $school1],
    ['full_name' => 'Ayesha Malik (Student)', 'email' => 'student@sch001.com', 'password' => 'Student@123', 'role' => 'student', 'school_id' => $school1],
    ['full_name' => 'Tariq Malik (Parent)', 'email' => 'parent@sch001.com', 'password' => 'Parent@123', 'role' => 'parent', 'school_id' => $school1],
    ['full_name' => 'Bilal Ahmed (School Admin)', 'email' => 'schooladmin@sch002.com', 'password' => 'School@123', 'role' => 'school_admin', 'school_id' => $school2],
    ['full_name' => 'Nadia Hussain (Teacher)', 'email' => 'teacher@sch002.com', 'password' => 'Teacher@123', 'role' => 'teacher', 'school_id' => $school2],
    ['full_name' => 'Hamza Sheikh (Student)', 'email' => 'student@sch002.com', 'password' => 'Student@123', 'role' => 'student', 'school_id' => $school2],
    ['full_name' => 'Fatima Sheikh (Parent)', 'email' => 'parent@sch002.com', 'password' => 'Parent@123', 'role' => 'parent', 'school_id' => $school2],
    // Legacy demo accounts kept for backward compatibility / test suite
    ['full_name' => 'Demo Teacher', 'email' => 'teacher@edusphere.com', 'password' => 'Teacher@123', 'role' => 'teacher', 'school_id' => $school1],
    ['full_name' => 'Demo Student', 'email' => 'student@edusphere.com', 'password' => 'Student@123', 'role' => 'student', 'school_id' => $school1],
];
$userIds = [];
foreach ($demoUsers as $u) {
    $userIds[$u['email']] = ensureUser($pdo, $u);
}
echo "[ok] demo users ensured (" . count($userIds) . ")\n";

/* ------------------------------------------------------------------
 * 7. Demo student profiles + parent links
 * ------------------------------------------------------------------ */
function ensureStudentProfile(PDO $pdo, int $userId, int $schoolId, string $code, string $name, string $email): int {
    $st = q($pdo, "SELECT id FROM students WHERE user_id = ?", [$userId]);
    $id = $st->fetchColumn();
    if ($id) {
        q($pdo, "UPDATE students SET school_id=?, full_name=?, email=? WHERE id=?", [$schoolId, $name, $email, $id]);
        return (int)$id;
    }
    $num = $code . '-STU-' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    q($pdo, "INSERT INTO students (user_id, school_id, student_number, full_name, email, enrollment_date, status) VALUES (?,?,?,?,?,CURDATE(),'Active')",
        [$userId, $schoolId, $num, $name, $email]);
    return (int)$pdo->lastInsertId();
}

$stu1 = ensureStudentProfile($pdo, $userIds['student@sch001.com'], $school1, 'SCH001', 'Ayesha Malik', 'student@sch001.com');
$stu2 = ensureStudentProfile($pdo, $userIds['student@sch002.com'], $school2, 'SCH002', 'Hamza Sheikh', 'student@sch002.com');
echo "[ok] demo student profiles ($stu1, $stu2)\n";

foreach ([
    [$userIds['parent@sch001.com'], $stu1, $school1, 'father'],
    [$userIds['parent@sch002.com'], $stu2, $school2, 'guardian'],
] as [$pid, $sid, $sch, $rel]) {
    q($pdo, "INSERT INTO parent_student (parent_id, student_id, school_id, relationship) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE relationship = VALUES(relationship)", [$pid, $sid, $sch, $rel]);
}
echo "[ok] parent-student links\n";

/* ------------------------------------------------------------------
 * 8. Demo tasks / events (only if a school has none)
 * ------------------------------------------------------------------ */
function seedTask(PDO $pdo, int $schoolId, int $teacherId, int $studentId, string $title, string $desc, string $priority, string $status, int $dueInDays): void {
    $exists = q($pdo, "SELECT COUNT(*) FROM tasks WHERE school_id = ? AND title = ?", [$schoolId, $title])->fetchColumn();
    if ($exists) return;
    q($pdo, "INSERT INTO tasks (user_id, school_id, created_by_user_id, assigned_to_user_id, student_id, title, description, priority, status, due_date, visibility)
             VALUES (?,?,?,?,?,?,?,?,?,?, 'school')",
        [$teacherId, $schoolId, $teacherId, $studentId, $studentId, $title, $desc, $priority, $status, date('Y-m-d', strtotime("+$dueInDays days"))]);
}
seedTask($pdo, $school1, $userIds['teacher@sch001.com'], $stu1, 'Algebra Worksheet Chapter 4', 'Complete exercises 4.1 to 4.5 and upload scans.', 'High', 'Pending', 5);
seedTask($pdo, $school1, $userIds['teacher@sch001.com'], $stu1, 'Science Lab Report', 'Write a report on the photosynthesis experiment.', 'Medium', 'In Progress', 9);
seedTask($pdo, $school2, $userIds['teacher@sch002.com'], $stu2, 'English Essay — My City', '300 words descriptive essay.', 'Medium', 'Pending', 7);

function seedEvent(PDO $pdo, int $schoolId, int $creatorId, string $title, string $type, string $date, int $holiday, string $status): void {
    $exists = q($pdo, "SELECT COUNT(*) FROM school_events WHERE school_id = ? AND title = ?", [$schoolId, $title])->fetchColumn();
    if ($exists) return;
    q($pdo, "INSERT INTO school_events (school_id, title, event_type, description, event_date, is_holiday, status, created_by)
             VALUES (?,?,?,?,?,?,?,?)",
        [$schoolId, $title, $type, 'Auto-seeded demo event.', $date, $holiday, $status, $creatorId]);
}
seedEvent($pdo, $school1, $userIds['schooladmin@sch001.com'], 'Annual Science Exhibition', 'Function', date('Y-m-d', strtotime('+12 days')), 0, 'Upcoming');
seedEvent($pdo, $school1, $userIds['schooladmin@sch001.com'], 'Founder\'s Day Holiday', 'Holiday', date('Y-m-d', strtotime('+20 days')), 1, 'Upcoming');
seedEvent($pdo, $school2, $userIds['schooladmin@sch002.com'], 'Inter-School Debate', 'Competition', date('Y-m-d', strtotime('+15 days')), 0, 'Upcoming');
echo "[ok] demo tasks & events\n";

/* ------------------------------------------------------------------
 * 9. Link legacy student profile to school 1
 * ------------------------------------------------------------------ */
q($pdo, "UPDATE students SET school_id = ? WHERE school_id IS NULL", [$school1]);

echo "\n=== Migration complete ===\n";
echo "Schools: " . q($pdo, "SELECT COUNT(*) FROM schools")->fetchColumn() . "\n";
echo "Users:   " . q($pdo, "SELECT COUNT(*) FROM users")->fetchColumn() . "\n";
echo "Tenant rows with NULL school_id (expected only super admins):\n";
foreach ($pdo->query("SELECT role, COUNT(*) c FROM users WHERE school_id IS NULL GROUP BY role") as $row) {
    echo "  - {$row['role']}: {$row['c']}\n";
}

/* ------------------------------------------------------------------
 * 10. SaaS layer (subdomains, plans, subscriptions, contact messages)
 * ------------------------------------------------------------------ */
require __DIR__ . '/migrate_saas.php';

/* ------------------------------------------------------------------
 * 11. Feature layer (attendance, password reset)
 * ------------------------------------------------------------------ */
require __DIR__ . '/migrate_features.php';
