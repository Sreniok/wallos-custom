<?php
/**
 * Per-vehicle fuel summary loader.
 *
 * Aggregates each vehicle's fill count, quantity and converted spend for the
 * active dashboard period and the calendar year. Currency conversion uses a
 * single pre-fetched rate map so the cost is O(expenses) instead of
 * O(vehicles × currencies) DB calls.
 */

require_once __DIR__ . '/formatting_helpers.php';
require_once __DIR__ . '/budget_cycles.php';

if (!function_exists('wallosLoadFuelVehicleSummary')) {

    function wallosLoadFuelVehicleSummary(
        SQLite3 $db,
        int $userId,
        array $userData,
        array $i18n,
        ?int $mainCurrencyId = null
    ): array {
        $empty = [
            'vehicles' => [],
            'period' => null,
            'period_label' => '',
        ];

        if (!wallosFuelTablesExist($db)) {
            return $empty;
        }

        if ($mainCurrencyId === null) {
            $mainCurrencyId = (int) ($userData['main_currency'] ?? 0);
        }

        if (wallosUsesPayrollBudgetCycle($userData)) {
            $period = wallosGetPayrollPeriod($userData);
            $periodLabel = translate('payroll_month', $i18n);
        } else {
            $period = [
                'start' => new DateTimeImmutable('first day of this month'),
                'end' => new DateTimeImmutable('last day of this month'),
            ];
            $periodLabel = translate('calendar_month', $i18n);
        }

        $vehicleStmt = $db->prepare("SELECT fv.*,
                h.name AS payer_name,
                COUNT(e.id) AS fill_count,
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
        $vehicleStmt->bindValue(':startDate', $period['start']->format('Y-m-d'), SQLITE3_TEXT);
        $vehicleStmt->bindValue(':endDate', $period['end']->format('Y-m-d'), SQLITE3_TEXT);
        $vehicleResult = $vehicleStmt->execute();

        $vehiclesById = [];
        while ($row = $vehicleResult->fetchArray(SQLITE3_ASSOC)) {
            $row['period_total'] = 0.0;
            $row['year_total'] = 0.0;
            $vehiclesById[(int) $row['id']] = $row;
        }

        if (empty($vehiclesById)) {
            return ['vehicles' => [], 'period' => $period, 'period_label' => $periodLabel];
        }

        $rates = wallosLoadCurrencyRates($db, $userId);
        $yearStart = date('Y') . '-01-01';
        $yearEnd = date('Y') . '-12-31';

        // Single year-wide scan; accumulate period subtotals on the fly.
        $expenseStmt = $db->prepare("SELECT vehicle_id, amount, currency_id, expense_date
            FROM expenses
            WHERE user_id = :userId
                AND category = 'fuel'
                AND vehicle_id IS NOT NULL
                AND expense_date BETWEEN :yearStart AND :yearEnd");
        $expenseStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $expenseStmt->bindValue(':yearStart', $yearStart, SQLITE3_TEXT);
        $expenseStmt->bindValue(':yearEnd', $yearEnd, SQLITE3_TEXT);
        $expenseResult = $expenseStmt->execute();

        $periodStartStr = $period['start']->format('Y-m-d');
        $periodEndStr = $period['end']->format('Y-m-d');

        while ($expense = $expenseResult->fetchArray(SQLITE3_ASSOC)) {
            $vehicleId = (int) $expense['vehicle_id'];
            if (!isset($vehiclesById[$vehicleId])) {
                continue;
            }

            $amount = (float) $expense['amount'];
            $currencyId = (int) $expense['currency_id'];
            if ($currencyId !== $mainCurrencyId && isset($rates[$currencyId]) && $rates[$currencyId] > 0) {
                $amount = $amount / $rates[$currencyId];
            }

            $vehiclesById[$vehicleId]['year_total'] += $amount;
            $date = $expense['expense_date'];
            if ($date >= $periodStartStr && $date <= $periodEndStr) {
                $vehiclesById[$vehicleId]['period_total'] += $amount;
            }
        }

        return [
            'vehicles' => array_values($vehiclesById),
            'period' => $period,
            'period_label' => $periodLabel,
        ];
    }

    function wallosFuelTablesExist(SQLite3 $db): bool
    {
        $tableExists = $db->querySingle("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'fuel_vehicles'");
        if (!$tableExists) {
            return false;
        }
        $cols = $db->query("PRAGMA table_info(expenses)");
        while ($col = $cols->fetchArray(SQLITE3_ASSOC)) {
            if (($col['name'] ?? '') === 'vehicle_id') {
                return true;
            }
        }
        return false;
    }

    function wallosLoadCurrencyRates(SQLite3 $db, int $userId): array
    {
        $stmt = $db->prepare("SELECT id, rate FROM currencies WHERE user_id = :userId");
        $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $rates = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rates[(int) $row['id']] = (float) $row['rate'];
        }
        return $rates;
    }
}
