<?php
/**
 * Academics Multi-Tenant Core
 * ---------------------------
 * School context, cross-tenant isolation guards, role helpers,
 * Redis-compatible cache and secure auto-login tokens.
 *
 * Redis is used automatically when the phpredis extension + server are
 * available. Otherwise an encrypted file cache under /storage/cache is used,
 * so the platform works on stock XAMPP with zero extra setup.
 */

require_once __DIR__ . '/functions.php';

/* -------------------------------------------------------------------------
 * School context
 * ---------------------------------------------------------------------- */

function tenant_pdo(): ?PDO {
    global $pdo;
    return $pdo instanceof PDO ? $pdo : null;
}

function currentSchoolId(): ?int {
    startSecureSession();
    if (!empty($_SESSION['school_id'])) {
        return (int)$_SESSION['school_id'];
    }
    return null;
}

function currentSchool(?PDO $pdo = null): ?array {
    static $cache = [];
    $pdo = $pdo ?: tenant_pdo();
    $sid = currentSchoolId();
    if (!$pdo || !$sid) return null;
    if (!array_key_exists($sid, $cache)) {
        $st = $pdo->prepare("SELECT s.*, ss.public_page_title, ss.public_page_description, ss.allow_parent_auto_login, ss.session_timeout_minutes
                             FROM schools s LEFT JOIN school_settings ss ON ss.school_id = s.id
                             WHERE s.id = ? LIMIT 1");
        $st->execute([$sid]);
        $cache[$sid] = $st->fetch() ?: null;
    }
    return $cache[$sid];
}

function school_by_id(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare("SELECT * FROM schools WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function school_by_code(PDO $pdo, string $code, bool $activeOnly = true): ?array {
    $sql = "SELECT s.*, ss.public_page_title, ss.public_page_description
            FROM schools s LEFT JOIN school_settings ss ON ss.school_id = s.id
            WHERE s.school_code = ?" . ($activeOnly ? " AND s.is_active = 1" : "") . " LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([$code]);
    return $st->fetch() ?: null;
}

function isParent(): bool {
    return hasRole('parent');
}

function isTenantUser(): bool {
    return isLoggedIn() && !isSuperAdmin() && currentSchoolId() !== null;
}

function requireParent(): void {
    requireAuth();
    if (!isParent()) {
        http_response_code(403);
        header('Location: ' . getBasePath() . 'errors/403.php');
        exit;
    }
}

/** Roles that are scoped to a single school. */
function tenant_scoped_roles(): array {
    return ['school_admin', 'teacher', 'student', 'parent', 'admin'];
}

/**
 * Build a SQL scope fragment for a school-owned column.
 * No signed-in school means no rows. Platform admins do not inherit every tenant.
 *
 * @return array{0:string,1:array}
 */
function school_scope(string $column, ?PDO $pdo = null): array {
    $sid = currentSchoolId();
    if ($sid === null || (int)$sid <= 0) {
        return ['1=0', []];
    }
    return ["$column = ?", [(int)$sid]];
}

/**
 * Append school scope to an existing WHERE array.
 */
function apply_school_scope(array &$where, array &$params, string $column, ?PDO $pdo = null): void {
    [$frag, $bind] = school_scope($column, $pdo);
    $where[] = $frag;
    foreach ($bind as $b) $params[] = $b;
}

/**
 * Stop cross-school access to a single record.
 */
function require_record_school(array $record, string $column = 'school_id'): void {
    $recordSchool = isset($record[$column]) ? (int)$record[$column] : 0;
    if (!can_access_school($recordSchool > 0 ? $recordSchool : null)) {
        http_response_code(403);
        header('Location: ' . getBasePath() . 'errors/403.php');
        exit;
    }
}

/** True when the record belongs to the current actor's school. */
function can_access_school(?int $schoolId): bool {
    $sid = currentSchoolId();
    return $schoolId !== null && $sid !== null && (int)$schoolId === (int)$sid;
}

/** Role-aware post-login destination. */
function tenant_home_for_role(string $role): string {
    switch ($role) {
        case 'super_admin':
            return 'dashboard.php';
        case 'school_admin':
            return 'school-admin/dashboard.php';
        case 'parent':
            return 'parent/dashboard.php';
        default:
            return 'dashboard.php';
    }
}

/** Validate a CSS hex colour from tenant input. */
function tenant_safe_color(?string $color, string $fallback = '#4f46e5'): string
{
    return ($color && preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) ? $color : $fallback;
}

function tenant_role_label(string $role): string {
    $map = [
        'super_admin' => 'Super Admin',
        'school_admin' => 'School Admin',
        'admin' => 'Administrator',
        'teacher' => 'Teacher',
        'student' => 'Student',
        'parent' => 'Parent / Guardian',
    ];
    return $map[$role] ?? ucfirst($role);
}

/* -------------------------------------------------------------------------
 * Redis-compatible cache (Redis when available, encrypted files otherwise)
 * ---------------------------------------------------------------------- */

final class TenantCache
{
    private static ?object $redis = null;
    private static bool $redisChecked = false;
    private static ?string $key = null;

    private static function redis(): ?object
    {
        if (self::$redisChecked) return self::$redis;
        self::$redisChecked = true;
        if (!class_exists('Redis')) return null;
        $host = getenv('REDIS_HOST') ?: '127.0.0.1';
        $port = (int)(getenv('REDIS_PORT') ?: 6379);
        $pass = getenv('REDIS_PASSWORD') ?: null;
        try {
            $r = new Redis();
            if (!@$r->connect($host, $port, 0.4)) return null;
            if ($pass) @$r->auth($pass);
            @$r->select((int)(getenv('REDIS_DB') ?: 0));
            return self::$redis = $r;
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function usingRedis(): bool
    {
        return self::redis() instanceof Redis;
    }

    private static function dir(): string
    {
        $dir = dirname(__DIR__) . '/storage/cache';
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        return $dir;
    }

    private static function encKey(): string
    {
        if (self::$key !== null) return self::$key;
        $file = dirname(__DIR__) . '/storage/.cache_key';
        if (!is_file($file)) {
            @mkdir(dirname($file), 0700, true);
            file_put_contents($file, bin2hex(random_bytes(32)), LOCK_EX);
            @chmod($file, 0600);
        }
        return self::$key = trim((string)file_get_contents($file));
    }

    private static function path(string $key): string
    {
        return self::dir() . '/' . hash('sha256', $key) . '.cache';
    }

    public static function set(string $key, $value, int $ttl = 604800): bool
    {
        $payload = json_encode(['expires' => time() + $ttl, 'value' => $value], JSON_UNESCAPED_UNICODE);
        $r = self::redis();
        if ($r) {
            try { return (bool)$r->setex('edu:' . $key, max(1, $ttl), $payload); }
            catch (Throwable $e) { /* fall through to file */ }
        }
        $keyHex = self::encKey();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($payload, 'aes-256-gcm', hex2bin($keyHex), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) return false;
        $blob = base64_encode($iv . $tag . $cipher);
        return file_put_contents(self::path($key), $blob, LOCK_EX) !== false;
    }

    public static function get(string $key)
    {
        $r = self::redis();
        if ($r) {
            try {
                $raw = $r->get('edu:' . $key);
                if ($raw !== false && $raw !== null) {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded) && ($decoded['expires'] ?? 0) >= time()) {
                        return $decoded['value'] ?? null;
                    }
                    return null;
                }
            } catch (Throwable $e) { /* fall through */ }
        }
        $file = self::path($key);
        if (!is_file($file)) return null;
        $blob = base64_decode((string)file_get_contents($file), true);
        if ($blob === false || strlen($blob) < 29) return null;
        $iv = substr($blob, 0, 12);
        $tag = substr($blob, 12, 16);
        $cipher = substr($blob, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', hex2bin(self::encKey()), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) return null;
        $decoded = json_decode($plain, true);
        if (!is_array($decoded) || ($decoded['expires'] ?? 0) < time()) {
            @unlink($file);
            return null;
        }
        return $decoded['value'] ?? null;
    }

    public static function forget(string $key): void
    {
        $r = self::redis();
        if ($r) {
            try { $r->del('edu:' . $key); } catch (Throwable $e) {}
        }
        @unlink(self::path($key));
    }
}

function cache_set(string $key, $value, int $ttl = 604800): bool { return TenantCache::set($key, $value, $ttl); }
function cache_get(string $key) { return TenantCache::get($key); }
function cache_forget(string $key): void { TenantCache::forget($key); }

/* -------------------------------------------------------------------------
 * Auto-login tokens (single-use, hashed at rest, 7-day expiry)
 * ---------------------------------------------------------------------- */

function tenant_user_cache_upsert(PDO $pdo, array $user): void
{
    $cacheKey = 'user:' . (int)$user['id'];
    $st = $pdo->prepare("INSERT INTO user_redis_cache (user_id, school_id, email, role, cache_key, last_login)
                         VALUES (?,?,?,?,?,NOW())
                         ON DUPLICATE KEY UPDATE school_id = VALUES(school_id), email = VALUES(email),
                                                 role = VALUES(role), last_login = NOW()");
    $st->execute([
        (int)$user['id'],
        $user['school_id'] !== null ? (int)$user['school_id'] : null,
        $user['email'],
        $user['role'],
        $cacheKey,
    ]);

    $meta = [
        'user_id' => (int)$user['id'],
        'school_id' => $user['school_id'] !== null ? (int)$user['school_id'] : null,
        'role' => $user['role'],
        'email' => $user['email'],
        'name' => $user['full_name'],
        'last_login' => date('c'),
    ];
    cache_set($cacheKey, $meta, 604800);
}

/**
 * Create a single-use auto-login token. Only its SHA-256 hash is stored.
 */
function create_auto_login_token(PDO $pdo, int $userId, int $ttl = 604800): ?string
{
    $st = $pdo->prepare("SELECT id FROM user_redis_cache WHERE user_id = ? LIMIT 1");
    $st->execute([$userId]);
    if (!$st->fetchColumn()) return null;

    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $st = $pdo->prepare("UPDATE user_redis_cache SET token_hash = ?, token_expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE user_id = ?");
    $st->execute([$hash, $ttl, $userId]);
    return $token;
}

/**
 * Validate + consume an auto-login token.
 */
function consume_auto_login_token(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $hash = hash('sha256', $token);
    $st = $pdo->prepare("SELECT c.*, u.full_name, u.is_active, u.password
                         FROM user_redis_cache c
                         JOIN users u ON u.id = c.user_id
                         WHERE c.token_hash = ? AND c.token_expires_at > NOW() LIMIT 1");
    $st->execute([$hash]);
    $row = $st->fetch();
    if (!$row || !$row['is_active']) return null;

    // Single use
    $pdo->prepare("UPDATE user_redis_cache SET token_hash = NULL, token_expires_at = NULL WHERE id = ?")
        ->execute([$row['id']]);

    if (!empty($row['school_id'])) {
        $chk = $pdo->prepare("SELECT is_active FROM schools WHERE id = ?");
        $chk->execute([$row['school_id']]);
        if ((int)$chk->fetchColumn() !== 1) return null;
    }
    return $row;
}

/* -------------------------------------------------------------------------
 * Audit logging with tenant awareness
 * ---------------------------------------------------------------------- */

function tenant_log(PDO $pdo, string $action, ?string $entityType = null, ?int $entityId = null, ?string $description = null): void
{
    if (!isLoggedIn()) return;
    try {
        $st = $pdo->prepare("INSERT INTO activity_logs (user_id, school_id, action, entity_type, entity_id, description, ip_address, user_agent)
                             VALUES (?,?,?,?,?,?,?,?)");
        $st->execute([
            (int)$_SESSION['user_id'],
            currentSchoolId(),
            $action,
            $entityType,
            $entityId,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255),
        ]);
    } catch (Throwable $e) {
        error_log('tenant_log failed: ' . $e->getMessage());
    }
}
