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

/**
 * Finds the one contribution_brackets row that applies to a monthly-equivalent
 * salary, for a given statutory deduction type.
 *
 * min_monthly is inclusive, max_monthly is exclusive and NULL means "and above", so
 * this always resolves to exactly one row -- there is no separate clamping step
 * before the lookup, the bracket boundaries themselves are the clamp.
 */
function lookupContributionBracket(PDO $db, string $type, float $monthlyEquiv): ?array {
    $stmt = $db->prepare(
        'SELECT * FROM contribution_brackets
         WHERE type = ? AND min_monthly <= ? AND (max_monthly IS NULL OR max_monthly > ?)
         ORDER BY min_monthly DESC LIMIT 1'
    );
    $stmt->execute([$type, $monthlyEquiv, $monthlyEquiv]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * The full MONTHLY employee contribution for one bracket row -- either a fixed peso
 * amount (SSS's real-world table: a salary range maps to a specific contribution,
 * not a formula) or rate * a base clamped between the row's base_min/base_max
 * (PhilHealth and Pag-IBIG: a flat rate over a floored-and-capped base).
 */
function contributionFromBracket(array $row, float $monthlyEquiv): float {
    if ($row['employee_share'] !== null) {
        return (float) $row['employee_share'];
    }
    $base = $monthlyEquiv;
    if ($row['base_min'] !== null) { $base = max($base, (float) $row['base_min']); }
    if ($row['base_max'] !== null) { $base = min($base, (float) $row['base_max']); }
    return round($base * (float) $row['rate'], 2);
}

// Brackets now come from the contribution_brackets table (see migration 030) instead
// of hardcoded formulas, so the panel's ask -- "make it vary by salary size, from the
// database, not hardcoded" -- is answered by editing a row in more/contribution-brackets/
// rather than editing this file. Seeded values reproduce the original 2026 hardcoded
// SSS/PhilHealth/Pag-IBIG formulas exactly (see migration 030's header), so this
// rewrite changes nothing about existing figures on its own -- only a future bracket
// edit can.
function calculateSSS(float $periodGross): array {
    $monthlyEquiv = $periodGross * 2;
    $row = lookupContributionBracket(getDB(), 'sss', $monthlyEquiv);
    if ($row === null) {
        return [0.0, 'No SSS bracket configured for this salary — nothing withheld'];
    }
    $perCutoff = round(contributionFromBracket($row, $monthlyEquiv) / 2, 2);
    return [$perCutoff, (string) $row['notes']];
}

function calculatePhilHealth(float $periodGross): array {
    $monthlyEquiv = $periodGross * 2;
    $row = lookupContributionBracket(getDB(), 'philhealth', $monthlyEquiv);
    if ($row === null) {
        return [0.0, 'No PhilHealth bracket configured for this salary — nothing withheld'];
    }
    $perCutoff = round(contributionFromBracket($row, $monthlyEquiv) / 2, 2);
    return [$perCutoff, (string) $row['notes']];
}

function calculatePagibig(float $periodGross): array {
    $monthlyEquiv = $periodGross * 2;
    $row = lookupContributionBracket(getDB(), 'pagibig', $monthlyEquiv);
    if ($row === null) {
        return [0.0, 'No Pag-IBIG bracket configured for this salary — nothing withheld'];
    }
    $perCutoff = round(contributionFromBracket($row, $monthlyEquiv) / 2, 2);
    return [$perCutoff, (string) $row['notes']];
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
        $rate = $isDriver ? DRIVER_TRIP_RATE : HELPER_TRIP_RATE;

        // A trip can now have several helpers (trip_helpers), each earning the same helper
        // commission independently on the same trip -- a driver still matches by trips_new.driver_id
        // directly, since a trip has exactly one driver.
        if ($isDriver) {
            $stmt = $db->prepare(
                "SELECT COUNT(*) AS trip_count, COALESCE(SUM(amount_per_trip), 0) AS trip_sum
                 FROM trips_new
                 WHERE driver_id = ? AND status = 'completed' AND DATE(completed_at) BETWEEN ? AND ?"
            );
        } else {
            $stmt = $db->prepare(
                "SELECT COUNT(*) AS trip_count, COALESCE(SUM(t.amount_per_trip), 0) AS trip_sum
                 FROM trips_new t
                 JOIN trip_helpers th ON th.trip_id = t.id
                 WHERE th.helper_id = ? AND t.status = 'completed' AND DATE(t.completed_at) BETWEEN ? AND ?"
            );
        }
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
 * $contributionMode is the employee's employee_profiles.government_contribution_mode
 * (migration 029). Some employees remit SSS/PhilHealth/Pag-IBIG themselves instead of
 * having the employer withhold them -- for those, every statutory deduction is 0 and
 * net pay equals gross, with a note explaining why rather than three blank rows that
 * look like a bracket lookup silently found nothing.
 *
 * Returns ['sss', 'philhealth', 'pagibig', 'total_deductions', 'net_pay',
 *          'sss_note', 'philhealth_note', 'pagibig_note'].
 */
function applyDeductions(float $grossPay, string $contributionMode = 'employer_withholds'): array
{
    if ($contributionMode === 'self_remit') {
        $note = 'Employee self-remits this contribution — not withheld by the employer';
        return [
            'sss' => 0.0,
            'philhealth' => 0.0,
            'pagibig' => 0.0,
            'total_deductions' => 0.0,
            'net_pay' => round($grossPay, 2),
            'sss_note' => $note,
            'philhealth_note' => $note,
            'pagibig_note' => $note,
        ];
    }

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
