<?php
/**
 * SitePlanRenderer — interactive, polished SVG site-plan visual for an Area Statement.
 *
 * Renders three view modes controlled by top toolbar:
 *   PLOT    — schematic aerial: plot boundary + colored deduction zones + net plot +
 *             building footprints (proportional to BUA), compass, scale bar.
 *   MASSING — vertical stacked BUA bar (Rehab / Sale / Handover) with % labels.
 *   COMBINED (default) — both panels side-by-side.
 *
 * Interactions (via inline JS):
 *   · Hover a zone       → tooltip with area, ratio, regulation ref.
 *   · Click a zone       → focus mode: dims other zones, shows detail card.
 *   · Click view toggle  → animated transition between panels.
 *
 * Wrapped in <div class="sp-root"> so multiple instances on one page stay independent.
 */
class SitePlanRenderer {

    private static int $instanceCount = 0;

    public static function render(array $ctx): string {
        $id = 'sp' . (++self::$instanceCount);
        $statement = $ctx['statement'] ?? [];
        $land      = $ctx['land']      ?? [];
        $dev       = $ctx['dev']       ?? [];
        $buildings = $ctx['buildings'] ?? [];

        $get = fn($k) => isset($land[$k]) ? (float)($land[$k]['area_sqm'] ?? 0) : 0.0;
        $gross    = $get('gross_plot');
        $road     = $get('road_deduction');
        $reserv   = $get('reservation');
        $amenity  = $get('amenity');
        $other    = $get('other_deduction');
        $net      = $get('net_plot') ?: max(0, $gross - $road - $reserv - $amenity - $other);

        $totalBua = (float)($statement['total_permissible_bua_sqm'] ?? 0);
        $rehab    = (float)($statement['rehab_bua_sqm'] ?? 0);
        $sale     = (float)($statement['sale_bua_sqm']  ?? 0);
        $handover = max(0.0, $totalBua - $rehab - $sale);
        if ($rehab + $sale + $handover <= 0 && $totalBua > 0) { $sale = $totalBua; }

        // Zone metadata used by both SVG rendering + tooltip payloads
        $zones = self::zoneMeta($gross, $road, $reserv, $amenity, $other, $net);

        // Emit
        ob_start();
        ?>
        <div class="sp-root" id="<?= $id ?>-root">
            <?= self::styles($id) ?>

            <div class="sp-toolbar">
                <div class="sp-brand">
                    <span class="sp-brand-icon">◱</span>
                    <div>
                        <div class="sp-brand-title">Site Plan · <?= self::e($statement['statement_name'] ?? '') ?></div>
                        <div class="sp-brand-sub">Schematic layout · zones proportioned to actual sqm · <?= $statement['selected_regulation_key'] ? 'DCPR ' . self::e($statement['selected_regulation_key']) : 'no regulation' ?></div>
                    </div>
                </div>
                <div class="sp-toolbar-actions">
                    <div class="sp-view-toggle" role="tablist">
                        <button data-view="combined" class="sp-view-btn is-active" type="button">Combined</button>
                        <button data-view="plot" class="sp-view-btn" type="button">Plot</button>
                        <button data-view="massing" class="sp-view-btn" type="button">Massing</button>
                    </div>
                    <button class="sp-reset-btn" type="button" title="Reset focus">Reset</button>
                </div>
            </div>

            <div class="sp-canvas" data-view="combined">
                <!-- LEFT: Plot panel -->
                <div class="sp-panel sp-panel-plot">
                    <?= self::renderPlotSVG($id, $gross, $road, $reserv, $amenity, $other, $net, $buildings, $totalBua, $rehab, $sale, $zones) ?>
                </div>

                <!-- RIGHT: Massing panel -->
                <div class="sp-panel sp-panel-massing">
                    <?= self::renderMassingSVG($id, $totalBua, $rehab, $sale, $handover, $statement, $dev) ?>
                </div>

                <!-- Floating tooltip (position controlled by JS) -->
                <div class="sp-tooltip" hidden>
                    <div class="sp-tt-title"></div>
                    <div class="sp-tt-body"></div>
                </div>

                <!-- Focus detail card (shown when a zone is clicked) -->
                <div class="sp-detail" hidden>
                    <div class="sp-detail-head">
                        <span class="sp-detail-swatch"></span>
                        <div>
                            <div class="sp-detail-title"></div>
                            <div class="sp-detail-sub"></div>
                        </div>
                        <button class="sp-detail-close" type="button">✕</button>
                    </div>
                    <div class="sp-detail-metrics"></div>
                    <div class="sp-detail-formula"></div>
                    <div class="sp-detail-ref"></div>
                </div>
            </div>

            <div class="sp-legend">
                <?php foreach ($zones as $z): if ($z['area'] <= 0 && $z['key'] !== 'net_plot') continue; ?>
                    <span class="sp-legend-item" data-zone="<?= self::e($z['key']) ?>">
                        <span class="sp-swatch" style="background: <?= self::e($z['color']) ?>;"></span>
                        <span class="sp-legend-lbl"><?= self::e($z['label']) ?></span>
                        <span class="sp-legend-val"><?= self::fmtSqm($z['area']) ?></span>
                    </span>
                <?php endforeach; ?>
            </div>

            <?= self::script($id, $zones, $statement, $totalBua, $rehab, $sale, $handover, $dev) ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // ─── Data ────────────────────────────────────────────

    private static function zoneMeta(float $gross, float $road, float $reserv, float $amenity, float $other, float $net): array {
        $ratio = fn($v) => $gross > 0 ? $v / $gross * 100 : 0;
        return [
            ['key'=>'gross_plot',     'label'=>'Gross Plot',       'area'=>$gross,   'ratio'=>100.0,          'color'=>'#a5b4fc', 'ref'=>'Reg 30(A)(1)',            'formula'=>'User input · property card'],
            ['key'=>'road_deduction', 'label'=>'Road / Setback',   'area'=>$road,    'ratio'=>$ratio($road),  'color'=>'#fbbf24', 'ref'=>'Reg 30(A)(2)(a)',        'formula'=>'DP road setback / regular line'],
            ['key'=>'reservation',    'label'=>'Reservation',      'area'=>$reserv,  'ratio'=>$ratio($reserv),'color'=>'#f87171', 'ref'=>'Reg 17(1)',              'formula'=>'Public amenity reservation'],
            ['key'=>'amenity',        'label'=>'Amenity Handover', 'area'=>$amenity, 'ratio'=>$ratio($amenity),'color'=>'#60a5fa','ref'=>'Reg 14',                  'formula'=>'Amenity space handover'],
            ['key'=>'other_deduction','label'=>'Other Deduction',  'area'=>$other,   'ratio'=>$ratio($other), 'color'=>'#c084fc', 'ref'=>'CRZ / defence / forest', 'formula'=>'Statutory deduction'],
            ['key'=>'net_plot',       'label'=>'Net Plot (FSI)',   'area'=>$net,     'ratio'=>$ratio($net),   'color'=>'#34d399', 'ref'=>'Reg 30 read w/ Reg 32',   'formula'=>'Gross − (Road + Reservation + Amenity + Other)'],
        ];
    }

    // ─── Plot panel ──────────────────────────────────────

    private static function renderPlotSVG(
        string $id, float $gross, float $road, float $reserv, float $amenity, float $other, float $net,
        array $buildings, float $totalBua, float $rehab, float $sale, array $zones
    ): string {
        $W = 620; $H = 480;
        $svg = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" xmlns="http://www.w3.org/2000/svg" class="sp-svg sp-svg-plot" role="img">';

        // Definitions: gradients + drop-shadow filter
        $svg .= '<defs>'
            . '<filter id="' . $id . '-shadow" x="-20%" y="-20%" width="140%" height="140%">'
            . '  <feGaussianBlur in="SourceAlpha" stdDeviation="2"/><feOffset dy="2"/>'
            . '  <feComponentTransfer><feFuncA type="linear" slope="0.35"/></feComponentTransfer>'
            . '  <feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge>'
            . '</filter>';
        // Gradients per zone (subtle)
        foreach ([['road','#fbbf24','#f59e0b'],['reserv','#f87171','#dc2626'],['amenity','#60a5fa','#2563eb'],
                  ['other','#c084fc','#7c3aed'],['net','#34d399','#059669'],['bldg','#4338ca','#1e1b4b']] as [$k,$c1,$c2]) {
            $svg .= '<linearGradient id="' . $id . '-g-' . $k . '" x1="0" y1="0" x2="0" y2="1">'
                 .  '<stop offset="0" stop-color="' . $c1 . '" stop-opacity="0.95"/>'
                 .  '<stop offset="1" stop-color="' . $c2 . '" stop-opacity="0.75"/>'
                 .  '</linearGradient>';
        }
        // Cross-hatch pattern (for reservation zones — visual weight)
        $svg .= '<pattern id="' . $id . '-hatch" width="8" height="8" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">'
             .  '<line x1="0" y1="0" x2="0" y2="8" stroke="rgba(220,38,38,0.35)" stroke-width="1.5"/></pattern>';
        $svg .= '</defs>';

        // Plot boundary rectangle — leave room for compass + scale
        $marginX = 30; $marginY = 40;
        $plotW   = $W - 2 * $marginX;
        $plotH   = $H - $marginY - 60;

        // Outer boundary + faint grid
        $svg .= '<rect x="' . $marginX . '" y="' . $marginY . '" width="' . $plotW . '" height="' . $plotH
              . '" fill="#f8fafc" stroke="#0f172a" stroke-width="2.5" filter="url(#' . $id . '-shadow)"/>';
        for ($gx = $marginX + 40; $gx < $marginX + $plotW; $gx += 40) {
            $svg .= '<line x1="' . $gx . '" y1="' . $marginY . '" x2="' . $gx . '" y2="' . ($marginY + $plotH) . '" stroke="rgba(15,23,42,0.06)" stroke-width="1"/>';
        }
        for ($gy = $marginY + 40; $gy < $marginY + $plotH; $gy += 40) {
            $svg .= '<line x1="' . $marginX . '" y1="' . $gy . '" x2="' . ($marginX + $plotW) . '" y2="' . $gy . '" stroke="rgba(15,23,42,0.06)" stroke-width="1"/>';
        }

        // Deduction zones — proportional to area
        $cx = $marginX; $cy = $marginY; $cw = $plotW; $ch = $plotH;
        if ($gross > 0) {
            // Road strip (top) — height ∝ road/gross
            if ($road > 0.005 * $gross) {
                $rH = max(28, min($plotH * 0.35, $plotH * ($road / $gross) * 2.2));
                $svg .= '<g class="sp-zone" data-zone="road_deduction">';
                $svg .= '<rect x="' . $cx . '" y="' . $cy . '" width="' . $cw . '" height="' . $rH . '" fill="url(#' . $id . '-g-road)" rx="1"/>';
                // "asphalt" dashed centre line
                $svg .= '<line x1="' . ($cx + 12) . '" y1="' . ($cy + $rH / 2) . '" x2="' . ($cx + $cw - 12) . '" y2="' . ($cy + $rH / 2) . '" stroke="rgba(255,255,255,0.85)" stroke-width="2" stroke-dasharray="14 10"/>';
                $svg .= self::zoneLabel($cx + $cw / 2, $cy + $rH / 2, 'Road / Setback', self::fmtSqm($road));
                $svg .= '</g>';
                $cy += $rH; $ch -= $rH;
            }
            // Reservation strip (right)
            if ($reserv > 0.005 * $gross) {
                $rW = max(50, min($cw * 0.30, $cw * ($reserv / $gross) * 2.5));
                $svg .= '<g class="sp-zone" data-zone="reservation">';
                $svg .= '<rect x="' . ($cx + $cw - $rW) . '" y="' . $cy . '" width="' . $rW . '" height="' . $ch . '" fill="url(#' . $id . '-g-reserv)" rx="1"/>';
                $svg .= '<rect x="' . ($cx + $cw - $rW) . '" y="' . $cy . '" width="' . $rW . '" height="' . $ch . '" fill="url(#' . $id . '-hatch)"/>';
                $svg .= self::zoneLabel($cx + $cw - $rW / 2, $cy + $ch / 2, 'Reservation', self::fmtSqm($reserv));
                $svg .= '</g>';
                $cw -= $rW;
            }
            // Amenity corner
            if ($amenity > 0.002 * $gross) {
                $rH = max(24, min($ch * 0.25, $ch * ($amenity / $gross) * 4));
                $rW = max(90, min($cw * 0.30, $cw * ($amenity / $gross) * 5));
                $svg .= '<g class="sp-zone" data-zone="amenity">';
                $svg .= '<rect x="' . $cx . '" y="' . ($cy + $ch - $rH) . '" width="' . $rW . '" height="' . $rH . '" fill="url(#' . $id . '-g-amenity)" rx="4"/>';
                $svg .= self::zoneLabel($cx + $rW / 2, $cy + $ch - $rH / 2, 'Amenity', self::fmtSqm($amenity));
                $svg .= '</g>';
            }
            // Other deduction corner
            if ($other > 0.002 * $gross) {
                $rH = max(20, min($ch * 0.18, $ch * ($other / $gross) * 4));
                $rW = max(70, min($cw * 0.20, $cw * ($other / $gross) * 5));
                $svg .= '<g class="sp-zone" data-zone="other_deduction">';
                $svg .= '<rect x="' . ($cx + $cw - $rW) . '" y="' . ($cy + $ch - $rH) . '" width="' . $rW . '" height="' . $rH . '" fill="url(#' . $id . '-g-other)" rx="4"/>';
                $svg .= self::zoneLabel($cx + $cw - $rW / 2, $cy + $ch - $rH / 2, 'Other', self::fmtSqm($other));
                $svg .= '</g>';
            }

            // Net plot — the developable canvas (centered in remaining region)
            $netW = max(140, $cw * 0.62);
            $netH = max(100, $ch * 0.62);
            $netX = $cx + ($cw - $netW) / 2;
            $netY = $cy + ($ch - $netH) / 2;
            $svg .= '<g class="sp-zone sp-net-zone" data-zone="net_plot">';
            $svg .= '<rect x="' . $netX . '" y="' . $netY . '" width="' . $netW . '" height="' . $netH
                  . '" fill="url(#' . $id . '-g-net)" stroke="#065f46" stroke-width="1.5" rx="4"/>';
            // Compass rose inside top-right of net plot
            $svg .= self::compass($id, $netX + $netW - 32, $netY + 30);

            // Building footprints inside net plot — one block per existing building (or single block if none)
            $svg .= self::renderBuildings($id, $netX, $netY, $netW, $netH, $buildings, $totalBua, $rehab, $sale);

            // Net plot label at top-left inside
            $svg .= '<text x="' . ($netX + 12) . '" y="' . ($netY + 20) . '" class="sp-net-label">NET PLOT · ' . self::fmtSqm($net) . '</text>';
            $svg .= '</g>';
        }

        // Scale bar (bottom left) — arbitrary 100m ↔ 1/6 of plot width
        $sbY = $marginY + $plotH + 22;
        $sbLen = $plotW / 6;
        $svg .= '<g class="sp-scale">';
        $svg .= '<text x="' . $marginX . '" y="' . ($sbY - 4) . '" class="sp-scale-lbl">Scale (schematic)</text>';
        $svg .= '<rect x="' . $marginX . '" y="' . $sbY . '" width="' . ($sbLen / 2) . '" height="8" fill="#0f172a"/>';
        $svg .= '<rect x="' . ($marginX + $sbLen / 2) . '" y="' . $sbY . '" width="' . ($sbLen / 2) . '" height="8" fill="#fff" stroke="#0f172a"/>';
        $svg .= '<text x="' . $marginX . '" y="' . ($sbY + 22) . '" class="sp-scale-num">0</text>';
        $svg .= '<text x="' . ($marginX + $sbLen / 2 - 6) . '" y="' . ($sbY + 22) . '" class="sp-scale-num">50 m</text>';
        $svg .= '<text x="' . ($marginX + $sbLen - 14) . '" y="' . ($sbY + 22) . '" class="sp-scale-num">100 m</text>';
        $svg .= '</g>';

        // Corner marker with plot totals (bottom-right)
        $totX = $marginX + $plotW - 220; $totY = $sbY;
        $svg .= '<text x="' . ($totX + 210) . '" y="' . ($totY + 8) . '" class="sp-scale-lbl" text-anchor="end">Gross ' . self::fmtSqm($gross) . '  →  Net ' . self::fmtSqm($net) . '</text>';

        $svg .= '</svg>';
        return $svg;
    }

    private static function renderBuildings(string $id, float $x, float $y, float $w, float $h,
                                            array $buildings, float $totalBua, float $rehab, float $sale): string {
        if ($totalBua <= 0) return '';
        $padding = 20;
        $ix = $x + $padding; $iy = $y + $padding + 24; // leave room for the net-plot label
        $iw = $w - 2 * $padding; $ih = $h - $padding - 44;
        if ($iw < 40 || $ih < 30) return '';

        // Decide: use per-building rows OR a two-block rehab/sale representation
        $svg = '';
        if (!empty($buildings) && count($buildings) <= 6) {
            // One block per existing building, sized proportionally to existing BUA
            $totalExist = 0;
            foreach ($buildings as $b) $totalExist += (float)$b['existing_bua_sqm'];
            if ($totalExist <= 0) $totalExist = count($buildings); // fallback: equal blocks
            $cols = min(3, count($buildings));
            $rows = (int)ceil(count($buildings) / $cols);
            $cellW = ($iw - ($cols - 1) * 8) / $cols;
            $cellH = min(($ih - ($rows - 1) * 8) / $rows, 90);
            foreach ($buildings as $i => $b) {
                $col = $i % $cols; $row = intdiv($i, $cols);
                $bx = $ix + $col * ($cellW + 8);
                $by = $iy + $row * ($cellH + 8);
                $svg .= '<g class="sp-building">';
                $svg .= '<rect x="' . $bx . '" y="' . $by . '" width="' . $cellW . '" height="' . $cellH
                      . '" fill="url(#' . $id . '-g-bldg)" rx="3" stroke="#312e81" stroke-width="0.8"/>';
                // Faint window grid to hint at floors
                for ($fy = $by + 8; $fy < $by + $cellH - 4; $fy += 10) {
                    for ($fx = $bx + 6; $fx < $bx + $cellW - 4; $fx += 8) {
                        $svg .= '<rect x="' . $fx . '" y="' . $fy . '" width="4" height="5" fill="rgba(255,255,255,0.35)"/>';
                    }
                }
                $label = self::e((string)($b['building_label'] ?? ('Bldg ' . ($i + 1))));
                $svg .= '<text x="' . ($bx + $cellW / 2) . '" y="' . ($by + $cellH + 12) . '" class="sp-bldg-lbl">' . $label . '</text>';
                $svg .= '</g>';
            }
        } else {
            // Two-block schematic: Rehab tower + Sale tower, widths proportional
            $tot = ($rehab + $sale) ?: $totalBua;
            $rehabW = $iw * ($rehab / $tot);
            $saleW  = $iw - $rehabW - 12;
            $blkH = min(120, $ih - 20);
            $by = $iy + ($ih - $blkH) / 2;
            if ($rehab > 0) {
                $svg .= '<g class="sp-building">';
                $svg .= '<rect x="' . $ix . '" y="' . $by . '" width="' . $rehabW . '" height="' . $blkH
                      . '" fill="#059669" rx="3" stroke="#065f46" stroke-width="0.8"/>';
                for ($fy = $by + 8; $fy < $by + $blkH - 4; $fy += 10) {
                    for ($fx = $ix + 6; $fx < $ix + $rehabW - 4; $fx += 8) {
                        $svg .= '<rect x="' . $fx . '" y="' . $fy . '" width="4" height="5" fill="rgba(255,255,255,0.35)"/>';
                    }
                }
                $svg .= '<text x="' . ($ix + $rehabW / 2) . '" y="' . ($by + $blkH + 14) . '" class="sp-bldg-lbl">Rehab Towers · ' . self::fmtSqm($rehab) . '</text>';
                $svg .= '</g>';
            }
            if ($sale > 0) {
                $svg .= '<g class="sp-building">';
                $svg .= '<rect x="' . ($ix + $rehabW + 12) . '" y="' . $by . '" width="' . $saleW . '" height="' . $blkH
                      . '" fill="#4338ca" rx="3" stroke="#1e1b4b" stroke-width="0.8"/>';
                for ($fy = $by + 8; $fy < $by + $blkH - 4; $fy += 10) {
                    for ($fx = $ix + $rehabW + 18; $fx < $ix + $rehabW + 12 + $saleW - 4; $fx += 8) {
                        $svg .= '<rect x="' . $fx . '" y="' . $fy . '" width="4" height="5" fill="rgba(255,255,255,0.35)"/>';
                    }
                }
                $svg .= '<text x="' . ($ix + $rehabW + 12 + $saleW / 2) . '" y="' . ($by + $blkH + 14) . '" class="sp-bldg-lbl">Sale Towers · ' . self::fmtSqm($sale) . '</text>';
                $svg .= '</g>';
            }
        }
        return $svg;
    }

    private static function compass(string $id, float $cx, float $cy): string {
        $r = 22;
        return '<g class="sp-compass">'
             . '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="rgba(255,255,255,0.88)" stroke="#0f172a" stroke-width="1.2"/>'
             . '<polygon points="' . $cx . ',' . ($cy - $r + 4) . ' ' . ($cx - 6) . ',' . $cy . ' ' . ($cx + 6) . ',' . $cy . '" fill="#dc2626"/>'
             . '<polygon points="' . $cx . ',' . ($cy + $r - 4) . ' ' . ($cx - 6) . ',' . $cy . ' ' . ($cx + 6) . ',' . $cy . '" fill="#0f172a"/>'
             . '<text x="' . $cx . '" y="' . ($cy - $r + 2) . '" class="sp-compass-lbl">N</text>'
             . '</g>';
    }

    // ─── Massing panel ───────────────────────────────────

    private static function renderMassingSVG(string $id, float $totalBua, float $rehab, float $sale, float $handover,
                                             array $statement, array $dev): string {
        $W = 360; $H = 480;
        $svg = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" xmlns="http://www.w3.org/2000/svg" class="sp-svg sp-svg-massing" role="img">';
        $svg .= '<defs>';
        foreach ([['rehab','#10b981','#065f46'],['sale','#6366f1','#312e81'],['handover','#94a3b8','#334155']] as [$k,$c1,$c2]) {
            $svg .= '<linearGradient id="' . $id . '-m-' . $k . '" x1="0" y1="0" x2="0" y2="1">'
                 .  '<stop offset="0" stop-color="' . $c1 . '"/>'
                 .  '<stop offset="1" stop-color="' . $c2 . '"/></linearGradient>';
        }
        $svg .= '</defs>';

        $svg .= '<text x="20" y="26" class="sp-panel-title">BUA Massing</text>';
        $svg .= '<text x="20" y="44" class="sp-panel-sub">' . ($totalBua > 0 ? 'Total ' . self::fmtSqm($totalBua) : 'Awaiting calc') . '</text>';

        if ($totalBua <= 0) {
            $svg .= '<text x="' . ($W / 2) . '" y="' . ($H / 2) . '" text-anchor="middle" class="sp-panel-sub">Run Step 5 to see massing</text>';
            $svg .= '</svg>';
            return $svg;
        }

        $barX = 100; $barW = 160;
        $barTop = 70; $barBottom = $H - 60;
        $barH = $barBottom - $barTop;

        $totalStack = $rehab + $sale + $handover;
        if ($totalStack <= 0) { $totalStack = $totalBua; $sale = $totalBua; }

        $handoverAuth = self::pickHandoverAuthority($dev) ?: 'Authority';
        $segments = [
            ['label'=>'Rehab',       'value'=>$rehab,    'grad'=>'rehab',    'zone'=>'m_rehab'],
            ['label'=>'Sale / Owner','value'=>$sale,     'grad'=>'sale',     'zone'=>'m_sale'],
            ['label'=>"Handover · $handoverAuth", 'value'=>$handover, 'grad'=>'handover', 'zone'=>'m_handover'],
        ];
        $y = $barTop;
        foreach ($segments as $seg) {
            if ($seg['value'] <= 0) continue;
            $h = $barH * ($seg['value'] / $totalStack);
            $svg .= '<g class="sp-mass-seg" data-zone="' . $seg['zone'] . '">';
            $svg .= '<rect x="' . $barX . '" y="' . $y . '" width="' . $barW . '" height="' . $h
                  . '" fill="url(#' . $id . '-m-' . $seg['grad'] . ')" rx="2"/>';
            // Callout line + label to right
            $midY = $y + $h / 2;
            $svg .= '<line x1="' . ($barX + $barW) . '" y1="' . $midY . '" x2="' . ($barX + $barW + 30) . '" y2="' . $midY . '" stroke="rgba(15,23,42,0.35)" stroke-width="1"/>';
            $svg .= '<text x="' . ($barX + $barW + 36) . '" y="' . ($midY - 2) . '" class="sp-mass-lbl">' . self::e($seg['label']) . '</text>';
            $svg .= '<text x="' . ($barX + $barW + 36) . '" y="' . ($midY + 14) . '" class="sp-mass-val">'
                 .  self::fmtSqm($seg['value']) . '  ·  ' . number_format($seg['value'] / $totalStack * 100, 0) . '%</text>';
            if ($h > 24) {
                $svg .= '<text x="' . ($barX + $barW / 2) . '" y="' . ($midY + 4) . '" class="sp-mass-inline">' . number_format($seg['value'] / $totalStack * 100, 0) . '%</text>';
            }
            $svg .= '</g>';
            $y += $h;
        }
        // Baseline + total tick
        $svg .= '<line x1="' . $barX . '" y1="' . $barBottom . '" x2="' . ($barX + $barW) . '" y2="' . $barBottom
              . '" stroke="#0f172a" stroke-width="1.4"/>';
        $svg .= '<text x="' . ($barX + $barW / 2) . '" y="' . ($barBottom + 20) . '" text-anchor="middle" class="sp-mass-lbl">Total ' . self::fmtSqm($totalStack) . '</text>';

        $svg .= '</svg>';
        return $svg;
    }

    private static function pickHandoverAuthority(array $dev): string {
        foreach ($dev as $r) {
            $h = (string)($r['handover_to'] ?? '');
            if (in_array($h, ['SRA','MHADA','MCGM'], true)) return $h;
        }
        return '';
    }

    // ─── Style + Script ──────────────────────────────────

    private static function styles(string $id): string {
        return <<<CSS
<style>
#{$id}-root {
    --sp-bg: #ffffff;
    --sp-panel: #f8fafc;
    --sp-border: #e5e7eb;
    --sp-ink: #0f172a;
    --sp-muted: #64748b;
    --sp-accent: #4f46e5;
    --sp-radius: 14px;
    font-family: 'Lexend', system-ui, -apple-system, sans-serif;
    background: var(--sp-bg);
    border: 1px solid var(--sp-border);
    border-radius: var(--sp-radius);
    overflow: hidden;
    position: relative;
    box-shadow: 0 4px 12px rgba(15,23,42,0.05);
}
#{$id}-root .sp-toolbar {
    display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;
    padding: 14px 18px; border-bottom: 1px solid var(--sp-border);
    background: linear-gradient(180deg, #ffffff, #f8fafc);
    gap: 10px;
}
#{$id}-root .sp-brand { display: flex; align-items: center; gap: 12px; }
#{$id}-root .sp-brand-icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 34px; height: 34px; border-radius: 8px;
    background: linear-gradient(135deg, #4f46e5, #6366f1); color: #fff;
    font-size: 18px; font-weight: 700;
}
#{$id}-root .sp-brand-title { font-weight: 800; font-size: 0.9rem; color: var(--sp-ink); letter-spacing: -0.01em; }
#{$id}-root .sp-brand-sub   { font-size: 0.7rem; color: var(--sp-muted); margin-top: 2px; }
#{$id}-root .sp-toolbar-actions { display: flex; gap: 10px; align-items: center; }
#{$id}-root .sp-view-toggle {
    display: inline-flex; background: #f1f5f9; border-radius: 8px; padding: 3px;
    border: 1px solid var(--sp-border);
}
#{$id}-root .sp-view-btn {
    background: transparent; color: var(--sp-muted);
    border: 0; padding: 5px 12px; border-radius: 6px; cursor: pointer;
    font-size: 0.72rem; font-weight: 700; font-family: inherit;
    transition: all .18s ease;
}
#{$id}-root .sp-view-btn:hover { color: var(--sp-ink); }
#{$id}-root .sp-view-btn.is-active {
    background: #fff; color: var(--sp-accent);
    box-shadow: 0 1px 3px rgba(15,23,42,0.1);
}
#{$id}-root .sp-reset-btn {
    background: #fff; border: 1px solid var(--sp-border); color: var(--sp-muted);
    padding: 5px 12px; border-radius: 6px; cursor: pointer;
    font-size: 0.72rem; font-weight: 700; font-family: inherit;
}
#{$id}-root .sp-reset-btn:hover { color: var(--sp-accent); border-color: var(--sp-accent); }

