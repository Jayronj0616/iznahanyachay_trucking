<?php
$pageTitle = 'Payroll';
$activeNav = 'payroll';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payroll.php';
requireLogin();
$isAdmin = $_SESSION['user']['role'] === 'admin';
include __DIR__ . '/../includes/head.php';

$pageIcon = '💼';
$pageLabel = 'Payroll';
include __DIR__ . '/../includes/topbar.php';

$error = null;
$success = null;

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

        // Driver/helper pay is trip-commission-only and isn't gated by approved hourly timesheets,
        // so pull in anyone with a completed trip in this period who wasn't already caught above.
        $stmt = $db->prepare(
            "SELECT DISTINCT u.id, u.name FROM users u
             JOIN employee_profiles ep ON ep.user_id = u.id
             JOIN trips_new t ON (t.driver_id = u.id OR t.helper_id = u.id)
             WHERE ep.position IN ('driver', 'helper') AND t.status = 'completed'
               AND DATE(t.completed_at) BETWEEN ? AND ?"
        );
        $stmt->execute([$periodStart, $periodEnd]);
        $tripEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $seenIds = array_column($employees, 'id');
        foreach ($tripEmployees as $te) {
            if (!in_array($te['id'], $seenIds, true)) {
                $employees[] = $te;
                $seenIds[] = $te['id'];
            }
        }

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

                $stmt = $db->prepare('SELECT position FROM employee_profiles WHERE user_id = ?');
                $stmt->execute([$userId]);
                $position = $stmt->fetchColumn() ?: null;
                $earnings = computePeriodEarnings($db, $userId, $position, $periodStart, $periodEnd);
                $regularHours = $earnings['regular_hours'];
                $otHours = $earnings['ot_hours'];
                $tripCount = $earnings['trip_count'];
                $tripTotal = $earnings['trip_incentive_total'];
                $grossPay = $earnings['gross_pay'];

                $deductions = applyDeductions($grossPay);
                $sss = $deductions['sss'];
                $philhealth = $deductions['philhealth'];
                $pagibig = $deductions['pagibig'];
                $sssNote = $deductions['sss_note'];
                $philhealthNote = $deductions['philhealth_note'];
                $pagibigNote = $deductions['pagibig_note'];
                $totalDeductions = $deductions['total_deductions'];
                $netPay = $deductions['net_pay'];

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

// Eligible periods come from TWO sources, not just timesheet_approvals.
//
// Drivers and helpers are paid by trip commission and never have a timesheet, so they
// never produce a timesheet_approvals row. Sourcing this list from that table alone
// meant a period containing only trip activity never appeared here at all, and those
// commissions could not be paid by any route through the UI. That was live: a trip
// completed 2026-08-07 was unpayable because no hourly employee had an approved August.
//
// Trip periods are expressed as whole calendar months, matching the month-boundary
// convention the existing approval rows already use (e.g. 2026-09-01 .. 2026-09-30).
$periodRows = $db->query(
    'SELECT period_start, period_end, MAX(has_timesheet) AS has_timesheet, MAX(has_trips) AS has_trips FROM (
         SELECT period_start, period_end, 1 AS has_timesheet, 0 AS has_trips
         FROM timesheet_approvals
         UNION ALL
         SELECT DATE_FORMAT(completed_at, "%Y-%m-01") AS period_start,
                LAST_DAY(completed_at) AS period_end,
                0 AS has_timesheet, 1 AS has_trips
         FROM trips_new
         WHERE status = "completed" AND completed_at IS NOT NULL
     ) AS combined
     GROUP BY period_start, period_end
     ORDER BY period_start DESC'
)->fetchAll(PDO::FETCH_ASSOC);

