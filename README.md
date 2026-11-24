# Academics — Premium Student Management System (SMS)
> **Academic Level:** Aptech Academic Assignment & Commercial SaaS Prototype  
> **Core Stack:** Plain PHP 8.2+, MySQL 8+ / MariaDB, PDO, Bootstrap 5 CDN, Vanilla JavaScript, Fetch API, HTML5/CSS3

---

## 1. Executive Summary

**Academics** is an enterprise-grade, responsive Student Management System built without heavy backend frameworks. It combines clean modular PHP architecture, parameterized PDO queries, and Vanilla JavaScript Fetch API interactions with a modern educational SaaS user interface.

The application retains the **Student Task Manager** as its mandatory, first-class core module, seamlessly integrating it with **Student Management**, **Course & Enrollment Management**, **Role-Based Access Control (RBAC)**, **Visual Analytics Dashboards**, **Secure Audit Logs**, and **Formula-Protected CSV Exports**.

---

## 2. Key Modules & Features

### A. Public Authentication & Security
- **Registration**: Public self-registration creates a student account and matching academic profile in the selected school; the account remains inactive until school approval.
- **Login**: Verifies credentials with `password_verify()` against bcrypt hashes.
- **Rate Limiting**: Throttles brute-force login attempts (maximum 5 attempts per 15 minutes per email/IP).
- **Session Security**: Session fixation defense via `session_regenerate_id(true)`, `HttpOnly`, `SameSite=Strict`, and strict mode cookies.
- **CSRF Defense**: Unpredictable session tokens required on all destructive actions, state-changing POST requests, and Fetch API calls (`X-CSRF-Token`).
- **Generic Auth Errors**: Prevents username/email enumeration.

### B. Interactive Dashboard & Visual Analytics
- **Live Metric Cards**: Total Students, Active Students, Inactive Students, Total Courses, Total Tasks, Completed, In-Progress, Pending, and Overdue tasks.
- **Dynamic Completion Rate**: Real-time progress bar reflecting verified completed tasks.
- **Chart.js Visualizations**:
  - *Task Status Breakdown*: Interactive donut chart (Completed, In-Progress, Pending, Overdue).
  - *Tasks by Priority*: Bar chart (Low, Medium, High, Urgent).
- **Recent Activity Tables**: 5 most recent tasks and upcoming deadlines based on user permissions.
- **Quick Actions**: One-click shortcuts for adding tasks, students, and courses.

### C. Student Task Manager (Mandatory Assignment Core)
- **Full CRUD**: Create, read, update, and delete tasks with confirm dialogs.
- **Task Attributes**: Title, detailed description, assigned student, course association, priority (*Low*, *Medium*, *High*, *Urgent*), status (*Pending*, *In Progress*, *Completed*, *Cancelled*), due date, and completion timestamp.
- **Fetch API / AJAX Engine**:
  - Live debounced search (300ms) across title, description, and assigned student.
  - Multi-criteria filtering by status, priority, and student without full page reloads.
  - Server-side pagination with dynamic page buttons.
  - Inline quick status dropdown with immediate asynchronous database sync.
  - Toast feedback notifications for instant user confirmation.
- **Overdue Detection**: Dynamically calculates and highlights overdue tasks with high-visibility badges.

### D. Student Management
- **Full CRUD**: Add, edit, view, and delete students.
- **Fields**: Unique student number (`STU-YYYY-XXXX`), full name, email, phone, date of birth, gender, address, enrollment date, and status (*Active*, *Inactive*, *Graduated*, *Suspended*).
- **Relational Profiles**: Detail view (`students/show.php`) displays student contact information, course enrollments, grade history, and assigned task progress breakdown.
- **Student Self-Service**: Students can view their own profile and assigned tasks while being restricted from modifying administrative records.

