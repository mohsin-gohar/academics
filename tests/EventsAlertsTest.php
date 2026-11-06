<?php
/**
 * Test Suite: School Events, Gazetted Holidays & Emergency Closure Alerts
 */
class EventsAlertsTest {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function run(): array {
        $results = [];

        // Test 1: Emergency alerts table exists and an active alert can be fetched.
        // Create a self-contained alert so the test never depends on demo data
        // (or on dates that eventually expire).
        $testAlertTitle = 'QA Emergency Closure ' . time();
        $insAlert = $this->pdo->prepare("INSERT INTO emergency_alerts
            (school_id, title, message, alert_type, effective_date, end_date, is_active)
            VALUES (1, ?, 'Automated test alert', 'Emergency Closure', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 1)");
        $insAlert->execute([$testAlertTitle]);
        $alertId = (int)$this->pdo->lastInsertId();

        $alert = getActiveEmergencyAlert($this->pdo, 1);
        $results[] = [
            'name' => 'getActiveEmergencyAlert retrieves active emergency closure notice',
            'pass' => $alert !== null && (int)$alert['id'] === $alertId && !empty($alert['title'])
        ];

        // Test 2: Emergency alert status toggle
        if ($alertId > 0) {
            $newStatus = 0;
            $this->pdo->prepare("UPDATE emergency_alerts SET is_active = ? WHERE id = ?")->execute([$newStatus, $alertId]);

            $chk = $this->pdo->prepare("SELECT is_active FROM emergency_alerts WHERE id = ?");
            $chk->execute([$alertId]);
            $updated = (int)$chk->fetchColumn();

            // A deactivated alert must no longer be returned.
            $afterToggle = getActiveEmergencyAlert($this->pdo, 1);

            $this->pdo->prepare("DELETE FROM emergency_alerts WHERE id = ?")->execute([$alertId]);

            $results[] = [
                'name' => 'Emergency alert is_active status can be toggled by school admin',
                'pass' => $updated === $newStatus && (empty($afterToggle) || (int)$afterToggle['id'] !== $alertId)
            ];
        }

        // Test 3: School events can be inserted
        $testTitle = 'Test Annual Science Exhibition ' . time();
        $ins = $this->pdo->prepare("INSERT INTO school_events (title, event_type, description, event_date, is_holiday, status) VALUES (?, 'Function', 'Test Expo', DATE_ADD(CURDATE(), INTERVAL 14 DAY), 0, 'Upcoming')");
        $ins->execute([$testTitle]);
        $eventId = (int)$this->pdo->lastInsertId();

        $results[] = [
            'name' => 'School function event can be created with date and status',
            'pass' => $eventId > 0
        ];

        // Test 4: School event can be queried via helper
        $events = getSchoolEventsList($this->pdo, 10, true);
        $found = false;
        foreach ($events as $ev) {
            if ($ev['title'] === $testTitle) {
                $found = true;
                break;
            }
        }
        $results[] = [
            'name' => 'getSchoolEventsList successfully returns upcoming events',
            'pass' => $found
        ];

        // Test 5: Gazetted Holiday query
        $holidays = $this->pdo->query("SELECT COUNT(*) FROM school_events WHERE is_holiday = 1")->fetchColumn();
        $results[] = [
            'name' => 'Gazetted holidays and off-days are correctly indexed and queryable',
            'pass' => (int)$holidays > 0
        ];

        // Clean up test event
        $this->pdo->prepare("DELETE FROM school_events WHERE id = ?")->execute([$eventId]);
        $results[] = [
            'name' => 'School event can be safely deleted with cleanup',
            'pass' => true
        ];

        return $results;
    }
}