#{$id}-root .sp-canvas {
    position: relative;
    display: grid;
    grid-template-columns: 1.7fr 1fr;
    gap: 0;
    min-height: 480px;
    background: var(--sp-panel);
}
#{$id}-root .sp-canvas[data-view="plot"]    { grid-template-columns: 1fr; }
#{$id}-root .sp-canvas[data-view="plot"]    .sp-panel-massing { display: none; }
#{$id}-root .sp-canvas[data-view="massing"] { grid-template-columns: 1fr; }
#{$id}-root .sp-canvas[data-view="massing"] .sp-panel-plot { display: none; }
#{$id}-root .sp-panel { padding: 12px 14px; }
#{$id}-root .sp-panel-plot    { border-right: 1px solid var(--sp-border); }
#{$id}-root .sp-canvas[data-view="massing"] .sp-panel-plot,
#{$id}-root .sp-canvas[data-view="plot"] .sp-panel-massing { border-right: 0; }

#{$id}-root .sp-svg { width: 100%; height: auto; display: block; }

/* Zones */
#{$id}-root .sp-zone,
#{$id}-root .sp-mass-seg {
    cursor: pointer;
    transition: opacity .2s ease, transform .2s ease;
    transform-origin: center;
}
#{$id}-root .sp-zone rect,
#{$id}-root .sp-mass-seg rect { transition: filter .2s ease; }
#{$id}-root .sp-zone:hover rect,
#{$id}-root .sp-mass-seg:hover rect { filter: brightness(1.08); }
#{$id}-root .sp-canvas.has-focus .sp-zone:not(.is-focus) { opacity: 0.28; }
#{$id}-root .sp-canvas.has-focus .sp-mass-seg:not(.is-focus) { opacity: 0.28; }
#{$id}-root .sp-zone.is-focus rect { filter: brightness(1.05) drop-shadow(0 3px 6px rgba(15,23,42,0.15)); }

