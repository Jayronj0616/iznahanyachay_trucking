<?php
// Reader for the payslips table.
//
// Until now this table was write-only: payroll/index.php INSERTed a snapshot on Finalize
// and nothing in the app ever read it back. Both roles were shown payroll_runs instead —
// the mutable draft table — so no employee could view a payslip anywhere, and Finalize had
// no visible consequence at all. This page is that consequence.
//
// It renders the SNAPSHOT, never the live run. That is the whole point of the payslips
// table: once finalized, what the employee was told they were paid must not change if a
// rate constant or a deduction formula is edited later.

$pageTitle = 'Payslip';
$activeNav = 'payroll';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
$isAdmin = $_SESSION['user']['role'] === 'admin';
include __DIR__ . '/../../includes/head.php';

$pageIcon = '🧾';
$pageLabel = 'Payslip';
include __DIR__ . '/../../includes/topbar.php';

$db = getDB();
$payslipId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$payslip = null;
$error = null;

if (!$payslipId) {
    $error = 'No payslip selected.';
} else {
    $stmt = $db->prepare(
        'SELECT ps.*, u.name AS employee_name, u.email AS employee_email,
                ep.position, ep.license_number, ep.hire_date
         FROM payslips ps
         JOIN users u ON u.id = ps.user_id
         LEFT JOIN employee_profiles ep ON ep.user_id = ps.user_id
         WHERE ps.id = ?'
    );
    $stmt->execute([$payslipId]);
    $payslip = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payslip) {
        $error = 'Payslip not found.';
    } elseif (!$isAdmin && (int) $payslip['user_id'] !== (int) $_SESSION['user']['id']) {
        // Ownership check. An employee may only ever open their own payslip; without this
        // any logged-in user could read a colleague's pay by incrementing the id.
        $payslip = null;
        $error = 'You do not have access to that payslip.';
    }
}

