<?php
// Deprecated: the client asked not to show a separate Owner/Admin sign-in at all --
// one shared login form for every role now (index.php's modal). What used to make
// Admin/Owner "special" wasn't a separate page, it's that their account uses a
// company email (@iznahanyachay.com-style) instead of a personal one, which is a
// data convention, not a different login path. This route just sends anyone who
// still has the old link bookmarked back to the real login.
require_once __DIR__ . '/../../includes/config.php';
header('Location: ' . BASE_PATH . '/');
exit;
