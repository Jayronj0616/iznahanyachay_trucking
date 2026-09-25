<?php
/**
 * Payroll reports.
 *
 * The Payroll page lists every run ever made, in one table, with no totals and no way to
 * narrow it. That is a working list, not a report: it cannot answer "what did this period
 * cost", "how much SSS do I remit this month", or "give me something I can hand the
 * accountant". This page answers those three.
 *
 * Scoped to ONE period at a time, because every figure here is a total and a total across
 * mixed periods means nothing.
 *
 * DRAFT vs FINALIZED matters more here than anywhere else in the app. A draft run is a
 * computation that nobody has committed to; a finalized one has a locked payslip behind it.
 * Adding the two together would produce a remittance figure that is simply wrong, so this
 * page counts FINALIZED ONLY by default and says plainly how many drafts it left out.
 */

$pageTitle = 'Payroll Reports';
$activeNav = 'payroll';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
include __DIR__ . '/../../includes/head.php';

$pageIcon = '📄';
$pageLabel = 'Payroll Reports';
include __DIR__ . '/../../includes/topbar.php';

$db = getDB();

// Periods that actually have runs. Unlike the Payroll page, which lists periods that COULD
// be run, a report can only describe periods that HAVE been run.
$periods = $db->query(
    'SELECT period_start, period_end, COUNT(*) AS run_count,
            SUM(status = "finalized") AS finalized_count
     FROM payroll_runs
     GROUP BY period_start, period_end
     ORDER BY period_start DESC'
)->fetchAll(PDO::FETCH_ASSOC);

$periodStart = $_GET['period_start'] ?? ($periods[0]['period_start'] ?? '');
$periodEnd   = $_GET['period_end']   ?? ($periods[0]['period_end']   ?? '');
$includeDrafts = ($_GET['status'] ?? 'finalized') === 'all';

// Only accept a period that exists, so a hand-edited URL cannot produce an empty report
// that looks like a period with no payroll rather than a period that does not exist.
$validPeriod = false;
foreach ($periods as $p) {
    if ($p['period_start'] === $periodStart && $p['period_end'] === $periodEnd) {
        $validPeriod = true;
        break;
    }
}
if (!$validPeriod && !empty($periods)) {
    $periodStart = $periods[0]['period_start'];
    $periodEnd   = $periods[0]['period_end'];
}

$rows = [];
$draftsExcluded = 0;

if ($periodStart && $periodEnd) {
    $sql =
        'SELECT pr.*, u.name AS employee_name, ep.position
         FROM payroll_runs pr
         JOIN users u ON u.id = pr.user_id
         LEFT JOIN employee_profiles ep ON ep.user_id = pr.user_id
         WHERE pr.period_start = ? AND pr.period_end = ?';
    if (!$includeDrafts) {
        $sql .= " AND pr.status = 'finalized'";
    }
    $sql .= ' ORDER BY u.name ASC';

    $stmt = $db->prepare($sql);
    $stmt->execute([$periodStart, $periodEnd]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM payroll_runs
         WHERE period_start = ? AND period_end = ? AND status = 'draft'"
    );
    $stmt->execute([$periodStart, $periodEnd]);
    $draftsExcluded = (int) $stmt->fetchColumn();
}

// Totals. Basic and overtime pay are derived the same way the payslip derives them, from
// the hours and the rate snapshotted onto the run, so this page can never disagree with a
// payslip about the same period.
$t = [
    'headcount' => count($rows),
    'regular_hours' => 0.0, 'ot_hours' => 0.0, 'trip_count' => 0,
    'base_pay' => 0.0, 'ot_pay' => 0.0, 'commission' => 0.0, 'gross' => 0.0,
    'sss' => 0.0, 'philhealth' => 0.0, 'pagibig' => 0.0, 'deductions' => 0.0, 'net' => 0.0,
];
// The two pay models this company runs on, reported separately because they answer
// different questions: one is a wage bill, the other is a cost of deliveries.
$byModel = [
    'commission' => ['label' => 'Drivers & helpers (trip commission)', 'count' => 0, 'gross' => 0.0, 'net' => 0.0],
    'hourly'     => ['label' => 'Hourly staff (timesheet)',            'count' => 0, 'gross' => 0.0, 'net' => 0.0],
];

