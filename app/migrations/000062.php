<?php
// Per-user secret used to sign one-click action tokens embedded in
// notification emails (e.g., "Mark as paid"). Generated lazily on first
// use; users can rotate it from settings to invalidate outstanding links.

$columnQuery = $db->query("SELECT * FROM pragma_table_info('user') WHERE name='notification_action_secret'");
if ($columnQuery->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec("ALTER TABLE user ADD COLUMN notification_action_secret TEXT");
}
