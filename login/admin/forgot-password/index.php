<?php
// Deprecated: folded into the single /forgot-password/ page, which now detects an
// Admin/Owner email and asks the security question itself instead of needing a
// separate route. See that file for the real logic.
require_once __DIR__ . '/../../../includes/config.php';
header('Location: ' . BASE_PATH . '/forgot-password/');
exit;
