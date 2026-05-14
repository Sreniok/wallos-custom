<?php

$columns = [
    'first_notification_email'      => 'INTEGER DEFAULT 1',
    'first_notification_ntfy'       => 'INTEGER DEFAULT 1',
    'first_notification_webhook'    => 'INTEGER DEFAULT 1',
    'first_notification_discord'    => 'INTEGER DEFAULT 1',
    'first_notification_telegram'   => 'INTEGER DEFAULT 1',
    'first_notification_gotify'     => 'INTEGER DEFAULT 1',
    'first_notification_pushover'   => 'INTEGER DEFAULT 1',
    'first_notification_pushplus'   => 'INTEGER DEFAULT 1',
    'first_notification_mattermost' => 'INTEGER DEFAULT 1',
    'first_notification_serverchan' => 'INTEGER DEFAULT 1',
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
