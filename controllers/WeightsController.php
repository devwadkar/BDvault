<?php
require_once __DIR__ . '/../models/WeightsConfig.php';
require_once __DIR__ . '/../models/Stage1Filter.php';
require_once __DIR__ . '/../models/Stage2Scorer.php';
require_once __DIR__ . '/../models/Proposal.php';
require_once __DIR__ . '/../includes/functions.php';

class WeightsController {

    public static function index(): void {
        $configs = WeightsConfig::getAll();
        $active  = WeightsConfig::getActive();
        $flash   = flashGet();
        require __DIR__ . '/../views/weights/config.php';
    }

    public static function save(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('index.php?page=weights');
            return;
        }

        $d = [
            'id'                    => (int)($_POST['id'] ?? 0),
            'config_name'           => trim($_POST['config_name'] ?? 'Custom Config'),
            'description'           => trim($_POST['description'] ?? ''),
            'min_sale_rate'         => (float)($_POST['min_sale_rate'] ?? 60000),
            'max_fsi_used'          => (float)($_POST['max_fsi_used'] ?? 1.0),
            'min_free_sale_pct'     => (float)($_POST['min_free_sale_pct'] ?? 60),
            'min_density_ratio'     => (float)($_POST['min_density_ratio'] ?? 2.5),
            'max_timeline_years'    => (float)($_POST['max_timeline_years'] ?? 5),
            'min_profit_margin_pct' => (float)($_POST['min_profit_margin_pct'] ?? 20),
            'weight_profit_margin'  => (float)($_POST['weight_profit_margin'] ?? 25),
            'weight_location'       => (float)($_POST['weight_location'] ?? 20),
            'weight_risk'           => (float)($_POST['weight_risk'] ?? 20),
            'weight_cost_efficiency'=> (float)($_POST['weight_cost_efficiency'] ?? 20),
            'weight_sale_velocity'  => (float)($_POST['weight_sale_velocity'] ?? 15),
            'threshold_high'        => (float)($_POST['threshold_high'] ?? 70),
            'threshold_moderate'    => (float)($_POST['threshold_moderate'] ?? 50),
        ];

        // Validate weights sum to 100
        $wSum = $d['weight_profit_margin'] + $d['weight_location'] + $d['weight_risk']
              + $d['weight_cost_efficiency'] + $d['weight_sale_velocity'];
        if (abs($wSum - 100) > 0.01) {
            flashSet('danger', "Weights must sum to 100 (currently {$wSum}). Please adjust.");
            redirect('index.php?page=weights');
            return;
        }

        $savedId = WeightsConfig::save($d);

        if (!empty($_POST['set_active'])) {
            WeightsConfig::activate($savedId);
            // Re-run all classifications with new config
            if (!empty($_POST['rerun_all'])) {
                self::reRunAll();
            }
            flashSet('success', 'Configuration saved, activated, and proposals re-classified.');
        } else {
            flashSet('success', 'Configuration saved.');
        }

        redirect('index.php?page=weights');
    }

    public static function activate(): void {
        $id = (int)($_GET['id'] ?? 0);
        if ($id) {
            WeightsConfig::activate($id);
            if (!empty($_GET['rerun'])) self::reRunAll();
            flashSet('success', 'Configuration activated. Proposals re-classified.');
        }
        redirect('index.php?page=weights');
    }

    private static function reRunAll(): void {
        $cfg   = WeightsConfig::getActive();
        $db    = getDB();
        $db->query("DELETE FROM stage1_results");
        $db->query("DELETE FROM stage2_scores");
        $rows  = $db->query("SELECT * FROM proposals")->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as $p) {
            $s1 = Stage1Filter::evaluate($p, $cfg);
            if ($s1['is_eligible']) Stage2Scorer::evaluate($p, $cfg);
        }
    }
}