// Itemised statutory deductions are stored against the payroll RUN, not the payslip, so
// they are looked up via payroll_run_id. Falls back to the snapshot's own summary columns
// if the itemised rows are missing (pre-migration-019 runs).
$deductionRows = [];
if ($payslip) {
    $stmt = $db->prepare('SELECT * FROM deductions WHERE payroll_run_id = ? AND user_id = ? ORDER BY type');
    $stmt->execute([$payslip['payroll_run_id'], $payslip['user_id']]);
    $deductionRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$deductionLabels = ['sss' => 'SSS', 'philhealth' => 'PhilHealth', 'pagibig' => 'Pag-IBIG'];
$positionLabels = [
    'driver' => 'Driver', 'helper' => 'Helper', 'dispatcher' => 'Dispatcher',
    'secretary' => 'Secretary', 'maintenance' => 'Maintenance', 'liaison' => 'Liaison',
    'operator_manager' => 'Operations Manager',
];
?>

<main class="max-w-3xl mx-auto w-full px-4 pb-32 pt-4 sm:px-6">

  <?php if ($error): ?>
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 rounded-xl p-4 text-sm">
      <?php echo htmlspecialchars($error); ?>
    </div>
    <a href="<?php echo BASE_PATH; ?>/payroll/" class="inline-block mt-4 text-sm font-semibold text-brand-orange dark:text-brand-yellow hover:underline">← Back to Payroll</a>
  <?php else: ?>

    <div class="flex items-center justify-between gap-3 mb-4 print:hidden">
      <a href="<?php echo BASE_PATH; ?>/payroll/" class="text-sm font-semibold text-brand-orange dark:text-brand-yellow hover:underline">← Back to Payroll</a>
      <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 bg-brand-orange text-white font-semibold text-sm rounded-full px-5 py-2.5 hover:opacity-90 transition">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
        Print
      </button>
    </div>

    <div class="bg-white dark:bg-surface-card border border-gray-200 dark:border-surface-border rounded-xl overflow-hidden print:border-0">

      <!-- Header -->
      <div class="flex items-start justify-between gap-4 p-6 border-b border-gray-200 dark:border-surface-border">
        <div class="flex items-center gap-3 min-w-0">
          <img src="<?php echo BASE_PATH; ?>/assets/images/logo.png" alt="" class="h-12 w-12 rounded-full shrink-0">
          <div class="min-w-0">
            <div class="text-brand-orange dark:text-brand-yellow font-extrabold tracking-wide">IZNAHANYACHAY</div>
            <div class="text-[10px] tracking-[0.2em] text-gray-400 dark:text-gray-500 uppercase">Trucking Services</div>
          </div>
        </div>
        <div class="text-right shrink-0">
          <div class="text-xs uppercase tracking-wider text-gray-400 dark:text-gray-500">Payslip</div>
          <div class="font-bold text-gray-900 dark:text-white">#<?php echo (int) $payslip['id']; ?></div>
          <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
            Issued <?php echo htmlspecialchars(date('d M Y', strtotime($payslip['issued_at']))); ?>
          </div>
        </div>
      </div>

      <!-- Employee + period -->
      <div class="grid sm:grid-cols-2 gap-6 p-6 border-b border-gray-200 dark:border-surface-border">
        <div>
          <div class="text-xs uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">Employee</div>
          <div class="font-bold text-gray-900 dark:text-white"><?php echo htmlspecialchars($payslip['employee_name']); ?></div>
          <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($payslip['employee_email']); ?></div>
          <?php if (!empty($payslip['position'])): ?>
            <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
              <?php echo htmlspecialchars($positionLabels[$payslip['position']] ?? ucfirst($payslip['position'])); ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="sm:text-right">
          <div class="text-xs uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">Pay period</div>
          <div class="font-bold text-gray-900 dark:text-white">
            <?php echo htmlspecialchars(date('d M Y', strtotime($payslip['period_start']))); ?>
            &ndash;
            <?php echo htmlspecialchars(date('d M Y', strtotime($payslip['period_end']))); ?>
          </div>
        </div>
      </div>

      <!-- Earnings -->
      <div class="p-6 border-b border-gray-200 dark:border-surface-border">
        <div class="text-xs uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">Earnings</div>
        <table class="w-full text-sm">
          <tbody class="text-gray-700 dark:text-gray-300">
            <?php if ((float) $payslip['base_pay'] > 0 || (float) $payslip['regular_hours'] > 0): ?>
              <tr>
                <td class="py-1.5">Regular pay<span class="text-gray-400 dark:text-gray-500"> · <?php echo rtrim(rtrim($payslip['regular_hours'], '0'), '.'); ?> hrs</span></td>
                <td class="py-1.5 text-right tabular-nums">₱<?php echo number_format($payslip['base_pay'], 2); ?></td>
              </tr>
            <?php endif; ?>
            <?php if ((float) $payslip['ot_pay'] > 0 || (float) $payslip['ot_hours'] > 0): ?>
              <tr>
                <td class="py-1.5">Overtime<span class="text-gray-400 dark:text-gray-500"> · <?php echo rtrim(rtrim($payslip['ot_hours'], '0'), '.'); ?> hrs</span></td>
                <td class="py-1.5 text-right tabular-nums">₱<?php echo number_format($payslip['ot_pay'], 2); ?></td>
              </tr>
            <?php endif; ?>
            <?php if ((float) $payslip['trip_incentive_total'] > 0): ?>
              <tr>
                <td class="py-1.5">Trip commission</td>
                <td class="py-1.5 text-right tabular-nums">₱<?php echo number_format($payslip['trip_incentive_total'], 2); ?></td>
              </tr>
            <?php endif; ?>
            <tr class="border-t border-gray-200 dark:border-surface-border font-bold text-gray-900 dark:text-white">
              <td class="pt-2.5">Gross pay</td>
              <td class="pt-2.5 text-right tabular-nums">₱<?php echo number_format($payslip['gross_pay'], 2); ?></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Deductions -->
      <div class="p-6 border-b border-gray-200 dark:border-surface-border">
        <div class="text-xs uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">Deductions</div>
        <table class="w-full text-sm">
          <tbody class="text-gray-700 dark:text-gray-300">
            <?php if (!empty($deductionRows)): ?>
              <?php foreach ($deductionRows as $d): ?>
                <tr>
                  <td class="py-1.5">
                    <?php echo htmlspecialchars($deductionLabels[$d['type']] ?? ucfirst($d['type'])); ?>
                    <?php if (!empty($d['basis_note'])): ?>
                      <span class="block text-xs text-gray-400 dark:text-gray-500"><?php echo htmlspecialchars($d['basis_note']); ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="py-1.5 text-right tabular-nums align-top">₱<?php echo number_format($d['amount'], 2); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td class="py-1.5">SSS</td><td class="py-1.5 text-right tabular-nums">₱<?php echo number_format($payslip['sss_deduction'], 2); ?></td></tr>
              <tr><td class="py-1.5">PhilHealth</td><td class="py-1.5 text-right tabular-nums">₱<?php echo number_format($payslip['philhealth_deduction'], 2); ?></td></tr>
              <tr><td class="py-1.5">Pag-IBIG</td><td class="py-1.5 text-right tabular-nums">₱<?php echo number_format($payslip['pagibig_deduction'], 2); ?></td></tr>
            <?php endif; ?>
            <tr class="border-t border-gray-200 dark:border-surface-border font-bold text-gray-900 dark:text-white">
              <td class="pt-2.5">Total deductions</td>
              <td class="pt-2.5 text-right tabular-nums">&minus;₱<?php echo number_format($payslip['total_deductions'], 2); ?></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Net -->
      <div class="flex items-center justify-between gap-4 p-6 bg-orange-50 dark:bg-brand-yellow/5">
        <div>
          <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Net pay</div>
          <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Gross less total deductions</div>
        </div>
        <div class="text-3xl font-extrabold text-brand-orange dark:text-brand-yellow tabular-nums">
          ₱<?php echo number_format($payslip['net_pay'], 2); ?>
        </div>
      </div>
    </div>

    <p class="text-xs text-gray-400 dark:text-gray-500 mt-4 text-center">
      This payslip is a finalized record and does not change if payroll settings are edited later.
    </p>

  <?php endif; ?>
</main>

<style>
  @media print {
    /* Strip the app shell so a printed payslip is just the document. */
    header, nav, footer, .print\:hidden { display: none !important; }
    main { padding: 0 !important; max-width: none !important; }
    body { background: #fff !important; color: #000 !important; }
  }
</style>

<?php include __DIR__ . '/../../includes/foot.php'; ?>
