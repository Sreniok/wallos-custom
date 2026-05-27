<?php

require_once 'includes/header.php';
require_once 'includes/getdbkeys.php';
require_once 'includes/subscription_dates.php';
require_once 'includes/budget_cycles.php';

// Get the first name of the user
$stmt = $db->prepare("SELECT username, firstname FROM user WHERE id = :userId");
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);
$first_name = $user['firstname'] ?? $user['username'] ?? '';

// Fetch the next 3 enabled subscriptions that are due after today.
$stmt = $db->prepare("SELECT id, logo, name, price, currency_id, next_payment, inactive, auto_renew, cycle, frequency FROM subscriptions WHERE user_id = :userId AND next_payment > date('now') AND inactive = 0 ORDER BY next_payment ASC LIMIT 3");
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$upcomingSubscriptions = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $upcomingSubscriptions[] = $row;
}

// Fetch enabled subscriptions with manual renewal that are overdue
$stmt = $db->prepare("SELECT id, logo, name, price, currency_id, next_payment, inactive, auto_renew, cycle, frequency FROM subscriptions WHERE user_id = :userId AND next_payment < date('now') AND auto_renew = 0 AND inactive = 0 ORDER BY next_payment ASC");
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$overdueSubscriptions = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $overdueSubscriptions[] = $row;
}
$hasOverdueSubscriptions = !empty($overdueSubscriptions);

// Fetch payments explicitly recorded this cycle and auto-renewed payments that already passed.
$today = new DateTimeImmutable('today');
$usesPayrollCycle = wallosUsesPayrollBudgetCycle($userData);
if ($usesPayrollCycle) {
    $payrollPeriod = wallosGetPayrollPeriod($userData);
    $periodStart = $payrollPeriod['start'];
    $periodEnd = $payrollPeriod['end'] < $today ? $payrollPeriod['end'] : $today;
} else {
    $periodStart = new DateTimeImmutable('first day of this month');
    $periodEnd = $today;
}

$stmt = $db->prepare("SELECT id, logo, name, price, currency_id, next_payment, last_payment_date, start_date, cycle, frequency, auto_renew FROM subscriptions WHERE user_id = :userId AND inactive = 0 AND (auto_renew = 1 OR last_payment_date BETWEEN :periodStart AND :periodEnd)");
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$stmt->bindValue(':periodStart', $periodStart->format('Y-m-d'), SQLITE3_TEXT);
$stmt->bindValue(':periodEnd', $periodEnd->format('Y-m-d'), SQLITE3_TEXT);
$result = $stmt->execute();
$paidThisMonthSubscriptions = [];
$paidThisMonthKeys = [];

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $paymentDates = [];

    if (!empty($row['last_payment_date'])) {
        $lastPaymentDate = new DateTimeImmutable($row['last_payment_date']);
        if ($lastPaymentDate >= $periodStart && $lastPaymentDate <= $periodEnd) {
            $paymentDates[] = $lastPaymentDate->format('Y-m-d');
        }
    }

    $paymentDates = array_merge($paymentDates, getPassedAutoRenewalOccurrencesInRange($row, $periodStart, $periodEnd));

    foreach (array_unique($paymentDates) as $paymentDate) {
        $paymentKey = $row['id'] . ':' . $paymentDate;
        if (isset($paidThisMonthKeys[$paymentKey])) {
            continue;
        }

        $paidThisMonthKeys[$paymentKey] = true;
        $paidThisMonthSubscriptions[] = [
            'id' => $row['id'],
            'logo' => $row['logo'],
            'name' => $row['name'],
            'price' => $row['price'],
            'currency_id' => $row['currency_id'],
            'payment_date' => $paymentDate,
            'cycle' => $row['cycle'],
            'frequency' => $row['frequency'],
        ];
    }
}

usort($paidThisMonthSubscriptions, function ($left, $right) {
    return strcmp($right['payment_date'], $left['payment_date']);
});

require_once 'includes/stats_calculations.php';

