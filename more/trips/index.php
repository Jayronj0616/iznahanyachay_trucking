<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$pageTitle = 'Trips';
$activeNav = 'more';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '⚙️';
$pageLabel = 'Settings';
include __DIR__ . '/../../includes/topbar.php';

$db = getDB();
$error = null;
$success = null;

function tripFindActiveRoute(PDO $db, int $routeId): array|false {
    $stmt = $db->prepare('SELECT amount_per_trip FROM routes WHERE id = ? AND active = 1');
    $stmt->execute([$routeId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function tripIsValidDriver(PDO $db, int $driverId): bool {
    $stmt = $db->prepare(
        "SELECT u.id FROM users u JOIN employee_profiles ep ON ep.user_id = u.id
         WHERE u.id = ? AND ep.position = 'driver' AND ep.status = 'active'"
    );
    $stmt->execute([$driverId]);
    return (bool) $stmt->fetch();
}

function tripIsValidHelper(PDO $db, ?int $helperId): bool {
    if ($helperId === null) {
        return true;
    }
    $stmt = $db->prepare(
        "SELECT u.id FROM users u JOIN employee_profiles ep ON ep.user_id = u.id
         WHERE u.id = ? AND ep.position = 'helper' AND ep.status = 'active'"
    );
    $stmt->execute([$helperId]);
    return (bool) $stmt->fetch();
}

// Excludes $excludeTripId so editing a trip doesn't flag its own current driver/helper as "busy".
function tripDriverOrHelperBusy(PDO $db, int $driverId, ?int $helperId, ?int $excludeTripId = null): bool {
    $sql = "SELECT id FROM trips_new WHERE status = 'assigned' AND (driver_id = ? OR helper_id = ? OR driver_id = ? OR helper_id = ?)";
    $params = [$driverId, $driverId, $helperId, $helperId];
    if ($excludeTripId !== null) {
        $sql .= ' AND id != ?';
        $params[] = $excludeTripId;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (bool) $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $routeId = (int) ($_POST['route_id'] ?? 0);
        $driverId = (int) ($_POST['driver_id'] ?? 0);
        $helperId = isset($_POST['helper_id']) && $_POST['helper_id'] !== '' ? (int) $_POST['helper_id'] : null;

        $route = tripFindActiveRoute($db, $routeId);
        $validDriver = tripIsValidDriver($db, $driverId);
        $validHelper = tripIsValidHelper($db, $helperId);
        $driverOrHelperBusy = $validDriver && $validHelper && tripDriverOrHelperBusy($db, $driverId, $helperId);

        if (!$route) {
            $error = 'Please select a valid active route.';
        } elseif (!$validDriver) {
            $error = 'Please select a valid active driver.';
        } elseif (!$validHelper) {
            $error = 'Please select a valid active helper.';
        } elseif ($driverOrHelperBusy) {
            $error = 'The selected driver or helper already has a trip in progress. Mark it completed first.';
        } else {
            $stmt = $db->prepare(
                'INSERT INTO trips_new (route_id, driver_id, helper_id, amount_per_trip, status, started_at)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$routeId, $driverId, $helperId, $route['amount_per_trip'], 'assigned']);
            $success = 'Trip assigned.';
        }
    } elseif ($action === 'edit') {
        $tripId = (int) ($_POST['trip_id'] ?? 0);
        $routeId = (int) ($_POST['route_id'] ?? 0);
        $driverId = (int) ($_POST['driver_id'] ?? 0);
        $helperId = isset($_POST['helper_id']) && $_POST['helper_id'] !== '' ? (int) $_POST['helper_id'] : null;

        $route = tripFindActiveRoute($db, $routeId);
        $validDriver = tripIsValidDriver($db, $driverId);
        $validHelper = tripIsValidHelper($db, $helperId);
        $driverOrHelperBusy = $validDriver && $validHelper && tripDriverOrHelperBusy($db, $driverId, $helperId, $tripId);

        $stmt = $db->prepare("SELECT id FROM trips_new WHERE id = ? AND status = 'assigned'");
        $stmt->execute([$tripId]);
        $tripExists = (bool) $stmt->fetch();

        if (!$tripExists) {
            $error = 'This trip can no longer be edited (already completed, cancelled, or not found).';
        } elseif (!$route) {
            $error = 'Please select a valid active route.';
        } elseif (!$validDriver) {
            $error = 'Please select a valid active driver.';
        } elseif (!$validHelper) {
            $error = 'Please select a valid active helper.';
        } elseif ($driverOrHelperBusy) {
            $error = 'The selected driver or helper already has another trip in progress.';
        } else {
            $stmt = $db->prepare(
                "UPDATE trips_new SET route_id = ?, driver_id = ?, helper_id = ?, amount_per_trip = ?
                 WHERE id = ? AND status = 'assigned'"
            );
            $stmt->execute([$routeId, $driverId, $helperId, $route['amount_per_trip'], $tripId]);
            $success = 'Trip updated.';
        }
    } elseif ($action === 'cancel') {
        $tripId = (int) ($_POST['trip_id'] ?? 0);
        $stmt = $db->prepare("UPDATE trips_new SET status = 'cancelled', cancelled_at = NOW() WHERE id = ? AND status = 'assigned'");
        $stmt->execute([$tripId]);
        if ($stmt->rowCount() > 0) {
            $success = 'Trip cancelled.';
        } else {
            $error = 'This trip can no longer be cancelled (already completed, cancelled, or not found).';
        }
    } elseif ($action === 'complete') {
        $tripId = (int) ($_POST['trip_id'] ?? 0);

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT driver_id, helper_id FROM trips_new WHERE id = ? AND status = 'assigned' FOR UPDATE");
            $stmt->execute([$tripId]);
            $trip = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$trip) {
                $db->rollBack();
                $success = 'Trip was already completed or not found.';
            } else {
                $stmt = $db->prepare("UPDATE trips_new SET status = 'completed', completed_at = NOW() WHERE id = ? AND status = 'assigned'");
                $stmt->execute([$tripId]);

                $stmt = $db->prepare('INSERT INTO trip_attendance (trip_id, user_id, role, date) VALUES (?, ?, ?, CURDATE())');
                $stmt->execute([$tripId, $trip['driver_id'], 'driver']);
                if ($trip['helper_id'] !== null) {
                    $stmt->execute([$tripId, $trip['helper_id'], 'helper']);
                }

                $db->commit();
                $success = 'Trip marked completed.';
            }
        } catch (PDOException $e) {
            $db->rollBack();
            $error = 'Failed to mark trip completed: ' . $e->getMessage();
        }
    }
}

$activeRoutes = $db->query("SELECT id, destination, amount_per_trip FROM routes WHERE active = 1 ORDER BY destination ASC")->fetchAll(PDO::FETCH_ASSOC);

$drivers = $db->query(
    "SELECT u.id, u.name FROM users u JOIN employee_profiles ep ON ep.user_id = u.id
     WHERE ep.position = 'driver' AND ep.status = 'active' ORDER BY u.name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$helpers = $db->query(
    "SELECT u.id, u.name FROM users u JOIN employee_profiles ep ON ep.user_id = u.id
     WHERE ep.position = 'helper' AND ep.status = 'active' ORDER BY u.name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$trips = $db->query(
    "SELECT t.*, r.destination, d.name AS driver_name, h.name AS helper_name
     FROM trips_new t
     JOIN routes r ON r.id = t.route_id
     JOIN users d ON d.id = t.driver_id
     LEFT JOIN users h ON h.id = t.helper_id
     ORDER BY t.created_at DESC
     LIMIT 100"
)->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="max-w-3xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-6">
  <h1 class="text-xl font-bold text-gray-900 dark:text-white">Trips</h1>

  <?php if ($error): ?>
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 rounded-xl p-4 text-sm"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 rounded-xl p-4 text-sm"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-4">Assign Trip</h2>
    <?php if (empty($activeRoutes)): ?>
      <p class="text-gray-500 dark:text-gray-400 text-sm">No active routes. Add one in Routes first.</p>
    <?php elseif (empty($drivers)): ?>
      <p class="text-gray-500 dark:text-gray-400 text-sm">No active employees with Position = Driver. Set a driver's position in Employees first.</p>
    <?php else: ?>
      <form method="POST" data-confirm="Assign this trip?" class="space-y-4">
        <input type="hidden" name="action" value="create">
        <div>
          <label for="trip-route-id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Route</label>
          <select id="trip-route-id" name="route_id" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
            <option value="">Select route</option>
            <?php foreach ($activeRoutes as $r): ?>
              <option value="<?php echo (int) $r['id']; ?>"><?php echo htmlspecialchars($r['destination']); ?> (₱<?php echo number_format((float) $r['amount_per_trip'], 2); ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="trip-driver-id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Driver</label>
          <select id="trip-driver-id" name="driver_id" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
            <option value="">Select driver</option>
            <?php foreach ($drivers as $d): ?>
              <option value="<?php echo (int) $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="trip-helper-id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Helper (optional)</label>
          <select id="trip-helper-id" name="helper_id" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
            <option value="">No helper</option>
            <?php foreach ($helpers as $h): ?>
              <option value="<?php echo (int) $h['id']; ?>"><?php echo htmlspecialchars($h['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="bg-brand-orange text-white font-bold rounded-lg px-5 py-3 hover:opacity-90 transition">Assign Trip</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-4">Recent Trips</h2>
    <?php if (empty($trips)): ?>
      <p class="text-gray-500 dark:text-gray-400 text-sm">No trips yet.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
          <thead>
            <tr class="text-orange-600 dark:text-brand-yellow font-bold">
              <th class="pr-6 pb-2">Route</th>
              <th class="pr-6 pb-2">Driver</th>
              <th class="pr-6 pb-2">Helper</th>
              <th class="pr-6 pb-2">Amount</th>
              <th class="pr-6 pb-2">Status</th>
              <th class="pb-2"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($trips as $trip): ?>
              <tr class="border-t border-gray-200 dark:border-surface-border text-gray-900 dark:text-white">
                <td class="pr-6 py-2"><?php echo htmlspecialchars($trip['destination']); ?></td>
                <td class="pr-6 py-2"><?php echo htmlspecialchars($trip['driver_name']); ?></td>
                <td class="pr-6 py-2"><?php echo htmlspecialchars($trip['helper_name'] ?? '—'); ?></td>
                <td class="pr-6 py-2">₱<?php echo number_format((float) $trip['amount_per_trip'], 2); ?></td>
                <td class="pr-6 py-2">
                  <?php
                    $statusClasses = [
                        'completed' => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
                        'assigned' => 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400',
                        'cancelled' => 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400',
                    ];
                  ?>
                  <span class="inline-block text-xs font-semibold px-2 py-1 rounded-full <?php echo $statusClasses[$trip['status']]; ?>">
                    <?php echo ucfirst($trip['status']); ?>
                  </span>
                </td>
                <td class="py-2">
                  <?php if ($trip['status'] === 'assigned'): ?>
                    <div class="flex gap-2">
                      <button
                        type="button"
                        class="ts-edit-trip-btn bg-brand-orange text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:opacity-90 transition"
                        data-id="<?php echo (int) $trip['id']; ?>"
                        data-route-id="<?php echo (int) $trip['route_id']; ?>"
                        data-driver-id="<?php echo (int) $trip['driver_id']; ?>"
                        data-helper-id="<?php echo (int) ($trip['helper_id'] ?? 0); ?>"
                      >Edit</button>
                      <form method="POST" data-confirm="Mark this trip as completed?">
                        <input type="hidden" name="action" value="complete">
                        <input type="hidden" name="trip_id" value="<?php echo (int) $trip['id']; ?>">
                        <button type="submit" class="bg-brand-orange text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:opacity-90 transition">Complete</button>
                      </form>
                      <form method="POST" data-confirm="Cancel this trip? This cannot be undone.">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="trip_id" value="<?php echo (int) $trip['id']; ?>">
                        <button type="submit" class="bg-red-600 text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:opacity-90 transition">Cancel</button>
                      </form>
                    </div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</main>

<!-- Edit Trip modal -->
<div id="ts-edit-trip-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center px-4">
  <div id="ts-edit-trip-backdrop" class="absolute inset-0 bg-black/50"></div>
  <div class="relative bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl shadow-xl max-w-md w-full p-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-4">Edit Trip</h2>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Only active routes/drivers/helpers can be selected. If the current assignment isn't listed, it's no longer active — pick a replacement.</p>
    <form method="POST" data-confirm="Save changes to this trip?" class="space-y-4">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="trip_id" id="ts-edit-trip-id">
      <div>
        <label for="ts-edit-trip-route-id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Route</label>
        <select id="ts-edit-trip-route-id" name="route_id" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
          <option value="">Select route</option>
          <?php foreach ($activeRoutes as $r): ?>
            <option value="<?php echo (int) $r['id']; ?>"><?php echo htmlspecialchars($r['destination']); ?> (₱<?php echo number_format((float) $r['amount_per_trip'], 2); ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="ts-edit-trip-driver-id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Driver</label>
        <select id="ts-edit-trip-driver-id" name="driver_id" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
          <option value="">Select driver</option>
          <?php foreach ($drivers as $d): ?>
            <option value="<?php echo (int) $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="ts-edit-trip-helper-id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Helper (optional)</label>
        <select id="ts-edit-trip-helper-id" name="helper_id" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
          <option value="">No helper</option>
          <?php foreach ($helpers as $h): ?>
            <option value="<?php echo (int) $h['id']; ?>"><?php echo htmlspecialchars($h['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="flex gap-3">
        <button type="submit" class="flex-1 bg-brand-orange text-white font-bold rounded-lg px-5 py-3 hover:opacity-90 transition">Save Changes</button>
        <button type="button" id="ts-edit-trip-cancel" class="flex-1 border border-gray-300 dark:border-surface-border text-gray-700 dark:text-gray-300 font-bold rounded-lg px-5 py-3 hover:bg-gray-100 dark:hover:bg-white/5 transition">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var modal = document.getElementById('ts-edit-trip-modal');
  var backdrop = document.getElementById('ts-edit-trip-backdrop');
  var cancelBtn = document.getElementById('ts-edit-trip-cancel');

  var fields = {
    id: document.getElementById('ts-edit-trip-id'),
    routeId: document.getElementById('ts-edit-trip-route-id'),
    driverId: document.getElementById('ts-edit-trip-driver-id'),
    helperId: document.getElementById('ts-edit-trip-helper-id')
  };

  function openModal(btn) {
    fields.id.value = btn.dataset.id;
    fields.routeId.value = btn.dataset.routeId;
    fields.driverId.value = btn.dataset.driverId;
    fields.helperId.value = btn.dataset.helperId === '0' ? '' : btn.dataset.helperId;
    modal.classList.remove('hidden');
  }

  function closeModal() {
    modal.classList.add('hidden');
  }

  document.querySelectorAll('.ts-edit-trip-btn').forEach(function (btn) {
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
