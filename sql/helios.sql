-- ============================================================
-- Helios – Smart Solar & Grid Optimizer
-- Database Schema + Seed Data
-- ============================================================

CREATE DATABASE IF NOT EXISTS helios_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE helios_db;

-- ------------------------------------------------------------
-- Table: users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    location_lat DECIMAL(10,6) DEFAULT 28.6139,
    location_lon DECIMAL(10,6) DEFAULT 77.2090,
    city VARCHAR(100) DEFAULT 'New Delhi',
    tariff_rate DECIMAL(6,4) DEFAULT 0.08,
    panel_capacity_kw DECIMAL(6,2) DEFAULT 5.00,
    battery_capacity_kwh DECIMAL(6,2) DEFAULT 10.00,
    avatar VARCHAR(10) DEFAULT '☀️',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: devices
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50) DEFAULT 'General',
    priority INT DEFAULT 5,
    power_watts INT DEFAULT 100,
    is_on TINYINT(1) DEFAULT 1,
    is_essential TINYINT(1) DEFAULT 0,
    icon VARCHAR(10) DEFAULT '🔌',
    location VARCHAR(50) DEFAULT 'Home',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: energy_readings (time-series data)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS energy_readings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    solar_kw DECIMAL(8,3) DEFAULT 0.000,
    consumption_kw DECIMAL(8,3) DEFAULT 0.000,
    battery_pct DECIMAL(5,2) DEFAULT 0.00,
    grid_export_kw DECIMAL(8,3) DEFAULT 0.000,
    grid_import_kw DECIMAL(8,3) DEFAULT 0.000,
    temperature DECIMAL(5,2) DEFAULT 25.00,
    cloud_cover INT DEFAULT 20,
    recorded_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_time (user_id, recorded_at)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: forecasts
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS forecasts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    forecast_date DATE NOT NULL,
    predicted_kwh DECIMAL(8,3) DEFAULT 0.000,
    cloud_cover_pct INT DEFAULT 0,
    temperature DECIMAL(5,2) DEFAULT 25.00,
    humidity INT DEFAULT 50,
    confidence_pct INT DEFAULT 80,
    recommendation TEXT,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: net_metering
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS net_metering (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    month_year VARCHAR(7) NOT NULL,
    exported_kwh DECIMAL(10,3) DEFAULT 0.000,
    imported_kwh DECIMAL(10,3) DEFAULT 0.000,
    credit_amount DECIMAL(10,2) DEFAULT 0.00,
    tariff_rate DECIMAL(6,4) DEFAULT 0.08,
    net_savings DECIMAL(10,2) DEFAULT 0.00,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: carbon_offsets
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS carbon_offsets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    offset_date DATE NOT NULL,
    solar_kwh DECIMAL(8,3) DEFAULT 0.000,
    co2_saved_kg DECIMAL(8,3) DEFAULT 0.000,
    coal_equivalent_kg DECIMAL(8,3) DEFAULT 0.000,
    trees_equivalent DECIMAL(8,3) DEFAULT 0.000,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Demo user (password: helios123)
INSERT INTO users (name, email, password_hash, location_lat, location_lon, city, tariff_rate, panel_capacity_kw, battery_capacity_kwh, avatar)
VALUES ('Arjun Sharma', 'demo@helios.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 28.6139, 77.2090, 'New Delhi', 0.08, 5.50, 10.00, '☀️');

-- Devices for user 1
INSERT INTO devices (user_id, name, category, priority, power_watts, is_on, is_essential, icon, location) VALUES
(1, 'Refrigerator',       'Kitchen',    1, 150,  1, 1, '🧊', 'Kitchen'),
(1, 'Wi-Fi Router',       'Network',    2, 20,   1, 1, '📡', 'Living Room'),
(1, 'LED Lights',         'Lighting',   3, 60,   1, 1, '💡', 'All Rooms'),
(1, 'Air Conditioner',    'HVAC',       4, 2000, 1, 0, '❄️', 'Bedroom'),
(1, 'Washing Machine',    'Appliance',  5, 500,  0, 0, '🫧', 'Utility'),
(1, 'EV Charger',         'Transport',  6, 7400, 0, 0, '🔋', 'Garage'),
(1, 'Water Heater',       'Appliance',  7, 2000, 0, 0, '🚿', 'Bathroom'),
(1, 'Microwave',          'Kitchen',    8, 1100, 0, 0, '🍽️', 'Kitchen'),
(1, 'Television',         'Multimedia', 9, 120,  1, 0, '📺', 'Living Room'),
(1, 'Laptop Charger',     'Electronics',10, 65,  1, 0, '💻', 'Study');

-- Energy readings: 90 days of hourly data (condensed as daily averages via stored procedure logic)
-- We insert representative hourly records for the last 30 days
-- (real app would stream from IoT sensors)

DELIMITER $$
CREATE PROCEDURE seed_energy_readings()
BEGIN
    DECLARE d INT DEFAULT 0;
    DECLARE h INT DEFAULT 0;
    DECLARE ts DATETIME;
    DECLARE solar_val DECIMAL(8,3);
    DECLARE cons_val DECIMAL(8,3);
    DECLARE batt DECIMAL(5,2);
    DECLARE export_val DECIMAL(8,3);
    DECLARE import_val DECIMAL(8,3);
    DECLARE temp_val DECIMAL(5,2);
    DECLARE cloud_val INT;

    WHILE d < 90 DO
        SET h = 0;
        WHILE h < 24 DO
            SET ts = DATE_SUB(NOW(), INTERVAL (d * 24 - h) HOUR);

            -- Solar generation curve (peak at noon)
            IF h >= 6 AND h <= 18 THEN
                SET solar_val = ROUND((SIN((h - 6) / 12.0 * 3.14159) * 4.5 * (0.6 + RAND() * 0.5)), 3);
            ELSE
                SET solar_val = 0.000;
            END IF;

            -- Consumption varies by time of day
            IF h >= 6 AND h <= 9 THEN
                SET cons_val = ROUND(1.5 + RAND() * 1.5, 3);
            ELSEIF h >= 17 AND h <= 22 THEN
                SET cons_val = ROUND(2.0 + RAND() * 2.5, 3);
            ELSEIF h >= 23 OR h <= 5 THEN
                SET cons_val = ROUND(0.3 + RAND() * 0.4, 3);
            ELSE
                SET cons_val = ROUND(0.8 + RAND() * 1.0, 3);
            END IF;

            -- Battery level
            SET batt = ROUND(40 + RAND() * 55, 2);

            -- Export/import
            IF solar_val > cons_val THEN
                SET export_val = ROUND(solar_val - cons_val, 3);
                SET import_val = 0.000;
            ELSE
                SET export_val = 0.000;
                SET import_val = ROUND(cons_val - solar_val, 3);
            END IF;

            SET temp_val = ROUND(22 + RAND() * 18, 2);
            SET cloud_val = FLOOR(RAND() * 80);

            INSERT INTO energy_readings (user_id, solar_kw, consumption_kw, battery_pct, grid_export_kw, grid_import_kw, temperature, cloud_cover, recorded_at)
            VALUES (1, solar_val, cons_val, batt, export_val, import_val, temp_val, cloud_val, ts);

            SET h = h + 1;
        END WHILE;
        SET d = d + 1;
    END WHILE;
END$$
DELIMITER ;

CALL seed_energy_readings();
DROP PROCEDURE IF EXISTS seed_energy_readings;

-- Net metering data: last 12 months
INSERT INTO net_metering (user_id, month_year, exported_kwh, imported_kwh, credit_amount, tariff_rate, net_savings) VALUES
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
(1, '2026-03', 205.70, 44.80,  16.46, 0.08, 30.20);

-- Carbon offsets: last 30 days
DELIMITER $$
CREATE PROCEDURE seed_carbon()
BEGIN
    DECLARE d INT DEFAULT 0;
    DECLARE cdate DATE;
    DECLARE skwh DECIMAL(8,3);
    DECLARE co2 DECIMAL(8,3);
    WHILE d < 30 DO
        SET cdate = DATE_SUB(CURDATE(), INTERVAL d DAY);
        SET skwh  = ROUND(12 + RAND() * 18, 3);
        SET co2   = ROUND(skwh * 0.82, 3);
        INSERT INTO carbon_offsets (user_id, offset_date, solar_kwh, co2_saved_kg, coal_equivalent_kg, trees_equivalent)
        VALUES (1, cdate, skwh, co2, ROUND(co2 * 0.34, 3), ROUND(co2 / 21.77, 3));
        SET d = d + 1;
    END WHILE;
END$$
DELIMITER ;

CALL seed_carbon();
DROP PROCEDURE IF EXISTS seed_carbon;