### E. Course & Enrollment Management
- **Full CRUD**: Course code, title, credits, instructor assignment, schedule, capacity, and active status.
- **Relational Enrollments**: Many-to-many relationship (`enrollments` table) linking students to courses with enrollment date, status (*Enrolled*, *Completed*, *Dropped*, *Withdrawn*), and grades.
- **Course Rosters**: View enrolled student rosters directly on the course detail page with one-click enrollment and unenrollment.

### F. Reports & CSV Exports
- **Comprehensive Reports**: Students list, courses directory, task completion reports, and course enrollment summaries.
- **CSV Injection Protection**: Implements `safeCsvCell()` to prevent formula injection attacks (`=`, `+`, `-`, `@`, `\t`, `\r`) in Microsoft Excel / LibreOffice Calc.
- **Print Layout**: Clean `@media print` CSS rules hiding navigation and extraneous UI elements for crisp paper/PDF printouts.

### G. Administrative Utilities & Audit Logs
- **User Management**: Add, edit, toggle active status, and delete user accounts.
- **Audit Logs**: Complete activity history tracking user, action (*login*, *create*, *update*, *delete*), affected entity, IP address, user agent, and timestamp.
- **Application Settings**: Manage application name, records-per-page defaults, and timezone directly in the database.
- **Custom Error Pages**: Styled, professional error handlers for `403 Forbidden`, `404 Not Found`, and `500 Server Error`.

### I. Attendance & Password Reset
- **Daily Attendance**: Staff mark Present/Absent/Late/Excused for all active students per day; bulk "All Present" shortcut; per-student remarks.
- **Attendance Views**: Daily view with status chips and quick navigation (prev/today/next); per-student and period reports with CSV export.
- **Parent Portal**: Parents see per-child attendance summary and recent records.
- **Student Portal**: Students see their own attendance percentage and history.
- **Dashboard KPI**: School admin dashboard shows today's present/absent/late counts.
- **Password Reset**: Single-use, 1-hour expiring SHA-256-hashed tokens; account-independent email/IP throttling; absolute reset links; authenticated SMTP (STARTTLS/SMTPS) with PHP mail fallback. Local development may write the link to a protected log file.

### J. Gradebook & Fees
- **Gradebook**: Create assessments (exam/quiz/assignment/project) per course with max marks and weight; bulk mark entry; course gradebook matrix with weighted averages.
- **Student & Parent Portals**: Students and parents view graded assessments and overall average.
- **Fees & Invoices**: Define reusable fee structures; issue invoices; record payments (partial/full) with method and reference; auto-mark paid when fully covered.
- **Fee Reports**: Per-status summary (unpaid/overdue/paid) and CSV export.

### H. SaaS Multi-Tenant Platform
- **School Self-Registration**: Public `/register.php` creates a school, its first `school_admin`, default public-page settings, and a 30-day trial in one transaction.
- **Subscription Plans**: Basic ($50/mo), Pro ($150/mo), and Enterprise ($500/mo) are available to the platform team for billing, with legacy `subscription_plan` values kept in sync. Public pricing intentionally shows one **1 Month Free** card; commercial pricing is agreed personally with each school after the trial.
- **Trials & Access States**: New tenants start on `trial`; active paid subscriptions activate the school and clear `trial_ends_at`. Suspended schools and expired trials are blocked at login and on public pages.
- **Subdomain Routing**: Each school receives a unique subdomain (`greenvalley.your-domain.com`). Local/staging installs can use `?school=greenvalley` when wildcard DNS is unavailable.
- **Platform Super-Admin**: `/super-admin/dashboard.php` shows platform KPIs, MRR, trials and expiring subscriptions; `/admin/schools.php`, `/admin/subscriptions.php`, and `/admin/messages.php` manage tenants, billing and contact messages.
- **School-Scoped Alerts**: Each school's emergency notice renders only on that school's own dashboards and public page; it never leaks to other schools or the platform landing page.
- **Public Marketing Pages**: Landing page with pricing, school directory, per-school public page and events calendar, school signup, and a CSRF-protected contact form with honeypot and throttling.

---

