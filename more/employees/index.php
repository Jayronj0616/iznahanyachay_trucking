<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$pageTitle = 'Employees';
$activeNav = 'more';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '⚙️';
$pageLabel = 'Settings';
include __DIR__ . '/../../includes/topbar.php';

$db = getDB();
$error = null;
$success = null;

$positions = ['driver', 'helper', 'dispatcher', 'secretary', 'maintenance', 'liaison', 'operator_manager'];

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetId = (int) ($_POST['user_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $licenseNumber = trim($_POST['license_number'] ?? '');
    $licenseExpiry = $_POST['license_expiry'] ?? '';
    $hireDate = $_POST['hire_date'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $position = $_POST['position'] ?? '';
    $monthlySalary = trim($_POST['monthly_salary'] ?? '');

    $stmt = $db->prepare("SELECT id, role FROM users WHERE id = ?");
    $stmt->execute([$targetId]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target || $target['role'] !== 'employee') {
        $error = 'Employee not found.';
    } elseif (!$name || !$email) {
        $error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (!in_array($status, ['active', 'inactive'], true)) {
        $error = 'Invalid status.';
    } elseif ($position !== '' && !in_array($position, $positions, true)) {
        $error = 'Invalid position.';
    } elseif ($monthlySalary !== '' && (!is_numeric($monthlySalary) || (float) $monthlySalary <= 0)) {
        $error = 'Monthly salary must be a positive number.';
    } else {
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $stmt->execute([$email, $targetId]);
        if ($stmt->fetch()) {
            $error = 'That email is already in use by another account.';
        } else {
            $db->beginTransaction();
            try {
                $stmt = $db->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
                $stmt->execute([$name, $email, $targetId]);

                $stmt = $db->prepare('SELECT id FROM employee_profiles WHERE user_id = ?');
                $stmt->execute([$targetId]);
                $exists = $stmt->fetch();

                $licenseExpiryVal = $licenseExpiry ?: null;
                $hireDateVal = $hireDate ?: null;
                $positionVal = $position ?: null;
                $monthlySalaryVal = $monthlySalary !== '' ? (float) $monthlySalary : null;

                if ($exists) {
                    $stmt = $db->prepare(
                        'UPDATE employee_profiles SET license_number = ?, license_expiry = ?, hire_date = ?, status = ?, position = ?, monthly_salary = ? WHERE user_id = ?'
                    );
                    $stmt->execute([$licenseNumber ?: null, $licenseExpiryVal, $hireDateVal, $status, $positionVal, $monthlySalaryVal, $targetId]);
                } else {
                    $stmt = $db->prepare(
                        'INSERT INTO employee_profiles (user_id, license_number, license_expiry, hire_date, status, position, monthly_salary) VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$targetId, $licenseNumber ?: null, $licenseExpiryVal, $hireDateVal, $status, $positionVal, $monthlySalaryVal]);
                }

                $db->commit();
                $success = 'Employee record updated.';
                $editId = 0;
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Update failed: ' . $e->getMessage();
                $editId = $targetId;
            }
        }
    }
}

$employees = $db->query(
    "SELECT u.id, u.name, u.email, ep.phone, ep.license_number, ep.license_expiry, ep.hire_date, ep.status, ep.position, ep.monthly_salary
     FROM users u
     LEFT JOIN employee_profiles ep ON ep.user_id = u.id
     WHERE u.role = 'employee' AND (ep.status IS NULL OR ep.status != 'pending')
     ORDER BY u.name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$positionLabels = [
    'driver' => 'Driver',
    'helper' => 'Helper',
    'dispatcher' => 'Dispatcher',
    'secretary' => 'Secretary',
    'maintenance' => 'Maintenance',
    'liaison' => 'Liaison',
    'operator_manager' => 'Operator Manager',
];
?>

<main class="max-w-3xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-6">
  <h1 class="text-xl font-bold text-gray-900 dark:text-white">Employees</h1>

  <?php if ($error): ?>
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 rounded-xl p-4 text-sm"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 rounded-xl p-4 text-sm"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <div class="flex justify-end">
    <a href="<?php echo BASE_PATH; ?>/home/invite/" class="bg-brand-orange text-white text-sm font-bold px-4 py-2.5 rounded-lg hover:opacity-90 transition">+ Invite Employee</a>
  </div>

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-4">All Employees</h2>
    <?php if (empty($employees)): ?>
      <p class="text-gray-500 dark:text-gray-400 text-sm">No employees yet.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
          <thead>
            <tr class="text-orange-600 dark:text-brand-yellow font-bold">
              <th class="pr-6 pb-2">Name</th>
              <th class="pr-6 pb-2">Email</th>
              <th class="pr-6 pb-2">Position</th>
              <th class="pr-6 pb-2">Monthly Salary</th>
              <th class="pr-6 pb-2">Phone</th>
              <th class="pr-6 pb-2">License</th>
              <th class="pr-6 pb-2">Hire Date</th>
              <th class="pr-6 pb-2">Status</th>
              <th class="pb-2"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($employees as $emp): ?>
              <tr class="border-t border-gray-200 dark:border-surface-border text-gray-900 dark:text-white">
                <td class="pr-6 py-2"><?php echo htmlspecialchars($emp['name']); ?></td>
                <td class="pr-6 py-2"><?php echo htmlspecialchars($emp['email']); ?></td>
                <td class="pr-6 py-2"><?php echo htmlspecialchars($positionLabels[$emp['position']] ?? '—'); ?></td>
                <td class="pr-6 py-2"><?php echo $emp['monthly_salary'] !== null ? '₱' . number_format((float) $emp['monthly_salary'], 2) : '—'; ?></td>
                <td class="pr-6 py-2"><?php echo htmlspecialchars($emp['phone'] ?? '—'); ?></td>
                <td class="pr-6 py-2"><?php echo htmlspecialchars($emp['license_number'] ?? '—'); ?></td>
                <td class="pr-6 py-2"><?php echo htmlspecialchars($emp['hire_date'] ?? '—'); ?></td>
                <td class="pr-6 py-2">
                  <span class="inline-block text-xs font-semibold px-2 py-1 rounded-full <?php echo ($emp['status'] ?? 'active') === 'active' ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'; ?>">
                    <?php echo htmlspecialchars(ucfirst($emp['status'] ?? 'active')); ?>
                  </span>
                </td>
                <td class="py-2">
                  <button
                    type="button"
                    class="ts-edit-employee-btn bg-brand-orange text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:opacity-90 transition"
                    data-id="<?php echo (int) $emp['id']; ?>"
                    data-name="<?php echo htmlspecialchars($emp['name'], ENT_QUOTES); ?>"
                    data-email="<?php echo htmlspecialchars($emp['email'], ENT_QUOTES); ?>"
                    data-license-number="<?php echo htmlspecialchars($emp['license_number'] ?? '', ENT_QUOTES); ?>"
                    data-license-expiry="<?php echo htmlspecialchars($emp['license_expiry'] ?? '', ENT_QUOTES); ?>"
                    data-hire-date="<?php echo htmlspecialchars($emp['hire_date'] ?? '', ENT_QUOTES); ?>"
                    data-status="<?php echo htmlspecialchars($emp['status'] ?? 'active', ENT_QUOTES); ?>"
                    data-position="<?php echo htmlspecialchars($emp['position'] ?? '', ENT_QUOTES); ?>"
                    data-monthly-salary="<?php echo htmlspecialchars($emp['monthly_salary'] ?? '', ENT_QUOTES); ?>"
                  >Edit</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</main>

<!-- Edit Employee modal -->
<div id="ts-edit-employee-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center px-4">
  <div id="ts-edit-employee-backdrop" class="absolute inset-0 bg-black/50"></div>
  <div class="relative bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl shadow-xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto">
    <h2 class="text-gray-900 dark:text-white font-bold mb-4">Edit: <span id="ts-edit-employee-name"></span></h2>
    <form method="POST" data-confirm="Save changes to this employee's record?" class="space-y-4">
      <input type="hidden" name="user_id" id="ts-edit-user-id">
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name</label>
        <input type="text" name="name" id="ts-edit-name" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
        <input type="email" name="email" id="ts-edit-email" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Position</label>
        <select name="position" id="ts-edit-position" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
          <option value="">— Not set —</option>
          <?php foreach ($positions as $p): ?>
            <option value="<?php echo $p; ?>"><?php echo htmlspecialchars($positionLabels[$p]); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Monthly Salary (₱)</label>
        <input type="number" name="monthly_salary" id="ts-edit-monthly-salary" step="0.01" min="0.01" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <div id="ts-edit-license-fields" class="space-y-4 hidden">
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">License Number</label>
        <input type="text" name="license_number" id="ts-edit-license-number" maxlength="50" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">License Expiry</label>
        <input type="date" name="license_expiry" id="ts-edit-license-expiry" style="color-scheme: light;" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hire Date</label>
        <input type="date" name="hire_date" id="ts-edit-hire-date" style="color-scheme: light;" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
        <select name="status" id="ts-edit-status" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <div class="flex gap-3">
        <button type="submit" class="flex-1 bg-brand-orange text-white font-bold rounded-lg px-5 py-3 hover:opacity-90 transition">Save Changes</button>
        <button type="button" id="ts-edit-employee-cancel" class="flex-1 border border-gray-300 dark:border-surface-border text-gray-700 dark:text-gray-300 font-bold rounded-lg px-5 py-3 hover:bg-gray-100 dark:hover:bg-white/5 transition">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var modal = document.getElementById('ts-edit-employee-modal');
  var backdrop = document.getElementById('ts-edit-employee-backdrop');
  var cancelBtn = document.getElementById('ts-edit-employee-cancel');
  var nameHeading = document.getElementById('ts-edit-employee-name');
  var licenseFields = document.getElementById('ts-edit-license-fields');

  var fields = {
    id: document.getElementById('ts-edit-user-id'),
    name: document.getElementById('ts-edit-name'),
    email: document.getElementById('ts-edit-email'),
    position: document.getElementById('ts-edit-position'),
    licenseNumber: document.getElementById('ts-edit-license-number'),
    licenseExpiry: document.getElementById('ts-edit-license-expiry'),
    hireDate: document.getElementById('ts-edit-hire-date'),
    status: document.getElementById('ts-edit-status'),
    monthlySalary: document.getElementById('ts-edit-monthly-salary')
  };

  function openModal(btn) {
    fields.id.value = btn.dataset.id;
    fields.name.value = btn.dataset.name;
    fields.email.value = btn.dataset.email;
    fields.position.value = btn.dataset.position || '';
    fields.licenseNumber.value = btn.dataset.licenseNumber;
    fields.licenseExpiry.value = btn.dataset.licenseExpiry;
    fields.hireDate.value = btn.dataset.hireDate;
    fields.status.value = btn.dataset.status || 'active';
    fields.monthlySalary.value = btn.dataset.monthlySalary || '';
    nameHeading.textContent = btn.dataset.name;
    toggleLicenseFields();
    modal.classList.remove('hidden');
  }

  function toggleLicenseFields() {
    licenseFields.classList.toggle('hidden', fields.position.value !== 'driver');
  }

  function closeModal() {
    modal.classList.add('hidden');
  }

  document.querySelectorAll('.ts-edit-employee-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openModal(btn);
    });
  });

  fields.position.addEventListener('change', toggleLicenseFields);
  cancelBtn.addEventListener('click', closeModal);
  backdrop.addEventListener('click', closeModal);
})();
</script>

<?php
$navBase = BASE_PATH;
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/confirm-modal.php';
include __DIR__ . '/../../includes/foot.php';
?>
