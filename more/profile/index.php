<?php
$pageTitle = 'Profile Settings';
$activeNav = 'more';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '⚙️';
$pageLabel = 'Settings';
include __DIR__ . '/../../includes/topbar.php';

$placeholderTitle = 'Profile Settings';
$placeholderNote = 'No backend yet — this will manage the account (name, email, password).';
include __DIR__ . '/../../includes/placeholder.php';

$navBase = BASE_PATH;
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/foot.php';
?>
