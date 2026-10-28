<?php
require_once __DIR__ . '/../config/database.php';

class WeightsConfig {

    // ─── Fetch active config ─────────────────────────────────────────────────

    public static function getActive(): array {
        $db  = getDB();
        $row = $db->query("SELECT * FROM weights_config WHERE is_active = 1 LIMIT 1")->fetch_assoc();
        if (!$row) {
            // Return hardcoded defaults if table empty
            return self::defaults();
        }
        return $row;
    }

    public static function getAll(): array {
        return getDB()->query("SELECT * FROM weights_config ORDER BY is_active DESC, id ASC")
                      ->fetch_all(MYSQLI_ASSOC);
    }

    public static function getById(int $id): ?array {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM weights_config WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    // ─── Save / update ───────────────────────────────────────────────────────

    public static function save(array $data): int {
        $db = getDB();
        if (!empty($data['id'])) {
            $id = (int)$data['id'];
            $stmt = $db->prepare("
                UPDATE weights_config SET
                    config_name          = ?, description           = ?,
                    min_sale_rate        = ?, max_fsi_used          = ?,
                    min_free_sale_pct    = ?, min_density_ratio     = ?,
                    max_timeline_years   = ?, min_profit_margin_pct = ?,
                    weight_profit_margin = ?, weight_location       = ?,
                    weight_risk          = ?, weight_cost_efficiency= ?,
                    weight_sale_velocity = ?,
                    threshold_high       = ?, threshold_moderate    = ?
                WHERE id = ?
            ");
            $stmt->bind_param(
                'ssdddddddddddddi',
                $data['config_name'], $data['description'],
                $data['min_sale_rate'], $data['max_fsi_used'],
                $data['min_free_sale_pct'], $data['min_density_ratio'],
                $data['max_timeline_years'], $data['min_profit_margin_pct'],
                $data['weight_profit_margin'], $data['weight_location'],
                $data['weight_risk'], $data['weight_cost_efficiency'],
                $data['weight_sale_velocity'],
                $data['threshold_high'], $data['threshold_moderate'],
                $id
            );
            $stmt->execute();
            return $id;
        }

        $stmt = $db->prepare("
            INSERT INTO weights_config
                (config_name, description,
                 min_sale_rate, max_fsi_used, min_free_sale_pct,
                 min_density_ratio, max_timeline_years, min_profit_margin_pct,
                 weight_profit_margin, weight_location, weight_risk,
                 weight_cost_efficiency, weight_sale_velocity,
                 threshold_high, threshold_moderate, is_active)
            VALUES (?,?, ?,?,?, ?,?,?, ?,?,?, ?,?, ?,?,0)
        ");
        $stmt->bind_param(
            'ssddddddddddddd',
            $data['config_name'], $data['description'],
            $data['min_sale_rate'], $data['max_fsi_used'],
            $data['min_free_sale_pct'], $data['min_density_ratio'],
            $data['max_timeline_years'], $data['min_profit_margin_pct'],
            $data['weight_profit_margin'], $data['weight_location'],
            $data['weight_risk'], $data['weight_cost_efficiency'],
            $data['weight_sale_velocity'],
            $data['threshold_high'], $data['threshold_moderate']
        );
        $stmt->execute();
        return $db->insert_id;
    }

    public static function activate(int $id): void {
        $db = getDB();
        $db->query("UPDATE weights_config SET is_active = 0");
        $stmt = $db->prepare("UPDATE weights_config SET is_active = 1 WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
    }

    // ─── Defaults ────────────────────────────────────────────────────────────

    public static function defaults(): array {
        return [
            'id'                    => 0,
            'config_name'           => 'Default',
            'description'           => '',
            'min_sale_rate'         => 60000,
            'max_fsi_used'          => 1.0,
            'min_free_sale_pct'     => 60.0,
            'min_density_ratio'     => 2.5,
            'max_timeline_years'    => 5.0,
            'min_profit_margin_pct' => 20.0,
            'weight_profit_margin'  => 25,
            'weight_location'       => 20,
            'weight_risk'           => 20,
            'weight_cost_efficiency'=> 20,
            'weight_sale_velocity'  => 15,
            'threshold_high'        => 70,
            'threshold_moderate'    => 50,
        ];
    }
}
