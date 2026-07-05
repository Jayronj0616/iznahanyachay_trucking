<?php
$pageTitle = 'Timesheet Entry';
$activeNav = 'timesheet';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '⏱️';
$pageLabel = 'Timesheet';
include __DIR__ . '/../../includes/topbar.php';

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$placeholderTitle = 'Entry for ' . htmlspecialchars($date);
$placeholderNote = 'No backend yet — this will show/edit the time-in and time-out for this day.';
include __DIR__ . '/../../includes/placeholder.php';

$navBase = BASE_PATH;
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/foot.php';
?>