$eligiblePeriods = [];
foreach ($periodRows as $p) {
    // Label what each period actually contains, so the admin can tell a full period
    // from one that only has trip commissions waiting in it.
    if ($p['has_timesheet'] && $p['has_trips']) {
        $source = 'timesheets + trips';
    } elseif ($p['has_trips']) {
        $source = 'trips only';
    } else {
        $source = 'timesheets only';
    }
    $p['source_label'] = $source;
    $eligiblePeriods[] = $p;
}

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
$payslipIdByRun = [];
if (!empty($runs)) {
    $runIds = array_column($runs, 'id');
    $placeholders = implode(',', array_fill(0, count($runIds), '?'));
    $stmt = $db->prepare("SELECT * FROM deductions WHERE payroll_run_id IN ($placeholders) ORDER BY payroll_run_id, type");
    $stmt->execute($runIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $deductionsByRun[$d['payroll_run_id']][] = $d;
    }

    // Maps each finalized run to the payslip snapshot it produced, so the Finalized badge
    // can link to it. Until payroll/payslip/ existed there was nothing to link to and the
    // payslips table had no reader at all.
    $stmt = $db->prepare("SELECT id, payroll_run_id FROM payslips WHERE payroll_run_id IN ($placeholders)");
    $stmt->execute($runIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ps) {
        $payslipIdByRun[$ps['payroll_run_id']] = (int) $ps['id'];
    }
}
?>

