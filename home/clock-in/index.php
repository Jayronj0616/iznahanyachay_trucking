<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$pageTitle = 'Clock-In';
$activeNav = 'home';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '🏠';
$pageLabel = 'Home';
include __DIR__ . '/../../includes/topbar.php';

$placeholderTitle = 'Clock-In';
$placeholderNote = 'No backend yet — this will record a clock-in once auth exists.';
include __DIR__ . '/../../includes/placeholder.php';

$navBase = BASE_PATH;
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/foot.php';
?>
