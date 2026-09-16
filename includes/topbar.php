<?php
// Expects $pageIcon (emoji) and $pageLabel (string) to be set by the including page.
// Optional: $topbarExtra (raw HTML string) rendered between the title and the theme toggle,
// e.g. a page-specific action button. Left unset/empty by default.
if (!isset($pageIcon)) { $pageIcon = ''; }
if (!isset($pageLabel)) { $pageLabel = ''; }
if (!isset($topbarExtra)) { $topbarExtra = ''; }

// --- Back button -------------------------------------------------------------
// Links to the page's parent in the app's own hierarchy (/more/trips/ -> /more/),
// derived from the URL so no page has to declare anything.
//
// Deliberately NOT history.back(): browser history can re-submit a POST, or walk the
// user out of the app entirely when they opened a page directly. A parent link is
// always predictable and always stays inside the app.
//
// The five bottom-nav destinations are roots — there is nowhere above them, so they
// get no button. A page can override the target by setting $backHref/$backLabel
// before including this file.
$tsNavRoots = ['home', 'timesheet', 'home/overview', 'payroll', 'more'];
$tsSectionLabels = [
    'home'      => 'Home',
    'timesheet' => 'Timesheet',
    'payroll'   => 'Payroll',
    'more'      => 'Settings',
];

if (!isset($backHref)) {
    $backHref = null;
    $backLabel = '';

    $tsPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $tsRel = trim(substr($tsPath, strlen(BASE_PATH)), '/');

    if ($tsRel !== '' && !in_array($tsRel, $tsNavRoots, true)) {
        $tsSegments = explode('/', $tsRel);
        array_pop($tsSegments);
        $tsParent = implode('/', $tsSegments);

        if ($tsParent !== '') {
            $backHref = BASE_PATH . '/' . $tsParent . '/';
            $backLabel = $tsSectionLabels[$tsParent] ?? ucwords(str_replace('-', ' ', basename($tsParent)));
        }
    }
}
if (!isset($backLabel)) { $backLabel = ''; }
?>
<div class="flex items-center justify-between px-4 pt-4 sm:px-6 sm:pt-6 max-w-3xl mx-auto w-full">
  <div class="flex items-center gap-2 text-gray-900 dark:text-white font-semibold">
    <?php if ($backHref !== null): ?>
      <a
        href="<?php echo htmlspecialchars($backHref); ?>"
        title="Back to <?php echo htmlspecialchars($backLabel); ?>"
        aria-label="Back to <?php echo htmlspecialchars($backLabel); ?>"
        class="-ml-1 mr-0.5 w-9 h-9 flex items-center justify-center rounded-full text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-surface-card focus:outline-none focus:ring-2 focus:ring-brand-orange transition"
      >
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M15 18l-6-6 6-6"></path>
        </svg>
      </a>
    <?php endif; ?>
    <span><?php echo $pageIcon; ?></span>
    <span><?php echo htmlspecialchars($pageLabel); ?></span>
  </div>
  <div class="flex items-center gap-2">
    <?php echo $topbarExtra; ?>
    <?php include __DIR__ . '/theme-toggle.php'; ?>
  </div>
</div>