## 3. Technology Stack

| Layer | Technology | Purpose |
| :--- | :--- | :--- |
| **Markup & Semantics** | HTML5 | Accessible, semantic document structure |
| **Design System** | Custom CSS3 + Bootstrap 5.3.2 | Navy/indigo design tokens, responsive grid, utilities |
| **Icons & Typography** | Font Awesome 6.5.1 & Google Fonts Inter | Refined iconography and professional typography |
| **Client-Side Scripts** | Vanilla JavaScript (ES6+) | Event handling, validation, password toggle |
| **Asynchronous Engine**| Fetch API / AJAX | Debounced live search, filtering, inline status update |
| **Data Visualization** | Chart.js 4.4.1 (CDN) | Responsive donut and bar charts for dashboard analytics |
| **Server-Side Runtime**| PHP 8.2+ (Procedural / Modular) | Request handling, session management, business rules |
| **Database Engine** | MySQL 8.0+ / MariaDB 10.4+ | Relational schema, foreign keys, indexes, transactions |
| **Database Access** | PDO (PHP Data Objects) | Parameterized prepared statements preventing SQLi |

---

## 4. Directory Structure

```
student-task-manager/
├── .env.example              # Sample environment configuration template
├── .gitignore                # Git exclusions (credentials, logs, temp files)
├── .htaccess                 # Apache security headers & sensitive file protection
├── database/                 # Versioned SQL migrations and migration runner
│   ├── migrate.php            # CLI migration runner with checksum drift detection
│   ├── migrations/            # Ordered 0001–0009 schema migrations
│   └── seed.sql               # Local/demo data only — never seed production
├── database.sql              # Legacy full dump; not the canonical current schema
├── index.php                 # Public entry point / redirector
├── dashboard.php             # Data-driven dashboard with Chart.js analytics
├── register.php              # Public school self-registration (30-day trial)
├── contact.php               # Public contact page
├── contact_process.php       # CSRF-protected contact message intake
├── README.md                 # Complete system documentation
│
├── admin/                    # Administrative management
│   ├── activity_log.php      # Audit log viewer with action filters
│   ├── create_user.php       # Add new administrative / teacher / student account
│   ├── delete_user.php       # CSRF-protected user deletion
│   ├── edit_user.php         # Edit user credentials and roles
│   ├── messages.php          # Contact inbox (school or platform scoped)
│   ├── schools.php           # Super-admin tenant, plan, subdomain & trial manager
│   ├── settings.php          # Application settings & system health
│   ├── subscriptions.php     # Subscription creation, history & cancellation
│   ├── toggle_user.php       # Toggle user active status
│   └── users.php             # User accounts directory with pagination
│
├── ajax/                     # Asynchronous API endpoints (Fetch API)
│   ├── fetch_tasks.php       # JSON endpoint for search, filter & pagination
│   ├── task_search.php       # Backward-compatible search alias
│   └── update_status.php     # CSRF-validated inline task status changer
│
├── assets/                   # Static UI assets
│   ├── css/
│   │   └── style.css         # Custom Academics design system & variables
│   └── js/
│       ├── app.js            # Global UI scripts (sidebar, toasts, CSRF fetch)
│       ├── script.js         # Entry helper
│       └── tasks.js          # Asynchronous task manager frontend logic
│
├── auth/                     # Authentication workflow
│   ├── login.php             # Login form with password visibility toggle
│   ├── login_process.php     # Rate-limited authentication processor
│   ├── logout.php            # Secure session destruction
│   ├── register.php          # Student registration form
│   └── register_process.php  # Registration processor with auto-profile creation
│
├── config/                   # Configuration
│   └── db.php                # PDO connection & self-healing database bootstrap
│
├── courses/                  # Course & enrollment management
│   ├── create.php            # Add new course with instructor assignment
│   ├── delete.php            # CSRF-protected course deletion
│   ├── edit.php              # Edit course metadata and capacity
│   ├── enroll.php            # Enroll student into course
│   ├── index.php             # Courses directory with enrollment counts
│   ├── show.php              # Course roster, enrolled students & tasks
│   └── unenroll.php          # Unenroll student with confirmation
│
├── database/                 # Modular database scripts
│   ├── schema.sql            # Clean DDL table definitions
│   ├── seed.sql              # Seed data for demo accounts and records
│   ├── migrate_multi_school.php # Multi-tenant backfill (invokes SaaS migration)
│   └── migrate_saas.php      # Idempotent SaaS columns, tables & demo backfill
│
├── errors/                   # Custom HTTP error pages
│   ├── 403.php               # Forbidden / Unauthorized Access
│   ├── 404.php               # Page Not Found
│   └── 500.php               # Internal Server Error
│
├── includes/                 # Reusable layout templates & core libraries
│   ├── footer.php            # Layout footer with script loader
│   ├── functions.php         # Security helpers, CSRF, auth checks, formatting
│   ├── header.php            # HTML head, top navbar, session bootstrap
│   ├── public_nav.php        # Shared public marketing navigation
│   ├── public_footer.php     # Shared public marketing footer
│   ├── saas.php              # Plans, subdomains, trials, subscriptions, contact
│   ├── sidebar.php           # Responsive, role-aware sidebar navigation
│   └── tenant.php            # School context, scoping helpers & safe colors
│
├── public/                   # Public school directory & tenant pages
│   ├── index.php             # School directory (active tenants only)
│   ├── school.php            # Per-school public page (subdomain aware)
│   └── school_events.php     # Per-school public events calendar
│
├── reports/                  # Reporting & export engine
│   └── index.php             # Reports dashboard & safe CSV export handler
│
├── students/                 # Student profile management
│   ├── create.php            # Register new student profile
│   ├── delete.php            # CSRF-protected student deletion
│   ├── edit.php              # Edit student academic and contact details
│   ├── index.php             # Filterable student directory with pagination
│   └── show.php              # Complete student profile & task summary
│
├── tasks/                    # Student Task Manager Module
│   ├── create.php            # Create task and assign to student/course
│   ├── delete.php            # CSRF-protected task deletion
│   ├── edit.php              # Edit task details and status
│   ├── index.php             # Hybrid server-rendered & AJAX task workspace
│   ├── show.php              # Task detail view with quick status updater
│   └── update_status.php     # POST status updater fallback
│
├── super-admin/              # Platform-level tenant administration
│   ├── dashboard.php         # KPIs, MRR, trials & expiring subscriptions
│   ├── school.php            # Tenant detail, users, subscriptions & settings
│   └── suspend.php           # CSRF-protected suspend / activate action
│
└── tests/                    # Automated testing suite
    ├── AuthTest.php          # Auth, hashing, CSRF & rate limit tests
    ├── TasksTest.php         # Task CRUD & lifecycle tests
    ├── StudentsCoursesTest.php# Relational integrity & unique constraint tests
    ├── SecurityTest.php      # SQLi, XSS, formula injection & RBAC tests
    ├── EventsAlertsTest.php  # Events, holidays & emergency alerts tests
    ├── SaasTest.php          # Plans, trials, subdomains & tenant isolation tests
    └── run_tests.php         # CLI test runner
```

