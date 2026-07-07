<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$pageTitle = 'Invite Employee';
$activeNav = 'home';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '🏠';
$pageLabel = 'Home';
include __DIR__ . '/../../includes/topbar.php';

$db = getDB();
$error = null;
$success = null;
$createdPassword = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $licenseNumber = trim($_POST['license_number'] ?? '');
    $licenseExpiry = $_POST['license_expiry'] ?? '';
    $hireDate = $_POST['hire_date'] ?? '';
    $status = $_POST['status'] ?? 'active';

    if (!$name || !$email || !$password) {
        $error = 'Name, email, and temporary password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!in_array($status, ['active', 'inactive'], true)) {
        $error = 'Invalid status.';
    } else {
        $licenseExpiryVal = $licenseExpiry ?: null;
        $hireDateVal = $hireDate ?: null;

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), 'employee']);
            $newUserId = (int) $db->lastInsertId();

            $stmt = $db->prepare(
                'INSERT INTO employee_profiles (user_id, license_number, license_expiry, hire_date, status) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$newUserId, $licenseNumber ?: null, $licenseExpiryVal, $hireDateVal, $status]);

            $db->commit();
            $success = 'Employee account created.';
            $createdPassword = $password;
        } catch (PDOException $e) {
            $db->rollBack();
            if ((int) $e->getCode() === 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error = 'That email is already in use by another account.';
            } else {
                $error = 'Creation failed: ' . $e->getMessage();
            }
        }
    }
}
?>

<main class="max-w-3xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-6">
  <h1 class="text-xl font-bold text-gray-900 dark:text-white">Invite Employee</h1>

  <?php if ($error): ?>
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 rounded-xl p-4 text-sm"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 rounded-xl p-4 text-sm space-y-1">
      <p><?php echo htmlspecialchars($success); ?></p>
      <p>Temporary password: <span class="font-mono font-bold"><?php echo htmlspecialchars($createdPassword); ?></span> — share this with the employee directly. It will not be shown again.</p>
    </div>
  <?php endif; ?>

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
    <form method="POST" data-confirm="Create this employee account?" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name</label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Temporary Password</label>
        <input type="text" name="password" minlength="8" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Min 8 characters. Employee should change this after first login (no forced-change flow yet).</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">License Number</label>
        <input type="text" name="license_number" value="<?php echo htmlspecialchars($_POST['license_number'] ?? ''); ?>" maxlength="50" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">License Expiry</label>
        <input type="date" name="license_expiry" value="<?php echo htmlspecialchars($_POST['license_expiry'] ?? ''); ?>" style="color-scheme: light;" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hire Date</label>
        <input type="date" name="hire_date" value="<?php echo htmlspecialchars($_POST['hire_date'] ?? ''); ?>" style="color-scheme: light;" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
        <select name="status" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
          <option value="active" <?php echo ($_POST['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
          <option value="inactive" <?php echo ($_POST['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
        </select>
      </div>
      <button type="submit" class="w-full bg-brand-green text-white font-bold rounded-lg px-5 py-3 hover:opacity-90 transition">Create Employee Account</button>
    </form>
  </div>
</main>

<?php
$navBase = BASE_PATH;
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/confirm-modal.php';
include __DIR__ . '/../../includes/foot.php';
?>
