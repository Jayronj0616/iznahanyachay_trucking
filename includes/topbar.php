<?php
// Expects $pageIcon (emoji) and $pageLabel (string) to be set by the including page.
if (!isset($pageIcon)) { $pageIcon = ''; }
if (!isset($pageLabel)) { $pageLabel = ''; }
?>
<div class="flex items-center justify-between px-4 pt-4 sm:px-6 sm:pt-6 max-w-3xl mx-auto w-full">
  <div class="flex items-center gap-2 text-gray-900 dark:text-white font-semibold">
    <span><?php echo $pageIcon; ?></span>
    <span><?php echo htmlspecialchars($pageLabel); ?></span>
  </div>
  <?php include __DIR__ . '/theme-toggle.php'; ?>
</div>
