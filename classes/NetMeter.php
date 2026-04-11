<?php
// classes/NetMeter.php - Logic for Net Metering calculations
class NetMeter {
    private PDO $db;
    private int $userId;

    public function __construct(PDO $db, int $userId) {
        $this->db = $db;
        $this->userId = $userId;
    }

    public function getMonthlyStats(?string $monthYear = null): ?array {
        if (!$monthYear) {
            $monthYear = date('Y-m');
        }
        $stmt = $this->db->prepare("SELECT * FROM net_metering WHERE user_id=? AND month_year=?");
        $stmt->execute([$this->userId, $monthYear]);
        return $stmt->fetch() ?: null;
    }

    public function getAllRecords(): array {
        $stmt = $this->db->prepare("SELECT * FROM net_metering WHERE user_id=? ORDER BY month_year DESC");
        $stmt->execute([$this->userId]);
        return $stmt->fetchAll();
    }
}
