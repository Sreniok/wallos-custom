<?php

require_once __DIR__ . '/i18n/getlang.php';
require_once __DIR__ . '/formatting_helpers.php';
require_once __DIR__ . '/subscription_dates.php';

function getBillingCycle($cycle, $frequency, $i18n)
{
    switch ($cycle) {
        case 1:
            return $frequency == 1 ? translate('Daily', $i18n) : $frequency . " " . translate('days', $i18n);
        case 2:
            return $frequency == 1 ? translate('Weekly', $i18n) : $frequency . " " . translate('weeks', $i18n);
        case 3:
            return $frequency == 1 ? translate('Monthly', $i18n) : $frequency . " " . translate('months', $i18n);
        case 4:
            return $frequency == 1 ? translate('Yearly', $i18n) : $frequency . " " . translate('years', $i18n);
    }
}

function getSubscriptionProgress($cycle, $frequency, $next_payment)
{
    return getSubscriptionCycleProgress([
        'cycle' => $cycle,
        'frequency' => $frequency,
        'next_payment' => $next_payment,
    ]);
}

function formatNotificationLeadTime($days, $i18n)
{
    $days = (int) $days;

    if ($days === 0) {
        return translate('on_due_date', $i18n);
    }

    if ($days === 1) {
        return "1 " . translate('day_before', $i18n);
    }

    return $days . " " . translate('days_before', $i18n);
}

function getEffectiveNotificationRuleLabel($subscription, $globalNotificationDays, $i18n)
{
    if (empty($subscription['notify'])) {
        return null;
    }

    $isGlobalRule = (int) ($subscription['notify_days_before'] ?? -1) === -1;
    $effectiveDays = $isGlobalRule ? $globalNotificationDays : (int) $subscription['notify_days_before'];
    $leadTimeText = formatNotificationLeadTime($effectiveDays, $i18n);
    $ruleSource = $isGlobalRule ? translate('default_value_from_settings', $i18n) : translate('custom_rule', $i18n);

    return translate('notifications', $i18n) . ': ' . $ruleSource . ' (' . $leadTimeText . ')';
}