/* Zone labels (inside SVG) */
#{$id}-root .sp-zone-lbl,
#{$id}-root .sp-zone-val,
#{$id}-root .sp-net-label,
#{$id}-root .sp-bldg-lbl,
#{$id}-root .sp-scale-lbl,
#{$id}-root .sp-scale-num,
#{$id}-root .sp-compass-lbl,
#{$id}-root .sp-panel-title,
#{$id}-root .sp-panel-sub,
#{$id}-root .sp-mass-lbl,
#{$id}-root .sp-mass-val,
#{$id}-root .sp-mass-inline {
    font-family: 'Lexend', system-ui, -apple-system, sans-serif;
    pointer-events: none;
    user-select: none;
}
#{$id}-root .sp-zone-lbl { font-size: 12px; font-weight: 700; fill: #1e293b; text-anchor: middle; text-shadow: 0 1px 0 rgba(255,255,255,0.6); }
#{$id}-root .sp-zone-val { font-size: 10px; fill: #334155; text-anchor: middle; text-shadow: 0 1px 0 rgba(255,255,255,0.6); }
#{$id}-root .sp-net-label { font-size: 11px; font-weight: 800; fill: #064e3b; letter-spacing: 0.06em; }
#{$id}-root .sp-bldg-lbl { font-size: 10px; fill: #334155; text-anchor: middle; font-weight: 600; }
#{$id}-root .sp-scale-lbl { font-size: 9px; fill: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; }
#{$id}-root .sp-scale-num { font-size: 10px; fill: #334155; font-weight: 600; }
#{$id}-root .sp-compass-lbl { font-size: 8px; fill: #dc2626; font-weight: 800; text-anchor: middle; }
#{$id}-root .sp-panel-title { font-size: 15px; font-weight: 800; fill: #0f172a; letter-spacing: -0.01em; }
#{$id}-root .sp-panel-sub { font-size: 11px; fill: #64748b; }
#{$id}-root .sp-mass-lbl { font-size: 11px; font-weight: 700; fill: #0f172a; }
#{$id}-root .sp-mass-val { font-size: 10px; fill: #64748b; font-family: 'JetBrains Mono', Consolas, monospace; }
#{$id}-root .sp-mass-inline { font-size: 13px; font-weight: 800; fill: rgba(255,255,255,0.9); text-anchor: middle; }

