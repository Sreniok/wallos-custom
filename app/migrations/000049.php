<?php
$columnQuery = $db->query("SELECT * FROM pragma_table_info('settings') where name='design_theme'");
if ($columnQuery->fetchArray(SQLITE3_ASSOC) === false) {
    $db->exec("ALTER TABLE settings ADD COLUMN design_theme TEXT DEFAULT ''");
}
