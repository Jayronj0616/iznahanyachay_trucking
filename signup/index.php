<?php
// Public self-signup removed per panel revision: account creation is admin-only via home/invite/.
require_once __DIR__ . '/../includes/config.php';
header('Location: ' . BASE_PATH . '/');
exit;
