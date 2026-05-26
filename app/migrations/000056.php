<?php

$columns = [
    'budget_cycle' => "TEXT DEFAULT 'calendar_month'",
    'payroll_schedule_type' => "TEXT DEFAULT 'monthly_weekday_rule'",
    'payroll_fixed_day' => 'INTEGER DEFAULT 1',
    'payroll_fixed_day_2' => 'INTEGER DEFAULT 15',
    'payroll_weekday' => 'INTEGER DEFAULT 4',
    'payroll_ordinal' => "TEXT DEFAULT 'second_last'",
    'payroll_anchor_date' => 'TEXT',
];

$existingColumns = [];
$result = $db->query("PRAGMA table_info(user)");
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $existingColumns[] = $row['name'];
}

foreach ($columns as $column => $definition) {
    if (!in_array($column, $existingColumns, true)) {
        $db->exec("ALTER TABLE user ADD COLUMN {$column} {$definition}");
    }
}

?>
