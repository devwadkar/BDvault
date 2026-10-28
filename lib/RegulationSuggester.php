<?php
/**
 * RegulationSuggester — rule-based matcher that proposes the applicable DCPR regulation
 * for a plot, given its development_type, planning_authority, plot facts, and existing
 * structure. Returns confidence + rationale + alternates so the user can override
 * intelligently at Step 4 of the Area Analysis wizard.
 *
 * All rules are declarative — no user text, no LLM. Deterministic + auditable.
 */
class RegulationSuggester {

    /**
     * Every regulation this system can suggest. Order = default listing order.
     * `status` = 'calculator' → we have a Reg33_*_Calculator ; 'pending' → suggestion only.
     */
    public const REGULATIONS = [
        '33(5)'     => ['title' => 'MHADA Housing Redevelopment',                    'status' => 'calculator'],
        '33(7)'     => ['title' => 'Cessed Buildings (Island City)',                  'status' => 'pending'],
        '33(7)(A)'  => ['title' => 'Cessed Buildings — Category A',                   'status' => 'pending'],
        '33(7)(B)'  => ['title' => 'Cessed Building — Tenanted (Cat B/C)',            'status' => 'pending'],
        '33(9)'     => ['title' => 'Cluster / Urban Renewal Scheme',                  'status' => 'pending'],
        '33(10)'    => ['title' => 'Slum Rehabilitation (SRA)',                       'status' => 'calculator'],
        '33(11)'    => ['title' => 'SRA Permanent Transit Camp / Rental Housing',     'status' => 'calculator'],
        '33(20)(B)' => ['title' => 'Affordable Housing on Private Land',              'status' => 'calculator'],
    ];

