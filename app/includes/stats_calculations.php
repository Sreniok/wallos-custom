<?php

require_once __DIR__ . '/formatting_helpers.php';
require_once __DIR__ . '/subscription_dates.php';
require_once __DIR__ . '/budget_cycles.php';

// Get categories
$categories = array();
$query = "SELECT * FROM categories WHERE user_id = :userId ORDER BY 'order' ASC";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
  $categoryId = $row['id'];
  $categories[$categoryId] = $row;
  $categories[$categoryId]['count'] = 0;
  $categoryCost[$categoryId]['cost'] = 0;
  $categoryCost[$categoryId]['name'] = $row['name'];
}

// Get payment methods
$paymentMethods = array();
$query = "SELECT * FROM payment_methods WHERE user_id = :userId AND enabled = 1";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
  $paymentMethodId = $row['id'];
  $paymentMethods[$paymentMethodId] = $row;
  $paymentMethods[$paymentMethodId]['count'] = 0;
  $paymentMethodsCount[$paymentMethodId]['count'] = 0;
  $paymentMethodsCount[$paymentMethodId]['name'] = $row['name'];
}

//Get household members
$members = array();
$query = "SELECT * FROM household WHERE user_id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
  $memberId = $row['id'];
  $members[$memberId] = $row;
  $members[$memberId]['count'] = 0;
  $memberCost[$memberId]['cost'] = 0;
  $memberCost[$memberId]['name'] = $row['name'];
}

$activeSubscriptions = 0;
$inactiveSubscriptions = 0;
$missingPayments = 0;
// Calculate total monthly price
$mostExpensiveSubscription = array();
$mostExpensiveSubscription['price'] = 0;
$amountDueThisMonth = 0;
$totalCostPerMonth = 0;
$totalSavingsPerMonth = 0;
$totalCostsInReplacementsPerMonth = 0;

// Amount-due window: tomorrow through end of the active period (payroll
// cycle when configured, otherwise calendar month). Computed once and
// reused inside the subscription loop below.
$amountDueRangeStart = new DateTimeImmutable('tomorrow');
if (wallosUsesPayrollBudgetCycle($userData)) {
    $amountDueRangeEnd = wallosGetPayrollPeriod($userData)['end'];
    $amountDuePeriodLabel = translate('payroll_month', $i18n);
} else {
    $amountDueRangeEnd = new DateTimeImmutable('last day of this month');
    $amountDuePeriodLabel = translate('calendar_month', $i18n);
}

$statsSubtitleParts = [];
$query = "SELECT name, price, logo, frequency, cycle, currency_id, start_date, next_payment, last_payment_date, payer_user_id, category_id, payment_method_id, inactive, replacement_subscription_id, auto_renew FROM subscriptions";
$conditions = [];
$params = [];

if (isset($_GET['member'])) {
    $conditions[] = "payer_user_id = :member";
    $params[':member'] = $_GET['member'];
    $statsSubtitleParts[] = $members[$_GET['member']]['name'];
}

if (isset($_GET['category'])) {
    $conditions[] = "category_id = :category";
    $params[':category'] = $_GET['category'];
    $statsSubtitleParts[] = $categories[$_GET['category']]['name'] == "No category" ? translate("no_category", $i18n) : $categories[$_GET['category']]['name'];
}

if (isset($_GET['payment'])) {
    $conditions[] = "payment_method_id = :payment";
    $params[':payment'] = $_GET['payment'];
    $statsSubtitleParts[] = $paymentMethodsCount[$_GET['payment']]['name'];
}

$conditions[] = "user_id = :userId";
$params[':userId'] = $userId;

if (!empty($conditions)) {
    $query .= " WHERE " . implode(' AND ', $conditions);
}

$stmt = $db->prepare($query);
$statsSubtitle = !empty($statsSubtitleParts) ? '(' . implode(', ', $statsSubtitleParts) . ')' : "";

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, SQLITE3_INTEGER);
}

$result = $stmt->execute();
$usesMultipleCurrencies = false;

