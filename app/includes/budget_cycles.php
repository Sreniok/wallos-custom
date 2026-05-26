<?php

function wallosClampDayForMonth(int $year, int $month, int $day): int
{
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    return max(1, min($day, $daysInMonth));
}

function wallosMonthlyFixedPayrollDate(int $year, int $month, int $day): DateTimeImmutable
{
    $day = wallosClampDayForMonth($year, $month, $day);
    return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
}

function wallosMonthlyWeekdayPayrollDate(int $year, int $month, int $weekday, string $ordinal): DateTimeImmutable
{
    $weekday = max(1, min(7, $weekday));
    $dates = [];
    $date = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
    $end = $date->modify('last day of this month');

    while ($date <= $end) {
        if ((int) $date->format('N') === $weekday) {
            $dates[] = $date;
        }
        $date = $date->modify('+1 day');
    }

    $indexMap = [
        'first' => 0,
        'second' => 1,
        'third' => 2,
        'fourth' => 3,
        'last' => count($dates) - 1,
        'second_last' => count($dates) - 2,
    ];

    $index = $indexMap[$ordinal] ?? $indexMap['last'];
    $index = max(0, min($index, count($dates) - 1));

    return $dates[$index];
}

function wallosBiweeklyPayrollDateOnOrBefore(DateTimeImmutable $date, ?string $anchorDate): DateTimeImmutable
{
    $anchor = $anchorDate ? new DateTimeImmutable($anchorDate) : new DateTimeImmutable('today');
    $anchor = $anchor->setTime(0, 0, 0);
    $date = $date->setTime(0, 0, 0);
    $intervalDays = 14;
    $diffDays = (int) $anchor->diff($date)->format('%r%a');
    $steps = (int) floor($diffDays / $intervalDays);

    return $anchor->modify(($steps * $intervalDays) . ' days');
}

function wallosSemiMonthlyPayrollDates(int $year, int $month, int $day1, int $day2): array
{
    $first = wallosMonthlyFixedPayrollDate($year, $month, min($day1, $day2));
    $second = wallosMonthlyFixedPayrollDate($year, $month, max($day1, $day2));
    return [$first, $second];
}

function wallosGetPayrollDateForMonth(array $userData, int $year, int $month): DateTimeImmutable
{
    $type = $userData['payroll_schedule_type'] ?? 'monthly_weekday_rule';

    if ($type === 'monthly_fixed_day') {
        return wallosMonthlyFixedPayrollDate($year, $month, (int) ($userData['payroll_fixed_day'] ?? 1));
    }

    return wallosMonthlyWeekdayPayrollDate(
        $year,
        $month,
        (int) ($userData['payroll_weekday'] ?? 4),
        $userData['payroll_ordinal'] ?? 'second_last'
    );
}

function wallosGetPayrollPeriod(array $userData, ?DateTimeInterface $date = null): array
{
    $date = $date instanceof DateTimeInterface
        ? new DateTimeImmutable($date->format('Y-m-d'))
        : new DateTimeImmutable('today');
    $date = $date->setTime(0, 0, 0);
    $type = $userData['payroll_schedule_type'] ?? 'monthly_weekday_rule';

    if ($type === 'biweekly') {
        $start = wallosBiweeklyPayrollDateOnOrBefore($date, $userData['payroll_anchor_date'] ?? null);
        $end = $start->modify('+13 days');
        return ['start' => $start, 'end' => $end];
    }

    if ($type === 'semi_monthly') {
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');
        [$first, $second] = wallosSemiMonthlyPayrollDates(
            $year,
            $month,
            (int) ($userData['payroll_fixed_day'] ?? 1),
            (int) ($userData['payroll_fixed_day_2'] ?? 15)
        );

        if ($date < $first) {
            $previousMonth = $date->modify('first day of previous month');
            [, $start] = wallosSemiMonthlyPayrollDates(
                (int) $previousMonth->format('Y'),
                (int) $previousMonth->format('n'),
                (int) ($userData['payroll_fixed_day'] ?? 1),
                (int) ($userData['payroll_fixed_day_2'] ?? 15)
            );
            $end = $first->modify('-1 day');
        } elseif ($date < $second) {
            $start = $first;
            $end = $second->modify('-1 day');
        } else {
            $start = $second;
            $nextMonth = $date->modify('first day of next month');
            [$nextStart] = wallosSemiMonthlyPayrollDates(
                (int) $nextMonth->format('Y'),
                (int) $nextMonth->format('n'),
                (int) ($userData['payroll_fixed_day'] ?? 1),
                (int) ($userData['payroll_fixed_day_2'] ?? 15)
            );
            $end = $nextStart->modify('-1 day');
        }

        return ['start' => $start, 'end' => $end];
    }

    $year = (int) $date->format('Y');
    $month = (int) $date->format('n');
    $payrollDate = wallosGetPayrollDateForMonth($userData, $year, $month);

    if ($payrollDate > $date) {
        $previousMonth = $date->modify('first day of previous month');
        $start = wallosGetPayrollDateForMonth($userData, (int) $previousMonth->format('Y'), (int) $previousMonth->format('n'));
        $end = $payrollDate->modify('-1 day');
    } else {
        $nextMonth = $date->modify('first day of next month');
        $start = $payrollDate;
        $end = wallosGetPayrollDateForMonth($userData, (int) $nextMonth->format('Y'), (int) $nextMonth->format('n'))->modify('-1 day');
    }

    return ['start' => $start, 'end' => $end];
}

function wallosUsesPayrollBudgetCycle(array $userData): bool
{
    return ($userData['budget_cycle'] ?? 'calendar_month') === 'payroll';
}

?>
