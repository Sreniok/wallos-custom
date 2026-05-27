<?php
// Loads per-vehicle fuel data for the current period and year.
// Inputs:  $db, $userId, $userData, $i18n, $currencies
// Outputs: $fuelVehicles, $fuelPeriod, $fuelPeriodLabel
// $mainCurrencyId is read if defined, otherwise derived from $userData['main_currency'].

if (!isset($mainCurrencyId)) {
    $mainCurrencyId = (int) ($userData['main_currency'] ?? 0);
}

$fuelVehicles = [];
$fuelPeriod = null;
$fuelVehicleTableExists = $db->querySingle("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'fuel_vehicles'");
$expensesVehicleColumnExists = false;
$expenseColumnsResult = $db->query("PRAGMA table_info(expenses)");
while ($expenseColumn = $expenseColumnsResult->fetchArray(SQLITE3_ASSOC)) {
    if (($expenseColumn['name'] ?? '') === 'vehicle_id') {
        $expensesVehicleColumnExists = true;
        break;
    }
}

if ($fuelVehicleTableExists && $expensesVehicleColumnExists) {
    if (wallosUsesPayrollBudgetCycle($userData)) {
        $fuelPeriod = wallosGetPayrollPeriod($userData);
        $fuelPeriodLabel = translate('payroll_month', $i18n);
    } else {
        $fuelPeriod = [
            'start' => new DateTimeImmutable('first day of this month'),
            'end' => new DateTimeImmutable('last day of this month'),
        ];
        $fuelPeriodLabel = translate('calendar_month', $i18n);
    }

    $vehicleStmt = $db->prepare("SELECT fv.*,
            h.name AS payer_name,
            COUNT(e.id) AS fill_count,
            COALESCE(SUM(e.amount), 0) AS period_total,
            COALESCE(SUM(e.quantity), 0) AS period_quantity,
            MAX(e.expense_date) AS last_fill_date
        FROM fuel_vehicles fv
        LEFT JOIN household h
            ON h.id = fv.payer_user_id
            AND h.user_id = fv.user_id
        LEFT JOIN expenses e
            ON e.vehicle_id = fv.id
            AND e.user_id = fv.user_id
            AND e.category = 'fuel'
            AND e.expense_date BETWEEN :startDate AND :endDate
        WHERE fv.user_id = :userId
        GROUP BY fv.id
        ORDER BY fv.name ASC");
    $vehicleStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $vehicleStmt->bindValue(':startDate', $fuelPeriod['start']->format('Y-m-d'), SQLITE3_TEXT);
    $vehicleStmt->bindValue(':endDate', $fuelPeriod['end']->format('Y-m-d'), SQLITE3_TEXT);
    $vehicleResult = $vehicleStmt->execute();
    while ($vehicle = $vehicleResult->fetchArray(SQLITE3_ASSOC)) {
        $totalStmt = $db->prepare("SELECT amount, currency_id FROM expenses
            WHERE user_id = :userId
                AND vehicle_id = :vehicleId
                AND category = 'fuel'
                AND expense_date BETWEEN :startDate AND :endDate");
        $totalStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $totalStmt->bindValue(':vehicleId', (int) $vehicle['id'], SQLITE3_INTEGER);
        $totalStmt->bindValue(':startDate', $fuelPeriod['start']->format('Y-m-d'), SQLITE3_TEXT);
        $totalStmt->bindValue(':endDate', $fuelPeriod['end']->format('Y-m-d'), SQLITE3_TEXT);
        $totalResult = $totalStmt->execute();
        $convertedTotal = 0.0;
        while ($expense = $totalResult->fetchArray(SQLITE3_ASSOC)) {
            $amount = (float) $expense['amount'];
            if ((int) $expense['currency_id'] !== (int) $mainCurrencyId) {
                $amount = getPriceConverted($amount, (int) $expense['currency_id'], $db, $userId);
            }
            $convertedTotal += $amount;
        }
        $vehicle['period_total'] = $convertedTotal;

        $yearStart = date('Y') . '-01-01';
        $yearEnd = date('Y') . '-12-31';
        $yearStmt = $db->prepare("SELECT amount, currency_id FROM expenses
            WHERE user_id = :userId
                AND vehicle_id = :vehicleId
                AND category = 'fuel'
                AND expense_date BETWEEN :startDate AND :endDate");
        $yearStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $yearStmt->bindValue(':vehicleId', (int) $vehicle['id'], SQLITE3_INTEGER);
        $yearStmt->bindValue(':startDate', $yearStart, SQLITE3_TEXT);
        $yearStmt->bindValue(':endDate', $yearEnd, SQLITE3_TEXT);
        $yearResult = $yearStmt->execute();
        $convertedYear = 0.0;
        while ($yearExpense = $yearResult->fetchArray(SQLITE3_ASSOC)) {
            $amount = (float) $yearExpense['amount'];
            if ((int) $yearExpense['currency_id'] !== (int) $mainCurrencyId) {
                $amount = getPriceConverted($amount, (int) $yearExpense['currency_id'], $db, $userId);
            }
            $convertedYear += $amount;
        }
        $vehicle['year_total'] = $convertedYear;

        $fuelVehicles[] = $vehicle;
    }
}
