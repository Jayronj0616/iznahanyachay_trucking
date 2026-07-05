<?php
$pageTitle = 'Admin Timesheet';
$activeNav = 'timesheet';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '⏱️';
$pageLabel = 'Timesheet';
include __DIR__ . '/../../includes/topbar.php';

$daysInMonth = (int) date('t');
$monthAbbr = date('M');
$today = (int) date('j');
?>

<main class="max-w-3xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6">

  <!-- LIGHT MODE: calendar list, tap to add -->
  <div class="dark:hidden">
    <div class="flex items-center justify-between pb-3 mb-2 border-b border-gray-200">
      <span class="flex items-center gap-2 text-gray-800 font-medium text-sm">
        📅 This Month (<?php echo $monthAbbr; ?>)
      </span>
      <a href="#today" class="text-brand-green text-sm font-semibold">Today</a>
    </div>

    <div class="divide-y divide-gray-200">
      <?php for ($d = 1; $d <= $daysInMonth; $d++):
        $ts = mktime(0, 0, 0, (int) date('n'), $d, (int) date('Y'));
        $dayAbbr = date('D', $ts);
        $isToday = $d === $today;
      ?>
      <a
        <?php echo $isToday ? 'id="today"' : ''; ?>
        href="<?php echo BASE_PATH; ?>/timesheet/entry/?date=<?php echo date('Y-m-d', $ts); ?>"
        class="flex items-center justify-between py-4 px-4 -mx-4 <?php echo $isToday ? 'bg-brand-orange rounded-lg text-white' : 'text-gray-800'; ?>"
      >
        <span class="flex items-center gap-3">
          <span class="text-right w-6 font-semibold"><?php echo $d; ?></span>
          <span class="text-xs <?php echo $isToday ? 'text-white/80' : 'text-gray-400'; ?> w-10"><?php echo $dayAbbr; ?></span>
          <span class="text-sm italic <?php echo $isToday ? 'text-white/90' : 'text-gray-400'; ?>">Tap to Add</span>
        </span>
        <svg class="w-4 h-4 <?php echo $isToday ? 'text-white' : 'text-gray-400'; ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 5v14M5 12h14"></path>
        </svg>
      </a>
      <?php endfor; ?>
    </div>
  </div>

  <!-- DARK MODE: manual add form, QR scan, records, details -->
  <div class="hidden dark:block space-y-6">

    <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
      <h2 class="text-gray-900 dark:text-white font-bold mb-4">Add Time (Manual)</h2>
      <div class="space-y-3">
        <input type="text" placeholder="Employee Name" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-3 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:border-brand-yellow">
        <select class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-3 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
          <option>Employee</option>
          <option>Admin</option>
        </select>
        <input type="date" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-3 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
        <input type="time" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-3 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
        <input type="time" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-3 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
        <a href="<?php echo BASE_PATH; ?>/admin/timesheet/" class="block text-center bg-brand-green text-white font-bold rounded-lg px-5 py-3 hover:opacity-90 transition">Save</a>
      </div>
    </div>

    <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
      <h2 class="text-gray-900 dark:text-white font-bold mb-1">Scan QR (Auto Time In/Out)</h2>
      <p class="text-gray-500 dark:text-gray-400 text-sm">Waiting for scan...</p>
    </div>

    <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
      <h2 class="text-gray-900 dark:text-white font-bold">Records</h2>
    </div>

    <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
      <h2 class="text-gray-900 dark:text-white font-bold mb-3">Details</h2>
      <div class="bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-3 text-gray-500 dark:text-gray-400 text-sm">Click record</div>
    </div>

  </div>

</main>

<?php
$navBase = BASE_PATH . '/admin';
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/foot.php';
?>