function printSubscriptions($subscriptions, $sort, $categories, $members, $i18n, $colorTheme, $imagePath, $disabledToBottom, $mobileNavigation, $showSubscriptionProgress, $currencies, $lang)
{
    if ($sort === "price") {
        usort($subscriptions, function ($a, $b) {
            return $a['price'] < $b['price'] ? 1 : -1;
        });
        if ($disabledToBottom === 'true') {
            usort($subscriptions, function ($a, $b) {
                return $a['inactive'] - $b['inactive'];
            });
        }
    }

    $currentCategory = 0;
    $currentPayerUserId = 0;
    $currentPaymentMethodId = 0;
    foreach ($subscriptions as $subscription) {
        if ($sort == "category_id" && $subscription['category_id'] != $currentCategory) {
            ?>
            <div class="subscription-list-title">
                <?php
                if ($subscription['category_id'] == 1) {
                    echo translate('no_category', $i18n);
                } else {
                    echo $categories[$subscription['category_id']]['name'];
                }
                ?>
            </div>
            <?php
            $currentCategory = $subscription['category_id'];
        }
        if ($sort == "payer_user_id" && $subscription['payer_user_id'] != $currentPayerUserId) {
            ?>
            <div class="subscription-list-title">
                <?= $members[$subscription['payer_user_id']]['name'] ?>
            </div>
            <?php
            $currentPayerUserId = $subscription['payer_user_id'];
        }
        if ($sort == "payment_method_id" && $subscription['payment_method_id'] != $currentPaymentMethodId) {
            ?>
            <div class="subscription-list-title">
                <?= $subscription['payment_method_name'] ?>
            </div>
            <?php
            $currentPaymentMethodId = $subscription['payment_method_id'];
        }
        ?>
        <div class="subscription-container" tabindex="0" role="group" aria-label="<?= htmlspecialchars($subscription['name'], ENT_QUOTES, 'UTF-8') ?>">
            <?php
            if ($mobileNavigation === 'true') {
                ?>
                <div class="mobile-actions" data-id="<?= $subscription['id'] ?>">
                    <button class="mobile-action-clone"></button>
                    <button class="mobile-action-clone" aria-label="<?= translate('clone', $i18n) ?>" data-action="clone-subscription" data-id="<?= $subscription['id'] ?>">
                        <?php include $imagePath . "images/siteicons/svg/mobile-menu/clone.php"; ?>
                        Clone
                    </button>
                    <button class="mobile-action-delete" aria-label="<?= translate('delete', $i18n) ?>" data-action="delete-subscription" data-id="<?= $subscription['id'] ?>">
                        <?php include $imagePath . "images/siteicons/svg/mobile-menu/delete.php"; ?>
                        Delete
                    </button>
                    <?php
                    if ($subscription['auto_renew'] != 1) {
                        ?>
                        <button class="mobile-action-paid" aria-label="<?= translate('mark_paid', $i18n) ?>" data-action="mark-paid" data-id="<?= $subscription['id'] ?>">
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"></path>
                            </svg>
                            <?= translate('mark_paid', $i18n) ?>
                        </button>
                        <button class="mobile-action-renew" aria-label="<?= translate('skip_missing_payments', $i18n) ?>" data-action="renew-subscription" data-id="<?= $subscription['id'] ?>">
                            <?php include $imagePath . "images/siteicons/svg/mobile-menu/renew.php"; ?>
                            <?= translate('skip_missing_payments', $i18n) ?>
                        </button>
                        <?php
                    }
                    ?>
                    <button class="mobile-action-edit" aria-label="<?= translate('edit_subscription', $i18n) ?>" data-action="edit-subscription" data-id="<?= $subscription['id'] ?>">
                        <?php include $imagePath . "images/siteicons/svg/mobile-menu/edit.php"; ?>
                        Edit
                    </button>
                </div>
                <?php
            }

            $subscriptionExtraClasses = "";
            if ($subscription['inactive']) {
                $subscriptionExtraClasses .= " inactive";
            }
            if ($subscription['auto_renew'] != 1) {
                $subscriptionExtraClasses .= " manual";
            }

            $hasLogo = false;
            if ($subscription['logo'] != "") {
                $hasLogo = true;
            }

            ?>

            <div class="subscription<?= $subscriptionExtraClasses ?>"
                data-action="toggle-subscription" data-id="<?= $subscription['id'] ?>"
                data-name="<?= $subscription['name'] ?>" role="button" tabindex="0">
                <div class="subscription-main">
                    <span class="logo <?= !$hasLogo ? 'hideOnMobile' : '' ?>">
                        <?php
                        if ($hasLogo) {
                            ?>
                            <img src="<?= $subscription['logo'] ?>">
                            <?php
                        } else {
                            include $imagePath . "images/siteicons/svg/logo.php";
                        }
                        ?>
                    </span>
                    <?php
                    $hasNameBadge = !empty($subscription['notify'])
                        || !empty($subscription['adjust_to_working_day'])
                        || !empty($subscription['inactive']);
                    ?>
                    <?php
                    $renderedName = preg_replace(
                        '/[\x{2605}\x{2606}]+/u',
                        '<span class="name-stars">$0</span>',
                        $subscription['name']
                    );
                    ?>
                    <span class="name <?= $hasLogo ? 'hideOnMobile' : '' ?>">
                        <span class="name-text"><?= $renderedName ?></span>
                        <?php if ($hasNameBadge): ?>
                            <span class="name-badges">
                                <?php if (!empty($subscription['notify'])): ?>
                                    <span class="meta-badge meta-notify" title="<?= translate('notify_me', $i18n) ?>"><i class="fa-solid fa-bell"></i></span>
                                <?php endif; ?>
                                <?php if (!empty($subscription['adjust_to_working_day'])): ?>
                                    <span class="meta-badge meta-workday" title="<?= translate('adjust_to_working_day', $i18n) ?: 'Adjusts to working day' ?>"><i class="fa-solid fa-calendar-day"></i></span>
                                <?php endif; ?>
                                <?php if (!empty($subscription['inactive'])): ?>
                                    <span class="meta-badge meta-inactive" title="<?= translate('disabled', $i18n) ?: 'Disabled' ?>"><i class="fa-solid fa-circle-minus"></i></span>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </span>
                    <span class="cycle"
                        title="<?= $subscription['auto_renew'] ? translate("automatically_renews", $i18n) : translate("manual_renewal", $i18n) ?>">
                        <?php
                        if ($subscription['auto_renew']) {
                            include $imagePath . "images/siteicons/svg/automatic.php";
                        } else {
                            include $imagePath . "images/siteicons/svg/manual.php";
                        }
                        ?>
                        <?= $subscription['billing_cycle'] ?>
                    </span>
                    <span class="next"><?= formatDate($subscription['next_payment'], $lang) ?></span>
                    <?php
                    // Mobile meta line — mockup style: "in 3 days · auto · Visa"
                    $rawDate = $subscription['raw_next_payment'] ?? $subscription['next_payment'];
                    $relLabel = wallosRelativeDayLabel($rawDate);
                    $renewLabel = translate(((int) $subscription['auto_renew'] === 1) ? 'renew_auto_short' : 'renew_manual_short', $i18n);
                    $relIsLate = strpos($relLabel, 'late') !== false;
                    ?>
                    <span class="subscription-mobile-meta">
                        <?php if (!empty($relLabel)): ?>
                            <span class="rel-time<?= $relIsLate ? ' overdue' : '' ?>"><?= htmlspecialchars($relLabel) ?></span>
                        <?php endif; ?>
                        <span class="date-label"><?= htmlspecialchars(formatDate($subscription['next_payment'], $lang)) ?></span>
                        <span class="renew-label"><?= $renewLabel ?></span>
                        <?php if (!empty($subscription['payment_method_name'])): ?>
                            <span class="pm-label"><?= htmlspecialchars($subscription['payment_method_name']) ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="price">
                        <span class="value">
                            <?= formatPrice($subscription['price'], $subscription['currency_code'], $currencies) ?>
                            <?php
                            if (isset($subscription['original_price']) && $subscription['original_price'] != $subscription['price']) {
                                ?>
                                <span
                                    class="original_price">(<?= formatPrice($subscription['original_price'], $subscription['original_currency_code'], $currencies) ?>)</span>
                                <?php
                            }
                            ?>
                        </span>
                        <span class="cycle-suffix"><?= wallosCycleSuffix($subscription['cycle'] ?? 0) ?></span>
                    </span>
                    <span class="payment_method">
                        <img src="<?= $subscription['payment_method_icon'] ?>"
                            title="<?= translate('payment_method', $i18n) ?>: <?= $subscription['payment_method_name'] ?>" />
                    </span>
                    <?php
                    $desktopMenuButtonClass = ""; {
                    }
                    if ($mobileNavigation === "true") {
                        $desktopMenuButtonClass = "mobileNavigationHideOnMobile";
                    }
                    ?>
                    <button type="button" class="actions-expand <?= $desktopMenuButtonClass ?>"
                        aria-label="<?= translate('more_options', $i18n) ?>"
                        data-action="expand-actions" data-id="<?= $subscription['id'] ?>">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <ul class="actions">
                        <li class="edit" title="<?= translate('edit_subscription', $i18n) ?>" role="button" tabindex="0"
                            data-action="edit-subscription" data-id="<?= $subscription['id'] ?>">
                            <?php include $imagePath . "images/siteicons/svg/edit.php"; ?>
                            <?= translate('edit_subscription', $i18n) ?>
                        </li>
                        <li class="delete" title="<?= translate('delete', $i18n) ?>" role="button" tabindex="0"
                            data-action="delete-subscription" data-id="<?= $subscription['id'] ?>">
                            <?php include $imagePath . "images/siteicons/svg/delete.php"; ?>
                            <?= translate('delete', $i18n) ?>
                        </li>
                        <li class="clone" title="<?= translate('clone', $i18n) ?>" role="button" tabindex="0"
                            data-action="clone-subscription" data-id="<?= $subscription['id'] ?>">
                            <?php include $imagePath . "images/siteicons/svg/clone.php"; ?>
                            <?= translate('clone', $i18n) ?>
                        </li>
                        <?php
                        if ($subscription['auto_renew'] != 1) {
                            ?>
                            <li class="paid" title="<?= translate('mark_paid', $i18n) ?>" role="button" tabindex="0"
                                data-action="mark-paid" data-id="<?= $subscription['id'] ?>">
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"></path>
                                </svg>
                                <?= translate('mark_paid', $i18n) ?>
                            </li>
                            <li class="renew" title="<?= translate('renew', $i18n) ?>" role="button" tabindex="0"
                                data-action="renew-subscription" data-id="<?= $subscription['id'] ?>">
                                <?php include $imagePath . "images/siteicons/svg/renew.php"; ?>
                                <?= translate('skip_missing_payments', $i18n) ?>
                            </li>
                            <?php
                        }
                        ?>
                    </ul>
                </div>
                <div class="subscription-secondary">
                    <span
                        class="name"><?php include $imagePath . "images/siteicons/svg/subscription.php"; ?><?= $subscription['name'] ?></span>
                    <span class="category"
                        title="<?= translate('category', $i18n) ?>"><?php include $imagePath . "images/siteicons/svg/category.php"; ?><?= $categories[$subscription['category_id']]['name'] ?></span>
                    <span class="payer_user"
                        title="<?= translate('paid_by', $i18n) ?>"><?php include $imagePath . "images/siteicons/svg/payment.php"; ?><?= $members[$subscription['payer_user_id']]['name'] ?></span>
                    <span class="payment-method-name"
                        title="<?= translate('payment_method', $i18n) ?>"><img src="<?= $subscription['payment_method_icon'] ?>" alt=""><?= htmlspecialchars($subscription['payment_method_name'] ?? '') ?></span>
                    <?php
                    if (!empty($subscription['notification_rule_label'])) {
                        ?>
                        <span class="notification-rule" title="<?= htmlspecialchars($subscription['notification_rule_label']) ?>">
                            <i class="fa-solid fa-bell"></i><span class="rule-text"><?= htmlspecialchars($subscription['notification_rule_label']) ?></span>
                        </span>
                        <?php
                    }
                    ?>
                    <?php
                    if (!empty($subscription['adjust_to_working_day'])) {
                        ?>
                        <span class="working-day-badge" title="<?= translate('adjust_to_working_day', $i18n) ?: 'Adjusts to working day' ?>">
                            <i class="fa-solid fa-calendar-day"></i><span class="badge-text">working day</span>
                        </span>
                        <?php
                    }
                    ?>
                    <?php
                    if (!empty($subscription['inactive'])) {
                        ?>
                        <span class="inactive-badge" title="<?= translate('disabled', $i18n) ?: 'Disabled' ?>">
                            <i class="fa-solid fa-circle-minus"></i><span class="badge-text">inactive</span>
                        </span>
                        <?php
                    }
                    ?>
                    <?php
                    if ($subscription['url'] != "") {
                        $url = $subscription['url'];
                        if (!preg_match('/^https?:\/\//', $url)) {
                            $url = "https://" . $url;
                        }
                        ?>
                        <span class="url" title="<?= translate('external_url', $i18n) ?>"><a href="<?= $url ?>" target="_blank"
                                rel="noreferrer"><?php include $imagePath . "images/siteicons/svg/web.php"; ?></a></span>
                        <?php
                    }
                    ?>
                </div>
                <?php
                if ($subscription['notes'] != "") {
                    ?>
                    <div class="subscription-notes">
                        <span class="notes">
                            <?php include $imagePath . "images/siteicons/svg/notes.php"; ?>
                            <?= $subscription['notes'] ?>
                        </span>
                    </div>
                    <?php
                }
                ?>
            </div>
            <?php
            if ($showSubscriptionProgress === 'true') {
                $progress = $subscription['progress'] > 100 ? 100 : $subscription['progress'];
                ?>
                <div class="subscription-progress-container">
                    <span class="subscription-progress" style="width: <?= $progress ?>%;"></span>
                </div>
                <?php
            }
            ?>
        </div>
        <?php
    }
}

$query = "SELECT main_currency FROM user WHERE id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
if ($row !== false) {
    $mainCurrencyId = $row['main_currency'];
} else {
    $mainCurrencyId = $currencies[1]['id'];
}

?>
