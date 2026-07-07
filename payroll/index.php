<?php
$pageTitle = 'Payroll';
$activeNav = 'payroll';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$isAdmin = $_SESSION['user']['role'] === 'admin';
include __DIR__ . '/../includes/head.php';

$pageIcon = '💼';
$pageLabel = 'Payroll';
include __DIR__ . '/../includes/topbar.php';

$error = null;
$success = null;

const RATE_PER_HOUR = 100.00;
const OT_RATE_PER_HOUR = 110.00;

// 2026 govt contribution tables, halved for semi-monthly (15/30) cutoffs.
function calculateSSS(float $periodGross): array {
    $monthlyEquiv = $periodGross * 2;
    $msc = min(max($monthlyEquiv, 5000), 35000);
    $msc = floor($msc / 500) * 500;
    $employeeShare = round($msc * 0.05, 2);
    $perCutoff = round($employeeShare / 2, 2);
    return [$perCutoff, "MSC ₱{$msc} (monthly), 5% employee share"];
}

function calculatePhilHealth(float $periodGross): array {
    $monthlyEquiv = $periodGross * 2;
    $base = min(max($monthlyEquiv, 10000), 100000);
    $employeeShare = round($base * 0.025, 2);
    $perCutoff = round($employeeShare / 2, 2);
    return [$perCutoff, "Base ₱{$base} (monthly), 2.5% employee share"];
}

function calculatePagibig(float $periodGross): array {
    $monthlyEquiv = $periodGross * 2;
    $base = min($monthlyEquiv, 10000);
    $rate = $monthlyEquiv <= 1500 ? 0.01 : 0.02;
    $employeeShare = round($base * $rate, 2);
    $perCutoff = round($employeeShare / 2, 2);
    return [$perCutoff, "Base ₱{$base} (monthly), " . ($rate * 100) . "% employee share"];
}

