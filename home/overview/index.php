<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$pageTitle = 'Dashboard Overview';
$activeNav = 'overview';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '📊';
$pageLabel = 'Overview';
include __DIR__ . '/../../includes/topbar.php';
?>

<main class="max-w-3xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-6">
  <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dashboard Overview</h1>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div class="bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl px-6 py-5 shadow-sm dark:shadow-none">
      <div class="text-gray-900 dark:text-white font-semibold mb-4">Total Salary</div>
      <div class="border-t border-gray-200 dark:border-surface-border mb-4"></div>
      <div class="text-gray-900 dark:text-white text-xl font-bold">No data</div>
    </div>
    <div class="bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl px-6 py-5 shadow-sm dark:shadow-none">
      <div class="text-gray-900 dark:text-white font-semibold mb-4">Total Hours</div>
      <div class="border-t border-gray-200 dark:border-surface-border mb-4"></div>
      <div class="text-gray-900 dark:text-white text-xl font-bold">--</div>
    </div>
    <div class="bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl px-6 py-5 shadow-sm dark:shadow-none">
      <div class="text-gray-900 dark:text-white font-semibold mb-4">Total Late</div>
      <div class="border-t border-gray-200 dark:border-surface-border mb-4"></div>
      <div class="text-gray-900 dark:text-white text-xl font-bold">--</div>
    </div>
    <div class="bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl px-6 py-5 shadow-sm dark:shadow-none">
      <div class="text-gray-900 dark:text-white font-semibold mb-4">Performance</div>
      <div class="border-t border-gray-200 dark:border-surface-border mb-4"></div>
      <div class="text-gray-900 dark:text-white text-xl font-bold">--</div>
    </div>
  </div>
</main>

<?php
$navBase = BASE_PATH;
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/foot.php';
?>
