<?php

$columnCheck = $db->querySingle("SELECT COUNT(*) FROM pragma_table_info('subscriptions') WHERE name='adjust_to_working_day'");

if ($columnCheck == 0) {
    $db->exec("ALTER TABLE subscriptions ADD COLUMN adjust_to_working_day INTEGER DEFAULT 0");
}