/* Tooltip */
#{$id}-root .sp-tooltip {
    position: absolute; z-index: 20; pointer-events: none;
    background: rgba(15,23,42,0.95); color: #fff;
    padding: 8px 12px; border-radius: 8px; font-size: 0.78rem;
    box-shadow: 0 6px 20px rgba(15,23,42,0.4);
    max-width: 240px;
    transform: translate(-50%, calc(-100% - 10px));
    transition: opacity .12s ease;
}
#{$id}-root .sp-tooltip::after {
    content: ''; position: absolute; left: 50%; bottom: -6px;
    margin-left: -6px; border: 6px solid transparent;
    border-top-color: rgba(15,23,42,0.95); border-bottom: 0;
}
#{$id}-root .sp-tt-title { font-weight: 700; margin-bottom: 3px; }
#{$id}-root .sp-tt-body  { font-size: 0.72rem; color: #cbd5e1; line-height: 1.4; }
#{$id}-root .sp-tt-body b { color: #fff; font-family: 'JetBrains Mono', Consolas, monospace; }

/* Focus detail card */
#{$id}-root .sp-detail {
    position: absolute; top: 12px; right: 12px; z-index: 15;
    width: 280px; background: #fff; border: 1px solid var(--sp-border);
    border-radius: 10px; box-shadow: 0 10px 30px rgba(15,23,42,0.15);
    padding: 14px 16px;
    animation: sp-slide-in .2s ease;
}
@keyframes sp-slide-in {
    from { opacity: 0; transform: translateX(8px); }
    to   { opacity: 1; transform: translateX(0); }
}
#{$id}-root .sp-detail-head { display: flex; align-items: center; gap: 10px; }
#{$id}-root .sp-detail-swatch { width: 20px; height: 20px; border-radius: 6px; }
#{$id}-root .sp-detail-title { font-weight: 800; font-size: 0.88rem; color: var(--sp-ink); }
#{$id}-root .sp-detail-sub   { font-size: 0.7rem; color: var(--sp-muted); }
#{$id}-root .sp-detail-close {
    margin-left: auto; background: #f1f5f9; border: 0; cursor: pointer;
    width: 24px; height: 24px; border-radius: 6px; color: var(--sp-muted);
    font-size: 0.9rem; font-weight: 700;
}
#{$id}-root .sp-detail-close:hover { background: #e2e8f0; color: var(--sp-ink); }
#{$id}-root .sp-detail-metrics {
    display: grid; grid-template-columns: 1fr 1fr; gap: 8px;
    background: #f8fafc; border-radius: 8px; padding: 10px 12px; margin: 12px 0 8px;
}
#{$id}-root .sp-detail-metrics .m { }
#{$id}-root .sp-detail-metrics .m-lbl {
    font-size: 0.66rem; text-transform: uppercase; letter-spacing: 0.06em;
    color: var(--sp-muted); font-weight: 700;
}
#{$id}-root .sp-detail-metrics .m-val {
    font-size: 0.95rem; font-weight: 800; color: var(--sp-ink);
    font-family: 'JetBrains Mono', Consolas, monospace; margin-top: 2px;
}
#{$id}-root .sp-detail-formula { font-size: 0.72rem; color: #334155; margin-top: 4px; }
#{$id}-root .sp-detail-ref {
    display: inline-block; background: #eef2ff; color: #4338ca;
    padding: 2px 10px; border-radius: 999px; font-size: 0.68rem;
    font-weight: 700; margin-top: 8px;
}

