<?php

$columns = [
    'second_notification_enabled' => 'INTEGER DEFAULT 0',
    'second_notification_days' => 'INTEGER DEFAULT 0',
    'second_notification_email' => 'INTEGER DEFAULT 1',
    'second_notification_ntfy' => 'INTEGER DEFAULT 1',
];

$existingColumns = [];
$result = $db->query("PRAGMA table_info(notification_settings)");
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $existingColumns[] = $row['name'];
}

foreach ($columns as $column => $definition) {
    if (!in_array($column, $existingColumns, true)) {
        $db->exec("ALTER TABLE notification_settings ADD COLUMN {$column} {$definition}");
    }
}

?>
