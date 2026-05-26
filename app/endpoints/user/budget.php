<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);
if (!is_array($data)) {
    $data = [];
}

$budget = $data["budget"] ?? 0;
$budgetCycle = in_array($data["budget_cycle"] ?? "calendar_month", ["calendar_month", "payroll"], true)
    ? $data["budget_cycle"]
    : "calendar_month";
$payrollScheduleType = in_array($data["payroll_schedule_type"] ?? "monthly_weekday_rule", ["monthly_fixed_day", "monthly_weekday_rule", "biweekly", "semi_monthly"], true)
    ? $data["payroll_schedule_type"]
    : "monthly_weekday_rule";
$payrollFixedDay = max(1, min(31, (int) ($data["payroll_fixed_day"] ?? 1)));
$payrollFixedDay2 = max(1, min(31, (int) ($data["payroll_fixed_day_2"] ?? 15)));
$payrollWeekday = max(1, min(7, (int) ($data["payroll_weekday"] ?? 4)));
$payrollOrdinal = in_array($data["payroll_ordinal"] ?? "second_last", ["first", "second", "third", "fourth", "last", "second_last"], true)
    ? $data["payroll_ordinal"]
    : "second_last";
$payrollAnchorDate = $data["payroll_anchor_date"] ?? null;
$parsedPayrollAnchorDate = $payrollAnchorDate !== null && $payrollAnchorDate !== ""
    ? DateTime::createFromFormat('!Y-m-d', $payrollAnchorDate)
    : false;
if ($payrollAnchorDate !== null && $payrollAnchorDate !== "" && (!$parsedPayrollAnchorDate || $parsedPayrollAnchorDate->format('Y-m-d') !== $payrollAnchorDate)) {
    $payrollAnchorDate = null;
}

$sql = "UPDATE user
        SET budget = :budget,
            budget_cycle = :budgetCycle,
            payroll_schedule_type = :payrollScheduleType,
            payroll_fixed_day = :payrollFixedDay,
            payroll_fixed_day_2 = :payrollFixedDay2,
            payroll_weekday = :payrollWeekday,
            payroll_ordinal = :payrollOrdinal,
            payroll_anchor_date = :payrollAnchorDate
        WHERE id = :userId";
$stmt = $db->prepare($sql);
$stmt->bindValue(':budget', $budget, SQLITE3_TEXT);
$stmt->bindValue(':budgetCycle', $budgetCycle, SQLITE3_TEXT);
$stmt->bindValue(':payrollScheduleType', $payrollScheduleType, SQLITE3_TEXT);
$stmt->bindValue(':payrollFixedDay', $payrollFixedDay, SQLITE3_INTEGER);
$stmt->bindValue(':payrollFixedDay2', $payrollFixedDay2, SQLITE3_INTEGER);
$stmt->bindValue(':payrollWeekday', $payrollWeekday, SQLITE3_INTEGER);
$stmt->bindValue(':payrollOrdinal', $payrollOrdinal, SQLITE3_TEXT);
$stmt->bindValue(':payrollAnchorDate', $payrollAnchorDate, SQLITE3_TEXT);
$stmt->bindValue(':userId', $userId, SQLITE3_TEXT);
$result = $stmt->execute();

if ($result) {
    $response = [
        "success" => true,
        "message" => translate('user_details_saved', $i18n)
    ];
    echo json_encode($response);
} else {
    $response = [
        "success" => false,
        "message" => translate('error_updating_user_data', $i18n)
    ];
    echo json_encode($response);
}


?>
