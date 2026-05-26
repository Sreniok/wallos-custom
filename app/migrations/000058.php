<?php

$db->exec("CREATE TABLE IF NOT EXISTS fuel_vehicles (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    make TEXT,
    model TEXT,
    registration TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES user(id) ON DELETE CASCADE
)");

$existingExpenseColumns = [];
$result = $db->query("PRAGMA table_info(expenses)");
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $existingExpenseColumns[] = $row['name'];
}

if (!in_array('vehicle_id', $existingExpenseColumns, true)) {
    $db->exec("ALTER TABLE expenses ADD COLUMN vehicle_id INTEGER");
}

?>
