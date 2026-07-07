<?php
require_once __DIR__ . '/../includes/auth.php';
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

// This month's timesheet summary — same regular/OT split logic as payroll/index.php (>8h/day = OT)
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$stmt = $db->prepare(
    'SELECT date, time_in, time_out FROM timesheet_entries
     WHERE user_id = ? AND date BETWEEN ? AND ?'
);
$stmt->execute([$userId, $monthStart, $monthEnd]);
$monthEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$regularHours = 0.0;
$otHours = 0.0;
$daysWithEntry = [];
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

// Days absent = weekdays elapsed this month (up to today) with no entry row at all.
// ASSUMPTION: weekday-only, no holiday calendar exists yet — will overcount on holidays.
$daysAbsent = 0;
$dayCursor = strtotime($monthStart);
$todayTs = strtotime($today);
while ($dayCursor <= $todayTs) {
    $dow = (int) date('N', $dayCursor); // 1=Mon .. 7=Sun
    $dateStr = date('Y-m-d', $dayCursor);
    if ($dow < 6 && !isset($daysWithEntry[$dateStr])) {
        $daysAbsent++;
    }
    $dayCursor = strtotime('+1 day', $dayCursor);
}

// No leave table exists yet — do not fabricate numbers, show as not-yet-tracked.
$paidLeaveHours = null;
$unpaidLeaveHours = null;

// Real payroll history for sparkline-equivalent (last 6 runs, any status)
$stmt = $db->prepare(
    'SELECT period_start, period_end, net_pay, status FROM payroll_runs
     WHERE user_id = ? ORDER BY period_start DESC LIMIT 6'
);
$stmt->execute([$userId]);
$recentRuns = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
?>

<main class="max-w-3xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-6">
  <h1 class="text-3xl font-extrabold text-orange-600 dark:text-brand-orange">Dashboard</h1>

  <?php if ($_SESSION['user']['role'] === 'admin'): ?>
  <a href="<?php echo BASE_PATH; ?>/home/invite/" class="flex items-center justify-between bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-full px-5 py-3 shadow-sm dark:shadow-none">
    <span class="flex items-center gap-2 text-gray-800 dark:text-gray-100 text-sm font-medium">
      <svg class="w-4 h-4 text-brand-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M7 7h11l-3-3"></path>
        <path d="M17 17H6l3 3"></path>
      </svg>
      Invite your employee
    </span>
    <span class="w-7 h-7 rounded-full bg-brand-green flex items-center justify-center">
      <svg class="w-3.5 h-3.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M5 12h14M13 6l6 6-6 6"></path>
      </svg>
    </span>
  </a>
  <?php endif; ?>

  <div>
    <div class="flex items-center justify-between mb-3">
      <h2 class="text-lg font-bold text-gray-900 dark:text-white">Timesheet</h2>
      <span class="text-sm text-brand-green font-medium">
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
          <?php foreach (array_reverse($recentRuns) as $run): ?>
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
