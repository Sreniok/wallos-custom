<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/api_response.php';
require_once '../../includes/budget_cycles.php';
require_once '../../includes/formatting_helpers.php';

$vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
$offset = (int) ($_POST['offset'] ?? 0);
if ($vehicleId <= 0) {
    apiError(translate('invalid_input', $i18n));
}

$vehicleStmt = $db->prepare("SELECT * FROM fuel_vehicles WHERE id = :vehicleId AND user_id = :userId");
$vehicleStmt->bindValue(':vehicleId', $vehicleId, SQLITE3_INTEGER);
$vehicleStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$vehicleResult = $vehicleStmt->execute();
$vehicle = $vehicleResult ? $vehicleResult->fetchArray(SQLITE3_ASSOC) : false;

if (!$vehicle) {
    apiError(translate('invalid_input', $i18n));
}

$userStmt = $db->prepare("SELECT budget_cycle, payroll_schedule_type, payroll_fixed_day, payroll_fixed_day_2, payroll_weekday, payroll_ordinal, payroll_anchor_date FROM user WHERE id = :userId");
$userStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$userResult = $userStmt->execute();
$user = $userResult ? $userResult->fetchArray(SQLITE3_ASSOC) : [];
$fuelUnitSystem = $db->querySingle("SELECT fuel_unit_system FROM settings WHERE user_id = " . (int) $userId);
$summaryUnit = $fuelUnitSystem === 'us' ? 'gal' : 'L';

if (wallosUsesPayrollBudgetCycle($user)) {
    $referenceDate = new DateTimeImmutable('today');
    if ($offset !== 0) {
        $periodStep = $offset < 0 ? -1 : 1;
        for ($i = 0; $i < abs($offset); $i++) {
            $currentPeriod = wallosGetPayrollPeriod($user, $referenceDate);
            $referenceDate = $periodStep < 0
                ? $currentPeriod['start']->modify('-1 day')
                : $currentPeriod['end']->modify('+1 day');
        }
    }
    $period = wallosGetPayrollPeriod($user, $referenceDate);
    $periodLabel = translate('payroll_month', $i18n);
} else {
    $referenceDate = (new DateTimeImmutable('first day of this month'))->modify($offset . ' months');
    $start = $referenceDate;
    $end = $referenceDate->modify('last day of this month');
    $period = ['start' => $start, 'end' => $end];
    $periodLabel = translate('calendar_month', $i18n);
}

$mainCurrencyId = $_SESSION['main_currency'] ?? 0;
$currencyStmt = $db->prepare("SELECT id, code, symbol FROM currencies WHERE user_id = :userId");
$currencyStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$currencyResult = $currencyStmt->execute();
$currencies = [];
while ($currency = $currencyResult->fetchArray(SQLITE3_ASSOC)) {
    $currencies[(int) $currency['id']] = $currency;
}

$mainCurrencyCode = $currencies[(int) $mainCurrencyId]['code'] ?? '';
$expenseStmt = $db->prepare("SELECT e.*, c.code AS currency_code, c.symbol AS currency_symbol
    FROM expenses e
    JOIN currencies c ON c.id = e.currency_id
    WHERE e.user_id = :userId
      AND e.vehicle_id = :vehicleId
      AND e.category = 'fuel'
      AND e.expense_date BETWEEN :startDate AND :endDate
    ORDER BY e.expense_date DESC, e.id DESC");
$expenseStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$expenseStmt->bindValue(':vehicleId', $vehicleId, SQLITE3_INTEGER);
$expenseStmt->bindValue(':startDate', $period['start']->format('Y-m-d'), SQLITE3_TEXT);
$expenseStmt->bindValue(':endDate', $period['end']->format('Y-m-d'), SQLITE3_TEXT);
$expenseResult = $expenseStmt->execute();

$rows = [];
$total = 0.0;
$totalQuantity = 0.0;
while ($expense = $expenseResult->fetchArray(SQLITE3_ASSOC)) {
    $amount = (float) $expense['amount'];
    if ($mainCurrencyId && (int) $expense['currency_id'] !== (int) $mainCurrencyId) {
        $amount = getPriceConverted($amount, (int) $expense['currency_id'], $db, $userId);
    }

    $quantity = isset($expense['quantity']) ? (float) $expense['quantity'] : 0.0;
    $total += $amount;
    $totalQuantity += $quantity;

    $rows[] = [
        'id' => (int) $expense['id'],
        'date' => $expense['expense_date'],
        'amount' => formatPrice((float) $expense['amount'], $expense['currency_code'], array_values($currencies)),
        'quantity' => $quantity > 0 ? rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.') : '',
        'unit' => $expense['unit'] === 'gal_us' ? 'gal' : 'L',
        'unit_price' => isset($expense['unit_price'])
            ? rtrim(rtrim(number_format((float) $expense['unit_price'], 3, '.', ''), '0'), '.')
            : '',
        'notes' => $expense['notes'] ?? '',
    ];
}

apiSuccess([
    'vehicle' => [
        'id' => (int) $vehicle['id'],
        'name' => $vehicle['name'],
    ],
    'period' => [
        'label' => $periodLabel,
        'start' => $period['start']->format('Y-m-d'),
        'end' => $period['end']->format('Y-m-d'),
    ],
    'summary' => [
        'total' => $mainCurrencyCode ? formatPrice($total, $mainCurrencyCode, array_values($currencies)) : number_format($total, 2),
        'fills' => count($rows),
        'quantity' => rtrim(rtrim(number_format($totalQuantity, 3, '.', ''), '0'), '.'),
        'unit' => $summaryUnit,
    ],
    'rows' => $rows,
]);

?>
