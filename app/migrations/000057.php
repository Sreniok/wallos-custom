<?php

$db->exec("CREATE TABLE IF NOT EXISTS expenses (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL,
    category TEXT NOT NULL,
    name TEXT NOT NULL,
    amount REAL NOT NULL,
    currency_id INTEGER NOT NULL,
    expense_date DATE NOT NULL,
    quantity REAL,
    unit TEXT,
    unit_price REAL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES user(id) ON DELETE CASCADE,
    FOREIGN KEY(currency_id) REFERENCES currencies(id)
)");

$existingColumns = [];
$result = $db->query("PRAGMA table_info(settings)");
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $existingColumns[] = $row['name'];
}

if (!in_array('fuel_unit_system', $existingColumns, true)) {
    $db->exec("ALTER TABLE settings ADD COLUMN fuel_unit_system TEXT DEFAULT 'eu'");
}

?>