<main class="max-w-7xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6">

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
      <p class="text-gray-500 dark:text-gray-400 text-sm">Nothing to pay yet. Approve a timesheet period in Timesheet Review, or accept a delivered trip, and the period will appear here.</p>
    <?php else: ?>
    <form method="POST" data-confirm="Run payroll for all employees in this period? This will create a payroll record for each employee not yet run." class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
      <div class="sm:col-span-2">
        <label for="payroll-period" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Period</label>
        <select id="payroll-period" name="period" onchange="var v=this.value.split('|'); this.form.period_start.value=v[0]; this.form.period_end.value=v[1];" class="w-full rounded-lg border border-gray-300 dark:border-surface-border dark:bg-surface dark:text-white px-3 py-2 text-sm">
          <?php foreach ($eligiblePeriods as $p): ?>
            <option value="<?php echo htmlspecialchars($p['period_start'] . '|' . $p['period_end']); ?>"><?php echo htmlspecialchars($p['period_start'] . ' to ' . $p['period_end'] . ' — ' . $p['source_label']); ?></option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="period_start" value="<?php echo htmlspecialchars($eligiblePeriods[0]['period_start']); ?>">
        <input type="hidden" name="period_end" value="<?php echo htmlspecialchars($eligiblePeriods[0]['period_end']); ?>">
      </div>
      <button type="submit" class="bg-brand-orange text-white font-bold tracking-wide rounded-full px-5 py-2.5 hover:opacity-90 transition">
        RUN PAYROLL
      </button>
    </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
    <h2 class="text-gray-900 dark:text-white font-bold mb-4">Payroll Breakdown</h2>

    <?php
      // A breakdown row is a payslip -- fourteen columns of it. On a desktop that
      // reads fine at full width; on a phone it meant scrolling left and right to
      // read one employee's pay, which is what the client asked to be fixed. So the
      // same data renders as a stacked list below the sm breakpoint and as the table
      // above it.
      //
      // The status chip and the deductions button are identical in both, so they are
      // written once here and called from each. Duplicating them would mean the two
      // layouts drifting the first time either is touched.
      $renderStatus = function (array $run) use ($payslipIdByRun, $isAdmin) { ?>
        <?php if ($run['status'] === 'finalized' && isset($payslipIdByRun[$run['id']])): ?>
          <a href="<?php echo BASE_PATH; ?>/payroll/payslip/?id=<?php echo $payslipIdByRun[$run['id']]; ?>"
             class="inline-flex items-center gap-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-xs font-semibold px-2 py-1 rounded-full hover:opacity-80 transition">
            View Payslip
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
          </a>
        <?php elseif ($run['status'] === 'finalized'): ?>
          <span class="inline-block bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-xs font-semibold px-2 py-1 rounded-full">Finalized</span>
        <?php elseif ($isAdmin): ?>
          <form method="POST" data-confirm="Finalize this payslip? This cannot be undone.">
            <input type="hidden" name="finalize_run_id" value="<?php echo (int) $run['id']; ?>">
            <button type="submit" class="bg-brand-orange text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:opacity-90 transition">Finalize</button>
          </form>
        <?php else: ?>
          <span class="inline-block bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 text-xs font-semibold px-2 py-1 rounded-full">Draft</span>
        <?php endif; ?>
      <?php };

      $renderDetails = function (array $run) use ($deductionsByRun) { ?>
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
            onclick='openDeductionsModal(<?php echo htmlspecialchars(json_encode($dedPayload), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($run['employee_name'] . " — " . $run['period_start'] . " to " . $run['period_end']), ENT_QUOTES); ?>)'
            class="bg-brand-orange text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:opacity-90 transition">View</button>
        <?php else: ?>
          <span class="text-gray-400 text-xs">&mdash;</span>
        <?php endif; ?>
      <?php };

      // Earnings first, then what was taken off, then the net -- the order a payslip
      // is read in. Net is excluded here because it is the card's footer, not a row.
      $runLines = function (array $run) {
          return [
              'Reg Hrs' => number_format((float) $run['regular_hours'], 2),
              'OT Hrs' => number_format((float) $run['ot_hours'], 2),
              'Trips' => (string) (int) $run['trip_count'],
              'Basic' => '₱' . number_format($run['regular_hours'] * $run['rate_per_hour'], 2),
              'OT Pay' => '₱' . number_format($run['ot_hours'] * $run['ot_rate_per_hour'], 2),
              'Incentives' => '₱' . number_format($run['trip_incentive_total'], 2),
              'SSS' => '−₱' . number_format($run['sss_deduction'], 2),
              'PhilHealth' => '−₱' . number_format($run['philhealth_deduction'], 2),
              'Pag-IBIG' => '−₱' . number_format($run['pagibig_deduction'], 2),
          ];
      };
    ?>

    <div class="sm:hidden space-y-4">
      <?php if (empty($runs)): ?>
        <p class="text-center text-gray-500 dark:text-gray-400 py-6 text-sm">No payroll runs yet &mdash; use Run Payroll above to get started</p>
      <?php else: ?>
        <?php foreach ($runs as $run): ?>
          <div class="bg-white dark:bg-surface border border-gray-200 dark:border-surface-border rounded-xl p-4">
            <div class="flex items-start justify-between gap-3 pb-3 border-b border-gray-200 dark:border-surface-border">
              <div class="min-w-0">
                <p class="font-bold text-gray-900 dark:text-white break-words"><?php echo htmlspecialchars($run['employee_name']); ?></p>
                <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($run['period_start'] . ' – ' . $run['period_end']); ?></p>
              </div>
              <div class="shrink-0"><?php $renderStatus($run); ?></div>
            </div>

            <dl class="py-3 space-y-1.5">
              <?php foreach ($runLines($run) as $label => $value): ?>
                <div class="flex items-baseline justify-between gap-4">
                  <dt class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($label); ?></dt>
                  <dd class="text-sm text-gray-900 dark:text-white font-medium tabular-nums"><?php echo $value; ?></dd>
                </div>
              <?php endforeach; ?>
            </dl>

            <div class="flex items-baseline justify-between gap-4 pt-3 border-t border-gray-200 dark:border-surface-border">
              <span class="text-sm font-bold text-gray-900 dark:text-white">Net Pay</span>
              <span class="text-lg font-bold text-gray-900 dark:text-white tabular-nums">₱<?php echo number_format($run['net_pay'], 2); ?></span>
            </div>

            <div class="pt-3"><?php $renderDetails($run); ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="hidden sm:block overflow-x-auto">
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
                <td class="py-2"><?php $renderStatus($run); ?></td>
                <td class="py-2"><?php $renderDetails($run); ?></td>
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
