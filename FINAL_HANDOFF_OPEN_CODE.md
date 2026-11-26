# Academics — OpenCode / Cline Integration Handoff

## Goal
Complete this existing PHP 8+ / MySQL multi-school school-management SaaS. Preserve existing records and working modules. Do not replace the application with a demo or a new framework.

## Current implementation in this package
- Existing authentication, SaaS/school tenancy, students, teachers, parents, courses, attendance, tasks, assessments/grades, fees, timetable, leave, messages, notes, events, expenses, staff and reports.
- School academic years, classes/grades, sections, subjects, subject-teacher assignments and student enrolment pages.
- Dedicated principal, teacher, accountant, admissions, HR, library, transport and platform super-admin entry points.
- Additive migration files through 0013; school staff roles added without removing legacy roles.
- Library and transport PHP workflows expect their own MySQL tables; inspect code and implement migrations before enabling these modules.

## Mandatory work
1. Inspect every PHP file, SQL schema/migration, JS/CSS asset and test before changing anything.
2. Build an entity/route/role matrix. For every school-owned row enforce `school_id` server-side on read, update, delete, export and AJAX requests. Never trust posted school IDs.
3. Review and reconcile migrations from both a clean install and a copy of the supported legacy schema. Additive, reversible where practical. Never DROP user tables or reset production data.
4. Implement and test specialist role workflows: HR staff directory/attendance/leave/payroll preparation and approval; librarian catalogue/copies/circulation/overdues; transport fleet/routes/stops/assignments/capacity/safety; admissions documents/interviews/decisions/enrolment; receptionist visitor/enquiry workflow; principal approval and report-card publication.
5. Finish exam lifecycle, report cards, parent communication, finance receipts/partial payments/discount approval/refund controls, and school configuration (academic calendar, grading, fee heads, periods, optional modules).
6. Remove Aptech-only CV, jobs, placement/ProConnect and institute-specific competitive gamification. Do not delete unrelated school records.
7. Use integer minor units for persisted money. Add CSRF, output escaping, prepared SQL, rate limits, secure upload checks and audited privileged actions.
8. Run PHP lint, unit/integration tests, fresh and legacy migration tests, and real browser end-to-end workflows for each role at mobile/tablet/desktop widths. Test direct URL and AJAX authorization denials.
9. Fix every discovered defect, rerun all checks, and provide evidence with exact commands/results. Do not claim 100% or production-ready if any gate is blocked.

## Environment constraints observed in ChatGPT working environment
PHP CLI is available, but PDO reports no installed database drivers. Therefore MySQL migrations and database-backed integration tests could not be executed here. Run those in a PHP environment with `pdo_mysql` and a disposable MySQL database, never production.

## Deployment safety
No production website, hosting account, or production database changes are authorized by this handoff. Before deployment: create and verify a restorable backup, review `.env`, configure secrets outside source control, run migrations on staging, smoke-test all roles, then request explicit owner approval for production.

## Delivery requirement
Return one complete source ZIP (exclude `.git`, `.env`, caches, dependencies unless required; include `.env.example`, SQL migrations, tests and docs), plus a concise verification report and a list of anything genuinely blocked by external credentials/services.
