<?php
require 'config/db.php';
$columns = $pdo->query("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' ORDER BY ORDINAL_POSITION");
foreach ($columns as $col) {
    echo $col['COLUMN_NAME'] . ' (' . $col['COLUMN_TYPE'] . ', nullable=' . $col['IS_NULLABLE'] . ', default=' . ($col['COLUMN_DEFAULT'] ?? 'NULL') . ")\n";
}
?>