<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/attendance.php';
require_once __DIR__ . '/../../includes/payroll.php';
requireLogin();

$pageTitle = 'Dashboard Overview';
$activeNav = 'overview';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '📊';
$pageLabel = 'Overview';
include __DIR__ . '/../../includes/topbar.php';

$db = getDB();
$isAdmin = $_SESSION['user']['role'] === 'admin';

// The open period is the current month. Payroll cutoffs are semi-monthly, but the
// client asked to see pay accruing "bago mag cutoff" -- before the cutoff -- so this
// deliberately shows the month to date rather than waiting for a cutoff boundary.
$periodStart = date('Y-m-01');
$periodEnd = date('Y-m-t');
$today = date('Y-m-d');

// Admin sees company-wide totals (they have no payroll_runs/timesheet_entries of their
// own); employee sees only their own. Salary is finalized runs only — a draft isn't real
// money yet. Hours are approved entries only, same rule payroll/index.php enforces.
if ($isAdmin) {
    $totalSalary = (float) $db->query(
        "SELECT COALESCE(SUM(net_pay), 0) FROM payroll_runs WHERE status = 'finalized'"
    )->fetchColumn();
    $hourEntries = $db->query(
        "SELECT time_in, time_out FROM timesheet_entries
         WHERE status = 'approved' AND time_in IS NOT NULL AND time_out IS NOT NULL"
    )->fetchAll(PDO::FETCH_ASSOC);
    $staff = $db->query(
        "SELECT u.id, p.position FROM users u
         JOIN employee_profiles p ON p.user_id = u.id
         WHERE u.role = 'employee' AND p.status = 'active'"
    )->fetchAll(PDO::FETCH_ASSOC);
} else {
    $userId = (int) $_SESSION['user']['id'];
    $stmt = $db->prepare("SELECT COALESCE(SUM(net_pay), 0) FROM payroll_runs WHERE status = 'finalized' AND user_id = ?");
    $stmt->execute([$userId]);
    $totalSalary = (float) $stmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT time_in, time_out FROM timesheet_entries
         WHERE status = 'approved' AND time_in IS NOT NULL AND time_out IS NOT NULL AND user_id = ?"
    );
    $stmt->execute([$userId]);
    $hourEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare('SELECT position FROM employee_profiles WHERE user_id = ?');
    $stmt->execute([$userId]);
    $staff = [['id' => $userId, 'position' => $stmt->fetchColumn() ?: null]];
}

$totalHours = 0.0;
foreach ($hourEntries as $e) {
    $totalHours += max(0, (strtotime($e['time_out']) - strtotime($e['time_in'])) / 3600);
}

// Accruing pay for the open period, and absences over the same window.
//
// This is computed rather than read: no payroll_runs row exists until an admin runs
// payroll, which is exactly the point — the client wants the figure before the cutoff,
// not after it. It goes through the same includes/payroll.php the real run uses, so
// the number a visitor sees here cannot drift from the number the payslip later shows
// for the same inputs.
//
// Driver and helper never punch a timesheet, so an absence is not a concept that
// applies to them; counting weekdays without an entry would mark them absent every
// single day. Their pay still accrues from completed trips.
$accruedPay = 0.0;
$totalAbsences = 0;
$absenceApplies = false;

foreach ($staff as $person) {
    $sid = (int) $person['id'];
    $position = $person['position'] ?? null;

    $earnings = computePeriodEarnings($db, $sid, $position, $periodStart, $periodEnd);
    $accruedPay += applyDeductions($earnings['gross_pay'])['net_pay'];

    if (in_array($position, ['driver', 'helper'], true) || $position === null) {
        continue;
    }
    $absenceApplies = true;

    $stmt = $db->prepare('SELECT date FROM timesheet_entries WHERE user_id = ? AND date BETWEEN ? AND ?');
    $stmt->execute([$sid, $periodStart, $periodEnd]);
    $seen = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $d) {
        $seen[$d] = true;
    }

    $counts = classifyAttendance($periodStart, $periodEnd, $seen, employeeRestDays($db, $sid), $today);
    $totalAbsences += $counts['absent'];
}

$periodLabel = date('F Y');
?>

<main class="max-w-3xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-6">
  <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dashboard Overview</h1>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div class="bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl px-6 py-5 shadow-sm dark:shadow-none">
      <div class="text-gray-900 dark:text-white font-semibold mb-4">Total Salary<?php echo $isAdmin ? ' (Company)' : ''; ?></div>
      <div class="border-t border-gray-200 dark:border-surface-border mb-4"></div>
      <div class="text-gray-900 dark:text-white text-xl font-bold">₱<?php echo number_format($totalSalary, 2); ?></div>
    </div>
    <div class="bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl px-6 py-5 shadow-sm dark:shadow-none">
      <div class="text-gray-900 dark:text-white font-semibold mb-4">Total Hours<?php echo $isAdmin ? ' (Company)' : ''; ?></div>
      <div class="border-t border-gray-200 dark:border-surface-border mb-4"></div>
      <div class="text-gray-900 dark:text-white text-xl font-bold"><?php echo number_format($totalHours, 2); ?>h</div>
    </div>
    <div class="bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl px-6 py-5 shadow-sm dark:shadow-none">
      <div class="text-gray-900 dark:text-white font-semibold mb-1">Accruing This Period<?php echo $isAdmin ? ' (Company)' : ''; ?></div>
      <div class="text-gray-500 dark:text-gray-400 text-xs mb-3"><?php echo htmlspecialchars($periodLabel); ?> so far — not yet paid</div>
      <div class="border-t border-gray-200 dark:border-surface-border mb-4"></div>
      <div class="text-gray-900 dark:text-white text-xl font-bold">₱<?php echo number_format($accruedPay, 2); ?></div>
    </div>
    <div class="bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl px-6 py-5 shadow-sm dark:shadow-none">
      <div class="text-gray-900 dark:text-white font-semibold mb-1">Total Absences<?php echo $isAdmin ? ' (Company)' : ''; ?></div>
      <div class="text-gray-500 dark:text-gray-400 text-xs mb-3"><?php echo htmlspecialchars($periodLabel); ?>, rest days excluded</div>
      <div class="border-t border-gray-200 dark:border-surface-border mb-4"></div>
      <?php if ($absenceApplies): ?>
        <div class="text-gray-900 dark:text-white text-xl font-bold"><?php echo (int) $totalAbsences; ?></div>
      <?php else: ?>
        <div class="text-gray-400 dark:text-gray-500 text-sm font-bold">Not applicable — trip-based pay</div>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php
$navBase = BASE_PATH;
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/foot.php';
?>
