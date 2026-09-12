<?php
// Deprecated: login is now a modal on the landing page. This route redirects
// there — anything (requireLogin() bounces here, old bookmarks, etc.) that
// sends someone to /login/ now just lands on / where they can open the modal.
require_once __DIR__ . '/../includes/config.php';
header('Location: ' . BASE_PATH . '/');
exit;
