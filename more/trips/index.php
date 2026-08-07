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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $routeId = (int) ($_POST['route_id'] ?? 0);
        $driverId = (int) ($_POST['driver_id'] ?? 0);
        $helperId = isset($_POST['helper_id']) && $_POST['helper_id'] !== '' ? (int) $_POST['helper_id'] : null;

        $stmt = $db->prepare('SELECT amount_per_trip FROM routes WHERE id = ? AND active = 1');
        $stmt->execute([$routeId]);
        $route = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $db->prepare(
            "SELECT u.id FROM users u JOIN employee_profiles ep ON ep.user_id = u.id
             WHERE u.id = ? AND ep.position = 'driver' AND ep.status = 'active'"
        );
        $stmt->execute([$driverId]);
        $validDriver = $stmt->fetch();

        $validHelper = true;
        if ($helperId !== null) {
            $stmt = $db->prepare(
                "SELECT u.id FROM users u JOIN employee_profiles ep ON ep.user_id = u.id
                 WHERE u.id = ? AND ep.position = 'helper' AND ep.status = 'active'"
            );
            $stmt->execute([$helperId]);
            $validHelper = (bool) $stmt->fetch();
        }

        if (!$route) {
            $error = 'Please select a valid active route.';
        } elseif (!$validDriver) {
            $error = 'Please select a valid active driver.';
        } elseif (!$validHelper) {
            $error = 'Please select a valid active helper.';
        } else {
            $stmt = $db->prepare(
                'INSERT INTO trips_new (route_id, driver_id, helper_id, amount_per_trip, status, started_at)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$routeId, $driverId, $helperId, $route['amount_per_trip'], 'assigned']);
            $success = 'Trip assigned.';
        }
    } elseif ($action === 'complete') {
        $tripId = (int) ($_POST['trip_id'] ?? 0);
        $stmt = $db->prepare("UPDATE trips_new SET status = 'completed', completed_at = NOW() WHERE id = ? AND status = 'assigned'");
        $stmt->execute([$tripId]);
        $success = $stmt->rowCount() ? 'Trip marked completed.' : 'Trip was already completed or not found.';
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
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Route</label>
          <select name="route_id" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
            <option value="">Select route</option>
            <?php foreach ($activeRoutes as $r): ?>
              <option value="<?php echo (int) $r['id']; ?>"><?php echo htmlspecialchars($r['destination']); ?> (₱<?php echo number_format((float) $r['amount_per_trip'], 2); ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Driver</label>
          <select name="driver_id" required class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
            <option value="">Select driver</option>
            <?php foreach ($drivers as $d): ?>
              <option value="<?php echo (int) $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Helper (optional)</label>
          <select name="helper_id" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
            <option value="">No helper</option>
            <?php foreach ($helpers as $h): ?>
              <option value="<?php echo (int) $h['id']; ?>"><?php echo htmlspecialchars($h['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="bg-brand-green text-white font-bold rounded-lg px-5 py-3 hover:opacity-90 transition">Assign Trip</button>
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
                  <span class="inline-block text-xs font-semibold px-2 py-1 rounded-full <?php echo $trip['status'] === 'completed' ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400' : 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400'; ?>">
                    <?php echo ucfirst($trip['status']); ?>
                  </span>
                </td>
                <td class="py-2">
                  <?php if ($trip['status'] === 'assigned'): ?>
                    <form method="POST" data-confirm="Mark this trip as completed?">
                      <input type="hidden" name="action" value="complete">
                      <input type="hidden" name="trip_id" value="<?php echo (int) $trip['id']; ?>">
                      <button type="submit" class="bg-brand-orange text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:opacity-90 transition">Mark Completed</button>
                    </form>
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

<?php
$navBase = BASE_PATH;
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/confirm-modal.php';
include __DIR__ . '/../../includes/foot.php';
?>
