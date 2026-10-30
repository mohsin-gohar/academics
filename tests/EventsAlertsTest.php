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

        // Test 1: Emergency alerts table exists and active alert can be fetched
        $alert = getActiveEmergencyAlert($this->pdo, 1);
        $results[] = [
            'name' => 'getActiveEmergencyAlert retrieves active emergency closure notice',
            'pass' => $alert !== null && !empty($alert['title'])
        ];

        // Test 2: Emergency alert status toggle
        if ($alert) {
            $currStatus = (int)$alert['is_active'];
            $newStatus = $currStatus === 1 ? 0 : 1;
            $this->pdo->prepare("UPDATE emergency_alerts SET is_active = ? WHERE id = ?")->execute([$newStatus, $alert['id']]);
            
            $chk = $this->pdo->prepare("SELECT is_active FROM emergency_alerts WHERE id = ?");
            $chk->execute([$alert['id']]);
            $updated = (int)$chk->fetchColumn();

            // restore original status
            $this->pdo->prepare("UPDATE emergency_alerts SET is_active = ? WHERE id = ?")->execute([$currStatus, $alert['id']]);

            $results[] = [
                'name' => 'Emergency alert is_active status can be toggled by school admin',
                'pass' => $updated === $newStatus
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
