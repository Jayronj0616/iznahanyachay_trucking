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

// $helperIds: array of ints, may be empty (a trip needs no helper at all).
function tripAreValidHelpers(PDO $db, array $helperIds): bool {
    if (empty($helperIds)) {
        return true;
    }
    $placeholders = implode(',', array_fill(0, count($helperIds), '?'));
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM users u JOIN employee_profiles ep ON ep.user_id = u.id
         WHERE u.id IN ($placeholders) AND ep.position = 'helper' AND ep.status = 'active'"
    );
    $stmt->execute($helperIds);
    return (int) $stmt->fetchColumn() === count(array_unique($helperIds));
}

// Only 'assigned' counts as busy — once a trip is 'delivered' the driver/helper(s) are physically
// free to take the next run, even though an admin hasn't accepted the delivery for payment yet.
// Excludes $excludeTripId so editing a trip doesn't flag its own current driver/helpers as "busy".
function tripDriverOrHelperBusy(PDO $db, int $driverId, array $helperIds, ?int $excludeTripId = null): bool {
    $sql = "SELECT t.id FROM trips_new t
            LEFT JOIN trip_helpers th ON th.trip_id = t.id
            WHERE t.status = 'assigned' AND (t.driver_id = ?";
    $params = [$driverId];

    if (!empty($helperIds)) {
        $placeholders = implode(',', array_fill(0, count($helperIds), '?'));
        $sql .= " OR t.driver_id IN ($placeholders) OR th.helper_id IN ($placeholders)";
        $params = array_merge($params, $helperIds, $helperIds);
    }
    $sql .= ')';

    if ($excludeTripId !== null) {
        $sql .= ' AND t.id != ?';
        $params[] = $excludeTripId;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (bool) $stmt->fetch();
}

// Normalizes the helper_id[] checkbox group into a deduplicated array of ints. Absent entirely
// (every box cleared) is a valid state meaning "no helper on this trip" -- same pattern as
// rest_days[] in more/employees/index.php.
function tripParseHelperIds(array $raw): array {
    $ids = array_map('intval', $raw);
    $ids = array_filter($ids, fn($id) => $id > 0);
    return array_values(array_unique($ids));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $routeId = (int) ($_POST['route_id'] ?? 0);
        $driverId = (int) ($_POST['driver_id'] ?? 0);
        $helperIds = tripParseHelperIds((array) ($_POST['helper_id'] ?? []));

        $route = tripFindActiveRoute($db, $routeId);
        $validDriver = tripIsValidDriver($db, $driverId);
        $validHelpers = tripAreValidHelpers($db, $helperIds);
        $driverOrHelperBusy = $validDriver && $validHelpers && tripDriverOrHelperBusy($db, $driverId, $helperIds);

        if (!$route) {
            $error = 'Please select a valid active route.';
        } elseif (!$validDriver) {
            $error = 'Please select a valid active driver.';
        } elseif (!$validHelpers) {
            $error = 'Please select valid active helpers.';
        } elseif ($driverOrHelperBusy) {
            $error = 'The selected driver or a selected helper already has a trip in progress. Mark it delivered first.';
        } else {
            $db->beginTransaction();
            try {
                $stmt = $db->prepare(
                    'INSERT INTO trips_new (route_id, driver_id, amount_per_trip, status, started_at)
                     VALUES (?, ?, ?, ?, NOW())'
                );
                $stmt->execute([$routeId, $driverId, $route['amount_per_trip'], 'assigned']);
                $tripId = (int) $db->lastInsertId();

                $stmt = $db->prepare('INSERT INTO trip_helpers (trip_id, helper_id) VALUES (?, ?)');
                foreach ($helperIds as $hid) {
                    $stmt->execute([$tripId, $hid]);
                }

                $db->commit();
                $success = 'Trip assigned.';
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Failed to assign trip: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'edit') {
        $tripId = (int) ($_POST['trip_id'] ?? 0);
        $routeId = (int) ($_POST['route_id'] ?? 0);
        $driverId = (int) ($_POST['driver_id'] ?? 0);
        $helperIds = tripParseHelperIds((array) ($_POST['helper_id'] ?? []));

        $route = tripFindActiveRoute($db, $routeId);
        $validDriver = tripIsValidDriver($db, $driverId);
        $validHelpers = tripAreValidHelpers($db, $helperIds);
        $driverOrHelperBusy = $validDriver && $validHelpers && tripDriverOrHelperBusy($db, $driverId, $helperIds, $tripId);

        $stmt = $db->prepare("SELECT id FROM trips_new WHERE id = ? AND status = 'assigned'");
        $stmt->execute([$tripId]);
        $tripExists = (bool) $stmt->fetch();

        if (!$tripExists) {
            $error = 'This trip can no longer be edited (already completed, cancelled, or not found).';
        } elseif (!$route) {
            $error = 'Please select a valid active route.';
        } elseif (!$validDriver) {
            $error = 'Please select a valid active driver.';
        } elseif (!$validHelpers) {
            $error = 'Please select valid active helpers.';
        } elseif ($driverOrHelperBusy) {
            $error = 'The selected driver or a selected helper already has another trip in progress.';
        } else {
            $db->beginTransaction();
            try {
                // Re-snapshots amount_per_trip from the (possibly new) route. Only reachable while
                // the trip is still 'assigned' — a delivered trip must be returned to in-progress first.
                $stmt = $db->prepare(
                    "UPDATE trips_new SET route_id = ?, driver_id = ?, amount_per_trip = ?
                     WHERE id = ? AND status = 'assigned'"
                );
                $stmt->execute([$routeId, $driverId, $route['amount_per_trip'], $tripId]);

                // Simplest correct sync for a small per-trip list: clear and re-insert rather than
                // diffing old vs new helper sets.
                $stmt = $db->prepare('DELETE FROM trip_helpers WHERE trip_id = ?');
                $stmt->execute([$tripId]);
                $stmt = $db->prepare('INSERT INTO trip_helpers (trip_id, helper_id) VALUES (?, ?)');
                foreach ($helperIds as $hid) {
                    $stmt->execute([$tripId, $hid]);
                }

                $db->commit();
                $success = 'Trip updated.';
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Failed to update trip: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'cancel') {
        $tripId = (int) ($_POST['trip_id'] ?? 0);
        // Cancellable right up until acceptance — a delivery the admin refuses outright is a
        // cancellation, not a completion, so it still writes no attendance and never gets paid.
        $stmt = $db->prepare(
            "UPDATE trips_new SET status = 'cancelled', cancelled_at = NOW()
             WHERE id = ? AND status IN ('assigned', 'delivered')"
        );
        $stmt->execute([$tripId]);
        if ($stmt->rowCount() > 0) {
            $success = 'Trip cancelled.';
        } else {
            $error = 'This trip can no longer be cancelled (already completed, cancelled, or not found).';
        }
    } elseif ($action === 'deliver') {
        // Step 1 of 2: the driver has reported the run finished. Records the report only —
        // no attendance, no payroll eligibility. Both of those wait for 'complete' below.
        $tripId = (int) ($_POST['trip_id'] ?? 0);
        $stmt = $db->prepare(
            "UPDATE trips_new SET status = 'delivered', delivered_at = NOW()
             WHERE id = ? AND status = 'assigned'"
        );
        $stmt->execute([$tripId]);
        if ($stmt->rowCount() > 0) {
            $success = 'Trip marked delivered. It needs admin acceptance before it is paid.';
        } else {
            $error = 'This trip can no longer be marked delivered (already delivered, completed, cancelled, or not found).';
        }
    } elseif ($action === 'revert') {
        // Delivery reported in error, or the trip needs editing — send it back to in-progress.
        // Safe to reverse because 'delivered' created no attendance rows and paid nothing.
        $tripId = (int) ($_POST['trip_id'] ?? 0);
        $stmt = $db->prepare(
            "UPDATE trips_new SET status = 'assigned', delivered_at = NULL
             WHERE id = ? AND status = 'delivered'"
        );
        $stmt->execute([$tripId]);
        if ($stmt->rowCount() > 0) {
            $success = 'Trip returned to in-progress.';
        } else {
            $error = 'This trip cannot be returned to in-progress (not awaiting acceptance, or not found).';
        }
    } elseif ($action === 'complete') {
        // Step 2 of 2: admin accepts the delivery. THIS is the step that writes attendance and
        // makes the trip payable — deliberately separated from the driver's delivery report, so
        // no single click both closes a trip and commits the company to paying commission on it.
        // Mirrors timesheet_entries (pending -> approved, payroll counts approved only).
        $tripId = (int) ($_POST['trip_id'] ?? 0);

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT driver_id, delivered_at FROM trips_new WHERE id = ? AND status = 'delivered' FOR UPDATE");
            $stmt->execute([$tripId]);
            $trip = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$trip) {
                $db->rollBack();
                $error = 'This trip cannot be accepted — it must be marked delivered first, or it was already accepted.';
            } else {
                $stmt = $db->prepare("UPDATE trips_new SET status = 'completed', completed_at = NOW() WHERE id = ? AND status = 'delivered'");
                $stmt->execute([$tripId]);

                // Attendance is dated by the DELIVERY, not by this acceptance — presence records the
                // day the crew actually ran the route. An admin accepting three days late must not
                // move a driver's attendance onto a day he didn't work.
                $attendanceDate = $trip['delivered_at'] !== null
                    ? date('Y-m-d', strtotime($trip['delivered_at']))
                    : date('Y-m-d');

                $stmt = $db->prepare('INSERT INTO trip_attendance (trip_id, user_id, role, date) VALUES (?, ?, ?, ?)');
                $stmt->execute([$tripId, $trip['driver_id'], 'driver', $attendanceDate]);

                $helperStmt = $db->prepare('SELECT helper_id FROM trip_helpers WHERE trip_id = ?');
                $helperStmt->execute([$tripId]);
                foreach ($helperStmt->fetchAll(PDO::FETCH_COLUMN) as $helperId) {
                    $stmt->execute([$tripId, $helperId, 'helper', $attendanceDate]);
                }

                $db->commit();
                $success = 'Trip accepted. Attendance recorded and the trip is now payable.';
            }
        } catch (PDOException $e) {
            $db->rollBack();
            $error = 'Failed to accept trip: ' . $e->getMessage();
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
    "SELECT t.*, r.destination, d.name AS driver_name,
            GROUP_CONCAT(h.name ORDER BY h.name SEPARATOR ', ') AS helper_names,
            GROUP_CONCAT(h.id ORDER BY h.name SEPARATOR ',') AS helper_ids
     FROM trips_new t
     JOIN routes r ON r.id = t.route_id
     JOIN users d ON d.id = t.driver_id
     LEFT JOIN trip_helpers th ON th.trip_id = t.id
     LEFT JOIN users h ON h.id = th.helper_id
     GROUP BY t.id
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
          <span class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Helpers (optional, any number)</span>
          <?php if (empty($helpers)): ?>
            <p class="text-xs text-gray-500 dark:text-gray-400">No active employees with Position = Helper.</p>
          <?php else: ?>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
              <?php foreach ($helpers as $h): ?>
                <label class="flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300">
                  <input type="checkbox" name="helper_id[]" value="<?php echo (int) $h['id']; ?>"
                         class="rounded border-gray-300 dark:border-surface-border text-brand-orange focus:ring-brand-orange">
                  <?php echo htmlspecialchars($h['name']); ?>
                </label>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
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
                <td class="pr-6 py-2"><?php echo htmlspecialchars($trip['helper_names'] ?? '—'); ?></td>
                <td class="pr-6 py-2 text-right">₱<?php echo number_format((float) $trip['amount_per_trip'], 2); ?></td>
                <td class="pr-6 py-2">
                  <?php
                    $statusClasses = [
                        'completed' => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
                        'delivered' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
                        'assigned' => 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400',
                        'cancelled' => 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400',
                    ];
                    // 'completed' is the admin's acceptance, so label it as such — "Completed" alone
                    // reads like the driver finished, which is what 'delivered' now means.
                    $statusLabels = [
                        'completed' => 'Accepted',
                        'delivered' => 'Awaiting acceptance',
                        'assigned' => 'In progress',
                        'cancelled' => 'Cancelled',
                    ];
                  ?>
                  <span class="inline-block text-xs font-semibold px-2 py-1 rounded-full <?php echo $statusClasses[$trip['status']]; ?>">
                    <?php echo htmlspecialchars($statusLabels[$trip['status']]); ?>
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
                        data-helper-ids="<?php echo htmlspecialchars($trip['helper_ids'] ?? '', ENT_QUOTES); ?>"
                      >Edit</button>
                      <form method="POST" data-confirm="Mark this trip as delivered? It still needs your acceptance before it is paid.">
                        <input type="hidden" name="action" value="deliver">
                        <input type="hidden" name="trip_id" value="<?php echo (int) $trip['id']; ?>">
                        <button type="submit" class="bg-brand-orange text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:opacity-90 transition">Mark Delivered</button>
                      </form>
                      <form method="POST" data-confirm="Cancel this trip? This cannot be undone.">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="trip_id" value="<?php echo (int) $trip['id']; ?>">
                        <button type="submit" class="bg-red-600 text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:opacity-90 transition">Cancel</button>
                      </form>
                    </div>
                  <?php elseif ($trip['status'] === 'delivered'): ?>
                    <div class="flex gap-2">
                      <form method="POST" data-confirm="Accept this trip? This records attendance and makes it payable.">
                        <input type="hidden" name="action" value="complete">
                        <input type="hidden" name="trip_id" value="<?php echo (int) $trip['id']; ?>">
                        <button type="submit" class="bg-green-600 text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:opacity-90 transition">Accept</button>
                      </form>
                      <form method="POST" data-confirm="Return this trip to in-progress?">
                        <input type="hidden" name="action" value="revert">
                        <input type="hidden" name="trip_id" value="<?php echo (int) $trip['id']; ?>">
                        <button type="submit" class="bg-gray-600 text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:opacity-90 transition">Return</button>
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
        <span class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Helpers (optional, any number)</span>
        <?php if (empty($helpers)): ?>
          <p class="text-xs text-gray-500 dark:text-gray-400">No active employees with Position = Helper.</p>
        <?php else: ?>
          <div class="grid grid-cols-2 gap-2">
            <?php foreach ($helpers as $h): ?>
              <label class="flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="helper_id[]" value="<?php echo (int) $h['id']; ?>" data-helper-checkbox
                       class="rounded border-gray-300 dark:border-surface-border text-brand-orange focus:ring-brand-orange">
                <?php echo htmlspecialchars($h['name']); ?>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
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
    driverId: document.getElementById('ts-edit-trip-driver-id')
  };
  var helperCheckboxes = document.querySelectorAll('[data-helper-checkbox]');

  function openModal(btn) {
    fields.id.value = btn.dataset.id;
    fields.routeId.value = btn.dataset.routeId;
    fields.driverId.value = btn.dataset.driverId;

    var selected = (btn.dataset.helperIds || '').split(',').filter(Boolean);
    helperCheckboxes.forEach(function (box) {
      box.checked = selected.indexOf(box.value) !== -1;
    });

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
