<?php
// Daily digest notification mode: when enabled, replaces per-subscription
// reminders with a single email per day listing everything due in the next
// `digest_horizon_days` days.

$result = $db->query("PRAGMA table_info(notification_settings)");
$existingColumns = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $existingColumns[] = $row['name'];
}

$columns = [
    'digest_mode_enabled' => 'INTEGER DEFAULT 0',
    'digest_horizon_days' => 'INTEGER DEFAULT 7',
];

foreach ($columns as $column => $definition) {
    if (!in_array($column, $existingColumns, true)) {
        $db->exec("ALTER TABLE notification_settings ADD COLUMN {$column} {$definition}");
    }
}
