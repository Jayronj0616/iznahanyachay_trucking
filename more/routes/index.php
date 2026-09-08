<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$pageTitle = 'Routes';
$activeNav = 'more';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '⚙️';
$pageLabel = 'Settings';
include __DIR__ . '/../../includes/topbar.php';

$db = getDB();
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $destination = trim($_POST['destination'] ?? '');
        $amount = $_POST['amount_per_trip'] ?? '';

        if (!$destination) {
            $error = 'Destination is required.';
        } elseif (!is_numeric($amount) || (float) $amount <= 0) {
            $error = 'Amount per trip must be a positive number.';
        } else {
            $stmt = $db->prepare('INSERT INTO routes (destination, amount_per_trip, active) VALUES (?, ?, 1)');
            $stmt->execute([$destination, (float) $amount]);
            $success = 'Route added.';
        }
    } elseif ($action === 'update') {
        $routeId = (int) ($_POST['route_id'] ?? 0);
        $destination = trim($_POST['destination'] ?? '');
        $amount = $_POST['amount_per_trip'] ?? '';

        if (!$destination) {
            $error = 'Destination is required.';
        } elseif (!is_numeric($amount) || (float) $amount <= 0) {
            $error = 'Amount per trip must be a positive number.';
        } else {
            $stmt = $db->prepare('UPDATE routes SET destination = ?, amount_per_trip = ? WHERE id = ?');
            $stmt->execute([$destination, (float) $amount, $routeId]);
            $success = 'Route updated.';
        }
    } elseif ($action === 'toggle_active') {
        $routeId = (int) ($_POST['route_id'] ?? 0);
        $stmt = $db->prepare('UPDATE routes SET active = NOT active WHERE id = ?');
        $stmt->execute([$routeId]);
        $success = 'Route status updated.';
    }
}

$routes = $db->query('SELECT * FROM routes ORDER BY active DESC, destination ASC')->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="max-w-3xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-6">
  <h1 class="text-xl font-bold text-gray-900 dark:text-white">Routes</h1>

  <?php if ($error): ?>
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 rounded-xl p-4 text-sm"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 rounded-xl p-4 text-sm"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-4">Add Route</h2>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="create">
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Destination</label>
        <input type="text" name="destination" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount per Trip (₱)</label>
        <input type="number" name="amount_per_trip" step="0.01" min="0.01" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <button type="submit" class="bg-brand-orange text-white font-bold rounded-lg px-5 py-3 hover:opacity-90 transition">Add Route</button>
    </form>
  </div>

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-4">All Routes</h2>
    <?php if (empty($routes)): ?>
      <p class="text-gray-500 dark:text-gray-400 text-sm">No routes yet.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
          <thead>
            <tr class="text-orange-600 dark:text-brand-yellow font-bold">
              <th class="pr-6 pb-2">Destination</th>
              <th class="pr-6 pb-2">Amount/Trip</th>
              <th class="pr-6 pb-2">Status</th>
              <th class="pb-2"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($routes as $route): ?>
              <tr class="border-t border-gray-200 dark:border-surface-border text-gray-900 dark:text-white">
                <td class="pr-6 py-2"><?php echo htmlspecialchars($route['destination']); ?></td>
                <td class="pr-6 py-2">₱<?php echo number_format((float) $route['amount_per_trip'], 2); ?></td>
                <td class="pr-6 py-2">
                  <span class="inline-block text-xs font-semibold px-2 py-1 rounded-full <?php echo $route['active'] ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'; ?>">
                    <?php echo $route['active'] ? 'Active' : 'Inactive'; ?>
                  </span>
                </td>
                <td class="py-2">
                  <div class="flex gap-2">
                    <button
                      type="button"
                      class="ts-edit-route-btn bg-brand-orange text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:opacity-90 transition"
                      data-id="<?php echo (int) $route['id']; ?>"
                      data-destination="<?php echo htmlspecialchars($route['destination'], ENT_QUOTES); ?>"
                      data-amount="<?php echo htmlspecialchars($route['amount_per_trip'], ENT_QUOTES); ?>"
                    >Edit</button>
                    <form method="POST" data-confirm="<?php echo $route['active'] ? 'Deactivate this route?' : 'Reactivate this route?'; ?>">
                      <input type="hidden" name="action" value="toggle_active">
                      <input type="hidden" name="route_id" value="<?php echo (int) $route['id']; ?>">
                      <button type="submit" class="<?php echo $route['active'] ? 'bg-gray-400' : 'bg-brand-green'; ?> text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:opacity-90 transition">
                        <?php echo $route['active'] ? 'Deactivate' : 'Activate'; ?>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</main>

<!-- Edit Route modal -->
<div id="ts-edit-route-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center px-4">
  <div id="ts-edit-route-backdrop" class="absolute inset-0 bg-black/50"></div>
  <div class="relative bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl shadow-xl max-w-md w-full p-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-4">Edit Route</h2>
    <form method="POST" data-confirm="Save changes to this route?" class="space-y-4">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="route_id" id="ts-edit-route-id">
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Destination</label>
        <input type="text" name="destination" id="ts-edit-route-destination" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount per Trip (₱)</label>
        <input type="number" name="amount_per_trip" id="ts-edit-route-amount" step="0.01" min="0.01" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <div class="flex gap-3">
        <button type="submit" class="flex-1 bg-brand-orange text-white font-bold rounded-lg px-5 py-3 hover:opacity-90 transition">Save Changes</button>
        <button type="button" id="ts-edit-route-cancel" class="flex-1 border border-gray-300 dark:border-surface-border text-gray-700 dark:text-gray-300 font-bold rounded-lg px-5 py-3 hover:bg-gray-100 dark:hover:bg-white/5 transition">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var modal = document.getElementById('ts-edit-route-modal');
  var backdrop = document.getElementById('ts-edit-route-backdrop');
  var cancelBtn = document.getElementById('ts-edit-route-cancel');

  var fields = {
    id: document.getElementById('ts-edit-route-id'),
    destination: document.getElementById('ts-edit-route-destination'),
    amount: document.getElementById('ts-edit-route-amount')
  };

  function openModal(btn) {
    fields.id.value = btn.dataset.id;
    fields.destination.value = btn.dataset.destination;
    fields.amount.value = btn.dataset.amount;
    modal.classList.remove('hidden');
  }

  function closeModal() {
    modal.classList.add('hidden');
  }

  document.querySelectorAll('.ts-edit-route-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openModal(btn);
    });
  });

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