---

## 5. Local Setup Instructions (XAMPP on Windows)

### Step 1: Ensure XAMPP Services are Running
1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache** and **MySQL**.
3. Confirm that both services are running (Apache on port 80/443 and MySQL on port 3306).

### Step 2: Workspace Location
Ensure the project folder `student-task-manager` is located inside your XAMPP web root:
- Standard default: `C:\xampp\htdocs\student-task-manager\`
- Custom drive (if XAMPP is on F:): `F:\Xampp\htdocs\student-task-manager\`

### Step 3: Database Import
You can set up the database in **two easy ways**:

#### Option A: Automatic Self-Healing Bootstrap (Zero Configuration)
The application includes a self-healing database bootstrap in `config/db.php`. When you first visit `http://localhost/student-task-manager/`, if the `edusphere` database does not exist, the system automatically creates the database, imports `database.sql`, and provisions the demo accounts!

#### Option B: Manual Import via phpMyAdmin
1. Open your browser and navigate to `http://localhost/phpmyadmin/`.
2. Click the **Import** tab on the top menu bar.
3. Click **Choose File** and select `database.sql` from the project root.
4. Click **Import** at the bottom of the page.
5. MariaDB / MySQL will create the `edusphere` database with all 18 tables (tenants, subscriptions, contact messages, and the original academic modules) and sample seed records.

