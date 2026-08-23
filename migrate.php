<?php
require_once __DIR__ . '/config/db.php';
$sql = file_get_contents(__DIR__ . '/database/alter_multi_image.sql');
if (db_query($sql)) {
    echo "Successfully created plant_images table.\n";
} else {
    echo "Failed to create table.\n";
}
?>