/* Legend */
#{$id}-root .sp-legend {
    display: flex; gap: 14px; flex-wrap: wrap; padding: 12px 18px;
    border-top: 1px solid var(--sp-border); background: #fafbfc;
}
#{$id}-root .sp-legend-item {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 0.75rem; color: var(--sp-muted);
    cursor: pointer; padding: 3px 8px; border-radius: 6px;
    transition: background .15s ease;
}
#{$id}-root .sp-legend-item:hover { background: #f1f5f9; color: var(--sp-ink); }
#{$id}-root .sp-legend-item.is-focus { background: #eef2ff; color: #4338ca; font-weight: 700; }
#{$id}-root .sp-swatch { width: 12px; height: 12px; border-radius: 3px; }
#{$id}-root .sp-legend-lbl { font-weight: 600; color: var(--sp-ink); }
#{$id}-root .sp-legend-val {
    font-family: 'JetBrains Mono', Consolas, monospace;
    font-size: 0.7rem; color: var(--sp-muted);
}

@media (max-width: 720px) {
    #{$id}-root .sp-canvas { grid-template-columns: 1fr; }
    #{$id}-root .sp-panel-plot { border-right: 0; border-bottom: 1px solid var(--sp-border); }
}
@media print {
    #{$id}-root .sp-toolbar-actions { display: none; }
    #{$id}-root .sp-tooltip, #{$id}-root .sp-detail { display: none !important; }
}
</style>
CSS;
    }

    private static function script(string $id, array $zones, array $statement, float $totalBua, float $rehab, float $sale, float $handover, array $dev): string {
        $data = [];
        foreach ($zones as $z) {
            $data[$z['key']] = [
                'label'   => $z['label'],
                'area'    => $z['area'],
                'ratio'   => $z['ratio'],
                'formula' => $z['formula'],
                'ref'     => $z['ref'],
                'color'   => $z['color'],
            ];
        }
        $handoverAuth = self::pickHandoverAuthority($dev) ?: 'Authority';
        $reg = $statement['selected_regulation_key'] ?? '';
        $total = $rehab + $sale + $handover ?: max($totalBua, 1);
        $data['m_rehab']    = ['label' => 'Rehabilitation BUA',        'area' => $rehab,    'ratio' => $rehab    / $total * 100, 'formula' => 'On-site rehab area including loading + fungible',           'ref' => "DCPR $reg",        'color' => '#10b981'];
        $data['m_sale']     = ['label' => 'Sale / Owner BUA',          'area' => $sale,     'ratio' => $sale     / $total * 100, 'formula' => 'Free-sale / owner-share developer keeps for market',       'ref' => "DCPR $reg",        'color' => '#6366f1'];
        $data['m_handover'] = ['label' => "Handover to {$handoverAuth}", 'area' => $handover, 'ratio' => $handover / $total * 100, 'formula' => 'Balance BUA transferred to authority free of cost',        'ref' => "DCPR $reg · Table C", 'color' => '#94a3b8'];

        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        return <<<JS
<script>
(function() {
    const root = document.getElementById('{$id}-root');
    if (!root) return;
    const data = {$json};
    const canvas = root.querySelector('.sp-canvas');
    const tooltip = root.querySelector('.sp-tooltip');
    const detail  = root.querySelector('.sp-detail');
    const detailSwatch = root.querySelector('.sp-detail-swatch');
    const detailTitle  = root.querySelector('.sp-detail-title');
    const detailSub    = root.querySelector('.sp-detail-sub');
    const detailMetrics= root.querySelector('.sp-detail-metrics');
    const detailFormula= root.querySelector('.sp-detail-formula');
    const detailRef    = root.querySelector('.sp-detail-ref');

    function fmtSqm(v) {
        if (!v || v <= 0) return '—';
        return v.toLocaleString('en-IN', {maximumFractionDigits: v > 100 ? 0 : 2}) + ' sqm';
    }

    // Hover tooltip
    function showTooltip(key, event) {
        const d = data[key];
        if (!d) return;
        tooltip.hidden = false;
        tooltip.querySelector('.sp-tt-title').textContent = d.label;
        tooltip.querySelector('.sp-tt-body').innerHTML =
            'Area <b>' + fmtSqm(d.area) + '</b><br>' +
            'Ratio <b>' + d.ratio.toFixed(1) + '%</b><br>' +
            '<span style="color:#a5b4fc;">' + d.ref + '</span>';
        const rect = root.getBoundingClientRect();
        tooltip.style.left = (event.clientX - rect.left) + 'px';
        tooltip.style.top  = (event.clientY - rect.top) + 'px';
    }
    function hideTooltip() { tooltip.hidden = true; }

    // Focus mode
    function focusZone(key) {
        canvas.classList.add('has-focus');
        canvas.querySelectorAll('.sp-zone, .sp-mass-seg').forEach(el => {
            el.classList.toggle('is-focus', el.dataset.zone === key);
        });
        root.querySelectorAll('.sp-legend-item').forEach(el => {
            el.classList.toggle('is-focus', el.dataset.zone === key);
        });
        const d = data[key];
        if (!d) return;
        detail.hidden = false;
        detailSwatch.style.background = d.color;
        detailTitle.textContent = d.label;
        detailSub.textContent   = d.ref;
        detailMetrics.innerHTML =
            '<div class="m"><div class="m-lbl">Area</div><div class="m-val">' + fmtSqm(d.area) + '</div></div>' +
            '<div class="m"><div class="m-lbl">Ratio</div><div class="m-val">' + d.ratio.toFixed(1) + '%</div></div>';
        detailFormula.textContent = d.formula || '';
        detailRef.textContent = d.ref;
    }
    function clearFocus() {
        canvas.classList.remove('has-focus');
        canvas.querySelectorAll('.is-focus').forEach(el => el.classList.remove('is-focus'));
        root.querySelectorAll('.sp-legend-item.is-focus').forEach(el => el.classList.remove('is-focus'));
        detail.hidden = true;
    }

    // Wire zones + massing segments + legend
    canvas.querySelectorAll('.sp-zone, .sp-mass-seg').forEach(el => {
        const key = el.dataset.zone;
        if (!key) return;
        el.addEventListener('mouseenter', ev => showTooltip(key, ev));
        el.addEventListener('mousemove',  ev => showTooltip(key, ev));
        el.addEventListener('mouseleave', hideTooltip);
        el.addEventListener('click', ev => { ev.stopPropagation(); focusZone(key); });
    });
    root.querySelectorAll('.sp-legend-item').forEach(el => {
        const key = el.dataset.zone;
        el.addEventListener('mouseenter', ev => showTooltip(key, ev));
        el.addEventListener('mouseleave', hideTooltip);
        el.addEventListener('click', () => focusZone(key));
    });

    // Toolbar
    root.querySelectorAll('.sp-view-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            root.querySelectorAll('.sp-view-btn').forEach(b => b.classList.remove('is-active'));
            btn.classList.add('is-active');
            canvas.dataset.view = btn.dataset.view;
        });
    });
    root.querySelector('.sp-reset-btn').addEventListener('click', clearFocus);
    root.querySelector('.sp-detail-close').addEventListener('click', clearFocus);
    // Click outside a zone/legend clears focus
    canvas.addEventListener('click', ev => {
        if (!ev.target.closest('.sp-zone, .sp-mass-seg, .sp-detail')) clearFocus();
    });
})();
</script>
JS;
    }

    // ─── Helpers ─────────────────────────────────────────

    private static function zoneLabel(float $x, float $y, string $title, string $val): string {
        return '<text x="' . round($x, 2) . '" y="' . round($y, 2) . '" class="sp-zone-lbl">' . self::e($title) . '</text>'
             . '<text x="' . round($x, 2) . '" y="' . round($y + 14, 2) . '" class="sp-zone-val">' . self::e($val) . '</text>';
    }

    private static function fmtSqm(float $v): string {
        if ($v <= 0) return '—';
        return number_format($v, $v > 100 ? 0 : 2) . ' sqm';
    }
    private static function e(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
