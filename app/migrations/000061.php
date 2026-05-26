<?php

$existingFuelVehicleColumns = [];
$result = $db->query("PRAGMA table_info(fuel_vehicles)");
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $existingFuelVehicleColumns[] = $row['name'];
}

if (!in_array('logo_url', $existingFuelVehicleColumns, true)) {
    $db->exec("ALTER TABLE fuel_vehicles ADD COLUMN logo_url TEXT");
}

?>