$db = getDB();

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalize_run_id'])) {
    $runId = (int) $_POST['finalize_run_id'];

    $stmt = $db->prepare('SELECT * FROM payroll_runs WHERE id = ? AND status = "draft"');
    $stmt->execute([$runId]);
    $run = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$run) {
        $error = 'Payroll run not found or already finalized.';
    } else {
        $db->beginTransaction();
        try {
            $basePay = $run['regular_hours'] * $run['rate_per_hour'];
            $otPay = $run['ot_hours'] * $run['ot_rate_per_hour'];

            $stmt = $db->prepare(
                'INSERT INTO payslips
                 (payroll_run_id, user_id, period_start, period_end, regular_hours, ot_hours, base_pay, ot_pay,
                  trip_incentive_total, gross_pay, sss_deduction, philhealth_deduction, pagibig_deduction,
                  total_deductions, net_pay)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $run['id'], $run['user_id'], $run['period_start'], $run['period_end'],
                $run['regular_hours'], $run['ot_hours'], $basePay, $otPay,
                $run['trip_incentive_total'], $run['gross_pay'], $run['sss_deduction'],
                $run['philhealth_deduction'], $run['pagibig_deduction'], $run['total_deductions'], $run['net_pay'],
            ]);

            $stmt = $db->prepare('UPDATE payroll_runs SET status = "finalized" WHERE id = ?');
            $stmt->execute([$run['id']]);

            $db->commit();
            $success = 'Payslip finalized.';
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Finalize failed: ' . $e->getMessage();
        }
    }
} elseif ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $periodStart = $_POST['period_start'] ?? '';
    $periodEnd = $_POST['period_end'] ?? '';

    if (!$periodStart || !$periodEnd) {
        $error = 'Please select a period.';
    } else {
        $stmt = $db->prepare(
            "SELECT DISTINCT u.id, u.name FROM users u
             JOIN timesheet_approvals ta ON ta.user_id = u.id
             WHERE u.role = 'employee' AND ta.period_start = ? AND ta.period_end = ?"
        );
        $stmt->execute([$periodStart, $periodEnd]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $skipped = [];
        $ranFor = [];

        $db->beginTransaction();
        try {
            foreach ($employees as $emp) {
                $userId = $emp['id'];

                $stmt = $db->prepare(
                    'SELECT id FROM payroll_runs WHERE user_id = ? AND period_start = ? AND period_end = ?'
                );
                $stmt->execute([$userId, $periodStart, $periodEnd]);
                if ($stmt->fetch()) {
                    $skipped[] = $emp['name'];
                    continue;
                }

                $stmt = $db->prepare(
                    'SELECT time_in, time_out FROM timesheet_entries
                     WHERE user_id = ? AND date BETWEEN ? AND ? AND time_in IS NOT NULL AND time_out IS NOT NULL AND status = "approved"'
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

                [$sss, $sssNote] = calculateSSS($grossPay);
                [$philhealth, $philhealthNote] = calculatePhilHealth($grossPay);
                [$pagibig, $pagibigNote] = calculatePagibig($grossPay);
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

                $runId = (int) $db->lastInsertId();
                $stmt = $db->prepare(
                    'INSERT INTO deductions (payroll_run_id, user_id, type, amount, basis_note) VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([$runId, $userId, 'sss', $sss, $sssNote]);
                $stmt->execute([$runId, $userId, 'philhealth', $philhealth, $philhealthNote]);
                $stmt->execute([$runId, $userId, 'pagibig', $pagibig, $pagibigNote]);
                $ranFor[] = $emp['name'];
            }

            $db->commit();
            $success = 'Payroll run completed for ' . count($ranFor) . ' employee(s).';
            if (!empty($skipped)) {
                $success .= ' Skipped (already run for this period): ' . implode(', ', $skipped) . '.';
            }
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Payroll run failed: ' . $e->getMessage();
        }
    }
}

$eligiblePeriods = $db->query(
    'SELECT DISTINCT period_start, period_end FROM timesheet_approvals ORDER BY period_start DESC'
)->fetchAll(PDO::FETCH_ASSOC);

$runs = $isAdmin
    ? $db->query(
        'SELECT pr.*, u.name AS employee_name
         FROM payroll_runs pr
         JOIN users u ON u.id = pr.user_id
         ORDER BY pr.period_start DESC, u.name ASC'
    )->fetchAll(PDO::FETCH_ASSOC)
    : (function () use ($db) {
        $stmt = $db->prepare(
            'SELECT pr.*, u.name AS employee_name
             FROM payroll_runs pr
             JOIN users u ON u.id = pr.user_id
             WHERE pr.user_id = ?
             ORDER BY pr.period_start DESC'
        );
        $stmt->execute([$_SESSION['user']['id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    })();

$deductionsByRun = [];
if (!empty($runs)) {
    $runIds = array_column($runs, 'id');
    $placeholders = implode(',', array_fill(0, count($runIds), '?'));
    $stmt = $db->prepare("SELECT * FROM deductions WHERE payroll_run_id IN ($placeholders) ORDER BY payroll_run_id, type");
    $stmt->execute($runIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $deductionsByRun[$d['payroll_run_id']][] = $d;
    }
}
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

  <?php if ($isAdmin): ?>
  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6 mb-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-4">Run Payroll</h2>
    <?php if (empty($eligiblePeriods)): ?>
      <p class="text-gray-500 dark:text-gray-400 text-sm">No approved timesheet periods yet. Approve a period first in Timesheet Review.</p>
    <?php else: ?>
    <form method="POST" data-confirm="Run payroll for all employees in this period? This will create a payroll record for each employee not yet run." class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
      <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Period</label>
        <select name="period" onchange="var v=this.value.split('|'); this.form.period_start.value=v[0]; this.form.period_end.value=v[1];" class="w-full rounded-lg border border-gray-300 dark:border-surface-border dark:bg-surface dark:text-white px-3 py-2 text-sm">
          <?php foreach ($eligiblePeriods as $p): ?>
            <option value="<?php echo htmlspecialchars($p['period_start'] . '|' . $p['period_end']); ?>"><?php echo htmlspecialchars($p['period_start'] . ' to ' . $p['period_end']); ?></option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="period_start" value="<?php echo htmlspecialchars($eligiblePeriods[0]['period_start']); ?>">
        <input type="hidden" name="period_end" value="<?php echo htmlspecialchars($eligiblePeriods[0]['period_end']); ?>">
      </div>
      <button type="submit" class="bg-brand-green text-white font-bold tracking-wide rounded-full px-5 py-2.5 hover:opacity-90 transition">
        RUN PAYROLL
      </button>
    </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>

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
            <th class="pr-6 pb-3">Net Pay</th>
            <th class="pr-6 pb-3">Status</th>
            <th class="pb-3">Details</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($runs)): ?>
            <tr class="border-t border-gray-200 dark:border-surface-border">
              <td colspan="14" class="text-center text-gray-500 dark:text-gray-400 py-6">No payroll runs yet — use Run Payroll above to get started</td>
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
                <td class="pr-6 py-2 font-bold">₱<?php echo number_format($run['net_pay'], 2); ?></td>
                <td class="py-2">
                  <?php if ($run['status'] === 'finalized'): ?>
                    <span class="inline-block bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-xs font-semibold px-2 py-1 rounded-full">Finalized</span>
                  <?php elseif ($isAdmin): ?>
                    <form method="POST" data-confirm="Finalize this payslip? This cannot be undone.">
                      <input type="hidden" name="finalize_run_id" value="<?php echo (int) $run['id']; ?>">
                      <button type="submit" class="bg-brand-orange text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:opacity-90 transition">Finalize</button>
                    </form>
                  <?php else: ?>
                    <span class="inline-block bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 text-xs font-semibold px-2 py-1 rounded-full">Draft</span>
                  <?php endif; ?>
                </td>
                <td class="py-2">
                  <?php if (!empty($deductionsByRun[$run['id']])): ?>
                    <?php
                      $dedPayload = array_map(function ($d) {
                          return [
                              'type' => $d['type'],
                              'amount' => number_format((float) $d['amount'], 2),
                              'note' => $d['basis_note'],
                              'created_at' => $d['created_at'],
                          ];
                      }, $deductionsByRun[$run['id']]);
                    ?>
                    <button type="button"
                      onclick='openDeductionsModal(<?php echo htmlspecialchars(json_encode($dedPayload), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($run['employee_name'] . " \u2014 " . $run['period_start'] . " to " . $run['period_end']), ENT_QUOTES); ?>)'
                      class="bg-brand-orange text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:opacity-90 transition">View</button>
                  <?php else: ?>
                    <span class="text-gray-400 text-xs">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>

<div id="deductions-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center px-4">
  <div id="deductions-modal-backdrop" class="absolute inset-0 bg-black/50"></div>
  <div class="relative bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl shadow-xl max-w-lg w-full p-6">
    <h3 class="text-gray-900 dark:text-white font-bold text-base mb-1">Deduction Transactions</h3>
    <p id="deductions-modal-subtitle" class="text-xs text-gray-500 dark:text-gray-400 mb-4"></p>
    <div class="overflow-x-auto">
      <table class="w-full text-xs text-left">
        <thead>
          <tr class="text-orange-600 dark:text-brand-yellow font-bold">
            <th class="pr-4 pb-2">Type</th>
            <th class="pr-4 pb-2">Amount</th>
            <th class="pr-4 pb-2">Basis</th>
            <th class="pb-2">Recorded</th>
          </tr>
        </thead>
        <tbody id="deductions-modal-body" class="text-gray-700 dark:text-gray-300"></tbody>
      </table>
    </div>
    <div class="flex justify-end mt-6">
      <button type="button" id="deductions-modal-close" class="text-sm font-semibold text-gray-600 dark:text-gray-300 px-4 py-2 rounded-full hover:bg-gray-100 dark:hover:bg-white/5 transition">Close</button>
    </div>
  </div>
</div>

<script>
(function () {
  var modal = document.getElementById('deductions-modal');
  var backdrop = document.getElementById('deductions-modal-backdrop');
  var closeBtn = document.getElementById('deductions-modal-close');
  var subtitle = document.getElementById('deductions-modal-subtitle');
  var body = document.getElementById('deductions-modal-body');

  window.openDeductionsModal = function (deductions, label) {
    subtitle.textContent = label;
    body.innerHTML = deductions.map(function (d) {
      return '<tr class="border-t border-gray-100 dark:border-surface-border">'
        + '<td class="pr-4 py-2 font-semibold uppercase">' + d.type + '</td>'
        + '<td class="pr-4 py-2">\u20b1' + d.amount + '</td>'
        + '<td class="pr-4 py-2 text-gray-500 dark:text-gray-400">' + d.note + '</td>'
        + '<td class="py-2 text-gray-400">' + d.created_at + '</td>'
        + '</tr>';
    }).join('');
    modal.classList.remove('hidden');
  };

  function closeModal() {
    modal.classList.add('hidden');
  }

  closeBtn.addEventListener('click', closeModal);
  backdrop.addEventListener('click', closeModal);
})();
</script>

<?php
$navBase = BASE_PATH;
include __DIR__ . '/../includes/bottom-nav.php';
include __DIR__ . '/../includes/confirm-modal.php';
include __DIR__ . '/../includes/foot.php';
?>
