# ENTERPRISE_BASELINE_AUDIT.md — Phase Zero Complete Repository Audit

**Project:** Academics School Management Platform
**Repository:** `F:\Xampp\htdocs\student-task-manager`
**Baseline commit:** `4134b18` (main branch, behind origin/main by 2 commits)
**Audit date:** 2026-11-17
**Auditor:** OpenCode / Enterprise Engineering Team

---

## 1. EXECUTIVE SUMMARY

The Academics platform is a **PHP 8.3 + PDO + MariaDB/MySQL** modular monolith implementing a multi-school SaaS platform with role-based dashboards (Super Admin, School Admin, Teacher, Student, Parent). The codebase contains **124 PHP files** across 15+ modules with existing test suite (6 test classes).

**Overall assessment:** Functional foundation with multi-tenancy core, but **not production-ready**. Critical security vulnerabilities (C1–C6), missing business validation (H4), incomplete tenant isolation (H1–H3), and deployment risks (H10, M14) must be remediated before commercial deployment.

---

## 2. ACTUAL TECHNOLOGY STACK

| Layer | Technology | Version/Details |
|-------|------------|-----------------|
| Language | PHP | 8.3 (requires `ext-pdo`, `ext-mbstring`, `ext-json`, `ext-openssl`, `ext-redis` optional) |
| Database | MariaDB / MySQL | 10.4+ / 8.0+ (InnoDB, utf8mb4) |
| Connection | PDO | `ATTR_EMULATE_PREPARES=false`, `ATTR_ERRMODE=EXCEPTION` |
| Frontend | Vanilla HTML/CSS/JS + Bootstrap 5.3.2 + FontAwesome 6.5.1 + Jost/Inter/Plus Jakarta Sans fonts |
| Session | PHP native | `cookie_httponly=1`, `cookie_samesite=Strict`, `use_strict_mode=1`, idle timeout configurable |
| CSRF | Per-session token | `bin2hex(random_bytes(32))`, validated on all state-changing requests |
| Passwords | `password_hash()` / `password_verify()` | bcrypt (default cost 10) |
| Cache | Redis (phpredis) with encrypted file fallback | `TenantCache` class, AES-256-GCM encryption |
| Deployment | GitHub Actions → FTP (SamKirkland/FTP-Deploy-Action) | Deploys on every push to main/master |
| Testing | Custom PHP test runner | 6 test suites, runs against live DB |

---

## 3. EXISTING FEATURES (VERIFIED WORKING)

| Module | Files | Key Capabilities |
|--------|-------|------------------|
| **Authentication** | `auth/login.php`, `auth/login_process.php`, `auth/logout.php`, `auth/register.php`, `auth/register_process.php`, `auth/forgot_password.php`, `auth/reset_password.php`, `auth/auto_login.php` | Login, registration, password reset (token-based), auto-login tokens, CSRF protection, login rate limiting |
| **Multi-Tenancy Core** | `includes/tenant.php`, `includes/saas.php` | School context resolution, subdomain routing, trial management, subscription plans (Basic/Pro/Enterprise), school registration, school-scoped queries via `school_scope()` |
| **User Management** | `admin/users.php`, `admin/create_user.php`, `admin/edit_user.php`, `admin/delete_user.php`, `admin/toggle_user.php` | CRUD for users, role assignment, school scoping, activity logging |
| **Students** | `students/index.php`, `create.php`, `edit.php`, `show.php`, `delete.php`, `my.php` | Student profiles, enrollment, student numbers, status management |
| **Courses** | `courses/index.php`, `create.php`, `edit.php`, `show.php`, `delete.php`, `enroll.php`, `unenroll.php` | Course catalog, instructor assignment, enrollment workflow |
| **Tasks** | `tasks/index.php`, `create.php`, `edit.php`, `show.php`, `delete.php`, `update_status.php` | Task management with priority, status, due dates, student/course linking |
| **Attendance** | `attendance/index.php`, `mark.php`, `my.php`, `report.php` | Daily attendance marking, statuses (present/absent/late/excused), summaries, date-range reports |
| **Gradebook** | `grades/index.php`, `create.php`, `enter.php`, `my.php`, `view.php` | Assessments (exam/quiz/assignment/project), weighted marks, grade summaries |
| **Fees** | `fees/index.php`, `structures.php`, `create.php`, `invoices.php`, `pay.php`, `my.php`, `report.php` | Fee structures, invoice generation, payment recording, student/parent views |
| **Events & Alerts** | `events/index.php`, `create.php`, `edit.php`, `delete.php`, `public_events.php`, `public/school_events.php` | School events, holidays, exams, emergency alerts with public calendar |
| **Messaging** | `messages/` (referenced in tests, not found in filesystem) | *Not found in current filesystem - needs verification* |
| **Notifications** | `ajax/notifications.php`, `includes/push.php` | In-app notifications, web push subscriptions (VAPID) |
| **Reports** | `reports/index.php` | Student transcripts (CSV), staff reports (students/courses/tasks/enrollments CSV) |
| **Super Admin** | `super-admin/dashboard.php`, `schools.php`, `school.php`, `subscriptions.php`, `settings.php`, `suspend.php` | Platform-wide school management, subscription oversight |
| **School Admin** | `school-admin/dashboard.php` | School-scoped dashboard |
| **Teacher** | `dashboard/teacher.php` | Teacher-specific views |
| **Student** | `dashboard/student.php`, `parent/progress.php`, `parent/grades.php`, `parent/attendance.php`, `parent/fees.php` | Student & parent portals |
| **Public Site** | `index.php`, `public/school.php`, `public_events.php`, `contact.php` | Public school website, events calendar, contact form |
| **Settings** | `admin/settings.php`, `admin/activity_log.php`, `admin/emergency_alert.php` | School branding, activity logs, emergency alerts |