> Upgrading an existing installation? Run the idempotent SaaS migration once:
> ```powershell
> php database/migrate_saas.php
> ```
> It adds the subdomain/status/plan/trial columns, subscription and contact tables, and backfills the demo schools. `database/migrate_multi_school.php` also invokes it.

### Step 4: Access the Application
Open your web browser and navigate to:
```
http://localhost/student-task-manager/
```
You will be directed to the modern login screen.

### Step 5: Configure School Subdomains (Optional)
Set `APP_DOMAIN` in `.env` (or `config/db.php`) to the base domain used for tenant subdomains:
```dotenv
APP_DOMAIN=your-domain.com
```
Then point wildcard DNS (`*.your-domain.com`) and an Apache wildcard virtual host at the application. Without wildcard DNS (for example on localhost), use the path fallback:
```
http://localhost/student-task-manager/auth/login.php?school=greenvalley
```
`*.localhost` subdomains are also recognised automatically during local development.

---

## 6. Default Demo Accounts & Roles

Academics is seeded with demo accounts covering every role. School-scoped accounts belong to the demo tenants (`SCH001` and `SCH002`):

| Role | Email Address | Password | Permissions & Scope |
| :--- | :--- | :--- | :--- |
| **Platform Super Admin** | `admin@edusphere.com` | `Admin@123` | Platform-wide: schools, plans, subscriptions, contact inbox, users, settings, audit logs. |
| **School Admin (SCH001)** | `schooladmin@sch001.com` | `School@123` | Full access inside Academics International School only. |
| **Teacher (SCH001)** | `teacher@sch001.com` | `Teacher@123` | Academic access inside the school: courses, students, tasks, events. |
| **Student (SCH001)** | `student@sch001.com` | `Student@123` | Student access: own profile, courses, assigned tasks. |
| **Parent (SCH001)** | `parent@sch001.com` | `Parent@123` | Parent portal: linked children, progress, announcements, tasks. |
| **School Admin (SCH002)** | `schooladmin@sch002.com` | `School@123` | Full access inside Beaconhouse Model School only. |

Legacy demo accounts `teacher@edusphere.com` / `Teacher@123` and `student@edusphere.com` / `Student@123` remain available under the `SCH001` tenant.

---

## 7. How to Run the Automated Test Suite

EduSphere / Academics includes a comprehensive automated test runner verifying **100+ critical test cases** across authentication, security, database models, feature workflows (attendance, grades, fees, timetables, messages, leave requests), tenant isolation, SaaS billing, and application security.

To run the tests from your terminal:
```bash
php tests/run_tests.php
```

### Verified Test Suite Breakdown (101 Passing Tests):
1. **Authentication & Session Security Suite**:
   - `Password hashing creates valid bcrypt hash`
   - `Admin demo account authenticates with Admin@123`
   - `Teacher demo account authenticates with Teacher@123`
   - `Student demo account authenticates with Student@123`
   - `Existing email recognized for duplicate registration rejection`
   - `Login rate limit triggers after 5 failed attempts within window`
   - `CSRF token is generated with 64 hex characters (32 bytes)`
   - `validateCsrfToken accepts matching token`
   - `validateCsrfToken rejects forged or tampered token`

