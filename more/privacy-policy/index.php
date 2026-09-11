<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$pageTitle = 'Privacy Policy';
$activeNav = 'more';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '⚙️';
$pageLabel = 'Settings';
include __DIR__ . '/../../includes/topbar.php';
?>

<main class="max-w-2xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-6">
  <h1 class="text-xl font-bold text-gray-900 dark:text-white">Privacy Policy</h1>

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6 space-y-5 text-sm text-gray-600 dark:text-gray-300">
    <p class="text-xs text-gray-400 dark:text-gray-500">
      Internal-use summary — not a substitute for a lawyer-reviewed policy if this system is ever exposed outside the company.
    </p>

    <div>
      <div class="font-semibold text-gray-900 dark:text-white mb-1">What we collect</div>
      <p>Name, email, phone, and address you or an admin enters; a photo captured at time-in for attendance verification; license number and expiry for drivers; and work records (time in/out, trips, payroll runs).</p>
    </div>

    <div>
      <div class="font-semibold text-gray-900 dark:text-white mb-1">How it's used</div>
      <p>Solely for attendance verification, timesheet approval, and payroll computation for this company's own employees. Nothing is sold or shared with outside parties.</p>
    </div>

    <div>
      <div class="font-semibold text-gray-900 dark:text-white mb-1">Who can see it</div>
      <p>You can see your own records. Admin accounts can see all employee records, since approving attendance and running payroll requires it.</p>
    </div>

    <div>
      <div class="font-semibold text-gray-900 dark:text-white mb-1">Time-in photos</div>
      <p>Stored on the server and shown only to you and admins reviewing that entry, for verification purposes only.</p>
    </div>
  </div>
</main>

<?php
$navBase = BASE_PATH;
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/foot.php';
?>
