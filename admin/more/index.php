<?php
$pageTitle = 'Admin Settings';
$activeNav = 'more';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '⚙️';
$pageLabel = 'Settings';
include __DIR__ . '/../../includes/topbar.php';
?>

<main class="max-w-3xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6">
  <h1 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Settings</h1>

  <div class="bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl divide-y divide-gray-200 dark:divide-surface-border overflow-hidden">

    <a href="<?php echo BASE_PATH; ?>/more/profile/" class="flex items-start gap-3 px-5 py-4 hover:bg-gray-50 dark:hover:bg-white/5 transition">
      <span class="text-xl">👤</span>
      <span>
        <span class="block font-semibold text-gray-900 dark:text-white">Profile Settings</span>
        <span class="block text-sm text-gray-500 dark:text-gray-400">Manage account</span>
      </span>
    </a>

    <a href="<?php echo BASE_PATH; ?>/more/privacy-policy/" class="flex items-start gap-3 px-5 py-4 hover:bg-gray-50 dark:hover:bg-white/5 transition">
      <span class="text-xl">🔒</span>
      <span>
        <span class="block font-semibold text-gray-900 dark:text-white">Privacy Policy</span>
        <span class="block text-sm text-gray-500 dark:text-gray-400">System policies</span>
      </span>
    </a>

    <a href="<?php echo BASE_PATH; ?>/more/about/" class="flex items-start gap-3 px-5 py-4 hover:bg-gray-50 dark:hover:bg-white/5 transition">
      <span class="text-xl">ℹ️</span>
      <span>
        <span class="block font-semibold text-gray-900 dark:text-white">About</span>
        <span class="block text-sm text-gray-500 dark:text-gray-400">System info</span>
      </span>
    </a>

  </div>
</main>

<?php
$navBase = BASE_PATH . '/admin';
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/foot.php';
?>
