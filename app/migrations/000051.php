<?php
// Adds private calendar feed settings to each user account.

$columnQuery = $db->query("SELECT * FROM pragma_table_info('user') WHERE name='ical_token'");
if ($columnQuery->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec("ALTER TABLE user ADD COLUMN ical_token TEXT");
}

$columnQuery = $db->query("SELECT * FROM pragma_table_info('user') WHERE name='ical_enabled'");
if ($columnQuery->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec("ALTER TABLE user ADD COLUMN ical_enabled INTEGER DEFAULT 0");
}

