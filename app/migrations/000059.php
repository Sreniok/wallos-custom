<?php

$existingFuelVehicleColumns = [];
$result = $db->query("PRAGMA table_info(fuel_vehicles)");
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $existingFuelVehicleColumns[] = $row['name'];
}

if (!in_array('payer_user_id', $existingFuelVehicleColumns, true)) {
    $db->exec("ALTER TABLE fuel_vehicles ADD COLUMN payer_user_id INTEGER");
}

?>
