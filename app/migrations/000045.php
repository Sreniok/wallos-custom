<?php

$columns = [];
$columnQuery = $db->query("PRAGMA table_info(subscriptions)");
while ($column = $columnQuery->fetchArray(SQLITE3_ASSOC)) {
    $columns[] = $column['name'];
}

if (!in_array('regular_price', $columns, true)) {
    $db->exec('ALTER TABLE subscriptions ADD COLUMN regular_price REAL');
}

if (!in_array('last_payment_date', $columns, true)) {
    $db->exec('ALTER TABLE subscriptions ADD COLUMN last_payment_date DATE');
}

if (!in_array('ended_at', $columns, true)) {
    $db->exec('ALTER TABLE subscriptions ADD COLUMN ended_at DATE');
}

if (!in_array('completion_notified', $columns, true)) {
    $db->exec('ALTER TABLE subscriptions ADD COLUMN completion_notified INTEGER DEFAULT 0');
}
