<?php
// Dead placeholder from before the real biometric clock-in flow existed at
// /timesheet/entry/?date=<today> — nothing in the app links here anymore
// (confirmed via repo-wide grep), and its old copy falsely claimed "no
// backend yet" even though requireLogin() was already being called above
// it. Redirects to the real flow instead of showing a stale/misleading page.
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
header('Location: ' . BASE_PATH . '/timesheet/entry/?date=' . date('Y-m-d'));
exit;
