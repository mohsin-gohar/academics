<?php
require 'config/db.php';

// Add a course in school 2 for cross-school isolation testing
// Using created_by instead of creator_id, and default values for optional columns
$stmt = $pdo->prepare("INSERT INTO courses (school_id, course_code, course_name, status, credits, created_by) VALUES (?, ?, ?, 'Active', 3, 1)");
$stmt->execute([2, 'PHYS101', 'Physics I']);
echo "Added course in school 2: ID=" . $pdo->lastInsertId() . PHP_EOL;

// Verify
$r = $pdo->query("SELECT id, school_id, course_code, course_name FROM courses WHERE school_id = 2");
print_r($r->fetchAll());
?>