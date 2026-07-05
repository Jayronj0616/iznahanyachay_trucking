<?php
$pageTitle = 'Payroll';
$activeNav = 'payroll';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
include __DIR__ . '/../includes/head.php';

$pageIcon = '💼';
$pageLabel = 'Payroll';
include __DIR__ . '/../includes/topbar.php';

$error = null;
$success = null;

const RATE_PER_HOUR = 100.00;
const OT_RATE_PER_HOUR = 110.00;
const SSS_PCT = 0.045;
const PHILHEALTH_PCT = 0.03;
const PAGIBIG_PCT = 0.02;

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $periodStart = $_POST['period_start'] ?? '';
    $periodEnd = $_POST['period_end'] ?? '';

    if (!$periodStart || !$periodEnd || $periodStart > $periodEnd) {
        $error = 'Please select a valid date range.';
    } else {
        $employees = $db->query("SELECT id FROM users WHERE role = 'employee'")->fetchAll(PDO::FETCH_COLUMN);

        $db->beginTransaction();
        try {
            foreach ($employees as $userId) {
                $stmt = $db->prepare(
                    'SELECT time_in, time_out FROM timesheet_entries
                     WHERE user_id = ? AND date BETWEEN ? AND ? AND time_in IS NOT NULL AND time_out IS NOT NULL'
                );
                $stmt->execute([$userId, $periodStart, $periodEnd]);
                $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $regularHours = 0.0;
                $otHours = 0.0;
                foreach ($entries as $entry) {
                    $in = strtotime($entry['time_in']);
                    $out = strtotime($entry['time_out']);
                    $hours = max(0, ($out - $in) / 3600);
                    if ($hours > 8) {
                        $regularHours += 8;
                        $otHours += $hours - 8;
                    } else {
                        $regularHours += $hours;
                    }
                }

                $stmt = $db->prepare(
                    'SELECT COUNT(*) AS trip_count, COALESCE(SUM(incentive_amount), 0) AS trip_total
                     FROM trips WHERE user_id = ? AND trip_date BETWEEN ? AND ?'
                );
                $stmt->execute([$userId, $periodStart, $periodEnd]);
                $tripRow = $stmt->fetch(PDO::FETCH_ASSOC);
                $tripCount = (int) $tripRow['trip_count'];
                $tripTotal = (float) $tripRow['trip_total'];

                $basePay = $regularHours * RATE_PER_HOUR;
                $otPay = $otHours * OT_RATE_PER_HOUR;
                $grossPay = $basePay + $otPay + $tripTotal;

                $sss = round($grossPay * SSS_PCT, 2);
                $philhealth = round($grossPay * PHILHEALTH_PCT, 2);
                $pagibig = round($grossPay * PAGIBIG_PCT, 2);
                $totalDeductions = $sss + $philhealth + $pagibig;
                $netPay = $grossPay - $totalDeductions;

                $stmt = $db->prepare(
                    'INSERT INTO payroll_runs
                     (user_id, period_start, period_end, regular_hours, ot_hours, rate_per_hour, ot_rate_per_hour,
                      trip_count, trip_incentive_total, gross_pay, sss_deduction, philhealth_deduction, pagibig_deduction,
                      total_deductions, net_pay, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $userId, $periodStart, $periodEnd, $regularHours, $otHours, RATE_PER_HOUR, OT_RATE_PER_HOUR,
                    $tripCount, $tripTotal, $grossPay, $sss, $philhealth, $pagibig,
                    $totalDeductions, $netPay, 'draft',
                ]);
            }

            $db->commit();
            $success = 'Payroll run completed for ' . count($employees) . ' employee(s).';
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Payroll run failed: ' . $e->getMessage();
        }
    }
}

