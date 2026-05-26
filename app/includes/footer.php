</main>
<?php
$footerCurrencies = [];
$footerFuelVehicles = [];
$showExpenseTools = isset($db, $userId) && $userId;
if ($showExpenseTools) {
  $currencyStmt = $db->prepare("SELECT id, name, symbol, code FROM currencies WHERE user_id = :userId ORDER BY name ASC");
  $currencyStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
  $currencyResult = $currencyStmt->execute();
  while ($currencyRow = $currencyResult->fetchArray(SQLITE3_ASSOC)) {
    $footerCurrencies[] = $currencyRow;
  }

  $vehicleTableResult = $db->querySingle("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'fuel_vehicles'");
  if ($vehicleTableResult) {
    $vehicleStmt = $db->prepare("SELECT id, name FROM fuel_vehicles WHERE user_id = :userId ORDER BY name ASC");
    $vehicleStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $vehicleResult = $vehicleStmt->execute();
    while ($vehicleRow = $vehicleResult->fetchArray(SQLITE3_ASSOC)) {
      $footerFuelVehicles[] = $vehicleRow;
    }
  }
}
$fuelUnitSystem = isset($settings['fuelUnitSystem']) ? $settings['fuelUnitSystem'] : 'eu';
$fuelUnit = $fuelUnitSystem === 'us' ? 'gal_us' : 'l';
?>

<?php if ($showExpenseTools): ?>
<div id="quickAddMenu" class="quick-add-menu" hidden>
  <button type="button" data-click="quickAddSubscription">
    <i class="fa-solid fa-circle-plus"></i>
    <?= translate('new_subscription', $i18n) ?>
  </button>
  <button type="button" data-click="openPetrolExpenseModal">
    <i class="fa-solid fa-gas-pump"></i>
    <?= translate('add_petrol', $i18n) ?>
  </button>
</div>

<section class="subscription-form expense-form" id="petrolExpenseModal" role="dialog" aria-modal="true" aria-labelledby="petrolExpenseTitle" tabindex="-1">
  <header>
    <h3 id="petrolExpenseTitle"><?= translate('add_petrol', $i18n) ?></h3>
    <span class="fa-solid fa-xmark close-form" role="button" tabindex="0" aria-label="Close"
      data-click="closePetrolExpenseModal" data-keydown="closePetrolExpenseModal" data-keys="Enter,Space" data-prevent-default="true"></span>
  </header>
  <form id="petrolExpenseForm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="category" value="fuel">
    <input type="hidden" name="name" value="<?= htmlspecialchars(translate('petrol', $i18n), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="unit" id="petrolUnit" value="<?= htmlspecialchars($fuelUnit, ENT_QUOTES, 'UTF-8') ?>">

    <div class="form-group">
      <select id="petrolVehicle" name="vehicle_id" <?= count($footerFuelVehicles) === 0 ? 'disabled' : '' ?>>
        <?php if (count($footerFuelVehicles) === 0): ?>
          <option value=""><?= translate('create_petrol_vehicle_first', $i18n) ?></option>
        <?php else: ?>
          <?php foreach ($footerFuelVehicles as $vehicle): ?>
            <option value="<?= (int) $vehicle['id'] ?>">
              <?= htmlspecialchars($vehicle['name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach ?>
        <?php endif ?>
      </select>
    </div>

    <div class="form-group-inline">
      <input type="number" step="0.01" min="0" id="petrolAmount" name="amount"
        placeholder="<?= translate('amount_paid', $i18n) ?>" data-input="updatePetrolUnitPrice" required>
      <select id="petrolCurrency" name="currency_id">
        <?php foreach ($footerCurrencies as $currency): ?>
          <option value="<?= (int) $currency['id'] ?>" <?= (int) $currency['id'] === (int) $userData['main_currency'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($currency['name'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach ?>
      </select>
    </div>

    <div class="form-group-inline">
      <input type="number" step="0.001" min="0" id="petrolQuantity" name="quantity"
        placeholder="<?= $fuelUnit === 'gal_us' ? translate('gallons', $i18n) : translate('litres', $i18n) ?>"
        data-input="updatePetrolUnitPrice" required>
      <input type="date" id="petrolExpenseDate" name="expense_date" value="<?= date('Y-m-d') ?>" required>
    </div>

    <div class="petrol-unit-price-preview" id="petrolUnitPricePreview">
      <?= translate('unit_price_will_calculate', $i18n) ?>
    </div>

    <div class="form-group">
      <textarea id="petrolNotes" name="notes" placeholder="<?= translate('notes', $i18n) ?>"></textarea>
    </div>

    <div class="buttons">
      <input type="button" value="<?= translate('cancel', $i18n) ?>" class="secondary-button thin"
        data-click="closePetrolExpenseModal">
      <input type="submit" value="<?= translate('save', $i18n) ?>" class="thin" id="savePetrolExpense">
    </div>
  </form>
</section>

<section class="subscription-modal fuel-history-modal" id="fuelVehicleHistoryModal" role="dialog" aria-modal="true" aria-labelledby="fuelVehicleHistoryTitle" tabindex="-1">
  <header>
    <h3 id="fuelVehicleHistoryTitle"><?= translate('petrol_history', $i18n) ?></h3>
    <span class="fa-solid fa-xmark close-form" role="button" tabindex="0" aria-label="Close"
      data-click="closeFuelVehicleHistory" data-keydown="closeFuelVehicleHistory" data-keys="Enter,Space" data-prevent-default="true"></span>
  </header>
  <div class="fuel-history-nav">
    <button type="button" class="secondary-button thin" data-click="changeFuelHistoryPeriod" data-args="[-1]">
      <i class="fa-solid fa-chevron-left"></i>
    </button>
    <button type="button" class="secondary-button thin" data-click="changeFuelHistoryPeriod" data-args="[1]">
      <i class="fa-solid fa-chevron-right"></i>
    </button>
  </div>
  <div class="fuel-history-summary" id="fuelVehicleHistorySummary"></div>
  <div class="fuel-history-list" id="fuelVehicleHistoryList"></div>
</section>
<?php endif ?>

<div class="toast" id="errorToast">
  <div class="toast-content">
    <i class="fas fa-solid fa-x toast-icon error"></i>
    <div class="message">
      <span class="text text-1"><?= translate("error", $i18n) ?></span>
      <span class="text text-2 errorMessage"></span>
    </div>
  </div>
  <i class="fa-solid fa-xmark close close-error"></i>
  <div class="progress error"></div>
</div>

<div class="toast" id="successToast">
  <div class="toast-content">
    <i class="fas fa-solid fa-check toast-icon success"></i>
    <div class="message">
      <span class="text text-1"><?= translate("success", $i18n) ?></span>
      <span class="text text-2 successMessage"></span>
    </div>
  </div>
  <i class="fa-solid fa-xmark close close-success"></i>
  <div class="progress success"></div>
</div>

<?php if ($showExpenseTools): ?>
  <script>
    window.fuelUnitSystem = "<?= htmlspecialchars($fuelUnitSystem, ENT_QUOTES, 'UTF-8') ?>";
  </script>
  <script src="scripts/expenses.js?<?= $version ?>"></script>
<?php endif ?>

<?php
if (isset($db)) {
  $db->close();
}
?>

</body>

</html>
