<?php

require_once __DIR__ . '/inputvalidation.php';
require_once __DIR__ . '/request_helpers.php';

function normalizeOptionalDateInput($value): ?string
{
    $value = trim((string) ($value ?? ''));
    return $value === '' ? null : $value;
}

function normalizeSubscriptionForm(array $input): array
{
    $nextPayment = $input['next_payment'] ?? '';
    $startDate = normalizeOptionalDateInput($input['start_date'] ?? null) ?? $nextPayment;
    $lastPaymentDate = normalizeOptionalDateInput($input['last_payment_date'] ?? null);
    $cancellationDate = normalizeOptionalDateInput($input['cancellation_date'] ?? null);
    $regularPriceInput = trim((string) ($input['regular_price'] ?? ''));
    $inactive = isset($input['inactive']);
    $replacementSubscriptionId = $input['replacement_subscription_id'] ?? null;

    if ($replacementSubscriptionId == 0 || !$inactive) {
        $replacementSubscriptionId = null;
    }

    return [
        'is_edit' => isset($input['id']) && $input['id'] !== '',
        'id' => $input['id'] ?? null,
        'name' => validate($input['name'] ?? ''),
        'price' => $input['price'] ?? 0,
        'regular_price' => $regularPriceInput !== '' ? (float) $regularPriceInput : null,
        'currency_id' => $input['currency_id'] ?? 0,
        'frequency' => $input['frequency'] ?? 1,
        'cycle' => $input['cycle'] ?? 3,
        'next_payment' => $nextPayment,
        'auto_renew' => isset($input['auto_renew']),
        'adjust_to_working_day' => isset($input['adjust_to_working_day']) ? 1 : 0,
        'start_date' => $startDate,
        'payment_method_id' => $input['payment_method_id'] ?? 0,
        'payer_user_id' => $input['payer_user_id'] ?? 0,
        'category_id' => $input['category_id'] ?? 1,
        'notes' => validate($input['notes'] ?? ''),
        'url' => validate($input['url'] ?? ''),
        'logo_url' => validate($input['logo-url'] ?? ''),
        'notify' => isset($input['notifications']),
        'notify_days_before' => $input['notify_days_before'] ?? 0,
        'inactive' => $inactive,
        'cancellation_date' => $cancellationDate,
        'last_payment_date' => $lastPaymentDate,
        'replacement_subscription_id' => $replacementSubscriptionId,
    ];
}

function validateSubscriptionForm(array $subscription, array $i18n): ?string
{
    if ($subscription['regular_price'] !== null && $subscription['regular_price'] <= 0) {
        return translate('regular_price_must_be_positive', $i18n);
    }

    if (
        $subscription['last_payment_date'] !== null &&
        strtotime($subscription['last_payment_date']) < strtotime((string) $subscription['next_payment'])
    ) {
        return translate('last_payment_date_after_next_payment', $i18n);
    }

    return null;
}

function bindSubscriptionForm(SQLite3Stmt $stmt, array $subscription, int $userId, string $logo): void
{
    $stmt->bindValue(':name', $subscription['name'], SQLITE3_TEXT);
    if ($logo !== '') {
        $stmt->bindValue(':logo', $logo, SQLITE3_TEXT);
    }
    $stmt->bindValue(':price', $subscription['price'], SQLITE3_FLOAT);
    $stmt->bindValue(':regularPrice', $subscription['regular_price'], $subscription['regular_price'] === null ? SQLITE3_NULL : SQLITE3_FLOAT);
    $stmt->bindValue(':currencyId', $subscription['currency_id'], SQLITE3_INTEGER);
    $stmt->bindValue(':nextPayment', $subscription['next_payment'], SQLITE3_TEXT);
    bindNullableDate($stmt, ':lastPaymentDate', $subscription['last_payment_date']);
    bindBool($stmt, ':autoRenew', $subscription['auto_renew']);
    $stmt->bindValue(':startDate', $subscription['start_date'], SQLITE3_TEXT);
    $stmt->bindValue(':cycle', $subscription['cycle'], SQLITE3_INTEGER);
    $stmt->bindValue(':frequency', $subscription['frequency'], SQLITE3_INTEGER);
    $stmt->bindValue(':notes', $subscription['notes'], SQLITE3_TEXT);
    $stmt->bindValue(':paymentMethodId', $subscription['payment_method_id'], SQLITE3_INTEGER);
    $stmt->bindValue(':payerUserId', $subscription['payer_user_id'], SQLITE3_INTEGER);
    $stmt->bindValue(':categoryId', $subscription['category_id'], SQLITE3_INTEGER);
    bindBool($stmt, ':notify', $subscription['notify']);
    bindBool($stmt, ':inactive', $subscription['inactive']);
    $stmt->bindValue(':url', $subscription['url'], SQLITE3_TEXT);
    $stmt->bindValue(':notifyDaysBefore', $subscription['notify_days_before'], SQLITE3_INTEGER);
    bindNullableDate($stmt, ':cancellationDate', $subscription['cancellation_date']);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(
        ':replacement_subscription_id',
        $subscription['replacement_subscription_id'],
        $subscription['replacement_subscription_id'] === null ? SQLITE3_NULL : SQLITE3_INTEGER
    );
    $stmt->bindValue(':adjustToWorkingDay', $subscription['adjust_to_working_day'], SQLITE3_INTEGER);

    if ($subscription['is_edit']) {
        $stmt->bindValue(':id', $subscription['id'], SQLITE3_INTEGER);
    }
}
