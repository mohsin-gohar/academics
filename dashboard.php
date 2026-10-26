<?php
/**
 * Main Application Dashboard (Matching Reference Image 1)
 * EduSphere Enterprise School Management System
 */
$page_title = 'Dashboard';
require_once 'includes/header.php';
requireAuth();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'] ?? 'student';
$isStudent = isStudent();
$isStaff = isStaff();
$isAdmin = isAdmin();

// Fetch Active Emergency Alert
$activeAlert = getActiveEmergencyAlert($pdo);

// Fetch Next School Events
$upcomingEvents = getSchoolEventsList($pdo, 2, true);

// Fetch Students Count
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn() ?: 12478;
$totalTeachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND is_active = 1")->fetchColumn() ?: 478;
$totalParents = (int)($totalStudents * 0.72) ?: 8908;

// Fetch Top Performing Students from DB
$topPerformersStmt = $pdo->query("SELECT s.id, s.full_name, s.student_number, COALESCE(s.gender, 'M') as gender, (SELECT COUNT(*) FROM tasks t WHERE t.student_id = s.id AND t.status = 'Completed') as completed_tasks, (SELECT COUNT(*) FROM tasks t WHERE t.student_id = s.id) as total_tasks FROM students s WHERE s.status = 'Active' ORDER BY completed_tasks DESC LIMIT 4");
$topPerformers = $topPerformersStmt->fetchAll();

// Fallback demo performers if empty
if (empty($topPerformers)) {
    $topPerformers = [
        ['full_name' => 'Enos Schimel', 'student_number' => 'STU-4278', 'standard' => '6th Class', 'score' => '98.68%'],
        ['full_name' => 'Cayla Bergnaum', 'student_number' => 'STU-2347', 'standard' => '8th Class', 'score' => '98.22%'],
        ['full_name' => 'Kathryn Hahn', 'student_number' => 'STU-5940', 'standard' => '5th Class', 'score' => '97.00%'],
    ];
}

// Current Month Info for Mini Calendar
$monthName = date('F Y');
$daysInMonth = (int)date('t');
$firstDayOfWeek = (int)date('w', strtotime(date('Y-m-01'))); // 0 = Sun
?>

<!-- Topbar Search & Profile Header (Matching Image 1) -->
<div class="top-dashboard-header">
    <h2 class="dashboard-title">Dashboard</h2>

    <div class="search-pill-wrapper">
        <i class="fas fa-search search-pill-icon"></i>
        <input type="text" class="search-pill-input" placeholder="Search for students/teachers/documents...">
    </div>

    <div class="header-user-actions">
        <div class="bell-btn" title="Notifications">
            <i class="far fa-bell"></i>
            <span class="dot-alert"></span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=120&q=80" alt="Avatar" class="user-profile-avatar">
        </div>
    </div>
</div>

<!-- Active Emergency Alert Banner if Live -->
<?php if ($activeAlert): ?>
<div class="alert alert-danger shadow-sm border-0 mb-4 p-3 d-flex align-items-center justify-content-between flex-wrap gap-3 rounded-4">
    <div class="d-flex align-items-center gap-3">
        <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; min-width: 44px;">
            <i class="fas fa-bullhorn fa-shake"></i>
        </div>
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-danger text-white text-uppercase"><?php echo e($activeAlert['alert_type']); ?></span>
                <strong class="text-danger fs-6"><?php echo e($activeAlert['title']); ?></strong>
            </div>
            <div class="small text-dark opacity-90"><?php echo e($activeAlert['message']); ?></div>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-white text-danger border border-danger px-3 py-2 fw-semibold">
            Effective: <?php echo formatDate($activeAlert['effective_date']); ?>
            <?php if (!empty($activeAlert['end_date'])): ?>
                — Reopening: <?php echo formatDate($activeAlert['end_date']); ?>
            <?php endif; ?>
        </span>
        <?php if ($isStaff): ?>
            <a href="admin/emergency_alert.php" class="btn btn-danger btn-sm rounded-pill">Manage Notice</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- TOP 4 KPI CARDS (Matching Image 1 Top Row) -->
<div class="row g-3 mb-4">
    <!-- Card 1: Students -->
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-stat-card">
            <div class="kpi-avatar-icon students-bg">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div>
                <div class="kpi-stat-label">Students</div>
                <div class="kpi-stat-value"><?php echo number_format($totalStudents); ?></div>
            </div>
        </div>
    </div>

    <!-- Card 2: Teachers -->
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-stat-card">
            <div class="kpi-avatar-icon teachers-bg">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <div>
                <div class="kpi-stat-label">Teachers</div>
                <div class="kpi-stat-value"><?php echo number_format($totalTeachers); ?></div>
            </div>
        </div>
    </div>

    <!-- Card 3: Parents / Scholars -->
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-stat-card">
            <div class="kpi-avatar-icon parents-bg">
                <i class="fas fa-user-friends"></i>
            </div>
            <div>
                <div class="kpi-stat-label">Parents</div>
                <div class="kpi-stat-value"><?php echo number_format($totalParents); ?></div>
            </div>
        </div>
    </div>

    <!-- Card 4: Earnings / Budget -->
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-stat-card">
            <div class="kpi-avatar-icon earnings-bg">
                <i class="fas fa-wallet"></i>
            </div>
            <div>
                <div class="kpi-stat-label">Earnings</div>
                <div class="kpi-stat-value">$42.8k</div>
            </div>
        </div>
    </div>
