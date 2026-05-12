<div class="sort-options" id="sort-options">
    <ul>
        <li <?= $sortOrder == "name" ? 'class="selected"' : "" ?> data-click="setSortOption" data-args='["name"]' id="sort-name">
            <?= translate('name', $i18n) ?>
        </li>
        <li <?= $sortOrder == "id" ? 'class="selected"' : "" ?> data-click="setSortOption" data-args='["id"]' id="sort-id">
            <?= translate('last_added', $i18n) ?>
        </li>
        <li <?= $sortOrder == "price" ? 'class="selected"' : "" ?> data-click="setSortOption" data-args='["price"]' id="sort-price">
            <?= translate('price', $i18n) ?>
        </li>
        <li <?= $sortOrder == "next_payment" ? 'class="selected"' : "" ?> data-click="setSortOption" data-args='["next_payment"]'
            id="sort-next_payment"><?= translate('next_payment', $i18n) ?></li>
        <li <?= $sortOrder == "payer_user_id" ? 'class="selected"' : "" ?> data-click="setSortOption" data-args='["payer_user_id"]'
            id="sort-payer_user_id"><?= translate('member', $i18n) ?></li>
        <li <?= $sortOrder == "category_id" ? 'class="selected"' : "" ?> data-click="setSortOption" data-args='["category_id"]'
            id="sort-category_id"><?= translate('category', $i18n) ?></li>
        <li <?= $sortOrder == "payment_method_id" ? 'class="selected"' : "" ?> data-click="setSortOption" data-args='["payment_method_id"]'
            id="sort-payment_method_id">
            <?= translate('payment_method', $i18n) ?>
        </li>
        <?php
        if (!isset($settings['hideDisabledSubscriptions']) || $settings['hideDisabledSubscriptions'] !== 'true') {
            ?>
            <li <?= $sortOrder == "inactive" ? 'class="selected"' : "" ?> data-click="setSortOption" data-args='["inactive"]'
                id="sort-inactive"><?= translate('state', $i18n) ?></li>
            <?php
        }
        ?>
        <li <?= $sortOrder == "alphanumeric" ? 'class="selected"' : "" ?> data-click="setSortOption" data-args='["alphanumeric"]'
            id="sort-alphanumeric"><?= translate('alphanumeric', $i18n) ?></li>
        <li <?= $sortOrder == "renewal_type" ? 'class="selected"' : "" ?> data-click="setSortOption" data-args='["renewal_type"]'
            id="sort-renewal_type"><?= translate('renewal_type', $i18n) ?></li>
    </ul>
</div>
