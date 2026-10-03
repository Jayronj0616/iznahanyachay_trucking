<?php
require_once __DIR__ . '/../../includes/auth.php';
requirePayrollMaster();

$pageTitle = 'Routes';
$activeNav = 'more';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '⚙️';
$pageLabel = 'Settings';
include __DIR__ . '/../../includes/topbar.php';

$db = getDB();
$error = null;
$success = null;
$isAdmin = $_SESSION['user']['role'] === 'admin';
$currentUserId = (int) $_SESSION['user']['id'];

// Panel feedback: "any amount for the trip values can be encoded, no validation" --
// Owner/Admin must confirm a rate change before it takes effect. Owner/Admin's own
// edits apply immediately (they are the top of the hierarchy, nobody above them to
// confirm for); a Payroll Master's create/update instead lands in pending_amount
// until an Owner/Admin approves or rejects it below.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $destination = trim($_POST['destination'] ?? '');
        $amount = $_POST['amount_per_trip'] ?? '';

        if (!$destination) {
            $error = 'Destination is required.';
        } elseif (!is_numeric($amount) || (float) $amount <= 0) {
            $error = 'Amount per trip must be a positive number.';
        } elseif ($isAdmin) {
            $stmt = $db->prepare('INSERT INTO routes (destination, amount_per_trip, active) VALUES (?, ?, 1)');
            $stmt->execute([$destination, (float) $amount]);
            $success = 'Route added.';
        } else {
            // A brand-new route has no live rate to protect yet, so the proposed
            // amount is the placeholder amount_per_trip too (NOT NULL column) --
            // pending_amount holds the same value and is what the approval screen
            // actually acts on; the route is inactive until approved.
            $stmt = $db->prepare('INSERT INTO routes (destination, amount_per_trip, active, pending_amount, pending_by, pending_at) VALUES (?, ?, 0, ?, ?, NOW())');
            $stmt->execute([$destination, (float) $amount, (float) $amount, $currentUserId]);
            $success = 'Route proposed. It will not be usable until an Owner/Admin approves the rate.';
        }
    } elseif ($action === 'update') {
        $routeId = (int) ($_POST['route_id'] ?? 0);
        $destination = trim($_POST['destination'] ?? '');
        $amount = $_POST['amount_per_trip'] ?? '';

        if (!$destination) {
            $error = 'Destination is required.';
        } elseif (!is_numeric($amount) || (float) $amount <= 0) {
            $error = 'Amount per trip must be a positive number.';
        } elseif ($isAdmin) {
            $stmt = $db->prepare('UPDATE routes SET destination = ?, amount_per_trip = ? WHERE id = ?');
            $stmt->execute([$destination, (float) $amount, $routeId]);
            $success = 'Route updated.';
        } else {
            $stmt = $db->prepare('UPDATE routes SET destination = ?, pending_amount = ?, pending_by = ?, pending_at = NOW() WHERE id = ?');
            $stmt->execute([$destination, (float) $amount, $currentUserId, $routeId]);
            $success = 'Rate change proposed. The current rate stays active until an Owner/Admin approves it.';
        }
    } elseif ($action === 'toggle_active' && $isAdmin) {
        $routeId = (int) ($_POST['route_id'] ?? 0);
        $stmt = $db->prepare('UPDATE routes SET active = NOT active WHERE id = ?');
        $stmt->execute([$routeId]);
        $success = 'Route status updated.';
    } elseif ($action === 'approve_rate' && $isAdmin) {
        $routeId = (int) ($_POST['route_id'] ?? 0);
        $stmt = $db->prepare('UPDATE routes SET amount_per_trip = pending_amount, active = 1, pending_amount = NULL, pending_by = NULL, pending_at = NULL WHERE id = ? AND pending_amount IS NOT NULL');
        $stmt->execute([$routeId]);
        $success = 'Rate change approved.';
    } elseif ($action === 'reject_rate' && $isAdmin) {
        $routeId = (int) ($_POST['route_id'] ?? 0);
        $stmt = $db->prepare('UPDATE routes SET pending_amount = NULL, pending_by = NULL, pending_at = NULL WHERE id = ?');
        $stmt->execute([$routeId]);
        $success = 'Rate change rejected.';
    }
}