if ($result) {
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $subscriptions[] = $row;
    }
    if (isset($subscriptions)) {
        $replacementSubscriptions = array();

        foreach ($subscriptions as $subscription) {
            $name = $subscription['name'];
            $price = $subscription['price'];
            $logo = $subscription['logo'];
            $frequency = $subscription['frequency'];
            $cycle = $subscription['cycle'];
            $currency = $subscription['currency_id'];
            if ($currency != $userData['main_currency']) {
                $usesMultipleCurrencies = true;
            }
            $next_payment = $subscription['next_payment'];
            $payerId = $subscription['payer_user_id'];
            $members[$payerId]['count'] += 1;
            $categoryId = $subscription['category_id'];
            $categories[$categoryId]['count'] += 1;
            $paymentMethodId = $subscription['payment_method_id'];
            $paymentMethods[$paymentMethodId]['count'] += 1;
            $inactive = $subscription['inactive'];
            $autoRenew = $subscription['auto_renew'];
            $replacementSubscriptionId = $subscription['replacement_subscription_id'];
            $originalSubscriptionPrice = getPriceConverted($price, $currency, $db, $userId);
            $price = getPricePerMonth($cycle, $frequency, $originalSubscriptionPrice);

            if ($inactive == 0) {
                $activeSubscriptions++;
                $today = new DateTime('today');
                $nextPaymentDate = DateTime::createFromFormat('Y-m-d', trim($next_payment));
                if ($autoRenew == 0 && $nextPaymentDate && $nextPaymentDate < $today) {
                    $missingPayments++;
                }
                $totalCostPerMonth += $price;
                $memberCost[$payerId]['cost'] += $price;
                $categoryCost[$categoryId]['cost'] += $price;
                $paymentMethodsCount[$paymentMethodId]['count'] += 1;
                if ($price > $mostExpensiveSubscription['price']) {
                    $mostExpensiveSubscription['price'] = $price;
                    $mostExpensiveSubscription['name'] = $name;
                    $mostExpensiveSubscription['logo'] = $logo;
                }

                // Amount due in the active budget period (calendar month or
                // payroll cycle). Counts actual billing occurrences instead
                // of approximating daily/weekly fractions like the previous
                // implementation did.
                if ($amountDueRangeEnd >= $amountDueRangeStart) {
                    $occurrences = getSubscriptionOccurrencesInRange($subscription, $amountDueRangeStart, $amountDueRangeEnd);
                    if (!empty($occurrences)) {
                        $amountDueThisMonth += $originalSubscriptionPrice * count($occurrences);
                    }
                }
            } else {
                $inactiveSubscriptions++;
                $totalSavingsPerMonth += $price;

                // Check if it has a replacement subscription and if it was not already counted
                if ($replacementSubscriptionId && !in_array($replacementSubscriptionId, $replacementSubscriptions)) {
                    $query = "SELECT price, currency_id, cycle, frequency FROM subscriptions WHERE id = :replacementSubscriptionId";
                    $stmt = $db->prepare($query);
                    $stmt->bindValue(':replacementSubscriptionId', $replacementSubscriptionId, SQLITE3_INTEGER);
                    $result = $stmt->execute();
                    $replacementSubscription = $result->fetchArray(SQLITE3_ASSOC);
                    if ($replacementSubscription) {
                        $replacementSubscriptionPrice = getPriceConverted($replacementSubscription['price'], $replacementSubscription['currency_id'], $db, $userId);
                        $replacementSubscriptionPrice = getPricePerMonth($replacementSubscription['cycle'], $replacementSubscription['frequency'], $replacementSubscriptionPrice);
                        $totalCostsInReplacementsPerMonth += $replacementSubscriptionPrice;
                    }
                }

                $replacementSubscriptions[] = $replacementSubscriptionId;
            }

        }

        // Subtract the total cost of replacement subscriptions from the total savings
        $totalSavingsPerMonth -= $totalCostsInReplacementsPerMonth;

        // Calculate yearly price
        $totalCostPerYear = $totalCostPerMonth * 12;

        // Calculate average subscription monthly cost
        if ($activeSubscriptions > 0) {
            $averageSubscriptionCost = $totalCostPerMonth / $activeSubscriptions;
        } else {
            $totalCostPerYear = 0;
            $averageSubscriptionCost = 0;
        }
    } else {
        $totalCostPerYear = 0;
        $averageSubscriptionCost = 0;
    }
}

