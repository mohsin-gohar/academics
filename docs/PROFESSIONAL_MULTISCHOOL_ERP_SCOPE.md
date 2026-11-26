# Academics — Professional Multi-School SaaS ERP Scope

## Product objective

Evolve the existing Academics PHP 8.2 / MySQL application into a production-grade, multi-school SaaS ERP. Each school is an independent tenant with its own users, campuses, academic calendar, records, configuration, files, reports and subscription. The platform operator manages tenants and plans without gaining accidental tenant-context access to school workflows.

This is an implementation and acceptance contract, not a claim that every listed module already exists.

## Product principles

1. Preserve existing working functionality and data. Prefer additive, reversible migrations; never drop or silently rewrite customer data.
2. Every tenant-owned record must have an explicit school ownership path. Every read, write, export, background task and file download must enforce tenant scope server-side.
3. A school may have multiple campuses. Campus-level scope is additive to school scope and must not be confused with tenant isolation.
4. All money is stored and calculated in integer minor units (paisa/cents) or exact DECIMAL values; never use binary floating point for financial decisions.
5. State-changing actions require authorization, CSRF protection, validation, audit events and transaction-safe persistence.
6. UI controls must perform real operations. No placeholder links, fake metrics, demo-only success messages or silent no-op actions in production.
7. Do not claim completion from source inspection alone. CI, MySQL migrations, tests and authenticated browser workflows must provide evidence.

## SaaS platform and tenancy

- Public marketing pages, school registration, tenant onboarding and verified school-domain/subdomain resolution.
- Platform super-admin console: tenant lifecycle, plan catalogue, subscriptions, invoices, payment reconciliation, support inbox, platform audit trail and usage metrics.
- School owner/admin console: school profile, branding, campuses, academic years, policies, user invitations, role assignments, subscription and usage.
- Subscription lifecycle: trial, active, past-due, grace period, suspended, cancelled and expired; explicit renewal/reactivation rules.
- Configurable plans, feature entitlements, student/staff/storage limits, usage metering and safe limit enforcement.
- Tenant-safe exports, backups, retention and deletion workflows; platform support access must be audited and time-bounded.
- Tenant resolution must reject unknown/mismatched hostnames and must not trust a user-supplied school ID as authorization.

## School operations

### Administration
School profile, campuses, buildings, rooms, departments, academic years/terms, calendars, holidays, shifts, class levels, classes, sections, houses, configurable numbering, document templates, policies and school-wide announcements.

### Admissions and student lifecycle
Enquiries, applications, document checklist, admission review, enrollment, student/guardian profiles, emergency contacts, medical alerts, student IDs, transfers, promotion, graduation, withdrawal, alumni and complete student history.

### Academics
Curriculum, subjects, class/section assignment, teacher allocation, lesson plans, syllabus progress, homework, assignments, learning resources, academic calendar, timetable, room allocation and conflict detection.

### Attendance
Student/staff daily and period attendance, bulk marking, late/early departure, excused absence, corrections with approval, attendance devices/imports, leave, thresholds, alerts and monthly/term reports.

### Examinations and outcomes
Exam terms, assessment categories, date sheets, rooms/seating, invigilation, mark entry, moderation, weighted grading, GPA/percentage, grade scales, report cards, transcripts, promotion rules, result publication and correction audit trail.

### Finance
Fee heads, class/student-specific fee plans, recurring schedules, invoices/challans, installments, concessions, scholarships, sibling discounts, fines, waivers, receipts, partial payments, refunds, reversals, payment gateway reconciliation, cash/bank ledgers, expenses, budgets, payroll journals and financial statements. Enforce immutable posted transactions and reversal entries rather than destructive edits.

### HR and payroll
Staff recruitment/onboarding, employment contracts, qualifications, departments, designations, attendance, shifts, leave approvals, payroll periods, salary components, overtime, deductions, advances, payslips, tax configuration, settlements and staff performance.

### Parent/student/staff self-service
Separate dashboards, profile and document access, timetable, homework, attendance, results, fee statements/payments, leave requests, notices, secure messaging, complaints, consent and notification preferences.

### Operational services
Library catalogue/circulation/fines, transport routes/stops/vehicles/drivers, inventory/procurement/suppliers/assets, hostel/boarding, clinic/medical visits, visitor/front-office register, discipline, counselling, events, cafeteria and extracurricular activities. Modules may be enabled by plan and school configuration.

### Communication
In-app notifications, email, SMS and WhatsApp provider adapters; templates, delivery logs, retries, opt-outs, consent, announcements and audience targeting. Provider credentials must be secrets, never committed to source.

