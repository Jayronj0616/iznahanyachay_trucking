<button
  type="button"
  onclick="tsToggleTheme()"
  aria-label="Toggle dark mode"
  class="w-10 h-10 rounded-xl bg-white/90 dark:bg-surface-card border border-gray-200 dark:border-surface-border flex items-center justify-center shadow-sm hover:opacity-80 transition"
>
  <!-- Moon: shown in light mode, click to go dark -->
  <svg class="block dark:hidden w-5 h-5 text-gray-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
  </svg>
  <!-- Sun: shown in dark mode, click to go light -->
  <svg class="hidden dark:block w-5 h-5 text-brand-yellow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="12" cy="12" r="5"></circle>
    <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path>
  </svg>
</button>
