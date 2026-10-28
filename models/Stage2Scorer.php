<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * Stage 2 – Weighted Scoring & Probability Model
 *
 * Each dimension is scored 0–100, then multiplied by its weight.
 * Final score = weighted average (0–100).
 * Probability = logistic transformation of the final score.
 *
 * Dimension scoring functions are documented inline so BD team can
 * understand and adjust thresholds.
 */
class Stage2Scorer {

    // ─── Evaluate a single eligible proposal ────────────────────────────────

    public static function evaluate(array $proposal, array $cfg): array {
        $breakdown = [];

        // ── 1. Profit Margin Score (0–100) ────────────────────────────────
        // Maps profit margin % linearly: 20% → 0, 60%+ → 100
        $saleRate  = (float)($proposal['avg_sale_price_sqft']  ?? 0);
        $entryCost = (float)($proposal['entry_cost_per_sqft']  ?? 0);
        if ($saleRate > 0 && $entryCost > 0) {
            $pm = ($saleRate - $entryCost) / $saleRate * 100;
        } else {
            $pm = (float)$cfg['min_profit_margin_pct']; // assume at the floor if missing
        }
        $pmScore = self::clamp(($pm - 20) / 40 * 100);
        $breakdown['profit_margin'] = [
            'label'       => 'Profit Margin Potential',
            'raw_value'   => round($pm, 1) . '%',
            'score'       => $pmScore,
            'weight'      => (float)$cfg['weight_profit_margin'],
            'explanation' => "Profit margin {$pm}% mapped to score (20%→0, 60%+→100)",
        ];

        // ── 2. Location Desirability Score (0–100) ────────────────────────
        // location_rating is 1–10 (entered by BD), scaled to 0–100
        $locRating = (int)($proposal['location_rating'] ?? 5);
        $locScore  = self::clamp(($locRating - 1) / 9 * 100);
        $breakdown['location'] = [
            'label'       => 'Location Desirability',
            'raw_value'   => "{$locRating}/10",
            'score'       => $locScore,
            'weight'      => (float)$cfg['weight_location'],
            'explanation' => "BD rating {$locRating}/10 → score " . round($locScore, 1),
        ];

        // ── 3. Risk Score (0–100) ─────────────────────────────────────────
        // Composite: timeline risk + approval complexity
        // Timeline: 3 years → 100, 5 years → 0 (linear)
        $timeline    = (float)($proposal['completion_timeline_yrs'] ?? 4);
        $tlScore     = self::clamp((5 - $timeline) / 2 * 100);

        // Approval complexity: 1 (simple) → 100, 10 (very complex) → 0
        $complexity  = (int)($proposal['approval_complexity'] ?? 5);
        $compScore   = self::clamp((10 - $complexity) / 9 * 100);

        $riskScore   = ($tlScore + $compScore) / 2;
        $breakdown['risk'] = [
            'label'       => 'Risk Assessment',
            'raw_value'   => "Timeline: {$timeline}y, Complexity: {$complexity}/10",
            'score'       => round($riskScore, 1),
            'weight'      => (float)$cfg['weight_risk'],
            'sub_scores'  => [
                'timeline_score'   => round($tlScore, 1),
                'complexity_score' => round($compScore, 1),
            ],
            'explanation' => "Avg of timeline score ({$tlScore}) and approval complexity score ({$compScore})",
        ];

        // ── 4. Cost Efficiency Score (0–100) ──────────────────────────────
        // cost_ratio = entry_cost / sale_rate
        // 20% cost ratio → 100,  80%+ cost ratio → 0
        $costRatio = ($saleRate > 0 && $entryCost > 0) ? $entryCost / $saleRate : 0.5;
        $ceScore   = self::clamp((0.8 - $costRatio) / 0.6 * 100);
        $breakdown['cost_efficiency'] = [
            'label'       => 'Cost Efficiency',
            'raw_value'   => round($costRatio * 100, 1) . '% cost ratio',
            'score'       => $ceScore,
            'weight'      => (float)$cfg['weight_cost_efficiency'],
            'explanation' => "Entry cost is " . round($costRatio * 100, 1) . "% of sale rate (20%→100, 80%+→0)",
        ];

        // ── 5. Sale Velocity Score (0–100) ────────────────────────────────
        // market_demand is 1–10 (entered by BD), scaled to 0–100
        $demand   = (int)($proposal['market_demand'] ?? 5);
        $svScore  = self::clamp(($demand - 1) / 9 * 100);
        $breakdown['sale_velocity'] = [
            'label'       => 'Sale Velocity / Market Demand',
            'raw_value'   => "{$demand}/10",
            'score'       => $svScore,
            'weight'      => (float)$cfg['weight_sale_velocity'],
            'explanation' => "Market demand rating {$demand}/10 → score " . round($svScore, 1),
        ];

        // ── Weighted total ────────────────────────────────────────────────
        $wTotal = (float)$cfg['weight_profit_margin'] + (float)$cfg['weight_location']
                + (float)$cfg['weight_risk'] + (float)$cfg['weight_cost_efficiency']
                + (float)$cfg['weight_sale_velocity'];

        $totalScore = (
              $pmScore  * (float)$cfg['weight_profit_margin']
            + $locScore * (float)$cfg['weight_location']
            + $riskScore* (float)$cfg['weight_risk']
            + $ceScore  * (float)$cfg['weight_cost_efficiency']
            + $svScore  * (float)$cfg['weight_sale_velocity']
        ) / max($wTotal, 1);

        $totalScore = round($totalScore, 2);

        // ── Probability (logistic) ────────────────────────────────────────
        $probability = scoreToProbability($totalScore);

        // ── Category ─────────────────────────────────────────────────────
        $category = scoreToCategory($totalScore, $cfg);

        $result = [
            'proposal_id'           => $proposal['id'],
            'score_profit_margin'   => round($pmScore, 2),
            'score_location'        => round($locScore, 2),
            'score_risk'            => round($riskScore, 2),
            'score_cost_efficiency' => round($ceScore, 2),
            'score_sale_velocity'   => round($svScore, 2),
            'contrib_profit_margin' => round($pmScore  * (float)$cfg['weight_profit_margin'] / 100, 2),
            'contrib_location'      => round($locScore * (float)$cfg['weight_location'] / 100, 2),
            'contrib_risk'          => round($riskScore* (float)$cfg['weight_risk'] / 100, 2),
            'contrib_cost_efficiency'=> round($ceScore * (float)$cfg['weight_cost_efficiency'] / 100, 2),
            'contrib_sale_velocity' => round($svScore  * (float)$cfg['weight_sale_velocity'] / 100, 2),
            'total_score'           => $totalScore,
            'probability_pct'       => $probability,
            'category'              => $category,
            'score_breakdown'       => json_encode($breakdown),
            'weights_used'          => json_encode([
                'profit_margin'   => $cfg['weight_profit_margin'],
                'location'        => $cfg['weight_location'],
                'risk'            => $cfg['weight_risk'],
                'cost_efficiency' => $cfg['weight_cost_efficiency'],
                'sale_velocity'   => $cfg['weight_sale_velocity'],
            ]),
        ];

        self::persist($result);
        return $result;
    }

