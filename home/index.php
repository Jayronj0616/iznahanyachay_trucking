<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance.php';
requireLogin();

$pageTitle = 'Home';
$activeNav = 'home';
include __DIR__ . '/../includes/head.php';

$pageIcon = '🏠';
$pageLabel = 'Home';
include __DIR__ . '/../includes/topbar.php';

$db = getDB();
$userId = (int) $_SESSION['user']['id'];
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

$stmt = $db->prepare('SELECT position FROM employee_profiles WHERE user_id = ?');
$stmt->execute([$userId]);
$position = $stmt->fetchColumn() ?: null;

// Driver/helper are trip-commission-only and never punch a timesheet — the weekday
// present/absent math below only means something for hourly employees.
$isHourly = $position !== null && !in_array($position, ['driver', 'helper'], true);

$daysWithEntry = [];
$regularHours = 0.0;
$otHours = 0.0;
$daysAbsent = 0;

if ($isHourly) {
    // This month's timesheet summary — same regular/OT split logic as payroll/index.php (>8h/day = OT)
    $stmt = $db->prepare(
        'SELECT date, time_in, time_out FROM timesheet_entries
         WHERE user_id = ? AND date BETWEEN ? AND ?'
    );
    $stmt->execute([$userId, $monthStart, $monthEnd]);
    $monthEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($monthEntries as $e) {
        $daysWithEntry[$e['date']] = true;
        if (!$e['time_in'] || !$e['time_out']) {
            continue;
        }
        $hours = max(0, (strtotime($e['time_out']) - strtotime($e['time_in'])) / 3600);
        if ($hours > 8) {
            $regularHours += 8;
            $otHours += $hours - 8;
        } else {
            $regularHours += $hours;
        }
    }

    // Absences come from the shared rule in includes/attendance.php, the same one
    // the Overview card and the timesheet calendar use. This loop used to hardcode
    // Saturday and Sunday here, which meant an employee whose rest day fell midweek
    // was marked absent on it — and, once rest days became editable, that this page
    // and the Overview would have reported different numbers for the same person.
    //
    // Still no holiday calendar, so a public holiday is still counted as an absence.
    $counts = classifyAttendance($monthStart, $monthEnd, $daysWithEntry, employeeRestDays($db, $userId), $today);
    $daysAbsent = $counts['absent'];
}

// No leave table exists yet — do not fabricate numbers, show as not-yet-tracked.
$paidLeaveHours = null;
$unpaidLeaveHours = null;