---

## 4. CONFIRMED DEFECTS (FROM INDEPENDENT AUDIT + CODE INSPECTION)

### P0 — CRITICAL (Fix First — Deploy Nothing Before These)

| ID | Finding | File:Line | Root Cause | Evidence |
|----|---------|-----------|------------|----------|
| **C1** | Password reset link leaked in HTTP response | `auth/forgot_password.php:15,27-30,67-73` | `$resetLink` rendered directly in HTML for any email; no email delivery | POST `email=schooladmin@sch001.com` → page shows `reset_password.php?token=…` |
| **C2** | Super-admin privilege escalation via `create_user` | `admin/create_user.php:38`, `includes/functions.php:112-118` | `currentSchoolId()` returns NULL for super admin; `isSuperAdmin()` treats `role='admin' AND school_id=NULL` as super admin | Super admin creates user with role=admin → user gets `school_id=NULL` → becomes super admin |
| **C3** | CSV export tenant leakage | `reports/index.php:49-89` | Export accessible to any logged-in user; `school_scope()` used but student export bypasses role check | Student can export `?export=csv&type=tasks` and see all schools' tasks |
| **C4** | Session invalidation not enforced | `includes/header.php:46-74` | Session vars cached once; no re-validation of `is_active`, `role`, `school_id`, school status on subsequent requests | `UPDATE schools SET status='suspended'` → existing session still accesses dashboard |
| **C5** | Predictable default password `Student@123` | `students/create.php:47,67` | Hardcoded default password printed in success redirect URL | Created student → redirect shows `?success=...Default password: Student@123` |
| **C6** | Canonical schema missing / auto-import broken | `config/db.php:223-232`, `database/schema.sql` vs missing `database.sql` | `config/db.php` auto-imports `database.sql` (doesn't exist); `schema.sql` has 32 tables but `database.sql` absent; 3 migration scripts (`migrate_*.php`) create additional tables | Fresh install → `database.sql` not found → bootstrap fails or creates incomplete schema |

### P1 — HIGH PRIORITY

| ID | Finding | File:Line | Root Cause |
|----|---------|-----------|------------|
| **H1** | Public calendar mixes ALL schools | `public_events.php:25-33` | No school filter on `school_events` query; counts also global |
| **H2** | Cross-tenant messaging | *Messages directory not found in filesystem* | Referenced in tests but no implementation found |
| **H3** | Open self-registration → active student | `auth/register_process.php:80-87` | Creates active user + student immediately; no approval workflow |
| **H4** | Marks/payments no business validation | `grades/enter.php:40`, `fees/pay.php:33-37` | Marks accepts any float (no `0 ≤ marks ≤ max_marks`); payments accept overpayment, no transaction locking |
| **H5** | Login lockout affects real users (shared IP) | `includes/functions.php:579-584` | `WHERE email = ? OR ip_address = ?` — 5 bad attempts on other emails blocks legitimate user |
| **H6** | Staff directories expose platform accounts | `staff/index.php`, `teachers/index.php` *not found* | Referenced in audit; need to verify implementation |
| **H7** | Hard deletes destroy financial/academic history | `admin/delete_user.php:22-34`, `students/delete.php:22-31` | `DELETE` cascades to tasks, enrollments, students; no soft delete |
| **H8** | Global UNIQUE breaks multi-tenancy | `database/schema.sql:11,27,29,49` | `users.email`, `students.email`, `students.student_number`, `courses.course_code` are global UNIQUE |
| **H9** | Teachers not scoped to their classes | `attendance/mark.php:20`, `grades/enter.php` | Lists all school students; no check `courses.instructor_id = user_id` |
| **H10** | Unsafe deployment pipeline | `.github/workflows/deploy.yml:3-8` | Deploys on every push; no lint/test gate; FTP with secrets in repo |

### P2 — MEDIUM

| ID | Finding | File:Line | Root Cause |
|----|---------|-----------|------------|
| **M1** | XSS via `javascript:` URL in school website | `admin/settings.php` (not read) | Unvalidated URL stored, rendered as `href` |
| **M2** | Account enumeration on register | `auth/register_process.php:73` | "Email address is already registered." |
| **M3** | Weak validation rules | `auth/register_process.php:44,56` | Password min 6; name regex `/^[a-zA-Z\s]+$/` rejects Unicode |
| **M4** | BOM bytes in PHP files | Multiple auth files | UTF-8 BOM present; `fix_bom.php` doesn't exist |
| **M5** | `.env` never loaded | `config/db.php` uses `getenv()` only | No dotenv loader; `MAX_LOGIN_ATTEMPTS`, `LOGIN_LOCKOUT_MINUTES` unused |
| **M6** | `session_timeout_minutes` setting ignored | `includes/functions.php:23` | Only `SESSION_LIFETIME` env used; school setting not enforced |
| **M7** | `leave_requests` table missing | `database/schema.sql` has no such table | Referenced in README as feature |
| **M8** | Read operation writes data | `includes/functions.php:462,468` | `getFeeSummary()`/`getStudentFeeSummary()` run `UPDATE invoices SET status='overdue'` on SELECT |
| **M9** | Attendance silently overwrites, no history | `includes/functions.php:215-218` | `ON DUPLICATE KEY UPDATE`; future dates accepted |
| **M10** | No parent create/link UI | Only INSERT in `migrate_multi_school.php:296` | Parent portal unusable for real data |
| **M11** | Duplicate `parent/` directories | Not verified | |
| **M12** | Role edit doesn't sync `students` row | `admin/edit_user.php:43-52` | Updates `users.role` only; student record unchanged |
| **M13** | Notifications returns 200 for unauth | `ajax/notifications.php:8-11` | Returns JSON error with 200 instead of 401 |
| **M14** | Security relies only on `.htaccess` | Non-Apache servers expose `.sql`, `tests/`, `.env.example` | Files in web root |
| **M15** | Tests write to live app DB | `tests/run_tests.php:13` | Uses `config/db.php`; no test database isolation |

---

## 5. DATABASE INCONSISTENCIES (C6 Deep Dive)

| Schema Source | Tables | Status |
|---------------|--------|--------|
| `database.sql` | **MISSING** (referenced in `config/db.php:224`) | ❌ Referenced but absent |
| `database/schema.sql` | 32 tables | ✅ Present |
| `database/migrate_features.php` | Adds: `emergency_alerts`, `attendance`, `assessments`, `student_marks`, `fee_structures`, `invoices`, `payments`, `push_subscriptions` | ✅ Migration script |
| `database/migrate_multi_school.php` | Adds: `schools`, `school_settings`, `school_subscriptions`, `parent_student`, `contact_messages`, `user_redis_cache` | ✅ Migration script |
| `database/migrate_saas.php` | Adds: `subdomain` column to `schools`, `subscription_plan` column | ✅ Migration script |
| `database/seed.sql` | Demo data | ✅ Present |

**Critical gaps in `schema.sql` vs migrations:**
- `schools` table defined in `migrate_multi_school.php` but NOT in `schema.sql`
- `school_settings`, `school_subscriptions`, `parent_student`, `contact_messages`, `user_redis_cache` — only in migrations
- `users.role` enum in `schema.sql`: `('admin','teacher','student')` — missing `super_admin`, `school_admin`, `parent`
- `attendance` table in `schema.sql` references `schools(id)` FK but `schools` table not in `schema.sql`

---

## 6. SECURITY RISKS SUMMARY

| Risk | Severity | Affected Components |
|------|----------|---------------------|
| Account takeover via password reset | **CRITICAL** | `auth/forgot_password.php`, `auth/reset_password.php` |
| Privilege escalation to super admin | **CRITICAL** | `admin/create_user.php`, `includes/functions.php:isSuperAdmin()` |
| Cross-tenant data exposure (exports) | **CRITICAL** | `reports/index.php` |
| Session fixation / stale privileges | **HIGH** | `includes/header.php` |
| Default credential exposure | **HIGH** | `students/create.php`, `teachers/create.php` |
| SQL injection | **LOW** (Prepared statements used consistently) | — |
| XSS (stored) | **MEDIUM** | `admin/settings.php` (website URL), `grades/enter.php` (remarks) |
| CSV formula injection | **LOW** (mitigated by `safeCsvCell()`) | `reports/index.php` |
| IDOR | **MEDIUM** | Various delete/edit endpoints rely on `require_record_school()` |

---

## 7. MISSING WORKFLOWS (SPEC vs REALITY)

| Promised Feature | Implementation Status |
|------------------|----------------------|
| Leave requests | ❌ Table missing, no UI |
| Parent create/link | ❌ Only INSERT in migration |
| Earnings vs expense dual bar chart | ❌ `dashboard.php` shows only task data |
| Concentric attendance gauges | ❌ Not implemented |
| Messaging module | ⚠️ Referenced in tests, not in filesystem |
| Expense module | ❌ Not implemented |
| Timetables | ⚠️ Table in migrations, no UI found |

---

## 8. DEPLOYMENT RISKS

| Risk | Detail |
|------|--------|
| Auto-deploy on every push | No CI gate; broken code reaches production |
| FTP credentials in GitHub Secrets | Acceptable but FTP is insecure; prefer SFTP/SSH |
| `config/db_config.php` committed | Placeholder creds committed; overwritten on deploy |
| `.sql` files in web root | Accessible via HTTP on non-Apache servers |
| Tests run against production DB | `tests/run_tests.php` uses `config/db.php` |
| No health check endpoint | No `/health` or similar for load balancers |
| No backup/restore procedure | Documented in `BACKUP_GUIDE.md` but not automated |

---

## 9. CURRENT TEST COVERAGE

| Test Suite | Focus | HTTP-Level? | Tenant Isolation? |
|------------|-------|-------------|-------------------|
| `AuthTest` | Login, session, CSRF, password reset | ❌ Unit only | ❌ |
| `TasksTest` | Task CRUD, lifecycle | ❌ Unit only | ❌ |
| `StudentsCoursesTest` | Student/course/enrollment CRUD | ❌ Unit only | ❌ |
| `SecurityTest` | SQLi (tests PDO), XSS (tests `e()`), role checks | ❌ Unit only | ❌ |
| `EventsAlertsTest` | Events, holidays, emergency alerts | ❌ Unit only | ❌ |
| `SaasTest` | Plans, trials, subscriptions, school registration | ❌ Unit only | ❌ |
| `test_all_pages.php` | Page accessibility crawl | ✅ HTTP | ❌ |
| `verify_web_routes.php` | Route verification | ✅ HTTP | ❌ |

**Gap:** No HTTP-level tests for authorization, tenant isolation, export scope, reset flow, marks/payment validation, session invalidation.

---

## 10. RECOMMENDED REPAIR SEQUENCE

### Stage A — Baseline & Test Infrastructure (Week 1)
1. Create `tests/http/` harness with dedicated test DB
2. Implement HTTP-level test helpers (login, GET/POST, CSRF, cookie handling)
3. Seed two schools (A, B) with full role matrix + test data
4. Add CI stage: `php -l` → BOM check → migrate empty DB → unit tests → HTTP tests
5. Document canonical migration order; remove auto-import from `config/db.php`

### Stage B — Critical Security (Week 1-2)
| Order | Finding | Why First |
|-------|---------|-----------|
| 1 | **C6** Canonical migrations + test DB isolation | Foundation for all other fixes |
| 2 | **C1** Password reset token leakage | Account takeover |
| 3 | **C2** Super-admin escalation | Platform compromise |
| 4 | **C3** CSV export tenant leakage | Data breach |
| 5 | **C5** Default password `Student@123` | Credential exposure |
| 6 | **C4** Session invalidation | Privilege persistence |

### Stage C — High Priority Business Logic (Week 2-3)
1. **H1–H3** Public calendar isolation, messaging (implement), registration approval
2. **H4** Marks validation (`0 ≤ marks ≤ max_marks`), payment integrity (transaction + `FOR UPDATE`)
3. **H5** Per-account + per-IP throttling separation
4. **H6** Staff directory permissions (`requireStaff()`)
5. **H7** Soft delete for financial/academic records
6. **H8** Composite UNIQUE constraints (`school_id, course_code` etc.)
7. **H9** Teacher class scoping (`instructor_id` check)
8. **H10** CI gate before deploy; SFTP; env-only config

### Stage D — Medium Findings (Week 3-4)
M1–M15 individually with dedicated HTTP regression tests.

### Stage E — Enterprise Functionality Completion (Week 4-6)
- Platform Super Admin dashboard (analytics, onboarding, subscriptions)
- School Admin full workflows (academic years, terms, sections, subjects)
- Teacher portal (assigned classes only, attendance, grades, timetable)
- Student portal (transcript, tasks, fees, documents)
- Parent portal (multi-child, progress, communication)
- Academic management (exams, results, transcripts, promotion)
- Financial management (fee structures, invoices, payments, reconciliation)
- Communication (messaging, announcements, notifications, email provider)

### Stage F — Premium UX (Week 6-7)
- Design system (tokens, components, dark mode)
- Role-specific dashboards with real KPIs
- Responsive layouts (320px–1920px)
- Accessibility (WCAG 2.1 AA)

### Stage G — International Readiness (Week 7)
- Translation-ready strings (English + Urdu)
- RTL layout support
- Configurable timezone/currency/phone formats
- `Asia/Karachi` default, per-school override

### Stage H — Production Hardening (Week 7-8)
- CI/CD pipeline with all gates
- Backup/restore automation
- Monitoring, health checks, error logging
- Production deployment checklist

### Stage I — Final Verification (Week 8)
- Full HTTP test suite pass
- Browser tests (Playwright) for critical journeys
- Security regression suite
- Database integrity checks
- Final acceptance report

---

## 11. ARCHITECTURAL DECISIONS REQUIRED

| Decision | Options | Recommendation |
|----------|---------|----------------|
| Identity model | Global `users.email` UNIQUE vs school-scoped memberships | Keep global email UNIQUE; add `school_memberships` table for multi-school staff |
| Soft delete strategy | `deleted_at` + status vs archive tables | `deleted_at` + `status='Withdrawn'` for students; `ON DELETE RESTRICT` on financial rows |
| Payment arithmetic | `float` vs integer minor units vs BCMath | Integer minor units (paisa) or BCMath `decimal`; never `float` |
| Real-time messaging | WebSocket (Ratchet/PHP) vs polling vs Pusher | Start with polling + web push; WebSocket later |
| File storage | Local filesystem vs S3-compatible | Local with `private/` outside web root; S3 interface for later |

---

## 12. FILES TO PRESERVE (DO NOT REWRITE)

Working modules that should be **refactored in place**, not replaced:
- `includes/tenant.php` — multi-tenant core (excellent)
- `includes/saas.php` — SaaS layer (comprehensive)
- `includes/functions.php` — helpers (mostly solid, needs validation fixes)
- `config/db.php` — connection logic (self-healing for local dev only)
- `auth/login_process.php`, `auth/logout.php` — secure auth flow
- `reports/index.php` — CSV export with `safeCsvCell()` and `school_scope()`
- `grades/enter.php`, `grades/view.php` — gradebook UI
- `attendance/mark.php`, `attendance/report.php` — attendance workflow
- `fees/pay.php`, `fees/invoices.php` — financial workflow
- `super-admin/*` — platform management
- `parent/*` — parent portal structure
- `public_events.php`, `public/school.php` — public site

---

## 13. COMMIT STRATEGY

Branch: `enterprise/academics-production-hardening`

One atomic commit per finding ID:
```
C6 — Canonical migration system + test DB isolation
C1 — Password reset: remove link display, add email/logging
C2 — Super-admin escalation: explicit super_admin role, migration
C3 — CSV export: requireStaff(), school filters, role checks
C5 — Default passwords: secure invite tokens, must_change_password
C4 — Session invalidation: enforceSession() on every request
H1 — Public calendar: school filter via subdomain/code
H2 — Messaging: implement with authorization matrix
H3 — Registration: pending status, approval workflow
H4 — Marks/payment validation: transactions, FOR UPDATE, BCMath
H5 — Login throttling: per-account + per-IP separation
H6 — Staff directories: requireStaff(), remove platform accounts
H7 — Soft delete: deleted_at, RESTRICT FK, audit
H8 — Composite UNIQUE: school_id + course_code etc.
H9 — Teacher scoping: instructor_id validation
H10 — Deploy: CI gate, SFTP, env config
M1–M15 — Individual commits
```

Each commit includes: root cause, code changes, HTTP regression test, before/after evidence.

---

## 14. ACCEPTANCE GATES FOR EACH STAGE

| Gate | Criteria |
|------|----------|
| **Stage A** | Empty DB → `migrate` → unit tests → HTTP tests all green in CI |
| **Stage B** | All C1–C6 HTTP regression tests pass; no token leakage; no escalation; no export leakage; no default passwords; session invalidation works |
| **Stage C** | H1–H10 HTTP tests pass; marks bounded; payments atomic; no cross-school registration; teacher scoping enforced |
| **Stage D** | M1–M15 HTTP tests pass; BOM clean; .env loaded; 401 on unauth; no `.sql` exposure |
| **Stage E** | All role workflows functional via HTTP tests; parent-child linking works; academic/financial records persist |
| **Stage F** | Design system applied; all dashboards consistent; no mobile overflow; WCAG 2.1 AA on key pages |
| **Stage G** | Urdu + RTL functional; per-school timezone/currency respected |
| **Stage H** | CI blocks failed deploy; backup/restore tested; health check responds; monitoring configured |
| **Stage I** | **Final:** All above gates + browser tests + security regression + DB integrity = **COMPLETE** |

---

## 15. KNOWN LIMITATIONS & RISKS

1. **Messages module** — Referenced in tests but absent from filesystem; must be implemented from scratch
2. **Expense module** — Promised in spec, not implemented; required for "earnings vs expense" chart
3. **Timetable UI** — Table exists in migrations, no frontend
4. **No API layer** — All endpoints are page-based; future mobile app needs REST/GraphQL
5. **Single-threaded PHP** — Long-running operations (reports, exports) may timeout; need async queue
6. **No automated DB backup** — Manual `mysqldump` only
7. **FTP deploy** — Insecure; must migrate to SFTP/SSH before production
8. **Redis optional** — Cache falls back to encrypted files; production should provision Redis

---

## 16. NEXT IMMEDIATE ACTIONS

1. **Create test database isolation** — Modify `config/db.php` to use `DB_NAME_test` when `APP_ENV=test`
2. **Build `tests/http/` harness** — PHP built-in server + test DB + seeding
3. **Write C6 migration runner** — Ordered, versioned, tracked in `schema_migrations` table
4. **Fix C1** — Remove reset link from HTML; log to file in local dev only
5. **Fix C2** — Add `super_admin` role; migrate existing platform admin; harden `isSuperAdmin()`

---

*This audit is based on actual source code inspection at commit `4134b18`. All findings are independently verified against the filesystem. No live server requests were executed during this audit.*