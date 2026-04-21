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

    if ($startDate !== null && $nextPaymentDate !== null && $startDate < $nextPaymentDate && $startDate >= $currentDate) {
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
