<?php
// Expects $activeNav = 'home' | 'timesheet' | 'overview' | 'payroll' | 'more'
// Expects $navBase = BASE_PATH for employee pages, BASE_PATH . '/admin' for admin pages
if (!isset($activeNav)) { $activeNav = ''; }
if (!isset($navBase)) { $navBase = BASE_PATH; }
?>
<nav class="fixed bottom-4 left-1/2 -translate-x-1/2 w-[calc(100%-2rem)] max-w-lg z-40">
  <div class="flex justify-around items-center bg-brand-orange dark:bg-surface-card dark:border dark:border-surface-border rounded-full shadow-lg px-1 py-2">

    <a href="<?php echo $navBase; ?>/home/" class="flex flex-col items-center gap-1 px-2 py-1 min-w-[56px]">
      <span class="w-8 h-8 rounded-full flex items-center justify-center <?php echo $activeNav === 'home' ? 'bg-brand-green' : ''; ?>">
        <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 9.5L12 3l9 6.5"></path>
          <path d="M5 10v10a1 1 0 0 0 1 1h3v-6h6v6h3a1 1 0 0 0 1-1V10"></path>
        </svg>
      </span>
      <span class="text-[10px] font-medium text-white">Home</span>
    </a>

    <a href="<?php echo $navBase; ?>/timesheet/" class="flex flex-col items-center gap-1 px-2 py-1 min-w-[56px]">
      <span class="w-8 h-8 rounded-full flex items-center justify-center <?php echo $activeNav === 'timesheet' ? 'bg-brand-green' : ''; ?>">
        <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="9"></circle>
          <path d="M12 7v5l3 3"></path>
        </svg>
      </span>
      <span class="text-[10px] font-medium text-white">Timesheet</span>
    </a>

    <a href="<?php echo $navBase; ?>/home/overview/" class="flex flex-col items-center gap-1 px-2 py-1 min-w-[56px]">
      <span class="w-8 h-8 rounded-full flex items-center justify-center <?php echo $activeNav === 'overview' ? 'bg-brand-green' : ''; ?>">
        <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 3v18h18"></path>
          <path d="M7 15l4-6 4 3 4-8"></path>
        </svg>
      </span>
      <span class="text-[10px] font-medium text-white">Overview</span>
    </a>

    <a href="<?php echo $navBase; ?>/payroll/" class="flex flex-col items-center gap-1 px-2 py-1 min-w-[56px]">
      <span class="w-8 h-8 rounded-full flex items-center justify-center <?php echo $activeNav === 'payroll' ? 'bg-brand-green' : ''; ?>">
        <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 2v20"></path>
          <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
        </svg>
      </span>
      <span class="text-[10px] font-medium text-white">Payroll</span>
    </a>

    <a href="<?php echo $navBase; ?>/more/" class="flex flex-col items-center gap-1 px-2 py-1 min-w-[56px]">
      <span class="w-8 h-8 rounded-full flex items-center justify-center <?php echo $activeNav === 'more' ? 'bg-brand-green' : ''; ?>">
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
