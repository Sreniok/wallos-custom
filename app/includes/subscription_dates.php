<?php

function normalizeSubscriptionDate($date)
{
    if (empty($date)) {
        return null;
    }

    try {
        return new DateTimeImmutable($date);
    } catch (Exception $e) {
        return null;
    }
}

function getSubscriptionInterval($cycle, $frequency)
{
    $frequency = max(1, (int) $frequency);

    switch ((int) $cycle) {
        case 1:
            return new DateInterval("P{$frequency}D");
        case 2:
            return new DateInterval("P{$frequency}W");
        case 3:
            return new DateInterval("P{$frequency}M");
        case 4:
            return new DateInterval("P{$frequency}Y");
        default:
            return new DateInterval("P{$frequency}M");
    }
}

function hasSeparateFirstPayment($subscription)
{
    $startDate = normalizeSubscriptionDate($subscription['start_date'] ?? null);
    $nextPaymentDate = normalizeSubscriptionDate($subscription['next_payment'] ?? null);

    return $startDate !== null && $nextPaymentDate !== null && $startDate < $nextPaymentDate;
}

function getUpcomingSubscriptionPaymentDate($subscription, $currentDate = null)
{
    $currentDate = $currentDate instanceof DateTimeInterface
        ? new DateTimeImmutable($currentDate->format('Y-m-d'))
        : new DateTimeImmutable('today');

    $currentDate = $currentDate->setTime(0, 0, 0);
    $startDate = normalizeSubscriptionDate($subscription['start_date'] ?? null);
    $nextPaymentDate = normalizeSubscriptionDate($subscription['next_payment'] ?? null);

    // Only treat start_date as the upcoming payment when it's strictly in the future
    // (e.g. a trial that ends on a known date). When start_date == today it means the
    // subscription started today (already paid implicitly), so the recurring
    // next_payment is the actual next due date.
    if ($startDate !== null && $nextPaymentDate !== null && $startDate < $nextPaymentDate && $startDate > $currentDate) {
        return $startDate->format('Y-m-d');
    }

    if ($nextPaymentDate !== null) {
        return $nextPaymentDate->format('Y-m-d');
    }

    if ($startDate !== null) {
        return $startDate->format('Y-m-d');
    }

    return null;
}

function getSubscriptionOccurrencesInRange($subscription, $rangeStart, $rangeEnd)
{
    $rangeStart = new DateTimeImmutable($rangeStart->format('Y-m-d'));
    $rangeEnd = new DateTimeImmutable($rangeEnd->format('Y-m-d'));

    if ($rangeEnd < $rangeStart) {
        return [];
    }

    $occurrences = [];
    $startDate = normalizeSubscriptionDate($subscription['start_date'] ?? null);
    $nextPaymentDate = normalizeSubscriptionDate($subscription['next_payment'] ?? null);
    $lastPaymentDate = normalizeSubscriptionDate($subscription['last_payment_date'] ?? null);

    if (
        $startDate !== null &&
        ($lastPaymentDate === null || $startDate <= $lastPaymentDate) &&
        $startDate >= $rangeStart &&
        $startDate <= $rangeEnd &&
        ($nextPaymentDate === null || $startDate < $nextPaymentDate)
    ) {
        $occurrences[] = $startDate->format('Y-m-d');
    }

    $seriesStartDate = $nextPaymentDate ?? $startDate;
    if ($seriesStartDate === null) {
        return $occurrences;
    }

    if ($lastPaymentDate !== null && $seriesStartDate > $lastPaymentDate) {
        return $occurrences;
    }

    $interval = getSubscriptionInterval($subscription['cycle'] ?? 3, $subscription['frequency'] ?? 1);
    $occurrenceDate = $seriesStartDate;

    while ($occurrenceDate < $rangeStart) {
        $occurrenceDate = $occurrenceDate->add($interval);
    }

    while ($occurrenceDate <= $rangeEnd) {
        if ($lastPaymentDate !== null && $occurrenceDate > $lastPaymentDate) {
            break;
        }

        $occurrences[] = $occurrenceDate->format('Y-m-d');
        $occurrenceDate = $occurrenceDate->add($interval);
    }

    return array_values(array_unique($occurrences));
}

