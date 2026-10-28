<?php $pageTitle = 'Financial Analysis · ' . e($statement['statement_name']); ?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php
$v = fn($k, $def = '') => htmlspecialchars((string)($financials[$k] ?? $def), ENT_QUOTES);
$curve = function ($col, $defArr, $N) use ($financials) {
    $raw = $financials[$col . '_json'] ?? null;
    if ($raw) { $d = json_decode($raw, true); if (is_array($d) && !empty($d)) return $d; }
    return array_slice(array_pad($defArr, $N, 0), 0, $N);
};
$N = max(3, (int)($financials['project_duration_years'] ?? 7));
$booking     = $curve('booking_curve',    [0, 0.15, 0.20, 0.20, 0.20, 0.20, 0.05], $N);
$collection  = $curve('collection_curve', [0.30, 0.30, 0.55, 0.70, 0.80, 0.90, 1.00], $N);
$rehabCost   = $curve('rehab_cost_curve', [0.20, 0.30, 0.25, 0.15, 0.10, 0, 0], $N);
$saleCost    = $curve('sale_cost_curve',  [0, 0.10, 0.20, 0.25, 0.25, 0.15, 0.05], $N);
$premium     = $curve('premium_curve',    [0.60, 0.20, 0.10, 0.05, 0.05, 0, 0], $N);
$curvePct = fn($x) => number_format((float)$x * 100, 1);
?>

