<?php
$pageTitle = 'About';
$activeNav = 'more';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '⚙️';
$pageLabel = 'Settings';
include __DIR__ . '/../../includes/topbar.php';

$placeholderTitle = 'About';
$placeholderNote = 'System version and info goes here.';
include __DIR__ . '/../../includes/placeholder.php';

$navBase = BASE_PATH;
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/foot.php';
?>