    /**
     * Suggest a regulation given wizard state.
     *
     * @param array $ctx Shape:
     *   [
     *     'development_type'   => 'sra' | 'mhada' | 'redevelopment' | 'society_redevelopment' |
     *                             'land_development' | 'cluster' | 'other',
     *     'planning_authority' => 'MCGM' | 'SRA' | 'MHADA' | 'MMRDA' | 'Collector' | 'Other',
     *     'region'             => 'Island City' | 'Suburbs' | null,
     *     'gross_plot_area_sqm'=> float|null,
     *     'road_width_m'       => float|null,
     *     'tenant_count'       => int,
     *     'has_existing_bua'   => bool,
     *   ]
     *
     * @return array {regulation_key, confidence, rationale, alternates: [{key, title, confidence, why}]}
     */
    public static function suggest(array $ctx): array {
        $devType   = strtolower((string)($ctx['development_type']   ?? ''));
        $authority = strtoupper((string)($ctx['planning_authority'] ?? ''));
        $tenants   = (int)($ctx['tenant_count'] ?? 0);
        $hasBua    = !empty($ctx['has_existing_bua']);
        $plot      = self::flt($ctx['gross_plot_area_sqm'] ?? null);
        $road      = self::flt($ctx['road_width_m']        ?? null);
        $region    = $ctx['region'] ?? null;

        $scores = []; // reg_key => ['score' => int, 'why' => [reason strings]]

        // ─── MHADA path ─────────────────────────────────────
        if ($devType === 'mhada' || $authority === 'MHADA') {
            $scores['33(5)']['score'] = 92;
            $scores['33(5)']['why'][] = 'Development Type = MHADA / Planning Authority = MHADA → Reg 33(5) covers MHADA housing schemes.';
            if ($hasBua) { $scores['33(5)']['score'] += 3; $scores['33(5)']['why'][] = 'Existing MHADA colony BUA present → redevelopment path (Basic FSI 3.0, upgradable to 4.0).'; }
            if ($plot !== null && $road !== null && $plot >= 4000 && $road >= 18) {
                $scores['33(5)']['score'] += 2;
                $scores['33(5)']['why'][] = 'Plot ≥ 4000 sqm + Road ≥ 18m qualifies for elevated 4.0 FSI cap.';
            }
        }

        // ─── SRA path ───────────────────────────────────────
        if ($devType === 'sra' || $authority === 'SRA') {
            // Prefer 33(10) whenever tenants exist ; 33(11) is niche PTC/rental housing.
            if ($tenants > 0 || $hasBua) {
                $scores['33(10)']['score'] = 90;
                $scores['33(10)']['why'][] = 'Development Type = SRA with existing slum tenants → Reg 33(10) Slum Rehabilitation Scheme.';
                if ($tenants >= 100) { $scores['33(10)']['score'] += 3; $scores['33(10)']['why'][] = 'Tenant count ≥ 100 typical of full SRA rehab schemes.'; }
                // 33(11) as alternate (transit camp / rental)
                $scores['33(11)']['score'] = 55;
                $scores['33(11)']['why'][] = 'Alternate: Reg 33(11) applies for Permanent Transit Camp / Rental Housing SRA schemes.';
            } else {
                // No tenants — probably a PTC on vacant SRA land
                $scores['33(11)']['score'] = 70;
                $scores['33(11)']['why'][] = 'SRA scheme without slum tenants → likely Reg 33(11) PTC / Rental Housing.';
                $scores['33(10)']['score'] = 45;
                $scores['33(10)']['why'][] = 'Alternate: Reg 33(10) if slum tenants are actually eligible on-site.';
            }
        }

        // ─── Private redevelopment / society ────────────────
        if (in_array($devType, ['redevelopment', 'society_redevelopment'], true)) {
            // 33(20)(B) is the AH-on-private-land path — matches when the plot is private + tenants exist
            $scores['33(20)(B)']['score'] = 75;
            $scores['33(20)(B)']['why'][] = 'Private redevelopment with owner-share + AH-share model → Reg 33(20)(B) composite (30(A) + 33(7)(B) + 33(20)(B)).';
            // 33(7) family — cessed building candidate in Island City
            if ($region === 'Island City' || ($ctx['project_location'] ?? '') && stripos((string)$ctx['project_location'], 'island') !== false) {
                $scores['33(7)']['score'] = 65;
                $scores['33(7)']['why'][] = 'Island City redevelopment → possibly Reg 33(7) cessed-building scheme (PENDING calculator).';
                $scores['33(7)(B)']['score'] = 60;
                $scores['33(7)(B)']['why'][] = 'If building is tenanted (Cat B/C cessed) → Reg 33(7)(B) (PENDING calculator).';
            }
        }

        // ─── Cluster / Urban Renewal ────────────────────────
        if ($devType === 'cluster') {
            $scores['33(9)']['score'] = 92;
            $scores['33(9)']['why'][] = 'Development Type = Cluster → Reg 33(9) Urban Renewal Scheme (PENDING calculator).';
            if ($plot !== null && $plot >= 4000) { $scores['33(9)']['score'] += 3; $scores['33(9)']['why'][] = 'Cluster plots ≥ 4000 sqm meet the minimum threshold.'; }
        }

        // ─── Vacant land / land development ─────────────────
        if ($devType === 'land_development') {
            $scores['33(20)(B)']['score'] = 60;
            $scores['33(20)(B)']['why'][] = 'Vacant private land eligible for AH-on-Private-Land scheme (33(20)(B)) when AH share offered.';
        }

        // ─── Fallback ───────────────────────────────────────
        if (empty($scores)) {
            $scores['33(20)(B)']['score'] = 40;
            $scores['33(20)(B)']['why'][] = 'No strong signal — defaulting to Reg 33(20)(B) as the most flexible private-land path.';
        }

        // Normalise & rank
        foreach ($scores as $k => &$s) {
            $s['score'] = max(0, min(100, $s['score']));
        }
        unset($s);
        uasort($scores, fn($a, $b) => $b['score'] <=> $a['score']);

        $best = array_key_first($scores);
        $bestMeta = self::REGULATIONS[$best] ?? ['title' => $best, 'status' => 'pending'];

        $alternates = [];
        foreach ($scores as $key => $s) {
            if ($key === $best) continue;
            $meta = self::REGULATIONS[$key] ?? ['title' => $key, 'status' => 'pending'];
            $alternates[] = [
                'key'        => $key,
                'title'      => $meta['title'],
                'status'     => $meta['status'],
                'confidence' => $s['score'],
                'why'        => implode(' ', $s['why']),
            ];
        }

        return [
            'regulation_key' => $best,
            'title'          => $bestMeta['title'],
            'status'         => $bestMeta['status'],
            'confidence'     => $scores[$best]['score'],
            'rationale'      => implode(' ', $scores[$best]['why']),
            'alternates'     => $alternates,
        ];
    }

    private static function flt($v): ?float {
        if ($v === null || $v === '' || !is_numeric($v)) return null;
        return (float)$v;
    }
}