### Reporting and analytics
Academic, attendance, admissions, enrollment, fee collection/arrears, cash flow, expenses, payroll, library, transport, inventory and platform SaaS reports. Every report/export must be permission-checked, tenant-scoped, paginated or bounded, and formula-injection-safe.

## Identity, permissions and governance

- Platform roles: platform super admin, platform support and billing operator with least privilege.
- School roles: owner, principal, vice principal, school administrator, academic coordinator, accountant, HR, admission officer, teacher/class teacher, librarian, transport manager, receptionist, nurse, student and parent/guardian.
- Configurable role-permission matrix with explicit deny-by-default behavior and optional campus/class/section scope.
- Parent-to-student relationships must be verified and support multiple guardians and multiple children.
- Invitation lifecycle, MFA for privileged roles, secure password reset, session/device management and login throttling.
- Append-only audit history for privileged actions, approvals, finance, grade changes, impersonation/support access and exports.

## Technical and operational requirements

- PHP 8.2+, MySQL 8+/supported MariaDB, PDO prepared statements and documented runtime requirements.
- Versioned, idempotent migrations tested from both fresh install and supported legacy schemas.
- Referential integrity, tenant-aware uniqueness/indexes, safe concurrency and transaction boundaries.
- Private file storage with randomized names, MIME/content validation, size limits, malware-scanning integration where available, authorization on every download and retention policies.
- Background job/cron strategy for billing, reminders, notification delivery, scheduled reports and backups.
- Structured logs, correlation IDs, health/readiness checks, error reporting, backup verification and restore drills.
- Secrets from environment/hosting secret store; production fails closed when required secrets are missing.
- Accessibility, localization, timezone/currency per school, responsive layouts and performance budgets.
- API versioning, authentication, authorization, rate limits, idempotency for payment/webhook endpoints and API documentation.

## Delivery sequence and exit gates

### Gate 0 — Baseline and risk inventory
Record default branch and active repair branches, enumerate all routes/modules/tables/roles, map tenant-owned entities, inspect every migration, and establish reproducible local/CI commands. Do not merge or deploy during baseline.

### Gate 1 — SaaS and tenant safety
Prove registration isolation, host resolution, school lifecycle, subscription enforcement, tenant-scoped CRUD, exports, uploads and background tasks with adversarial two-school tests. Resolve global-unique constraints that incorrectly prevent legitimate cross-school identifiers while preserving globally unique login identities where intended.

### Gate 2 — Core school ERP
Complete admissions/student lifecycle, campuses, academic setup, classes/sections, teachers/HR, timetable, attendance, exams/gradebook, fees and parent/student portals end-to-end.

### Gate 3 — Operations and integrations
Complete library, transport, inventory, hostel, clinic, front office, communications, payroll and integrations according to explicit feature flags and plan entitlements.

### Gate 4 — Security, resilience and quality
Run static analysis, PHP lint, unit/integration/security tests, migration-upgrade tests, concurrency tests, dependency checks, backup/restore drills, accessibility checks and authenticated browser QA across all roles and breakpoints.

### Gate 5 — Release acceptance
Verify staging deployment, secrets, HTTPS, mail/SMS/WhatsApp providers, cron/queues, storage permissions, backups, monitoring, rollback and production smoke tests. Production deployment requires explicit owner approval.

## Mandatory acceptance evidence

For each module provide: route inventory; role/permission matrix; schema and migration review; CRUD and workflow tests; validation/error-path tests; two-tenant isolation tests; browser test evidence; responsive/accessibility evidence; audit-log evidence; and operational/deployment notes. A module is not complete merely because its page renders.

## Known repository baseline caveats to resolve

- The repository is a plain-PHP application with existing student/task-centric workflows; its README describes a broad set of capabilities, but documentation claims are not treated as verification.
- The current schema and historical migrations require a systematic tenant-ownership and tenant-aware-uniqueness review.
- The open production-hardening PR and the subsequently closed, unmerged SaaS/CI repair PR must be reconciled deliberately; do not discard either branch's work or merge automatically.
- Browser smoke tests and seeded demo personas are useful starting points, but must be checked for route coverage, realistic workflows, cross-tenant attack cases and production-safe credentials.
- Production hosting, email/SMS/WhatsApp delivery, payment processing, backup restoration, scheduled jobs and live deployment remain unverified until exercised in the target environment.

## Definition of done

The system may be called production-ready only when all enabled modules pass their acceptance gates, all critical/high security findings are closed, fresh-install and legacy-upgrade migrations pass on supported MySQL versions, two-tenant isolation tests pass, all role workflows pass in a real browser, CI is green, and staging/production operational controls have been verified. External services that cannot be tested must be explicitly listed as release blockers rather than silently marked complete.
