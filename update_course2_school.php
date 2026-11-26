<?php
require 'config/db.php';
$pdo->exec("UPDATE courses SET school_id = 2 WHERE id = 2");
echo "Updated course 2 school_id to 2\n";

// Verify
$r = $pdo->query("SELECT id, school_id, course_code, course_name FROM courses");
foreach ($r as $row) {
    echo "Course {$row['id']}: school_id={$row['school_id']}, {$row['course_code']} - {$row['course_name']}\n";
}
?>