foreach ($rows as &$r) {
    $r['base_pay'] = (float) $r['regular_hours'] * (float) $r['rate_per_hour'];
    $r['ot_pay']   = (float) $r['ot_hours'] * (float) $r['ot_rate_per_hour'];

    $t['regular_hours'] += (float) $r['regular_hours'];
    $t['ot_hours']      += (float) $r['ot_hours'];
    $t['trip_count']    += (int) $r['trip_count'];
    $t['base_pay']      += $r['base_pay'];
    $t['ot_pay']        += $r['ot_pay'];
    $t['commission']    += (float) $r['trip_incentive_total'];
    $t['gross']         += (float) $r['gross_pay'];
    $t['sss']           += (float) $r['sss_deduction'];
    $t['philhealth']    += (float) $r['philhealth_deduction'];
    $t['pagibig']       += (float) $r['pagibig_deduction'];
    $t['deductions']    += (float) $r['total_deductions'];
    $t['net']           += (float) $r['net_pay'];

    $model = in_array($r['position'], ['driver', 'helper'], true) ? 'commission' : 'hourly';
    $byModel[$model]['count']++;
    $byModel[$model]['gross'] += (float) $r['gross_pay'];
    $byModel[$model]['net']   += (float) $r['net_pay'];
}
unset($r);

$positionLabels = [
    'driver' => 'Driver', 'helper' => 'Helper', 'dispatcher' => 'Dispatcher',
    'secretary' => 'Secretary', 'maintenance' => 'Maintenance', 'liaison' => 'Liaison',
    'operator_manager' => 'Operator Manager',
];

function peso(float $n): string { return '₱' . number_format($n, 2); }
function hrs(float $n): string { return rtrim(rtrim(number_format($n, 2), '0'), '.') ?: '0'; }

$periodLabel = $periodStart
    ? date('d M Y', strtotime($periodStart)) . ' – ' . date('d M Y', strtotime($periodEnd))
    : '';
$exportQuery = http_build_query([
    'period_start' => $periodStart,
    'period_end' => $periodEnd,
    'status' => $includeDrafts ? 'all' : 'finalized',
]);
?>

