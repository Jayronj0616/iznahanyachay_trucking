<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$pageTitle = 'Admin Home';
$activeNav = 'home';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '🏠';
$pageLabel = 'Home';
include __DIR__ . '/../../includes/topbar.php';
?>

<main class="max-w-3xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-6">
  <h1 class="text-3xl font-extrabold text-orange-600 dark:text-brand-orange">Dashboard</h1>

  <div class="flex items-center justify-between bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-full px-5 py-3 shadow-sm dark:shadow-none">
    <span class="flex items-center gap-2 text-gray-800 dark:text-gray-100 text-sm font-medium">
      <span class="w-2.5 h-2.5 rounded-full bg-gray-800 dark:bg-gray-300"></span>
      Not Clocked-In
    </span>
    <a href="<?php echo BASE_PATH; ?>/admin/home/clock-in/" class="text-brand-green text-sm font-semibold">Admin Clock-In</a>
  </div>

  <a href="<?php echo BASE_PATH; ?>/admin/home/invite/" class="flex items-center justify-between bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-full px-5 py-3 shadow-sm dark:shadow-none">
    <span class="flex items-center gap-2 text-gray-800 dark:text-gray-100 text-sm font-medium">
      <svg class="w-4 h-4 text-brand-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M7 7h11l-3-3"></path>
        <path d="M17 17H6l3 3"></path>
      </svg>
      Invite your employee
    </span>
    <span class="w-7 h-7 rounded-full bg-brand-green flex items-center justify-center">
      <svg class="w-3.5 h-3.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M5 12h14M13 6l6 6-6 6"></path>
      </svg>
    </span>
  </a>

  <div>
    <div class="flex items-center justify-between mb-3">
      <h2 class="text-lg font-bold text-gray-900 dark:text-white">Timesheet</h2>
      <span class="flex items-center gap-1 text-sm text-brand-green font-medium">
        1 April - 31 April 2026
        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg>
      </span>
    </div>
    <div class="bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl px-5 py-4 grid grid-cols-3 gap-y-4 text-center">
      <div><div class="text-xs text-gray-500 dark:text-gray-400">Not Clocked-In</div><div class="font-bold text-gray-900 dark:text-white mt-1">0</div></div>
      <div><div class="text-xs text-gray-500 dark:text-gray-400">Regular Time</div><div class="font-bold text-gray-900 dark:text-white mt-1">0.00h</div></div>
      <div><div class="text-xs text-gray-500 dark:text-gray-400">Overtime</div><div class="font-bold text-gray-900 dark:text-white mt-1">0.00h</div></div>
      <div><div class="text-xs text-gray-500 dark:text-gray-400">Days Absent</div><div class="font-bold text-gray-900 dark:text-white mt-1">0</div></div>
      <div><div class="text-xs text-gray-500 dark:text-gray-400">Paid Leave</div><div class="font-bold text-gray-900 dark:text-white mt-1">0.00h</div></div>
      <div><div class="text-xs text-gray-500 dark:text-gray-400">Unpaid Leave</div><div class="font-bold text-gray-900 dark:text-white mt-1">0.00h</div></div>
    </div>
  </div>

  <div>
    <div class="flex items-center justify-between mb-3">
      <h2 class="text-lg font-bold text-gray-900 dark:text-white">Payroll</h2>
      <span class="flex items-center gap-1 text-sm text-gray-600 dark:text-gray-300 font-medium border border-gray-200 dark:border-surface-border rounded-full px-3 py-1">
        2026
        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg>
      </span>
    </div>
    <div class="bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl px-5 py-5">
      <div class="text-center text-sm text-gray-500 dark:text-gray-400 mb-3">Net Payments</div>
      <svg viewBox="0 0 320 100" class="w-full h-24">
        <polyline points="10,80 45,70 80,85 115,75 150,88 185,72 220,84 255,76 290,82" fill="none" stroke="#E5E7EB" stroke-width="2"></polyline>
        <circle cx="10" cy="80" r="4" fill="#22C55E"></circle>
        <circle cx="45" cy="70" r="4" fill="#22C55E"></circle>
        <circle cx="80" cy="85" r="4" fill="#22C55E"></circle>
        <circle cx="115" cy="75" r="4" fill="#22C55E"></circle>
        <circle cx="150" cy="88" r="4" fill="#22C55E"></circle>
        <circle cx="185" cy="72" r="4" fill="#22C55E"></circle>
        <circle cx="220" cy="84" r="4" fill="#22C55E"></circle>
        <circle cx="255" cy="76" r="4" fill="#22C55E"></circle>
        <circle cx="290" cy="82" r="4" fill="#22C55E"></circle>
      </svg>
    </div>
  </div>
</main>

<?php
$navBase = BASE_PATH . '/admin';
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/foot.php';
?>
