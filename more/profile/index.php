<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$pageTitle = 'Profile Settings';
$activeNav = 'more';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '⚙️';
$pageLabel = 'Settings';
include __DIR__ . '/../../includes/topbar.php';

$db = getDB();
$userId = (int) $_SESSION['user']['id'];
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';

    if (!$name || !$email) {
        $error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif ($phone !== '' && !preg_match('/^[0-9+\-\s]{7,15}$/', $phone)) {
        $error = 'Phone must be 7-15 digits (may include +, -, spaces only).';
    } else {
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            $error = 'That email is already in use by another account.';
        } else {
            $db->beginTransaction();
            try {
                if ($newPassword !== '') {
                    if (strlen($newPassword) < 8) {
                        throw new Exception('New password must be at least 8 characters.');
                    }
                    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $stmt = $db->prepare('UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?');
                    $stmt->execute([$name, $email, $hash, $userId]);
                } else {
                    $stmt = $db->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
                    $stmt->execute([$name, $email, $userId]);
                }

                // phone/address are self-editable; license/hire_date/status are admin-only, not touched here
                $stmt = $db->prepare('SELECT id FROM employee_profiles WHERE user_id = ?');
                $stmt->execute([$userId]);
                if ($stmt->fetch()) {
                    $stmt = $db->prepare('UPDATE employee_profiles SET phone = ?, address = ? WHERE user_id = ?');
                    $stmt->execute([$phone ?: null, $address ?: null, $userId]);
                } else {
                    $stmt = $db->prepare('INSERT INTO employee_profiles (user_id, phone, address) VALUES (?, ?, ?)');
                    $stmt->execute([$userId, $phone ?: null, $address ?: null]);
                }

                $db->commit();
                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['email'] = $email;
                $success = 'Profile updated.';
            } catch (Exception $e) {
                $db->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}

$stmt = $db->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $db->prepare('SELECT * FROM employee_profiles WHERE user_id = ?');
$stmt->execute([$userId]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'phone' => '', 'address' => '', 'license_number' => null,
    'license_expiry' => null, 'hire_date' => null, 'status' => 'active',
];
?>

<main class="max-w-2xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-6">
  <h1 class="text-xl font-bold text-gray-900 dark:text-white">Profile Settings</h1>

  <?php if ($error): ?>
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 rounded-xl p-4 text-sm"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 rounded-xl p-4 text-sm"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <form method="POST" data-confirm="Save changes to your profile?" class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6 space-y-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name</label>
      <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
      <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
      <input type="tel" name="phone" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>" placeholder="09XX XXX XXXX" pattern="[0-9+\-\s]{7,15}" maxlength="15" title="7-15 digits, may include +, -, spaces" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address</label>
      <input type="text" name="address" value="<?php echo htmlspecialchars($profile['address'] ?? ''); ?>" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Password <span class="text-gray-400 font-normal">(leave blank to keep current)</span></label>
      <input type="password" name="new_password" minlength="8" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
    </div>
    <button type="submit" class="w-full bg-brand-green text-white font-bold rounded-lg px-5 py-3 hover:opacity-90 transition">Save Changes</button>
  </form>

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-1">Employment Details</h2>
    <p class="text-xs text-gray-400 dark:text-gray-500 mb-4">Set by admin — contact HR to update.</p>
    <div class="grid grid-cols-2 gap-4 text-sm">
      <div><div class="text-gray-500 dark:text-gray-400">License Number</div><div class="text-gray-900 dark:text-white font-medium"><?php echo htmlspecialchars($profile['license_number'] ?? '—'); ?></div></div>
      <div><div class="text-gray-500 dark:text-gray-400">License Expiry</div><div class="text-gray-900 dark:text-white font-medium"><?php echo htmlspecialchars($profile['license_expiry'] ?? '—'); ?></div></div>
      <div><div class="text-gray-500 dark:text-gray-400">Hire Date</div><div class="text-gray-900 dark:text-white font-medium"><?php echo htmlspecialchars($profile['hire_date'] ?? '—'); ?></div></div>
      <div><div class="text-gray-500 dark:text-gray-400">Status</div><div class="text-gray-900 dark:text-white font-medium capitalize"><?php echo htmlspecialchars($profile['status'] ?? 'active'); ?></div></div>
    </div>
  </div>
</main>

<?php
$navBase = BASE_PATH;
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/confirm-modal.php';
include __DIR__ . '/../../includes/foot.php';
?>