<main class="max-w-7xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6 space-y-4">

  <?php if (empty($periods)): ?>
    <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
      <h2 class="text-gray-900 dark:text-white font-bold mb-1">No payroll to report on yet</h2>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Reports describe payroll that has already been run. Run a period on the
        <a href="<?php echo BASE_PATH; ?>/payroll/" class="text-orange-600 dark:text-brand-yellow font-semibold">Payroll page</a>
        first, and it will appear here.
      </p>
    </div>
  <?php else: ?>

    <!-- Period + scope -->
    <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
      <h2 class="text-gray-900 dark:text-white font-bold mb-4">Report Period</h2>
      <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[240px]">
          <label for="rep-period" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Period</label>
          <select id="rep-period" name="period" onchange="tsApplyPeriod(this)" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
            <?php foreach ($periods as $p): ?>
              <option
                value="<?php echo htmlspecialchars($p['period_start'] . '|' . $p['period_end']); ?>"
                <?php echo ($p['period_start'] === $periodStart && $p['period_end'] === $periodEnd) ? 'selected' : ''; ?>
              >
                <?php
                  echo htmlspecialchars(date('d M Y', strtotime($p['period_start'])) . ' – ' . date('d M Y', strtotime($p['period_end'])));
                  echo ' (' . (int) $p['finalized_count'] . ' of ' . (int) $p['run_count'] . ' finalized)';
                ?>
              </option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="period_start" id="rep-start" value="<?php echo htmlspecialchars($periodStart); ?>">
          <input type="hidden" name="period_end" id="rep-end" value="<?php echo htmlspecialchars($periodEnd); ?>">
        </div>
        <div class="min-w-[200px]">
          <label for="rep-status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Include</label>
          <select id="rep-status" name="status" class="w-full bg-white dark:bg-surface border border-gray-300 dark:border-surface-border rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:border-brand-yellow">
            <option value="finalized" <?php echo !$includeDrafts ? 'selected' : ''; ?>>Finalized only</option>
            <option value="all" <?php echo $includeDrafts ? 'selected' : ''; ?>>Finalized and drafts</option>
          </select>
        </div>
        <button type="submit" class="bg-brand-orange text-white font-bold rounded-lg px-5 py-2.5 hover:opacity-90 transition">Show Report</button>
        <a href="<?php echo BASE_PATH; ?>/payroll/reports/export.php?<?php echo htmlspecialchars($exportQuery); ?>"
           class="bg-green-600 text-white font-bold rounded-lg px-5 py-2.5 hover:opacity-90 transition">⤓ Export CSV</a>
      </form>

      <?php if (!$includeDrafts && $draftsExcluded > 0): ?>
        <p class="mt-3 text-sm text-amber-700 dark:text-amber-400">
          <?php echo $draftsExcluded; ?> draft run<?php echo $draftsExcluded === 1 ? '' : 's'; ?>
          in this period <?php echo $draftsExcluded === 1 ? 'is' : 'are'; ?> excluded from every figure below.
          Drafts are not committed pay, so counting them would overstate the period. Finalize them on the
          <a href="<?php echo BASE_PATH; ?>/payroll/" class="font-semibold underline">Payroll page</a>, or switch
          the filter above to include them.
        </p>
      <?php elseif ($includeDrafts && $draftsExcluded > 0): ?>
        <p class="mt-3 text-sm text-amber-700 dark:text-amber-400">
          Including <?php echo $draftsExcluded; ?> draft run<?php echo $draftsExcluded === 1 ? '' : 's'; ?>.
          These are not committed pay — do not use this version for remittance.
        </p>
      <?php endif; ?>
    </div>

    <?php if (empty($rows)): ?>
      <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Nothing to report for <?php echo htmlspecialchars($periodLabel); ?>
          <?php echo !$includeDrafts ? ' once drafts are excluded.' : '.'; ?>
        </p>
      </div>
    <?php else: ?>

      <!-- Headline figures -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <?php
          $cards = [
            ['Employees paid', (string) $t['headcount'], 'in this period'],
            ['Total gross', peso($t['gross']), 'before deductions'],
            ['Total deductions', peso($t['deductions']), 'SSS, PhilHealth, Pag-IBIG'],
            ['Total net', peso($t['net']), 'actually paid out'],
          ];
          foreach ($cards as [$label, $value, $sub]):
        ?>
          <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-4">
            <div class="text-xs uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1"><?php echo $label; ?></div>
            <div class="text-xl font-bold text-gray-900 dark:text-white tabular-nums"><?php echo $value; ?></div>
            <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5"><?php echo $sub; ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="grid lg:grid-cols-2 gap-3">
        <!-- Remittance -->
        <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
          <h2 class="text-gray-900 dark:text-white font-bold mb-1">Statutory Remittance</h2>
          <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
            Employee share withheld this period. Employer counterparts are not computed by this system.
          </p>
          <table class="w-full text-sm">
            <tbody class="text-gray-700 dark:text-gray-300">
              <tr class="border-b border-gray-200 dark:border-surface-border">
                <td class="py-2">SSS</td>
                <td class="py-2 text-right tabular-nums font-semibold text-gray-900 dark:text-white"><?php echo peso($t['sss']); ?></td>
              </tr>
              <tr class="border-b border-gray-200 dark:border-surface-border">
                <td class="py-2">PhilHealth</td>
                <td class="py-2 text-right tabular-nums font-semibold text-gray-900 dark:text-white"><?php echo peso($t['philhealth']); ?></td>
              </tr>
              <tr class="border-b border-gray-200 dark:border-surface-border">
                <td class="py-2">Pag-IBIG</td>
                <td class="py-2 text-right tabular-nums font-semibold text-gray-900 dark:text-white"><?php echo peso($t['pagibig']); ?></td>
              </tr>
              <tr>
                <td class="pt-2.5 font-bold text-gray-900 dark:text-white">Total withheld</td>
                <td class="pt-2.5 text-right tabular-nums font-bold text-gray-900 dark:text-white"><?php echo peso($t['deductions']); ?></td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pay model split -->
        <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
          <h2 class="text-gray-900 dark:text-white font-bold mb-1">Cost by Pay Model</h2>
          <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
            The wage bill and the cost of deliveries are different questions, so they are totalled separately.
          </p>
          <table class="w-full text-sm">
            <thead>
              <tr class="text-xs uppercase tracking-wider text-gray-400 dark:text-gray-500 text-left">
                <th class="pb-2 font-medium">Model</th>
                <th class="pb-2 font-medium text-right">People</th>
                <th class="pb-2 font-medium text-right">Gross</th>
                <th class="pb-2 font-medium text-right">Net</th>
              </tr>
            </thead>
            <tbody class="text-gray-700 dark:text-gray-300">
              <?php foreach ($byModel as $m): ?>
                <tr class="border-t border-gray-200 dark:border-surface-border">
                  <td class="py-2"><?php echo htmlspecialchars($m['label']); ?></td>
                  <td class="py-2 text-right tabular-nums"><?php echo $m['count']; ?></td>
                  <td class="py-2 text-right tabular-nums"><?php echo peso($m['gross']); ?></td>
                  <td class="py-2 text-right tabular-nums font-semibold text-gray-900 dark:text-white"><?php echo peso($m['net']); ?></td>
                </tr>
              <?php endforeach; ?>
              <tr class="border-t-2 border-gray-300 dark:border-surface-border font-bold text-gray-900 dark:text-white">
                <td class="pt-2.5">Total</td>
                <td class="pt-2.5 text-right tabular-nums"><?php echo $t['headcount']; ?></td>
                <td class="pt-2.5 text-right tabular-nums"><?php echo peso($t['gross']); ?></td>
                <td class="pt-2.5 text-right tabular-nums"><?php echo peso($t['net']); ?></td>
              </tr>
            </tbody>
          </table>
          <p class="text-xs text-gray-400 dark:text-gray-500 mt-3">
            <?php echo hrs($t['regular_hours']); ?> regular and <?php echo hrs($t['ot_hours']); ?> overtime hours worked,
            <?php echo $t['trip_count']; ?> trip<?php echo $t['trip_count'] === 1 ? '' : 's'; ?> completed.
          </p>
        </div>
      </div>

      <!-- Register -->
      <div class="bg-gray-50 dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl p-6">
        <div class="flex items-baseline justify-between flex-wrap gap-2 mb-1">
          <h2 class="text-gray-900 dark:text-white font-bold">Payroll Register</h2>
          <span class="text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($periodLabel); ?></span>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
          One row per employee, with the period totalled at the bottom.
        </p>
        <div class="overflow-x-auto">
          <table class="w-full text-sm text-left whitespace-nowrap">
            <thead>
              <tr class="text-orange-600 dark:text-brand-yellow font-bold">
                <th class="pr-6 pb-2">Employee</th>
                <th class="pr-6 pb-2">Position</th>
                <th class="pr-6 pb-2 text-right">Reg Hrs</th>
                <th class="pr-6 pb-2 text-right">OT Hrs</th>
                <th class="pr-6 pb-2 text-right">Trips</th>
                <th class="pr-6 pb-2 text-right">Basic</th>
                <th class="pr-6 pb-2 text-right">OT Pay</th>
                <th class="pr-6 pb-2 text-right">Commission</th>
                <th class="pr-6 pb-2 text-right">Gross</th>
                <th class="pr-6 pb-2 text-right">SSS</th>
                <th class="pr-6 pb-2 text-right">PhilHealth</th>
                <th class="pr-6 pb-2 text-right">Pag-IBIG</th>
                <th class="pr-6 pb-2 text-right">Net Pay</th>
                <th class="pb-2">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr class="border-t border-gray-200 dark:border-surface-border text-gray-900 dark:text-white">
                  <td class="pr-6 py-2"><?php echo htmlspecialchars($r['employee_name']); ?></td>
                  <td class="pr-6 py-2 text-gray-500 dark:text-gray-400">
                    <?php echo htmlspecialchars($positionLabels[$r['position']] ?? '—'); ?>
                  </td>
                  <td class="pr-6 py-2 text-right tabular-nums"><?php echo hrs((float) $r['regular_hours']); ?></td>
                  <td class="pr-6 py-2 text-right tabular-nums"><?php echo hrs((float) $r['ot_hours']); ?></td>
                  <td class="pr-6 py-2 text-right tabular-nums"><?php echo (int) $r['trip_count']; ?></td>
                  <td class="pr-6 py-2 text-right tabular-nums"><?php echo peso($r['base_pay']); ?></td>
                  <td class="pr-6 py-2 text-right tabular-nums"><?php echo peso($r['ot_pay']); ?></td>
                  <td class="pr-6 py-2 text-right tabular-nums"><?php echo peso((float) $r['trip_incentive_total']); ?></td>
                  <td class="pr-6 py-2 text-right tabular-nums"><?php echo peso((float) $r['gross_pay']); ?></td>
                  <td class="pr-6 py-2 text-right tabular-nums"><?php echo peso((float) $r['sss_deduction']); ?></td>
                  <td class="pr-6 py-2 text-right tabular-nums"><?php echo peso((float) $r['philhealth_deduction']); ?></td>
                  <td class="pr-6 py-2 text-right tabular-nums"><?php echo peso((float) $r['pagibig_deduction']); ?></td>
                  <td class="pr-6 py-2 text-right tabular-nums font-bold"><?php echo peso((float) $r['net_pay']); ?></td>
                  <td class="py-2">
                    <?php if ($r['status'] === 'finalized'): ?>
                      <span class="inline-block bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-xs font-semibold px-2 py-1 rounded-full">Finalized</span>
                    <?php else: ?>
                      <span class="inline-block bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 text-xs font-semibold px-2 py-1 rounded-full">Draft</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <tr class="border-t-2 border-gray-300 dark:border-surface-border font-bold text-gray-900 dark:text-white">
                <td class="pr-6 py-2.5">TOTAL</td>
                <td class="pr-6 py-2.5 text-gray-500 dark:text-gray-400"><?php echo $t['headcount']; ?> employee<?php echo $t['headcount'] === 1 ? '' : 's'; ?></td>
                <td class="pr-6 py-2.5 text-right tabular-nums"><?php echo hrs($t['regular_hours']); ?></td>
                <td class="pr-6 py-2.5 text-right tabular-nums"><?php echo hrs($t['ot_hours']); ?></td>
                <td class="pr-6 py-2.5 text-right tabular-nums"><?php echo $t['trip_count']; ?></td>
                <td class="pr-6 py-2.5 text-right tabular-nums"><?php echo peso($t['base_pay']); ?></td>
                <td class="pr-6 py-2.5 text-right tabular-nums"><?php echo peso($t['ot_pay']); ?></td>
                <td class="pr-6 py-2.5 text-right tabular-nums"><?php echo peso($t['commission']); ?></td>
                <td class="pr-6 py-2.5 text-right tabular-nums"><?php echo peso($t['gross']); ?></td>
                <td class="pr-6 py-2.5 text-right tabular-nums"><?php echo peso($t['sss']); ?></td>
                <td class="pr-6 py-2.5 text-right tabular-nums"><?php echo peso($t['philhealth']); ?></td>
                <td class="pr-6 py-2.5 text-right tabular-nums"><?php echo peso($t['pagibig']); ?></td>
                <td class="pr-6 py-2.5 text-right tabular-nums"><?php echo peso($t['net']); ?></td>
                <td class="py-2.5"></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    <?php endif; ?>
  <?php endif; ?>
</main>

<script>
  // The period select carries both dates in one value; split it back into the two hidden
  // fields the query string uses, so the URL stays readable and shareable.
  function tsApplyPeriod(sel) {
    var parts = (sel.value || '').split('|');
    document.getElementById('rep-start').value = parts[0] || '';
    document.getElementById('rep-end').value = parts[1] || '';
  }
  document.addEventListener('DOMContentLoaded', function () {
    var sel = document.getElementById('rep-period');
    if (sel) { tsApplyPeriod(sel); }
  });
</script>

<?php
$navBase = BASE_PATH;
include __DIR__ . '/../../includes/bottom-nav.php';
include __DIR__ . '/../../includes/foot.php';
?>
