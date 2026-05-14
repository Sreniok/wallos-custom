<?php

require_once 'includes/header.php';
require_once 'includes/getdbkeys.php';
require_once 'includes/subscription_dates.php';

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

// Fetch payments explicitly recorded this month and auto-renewed payments that already passed.
$monthStart = new DateTimeImmutable('first day of this month');
$today = new DateTimeImmutable('today');

$stmt = $db->prepare("SELECT id, logo, name, price, currency_id, next_payment, last_payment_date, start_date, cycle, frequency, auto_renew FROM subscriptions WHERE user_id = :userId AND inactive = 0 AND (auto_renew = 1 OR last_payment_date BETWEEN :monthStart AND :today)");
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$stmt->bindValue(':monthStart', $monthStart->format('Y-m-d'), SQLITE3_TEXT);
$stmt->bindValue(':today', $today->format('Y-m-d'), SQLITE3_TEXT);
$result = $stmt->execute();
$paidThisMonthSubscriptions = [];
$paidThisMonthKeys = [];

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $paymentDates = [];

    if (!empty($row['last_payment_date'])) {
        $lastPaymentDate = new DateTimeImmutable($row['last_payment_date']);
        if ($lastPaymentDate >= $monthStart && $lastPaymentDate <= $today) {
            $paymentDates[] = $lastPaymentDate->format('Y-m-d');
        }
    }

    $paymentDates = array_merge($paymentDates, getPassedAutoRenewalOccurrencesInRange($row, $monthStart, $today));

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
                                <span class="rel-time overdue"><?= htmlspecialchars(wallosRelativeDayLabel($subscriptionNextPayment)) ?></span>
                                <span class="meta-sep">&middot;</span>
                                <span class="rel-date"><?= htmlspecialchars(formatDate($subscriptionDisplayNextPayment, $lang)) ?></span>
                                <span class="meta-sep">&middot;</span>
                                <span class="renew-label"><?= ((int) $subscription['auto_renew'] === 1) ? 'auto-renew' : 'manual' ?></span>
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
                                <span class="rel-time"><?= htmlspecialchars(wallosRelativeDayLabel($subscriptionNextPayment)) ?></span>
                                <span class="meta-sep">&middot;</span>
                                <span class="rel-date"><?= htmlspecialchars(formatDate($subscriptionDisplayNextPayment, $lang)) ?></span>
                                <span class="meta-sep">&middot;</span>
                                <span class="renew-label"><?= ((int) $subscription['auto_renew'] === 1) ? 'auto-renew' : 'manual' ?></span>
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

        <div class="paid-this-month-subscriptions">
            <h2><?= translate('paid_this_month', $i18n) ?></h2>
            <div class="dashboard-subscriptions-container">
                <div class="dashboard-subscriptions-list">
                    <?php
                    if (empty($paidThisMonthSubscriptions)) {
                        ?>
                        <p><?= translate('no_paid_this_month', $i18n) ?></p>
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
