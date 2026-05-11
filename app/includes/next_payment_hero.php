<?php
/**
 * Modern theme hero card — "Next payment day" summary.
 *
 * Renders the soonest-due subscription(s) on the next payment day. Visible
 * only when the modern design theme is active; rendered as an empty wrapper
 * for other themes (kept to avoid layout jumps on theme switch).
 *
 * Required scope (provided by header.php + page bootstraps):
 *   $db, $userId, $currencies, $i18n, $lang, $activeDesign
 */

// Render the hero for all design themes — mobile-first.css styles it
// universally. Each theme's --main-color / --accent-color / --hover-color
// drive the gradient so the hero blends with the active theme.

$nphStmt = $db->prepare("
    SELECT id, logo, name, price, currency_id, next_payment, auto_renew
    FROM subscriptions
    WHERE user_id = :userId AND inactive = 0 AND next_payment >= date('now')
    ORDER BY next_payment ASC
    LIMIT 50
");
$nphStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$nphResult = $nphStmt->execute();
$nphAllUpcoming = [];
while ($row = $nphResult->fetchArray(SQLITE3_ASSOC)) {
    $nphAllUpcoming[] = $row;
}
if (empty($nphAllUpcoming)) {
    return;
}

$nphFirstDate = $nphAllUpcoming[0]['next_payment'];
$nphSubs = array_values(array_filter(
    $nphAllUpcoming,
    static fn($s) => $s['next_payment'] === $nphFirstDate
));

try {
    $nphTodayDt = new DateTime('today');
    $nphFirstDt = new DateTime($nphFirstDate);
    $nphDaysUntil = (int) $nphTodayDt->diff($nphFirstDt)->format('%r%a');
    if ($nphDaysUntil < 0) {
        $nphDaysUntil = 0;
    }
} catch (Exception $e) {
    $nphDaysUntil = 0;
}

if ($nphDaysUntil === 0) {
    $nphWhenLabel = 'Today';
} elseif ($nphDaysUntil === 1) {
    $nphWhenLabel = 'Tomorrow';
} else {
    $nphWhenLabel = 'in ' . $nphDaysUntil . ' days';
}

$nphTotal = 0.0;
foreach ($nphSubs as $s) {
    $nphTotal += (float) $s['price'];
}
$nphMainCurrency = $nphSubs[0]['currency_id'];
$nphMainCurrencyCode = $currencies[$nphMainCurrency]['code'] ?? '';
$nphSingle = count($nphSubs) === 1;
$nphDateDisplay = wallosFormatSubscriptionDate(date('F j', strtotime($nphFirstDate)), $lang);
?>
<div class="next-payment-hero <?= $nphSingle ? 'single' : '' ?>">
    <div class="nph-top">
        <span class="nph-label">Next payment</span>
        <span class="nph-when"><?= htmlspecialchars($nphWhenLabel) ?></span>
    </div>
    <div class="nph-date"><?= htmlspecialchars($nphDateDisplay) ?></div>
    <div class="nph-list">
        <?php foreach ($nphSubs as $s):
            $logoSrc = !empty($s['logo']) ? "images/uploads/logos/" . $s['logo'] : "";
            $cur = $currencies[$s['currency_id']]['code'] ?? '';
            $price = formatPrice($s['price'], $cur, $currencies);
            $renewLabel = ((int) $s['auto_renew'] === 1)
                ? translate('automatically_renews', $i18n)
                : translate('manual_renewal', $i18n);
        ?>
        <div class="nph-item" onclick="openSubscriptionModal(<?= (int) $s['id'] ?>)">
            <div class="nph-logo <?= empty($logoSrc) ? 'empty' : '' ?>">
                <?php if (!empty($logoSrc)): ?>
                    <img src="<?= htmlspecialchars($logoSrc) ?>" alt="">
                <?php else: ?>
                    <?= htmlspecialchars(mb_strtoupper(mb_substr($s['name'], 0, 1))) ?>
                <?php endif; ?>
            </div>
            <div class="nph-body">
                <div class="nph-name"><?= htmlspecialchars($s['name']) ?></div>
                <div class="nph-meta"><?= htmlspecialchars($renewLabel) ?></div>
            </div>
            <div class="nph-price"><?= $price ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if (!$nphSingle): ?>
    <div class="nph-total">
        <span>Total due</span>
        <span class="nph-total-amt"><?= formatPrice($nphTotal, $nphMainCurrencyCode, $currencies) ?></span>
    </div>
    <?php endif; ?>
</div>
<?php
unset($nphStmt, $nphResult, $nphAllUpcoming, $nphFirstDate, $nphSubs, $nphTodayDt, $nphFirstDt, $nphDaysUntil, $nphWhenLabel, $nphTotal, $nphMainCurrency, $nphMainCurrencyCode, $nphSingle, $nphDateDisplay);
?>
