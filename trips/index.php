<?php
/**
 * A driver or helper's own trips.
 *
 * Read-only on purpose. Assigning, editing, marking delivered and accepting are
 * all admin actions in more/trips/, and none of them move here -- this page
 * exists because a driver previously had no way to see a trip at all. The
 * dashboard counted only completed trips, so the run they were supposed to be
 * doing right now was invisible to the person doing it.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payroll.php';
requireLogin();

// Admins have the real trips screen, with the actions on it.
if ($_SESSION['user']['role'] === 'admin') {
    header('Location: ' . BASE_PATH . '/more/trips/');
    exit;
}

$db = getDB();
$userId = (int) $_SESSION['user']['id'];

$stmt = $db->prepare('SELECT position FROM employee_profiles WHERE user_id = ?');
$stmt->execute([$userId]);
$position = $stmt->fetchColumn() ?: null;

// Nobody else is ever put on a trip, so there is nothing here for them.
if (!in_array($position, ['driver', 'helper'], true)) {
    header('Location: ' . BASE_PATH . '/home/');
    exit;
}

$pageTitle = 'My Trips';
$activeNav = 'trips';
include __DIR__ . '/../includes/head.php';

$pageIcon = '🚚';
$pageLabel = 'My Trips';
include __DIR__ . '/../includes/topbar.php';

$isDriver = $position === 'driver';
$column = $isDriver ? 'driver_id' : 'helper_id';
// Same rates the payroll run uses, from the same file, so what a driver reads
// here is what the payslip will eventually say.
$share = $isDriver ? DRIVER_TRIP_RATE : HELPER_TRIP_RATE;

$stmt = $db->prepare(
    "SELECT t.*, r.destination
     FROM trips_new t
     LEFT JOIN routes r ON r.id = t.route_id
     WHERE t.$column = ?
     ORDER BY FIELD(t.status, 'assigned', 'delivered', 'completed', 'cancelled'), t.id DESC"
);
$stmt->execute([$userId]);
$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

$active = array_values(array_filter($trips, function ($t) {
    return in_array($t['status'], ['assigned', 'delivered'], true);
}));
$past = array_values(array_filter($trips, function ($t) {
    return !in_array($t['status'], ['assigned', 'delivered'], true);
}));

$statusChip = [
    'assigned' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    'delivered' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
    'completed' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    'cancelled' => 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
];
// Phrased from the driver's side -- what it means for them, not which column
// changed.
$statusLabel = [
    'assigned' => 'To deliver',
    'delivered' => 'Waiting for admin',
    'completed' => 'Accepted',
    'cancelled' => 'Cancelled',
];
?>

<main class="max-w-3xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-6">

  <section>
    <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">Current</h2>
    <?php if (empty($active)): ?>
      <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6 text-center">
        <p class="text-sm text-gray-500 dark:text-gray-400">No trip assigned to you right now.</p>
        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">An admin assigns trips &mdash; one will appear here when you are put on a route.</p>
      </div>
    <?php else: ?>
      <div class="space-y-4">
        <?php foreach ($active as $t): ?>
          <div class="bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-5">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="font-bold text-gray-900 dark:text-white text-lg break-words"><?php echo htmlspecialchars($t['destination'] ?? 'Route removed'); ?></p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5"><?php echo $isDriver ? 'You are the driver' : 'You are the helper'; ?></p>
              </div>
              <span class="shrink-0 text-xs font-semibold px-2.5 py-1 rounded-full <?php echo $statusChip[$t['status']]; ?>">
                <?php echo htmlspecialchars($statusLabel[$t['status']]); ?>
              </span>
            </div>

            <dl class="mt-4 pt-3 border-t border-gray-200 dark:border-surface-border space-y-1.5">
              <div class="flex items-baseline justify-between gap-4">
                <dt class="text-sm text-gray-500 dark:text-gray-400">Route rate</dt>
                <dd class="text-sm text-gray-900 dark:text-white tabular-nums">&#8369;<?php echo number_format($t['amount_per_trip'], 2); ?></dd>
              </div>
              <div class="flex items-baseline justify-between gap-4">
                <dt class="text-sm text-gray-500 dark:text-gray-400">Your share (<?php echo (int) round($share * 100); ?>%)</dt>
                <dd class="text-sm font-bold text-gray-900 dark:text-white tabular-nums">&#8369;<?php echo number_format($t['amount_per_trip'] * $share, 2); ?></dd>
              </div>
              <?php if ($t['started_at']): ?>
                <div class="flex items-baseline justify-between gap-4">
                  <dt class="text-sm text-gray-500 dark:text-gray-400">Assigned</dt>
                  <dd class="text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars(date('M j, Y', strtotime($t['started_at']))); ?></dd>
                </div>
              <?php endif; ?>
            </dl>

            <?php if ($t['status'] === 'delivered'): ?>
              <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Reported delivered. It is not paid until an admin accepts it.</p>
            <?php else: ?>
              <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Tell the office when this run is finished &mdash; they record the delivery.</p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section>
    <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">History</h2>
    <?php if (empty($past)): ?>
      <p class="text-sm text-gray-500 dark:text-gray-400">No past trips yet.</p>
    <?php else: ?>
      <div class="space-y-3">
        <?php foreach ($past as $t): ?>
          <div class="bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-4">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="font-semibold text-gray-900 dark:text-white break-words"><?php echo htmlspecialchars($t['destination'] ?? 'Route removed'); ?></p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5"><?php echo $t['completed_at'] ? htmlspecialchars(date('M j, Y', strtotime($t['completed_at']))) : ''; ?></p>
              </div>
              <div class="shrink-0 text-right">
                <span class="block text-xs font-semibold px-2.5 py-1 rounded-full <?php echo $statusChip[$t['status']]; ?>">
                  <?php echo htmlspecialchars($statusLabel[$t['status']]); ?>
                </span>
                <?php if ($t['status'] === 'completed'): ?>
                  <span class="block mt-1.5 text-sm font-bold text-gray-900 dark:text-white tabular-nums">&#8369;<?php echo number_format($t['amount_per_trip'] * $share, 2); ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

</main>

<?php
$navBase = BASE_PATH;
include __DIR__ . '/../includes/bottom-nav.php';
include __DIR__ . '/../includes/foot.php';
?>
