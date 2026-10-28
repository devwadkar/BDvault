<?php
require_once __DIR__ . '/../models/Proposal.php';
require_once __DIR__ . '/../models/WeightsConfig.php';

class DashboardController {

    public static function index(): void {
        $db = getDB();
        $filters = [
            'from_date' => $_GET['from_date'] ?? '',
            'to_date'   => $_GET['to_date']   ?? '',
        ];

        $stats     = Proposal::getSummaryStats($filters);
        $topScored = Proposal::getTopScored(5);
        $scoreDist = Proposal::getScoreDistribution($filters);
        $cfg       = WeightsConfig::getActive();

        // Proposal Type breakdown for pie chart
        $typeDist   = Proposal::getTypeDistribution(8, $filters);
        $typeLabels = array_column($typeDist, 'proposal_type');
        $typeValues = array_column($typeDist, 'cnt');

        // Score distribution for bar chart
        $distLabels = [];
        $distValues = [];
        foreach ($scoreDist as $row) {
            $distLabels[] = $row['score_bucket'] . '-' . ($row['score_bucket'] + 9);
            $distValues[] = $row['cnt'];
        }

        // Geographic distribution
        $cityDist   = Proposal::getCityDistribution(10, $filters);
        $cityLabels = array_column($cityDist, 'city');
        $cityValues = array_column($cityDist, 'cnt');

        $regionDist   = Proposal::getRegionDistribution(10, $filters);
        $regionLabels = array_column($regionDist, 'region');
        $regionValues = array_column($regionDist, 'cnt');

        // ── New KPI Stat Cards data ──────────────────────────────────────────

        // New proposals today vs yesterday
        $today     = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $r = $db->query("SELECT COUNT(*) AS cnt FROM proposals WHERE DATE(created_at) = '$today'")->fetch_assoc();
        $todayCount = (int)($r['cnt'] ?? 0);
        $r = $db->query("SELECT COUNT(*) AS cnt FROM proposals WHERE DATE(created_at) = '$yesterday'")->fetch_assoc();
        $yesterdayCount = (int)($r['cnt'] ?? 0);
        $todayDelta = $yesterdayCount > 0 ? round(($todayCount - $yesterdayCount) / $yesterdayCount * 100, 1) : 0;

        // High potential this week vs last week
        $weekStart     = date('Y-m-d', strtotime('monday this week'));
        $lastWeekStart = date('Y-m-d', strtotime('monday last week'));
        $lastWeekEnd   = date('Y-m-d', strtotime('sunday last week'));
        $r = $db->query("SELECT COUNT(*) AS cnt FROM proposals p JOIN stage2_scores s2 ON s2.proposal_id=p.id WHERE s2.category='High Potential' AND DATE(p.created_at) >= '$weekStart'")->fetch_assoc();
        $highThisWeek = (int)($r['cnt'] ?? 0);
        $r = $db->query("SELECT COUNT(*) AS cnt FROM proposals p JOIN stage2_scores s2 ON s2.proposal_id=p.id WHERE s2.category='High Potential' AND DATE(p.created_at) BETWEEN '$lastWeekStart' AND '$lastWeekEnd'")->fetch_assoc();
        $highLastWeek = (int)($r['cnt'] ?? 0);
        $highNewThisWeek = $highThisWeek - $highLastWeek;

        // AI Success Rate = eligible / total with s1 processed
        $r = $db->query("SELECT COUNT(*) AS total, SUM(is_eligible=1) AS eligible FROM stage1_results")->fetch_assoc();
        $aiSuccessRate = ($r['total'] > 0) ? round($r['eligible'] / $r['total'] * 100, 1) : 0;

        // Potential value this month vs last month
        $thisMonthStart = date('Y-m-01');
        $lastMonthStart = date('Y-m-01', strtotime('-1 month'));
        $lastMonthEnd   = date('Y-m-t', strtotime('-1 month'));
        $r = $db->query("SELECT SUM(p.plot_area_sqm * 10.764 * p.avg_sale_price_sqft)/10000000 AS val FROM proposals p JOIN stage2_scores s2 ON s2.proposal_id=p.id WHERE s2.category='High Potential' AND p.created_at >= '$thisMonthStart'")->fetch_assoc();
        $valThisMonth = round((float)($r['val'] ?? 0), 1);
        $r = $db->query("SELECT SUM(p.plot_area_sqm * 10.764 * p.avg_sale_price_sqft)/10000000 AS val FROM proposals p JOIN stage2_scores s2 ON s2.proposal_id=p.id WHERE s2.category='High Potential' AND p.created_at BETWEEN '$lastMonthStart' AND '$lastMonthEnd'")->fetch_assoc();
        $valLastMonth = round((float)($r['val'] ?? 0), 1);
        $valDelta = $valLastMonth > 0 ? round(($valThisMonth - $valLastMonth) / $valLastMonth * 100, 1) : 0;

        // Avg score delta (this month vs last month)
        $r = $db->query("SELECT ROUND(AVG(s2.total_score),1) AS avg FROM stage2_scores s2 JOIN proposals p ON p.id=s2.proposal_id WHERE p.created_at >= '$thisMonthStart'")->fetch_assoc();
        $avgScoreThisMonth = (float)($r['avg'] ?? 0);
        $r = $db->query("SELECT ROUND(AVG(s2.total_score),1) AS avg FROM stage2_scores s2 JOIN proposals p ON p.id=s2.proposal_id WHERE p.created_at BETWEEN '$lastMonthStart' AND '$lastMonthEnd'")->fetch_assoc();
        $avgScoreLastMonth = (float)($r['avg'] ?? 0);
        $avgScoreDelta = round($avgScoreThisMonth - $avgScoreLastMonth, 1);

        // ── Heatmap data: region with lat/lng approximations ─────────────────
        // Comprehensive Mumbai region lat/lng lookup (keys lowercase-trimmed for matching)
        $regionCoords = [
            // Exact/variant matches for what's actually in the DB
            'andheri'           => [19.1197, 72.8369],
            'andheri west'      => [19.1336, 72.8263],
            'andheri east'      => [19.1197, 72.8469],
            'bandra'            => [19.0596, 72.8295],
            'bandra west'       => [19.0596, 72.8295],
            'bandra east'       => [19.0648, 72.8559],
            'juhu'              => [19.1075, 72.8263],
            'malabar hill'      => [18.9545, 72.7985],
            'walkeshwar'        => [18.9535, 72.8044],
            'powai'             => [19.1176, 72.9060],
            'kandivali'         => [19.2094, 72.8477],
            'vile parle'        => [19.0990, 72.8452],
            'vile parle west'   => [19.0990, 72.8352],
            'vile parle east'   => [19.0990, 72.8552],
            'khar'              => [19.0723, 72.8334],
            'malad'             => [19.1871, 72.8484],
            'malad west'        => [19.1871, 72.8384],
            'malad east'        => [19.1871, 72.8584],
            'dadar'             => [19.0178, 72.8478],
            'dadar west'        => [19.0178, 72.8378],
            'worli'             => [19.0176, 72.8162],
            'chembur'           => [19.0522, 72.8993],
            'tardeo'            => [18.9724, 72.8123],
            'mulund'            => [19.1726, 72.9561],
            'mulund west'       => [19.1726, 72.9461],
            'prabhadevi'        => [19.0160, 72.8258],
            'santacruz'         => [19.0814, 72.8416],
            'santacruz west'    => [19.0814, 72.8316],
            'santacruz east'    => [19.0814, 72.8516],
            'thane'             => [19.2183, 72.9781],
            'breach candy'      => [18.9708, 72.8077],
            'bhandup'           => [19.1497, 72.9422],
            'bhandup west'      => [19.1497, 72.9322],
            'mahim'             => [19.0332, 72.8415],
            'borivali'          => [19.2307, 72.8567],
            'borivali west'     => [19.2278, 72.8472],
            'ghatkopar'         => [19.0864, 72.9081],
            'haji ali'          => [18.9814, 72.8087],
            'lokhandwala'       => [19.1386, 72.8279],
            'lokhandwala complex' => [19.1386, 72.8279],
            'goregaon'          => [19.1663, 72.8526],
            'goregoan'          => [19.1663, 72.8526],  // common misspelling in DB
            'goregaon west'     => [19.1663, 72.8426],
            'goregaon east'     => [19.1663, 72.8626],
            'lower parel'       => [18.9935, 72.8259],
            'mahalaxmi'         => [18.9826, 72.8249],
            'vikhroli'          => [19.1070, 72.9249],
            'kurla'             => [19.0726, 72.8821],
            'versova'           => [19.1297, 72.8135],
            'jogeshwari'        => [19.1442, 72.8500],
            'dharavi'           => [19.0400, 72.8543],
            'sion'              => [19.0441, 72.8635],
            'wadala'            => [19.0195, 72.8610],
            'matunga'           => [19.0281, 72.8569],
            'colaba'            => [18.9067, 72.8147],
            'churchgate'        => [18.9322, 72.8264],
            'navi mumbai'       => [19.0330, 73.0297],
            'pcmc'              => [18.6279, 73.8009],
            'mulund east'       => [19.1726, 72.9661],
            'kandivali west'    => [19.2094, 72.8377],
            'kandivali east'    => [19.2094, 72.8577],
        ];

        // Build heatmap points — case-insensitive, trimmed key matching
        $heatmapPoints   = [];
        $regionTableData = [];
        $allRegionDist   = Proposal::getRegionDistribution(50, $filters);
        foreach ($allRegionDist as $row) {
            $region  = $row['region'];
            $cnt     = (int)$row['cnt'];
            $key     = strtolower(trim($region));
            if (isset($regionCoords[$key])) {
                [$lat, $lng] = $regionCoords[$key];
                $heatmapPoints[] = [$lat, $lng, $cnt];
            }
            $regionTableData[] = ['region' => $region, 'cnt' => $cnt];
        }

        require __DIR__ . '/../views/dashboard/index.php';
    }
}
