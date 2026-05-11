<?php
// Add global adjust_to_working_day toggle to settings table.
// Data migration note: this intentionally flips existing subscription rows
// from 0 to 1 so they keep following the global working-day behavior after
// the meaning of the per-subscription flag changed in this release.

$columnQuery = $db->query("SELECT * FROM pragma_table_info('settings') where name='adjust_to_working_day'");
if ($columnQuery->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec("ALTER TABLE settings ADD COLUMN adjust_to_working_day BOOLEAN DEFAULT 0");
}

// Flip all subscriptions to 1 (= "not excluded from global").
// Previously 0 meant "opt-in off"; now 0 means "explicitly excluded".
$db->exec("UPDATE subscriptions SET adjust_to_working_day = 1 WHERE adjust_to_working_day = 0");
