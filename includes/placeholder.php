<?php
// Expects $placeholderTitle and optionally $placeholderNote to be set by the including page.
if (!isset($placeholderNote)) { $placeholderNote = 'This page isn\'t built yet.'; }
?>
<main class="max-w-3xl mx-auto w-full px-4 pb-32 pt-16 sm:px-6 text-center">
  <div class="max-w-sm mx-auto">
    <h1 class="text-xl font-bold text-gray-900 dark:text-white mb-2"><?php echo htmlspecialchars($placeholderTitle); ?></h1>
    <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($placeholderNote); ?></p>
    <a href="javascript:history.back()" class="inline-block mt-6 text-sm font-semibold text-brand-orange dark:text-brand-yellow underline">Go back</a>
  </div>
</main>