function shiftToNextWorkingDay(DateTimeImmutable $date): DateTimeImmutable
{
    // N format: 1=Monday … 6=Saturday, 7=Sunday
    while ((int)$date->format('N') >= 6) {
        $date = $date->modify('+1 day');
    }
    return $date;
}

function getAdjustedPaymentDate($subscription, $currentDate = null, $globalAdjust = false): ?string
{
    $date = getUpcomingSubscriptionPaymentDate($subscription, $currentDate);
    if ($date === null || !$globalAdjust || empty($subscription['adjust_to_working_day'])) {
        return $date;
    }
    return shiftToNextWorkingDay(new DateTimeImmutable($date))->format('Y-m-d');
}

function getPreviousSubscriptionPaymentDate($subscription, $currentDate = null): ?string
{
    $upcomingPayment = getUpcomingSubscriptionPaymentDate($subscription, $currentDate);
    if ($upcomingPayment === null) {
        return null;
    }

    $upcomingPaymentDate = new DateTimeImmutable($upcomingPayment);
    $startDate = normalizeSubscriptionDate($subscription['start_date'] ?? null);
    $interval = getSubscriptionInterval($subscription['cycle'] ?? 3, $subscription['frequency'] ?? 1);
    $previousPaymentDate = $upcomingPaymentDate->sub($interval);

    if ($startDate !== null && $previousPaymentDate < $startDate) {
        return $startDate->format('Y-m-d');
    }

    return $previousPaymentDate->format('Y-m-d');
}

function getSubscriptionCycleProgress($subscription, $currentDate = null): int
{
    $upcomingPayment = getUpcomingSubscriptionPaymentDate($subscription, $currentDate);
    $previousPayment = getPreviousSubscriptionPaymentDate($subscription, $currentDate);
    if ($upcomingPayment === null || $previousPayment === null) {
        return 0;
    }

    $upcomingPaymentDate = new DateTimeImmutable($upcomingPayment);
    $previousPaymentDate = new DateTimeImmutable($previousPayment);
    $currentDate = $currentDate instanceof DateTimeInterface
        ? new DateTimeImmutable($currentDate->format('Y-m-d'))
        : new DateTimeImmutable('today');

    $totalCycleDays = $previousPaymentDate->diff($upcomingPaymentDate)->days;
    if ($totalCycleDays <= 0) {
        return 0;
    }

    $elapsedDays = $previousPaymentDate->diff($currentDate)->days;
    if ($currentDate < $previousPaymentDate) {
        $elapsedDays = 0;
    }

    return (int) floor(min(100, max(0, ($elapsedDays / $totalCycleDays) * 100)));
}

function getPassedAutoRenewalOccurrencesInRange($subscription, $rangeStart, $rangeEnd)
{
    $rangeStart = new DateTimeImmutable($rangeStart->format('Y-m-d'));
    $rangeEnd = new DateTimeImmutable($rangeEnd->format('Y-m-d'));

    if ($rangeEnd < $rangeStart || empty($subscription['auto_renew'])) {
        return [];
    }

    $startDate = normalizeSubscriptionDate($subscription['start_date'] ?? null);
    $nextPaymentDate = normalizeSubscriptionDate($subscription['next_payment'] ?? null);

    if ($nextPaymentDate === null) {
        return [];
    }

    $occurrences = [];

    if (
        $startDate !== null &&
        $startDate >= $rangeStart &&
        $startDate <= $rangeEnd &&
        $startDate < $nextPaymentDate
    ) {
        $occurrences[] = $startDate->format('Y-m-d');
    }

    $interval = getSubscriptionInterval($subscription['cycle'] ?? 3, $subscription['frequency'] ?? 1);
    $occurrenceDate = $nextPaymentDate;

    while ($occurrenceDate > $rangeEnd) {
        $occurrenceDate = $occurrenceDate->sub($interval);
    }

    while ($occurrenceDate >= $rangeStart) {
        if ($startDate === null || $occurrenceDate >= $startDate) {
            $occurrences[] = $occurrenceDate->format('Y-m-d');
        }

        $occurrenceDate = $occurrenceDate->sub($interval);
    }

    return array_values(array_unique($occurrences));
}