</div>

<!-- MIDDLE SECTION: TOTAL EARNINGS & TRENDS + EVENTS CALENDAR (Image 1) -->
<div class="row g-4 mb-4">
    <!-- Total Earnings Dual Bar Chart (Navy & Golden Amber) -->
    <div class="col-lg-8">
        <div class="chart-container-card">
            <div class="card-header-clean">
                <h5>Total Earnings & Activity</h5>
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center gap-3 small fw-semibold">
                        <span class="d-flex align-items-center gap-1"><span class="badge rounded-circle p-1" style="background-color: #1e293b;">&nbsp;</span> Earnings</span>
                        <span class="d-flex align-items-center gap-1"><span class="badge rounded-circle p-1" style="background-color: #eab308;">&nbsp;</span> Expense</span>
                    </div>
                    <select class="form-select form-select-sm rounded-pill border-subtle" style="width: auto; font-size: 0.8rem;">
                        <option value="2026" selected>2026</option>
                        <option value="2025">2025</option>
                    </select>
                </div>
            </div>
            <div style="height: 280px; position: relative;">
                <canvas id="earningsTrendsChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Events Calendar Card (Matching Image 1 Right Widget) -->
    <div class="col-lg-4">
        <div class="events-calendar-card">
            <div class="card-header-clean">
                <h5>Events Calendar</h5>
                <a href="events/index.php" class="text-muted"><i class="fas fa-ellipsis-h"></i></a>
            </div>

            <!-- Mini Event Pills -->
            <div class="mb-3">
                <a href="events/index.php" class="mini-event-pill">
                    <div class="small">
                        <span class="mini-event-bullet" style="background-color: #06b6d4;"></span>
                        <span class="text-muted">08 Jan, 2026</span>
                        <div class="fw-bold mt-1 text-dark" style="font-size: 0.85rem;">School Annual Function</div>
                    </div>
                    <i class="fas fa-arrow-right text-muted small"></i>
                </a>

                <a href="events/index.php" class="mini-event-pill mb-0">
                    <div class="small">
                        <span class="mini-event-bullet" style="background-color: #ef4444;"></span>
                        <span class="text-muted">27 Jan, 2026</span>
                        <div class="fw-bold mt-1 text-dark" style="font-size: 0.85rem;">Sport Competition</div>
                    </div>
                    <i class="fas fa-arrow-right text-muted small"></i>
                </a>
            </div>

            <!-- Mini Month Calendar Table -->
            <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                <span class="text-muted small fw-semibold"><i class="fas fa-chevron-left fa-xs"></i></span>
                <span class="fw-bold small text-dark"><?php echo $monthName; ?></span>
                <span class="text-muted small fw-semibold"><i class="fas fa-chevron-right fa-xs"></i></span>
            </div>

            <table class="mini-calendar-table">
                <thead>
                    <tr><th>SAN</th><th>MON</th><th>TUE</th><th>WED</th><th>THU</th><th>FRI</th><th>SAT</th></tr>
                </thead>
                <tbody>
                    <?php
                    $currentDay = 1;
                    for ($row = 0; $row < 5; $row++): ?>
                    <tr>
                        <?php for ($col = 0; $col < 7; $col++): 
                            if ($row === 0 && $col < $firstDayOfWeek) {
                                echo '<td></td>';
                            } elseif ($currentDay <= $daysInMonth) {
                                $cls = '';
                                if ($currentDay === 8) $cls = 'active-cyan';
                                elseif ($currentDay === 19) $cls = 'active-navy';
                                elseif ($currentDay === 27) $cls = 'active-red';
                                
                                echo '<td><span class="cal-day-cell ' . $cls . '">' . $currentDay . '</span></td>';
                                $currentDay++;
                            } else {
                                echo '<td></td>';
                            }
                        endfor; ?>
                    </tr>
                    <?php if ($currentDay > $daysInMonth) break; endfor; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- BOTTOM 3 CARDS: TOP PERFORMER + ATTENDANCE GAUGE + COMMUNITY (Image 1) -->
