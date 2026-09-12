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

$positions = ['driver', 'helper', 'dispatcher', 'secretary', 'maintenance', 'liaison', 'operator_manager'];
$positionLabels = [
    'driver' => 'Driver',
    'helper' => 'Helper',
    'dispatcher' => 'Dispatcher',
    'secretary' => 'Secretary',
    'maintenance' => 'Maintenance',
    'liaison' => 'Liaison',
    'operator_manager' => 'Operator Manager',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $licenseNumber = trim($_POST['license_number'] ?? '');
    $licenseExpiry = $_POST['license_expiry'] ?? '';
    $hireDate = $_POST['hire_date'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $position = $_POST['position'] ?? '';

    if (!$name || !$email || !$password) {
        $error = 'Name, email, and temporary password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!in_array($status, ['active', 'inactive'], true)) {
        $error = 'Invalid status.';
    } elseif ($position !== '' && !in_array($position, $positions, true)) {
        $error = 'Invalid position.';
    } else {
        $licenseExpiryVal = $licenseExpiry ?: null;
        $hireDateVal = $hireDate ?: null;
        $positionVal = $position ?: null;

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), 'employee']);
            $newUserId = (int) $db->lastInsertId();

            $stmt = $db->prepare(
                'INSERT INTO employee_profiles (user_id, license_number, license_expiry, hire_date, status, position) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$newUserId, $licenseNumber ?: null, $licenseExpiryVal, $hireDateVal, $status, $positionVal]);

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
        <label for="invite-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name</label>
        <input id="invite-name" type="text" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <div>
        <label for="invite-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
        <input id="invite-email" type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <div>
        <label for="invite-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Temporary Password</label>
        <input id="invite-password" type="text" name="password" minlength="8" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Min 8 characters. Employee should change this after first login (no forced-change flow yet).</p>
      </div>
      <div>
        <label for="ts-invite-position" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Position</label>
        <select name="position" id="ts-invite-position" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
          <option value="">— Not set —</option>
          <?php foreach ($positions as $p): ?>
            <option value="<?php echo $p; ?>" <?php echo ($_POST['position'] ?? '') === $p ? 'selected' : ''; ?>><?php echo htmlspecialchars($positionLabels[$p]); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div id="ts-invite-license-fields" class="space-y-4 hidden">
      <div>
        <label for="invite-license-number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">License Number</label>
        <input id="invite-license-number" type="text" name="license_number" value="<?php echo htmlspecialchars($_POST['license_number'] ?? ''); ?>" maxlength="50" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <div>
        <label for="invite-license-expiry" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">License Expiry</label>
        <input id="invite-license-expiry" type="date" name="license_expiry" value="<?php echo htmlspecialchars($_POST['license_expiry'] ?? ''); ?>" style="color-scheme: light;" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      </div>
      <div>
        <label for="invite-hire-date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hire Date</label>
        <input id="invite-hire-date" type="date" name="hire_date" value="<?php echo htmlspecialchars($_POST['hire_date'] ?? ''); ?>" style="color-scheme: light;" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <div>
        <label for="invite-status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
        <select id="invite-status" name="status" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
          <option value="active" <?php echo ($_POST['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
          <option value="inactive" <?php echo ($_POST['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
        </select>
      </div>
      <button type="submit" class="w-full bg-brand-orange text-white font-bold rounded-lg px-5 py-3 hover:opacity-90 transition">Create Employee Account</button>
    </form>
  </div>
</main>

<script>
(function () {
  var positionSelect = document.getElementById('ts-invite-position');
  var licenseFields = document.getElementById('ts-invite-license-fields');

  function toggleLicenseFields() {
    licenseFields.classList.toggle('hidden', positionSelect.value !== 'driver');
  }

  positionSelect.addEventListener('change', toggleLicenseFields);
  toggleLicenseFields();
})();
</script>

<?php
$navBase = BASE_PATH;
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/confirm-modal.php';
include __DIR__ . '/../../includes/foot.php';
?>
