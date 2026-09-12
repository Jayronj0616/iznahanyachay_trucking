<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$pageTitle = 'About';
$activeNav = 'more';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '⚙️';
$pageLabel = 'Settings';
include __DIR__ . '/../../includes/topbar.php';
?>

<main class="max-w-2xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-6">
  <h1 class="text-xl font-bold text-gray-900 dark:text-white">About</h1>

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6 space-y-4">
    <div>
      <div class="text-sm font-semibold text-gray-900 dark:text-white">Iznahanyachay Trucking Services</div>
      <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
        Attendance, timesheet approval, driver/helper trip commission, and payroll for a small trucking fleet.
      </div>
    </div>
    <div class="border-t border-gray-200 dark:border-surface-border pt-4">
      <div class="text-sm font-semibold text-gray-900 dark:text-white mb-2">What this system does</div>
      <ul class="text-sm text-gray-500 dark:text-gray-400 space-y-1.5 list-disc list-inside">
        <li>Biometric-verified daily time in/out with admin approval</li>
        <li>Route, trip assignment, and trip-based attendance for drivers and helpers</li>
        <li>Semi-monthly payroll with real SSS, PhilHealth, and Pag-IBIG government deduction tables</li>
        <li>Employee record management (position, license, hire date, status)</li>
      </ul>
    </div>
  </div>
</main>

<?php
$navBase = BASE_PATH;
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/foot.php';
?>