$showVsBudgetGraph = false;
$vsBudgetDataPoints = [];
$fuelThisMonth = 0;
$fuelThisPeriod = 0;
$fuelThisYear = 0;
$fuelQuantityThisPeriod = 0;
$fuelQuantityThisYear = 0;
$fuelAverageMonthly = 0;
$fuelLastFill = null;
$fuelMonthlyTotals = [];
$fuelMonthlyUnitPrices = [];
$fuelCostDataPoints = [];
$fuelPriceDataPoints = [];
$fuelUnitSystem = $settings['fuelUnitSystem'] ?? 'eu';
$fuelUnitLabel = $fuelUnitSystem === 'us' ? 'gal' : 'L';
$fuelPeriodLabel = wallosUsesPayrollBudgetCycle($userData) ? translate('payroll_month', $i18n) : translate('calendar_month', $i18n);
$fuelDashboardPeriod = wallosUsesPayrollBudgetCycle($userData)
    ? wallosGetPayrollPeriod($userData)
    : [
        'start' => new DateTimeImmutable('first day of this month'),
        'end' => new DateTimeImmutable('last day of this month'),
    ];

// Previous period: payroll cycle prior to the current one, or the previous
// calendar month. Used for at-a-glance "vs. last cycle" comparison.
if (wallosUsesPayrollBudgetCycle($userData)) {
    $fuelDashboardPreviousPeriod = wallosGetPayrollPeriod(
        $userData,
        $fuelDashboardPeriod['start']->modify('-1 day')
    );
    $fuelLastPeriodLabel = translate('last_payroll_month', $i18n);
} else {
    $fuelDashboardPreviousPeriod = [
        'start' => new DateTimeImmutable('first day of previous month'),
        'end' => new DateTimeImmutable('last day of previous month'),
    ];
    $fuelLastPeriodLabel = translate('last_calendar_month', $i18n);
}
$fuelLastPeriod = 0;
$fuelQuantityLastPeriod = 0;