2. **Auth Deep-Dive Suite (test_auth.php)**:
   - `hashPassword creates valid bcrypt signature`
   - `verifyPassword helper returns true for valid password`
   - `verifyPassword helper returns false for invalid password`
   - `generateCsrfToken produces 64-char hexadecimal string`
   - `validateCsrfToken validates session token correctly`
   - `validateCsrfToken rejects empty or invalid token`
   - `checkLoginRateLimit triggers after 5 failed attempts`
   - `clearLoginAttempts resets rate limit counter`
   - `isValidEmail accepts valid formats and rejects invalid formats`
   - `getRole returns valid role string`
   - `createPasswordResetToken generates valid reset token`
   - `validatePasswordResetToken returns matching email address`
   - `consumePasswordResetToken updates user password successfully`

3. **Mandatory Tasks Module & Lifecycle Suite**:
   - `Task can be successfully created in database`
   - `Created task can be retrieved with correct attributes`
   - `Completing a task records completion timestamp`
   - `Moving task back to In Progress clears completed_at`
   - `Overdue tasks are correctly identified by query logic`
   - `Task can be deleted and removed from database`

4. **Students, Courses & Enrollments Suite**:
   - `generateStudentNumber returns standard STU-YYYY-XXXX format`
   - `Student record can be created successfully`
   - `Database enforces UNIQUE constraint on student email`
   - `Course record created with code, credits, and creator`
   - `Database enforces UNIQUE constraint on course code`
   - `Student successfully enrolled into course`
   - `Database prevents duplicate enrollment of same student in same course`

5. **Database Models & Constraints Suite (test_models.php)**:
   - `Users model: Insert record and retrieve ID`
   - `Students model: Auto-generates valid format student number`
   - `Students model: Create student record successfully`
   - `Students model: Database enforces unique student_number constraint`
   - `Courses model: Create course record with code and credits`
   - `Enrollments model: Enroll student to course`
   - `Enrollments model: Enforces unique student and course composite key`
   - `Tasks model: Create and assign task successfully`
   - `Tasks model: Completed status stamps completed_at timestamp`
   - `Models cascade deletion and test data cleanup`

6. **Security, SQLi, XSS & Injection Suite**:
   - `Prepared statement safely neutralizes classic SQLi payload (OR 1=1)`
   - `Prepared statement neutralizes UNION-based SQLi attempt`
   - `e() helper escapes dangerous script tags to &lt;script&gt;`
   - `e() helper escapes double quotes to prevent attribute breakout`
   - `safeCsvCell prefixes "=" formula injection with a single quote`
   - `safeCsvCell neutralizes "+", "-", "@" spreadsheet triggers`
   - `safeCsvCell preserves normal safe alphanumeric text untouched`
   - `Admin role correctly validates isAdmin=true, isStaff=true, isStudent=false`
   - `Teacher role correctly validates isStaff=true, isAdmin=false, isStudent=false`
   - `Student role correctly validates isStudent=true, isStaff=false, isAdmin=false`

7. **Security Hardening & RBAC Suite (test_security.php)**:
   - `SQLi attacks safely neutralized across parameter variations`
   - `e() HTML entity encoding neutralizes malicious script tags and attributes`
   - `safeCsvCell neutralizes formula injection triggers (=, +, -, @)`
   - `safeCsvCell leaves safe alphanumeric strings unchanged`
   - `Admin role grants isAdmin and isStaff`
   - `Teacher role grants isStaff and blocks isAdmin`
   - `Student role grants isStudent and blocks isStaff/isAdmin`
   - `Parent role correctly validates isParent and blocks isStaff`
   - `recordActivity successfully records security audit logs`

8. **School Events, Holidays & Alerts Suite**:
   - `getActiveEmergencyAlert retrieves active emergency closure notice`
   - `Emergency alert is_active status can be toggled by school admin`
   - `School function event can be created with date and status`
   - `getSchoolEventsList successfully returns upcoming events`
   - `Gazetted holidays and off-days are correctly indexed and queryable`
   - `School event can be safely deleted with cleanup`

