<?php
require_once 'includes/header.php';
require_once 'includes/subscription_dates.php';
require_once 'includes/budget_cycles.php';

// Get budget from user table
$query = "SELECT budget, budget_cycle, payroll_schedule_type, payroll_fixed_day, payroll_fixed_day_2, payroll_weekday, payroll_ordinal, payroll_anchor_date FROM user WHERE id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
$budget = $row['budget'] ?? 0;
$budgetData = array_merge($userData, $row ?: []);

$currentMonth = date('m');
$currentYear = date('Y');
$sameAsCurrent = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['month']) && isset($_GET['year'])) {
  $calendarMonth = str_pad($_GET['month'], 2, '0', STR_PAD_LEFT);
  $calendarYear = $_GET['year'];

  if ($calendarMonth == $currentMonth && $calendarYear == $currentYear) {
    $sameAsCurrent = true;
  }
} else {
  $calendarMonth = $currentMonth;
  $calendarYear = $currentYear;
  $sameAsCurrent = true;
}

$currenciesInUse = [];
$numberOfSubscriptionsToPayThisMonth = 0;
$totalCostThisMonth = 0;
$amountDueThisMonth = 0;

$query = "SELECT * FROM subscriptions WHERE user_id = :user_id AND inactive = 0";
$stmt = $db->prepare($query);
$stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$subscriptions = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
  $subscriptions[] = $row;
  $currenciesInUse[] = $row['currency_id'];
}

$currenciesInUse = array_unique($currenciesInUse);
$usesMultipleCurrencies = count($currenciesInUse) > 1;

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

// Get code of main currency to display on statistics
$query = "SELECT c.code
          FROM currencies c
          INNER JOIN user u ON c.id = u.main_currency
          WHERE u.id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
$code = $row['code'];

$yearsToLoad = $calendarYear - $currentYear + 1;

$todayParts = explode('-', date('Y-m-d'));
$todayYear = $todayParts[0];
$todayMonth = $todayParts[1];
$todayDay = $todayParts[2];
$today = strtotime($todayYear . '-' . $todayMonth . '-' . $todayDay);

// Precompute payments grouped by day-of-month for the calendar month being viewed.
$paymentsByDay = [];
$startOfMonth = new DateTimeImmutable($calendarYear . '-' . str_pad($calendarMonth, 2, '0', STR_PAD_LEFT) . '-01');
$endOfMonth = $startOfMonth->modify('last day of this month');

foreach ($subscriptions as $subscription) {
  $occurrences = getSubscriptionOccurrencesInRange($subscription, $startOfMonth, $endOfMonth);
  if (empty($occurrences)) {
    continue;
  }
  $convertedPrice = getPriceConverted($subscription['price'], $subscription['currency_id'], $db, $userId);
  foreach ($occurrences as $occ) {
    $dayNum = (int) substr($occ, 8, 2);
    $paymentsByDay[$dayNum][] = [
      'id'    => $subscription['id'],
      'name'  => $subscription['name'],
      'price' => $convertedPrice,
      'date'  => $occ,
    ];
    $totalCostThisMonth += $convertedPrice;
    $numberOfSubscriptionsToPayThisMonth++;
    if (strtotime($occ) > $today) {
      $amountDueThisMonth += $convertedPrice;
    }
  }
}
?>