$routes = $db->query(
    "SELECT r.*, u.name AS pending_by_name FROM routes r
     LEFT JOIN users u ON u.id = r.pending_by
     ORDER BY r.active DESC, r.destination ASC"
)->fetchAll(PDO::FETCH_ASSOC);
$pendingRoutes = array_values(array_filter($routes, fn($r) => $r['pending_amount'] !== null));
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
    <h2 class="text-gray-900 dark:text-white font-bold mb-4"><?php echo $isAdmin ? 'Add Route' : 'Propose Route'; ?></h2>
    <?php if (!$isAdmin): ?>
      <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">A proposed route stays inactive until an Owner/Admin approves its rate below.</p>
    <?php endif; ?>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="create">
      <div>
        <label for="route-destination" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Destination</label>
        <input id="route-destination" type="text" name="destination" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <div>
        <label for="route-amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount per Trip (₱)</label>
        <input id="route-amount" type="number" name="amount_per_trip" step="0.01" min="0.01" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <button type="submit" class="bg-brand-orange text-white font-bold rounded-lg px-5 py-3 hover:opacity-90 transition"><?php echo $isAdmin ? 'Add Route' : 'Propose Route'; ?></button>
    </form>
  </div>

  <?php if ($isAdmin && !empty($pendingRoutes)): ?>
  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-4">Pending Rate Changes</h2>
    <div class="overflow-x-auto">
      <table class="w-full text-sm text-left whitespace-nowrap">
        <thead>
          <tr class="text-orange-600 dark:text-brand-yellow font-bold">
            <th class="pr-6 pb-2">Destination</th>
            <th class="pr-6 pb-2 text-right">Current</th>
            <th class="pr-6 pb-2 text-right">Proposed</th>
            <th class="pr-6 pb-2">Proposed By</th>
            <th class="pb-2"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingRoutes as $route): ?>
            <tr class="border-t border-gray-200 dark:border-surface-border text-gray-900 dark:text-white">
              <td class="pr-6 py-2"><?php echo htmlspecialchars($route['destination']); ?></td>
              <td class="pr-6 py-2 text-right">₱<?php echo number_format((float) $route['amount_per_trip'], 2); ?></td>
              <td class="pr-6 py-2 text-right font-semibold">₱<?php echo number_format((float) $route['pending_amount'], 2); ?></td>
              <td class="pr-6 py-2"><?php echo htmlspecialchars($route['pending_by_name'] ?? '—'); ?></td>
              <td class="py-2">
                <div class="flex gap-2">
                  <form method="POST" data-confirm="Approve this rate change?">
                    <input type="hidden" name="action" value="approve_rate">
                    <input type="hidden" name="route_id" value="<?php echo (int) $route['id']; ?>">
                    <button type="submit" class="bg-brand-green text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:opacity-90 transition">Approve</button>
                  </form>
                  <form method="POST" data-confirm="Reject this rate change?">
                    <input type="hidden" name="action" value="reject_rate">
                    <input type="hidden" name="route_id" value="<?php echo (int) $route['id']; ?>">
                    <button type="submit" class="bg-gray-400 text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:opacity-90 transition">Reject</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

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
              <th class="pr-6 pb-2 text-right">Amount/Trip</th>
              <th class="pr-6 pb-2">Status</th>
              <th class="pb-2"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($routes as $route): ?>
              <tr class="border-t border-gray-200 dark:border-surface-border text-gray-900 dark:text-white">
                <td class="pr-6 py-2"><?php echo htmlspecialchars($route['destination']); ?></td>
                <td class="pr-6 py-2 text-right">
                  ₱<?php echo number_format((float) $route['amount_per_trip'], 2); ?>
                  <?php if ($route['pending_amount'] !== null): ?>
                    <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full bg-brand-yellow/20 text-brand-orange ml-1">Pending ₱<?php echo number_format((float) $route['pending_amount'], 2); ?></span>
                  <?php endif; ?>
                </td>
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
                      data-amount="<?php echo htmlspecialchars($isAdmin ? $route['amount_per_trip'] : ($route['pending_amount'] ?? $route['amount_per_trip']), ENT_QUOTES); ?>"
                    ><?php echo $isAdmin ? 'Edit' : 'Propose Rate'; ?></button>
                    <?php if ($isAdmin): ?>
                    <form method="POST" data-confirm="<?php echo $route['active'] ? 'Deactivate this route?' : 'Reactivate this route?'; ?>">
                      <input type="hidden" name="action" value="toggle_active">
                      <input type="hidden" name="route_id" value="<?php echo (int) $route['id']; ?>">
                      <button type="submit" class="<?php echo $route['active'] ? 'bg-gray-400' : 'bg-brand-green'; ?> text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:opacity-90 transition">
                        <?php echo $route['active'] ? 'Deactivate' : 'Activate'; ?>
                      </button>
                    </form>
                    <?php endif; ?>
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
    <h2 class="text-gray-900 dark:text-white font-bold mb-4"><?php echo $isAdmin ? 'Edit Route' : 'Propose Rate Change'; ?></h2>
    <?php if (!$isAdmin): ?>
      <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">The current rate stays active until an Owner/Admin approves this.</p>
    <?php endif; ?>
    <form method="POST" data-confirm="<?php echo $isAdmin ? 'Save changes to this route?' : 'Propose this rate change?'; ?>" class="space-y-4">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="route_id" id="ts-edit-route-id">
      <div>
        <label for="ts-edit-route-destination" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Destination</label>
        <input type="text" name="destination" id="ts-edit-route-destination" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <div>
        <label for="ts-edit-route-amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount per Trip (₱)</label>
        <input type="number" name="amount_per_trip" id="ts-edit-route-amount" step="0.01" min="0.01" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
      </div>
      <div class="flex gap-3">
        <button type="submit" class="flex-1 bg-brand-orange text-white font-bold rounded-lg px-5 py-3 hover:opacity-90 transition"><?php echo $isAdmin ? 'Save Changes' : 'Propose Rate'; ?></button>
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
