<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require 'config/db.php';
$r = $pdo->query('SELECT id, school_id, course_name FROM courses');
print_r($r->fetchAll());
?>