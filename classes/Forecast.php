<?php
// classes/Forecast.php – ML-simulation forecast engine
class Forecast {
    private PDO $db;
    private int $userId;
    private array $user;

    public function __construct(PDO $db, int $userId, array $user) {
        $this->db     = $db;
        $this->userId = $userId;
        $this->user   = $user;
    }

    /**
     * Predict solar yield from weather parameters using a physics-inspired regression:
     *   yield = panel_kw × peak_sun_hours × (1 - cloud_factor) × temp_factor × efficiency
     */
    public function predictYield(float $cloudPct, float $temp, float $humidity, int $hour=12): float {
        $panelKw      = (float)$this->user['panel_capacity_kw'];
        $cloudFactor  = $cloudPct / 100 * 0.75;         // clouds reduce up to 75%
        $tempFactor   = 1 - max(0, ($temp - 25) * 0.004); // efficiency drops above 25°C
        $humidFactor  = 1 - ($humidity / 100 * 0.05);   // small humidity penalty
        $peakHours    = SOLAR_PEAK_HOURS;
        $yield        = $panelKw * $peakHours * (1 - $cloudFactor) * $tempFactor * $humidFactor * PANEL_EFFICIENCY * 10;
        return max(0, round($yield, 3));
    }

    /** Confidence score based on cloud cover variance */
    public function confidence(float $cloudPct): int {
        if ($cloudPct <= 10)       return mt_rand(90, 97);
        elseif ($cloudPct <= 30)   return mt_rand(80, 90);
        elseif ($cloudPct <= 60)   return mt_rand(65, 80);
        else                       return mt_rand(45, 65);
    }

    /** Recommendation string based on predicted yield */
    public function recommend(float $kwh, float $cloudPct, string $date): string {
        $dow = date('l', strtotime($date));
        if ($cloudPct <= 20 && $kwh >= 20) {
            return "🌞 Excellent solar day ({$dow})! Ideal for EV charging, washing machine & water heater.";
        } elseif ($cloudPct <= 40 && $kwh >= 14) {
            return "⛅ Good solar output expected. Run high-power appliances in the 10am–2pm window.";
        } elseif ($cloudPct <= 65) {
            return "🌤️ Moderate generation likely. Conserve battery for evening peak; avoid EV charging.";
        } else {
            return "☁️ Low solar yield forecast. Grid import likely. Schedule non-essential tasks for tomorrow.";
        }
    }

    /** Generate forecasts for next N days using weather data */
    public function generateForecasts(array $weatherDays): array {
        $forecasts = [];
        foreach ($weatherDays as $day) {
            $cloudPct  = (int)($day['clouds']['all'] ?? 30);
            $temp      = round($day['main']['temp'] - 273.15, 1); // Kelvin→°C
            $humidity  = (int)($day['main']['humidity'] ?? 50);
            $date      = date('Y-m-d', $day['dt']);
            $kwh       = $this->predictYield($cloudPct, $temp, $humidity);
            $conf      = $this->confidence($cloudPct);
            $rec       = $this->recommend($kwh, $cloudPct, $date);

            // Upsert into DB
            $stmt = $this->db->prepare("
                INSERT INTO forecasts (user_id,forecast_date,predicted_kwh,cloud_cover_pct,temperature,humidity,confidence_pct,recommendation)
                VALUES (?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                    predicted_kwh=VALUES(predicted_kwh),cloud_cover_pct=VALUES(cloud_cover_pct),
                    temperature=VALUES(temperature),humidity=VALUES(humidity),
                    confidence_pct=VALUES(confidence_pct),recommendation=VALUES(recommendation),
                    generated_at=NOW()");
            $stmt->execute([$this->userId,$date,$kwh,$cloudPct,$temp,$humidity,$conf,$rec]);

            $forecasts[] = compact('date','kwh','cloudPct','temp','humidity','conf','rec');
        }
        return $forecasts;
    }

    /** Get stored forecasts from DB */
    public function getStored(int $days = 30): array {
        $stmt = $this->db->prepare("
            SELECT * FROM forecasts
            WHERE user_id=? AND forecast_date >= CURDATE()
            ORDER BY forecast_date
            LIMIT ?");
        $stmt->execute([$this->userId, $days]);
        return $stmt->fetchAll();
    }

    /** Sun/cloud icon helper */
    public static function weatherIcon(int $cloudPct): string {
        if ($cloudPct <= 10)  return '☀️';
        if ($cloudPct <= 30)  return '🌤️';
        if ($cloudPct <= 60)  return '⛅';
        if ($cloudPct <= 80)  return '🌥️';
        return '☁️';
    }
}
