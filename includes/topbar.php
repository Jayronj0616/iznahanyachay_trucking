<?php
// Expects $pageIcon (emoji) and $pageLabel (string) to be set by the including page.
// Optional: $topbarExtra (raw HTML string) rendered between the title and the theme toggle,
// e.g. a page-specific action button. Left unset/empty by default.
if (!isset($pageIcon)) { $pageIcon = ''; }
if (!isset($pageLabel)) { $pageLabel = ''; }
if (!isset($topbarExtra)) { $topbarExtra = ''; }
?>
<div class="flex items-center justify-between px-4 pt-4 sm:px-6 sm:pt-6 max-w-3xl mx-auto w-full">
  <div class="flex items-center gap-2 text-gray-900 dark:text-white font-semibold">
    <span><?php echo $pageIcon; ?></span>
    <span><?php echo htmlspecialchars($pageLabel); ?></span>
  </div>
  <div class="flex items-center gap-2">
    <?php echo $topbarExtra; ?>
    <?php include __DIR__ . '/theme-toggle.php'; ?>
  </div>
</div>
