<?php
/**
 * Rest days and the present / absent / worked-a-rest-day distinction.
 *
 * This logic used to live inline in home/index.php, where it assumed Saturday and
 * Sunday were the only non-working days. It lives here now because the Overview,
 * the dashboard and the timesheet calendar all have to agree about what an absence
 * is -- three separate answers to that question is how an employee ends up disputing
 * a payslip.
 *
 * A day is one of:
 *   rest         the employee's weekly pattern says they are off
 *   worked_rest  a rest day with an approved entry on it -- came in anyway
 *   present      a working day with an entry
 *   absent       a working day, already elapsed, with no entry at all
 *
 * Only `absent` is counted against anyone.
 */

/**
 * Weekly rest-day pattern for one employee, as ISO-8601 day numbers (1 = Mon .. 7 = Sun).
 *
 * Falls back to the weekend when a profile is missing rather than returning nothing:
 * an empty pattern would mean "works every day", which would silently invent absences
 * on weekends for anyone whose profile row has not been created yet.
 */
function employeeRestDays(PDO $db, int $userId): array
{
    $stmt = $db->prepare('SELECT rest_days FROM employee_profiles WHERE user_id = ?');
    $stmt->execute([$userId]);
    $raw = $stmt->fetchColumn();

    if ($raw === false) {
        return [6, 7];
    }

    return parseRestDays((string) $raw);
}

/** Turns the stored '6,7' into [6, 7]. Tolerates spaces, blanks and out-of-range junk. */
function parseRestDays(string $raw): array
{
    $days = [];
    foreach (explode(',', $raw) as $part) {
        $n = (int) trim($part);
        if ($n >= 1 && $n <= 7) {
            $days[] = $n;
        }
    }

    return array_values(array_unique($days));
}

/** Stores [6, 7] back as '6,7'. */
function formatRestDays(array $days): string
{
    $clean = [];
    foreach ($days as $d) {
        $n = (int) $d;
        if ($n >= 1 && $n <= 7) {
            $clean[] = $n;
        }
    }
    sort($clean);

    return implode(',', array_unique($clean));
}

/** Is this Y-m-d date a rest day under the given pattern? */
function isRestDay(string $date, array $restDays): bool
{
    return in_array((int) date('N', strtotime($date)), $restDays, true);
}

/**
 * Classify every day in a range.
 *
 * $datesWithEntry is a set keyed by Y-m-d -- whatever the caller already fetched, so
 * this does not re-query. $upTo stops the walk at today: a working day that has not
 * happened yet is not an absence, and counting it as one was the bug that made the
 * month's absence figure climb every time you loaded the page early in the month.
 *
 * Returns ['rest' => n, 'worked_rest' => n, 'present' => n, 'absent' => n, 'days' => [date => state]].
 */
function classifyAttendance(string $start, string $end, array $datesWithEntry, array $restDays, ?string $upTo = null): array
{
    $upTo = $upTo ?: date('Y-m-d');
    $out = ['rest' => 0, 'worked_rest' => 0, 'present' => 0, 'absent' => 0, 'days' => []];

    $cursor = strtotime($start);
    $endTs = strtotime($end);
    $upToTs = strtotime($upTo);

    while ($cursor <= $endTs) {
        $date = date('Y-m-d', $cursor);
        $hasEntry = isset($datesWithEntry[$date]);
        $rest = isRestDay($date, $restDays);

        if ($rest) {
            $state = $hasEntry ? 'worked_rest' : 'rest';
        } elseif ($hasEntry) {
            $state = 'present';
        } elseif ($cursor <= $upToTs) {
            $state = 'absent';
        } else {
            $state = 'upcoming';
        }

        $out['days'][$date] = $state;
        if ($state !== 'upcoming') {
            $out[$state]++;
        }

        $cursor = strtotime('+1 day', $cursor);
    }

    return $out;
}

/** Human label for a state, used by the calendar and the day detail. */
function attendanceLabel(string $state): string
{
    return [
        'rest' => 'Rest day',
        'worked_rest' => 'Worked (rest day)',
        'present' => 'Present',
        'absent' => 'Absent',
        'upcoming' => '',
    ][$state] ?? '';
}