<style>
    .fm-hero { background: linear-gradient(135deg, #065f46, #047857); color: #fff; border-radius: 14px; padding: 20px 24px; margin-bottom: 18px; }
    .fm-hero h5 { margin: 8px 0 0; font-weight: 800; }
    .fm-hero p  { margin: 4px 0 0; color: #d1fae5; font-size: 0.82rem; }
    .fm-hero .badge-reg { background: rgba(255,255,255,0.2); font-weight: 700; padding: 3px 12px; border-radius: 999px; font-size: 0.7rem; letter-spacing: .05em; }

    .fm-card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 18px 22px; background: #fff; margin-bottom: 16px; }
    .fm-card h6 { font-weight: 800; color: #065f46; text-transform: uppercase; letter-spacing: .06em; font-size: 0.72rem; margin: 0 0 4px; }
    .fm-help { color: #64748b; font-size: 0.78rem; margin: 0 0 14px; }
    .fm-label { font-weight: 600; color: #334155; font-size: 0.78rem; margin-bottom: 2px; }
    .fm-help-small { color: #64748b; font-size: 0.7rem; margin-top: 2px; }

    .snap-card { background: linear-gradient(135deg, #f0fdf4, #dcfce7); border: 1px solid #86efac; border-radius: 12px; padding: 14px 18px; margin-bottom: 14px; }
    .snap-card h6 { color: #065f46; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; font-size: 0.7rem; margin: 0 0 8px; }
    .snap-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; }
    .snap-item .lbl { color: #047857; font-size: 0.68rem; text-transform: uppercase; letter-spacing: .06em; font-weight: 700; }
    .snap-item .val { color: #064e3b; font-weight: 800; font-size: 0.95rem; font-family: 'JetBrains Mono', Consolas, monospace; }

    .curve-table { width: 100%; font-size: 0.78rem; margin-top: 8px; }
    .curve-table th, .curve-table td { text-align: center; padding: 4px 6px; }
    .curve-table td.label { text-align: left; font-weight: 600; color: #334155; }
    .curve-table input { text-align: right; font-family: 'JetBrains Mono', monospace; padding: 4px 6px; border: 1px solid #e5e7eb; border-radius: 4px; width: 100%; font-size: 0.78rem; }
    .curve-total { display: inline-block; padding: 2px 10px; border-radius: 999px; background: #f1f5f9; color: #475569; font-family: 'JetBrains Mono', monospace; font-size: 0.7rem; margin-left: 8px; }
</style>

<a href="index.php?page=area_analysis_report&statement_id=<?= (int)$statement['id'] ?>" class="text-muted small text-decoration-none">
    <i class="bi bi-arrow-left me-1"></i>Back to Area Statement
</a>

<div class="fm-hero mt-2">
    <span class="badge-reg">FINANCIAL ANALYSIS</span>
    <h5><?= e($statement['statement_name']) ?></h5>
    <p>Configure rates, escalation, curves, loan and overheads. Yearly cash flow + IRR computed on save.</p>
</div>

<!-- Snapshot from Area Statement -->
<div class="snap-card">
    <h6><i class="bi bi-link-45deg me-1"></i>Pre-filled from Area Statement (locked v<?= (int)$statement['version_major'] ?>.<?= (int)$statement['version_minor'] ?>)</h6>
    <div class="snap-grid">
        <div class="snap-item"><div class="lbl">Net Plot</div><div class="val"><?= $statement['net_plot_area_sqm'] ? number_format((float)$statement['net_plot_area_sqm'], 2) . ' sqm' : '—' ?></div></div>
        <div class="snap-item"><div class="lbl">Total FSI</div><div class="val"><?= $statement['total_permissible_fsi'] ? number_format((float)$statement['total_permissible_fsi'], 3) : '—' ?></div></div>
        <div class="snap-item"><div class="lbl">Total BUA</div><div class="val"><?= $statement['total_permissible_bua_sqm'] ? number_format((float)$statement['total_permissible_bua_sqm'], 0) . ' sqm' : '—' ?></div></div>
        <div class="snap-item"><div class="lbl">Rehab BUA</div><div class="val"><?= $statement['rehab_bua_sqm'] ? number_format((float)$statement['rehab_bua_sqm'], 0) . ' sqm' : '—' ?></div></div>
        <div class="snap-item"><div class="lbl">Sale BUA</div><div class="val"><?= $statement['sale_bua_sqm'] ? number_format((float)$statement['sale_bua_sqm'], 0) . ' sqm' : '—' ?></div></div>
        <div class="snap-item"><div class="lbl">Regulation</div><div class="val">DCPR <?= e($statement['selected_regulation_key']) ?></div></div>
    </div>
</div>

<style>
    /* Line-item tables */
    .li-tbl { width: 100%; font-size: 0.85rem; border-collapse: separate; border-spacing: 0; margin-top: 10px; }
    .li-tbl th { background: #f8fafc; color: #64748b; font-weight: 700; text-transform: uppercase; font-size: 0.66rem; letter-spacing: .04em; padding: 8px 6px; text-align: left; border-bottom: 1px solid #e5e7eb; }
    .li-tbl td { padding: 5px 4px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .li-tbl input, .li-tbl select { width: 100%; padding: 5px 8px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 0.82rem; font-family: inherit; }
    .li-tbl input:focus, .li-tbl select:focus { outline: none; border-color: #065f46; box-shadow: 0 0 0 2px rgba(6,95,70,0.1); }
    .li-tbl input.num { font-family: 'JetBrains Mono', Consolas, monospace; text-align: right; }
    .li-tbl input.amt { background: #f0fdf4; font-weight: 700; color: #065f46; }
    .li-add-btn { background: transparent; color: #065f46; border: 1px dashed #86efac; padding: 6px 14px; border-radius: 8px; font-size: 0.78rem; font-weight: 600; cursor: pointer; margin-top: 8px; }
    .li-add-btn:hover { background: #ecfdf5; border-style: solid; }
    .li-rm { color: #dc2626; background: transparent; border: 0; padding: 5px 8px; cursor: pointer; border-radius: 4px; }
    .li-rm:hover { background: #fee2e2; }
    .li-totals { display: flex; justify-content: flex-end; gap: 24px; padding: 10px 0; border-top: 2px solid #86efac; margin-top: 4px; }
    .li-totals .k { color: #065f46; font-weight: 700; text-transform: uppercase; font-size: 0.72rem; letter-spacing: .06em; }
    .li-totals .v { color: #064e3b; font-weight: 800; font-family: 'JetBrains Mono', Consolas, monospace; font-size: 1rem; }
</style>

<form method="POST" action="index.php?page=area_analysis_financials_save">
    <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">

    <?php
    /*
     * Helper to render one line-item table (Revenue / Premium / Society).
     * @param string $cat        line-items category
     * @param string $title      section heading
     * @param string $help       help text
     * @param array  $rows       existing rows for this category
     * @param array  $suggestions preset labels the user can pick to prefill
     * @param string $qtyLabel   column header for area/quantity (e.g. "Area (sqft)", "Tenants")
     * @param string $rateLabel  column header for rate (e.g. "Rate ₹/sqft", "₹/month")
     */
    $renderLineItemSection = function (string $cat, string $title, string $help, array $rows, array $suggestions,
                                       string $qtyLabel, string $rateLabel) {
        if (empty($rows)) {
            // Seed one blank row
            $rows = [['label'=>'','area_sqft'=>'','rate_inr'=>'','amount_inr'=>'']];
        }
        ?>
        <div class="fm-card">
            <h6><?= htmlspecialchars($title) ?></h6>
            <p class="fm-help"><?= htmlspecialchars($help) ?></p>

            <table class="li-tbl">
                <thead>
                    <tr>
                        <th style="width: 30%">Label</th>
                        <th style="width: 20%"><?= htmlspecialchars($qtyLabel) ?></th>
                        <th style="width: 20%"><?= htmlspecialchars($rateLabel) ?></th>
                        <th style="width: 20%">Amount (₹)</th>
                        <th style="width: 10%"></th>
                    </tr>
                </thead>
                <tbody data-li-body="<?= $cat ?>">
                    <?php foreach ($rows as $i => $r): ?>
                        <tr class="li-row" data-li-cat="<?= $cat ?>">
                            <td>
                                <input type="text" name="line_items[<?= $cat ?>][<?= $i ?>][label]"
                                       value="<?= htmlspecialchars((string)($r['label'] ?? '')) ?>"
                                       placeholder="e.g. <?= htmlspecialchars($suggestions[0] ?? 'Line item') ?>"
                                       list="li-sug-<?= $cat ?>">
                            </td>
                            <td>
                                <input type="number" step="any" name="line_items[<?= $cat ?>][<?= $i ?>][area_sqft]"
                                       class="num li-qty"
                                       value="<?= htmlspecialchars((string)($r['area_sqft'] ?? $r['quantity'] ?? '')) ?>">
                            </td>
                            <td>
                                <input type="number" step="any" name="line_items[<?= $cat ?>][<?= $i ?>][rate_inr]"
                                       class="num li-rate"
                                       value="<?= htmlspecialchars((string)($r['rate_inr'] ?? '')) ?>">
                                <input type="hidden" name="line_items[<?= $cat ?>][<?= $i ?>][formula_type]" value="area_x_rate">
                            </td>
                            <td>
                                <input type="number" step="any" name="line_items[<?= $cat ?>][<?= $i ?>][amount_inr]"
                                       class="num amt li-amount"
                                       value="<?= htmlspecialchars((string)($r['amount_inr'] ?? '')) ?>">
                            </td>
                            <td><button type="button" class="li-rm" onclick="liRemove(this)"><i class="bi bi-trash"></i></button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <datalist id="li-sug-<?= $cat ?>">
                <?php foreach ($suggestions as $s): ?><option value="<?= htmlspecialchars($s) ?>"><?php endforeach; ?>
            </datalist>
            <button type="button" class="li-add-btn" onclick="liAdd('<?= $cat ?>')"><i class="bi bi-plus-lg me-1"></i>Add row</button>
            <div class="li-totals">
                <span class="k">Total</span>
                <span class="v" data-li-total="<?= $cat ?>">₹0.00 Cr</span>
            </div>
        </div>
        <?php
    };

    $renderLineItemSection('revenue', 'A · Revenue components',
        'One row per revenue stream. Amount is auto-computed as Area × Rate when both are entered.',
        $lineItems['revenue'] ?? [],
        ['Residential Sale', 'Commercial Sale', 'Parking', 'Society Corpus (received)', 'Other'],
        'Area (sqft carpet)', 'Rate ₹/sqft');

    $renderLineItemSection('premium', 'B · Premiums &amp; statutory charges',
        'Layout deposit, CFO capitation, IOD deposit, fungible premium, etc. Rate × Area or a flat amount.',
        $lineItems['premium'] ?? [],
        ['Layout Deposit', 'CFO Capitation', 'CFO Scrutiny', 'IOD Deposit', 'Debris Deposit',
         'Development Charges', 'Infrastructure Charges', 'Labour Welfare Cess',
         'Staircase Premium', 'Fungible FSI Premium', 'Additional Land Premium'],
        'Area (sqft/sqm)', 'Rate ₹');

    $renderLineItemSection('society', 'C · Society &amp; rehab costs',
        'Rent, shifting, maintenance, corpus. Use "Qty × Rate" (e.g. tenants × ₹/month × months).',
        $lineItems['society'] ?? [],
        ['Rent (tenants × ₹/month × months)', 'Shifting Cost', 'Maintenance Deposit', 'Corpus Fund', 'Brokerage', 'Other Society'],
        'Quantity / Units', 'Rate ₹');
    ?>

    <div class="fm-card">
        <h6>Duration</h6>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="fm-label">Project Duration (years)</label>
                <input type="number" name="project_duration_years" class="form-control form-control-sm" value="<?= $v('project_duration_years', 7) ?>" min="3" max="15">
            </div>
        </div>
    </div>

    <div class="fm-card">
        <h6>Base-Year Rates &amp; Escalation</h6>
        <p class="fm-help">Y1 rates auto-escalate at the annual escalation rate. Sale rate feeds revenue booking; construction rates feed cost.</p>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="fm-label">Sale Rate Y1 (₹/sqft carpet)</label>
                <input type="number" step="any" name="sale_rate_y1_inr_sqft" class="form-control form-control-sm" value="<?= $v('sale_rate_y1_inr_sqft') ?>" placeholder="e.g. 20000">
            </div>
            <div class="col-md-3">
                <label class="fm-label">Rehab Construction Y1 (₹/sqft)</label>
                <input type="number" step="any" name="rehab_construction_rate_y1_inr_sqft" class="form-control form-control-sm" value="<?= $v('rehab_construction_rate_y1_inr_sqft') ?>" placeholder="e.g. 3800">
            </div>
            <div class="col-md-3">
                <label class="fm-label">Sale Construction Y1 (₹/sqft)</label>
                <input type="number" step="any" name="sale_construction_rate_y1_inr_sqft" class="form-control form-control-sm" value="<?= $v('sale_construction_rate_y1_inr_sqft') ?>" placeholder="e.g. 4500">
            </div>
            <div class="col-md-3">
                <label class="fm-label">Annual Escalation (%)</label>
                <input type="number" step="any" name="annual_escalation_pct" class="form-control form-control-sm" value="<?= $v('annual_escalation_pct', 5) ?>">
            </div>
            <div class="col-md-3">
                <label class="fm-label">RERA Carpet Loading (%)</label>
                <input type="number" step="any" name="rera_carpet_loading_pct" class="form-control form-control-sm" value="<?= $v('rera_carpet_loading_pct', 88) ?>">
                <div class="fm-help-small">RERA Carpet = Sale BUA × this / 100</div>
            </div>
            <div class="col-md-3">
                <label class="fm-label">RERA Carpet Override (sqft)</label>
                <input type="number" step="any" name="saleable_rera_carpet_sqft_override" class="form-control form-control-sm" value="<?= $v('saleable_rera_carpet_sqft_override', '') ?>" placeholder="auto">
                <div class="fm-help-small">Leave blank to auto-derive</div>
            </div>
        </div>
    </div>

    <div class="fm-card">
        <h6>Loan</h6>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="fm-label">Loan Principal (₹)</label>
                <input type="number" step="any" name="loan_principal_inr" class="form-control form-control-sm" value="<?= $v('loan_principal_inr', 0) ?>">
            </div>
            <div class="col-md-2">
                <label class="fm-label">Interest %</label>
                <input type="number" step="any" name="loan_interest_rate_pct" class="form-control form-control-sm" value="<?= $v('loan_interest_rate_pct', 11) ?>">
            </div>
            <div class="col-md-2">
                <label class="fm-label">Moratorium Yrs</label>
                <input type="number" name="loan_moratorium_years" class="form-control form-control-sm" value="<?= $v('loan_moratorium_years', 3) ?>">
            </div>
            <div class="col-md-2">
                <label class="fm-label">Repay Start Yr</label>
                <input type="number" name="loan_repayment_start_year" class="form-control form-control-sm" value="<?= $v('loan_repayment_start_year', 5) ?>">
            </div>
            <div class="col-md-2">
                <label class="fm-label">Repay Yrs</label>
                <input type="number" name="loan_repayment_years" class="form-control form-control-sm" value="<?= $v('loan_repayment_years', 3) ?>">
            </div>
        </div>
    </div>

    <div class="fm-card">
        <h6>Overheads (%)</h6>
        <div class="row g-3">
            <div class="col-md-3"><label class="fm-label">Marketing % of Sales</label>
                <input type="number" step="any" name="marketing_pct_of_sales" class="form-control form-control-sm" value="<?= $v('marketing_pct_of_sales', 3) ?>"></div>
            <div class="col-md-3"><label class="fm-label">Admin % of Costs</label>
                <input type="number" step="any" name="admin_pct_of_costs" class="form-control form-control-sm" value="<?= $v('admin_pct_of_costs', 3) ?>"></div>
            <div class="col-md-3"><label class="fm-label">Contingency % of Costs</label>
                <input type="number" step="any" name="contingency_pct_of_costs" class="form-control form-control-sm" value="<?= $v('contingency_pct_of_costs', 4) ?>"></div>
            <div class="col-md-3"><label class="fm-label">Other % of Costs</label>
                <input type="number" step="any" name="other_costs_pct_of_costs" class="form-control form-control-sm" value="<?= $v('other_costs_pct_of_costs', 3) ?>"></div>
        </div>
    </div>

    <div class="fm-card">
        <h6>Society Costs (Lumps)</h6>
        <div class="row g-3">
            <div class="col-md-3"><label class="fm-label">Society Rent (₹ lump)</label>
                <input type="number" step="any" name="society_rent_lump_inr" class="form-control form-control-sm" value="<?= $v('society_rent_lump_inr', 0) ?>"></div>
            <div class="col-md-2"><label class="fm-label">Rent Year A</label>
                <input type="number" name="society_rent_year_a" class="form-control form-control-sm" value="<?= $v('society_rent_year_a', 1) ?>"></div>
            <div class="col-md-2"><label class="fm-label">Rent Year B</label>
                <input type="number" name="society_rent_year_b" class="form-control form-control-sm" value="<?= $v('society_rent_year_b', 3) ?>"></div>
            <div class="col-md-3"><label class="fm-label">Other Society (₹ lump)</label>
                <input type="number" step="any" name="society_other_lump_inr" class="form-control form-control-sm" value="<?= $v('society_other_lump_inr', 0) ?>"></div>
            <div class="col-md-2"><label class="fm-label">Other Year</label>
                <input type="number" name="society_other_year" class="form-control form-control-sm" value="<?= $v('society_other_year', 1) ?>"></div>
        </div>
    </div>

    <div class="fm-card">
        <h6>Yearly Curves (fractions; enter as decimals, comma-separated)</h6>
        <p class="fm-help">One value per year. Defaults are Parksite conservative curves. Totals should approach 1.00 for booking/rehab-cost/sale-cost/premium; collection is cumulative.</p>
        <table class="curve-table">
            <thead>
                <tr><th style="width:16%"></th>
                    <?php for ($i = 1; $i <= $N; $i++): ?><th>Y<?= $i ?></th><?php endfor; ?>
                    <th style="width:15%">Sum</th></tr>
            </thead>
            <tbody>
                <?php foreach ([
                    ['booking',    'Booking %',     $booking],
                    ['collection', 'Collection % (cum)', $collection],
                    ['rehab_cost', 'Rehab Cost %',  $rehabCost],
                    ['sale_cost',  'Sale Cost %',   $saleCost],
                    ['premium',    'Premium %',     $premium],
                ] as $row): [$name, $label, $arr] = $row; $sum = array_sum($arr); ?>
                <tr>
                    <td class="label"><?= e($label) ?></td>
                    <?php for ($i = 0; $i < $N; $i++): $val = $arr[$i] ?? 0; ?>
                        <td><input type="number" step="any" name="<?= $name ?>[]" value="<?= number_format((float)$val, 3, '.', '') ?>"></td>
                    <?php endfor; ?>
                    <td><span class="curve-total"><?= number_format((float)$sum, 3) ?></span></td>
                </tr>
                <input type="hidden" name="<?= $name ?>" id="hidden_<?= $name ?>" value="<?= e(json_encode($arr)) ?>">
                <?php endforeach; ?>
            </tbody>
        </table>
        <script>
        (function () {
            // On submit, collect each curve's array inputs into its hidden JSON field.
            document.querySelector('form').addEventListener('submit', () => {
                ['booking','collection','rehab_cost','sale_cost','premium'].forEach(name => {
                    const inputs = document.querySelectorAll('input[name="' + name + '[]"]');
                    const arr = Array.from(inputs).map(i => parseFloat(i.value) || 0);
                    document.getElementById('hidden_' + name).value = JSON.stringify(arr);
                });
            });
        })();
        </script>
    </div>

    <div class="fm-card" style="border-left: 4px solid #4f46e5;">
        <h6>Feasibility Statement Inputs (classic 21-row format)</h6>
        <p class="fm-help">These populate the printed feasibility statement (preamble RR rates + Row 15 Sale-In-Situ). If left blank, the statement uses ASR seed values and a computed default.</p>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="fm-label">RR Land Rate (₹/sqm)</label>
                <input type="number" step="any" name="snapshot_rr_land_rate" class="form-control form-control-sm" value="<?= $v('snapshot_rr_land_rate') ?>" placeholder="e.g. 69210">
                <div class="fm-help-small">From Ready Reckoner (year of approval). Falls back to seeded ASR.</div>
            </div>
            <div class="col-md-4">
                <label class="fm-label">RR for Construction (₹/sqm)</label>
                <input type="number" step="any" name="snapshot_rr_construction" class="form-control form-control-sm" value="<?= $v('snapshot_rr_construction') ?>" placeholder="e.g. 31763">
                <div class="fm-help-small">From Ready Reckoner construction rate schedule.</div>
            </div>
            <div class="col-md-4">
                <label class="fm-label">Sale BUA Proposed In Situ (sqm)</label>
                <input type="number" step="any" name="sale_in_situ_sqm" class="form-control form-control-sm" value="<?= $v('sale_in_situ_sqm') ?>" placeholder="Auto-computed if blank">
                <div class="fm-help-small">How much of the sale entitlement to build on-site (rest → Balance TDR).</div>
            </div>
            <div class="col-md-4 mt-2">
                <label class="fm-label">Rehab BUA Override (sqm)</label>
                <input type="number" step="any" name="rehab_bua_override_sqm" class="form-control form-control-sm" value="<?= $v('rehab_bua_override_sqm') ?>" placeholder="Blank = use calculator">
                <div class="fm-help-small">e.g. 1,15,960.72 for Parksite Ghatkopar (uses 1.15 loading factor).</div>
            </div>
            <div class="col-md-4 mt-2">
                <label class="fm-label">Amenity Area Override (sqm)</label>
                <input type="number" step="any" name="amenity_bua_override_sqm" class="form-control form-control-sm" value="<?= $v('amenity_bua_override_sqm') ?>" placeholder="Blank = use Step 2 value or 38% default">
                <div class="fm-help-small">Advanced fallback. Primary input for Amenity Built Area is <b>Step 2 · Section D</b>. This override only wins if Step 2 is blank.</div>
            </div>
        </div>
    </div>

    <div class="fm-card">
        <h6>Equity &amp; Partner</h6>
        <div class="row g-3">
            <div class="col-md-4"><label class="fm-label">Equity Investment (₹)</label>
                <input type="number" step="any" name="equity_investment_inr" class="form-control form-control-sm" value="<?= $v('equity_investment_inr', 0) ?>"></div>
            <div class="col-md-3"><label class="fm-label">Partner Surplus Share %</label>
                <input type="number" step="any" name="surplus_share_partner_pct" class="form-control form-control-sm" value="<?= $v('surplus_share_partner_pct', 22) ?>"></div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-success fw-semibold">
            <i class="bi bi-calculator me-1"></i>Save &amp; Calculate Financials
        </button>
        <a href="index.php?page=area_analysis_report&statement_id=<?= (int)$statement['id'] ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
(function () {
    function num(el) { const v = parseFloat(el?.value || 0); return isNaN(v) ? 0 : v; }
    function fmtCr(inr) { return '₹' + (inr / 1e7).toLocaleString('en-IN', {maximumFractionDigits: 2}) + ' Cr'; }

    // Recompute the amount in a row (Qty × Rate) when either input changes.
    function recalcRow(row) {
        const qty  = row.querySelector('.li-qty');
        const rate = row.querySelector('.li-rate');
        const amt  = row.querySelector('.li-amount');
        if (!qty || !rate || !amt) return;
        const q = parseFloat(qty.value), r = parseFloat(rate.value);
        if (isFinite(q) && isFinite(r) && q > 0 && r > 0) {
            amt.value = (q * r).toFixed(2);
        }
        recalcTotals(row.dataset.liCat);
    }
    function recalcTotals(cat) {
        let total = 0;
        document.querySelectorAll(`.li-row[data-li-cat="${cat}"] .li-amount`).forEach(el => total += num(el));
        const target = document.querySelector(`[data-li-total="${cat}"]`);
        if (target) target.textContent = fmtCr(total);
    }

    // Add row
    window.liAdd = function (cat) {
        const body = document.querySelector(`[data-li-body="${cat}"]`);
        const idx = body.children.length;
        const tr = document.createElement('tr');
        tr.className = 'li-row';
        tr.dataset.liCat = cat;
        tr.innerHTML = `
            <td><input type="text" name="line_items[${cat}][${idx}][label]"></td>
            <td><input type="number" step="any" name="line_items[${cat}][${idx}][area_sqft]" class="num li-qty"></td>
            <td>
                <input type="number" step="any" name="line_items[${cat}][${idx}][rate_inr]" class="num li-rate">
                <input type="hidden" name="line_items[${cat}][${idx}][formula_type]" value="area_x_rate">
            </td>
            <td><input type="number" step="any" name="line_items[${cat}][${idx}][amount_inr]" class="num amt li-amount"></td>
            <td><button type="button" class="li-rm" onclick="liRemove(this)"><i class="bi bi-trash"></i></button></td>`;
        body.appendChild(tr);
        wireRow(tr);
    };
    window.liRemove = function (btn) {
        const tr = btn.closest('tr');
        const cat = tr.dataset.liCat;
        const body = tr.parentElement;
        if (body.children.length <= 1) {
            tr.querySelectorAll('input').forEach(i => i.value = '');
        } else tr.remove();
        recalcTotals(cat);
    };
    function wireRow(row) {
        ['.li-qty', '.li-rate', '.li-amount'].forEach(sel => {
            const el = row.querySelector(sel);
            if (el) el.addEventListener('input', () => recalcRow(row));
        });
    }
    // Wire existing rows + compute initial totals
    document.querySelectorAll('.li-row').forEach(wireRow);
    ['revenue','premium','society'].forEach(recalcTotals);
})();
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
