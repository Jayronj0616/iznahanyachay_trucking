<?php
/**
 * Payroll arithmetic, shared.
 *
 * All of this used to live inside payroll/index.php, which was fine while payroll was
 * the only thing that computed pay. The Overview now has to show what an employee has
 * earned so far in an open period, before any run exists, and a second copy of this
 * maths is how the Overview and the payslip start quietly disagreeing about the same
 * peso. One source of truth instead.
 *
 * Moved verbatim -- the rates, the contribution tables and the allocation order are
 * unchanged. The refactor was verified by recomputing every existing payroll_runs row
 * through these functions and checking the results matched what is stored.
 */

const RATE_PER_HOUR = 100.00;
const OT_RATE_PER_HOUR = 110.00;

// Driver and helper are paid a commission on the route rate, not by the hour.
const DRIVER_TRIP_RATE = 0.15;
const HELPER_TRIP_RATE = 0.08;

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

/**
 * What one employee earned in a period, before deductions.
 *
 * Hourly staff are paid from approved timesheet entries only, with anything past 8
 * hours in a single day counting as overtime. Driver and helper are paid a commission
 * on completed trips instead and never punch a timesheet at all.
 *
 * Returns ['regular_hours', 'ot_hours', 'trip_count', 'trip_incentive_total',
 *          'base_pay', 'ot_pay', 'gross_pay'].
 */
function computePeriodEarnings(PDO $db, int $userId, ?string $position, string $periodStart, string $periodEnd): array
{
    $isDriver = $position === 'driver';
    $isHelper = $position === 'helper';

    $regularHours = 0.0;
    $otHours = 0.0;
    $tripCount = 0;
    $tripTotal = 0.0;

    if ($isDriver || $isHelper) {
        $column = $isDriver ? 'driver_id' : 'helper_id';
        $rate = $isDriver ? DRIVER_TRIP_RATE : HELPER_TRIP_RATE;

        $stmt = $db->prepare(
            "SELECT COUNT(*) AS trip_count, COALESCE(SUM(amount_per_trip), 0) AS trip_sum
             FROM trips_new
             WHERE $column = ? AND status = 'completed' AND DATE(completed_at) BETWEEN ? AND ?"
        );
        $stmt->execute([$userId, $periodStart, $periodEnd]);
        $tripRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $tripCount = (int) $tripRow['trip_count'];
        $tripTotal = round((float) $tripRow['trip_sum'] * $rate, 2);
    } else {
        $stmt = $db->prepare(
            'SELECT time_in, time_out FROM timesheet_entries
             WHERE user_id = ? AND date BETWEEN ? AND ? AND time_in IS NOT NULL AND time_out IS NOT NULL AND status = "approved"'
        );
        $stmt->execute([$userId, $periodStart, $periodEnd]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $entry) {
            $hours = max(0, (strtotime($entry['time_out']) - strtotime($entry['time_in'])) / 3600);
            if ($hours > 8) {
                $regularHours += 8;
                $otHours += $hours - 8;
            } else {
                $regularHours += $hours;
            }
        }
    }

    $basePay = $regularHours * RATE_PER_HOUR;
    $otPay = $otHours * OT_RATE_PER_HOUR;

    return [
        'regular_hours' => $regularHours,
        'ot_hours' => $otHours,
        'trip_count' => $tripCount,
        'trip_incentive_total' => $tripTotal,
        'base_pay' => $basePay,
        'ot_pay' => $otPay,
        'gross_pay' => $basePay + $otPay + $tripTotal,
    ];
}

/**
 * Statutory deductions for a gross, and the net that survives them.
 *
 * SSS and PhilHealth both have floors (MSC 5,000 and base 10,000), so on a very small
 * gross the computed contributions can exceed what was actually earned -- a helper on
 * a cheap route can gross less than the ~265 minimum. Flooring net pay at 0 alone was
 * not enough: total_deductions and the itemised deduction rows kept their uncapped
 * values, so the run did not reconcile (gross - deductions != net) and the deductions
 * modal disagreed with the summary line.
 *
 * Nothing can be withheld that was not earned, so the contributions are allocated in
 * statutory order against whatever gross exists, and each row is reported at the
 * amount actually taken.
 *
 * Returns ['sss', 'philhealth', 'pagibig', 'total_deductions', 'net_pay',
 *          'sss_note', 'philhealth_note', 'pagibig_note'].
 */
function applyDeductions(float $grossPay): array
{
    [$sss, $sssNote] = calculateSSS($grossPay);
    [$philhealth, $philhealthNote] = calculatePhilHealth($grossPay);
    [$pagibig, $pagibigNote] = calculatePagibig($grossPay);

    $remaining = $grossPay;
    $allocate = function (float $amount) use (&$remaining): float {
        $taken = min($amount, $remaining);
        $remaining = round($remaining - $taken, 2);
        return round($taken, 2);
    };

    $sssFull = $sss;
    $philhealthFull = $philhealth;
    $pagibigFull = $pagibig;

    $sss = $allocate($sssFull);
    $philhealth = $allocate($philhealthFull);
    $pagibig = $allocate($pagibigFull);

    if ($sss < $sssFull) {
        $sssNote .= sprintf(' — capped at %.2f of %.2f, gross too low', $sss, $sssFull);
    }
    if ($philhealth < $philhealthFull) {
        $philhealthNote .= sprintf(' — capped at %.2f of %.2f, gross too low', $philhealth, $philhealthFull);
    }
    if ($pagibig < $pagibigFull) {
        $pagibigNote .= sprintf(' — capped at %.2f of %.2f, gross too low', $pagibig, $pagibigFull);
    }

    $totalDeductions = round($sss + $philhealth + $pagibig, 2);

    return [
        'sss' => $sss,
        'philhealth' => $philhealth,
        'pagibig' => $pagibig,
        'total_deductions' => $totalDeductions,
        'net_pay' => round($grossPay - $totalDeductions, 2),
        'sss_note' => $sssNote,
        'philhealth_note' => $philhealthNote,
        'pagibig_note' => $pagibigNote,
    ];
}