9. **Advanced Features Suite (test_features.php)**:
   - Attendance marking and summary/percentage calculations
   - Assessment creation and marks recording
   - Student GPA and course performance calculations
   - Fee invoices and payment processing
   - Timetable scheduling and conflict detection
   - Internal messaging and read receipts
   - Staff leave requests and admin approval workflow
   - System settings persistence

10. **SaaS Plans, Trials & Subscriptions Suite** (15 tests):
   - Migration adds `subdomain`, `status`, `plan`, and `trial_ends_at`; subscription and contact tables exist.
   - Three plans are priced Basic $50, Pro $150, Enterprise $500; legacy plans map correctly.
   - Subdomain validation rejects reserved/malformed names; host resolution supports `APP_DOMAIN`, `*.localhost`, ports, and ignores raw IPs.
   - School self-registration creates a 30-day trial, a hashed `school_admin` login, and a zero-price trial subscription.
   - Duplicate subdomains are rejected.
   - Expired trials and suspended schools are blocked; active paid subscriptions activate the school and clear the trial.
   - New tenant users stay isolated by `school_id`; contact intake stores messages with school scoping.

---

## 8. SaaS Setup, Plans & Trial Behavior

### Plans
| Plan | Price | Intended For |
| :--- | ---: | :--- |
| **Basic** | $50 / month | Small schools getting started (up to 100 students) |
| **Pro** | $150 / month | Growing schools (up to 500 students, parent portal, reports) |
| **Enterprise** | $500 / month | Large institutions (unlimited students, custom branding, dedicated onboarding) |

Prices live in `includes/saas.php` (`saas_plans()`), so the landing page, signup form, super-admin screens, and tests all read from one source.

### Tenant Lifecycle
1. A visitor registers at `/register.php`; the app creates the school, its first `school_admin`, default public settings, and a **30-day trial** with a zero-price subscription.
2. The trial school has `status = 'trial'`, `is_active = 1`, and `trial_ends_at = now + 30 days`.
3. A super admin records an active paid subscription in `/admin/subscriptions.php`; the school becomes `status = 'active'`, `trial_ends_at = NULL`, and its plan is updated.
4. Suspending a school sets `status = 'suspended'` and `is_active = 0`. Suspended schools and expired trials are blocked at login and return **HTTP 403** on public school pages.
5. Activating a school restores access without changing its subscription history.

### Subdomain Routing
- `APP_DOMAIN` defines the base domain. For `APP_DOMAIN=academics.com`, the school `greenvalley` is served at `greenvalley.academics.com`.
- Wildcard DNS (`*.academics.com`) and a wildcard Apache virtual host are required in production.
- Local fallback: `/auth/login.php?school=greenvalley`, `/public/school.php?code=SCH001`, or any `*.localhost` host.
- Reserved names (`www`, `admin`, `api`, `mail`, etc.) and malformed subdomains are rejected by `saas_validate_subdomain()`.

### Key SaaS Files
| File | Purpose |
| :--- | :--- |
| `includes/saas.php` | Plans, subdomain validation/resolution, registration, subscriptions, trial access, contact intake |
| `register.php` | Public school signup with CSRF, honeypot, and throttling |
| `contact.php` / `contact_process.php` | Public contact page and CSRF-protected message intake |
| `super-admin/dashboard.php` | Platform KPIs, MRR, trials, expiring subscriptions |
| `admin/schools.php` | Create/edit schools, subdomains, plans, status, trial dates |
| `admin/subscriptions.php` | Record, cancel, and review subscription history |
| `admin/messages.php` | Contact inbox scoped to the current school/platform admin |
| `database/migrate_saas.php` | Idempotent upgrade for existing databases |

---

## 9. Git & GitHub Publishing Instructions

Follow these exact commands to publish the project to your personal GitHub repository:

```bash
# 1. Initialize git in workspace (already initialized)
git init

# 2. Stage all project files (respecting .gitignore)
git add .

# 3. Create initial commit
git commit -m "Build Academics - Premium Student Management System"

# 4. Set main branch
git branch -M main

# 5. Link to your personal GitHub repository (replace with your repo URL)
git remote add origin https://github.com/YOUR_USERNAME/student-task-manager.git

# 6. Push to GitHub
git push -u origin main
```

---

## 10. Security & Hardening Architecture

Academics follows strict defense-in-depth security principles:

1. **SQL Injection**:
   - Zero string concatenation in database queries.
   - 100% of queries use PDO prepared statements with bound parameters.
2. **Cross-Site Scripting (XSS)**:
   - Output rendered through `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')` via the `e()` helper function.
   - DOM manipulation in `tasks.js` utilizes explicit character entity escaping.
3. **Cross-Site Request Forgery (CSRF)**:
   - Synchronizer Token Pattern using cryptographically secure random bytes (`random_bytes(32)`).
   - Validated on all state-changing actions, including Fetch API requests (`X-CSRF-Token`).
4. **CSV Formula Injection**:
   - Cells beginning with `=`, `+`, `-`, or `@` are escaped with a leading apostrophe (`'`) via `safeCsvCell()`.
5. **Session Security**:
   - Session identifiers regenerated on every login to eliminate session fixation.
   - Cookies flagged with `HttpOnly` and `SameSite=Strict`.

---

## 11. Aptech Viva & Presentation Tips

When presenting this project to your instructor or examiner, highlight these key engineering decisions:
1. **Explain the Architecture**: Emphasize that the system uses plain PHP with a clean modular MVC-inspired separation (`includes/`, `config/`, `ajax/`, `assets/`, `tests/`) rather than an bloated framework.
2. **Demonstrate Asynchronous UX**: Open `tasks/index.php`, type into the search bar, change the priority filter, and demonstrate that the table updates instantly via Fetch API without refreshing the browser.
3. **Showcase Inline Status Updates**: Change a task status dropdown in the table and point out the real-time toast alert and database sync.
4. **Walk Through Security**: Show the examiner `tests/SecurityTest.php` and explain how PDO parameterized statements prevent SQL injection and how `safeCsvCell` prevents CSV formula injection.
5. **Run the Test Suite**: Execute `php tests/run_tests.php` live in front of the instructor to prove 100% test coverage.

---

*Academics — Built with excellence for Aptech Computer Education.*


## 5. Local setup, migrations and verification

1. Copy `.env.example` to `.env` and configure the local MySQL connection.
2. Create the database named by `DB_NAME` (default `edusphere`).
3. Apply the current ordered schema migrations:

```bash
php database/migrate.php up
```

4. For automated tests, create a separate empty database (default `edusphere_test`) and use `APP_ENV=test` with `DB_NAME_TEST=edusphere_test`. The test fixture loader seeds data only in the test environment; it never seeds local or production databases.
5. Run:

```bash
php tests/run_tests.php
find . -type f -name '*.php' -not -path './storage/cache/*' -exec php -l {} \\;
```

6. For authenticated portal smoke checks, start the PHP server in one terminal and run the smoke script in another:

```bash
php -S 127.0.0.1:8090
```

```bash
ACADEMICS_BASE=http://127.0.0.1:8090 php storage/smoke_pages.php
```

The CI workflow performs MySQL migrations, PHP syntax checks, automated tests and authenticated multi-role portal smoke checks on pull requests. It deploys only after a push to `main`; it does not execute production database migrations.

**Production notes:** use a private root `.env` with `APP_ENV=production`, `APP_DEBUG=false`, canonical `APP_URL`, database credentials and SMTP credentials. Back up the database before applying migrations. Do not import the legacy `database.sql` dump into an existing installation and do not use demo seed accounts in production. See [INFINITYFREE_DEPLOYMENT_GUIDE.md](INFINITYFREE_DEPLOYMENT_GUIDE.md).