$expenseStmt = $db->prepare("SELECT amount, currency_id, expense_date, quantity, unit, unit_price
    FROM expenses
    WHERE user_id = :userId AND category = 'fuel'
    ORDER BY expense_date ASC");
$expenseStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$expenseResult = $expenseStmt->execute();
$currentMonthKey = date('Y-m');
$currentYear = date('Y');

while ($expense = $expenseResult->fetchArray(SQLITE3_ASSOC)) {
    $expenseDate = $expense['expense_date'];
    $monthKey = substr($expenseDate, 0, 7);
    $yearKey = substr($expenseDate, 0, 4);
    $amount = getPriceConverted($expense['amount'], $expense['currency_id'], $db, $userId);

    $fuelMonthlyTotals[$monthKey] = ($fuelMonthlyTotals[$monthKey] ?? 0) + $amount;

    if (!empty($expense['quantity']) && $expense['quantity'] > 0) {
        $quantity = (float) $expense['quantity'];
        if ($fuelUnitSystem === 'us' && ($expense['unit'] ?? 'l') === 'l') {
            $quantity = $quantity / 3.785411784;
        } elseif ($fuelUnitSystem !== 'us' && ($expense['unit'] ?? 'l') === 'gal_us') {
            $quantity = $quantity * 3.785411784;
        }

        if (!isset($fuelMonthlyUnitPrices[$monthKey])) {
            $fuelMonthlyUnitPrices[$monthKey] = ['amount' => 0, 'quantity' => 0];
        }
        $fuelMonthlyUnitPrices[$monthKey]['amount'] += $amount;
        $fuelMonthlyUnitPrices[$monthKey]['quantity'] += $quantity;
    }

    if ($monthKey === $currentMonthKey) {
        $fuelThisMonth += $amount;
    }

    $normalizedQuantity = 0.0;
    if (!empty($expense['quantity']) && $expense['quantity'] > 0) {
        $normalizedQuantity = (float) $expense['quantity'];
        if ($fuelUnitSystem === 'us' && ($expense['unit'] ?? 'l') === 'l') {
            $normalizedQuantity = $normalizedQuantity / 3.785411784;
        } elseif ($fuelUnitSystem !== 'us' && ($expense['unit'] ?? 'l') === 'gal_us') {
            $normalizedQuantity = $normalizedQuantity * 3.785411784;
        }
    }

    $expenseDateObject = new DateTimeImmutable($expenseDate);
    if ($expenseDateObject >= $fuelDashboardPeriod['start'] && $expenseDateObject <= $fuelDashboardPeriod['end']) {
        $fuelThisPeriod += $amount;
        $fuelQuantityThisPeriod += $normalizedQuantity;
    }
    if ($expenseDateObject >= $fuelDashboardPreviousPeriod['start'] && $expenseDateObject <= $fuelDashboardPreviousPeriod['end']) {
        $fuelLastPeriod += $amount;
        $fuelQuantityLastPeriod += $normalizedQuantity;
    }

    if ($yearKey === $currentYear) {
        $fuelThisYear += $amount;
        $fuelQuantityThisYear += $normalizedQuantity;
    }

    $fuelLastFill = $expenseDate;
}

if (count($fuelMonthlyTotals) > 0) {
    $fuelAverageMonthly = array_sum($fuelMonthlyTotals) / count($fuelMonthlyTotals);
}

foreach ($fuelMonthlyTotals as $month => $amount) {
    $fuelCostDataPoints[] = [
        'label' => date('M Y', strtotime($month . '-01')),
        'y' => round($amount, 2),
    ];
}

foreach ($fuelMonthlyUnitPrices as $month => $unitData) {
    if ($unitData['quantity'] > 0) {
        $fuelPriceDataPoints[] = [
            'label' => date('M Y', strtotime($month . '-01')),
            'y' => round($unitData['amount'] / $unitData['quantity'], 3),
        ];
    }
}

$showFuelCostGraph = count($fuelCostDataPoints) > 1;
$showFuelPriceGraph = count($fuelPriceDataPoints) > 1;

if (isset($userData['budget']) && $userData['budget'] > 0) {
    $budget = $userData['budget'];
    $budgetComparisonCost = $totalCostPerMonth;

    if (wallosUsesPayrollBudgetCycle($userData)) {
        $payrollPeriod = wallosGetPayrollPeriod($userData);
        $budgetComparisonCost = 0;

        foreach ($subscriptions ?? [] as $subscription) {
            if (!empty($subscription['inactive'])) {
                continue;
            }

            $occurrences = getSubscriptionOccurrencesInRange($subscription, $payrollPeriod['start'], $payrollPeriod['end']);
            $budgetComparisonCost += count($occurrences) * getPriceConverted($subscription['price'], $subscription['currency_id'], $db, $userId);
        }

        $budgetExpenseStmt = $db->prepare("SELECT amount, currency_id FROM expenses
            WHERE user_id = :userId AND expense_date >= :startDate AND expense_date <= :endDate");
        $budgetExpenseStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $budgetExpenseStmt->bindValue(':startDate', $payrollPeriod['start']->format('Y-m-d'), SQLITE3_TEXT);
        $budgetExpenseStmt->bindValue(':endDate', $payrollPeriod['end']->format('Y-m-d'), SQLITE3_TEXT);
        $budgetExpenseResult = $budgetExpenseStmt->execute();
        while ($expense = $budgetExpenseResult->fetchArray(SQLITE3_ASSOC)) {
            $budgetComparisonCost += getPriceConverted($expense['amount'], $expense['currency_id'], $db, $userId);
        }
    } else {
        $budgetComparisonCost += $fuelThisMonth;
    }

    $budgetLeft = $budget - $budgetComparisonCost;
    $budgetLeft = $budgetLeft < 0 ? 0 : $budgetLeft;
    $budgetUsed = ($budgetComparisonCost / $budget) * 100;
    $budgetUsed = $budgetUsed > 100 ? 100 : $budgetUsed;
    if ($budgetComparisonCost > $budget) {
        $overBudgetAmount = $budgetComparisonCost - $budget;
    }
    $showVsBudgetGraph = true;
    $vsBudgetDataPoints = [
        [
            "label" => translate('budget_remaining', $i18n),
            "y" => $budgetLeft,
        ],
        [
            "label" => translate('total_cost', $i18n),
            "y" => $budgetComparisonCost,
        ],
    ];
}

$showCantConverErrorMessage = false;
if ($usesMultipleCurrencies) {
    $query = "SELECT api_key FROM fixer WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    if ($result->fetchArray(SQLITE3_ASSOC) === false) {
        $showCantConverErrorMessage = true;
    }
}

$query = "SELECT * FROM total_yearly_cost WHERE user_id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();

$totalMonthlyCostDataPoints = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $totalMonthlyCostDataPoints[] = [
        "label" => html_entity_decode($row['date']),
        "y" => round($row['cost'] / 12, 2),
    ];
}

$showTotalMonthlyCostGraph = count($totalMonthlyCostDataPoints) > 1;

?>
