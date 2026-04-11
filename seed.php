<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

$db = getDB();

echo "Initializing database seed...\n";

// Clear existing tables
$db->exec("SET FOREIGN_KEY_CHECKS = 0;");
$db->exec("TRUNCATE TABLE carbon_offsets;");
$db->exec("TRUNCATE TABLE net_metering;");
$db->exec("TRUNCATE TABLE forecasts;");
$db->exec("TRUNCATE TABLE energy_readings;");
$db->exec("TRUNCATE TABLE devices;");
$db->exec("TRUNCATE TABLE users;");
$db->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "Tables cleared.\n";

// Seed user
$pass = password_hash('password', PASSWORD_BCRYPT);
$db->exec("INSERT INTO users (id, name, email, password_hash, location_lat, location_lon, city, tariff_rate, panel_capacity_kw, battery_capacity_kwh, avatar)
VALUES (1, 'Arjun Sharma', 'demo@helios.com', '{$pass}', 28.6139, 77.2090, 'New Delhi', 0.08, 5.50, 10.00, '☀️')");

echo "Demo user inserted.\n";

// Seed devices
$db->exec("INSERT INTO devices (user_id, name, category, priority, power_watts, is_on, is_essential, icon, location) VALUES
(1, 'Refrigerator',       'Kitchen',    1, 150,  1, 1, '🧊', 'Kitchen'),
(1, 'Wi-Fi Router',       'Network',    2, 20,   1, 1, '📡', 'Living Room'),
(1, 'LED Lights',         'Lighting',   3, 60,   1, 1, '💡', 'All Rooms'),
(1, 'Air Conditioner',    'HVAC',       4, 2000, 1, 0, '❄️', 'Bedroom'),
(1, 'Washing Machine',    'Appliance',  5, 500,  0, 0, '🫧', 'Utility'),
(1, 'EV Charger',         'Transport',  6, 7400, 0, 0, '🔋', 'Garage'),
(1, 'Water Heater',       'Appliance',  7, 2000, 0, 0, '🚿', 'Bathroom'),
(1, 'Microwave',          'Kitchen',    8, 1100, 0, 0, '🍽️', 'Kitchen'),
(1, 'Television',         'Multimedia', 9, 120,  1, 0, '📺', 'Living Room'),
(1, 'Laptop Charger',     'Electronics',10, 65,  1, 0, '💻', 'Study')");

echo "Devices inserted.\n";

// Seed 90 days of energy readings
echo "Seeding 90 days of energy readings (this may take a few seconds)...\n";
$stmt = $db->prepare("INSERT INTO energy_readings (user_id, solar_kw, consumption_kw, battery_pct, grid_export_kw, grid_import_kw, temperature, cloud_cover, recorded_at) VALUES (?,?,?,?,?,?,?,?,?)");

for ($d = 0; $d < 90; $d++) {
    for ($h = 0; $h < 24; $h++) {
        $ts = date('Y-m-d H:i:s', strtotime("-" . ($d * 24 - $h) . " hours"));
        
        // Solar generation curve
        if ($h >= 6 && $h <= 18) {
            $solar_val = round((sin(($h - 6) / 12.0 * pi()) * 4.5 * (0.6 + mt_rand(0, 50)/100)), 3);
        } else {
            $solar_val = 0.000;
        }

        // Consumption curve
        if ($h >= 6 && $h <= 9) {
            $cons_val = round(1.5 + mt_rand(0, 150)/100, 3);
        } elseif ($h >= 17 && $h <= 22) {
            $cons_val = round(2.0 + mt_rand(0, 250)/100, 3);
        } elseif ($h >= 23 || $h <= 5) {
            $cons_val = round(0.3 + mt_rand(0, 40)/100, 3);
        } else {
            $cons_val = round(0.8 + mt_rand(0, 100)/100, 3);
        }

        $batt = round(40 + mt_rand(0, 5500)/100, 2);

        if ($solar_val > $cons_val) {
            $export_val = round($solar_val - $cons_val, 3);
            $import_val = 0.000;
        } else {
            $export_val = 0.000;
            $import_val = round($cons_val - $solar_val, 3);
        }

        $temp_val = round(22 + mt_rand(0, 1800)/100, 2);
        $cloud_val = mt_rand(0, 80);

        $stmt->execute([1, $solar_val, $cons_val, $batt, $export_val, $import_val, $temp_val, $cloud_val, $ts]);
    }
}
echo "Energy readings inserted.\n";


// Net metering 12 months
$db->exec("INSERT INTO net_metering (user_id, month_year, exported_kwh, imported_kwh, credit_amount, tariff_rate, net_savings) VALUES
(1, '2025-04', 187.50, 42.30,  15.00, 0.08, 28.50),
(1, '2025-05', 210.80, 38.10,  16.86, 0.08, 32.10),
(1, '2025-06', 195.20, 55.40,  15.62, 0.08, 27.40),
(1, '2025-07', 180.60, 68.20,  14.45, 0.08, 22.80),
(1, '2025-08', 172.30, 72.10,  13.78, 0.08, 21.10),
(1, '2025-09', 188.90, 48.30,  15.11, 0.08, 26.50),
(1, '2025-10', 220.40, 35.70,  17.63, 0.08, 33.90),
(1, '2025-11', 165.20, 88.40,  13.22, 0.08, 19.70),
(1, '2025-12', 142.80, 110.50, 11.42, 0.08, 15.60),
(1, '2026-01', 155.60, 95.30,  12.45, 0.08, 17.90),
(1, '2026-02', 178.30, 62.40,  14.26, 0.08, 24.30),
(1, '2026-03', 205.70, 44.80,  16.46, 0.08, 30.20)");


// Carbon offsets 30 days
$cStmt = $db->prepare("INSERT INTO carbon_offsets (user_id, offset_date, solar_kwh, co2_saved_kg, coal_equivalent_kg, trees_equivalent) VALUES (?,?,?,?,?,?)");
for ($d = 0; $d < 30; $d++) {
    $cdate = date('Y-m-d', strtotime("-$d days"));
    $skwh  = round(12 + mt_rand(0, 1800)/100, 3);
    $co2   = round($skwh * 0.82, 3);
    
    $cStmt->execute([1, $cdate, $skwh, $co2, round($co2 * 0.34, 3), round($co2 / 21.77, 3)]);
}

echo "Database successfully seeded via PHP!\n";