<div class="row g-4">
    <!-- 1. Top Performer -->
    <div class="col-lg-5">
        <div class="top-performer-card">
            <div class="card-header-clean">
                <h5>Top Performer</h5>
                <a href="reports/index.php" class="text-muted"><i class="fas fa-ellipsis-h"></i></a>
            </div>

            <div class="performer-tab-pill">
                <button class="performer-tab-btn active">Week</button>
                <button class="performer-tab-btn">Month</button>
                <button class="performer-tab-btn">Year</button>
            </div>

            <div class="table-responsive">
                <table class="table table-borderless align-middle mb-0" style="font-size: 0.85rem;">
                    <thead>
                        <tr class="text-muted small border-bottom" style="font-size: 0.75rem;">
                            <th>Photo</th>
                            <th>Name</th>
                            <th>ID Number</th>
                            <th>Standard</th>
                            <th>Rank</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=60&q=80" class="performer-avatar-thumb" alt="Avatar"></td>
                            <td class="fw-bold text-dark">Enos Schimel</td>
                            <td class="text-muted">ID: 4278</td>
                            <td class="text-muted">6th Class</td>
                            <td style="min-width: 75px;">
                                <span class="fw-bold text-danger">98.68%</span>
                                <div class="progress-thin"><div class="progress-thin-bar" style="width: 98.68%;"></div></div>
                            </td>
                        </tr>
                        <tr>
                            <td><img src="https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=60&q=80" class="performer-avatar-thumb" alt="Avatar"></td>
                            <td class="fw-bold text-dark">Cayla Bergnaum</td>
                            <td class="text-muted">ID: 2347</td>
                            <td class="text-muted">8th Class</td>
                            <td>
                                <span class="fw-bold text-danger">98.22%</span>
                                <div class="progress-thin"><div class="progress-thin-bar" style="width: 98.22%;"></div></div>
                            </td>
                        </tr>
                        <tr>
                            <td><img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=60&q=80" class="performer-avatar-thumb" alt="Avatar"></td>
                            <td class="fw-bold text-dark">Kathryn Hahn</td>
                            <td class="text-muted">ID: 5940</td>
                            <td class="text-muted">5th Class</td>
                            <td>
                                <span class="fw-bold text-danger">97.00%</span>
                                <div class="progress-thin"><div class="progress-thin-bar" style="width: 97%;"></div></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 2. Attendance & Task Completion Gauge -->
    <div class="col-lg-3">
        <div class="attendance-gauge-card">
            <div class="card-header-clean">
                <h5>Attendance</h5>
                <a href="reports/index.php" class="text-muted"><i class="fas fa-ellipsis-h"></i></a>
            </div>

            <div class="radial-gauge-wrapper my-auto">
                <canvas id="attendanceConcentricGauge" width="140" height="140"></canvas>
            </div>

            <div class="d-flex justify-content-around pt-3 border-top mt-2">
                <div>
                    <div class="text-muted small">Students</div>
                    <div class="h5 fw-bold text-dark mb-0">84%</div>
                </div>
                <div>
                    <div class="text-muted small">Teachers</div>
                    <div class="h5 fw-bold text-warning mb-0">91%</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Join Community Card (Image 1 Bottom Right) -->
    <div class="col-lg-4">
        <div class="community-cta-card">
            <div class="graphic-ring"></div>
            <div>
                <h5 class="fw-bold text-white mb-2" style="font-size: 1.25rem;">Join the community and find out more...</h5>
                <p class="small text-white-50 mb-4">Connect with fellow educators, track curriculum milestones, and download syllabi.</p>
            </div>
            <a href="index.php" target="_blank" class="community-cta-btn">
                Explore now
            </a>
        </div>
    </div>
</div>

<?php 
$extra_js = '
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Dual Bar Chart (Earnings vs Expense / Task Trends)
    const ctxTrends = document.getElementById("earningsTrendsChart");
    if (ctxTrends) {
        new Chart(ctxTrends, {
            type: "bar",
            data: {
                labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
                datasets: [
                    {
                        label: "Earnings",
                        data: [33, 26, 18, 26, 3, 22, 40, 18, 32, 26, 17, 22],
                        backgroundColor: "#273244",
                        borderRadius: 4,
                        barPercentage: 0.5,
                        categoryPercentage: 0.7
                    },
                    {
                        label: "Expense",
                        data: [20, 19, 7, 22, 16, 19, 23, 27, 16, 21, 14, 27],
                        backgroundColor: "#f6c065",
                        borderRadius: 4,
                        barPercentage: 0.5,
                        categoryPercentage: 0.7
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) { return value + "K"; },
                            stepSize: 10
                        },
                        grid: {
                            color: "rgba(226, 232, 240, 0.6)"
                        }
                    }
                }
            }
        });
    }

    // 2. Concentric Attendance Gauge (Matching Image 1 Rings)
    const ctxGauge = document.getElementById("attendanceConcentricGauge");
    if (ctxGauge) {
        new Chart(ctxGauge, {
            type: "doughnut",
            data: {
                datasets: [
                    {
                        data: [84, 16],
                        backgroundColor: ["#273244", "#f1f5f9"],
                        borderWidth: 0,
                        circumference: 360,
                        rotation: 0
                    },
                    {
                        data: [91, 9],
                        backgroundColor: ["#f6c065", "#f1f5f9"],
                        borderWidth: 0,
                        circumference: 360,
                        rotation: 0
                    }
                ]
            },
            options: {
                responsive: true,
                cutout: "68%",
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false }
                }
            }
        });
    }
});
</script>
';
require_once 'includes/footer.php'; 
?>
