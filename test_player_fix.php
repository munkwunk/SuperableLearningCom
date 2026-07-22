<?php
$_GET['course_id'] = 'lc-json-showcase';
require_once 'config.php';

ob_start();
include 'player.php';
$output = ob_get_clean();

if (strpos($output, 'Course Not Found') === false) {
    echo "OK: Course Loaded successfully!\n";
} else {
    echo "ERROR: Course Not Found\n";
}