<section class="contain">
  <?php
  if ($showCantConverErrorMessage) {
    ?>
    <div class="error-box">
      <div class="error-message">
        <i class="fa-solid fa-exclamation-circle"></i>
        <?= translate('cant_convert_currency', $i18n) ?>
      </div>
    </div>
    <?php
  }
  ?>
  <div class="split-header">
    <h2><?= translate('calendar', $i18n) ?></h2>

    <div class="calendar-nav">
      <?php
      if (!$sameAsCurrent) {
        ?>
        <button class="button secondary-button tiny" data-click="currentMoth" aria-label="<?= translate('reset', $i18n) ?>" title="<?= translate('reset', $i18n) ?>"><i
            class="fa-solid fa-calendar-day"></i></button>
        <?php
      }
      ?>
      <button class="button tiny" id="prev" aria-label="<?= translate('previous_month', $i18n) ?>" data-click="prevMonth" data-args='[<?= $calendarMonth ?>, <?= $calendarYear ?>]'><i
          class="fa-solid fa-chevron-left"></i></button>
      <span id="month" class="month"><?= translate('month-' . $calendarMonth, $i18n) ?> <?= $calendarYear ?></span>
      <button class="button tiny" id="next" aria-label="<?= translate('next_month', $i18n) ?>" data-click="nextMonth" data-args='[<?= $calendarMonth ?>, <?= $calendarYear ?>]'><i
          class="fa-solid fa-chevron-right"></i></button>
    </div>
  </div>
  <div>
    <?php
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $calendarMonth, $calendarYear);
    $firstDay = mktime(0, 0, 0, $calendarMonth, 1, $calendarYear);
    $firstDayOfWeek = date('N', $firstDay) - 1; // Adjusted to make Monday (1) the first day
    $dayOfWeek = 0;
    $day = 1;

    $renderDayCell = function ($day) use ($paymentsByDay, $calendarYear, $calendarMonth, $todayYear, $todayMonth, $todayDay, $today, $code, $i18n, $lang) {
      $isToday = ($day == (int)$todayDay && $calendarMonth == $todayMonth && $calendarYear == $todayYear);
      $cellDate = $calendarYear . '-' . str_pad($calendarMonth, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
      $cellDateTs = strtotime($cellDate);
      $isPast = $cellDateTs < $today;
      $payments = $paymentsByDay[$day] ?? [];
      $hasPayments = !empty($payments);
      $classes = [];
      if ($isToday) $classes[] = 'today';
      if ($isPast && !$isToday) $classes[] = 'past';
      if ($hasPayments) $classes[] = 'has-payments';
      $cls = implode(' ', $classes);
      $dayTotal = 0;
      foreach ($payments as $p) $dayTotal += $p['price'];
      $dayLabel = formatDate($cellDate, $lang);
      $dayLabelAttr = htmlspecialchars($dayLabel, ENT_QUOTES, 'UTF-8');
      $cellAttrs = $hasPayments
        ? ' data-click="openDayModal" data-args=\'["' . $dayLabelAttr . '"]\''
        : '';
      ?>
      <div class="calendar-cell <?= $cls ?>" tabindex="0"<?= $cellAttrs ?>>
        <div class="calendar-cell-header">
          <span class="day"><?= $day ?></span>
          <?php if ($hasPayments && count($payments) > 1): ?>
            <span class="day-count" aria-hidden="true"><?= count($payments) ?></span>
          <?php endif; ?>
        </div>
        <div class="calendar-cell-content">
          <?php foreach ($payments as $p): ?>
            <button type="button" class="calendar-subscription-title" data-click="openSubscriptionModal" data-args='[<?= $p['id'] ?>]' aria-label="<?= translate('open_subscription', $i18n) ?>: <?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>">
              <span class="sub-name"><?= htmlspecialchars($p['name']) ?></span>
              <span class="sub-price"><?= CurrencyFormatter::format($p['price'], $code) ?></span>
            </button>
          <?php endforeach; ?>
          <?php if (count($payments) > 3): ?>
            <button type="button" class="calendar-more" data-click="openDayModal" data-args='["<?= $dayLabelAttr ?>"]'>+<?= count($payments) - 3 ?> <?= translate('more', $i18n) ?></button>
          <?php endif; ?>
        </div>
        <?php if ($hasPayments): ?>
          <div class="calendar-cell-total" aria-label="<?= translate('total_cost', $i18n) ?>"><?= CurrencyFormatter::format($dayTotal, $code) ?></div>
        <?php endif; ?>
      </div>
      <?php
    };
    ?>

    <div class="calendar">
      <div class="calendar-header">
        <div class="calendar-cell"><?= translate('mon', $i18n) ?></div>
        <div class="calendar-cell"><?= translate('tue', $i18n) ?></div>
        <div class="calendar-cell"><?= translate('wed', $i18n) ?></div>
        <div class="calendar-cell"><?= translate('thu', $i18n) ?></div>
        <div class="calendar-cell"><?= translate('fri', $i18n) ?></div>
        <div class="calendar-cell"><?= translate('sat', $i18n) ?></div>
        <div class="calendar-cell"><?= translate('sun', $i18n) ?></div>
      </div>
      <div class="calendar-body">
        <div class="week calendar-row">
          <?php
          for ($i = 0; $i < $firstDayOfWeek; $i++) { // Fill empty cells if month doesn't start on Monday
            ?>
            <div class="calendar-cell empty">
              <div class="calendar-cell-header">
                <span class="day">&nbsp;</span>
              </div>
              <div class="calendar-cell-content"></div>
            </div>
            <?php
          }
          for ($i = $firstDayOfWeek; $i < 7; $i++) {
            if ($day <= $daysInMonth) {
              $renderDayCell($day);
              $day++;
            }
          }
          while ($day <= $daysInMonth) {
            if ($dayOfWeek % 7 == 0) {
              ?>
            </div>
            <div class="week calendar-row">
              <?php
            }
            $renderDayCell($day);
            $day++;
            $dayOfWeek++;
          }
          while ($dayOfWeek % 7 != 0) { // Fill the rest of the week with empty cells
            ?>
            <div class="calendar-cell empty">
              <div class="calendar-cell-header">
                <span class="day">&nbsp;</span>
              </div>
              <div class="calendar-cell-content"></div>
            </div>
            <?php
            $dayOfWeek++;
          }
          ?>
        </div>
      </div>
    </div>

    <?php
      if ($numberOfSubscriptionsToPayThisMonth === 0) {
        ?>
          <div class="mf-empty-state">
            <i class="fa-solid fa-calendar-xmark"></i>
            <p><?= translate('no_payments_this_month', $i18n) ?></p>
          </div>
        <?php
      }
    ?>

    <?php
      $budgetComparisonCost = $totalCostThisMonth;
      if ($budget > 0 && wallosUsesPayrollBudgetCycle($budgetData) && $sameAsCurrent) {
        $payrollPeriod = wallosGetPayrollPeriod($budgetData);
        $budgetComparisonCost = 0;
        foreach ($subscriptions as $subscription) {
          $occurrences = getSubscriptionOccurrencesInRange($subscription, $payrollPeriod['start'], $payrollPeriod['end']);
          $budgetComparisonCost += count($occurrences) * getPriceConverted($subscription['price'], $subscription['currency_id'], $db, $userId);
        }
        $expenseStmt = $db->prepare("SELECT amount, currency_id FROM expenses WHERE user_id = :userId AND expense_date >= :startDate AND expense_date <= :endDate");
        $expenseStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $expenseStmt->bindValue(':startDate', $payrollPeriod['start']->format('Y-m-d'), SQLITE3_TEXT);
        $expenseStmt->bindValue(':endDate', $payrollPeriod['end']->format('Y-m-d'), SQLITE3_TEXT);
        $expenseResult = $expenseStmt->execute();
        while ($expense = $expenseResult->fetchArray(SQLITE3_ASSOC)) {
          $budgetComparisonCost += getPriceConverted($expense['amount'], $expense['currency_id'], $db, $userId);
        }
      } elseif ($budget > 0) {
        $expenseStart = $calendarYear . '-' . str_pad($calendarMonth, 2, '0', STR_PAD_LEFT) . '-01';
        $expenseEnd = (new DateTimeImmutable($expenseStart))->modify('last day of this month')->format('Y-m-d');
        $expenseStmt = $db->prepare("SELECT amount, currency_id FROM expenses WHERE user_id = :userId AND expense_date >= :startDate AND expense_date <= :endDate");
        $expenseStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $expenseStmt->bindValue(':startDate', $expenseStart, SQLITE3_TEXT);
        $expenseStmt->bindValue(':endDate', $expenseEnd, SQLITE3_TEXT);
        $expenseResult = $expenseStmt->execute();
        while ($expense = $expenseResult->fetchArray(SQLITE3_ASSOC)) {
          $budgetComparisonCost += getPriceConverted($expense['amount'], $expense['currency_id'], $db, $userId);
        }
      }

      if ($budget > 0 && $budgetComparisonCost > $budget) {
        $overBudgetAmount = $budgetComparisonCost - $budget;
        $overBudgetAmount = CurrencyFormatter::format($overBudgetAmount, $code);
        ?>
          <div class="over-budget">
            <i class="fa-solid fa-exclamation-triangle"></i>
            <?= translate('over_budget_warning', $i18n) ?>  (<?= $overBudgetAmount ?>)
          </div>
        <?php
      }
    ?>    

    <div class="calendar-monthly-stats">
      <div class="calendar-monthly-stats-header">
        <h3><?= translate("stats", $i18n) ?></h3>
      </div>
      <div class="statistics">
        <div class="statistic">
          <span>
            <?= $numberOfSubscriptionsToPayThisMonth ?></span>
          <div class="title"><?= translate("active_subscriptions", $i18n) ?></div>
        </div>
        <div class="statistic">
          <span><?= CurrencyFormatter::format($totalCostThisMonth, $code) ?></span>
          <div class="title"><?= translate("total_cost", $i18n) ?></div>
        </div>
        <div class="statistic">
          <span><?= CurrencyFormatter::format($amountDueThisMonth, $code) ?></span>
          <div class="title"><?= translate("amount_due", $i18n) ?></div>
        </div>
      </div>
    </div>

</section>

<div id="subscriptionModal" class="subscription-modal">
  <div class="modal-content">
    <div id="subscriptionModalContent"></div>
  </div>
</div>

<script src="scripts/calendar.js?<?= $version ?>"></script>
<?php
require_once 'includes/footer.php';
?>
