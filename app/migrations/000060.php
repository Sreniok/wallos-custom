<?php

$existingFuelVehicleColumns = [];
$result = $db->query("PRAGMA table_info(fuel_vehicles)");
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $existingFuelVehicleColumns[] = $row['name'];
}

if (!in_array('fuel_type', $existingFuelVehicleColumns, true)) {
    $db->exec("ALTER TABLE fuel_vehicles ADD COLUMN fuel_type TEXT DEFAULT 'petrol'");
}

?>
