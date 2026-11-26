# KiloCode Audit Follow-up — 2026-11-26

Branch: `repair/school-saas-ci-fixes-20261126`

## Changes pushed in this follow-up

- Converted AJAX task-list deletion to a POST form carrying the CSRF token. The server-side delete endpoint already requires POST.
- Enforced linked-parent/student authorization before a parent can open task details. Teacher task details are limited to tasks they created or courses they teach.
- Added `parent/tasks.php`, a school-scoped task list restricted to children linked through `parent_student`, and corrected the parent dashboard's View All destination.
- Added parent-to-child linking to the admin create-user flow. The selected student is validated against the selected school before the relation is inserted in the same transaction as the parent account.
- Restricted assessment mark entry for teachers to assessments belonging to courses assigned to that teacher.
- Kept parent task detail pages read-only by hiding the status mutation form from parent accounts.
- Wired the dashboard search field to filter dashboard cards and table rows.
- Added migration `0014_nullable_school_contact_fields.sql` to allow optional `schools.school_email` and `schools.owner_name` values without dropping data.
- Added a Content-Security-Policy response header in `.htaccess`.

## Findings already present before this follow-up

The checked branch already contained:
- `consumeFlashMessage()` in `includes/functions.php`.
- A migration adding `Cancelled` to task status, including legacy enum preservation.
- Last-active-platform-super-admin and last-active-school-admin deactivation guards.
- Teacher-to-student ownership checks in `students/show.php`.
- Announcement `target_roles` filtering in `notes/index.php`.
- Password-strength validation in `admin/edit_user.php`.
- Deterministic test fixtures loaded by `tests/run_tests.php`; CI provisions MySQL and runs migrations before tests.
- Role dashboard aliases redirecting to the central role-aware `dashboard.php`; they are redirects, not standalone dashboard implementations.

## Verification limits

- The current execution container has PHP but no PDO drivers (including `pdo_mysql`), so the database-backed suite cannot run here. A local run of `php tests/run_tests.php` stops with `could not find driver`.
- The branch has no GitHub Actions run attached to the latest follow-up commit. Do not interpret that as a green CI result.
- PHP lint and authenticated browser checks for the newly pushed remote versions have not been executed in this container.
- No production database or live website was modified.

## Still outstanding from the audit's missing-feature list

These are not claimed complete: school deletion/archive workflow; full subscription edit/renew lifecycle verification; contact-message reply/archive workflow; assessment edit/delete; student assignment submissions and secure attachments; general document uploads and school logo upload; notification inbox; online fee gateway integration; teacher leave request UX verification; timetable UI completion; batch/subject allocation UX verification; broader exports; sitemap; and full role-by-role browser regression.

## Delivery status

This follow-up closes several high-risk authorization and workflow gaps, but it does **not** establish 100% feature completion or production readiness. The remaining items require implementation and database/browser verification before release.
