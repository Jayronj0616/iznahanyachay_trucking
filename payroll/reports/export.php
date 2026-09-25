<?php
/**
 * CSV export of the payroll register for one period.
 *
 * Before this, nothing in the system could leave the screen — there was no CSV, no PDF and
 * no print. Handing figures to an accountant meant retyping them, which is exactly how
 * payroll numbers drift.
 *
 * Deliberately emits RAW NUMBERS: no peso sign, no thousands separators. A spreadsheet must
 * be able to sum this column. Formatting it prettily would turn every amount into text.
 *
 * No HTML is included here and nothing may be echoed before the headers, so this file does
 * not include head.php or topbar.php the way a page would.
 */

require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db = getDB();

$periodStart = $_GET['period_start'] ?? '';
$periodEnd = $_GET['period_end'] ?? '';
$includeDrafts = ($_GET['status'] ?? 'finalized') === 'all';

// Only export a period that exists. Without this an arbitrary pair of dates would download
// an empty file that looks like a period with no payroll.
$stmt = $db->prepare(
    'SELECT COUNT(*) FROM payroll_runs WHERE period_start = ? AND period_end = ?'
);
$stmt->execute([$periodStart, $periodEnd]);
if (!$periodStart || !$periodEnd || (int) $stmt->fetchColumn() === 0) {
    header('Location: ' . BASE_PATH . '/payroll/reports/');
    exit;
}

$sql =
    'SELECT pr.*, u.name AS employee_name, u.email, ep.position
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

$positionLabels = [
    'driver' => 'Driver', 'helper' => 'Helper', 'dispatcher' => 'Dispatcher',
    'secretary' => 'Secretary', 'maintenance' => 'Maintenance', 'liaison' => 'Liaison',
    'operator_manager' => 'Operator Manager',
];

$filename = 'payroll-register-' . $periodStart . '-to-' . $periodEnd
    . ($includeDrafts ? '-incl-drafts' : '') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');

// UTF-8 BOM so Excel reads accented names correctly instead of mangling them.
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['Iznahanyachay Trucking Services - Payroll Register']);
fputcsv($out, ['Period', $periodStart . ' to ' . $periodEnd]);
fputcsv($out, ['Scope', $includeDrafts ? 'Finalized and drafts - NOT for remittance' : 'Finalized only']);
fputcsv($out, ['Generated', date('Y-m-d H:i')]);
fputcsv($out, []);

fputcsv($out, [
    'Employee', 'Email', 'Position', 'Period Start', 'Period End',
    'Regular Hours', 'OT Hours', 'Rate Per Hour', 'OT Rate Per Hour',
    'Trips', 'Basic Pay', 'OT Pay', 'Trip Commission', 'Gross Pay',
    'SSS', 'PhilHealth', 'Pag-IBIG', 'Total Deductions', 'Net Pay', 'Status',
]);

$t = array_fill_keys(
    ['reg', 'ot', 'trips', 'basic', 'otpay', 'comm', 'gross', 'sss', 'ph', 'pi', 'ded', 'net'],
    0.0
);

foreach ($rows as $r) {
    $basePay = (float) $r['regular_hours'] * (float) $r['rate_per_hour'];
    $otPay = (float) $r['ot_hours'] * (float) $r['ot_rate_per_hour'];

    $t['reg']   += (float) $r['regular_hours'];
    $t['ot']    += (float) $r['ot_hours'];
    $t['trips'] += (int) $r['trip_count'];
    $t['basic'] += $basePay;
    $t['otpay'] += $otPay;
    $t['comm']  += (float) $r['trip_incentive_total'];
    $t['gross'] += (float) $r['gross_pay'];
    $t['sss']   += (float) $r['sss_deduction'];
    $t['ph']    += (float) $r['philhealth_deduction'];
    $t['pi']    += (float) $r['pagibig_deduction'];
    $t['ded']   += (float) $r['total_deductions'];
    $t['net']   += (float) $r['net_pay'];

    fputcsv($out, [
        $r['employee_name'],
        $r['email'],
        $positionLabels[$r['position']] ?? '',
        $r['period_start'],
        $r['period_end'],
        number_format((float) $r['regular_hours'], 2, '.', ''),
        number_format((float) $r['ot_hours'], 2, '.', ''),
        number_format((float) $r['rate_per_hour'], 2, '.', ''),
        number_format((float) $r['ot_rate_per_hour'], 2, '.', ''),
        (int) $r['trip_count'],
        number_format($basePay, 2, '.', ''),
        number_format($otPay, 2, '.', ''),
        number_format((float) $r['trip_incentive_total'], 2, '.', ''),
        number_format((float) $r['gross_pay'], 2, '.', ''),
        number_format((float) $r['sss_deduction'], 2, '.', ''),
        number_format((float) $r['philhealth_deduction'], 2, '.', ''),
        number_format((float) $r['pagibig_deduction'], 2, '.', ''),
        number_format((float) $r['total_deductions'], 2, '.', ''),
        number_format((float) $r['net_pay'], 2, '.', ''),
        $r['status'],
    ]);
}

fputcsv($out, [
    'TOTAL', '', count($rows) . ' employees', $periodStart, $periodEnd,
    number_format($t['reg'], 2, '.', ''),
    number_format($t['ot'], 2, '.', ''),
    '', '',
    (int) $t['trips'],
    number_format($t['basic'], 2, '.', ''),
    number_format($t['otpay'], 2, '.', ''),
    number_format($t['comm'], 2, '.', ''),
    number_format($t['gross'], 2, '.', ''),
    number_format($t['sss'], 2, '.', ''),
    number_format($t['ph'], 2, '.', ''),
    number_format($t['pi'], 2, '.', ''),
    number_format($t['ded'], 2, '.', ''),
    number_format($t['net'], 2, '.', ''),
    '',
]);

fclose($out);
exit;