    // ─── Batch score all eligible proposals without a Stage 2 score ──────────

    public static function evaluateAll(array $cfg): array {
        $db   = getDB();
        $rows = $db->query("
            SELECT p.*
            FROM proposals p
            INNER JOIN stage1_results s  ON s.proposal_id = p.id
            LEFT  JOIN stage2_scores  s2 ON s2.proposal_id = p.id
            WHERE s2.id IS NULL
        ")->fetch_all(MYSQLI_ASSOC);

        $results = [];
        foreach ($rows as $proposal) {
            $results[] = self::evaluate($proposal, $cfg);
        }
        return $results;
    }

    // ─── Re-evaluate (delete existing result first) ───────────────────────────

    public static function reEvaluate(array $proposal, array $cfg): array {
        $db   = getDB();
        $stmt = $db->prepare("DELETE FROM stage2_scores WHERE proposal_id = ?");
        $stmt->bind_param('i', $proposal['id']);
        $stmt->execute();
        return self::evaluate($proposal, $cfg);
    }

    // ─── Clamp 0–100 ─────────────────────────────────────────────────────────

    private static function clamp(float $v): float {
        return round(max(0.0, min(100.0, $v)), 2);
    }

    // ─── Persist ─────────────────────────────────────────────────────────────

    private static function persist(array $r): void {
        $db   = getDB();
        $stmt = $db->prepare("
            INSERT INTO stage2_scores
                (proposal_id,
                 score_profit_margin, score_location, score_risk,
                 score_cost_efficiency, score_sale_velocity,
                 contrib_profit_margin, contrib_location, contrib_risk,
                 contrib_cost_efficiency, contrib_sale_velocity,
                 total_score, probability_pct, category,
                 score_breakdown, weights_used)
            VALUES (?, ?,?,?, ?,?, ?,?,?, ?,?, ?,?,?, ?,?)
            ON DUPLICATE KEY UPDATE
                score_profit_margin    = VALUES(score_profit_margin),
                score_location         = VALUES(score_location),
                score_risk             = VALUES(score_risk),
                score_cost_efficiency  = VALUES(score_cost_efficiency),
                score_sale_velocity    = VALUES(score_sale_velocity),
                contrib_profit_margin  = VALUES(contrib_profit_margin),
                contrib_location       = VALUES(contrib_location),
                contrib_risk           = VALUES(contrib_risk),
                contrib_cost_efficiency= VALUES(contrib_cost_efficiency),
                contrib_sale_velocity  = VALUES(contrib_sale_velocity),
                total_score            = VALUES(total_score),
                probability_pct        = VALUES(probability_pct),
                category               = VALUES(category),
                score_breakdown        = VALUES(score_breakdown),
                weights_used           = VALUES(weights_used),
                processed_at           = CURRENT_TIMESTAMP
        ");
        // 16 params: 1×i (id) + 12×d (scores+contribs+total+prob) + 3×s (category,breakdown,weights)
        $stmt->bind_param(
            'iddddddddddddsss',
            $r['proposal_id'],
            $r['score_profit_margin'], $r['score_location'], $r['score_risk'],
            $r['score_cost_efficiency'], $r['score_sale_velocity'],
            $r['contrib_profit_margin'], $r['contrib_location'], $r['contrib_risk'],
            $r['contrib_cost_efficiency'], $r['contrib_sale_velocity'],
            $r['total_score'], $r['probability_pct'], $r['category'],
            $r['score_breakdown'], $r['weights_used']
        );
        $stmt->execute();
    }
}
