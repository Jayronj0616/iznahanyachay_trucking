<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$pageTitle = 'Set Your Password';
include __DIR__ . '/../../includes/head.php';

$db = getDB();
$userId = (int) $_SESSION['user']['id'];
$forced = !empty($_SESSION['user']['must_change_password']);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $stmt = $db->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $hash = $stmt->fetchColumn();

    if (!password_verify($currentPassword, $hash)) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New password and confirmation do not match.';
    } else {
        $stmt = $db->prepare('UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?');
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
        $_SESSION['user']['must_change_password'] = false;
        header('Location: ' . BASE_PATH . '/home/');
        exit;
    }
}
?>

<main class="max-w-md mx-auto w-full px-4 pb-16 pt-12 sm:px-6 space-y-6">
  <div class="text-center space-y-2">
    <h1 class="text-xl font-bold text-gray-900 dark:text-white">Set Your Password</h1>
    <?php if ($forced): ?>
      <p class="text-sm text-gray-500 dark:text-gray-400">You're using a temporary password. Set your own before continuing.</p>
    <?php else: ?>
      <p class="text-sm text-gray-500 dark:text-gray-400">Change your account password.</p>
    <?php endif; ?>
  </div>

  <?php if ($error): ?>
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 rounded-xl p-4 text-sm"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <form method="POST" class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6 space-y-4">
    <div>
      <label for="cp-current" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"><?php echo $forced ? 'Temporary Password' : 'Current Password'; ?></label>
      <input id="cp-current" type="password" name="current_password" autocomplete="current-password" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
    </div>
    <div>
      <label for="cp-new" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Password</label>
      <input id="cp-new" type="password" name="new_password" autocomplete="new-password" minlength="8" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
    </div>
    <div>
      <label for="cp-confirm" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm New Password</label>
      <input id="cp-confirm" type="password" name="confirm_password" autocomplete="new-password" minlength="8" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
    </div>
    <button type="submit" class="w-full bg-brand-orange text-white font-bold rounded-lg px-5 py-3 hover:opacity-90 transition">Save Password</button>
  </form>

  <?php if (!$forced): ?>
    <div class="text-center">
      <a href="<?php echo BASE_PATH; ?>/more/" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">Back to Settings</a>
    </div>
  <?php else: ?>
    <form method="POST" action="<?php echo BASE_PATH; ?>/logout/" class="text-center">
      <button type="submit" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">Log out instead</button>
    </form>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/../../includes/foot.php'; ?>