$tripCount = 0;
$tripIncentiveTotal = 0.0;
if (!$isHourly && $position !== null) {
    // Driver/helper: this month's completed-trip commission, same rate logic as payroll/index.php.
    $column = $position === 'driver' ? 'driver_id' : 'helper_id';
    $rate = $position === 'driver' ? 0.15 : 0.08;
    $stmt = $db->prepare(
        "SELECT COUNT(*) AS trip_count, COALESCE(SUM(amount_per_trip), 0) AS trip_sum
         FROM trips_new
         WHERE $column = ? AND status = 'completed' AND DATE(completed_at) BETWEEN ? AND ?"
    );
    $stmt->execute([$userId, $monthStart, $monthEnd]);
    $tripRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $tripCount = (int) $tripRow['trip_count'];
    $tripIncentiveTotal = round((float) $tripRow['trip_sum'] * $rate, 2);

    // The run they are on right now. Not filtered by month on purpose -- an open
    // trip is open regardless of when it was assigned, and a driver looking at
    // their dashboard wants the one they are doing, not the ones that happen to
    // fall inside this month's boundaries.
    $stmt = $db->prepare(
        "SELECT t.*, r.destination
         FROM trips_new t
         LEFT JOIN routes r ON r.id = t.route_id
         WHERE t.$column = ? AND t.status IN ('assigned', 'delivered')
         ORDER BY t.id DESC LIMIT 1"
    );
    $stmt->execute([$userId]);
    $activeTrip = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Real payroll history, newest period first (last 6 runs, any status)
$stmt = $db->prepare(
    'SELECT period_start, period_end, net_pay, status FROM payroll_runs
     WHERE user_id = ? ORDER BY period_start DESC LIMIT 6'
);
$stmt->execute([$userId]);
$recentRuns = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="max-w-3xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-6">
  <h1 class="text-3xl font-extrabold text-orange-600 dark:text-brand-orange">Dashboard</h1>

  <?php if ($_SESSION['user']['role'] === 'admin'): ?>
  <a href="<?php echo BASE_PATH; ?>/home/invite/" class="flex items-center justify-between bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-full px-5 py-3 shadow-sm dark:shadow-none">
    <span class="flex items-center gap-2 text-gray-800 dark:text-gray-100 text-sm font-medium">
      <svg class="w-4 h-4 text-brand-orange" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M7 7h11l-3-3"></path>
        <path d="M17 17H6l3 3"></path>
      </svg>
      Invite your employee
    </span>
    <span class="w-7 h-7 rounded-full bg-brand-orange flex items-center justify-center">
      <svg class="w-3.5 h-3.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M5 12h14M13 6l6 6-6 6"></path>
      </svg>
    </span>
  </a>
  <?php endif; ?>

  <?php if ($isHourly): ?>
  <div>
    <div class="flex items-center justify-between mb-3">
      <h2 class="text-lg font-bold text-gray-900 dark:text-white">Timesheet</h2>
      <span class="text-sm text-gray-500 dark:text-gray-400 font-medium">
        <?php echo date('j M', strtotime($monthStart)) . ' - ' . date('j M Y', strtotime($monthEnd)); ?>
      </span>
    </div>
    <div class="bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl px-5 py-4 grid grid-cols-3 gap-y-4 text-center">
      <div><div class="text-xs text-gray-500 dark:text-gray-400">Days Present</div><div class="font-bold text-gray-900 dark:text-white mt-1"><?php echo count($daysWithEntry); ?></div></div>
      <div><div class="text-xs text-gray-500 dark:text-gray-400">Regular Time</div><div class="font-bold text-gray-900 dark:text-white mt-1"><?php echo number_format($regularHours, 2); ?>h</div></div>
      <div><div class="text-xs text-gray-500 dark:text-gray-400">Overtime</div><div class="font-bold text-gray-900 dark:text-white mt-1"><?php echo number_format($otHours, 2); ?>h</div></div>
      <div><div class="text-xs text-gray-500 dark:text-gray-400">Days Absent</div><div class="font-bold text-gray-900 dark:text-white mt-1"><?php echo $daysAbsent; ?></div></div>
      <div><div class="text-xs text-gray-500 dark:text-gray-400">Paid Leave</div><div class="font-bold text-gray-400 dark:text-gray-500 mt-1 text-xs">Not tracked</div></div>
      <div><div class="text-xs text-gray-500 dark:text-gray-400">Unpaid Leave</div><div class="font-bold text-gray-400 dark:text-gray-500 mt-1 text-xs">Not tracked</div></div>
    </div>
  </div>
  <?php elseif ($position !== null): ?>
  <div>
    <div class="flex items-center justify-between mb-3">
      <h2 class="text-lg font-bold text-gray-900 dark:text-white">Trips</h2>
      <span class="text-sm text-gray-500 dark:text-gray-400 font-medium">
        <?php echo date('j M', strtotime($monthStart)) . ' - ' . date('j M Y', strtotime($monthEnd)); ?>
      </span>
    </div>
    <?php if (!empty($activeTrip)): ?>
      <a href="<?php echo BASE_PATH; ?>/trips/" class="block bg-white dark:bg-surface-card border border-brand-orange/40 rounded-xl px-5 py-4 mb-3">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="text-xs text-gray-500 dark:text-gray-400">Current trip</div>
            <div class="font-bold text-gray-900 dark:text-white mt-0.5 break-words"><?php echo htmlspecialchars($activeTrip['destination'] ?? 'Route removed'); ?></div>
          </div>
          <span class="shrink-0 text-xs font-semibold px-2.5 py-1 rounded-full <?php echo $activeTrip['status'] === 'delivered' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'; ?>">
            <?php echo $activeTrip['status'] === 'delivered' ? 'Waiting for admin' : 'To deliver'; ?>
          </span>
        </div>
        <div class="flex items-baseline justify-between gap-4 mt-3 pt-3 border-t border-gray-200 dark:border-surface-border">
          <span class="text-xs text-gray-500 dark:text-gray-400">Your share (<?php echo (int) round($rate * 100); ?>%)</span>
          <span class="font-bold text-gray-900 dark:text-white tabular-nums">₱<?php echo number_format($activeTrip['amount_per_trip'] * $rate, 2); ?></span>
        </div>
      </a>
    <?php endif; ?>
    <div class="bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl px-5 py-4 grid grid-cols-2 gap-y-4 text-center">
      <div><div class="text-xs text-gray-500 dark:text-gray-400">Completed Trips</div><div class="font-bold text-gray-900 dark:text-white mt-1"><?php echo $tripCount; ?></div></div>
      <div><div class="text-xs text-gray-500 dark:text-gray-400">Trip Incentive</div><div class="font-bold text-gray-900 dark:text-white mt-1">₱<?php echo number_format($tripIncentiveTotal, 2); ?></div></div>
    </div>
  </div>
  <?php endif; ?>

  <div>
    <div class="flex items-center justify-between mb-3">
      <h2 class="text-lg font-bold text-gray-900 dark:text-white">Payroll</h2>
    </div>
    <div class="bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl px-5 py-5">
      <?php if (empty($recentRuns)): ?>
        <div class="text-center text-sm text-gray-500 dark:text-gray-400">No payroll runs yet.</div>
      <?php else: ?>
        <div class="text-center text-sm text-gray-500 dark:text-gray-400 mb-3">Net Pay — Last <?php echo count($recentRuns); ?> Period(s)</div>
        <div class="grid grid-cols-1 gap-2">
          <?php foreach ($recentRuns as $run): ?>
            <div class="flex items-center justify-between text-sm border-b border-gray-100 dark:border-surface-border pb-2 last:border-0 last:pb-0">
              <span class="text-gray-600 dark:text-gray-300"><?php echo htmlspecialchars(date('M j', strtotime($run['period_start'])) . ' – ' . date('M j', strtotime($run['period_end']))); ?></span>
              <span class="font-bold text-gray-900 dark:text-white">₱<?php echo number_format($run['net_pay'], 2); ?></span>
              <span class="text-xs <?php echo $run['status'] === 'finalized' ? 'text-brand-green' : 'text-gray-400'; ?>"><?php echo ucfirst($run['status']); ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php
$navBase = BASE_PATH;
include __DIR__ . '/../includes/bottom-nav.php';
include __DIR__ . '/../includes/foot.php';
?>
