<?php
// classes/Device.php
class Device {
    private PDO $db;
    private int $userId;

    public function __construct(PDO $db, int $userId) {
        $this->db     = $db;
        $this->userId = $userId;
    }

    public function getAll(): array {
        $stmt = $this->db->prepare("SELECT * FROM devices WHERE user_id=? ORDER BY priority, name");
        $stmt->execute([$this->userId]);
        return $stmt->fetchAll();
    }

    public function getOne(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM devices WHERE id=? AND user_id=?");
        $stmt->execute([$id, $this->userId]);
        return $stmt->fetch() ?: null;
    }

    public function toggle(int $id): array {
        $device = $this->getOne($id);
        if (!$device) return ['success'=>false,'message'=>'Device not found'];
        if ($device['is_essential'] && $device['is_on']) {
            return ['success'=>false,'message'=>'Cannot turn off essential device'];
        }
        $newState = $device['is_on'] ? 0 : 1;
        $stmt = $this->db->prepare("UPDATE devices SET is_on=? WHERE id=? AND user_id=?");
        $stmt->execute([$newState, $id, $this->userId]);
        return ['success'=>true,'is_on'=>$newState,'device'=>$device['name']];
    }

    public function add(string $name, string $category, int $priority, int $watts, bool $essential, string $icon): int {
        $stmt = $this->db->prepare("INSERT INTO devices (user_id,name,category,priority,power_watts,is_essential,icon) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$this->userId,$name,$category,$priority,$watts,(int)$essential,$icon]);
        return (int)$this->db->lastInsertId();
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM devices WHERE id=? AND user_id=?");
        return $stmt->execute([$id, $this->userId]);
    }

    public function updatePriority(int $id, int $priority): bool {
        $stmt = $this->db->prepare("UPDATE devices SET priority=? WHERE id=? AND user_id=?");
        return $stmt->execute([$priority,$id,$this->userId]);
    }

    public function totalLoadKw(): float {
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(power_watts),0) FROM devices WHERE user_id=? AND is_on=1");
        $stmt->execute([$this->userId]);
        return round($stmt->fetchColumn() / 1000, 3);
    }

    public function autoShed(float $solarKw, float $threshold): array {
        // Returns devices that should be shed
        $stmt = $this->db->prepare("SELECT * FROM devices WHERE user_id=? AND is_on=1 AND is_essential=0 ORDER BY priority DESC");
        $stmt->execute([$this->userId]);
        $candidates = $stmt->fetchAll();
        $shed = [];
        $load = $this->totalLoadKw();
        foreach ($candidates as $d) {
            if ($load <= $solarKw * (1 - $threshold/100)) break;
            $shed[] = $d;
            $load  -= $d['power_watts']/1000;
        }
        return $shed;
    }
}
