<?php
require 'config/db.php';
$r = $pdo->query('SELECT id, school_id, course_name FROM courses');
print_r($r->fetchAll());
?>