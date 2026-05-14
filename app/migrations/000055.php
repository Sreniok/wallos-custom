<?php

$existingColumns = [];
$result = $db->query("PRAGMA table_info(notification_settings)");
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $existingColumns[] = $row['name'];
}

if (!in_array('first_notification_enabled', $existingColumns, true)) {
    $db->exec("ALTER TABLE notification_settings ADD COLUMN first_notification_enabled INTEGER DEFAULT 1");
}