$runs = $db->query(
    'SELECT pr.*, u.name AS employee_name
     FROM payroll_runs pr
     JOIN users u ON u.id = pr.user_id
     ORDER BY pr.period_start DESC, u.name ASC'
)->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="max-w-5xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6">

  <?php if ($error): ?>
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 rounded-xl p-4 mb-4 text-sm">
      <?php echo htmlspecialchars($error); ?>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 rounded-xl p-4 mb-4 text-sm">
      <?php echo htmlspecialchars($success); ?>
    </div>
  <?php endif; ?>

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6 mb-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-4">Run Payroll</h2>
    <form method="POST" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Period Start</label>
        <input type="date" name="period_start" required style="color-scheme: light;" class="w-full rounded-lg border border-gray-300 dark:border-surface-border dark:bg-surface dark:text-white px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Period End</label>
        <input type="date" name="period_end" required style="color-scheme: light;" class="w-full rounded-lg border border-gray-300 dark:border-surface-border dark:bg-surface dark:text-white px-3 py-2 text-sm">
      </div>
      <button type="submit" class="bg-brand-green text-white font-bold tracking-wide rounded-full px-5 py-2.5 hover:opacity-90 transition">
        RUN PAYROLL
      </button>
    </form>
  </div>

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-4">Payroll Breakdown</h2>
    <div class="overflow-x-auto">
      <table class="w-full text-sm text-left whitespace-nowrap">
        <thead>
          <tr class="text-orange-600 dark:text-brand-yellow font-bold">
            <th class="pr-6 pb-3">Employee</th>
            <th class="pr-6 pb-3">Period</th>
            <th class="pr-6 pb-3">Reg Hrs</th>
            <th class="pr-6 pb-3">OT Hrs</th>
            <th class="pr-6 pb-3">Trips</th>
            <th class="pr-6 pb-3">Basic</th>
            <th class="pr-6 pb-3">OT Pay</th>
            <th class="pr-6 pb-3">Incentives</th>
            <th class="pr-6 pb-3">SSS</th>
            <th class="pr-6 pb-3">PhilHealth</th>
            <th class="pr-6 pb-3">Pag-IBIG</th>
            <th class="pb-3">Net Pay</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($runs)): ?>
            <tr class="border-t border-gray-200 dark:border-surface-border">
              <td colspan="12" class="text-center text-gray-500 dark:text-gray-400 py-6">No payroll runs yet — use Run Payroll above to get started</td>
            </tr>
          <?php else: ?>
            <?php foreach ($runs as $run): ?>
              <tr class="border-t border-gray-200 dark:border-surface-border text-gray-900 dark:text-white">
                <td class="pr-6 py-2"><?php echo htmlspecialchars($run['employee_name']); ?></td>
                <td class="pr-6 py-2"><?php echo htmlspecialchars($run['period_start'] . ' – ' . $run['period_end']); ?></td>
                <td class="pr-6 py-2"><?php echo htmlspecialchars($run['regular_hours']); ?></td>
                <td class="pr-6 py-2"><?php echo htmlspecialchars($run['ot_hours']); ?></td>
                <td class="pr-6 py-2"><?php echo htmlspecialchars($run['trip_count']); ?></td>
                <td class="pr-6 py-2">₱<?php echo number_format($run['regular_hours'] * $run['rate_per_hour'], 2); ?></td>
                <td class="pr-6 py-2">₱<?php echo number_format($run['ot_hours'] * $run['ot_rate_per_hour'], 2); ?></td>
                <td class="pr-6 py-2">₱<?php echo number_format($run['trip_incentive_total'], 2); ?></td>
                <td class="pr-6 py-2">₱<?php echo number_format($run['sss_deduction'], 2); ?></td>
                <td class="pr-6 py-2">₱<?php echo number_format($run['philhealth_deduction'], 2); ?></td>
                <td class="pr-6 py-2">₱<?php echo number_format($run['pagibig_deduction'], 2); ?></td>
                <td class="py-2 font-bold">₱<?php echo number_format($run['net_pay'], 2); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>

<?php
$navBase = BASE_PATH;
include __DIR__ . '/../includes/bottom-nav.php';
include __DIR__ . '/../includes/foot.php';
?>