// Get AI Recommendations for user
$stmt = $db->prepare("SELECT * FROM ai_recommendations WHERE user_id = :userId");
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$aiRecommendations = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $aiRecommendations[] = $row;
}

?>

<section class="contain dashboard">
    <?php
        if ($isAdmin && $settings['update_notification']) {
            if (!is_null($settings['latest_version'])) {
                $latestVersion = $settings['latest_version'];
                if (version_compare($version, $latestVersion) == -1) {
                    ?>
                    <div class="update-banner">
                    <?= translate('new_version_available', $i18n) ?>:
                        <span><a href="https://github.com/ellite/Wallos/releases/tag/<?= htmlspecialchars($latestVersion) ?>"
                        target="_blank" rel="noreferer">
                        <?= htmlspecialchars($latestVersion) ?>
                        </a></span>
                    </div>
                    <?php
                }
            }
        }
        if ($demoMode) {
            ?>
            <div class="demo-banner">
            Running in <b>Demo Mode</b>, certain actions and settings are disabled.<br>
            The database will be reset every 120 minutes.
            </div>
            <?php
        }
    ?>
    <h1><?= translate('hello', $i18n) ?> <?= htmlspecialchars($first_name) ?></h1>

    <?php require 'includes/next_payment_hero.php'; ?>

    <?php
    // If there are overdue subscriptions, display them
    if ($hasOverdueSubscriptions) {
        ?>
        <div class="overdue-subscriptions">
            <h2><?= translate('overdue_renewals', $i18n) ?></h2>
            <div class="dashboard-subscriptions-container">
                <div class="dashboard-subscriptions-list">
                    <?php

                    foreach ($overdueSubscriptions as $subscription) {
                        $subscriptionLogo = "images/uploads/logos/" . $subscription['logo'];
                        $subscriptionName = htmlspecialchars($subscription['name']);
                        $subscriptionPrice = $subscription['price'];
                        $subscriptionCurrency = $subscription['currency_id'];
                        $subscriptionNextPayment = $subscription['next_payment'];
                        $subscriptionDisplayNextPayment = date('F j', strtotime($subscriptionNextPayment));
                        $subscriptionDisplayPrice = formatPrice($subscriptionPrice, $currencies[$subscriptionCurrency]['code'], $currencies);
                        $subscriptionCycleSuffix = wallosCycleSuffix($subscription['cycle'] ?? 0, $subscription['frequency'] ?? 1);

                        ?>
                        <div class="subscription-item subscription-item-clickable" role="button" tabindex="0" data-click="openSubscriptionModal" data-keydown="openSubscriptionModal" data-keys="Enter,Space" data-prevent-default="true" data-args='[<?= (int) $subscription['id'] ?>]'>
                            <?php
                            if (empty($subscription['logo'])) {
                                ?>
                                <p class="subscription-item-title"><?= $subscriptionName ?></p>
                                <?php
                            } else {
                                ?>
                                <img src="<?= $subscriptionLogo ?>" alt="<?= $subscriptionName ?> logo"
                                    class="subscription-item-logo" title="<?= $subscriptionName ?>">
                                <?php
                            }
                            ?>
                            <p class="subscription-item-name"><?= $subscriptionName ?></p>
                            <p class="subscription-item-meta">
                                <span class="rel-date"><?= htmlspecialchars(formatDate($subscriptionDisplayNextPayment, $lang)) ?></span>
                                <span class="meta-sep">&middot;</span>
                                <span class="rel-time overdue"><?= htmlspecialchars(wallosRelativeDayLabel($subscriptionNextPayment)) ?></span>
                                <span class="meta-sep">&middot;</span>
                                <span class="renew-label"><?= ((int) $subscription['auto_renew'] === 1) ? 'auto' : 'manual' ?></span>
                            </p>
                            <div class="subscription-item-info">
                                <p class="subscription-item-date"> <?= formatDate($subscriptionDisplayNextPayment, $lang) ?>
                                </p>
                                <p class="subscription-item-price"><span class="price-amount"><?= $subscriptionDisplayPrice ?></span><span class="price-cycle"><?= htmlspecialchars($subscriptionCycleSuffix) ?></span></p>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }
    ?>

    <div class="upcoming-subscriptions">
        <h2><?= translate('upcoming_payments', $i18n) ?></h2>
        <div class="dashboard-subscriptions-container">
            <div class="dashboard-subscriptions-list">
                <?php
                if (empty($upcomingSubscriptions)) {
                    ?>
                    <p><?= translate('no_upcoming_payments', $i18n) ?></p>
                    <?php
                } else {
                    foreach ($upcomingSubscriptions as $subscription) {
                        $subscriptionLogo = "images/uploads/logos/" . $subscription['logo'];
                        $subscriptionName = htmlspecialchars($subscription['name']);
                        $subscriptionPrice = $subscription['price'];
                        $subscriptionCurrency = $subscription['currency_id'];
                        $subscriptionNextPayment = $subscription['next_payment'];
                        $subscriptionDisplayNextPayment = date('F j', strtotime($subscriptionNextPayment));
                        $subscriptionDisplayPrice = formatPrice($subscriptionPrice, $currencies[$subscriptionCurrency]['code'], $currencies);
                        $subscriptionCycleSuffix = wallosCycleSuffix($subscription['cycle'] ?? 0, $subscription['frequency'] ?? 1);

                        ?>
                        <div class="subscription-item subscription-item-clickable" role="button" tabindex="0" data-click="openSubscriptionModal" data-keydown="openSubscriptionModal" data-keys="Enter,Space" data-prevent-default="true" data-args='[<?= (int) $subscription['id'] ?>]'>
                            <?php
                            if (empty($subscription['logo'])) {
                                ?>
                                <p class="subscription-item-title"><?= $subscriptionName ?></p>
                                <?php
                            } else {
                                ?>
                                <img src="<?= $subscriptionLogo ?>" alt="<?= $subscriptionName ?> logo"
                                    class="subscription-item-logo" title="<?= $subscriptionName ?>">
                                <?php
                            }
                            ?>
                            <p class="subscription-item-name"><?= $subscriptionName ?></p>
                            <p class="subscription-item-meta">
                                <span class="rel-date"><?= htmlspecialchars(formatDate($subscriptionDisplayNextPayment, $lang)) ?></span>
                                <span class="meta-sep">&middot;</span>
                                <span class="rel-time"><?= htmlspecialchars(wallosRelativeDayLabel($subscriptionNextPayment)) ?></span>
                                <span class="meta-sep">&middot;</span>
                                <span class="renew-label"><?= ((int) $subscription['auto_renew'] === 1) ? 'auto' : 'manual' ?></span>
                            </p>
                            <div class="subscription-item-info">
                                <p class="subscription-item-date"> <?= formatDate($subscriptionDisplayNextPayment, $lang) ?></p>
                                <p class="subscription-item-price"><span class="price-amount"><?= $subscriptionDisplayPrice ?></span><span class="price-cycle"><?= htmlspecialchars($subscriptionCycleSuffix) ?></span></p>
                            </div>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
        </div>

        <?php
        require_once 'includes/fuel_vehicles_load.php';
        if (!empty($fuelVehicles)):
            $mainCurrencyId = (int) ($userData['main_currency'] ?? 0);
            ?>
            <div class="petrol-upcoming-card">
                <h2><?= translate('petrol', $i18n) ?></h2>
                <div class="dashboard-subscriptions-container">
                    <div class="dashboard-subscriptions-list">
                        <?php
                        $fuelUnitLabel = (($settings['fuelUnitSystem'] ?? 'eu') === 'us') ? 'gal' : 'L';
                        foreach ($fuelVehicles as $vehicle):
                            $total = (float) ($vehicle['period_total'] ?? 0);
                            $quantity = (float) ($vehicle['period_quantity'] ?? 0);
                            $displayName = htmlspecialchars($vehicle['name'], ENT_QUOTES, 'UTF-8');
                            $registration = trim($vehicle['registration'] ?? '');
                            $fuelTypeLabel = translate(($vehicle['fuel_type'] ?? 'petrol') === 'diesel' ? 'diesel' : 'petrol', $i18n);
                            $fillCount = (int) ($vehicle['fill_count'] ?? 0);
                            $vehicleLogo = !empty($vehicle['logo_url']) ? htmlspecialchars($vehicle['logo_url'], ENT_QUOTES, 'UTF-8') : '';
                            $totalLabel = formatPrice($total, $currencies[$mainCurrencyId]['code'], $currencies);
                            $fillSuffix = '/' . $fillCount . ' ' . translate($fillCount === 1 ? 'fill' : 'fills', $i18n);
                            $quantityLabel = (rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.') ?: '0') . ' ' . $fuelUnitLabel;
                            ?>
                            <div class="subscription-item subscription-item-clickable petrol-subscription-card" role="button" tabindex="0"
                                data-click="openPetrolExpenseModal" data-keydown="openPetrolExpenseModal" data-keys="Enter,Space"
                                data-prevent-default="true" data-args='[<?= (int) $vehicle['id'] ?>]'
                                aria-label="<?= $displayName ?>">
                                <?php if ($vehicleLogo !== ''): ?>
                                    <img src="<?= $vehicleLogo ?>" alt="<?= $displayName ?> logo"
                                        class="subscription-item-logo" title="<?= $displayName ?>">
                                <?php else: ?>
                                    <span class="subscription-item-logo petrol-card-logo" aria-hidden="true">
                                        <i class="fa-solid fa-gas-pump"></i>
                                    </span>
                                <?php endif ?>
                                <p class="subscription-item-name"><?= $displayName ?></p>
                                <p class="subscription-item-meta" title="<?= htmlspecialchars($fuelTypeLabel) ?>">
                                    <?php if ($registration !== ''): ?>
                                        <span class="vehicle-plates-inline">
                                            <span class="fuel-registration-badge" title="<?= translate('vehicle_registration', $i18n) ?>">
                                                <i class="fa-solid fa-id-card-clip" aria-hidden="true"></i>
                                                <?= htmlspecialchars($registration, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </span>
                                        <span class="meta-sep">&middot;</span>
                                    <?php endif ?>
                                    <span class="renew-label fuel-quantity-label"><?= htmlspecialchars($quantityLabel) ?></span>
                                </p>
                                <div class="subscription-item-info">
                                    <p class="subscription-item-date"><?= htmlspecialchars($fuelPeriodLabel) ?></p>
                                    <p class="subscription-item-price">
                                        <span class="price-amount"><?= $totalLabel ?></span><span class="price-cycle"><?= htmlspecialchars($fillSuffix) ?></span>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>
        <?php endif ?>

        <div class="paid-this-month-subscriptions">
            <h2><?= translate($usesPayrollCycle ? 'paid_this_cycle' : 'paid_this_month', $i18n) ?></h2>
            <div class="dashboard-subscriptions-container">
                <div class="dashboard-subscriptions-list">
                    <?php
                    if (empty($paidThisMonthSubscriptions)) {
                        ?>
                        <p><?= translate($usesPayrollCycle ? 'no_paid_this_cycle' : 'no_paid_this_month', $i18n) ?></p>
                        <?php
                    } else {
                        foreach ($paidThisMonthSubscriptions as $subscription) {
                            $subscriptionLogo = "images/uploads/logos/" . $subscription['logo'];
                            $subscriptionName = htmlspecialchars($subscription['name']);
                            $subscriptionPrice = $subscription['price'];
                            $subscriptionCurrency = $subscription['currency_id'];
                            $subscriptionPaymentDate = $subscription['payment_date'];
                            $subscriptionDisplayPaymentDate = date('F j', strtotime($subscriptionPaymentDate));
                            $subscriptionDisplayPrice = formatPrice($subscriptionPrice, $currencies[$subscriptionCurrency]['code'], $currencies);
                            $subscriptionCycleSuffix = wallosCycleSuffix($subscription['cycle'] ?? 0, $subscription['frequency'] ?? 1);

                            ?>
                            <div class="subscription-item subscription-item-clickable" role="button" tabindex="0" data-click="openSubscriptionModal" data-keydown="openSubscriptionModal" data-keys="Enter,Space" data-prevent-default="true" data-args='[<?= (int) $subscription['id'] ?>]'>
                                <?php
                                if (empty($subscription['logo'])) {
                                    ?>
                                    <p class="subscription-item-title"><?= $subscriptionName ?></p>
                                    <?php
                                } else {
                                    ?>
                                    <img src="<?= $subscriptionLogo ?>" alt="<?= $subscriptionName ?> logo"
                                        class="subscription-item-logo" title="<?= $subscriptionName ?>">
                                    <?php
                                }
                                ?>
                                <p class="subscription-item-name"><?= $subscriptionName ?></p>
                                <p class="subscription-item-meta">
                                    <span class="rel-time"><?= htmlspecialchars(formatDate(date('F j', strtotime($subscriptionPaymentDate)), $lang)) ?></span>
                                    <span class="meta-sep">&middot;</span>
                                    <span class="paid-badge">&#10003; paid</span>
                                </p>
                                <div class="subscription-item-info">
                                    <p class="subscription-item-date"><?= formatDate($subscriptionDisplayPaymentDate, $lang) ?></p>
                                    <p class="subscription-item-price"><span class="price-amount"><?= $subscriptionDisplayPrice ?></span><span class="price-cycle"><?= htmlspecialchars($subscriptionCycleSuffix) ?></span></p>
                                </div>
                            </div>
                            <?php
                        }
                    }
                    ?>
                </div>
            </div>
        </div>

        <?php if (!empty($aiRecommendations)) { ?>
            <div class="ai-recommendations">
                <h2><?= translate('ai_recommendations', $i18n) ?></h2>
                <div class="ai-recommendations-container">
                    <ul class="ai-recommendations-list">
                        <?php

                        foreach ($aiRecommendations as $key => $recommendation) { ?>
                            <li class="ai-recommendation-item" data-id="<?= $recommendation['id'] ?>">
                                <div class="ai-recommendation-header">
                                    <h3>
                                        <span><?= ($key + 1) . ". " ?></span>
                                        <?= htmlspecialchars($recommendation['title']) ?>
                                    </h3>
                                    <span class="item-arrow-down fa fa-caret-down"></span>
                                </div>
                                <p class="collapsible"><?= htmlspecialchars($recommendation['description']) ?></p>
                                <p class="ai-recommendation-savings">
                                    <?= htmlspecialchars($recommendation['savings']) ?>
                                    <span>
                                        <a href="#" class="delete-ai-recommendation" title="<?= translate('delete', $i18n) ?>">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    </span>
                                </p>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
            </div>

        <?php } ?>

        <?php if (isset($amountDueThisMonth) || isset($budget) || isset($budgetUsed) || isset($budgetLeft) || isset($overBudgetAmount)) { ?>
            <div class="budget-subscriptions">
                <h2><?= translate('your_budget', $i18n) ?></h2>
                <div class="dashboard-subscriptions-container">
                    <div class="dashboard-subscriptions-list">
                        <?php if (isset($amountDueThisMonth)) { ?>
                            <div class="subscription-item thin">
                                <p class="subscription-item-title"><?= translate("amount_due", $i18n) ?></p>
                                <div class="subscription-item-info">
                                    <p class="subscription-item-value">
                                        <?= CurrencyFormatter::format($amountDueThisMonth, $currencies[$userData['main_currency']]['code']) ?>
                                    </p>
                                </div>
                            </div>
                        <?php } ?>
                        <?php if (isset($budget) && $budget > 0) { ?>
                            <div class="subscription-item thin">
                                <p class="subscription-item-title"><?= translate("budget", $i18n) ?></p>
                                <div class="subscription-item-info">
                                    <p class="subscription-item-value">
                                        <?= formatPrice($budget, $currencies[$userData['main_currency']]['code'], $currencies) ?>
                                    </p>
                                </div>
                            </div>
                        <?php } ?>
                        <?php if (isset($budgetUsed)) { ?>
                            <div class="subscription-item thin">
                                <p class="subscription-item-title"><?= translate("budget_used", $i18n) ?></p>
                                <div class="subscription-item-info">
                                    <p class="subscription-item-value">
                                        <?= number_format($budgetUsed, 2) ?>%
                                    </p>
                                </div>
                            </div>
                        <?php } ?>
                        <?php if (isset($budgetLeft)) { ?>
                            <div class="subscription-item thin">
                                <p class="subscription-item-title"><?= translate("budget_remaining", $i18n) ?></p>
                                <div class="subscription-item-info">
                                    <p class="subscription-item-value">
                                        <?= formatPrice($budgetLeft, $currencies[$userData['main_currency']]['code'], $currencies) ?>
                                    </p>
                                </div>
                            </div>
                        <?php } ?>
                        <?php if (isset($overBudgetAmount) && $overBudgetAmount > 0) { ?>
                            <div class="subscription-item thin">
                                <p class="subscription-item-title"><?= translate("over_budget", $i18n) ?></p>
                                <div class="subscription-item-info">
                                    <p class="subscription-item-value">
                                        <?= formatPrice($overBudgetAmount, $currencies[$userData['main_currency']]['code'], $currencies) ?>
                                    </p>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>

    <?php if (isset($fuelThisPeriod) && ($fuelThisPeriod > 0 || $fuelThisYear > 0)) {
        $fuelUnitLabelLocal = (($settings['fuelUnitSystem'] ?? 'eu') === 'us') ? 'gal' : 'L';
        $fmtQty = static function (float $q) {
            return (rtrim(rtrim(number_format($q, 2, '.', ''), '0'), '.') ?: '0');
        };
        $periodQtyLabel = ($fuelQuantityThisPeriod ?? 0) > 0
            ? ' / ' . $fmtQty((float) $fuelQuantityThisPeriod) . ' ' . $fuelUnitLabelLocal
            : '';
        $yearQtyLabel = ($fuelQuantityThisYear ?? 0) > 0
            ? ' / ' . $fmtQty((float) $fuelQuantityThisYear) . ' ' . $fuelUnitLabelLocal
            : '';
        ?>
        <div class="petrol-dashboard-subscriptions">
            <h2><?= translate('petrol', $i18n) ?></h2>
            <div class="dashboard-subscriptions-container">
                <div class="dashboard-subscriptions-list">
                    <div class="subscription-item thin">
                        <p class="subscription-item-title"><?= translate($usesPayrollCycle ? 'petrol_this_cycle' : 'petrol_this_month', $i18n) ?></p>
                        <div class="subscription-item-info">
                            <p class="subscription-item-value">
                                <?= formatPrice($fuelThisPeriod, $currencies[$userData['main_currency']]['code'], $currencies) ?><span class="fuel-quantity-suffix"><?= htmlspecialchars($periodQtyLabel) ?></span>
                            </p>
                        </div>
                        <p class="subscription-item-date"><?= htmlspecialchars($fuelPeriodLabel) ?></p>
                    </div>

                    <div class="subscription-item thin">
                        <p class="subscription-item-title"><?= translate('petrol_yearly_cost', $i18n) ?></p>
                        <div class="subscription-item-info">
                            <p class="subscription-item-value">
                                <?= formatPrice($fuelThisYear, $currencies[$userData['main_currency']]['code'], $currencies) ?><span class="fuel-quantity-suffix"><?= htmlspecialchars($yearQtyLabel) ?></span>
                            </p>
                        </div>
                        <p class="subscription-item-date"><?= date('Y') ?></p>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>

    <?php if (isset($activeSubscriptions) && $activeSubscriptions > 0) { ?>
        <div class="current-subscriptions">
            <h2><?= translate('your_subscriptions', $i18n) ?></h2>
            <div class="dashboard-subscriptions-container">
                <div class="dashboard-subscriptions-list">
                    <div class="subscription-item thin">
                        <p class="subscription-item-title"><?= translate('active_subscriptions', $i18n) ?></p>
                        <div class="subscription-item-info">
                            <p class="subscription-item-value"><?= $activeSubscriptions ?></p>
                        </div>
                    </div>

                    <?php if (isset($totalCostPerMonth)) { ?>
                        <div class="subscription-item thin">
                            <p class="subscription-item-title"><?= translate('monthly_cost', $i18n) ?></p>
                            <div class="subscription-item-info">
                                <p class="subscription-item-value">
                                    <?= CurrencyFormatter::format($totalCostPerMonth, $currencies[$userData['main_currency']]['code']) ?>
                                </p>
                            </div>
                        </div>
                    <?php } ?>

                    <?php if (isset($totalCostPerYear)) { ?>
                        <div class="subscription-item thin">
                            <p class="subscription-item-title"><?= translate('yearly_cost', $i18n) ?></p>
                            <div class="subscription-item-info">
                                <p class="subscription-item-value">
                                    <?= CurrencyFormatter::format($totalCostPerYear, $currencies[$userData['main_currency']]['code']) ?>
                                </p>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    <?php } ?>

    <?php if (isset($inactiveSubscriptions) && $inactiveSubscriptions > 0) { ?>
        <div class="savings-subscriptions">
            <h2><?= translate('your_savings', $i18n) ?></h2>
            <div class="dashboard-subscriptions-container">
                <div class="dashboard-subscriptions-list">
                    <div class="subscription-item thin">
                        <p class="subscription-item-title"><?= translate('inactive_subscriptions', $i18n) ?></p>
                        <div class="subscription-item-info">
                            <p class="subscription-item-value"><?= $inactiveSubscriptions ?></p>
                        </div>
                    </div>

                    <?php if (isset($totalSavingsPerMonth) && $totalSavingsPerMonth > 0) { ?>
                        <div class="subscription-item thin">
                            <p class="subscription-item-title"><?= translate('monthly_savings', $i18n) ?></p>
                            <div class="subscription-item-info">
                                <p class="subscription-item-value">
                                    <?= CurrencyFormatter::format($totalSavingsPerMonth, $currencies[$userData['main_currency']]['code']) ?>
                                </p>
                            </div>
                        </div>

                        <div class="subscription-item thin">
                            <p class="subscription-item-title"><?= translate('yearly_savings', $i18n) ?></p>
                            <div class="subscription-item-info">
                                <p class="subscription-item-value">
                                    <?= CurrencyFormatter::format($totalSavingsPerMonth * 12, $currencies[$userData['main_currency']]['code']) ?>
                                </p>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    <?php } ?>

</section>

<div id="subscriptionModal" class="subscription-modal">
    <div class="modal-content">
        <div id="subscriptionModalContent"></div>
    </div>
</div>

<div id="editEmbedOverlay" class="edit-embed-overlay">
    <div class="edit-embed-container">
        <button class="edit-embed-close" data-click="closeDashboardEditModal" title="Close">&times;</button>
        <iframe id="editEmbedFrame" src=""></iframe>
    </div>
</div>

<script src="scripts/calendar.js?<?= $version ?>"></script>
<script src="scripts/dashboard.js?<?= $version ?>"></script>
<script>
function openDashboardEditModal(id) {
    closeSubscriptionModal();
    const overlay = document.getElementById('editEmbedOverlay');
    const frame = document.getElementById('editEmbedFrame');
    frame.src = 'subscriptions.php?edit=' + id + '&embed=1';
    overlay.classList.add('is-open');
}

function closeDashboardEditModal() {
    const overlay = document.getElementById('editEmbedOverlay');
    const frame = document.getElementById('editEmbedFrame');
    overlay.classList.remove('is-open');
    frame.src = '';
    window.location.reload();
}

window.addEventListener('message', function(e) {
    if (e.data && e.data.type === 'subscription-saved') {
        closeDashboardEditModal();
    }
});
</script>

<?php
require_once 'includes/footer.php';
?>
