# EduSphere — Premium Student Management System (SMS)
> **Academic Level:** Aptech Academic Assignment & Commercial SaaS Prototype  
> **Core Stack:** Plain PHP 8.2+, MySQL 8+ / MariaDB, PDO, Bootstrap 5 CDN, Vanilla JavaScript, Fetch API, HTML5/CSS3

---

## 1. Executive Summary

**EduSphere** is an enterprise-grade, responsive Student Management System built without heavy backend frameworks. It combines clean modular PHP architecture, parameterized PDO queries, and Vanilla JavaScript Fetch API interactions with a modern educational SaaS user interface.

The application retains the **Student Task Manager** as its mandatory, first-class core module, seamlessly integrating it with **Student Management**, **Course & Enrollment Management**, **Role-Based Access Control (RBAC)**, **Visual Analytics Dashboards**, **Secure Audit Logs**, and **Formula-Protected CSV Exports**.

---

## 2. Key Modules & Features

### A. Public Authentication & Security
- **Registration**: Auto-provisions user account and matching student academic profile with a unique student ID.
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
├── database.sql              # Canonical SQL schema & idempotent demo seed
├── index.php                 # Public entry point / redirector
├── dashboard.php             # Data-driven dashboard with Chart.js analytics
├── README.md                 # Complete system documentation
│
├── admin/                    # Administrative management
│   ├── activity_log.php      # Audit log viewer with action filters
│   ├── create_user.php       # Add new administrative / teacher / student account
│   ├── delete_user.php       # CSRF-protected user deletion
│   ├── edit_user.php         # Edit user credentials and roles
│   ├── settings.php          # Application settings & system health
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
│   │   └── style.css         # Custom EduSphere design system & variables
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
│   └── seed.sql              # Seed data for demo accounts and records
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
│   └── sidebar.php           # Responsive, role-aware sidebar navigation
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
└── tests/                    # Automated testing suite
    ├── AuthTest.php          # Auth, hashing, CSRF & rate limit tests
    ├── TasksTest.php         # Task CRUD & lifecycle tests
    ├── StudentsCoursesTest.php# Relational integrity & unique constraint tests
    ├── SecurityTest.php      # SQLi, XSS, formula injection & RBAC tests
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
5. MariaDB / MySQL will create the `edusphere` database with all 8 tables and sample seed records.

### Step 4: Access the Application
Open your web browser and navigate to:
```
http://localhost/student-task-manager/
```
You will be directed to the modern login screen.

---

## 6. Default Demo Accounts & Roles

EduSphere is seeded with 3 pre-configured demo accounts for testing all user roles:

| Role | Email Address | Password | Permissions & Scope |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin@edusphere.com` | `Admin@123` | Full access: User management, settings, audit logs, all students, courses, and tasks. |
| **Teacher / Staff** | `teacher@edusphere.com` | `Teacher@123` | Academic access: Create/edit courses, manage students, assign tasks, review student progress. |
| **Student** | `student@edusphere.com` | `Student@123` | Student access: View personal profile, view courses, view assigned tasks, update task status. |

---

## 7. How to Run the Automated Test Suite

EduSphere includes an automated test runner verifying 32 critical test cases across authentication, task management, relational integrity, and application security.

To run the tests from your terminal (PowerShell or Command Prompt):
```powershell
cd F:\student-task-manager
php tests/run_tests.php
```

### Verified Test Suite Breakdown:
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

2. **Mandatory Tasks Module & Lifecycle Suite**:
   - `Task can be successfully created in database`
   - `Created task can be retrieved with correct attributes`
   - `Completing a task records completion timestamp`
   - `Moving task back to In Progress clears completed_at`
   - `Overdue tasks are correctly identified by query logic`
   - `Task can be deleted and removed from database`

3. **Students, Courses & Enrollments Suite**:
   - `generateStudentNumber returns standard STU-YYYY-XXXX format`
   - `Student record can be created successfully`
   - `Database enforces UNIQUE constraint on student email`
   - `Course record created with code, credits, and creator`
   - `Database enforces UNIQUE constraint on course code`
   - `Student successfully enrolled into course`
   - `Database prevents duplicate enrollment of same student in same course`

4. **Security, SQLi, XSS & Injection Suite**:
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

---

## 8. Git & GitHub Publishing Instructions

Follow these exact commands to publish the project to your personal GitHub repository:

```bash
# 1. Initialize git in workspace (already initialized)
git init

# 2. Stage all project files (respecting .gitignore)
git add .

# 3. Create initial commit
git commit -m "Build EduSphere - Premium Student Management System"

# 4. Set main branch
git branch -M main

# 5. Link to your personal GitHub repository (replace with your repo URL)
git remote add origin https://github.com/YOUR_USERNAME/student-task-manager.git

# 6. Push to GitHub
git push -u origin main
```

---

## 9. Security & Hardening Architecture

EduSphere follows strict defense-in-depth security principles:

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

## 10. Aptech Viva & Presentation Tips

When presenting this project to your instructor or examiner, highlight these key engineering decisions:
1. **Explain the Architecture**: Emphasize that the system uses plain PHP with a clean modular MVC-inspired separation (`includes/`, `config/`, `ajax/`, `assets/`, `tests/`) rather than an bloated framework.
2. **Demonstrate Asynchronous UX**: Open `tasks/index.php`, type into the search bar, change the priority filter, and demonstrate that the table updates instantly via Fetch API without refreshing the browser.
3. **Showcase Inline Status Updates**: Change a task status dropdown in the table and point out the real-time toast alert and database sync.
4. **Walk Through Security**: Show the examiner `tests/SecurityTest.php` and explain how PDO parameterized statements prevent SQL injection and how `safeCsvCell` prevents CSV formula injection.
5. **Run the Test Suite**: Execute `php tests/run_tests.php` live in front of the instructor to prove 100% test coverage.

---

*EduSphere — Built with excellence for Aptech Computer Education.*
"# academics" 
