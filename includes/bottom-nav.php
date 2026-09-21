<?php
// Expects $activeNav = 'home' | 'timesheet' | 'trips' | 'overview' | 'payroll' | 'more'
// Expects $navBase = BASE_PATH for employee pages, BASE_PATH . '/admin' for admin pages
if (!isset($activeNav)) { $activeNav = ''; }
if (!isset($navBase)) { $navBase = BASE_PATH; }

// Driver and helper are paid per trip and never punch a timesheet, so that tab
// opens a calendar that can never say anything about them. They get their trips
// in the same slot instead. This is the only place the nav differs by position;
// page content has branched this way since the dashboard was built.
$navPosition = $_SESSION['user']['position'] ?? null;
if ($navPosition === null && ($_SESSION['user']['role'] ?? '') === 'employee' && isset($_SESSION['user']['id'])) {
    // Sessions created before position was stored at login predate this and would
    // otherwise show a driver the wrong tab until they logged out. Read it once and
    // keep it, rather than querying on every page from here on.
    $stmt = getDB()->prepare('SELECT position FROM employee_profiles WHERE user_id = ?');
    $stmt->execute([(int) $_SESSION['user']['id']]);
    $navPosition = $stmt->fetchColumn() ?: null;
    $_SESSION['user']['position'] = $navPosition;
}
$navShowsTrips = in_array($navPosition, ['driver', 'helper'], true);
?>
<nav class="fixed bottom-4 left-1/2 -translate-x-1/2 w-[calc(100%-2rem)] max-w-lg z-40">
  <div class="flex justify-around items-center bg-brand-orange dark:bg-surface-card dark:border dark:border-surface-border rounded-full shadow-lg px-1 py-2">

    <a href="<?php echo $navBase; ?>/home/" class="flex flex-col items-center gap-1 px-2 py-1 min-w-[56px]">
      <span class="w-8 h-8 rounded-full flex items-center justify-center <?php echo $activeNav === 'home' ? 'bg-white/25' : ''; ?>">
        <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 9.5L12 3l9 6.5"></path>
          <path d="M5 10v10a1 1 0 0 0 1 1h3v-6h6v6h3a1 1 0 0 0 1-1V10"></path>
        </svg>
      </span>
      <span class="text-[10px] font-medium text-white">Home</span>
    </a>

    <?php if ($navShowsTrips): ?>
    <a href="<?php echo $navBase; ?>/trips/" class="flex flex-col items-center gap-1 px-2 py-1 min-w-[56px]">
      <span class="w-8 h-8 rounded-full flex items-center justify-center <?php echo $activeNav === 'trips' ? 'bg-white/25' : ''; ?>">
        <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 7h11v9H3z"></path>
          <path d="M14 10h4l3 3v3h-7z"></path>
          <circle cx="7" cy="18" r="1.6"></circle>
          <circle cx="17" cy="18" r="1.6"></circle>
        </svg>
      </span>
      <span class="text-[10px] font-medium text-white">Trips</span>
    </a>
    <?php else: ?>
    <a href="<?php echo $navBase; ?>/timesheet/" class="flex flex-col items-center gap-1 px-2 py-1 min-w-[56px]">
      <span class="w-8 h-8 rounded-full flex items-center justify-center <?php echo $activeNav === 'timesheet' ? 'bg-white/25' : ''; ?>">
        <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="9"></circle>
          <path d="M12 7v5l3 3"></path>
        </svg>
      </span>
      <span class="text-[10px] font-medium text-white">Timesheet</span>
    </a>
    <?php endif; ?>

    <a href="<?php echo $navBase; ?>/home/overview/" class="flex flex-col items-center gap-1 px-2 py-1 min-w-[56px]">
      <span class="w-8 h-8 rounded-full flex items-center justify-center <?php echo $activeNav === 'overview' ? 'bg-white/25' : ''; ?>">
        <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 3v18h18"></path>
          <path d="M7 15l4-6 4 3 4-8"></path>
        </svg>
      </span>
      <span class="text-[10px] font-medium text-white">Overview</span>
    </a>

    <a href="<?php echo $navBase; ?>/payroll/" class="flex flex-col items-center gap-1 px-2 py-1 min-w-[56px]">
      <span class="w-8 h-8 rounded-full flex items-center justify-center <?php echo $activeNav === 'payroll' ? 'bg-white/25' : ''; ?>">
        <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 2v20"></path>
          <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
        </svg>
      </span>
      <span class="text-[10px] font-medium text-white">Payroll</span>
    </a>

    <a href="<?php echo $navBase; ?>/more/" class="flex flex-col items-center gap-1 px-2 py-1 min-w-[56px]">
      <span class="w-8 h-8 rounded-full flex items-center justify-center <?php echo $activeNav === 'more' ? 'bg-white/25' : ''; ?>">
        <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="currentColor" stroke="none">
          <circle cx="5" cy="12" r="1.5"></circle>
          <circle cx="12" cy="12" r="1.5"></circle>
          <circle cx="19" cy="12" r="1.5"></circle>
        </svg>
      </span>
      <span class="text-[10px] font-medium text-white">More</span>
    </a>

  </div>
</nav>
