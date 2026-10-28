<?php $pageTitle = 'Financial Model — ' . e($plan['plan_name']); ?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<style>
    .fm-hero { background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff; border-radius: 14px; padding: 20px 24px; margin-bottom: 18px; }
    .fm-hero h5 { margin: 8px 0 0; font-weight: 700; }
    .fm-hero p  { margin: 4px 0 0; color: #cbd5e1; font-size: 0.8rem; }
    .fm-hero .badge-reg { background: rgba(16,185,129,0.25); color: #a7f3d0; font-weight: 700; padding: 3px 12px; border-radius: 999px; font-size: 0.72rem; }
    .fm-card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 18px 22px; background: #fff; margin-bottom: 16px; }
    .fm-card h6 { font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: .06em; font-size: 0.75rem; margin: 0 0 4px; }
    .fm-help { color: #64748b; font-size: 0.78rem; margin: 0 0 14px; }
    .fm-label { font-weight: 600; color: #334155; font-size: 0.8rem; margin-bottom: 2px; }
    .fm-help-small { color: #64748b; font-size: 0.72rem; margin-top: 2px; }
    .curve-table input { text-align: right; font-family: 'JetBrains Mono', monospace; }
    .curve-table th, .curve-table td { text-align: center; padding: 4px 6px; }
    .curve-table td.label { text-align: left; font-weight: 600; color: #334155; }
    .curve-total {
        display: inline-block; padding: 2px 10px; border-radius: 999px;
        background: #f1f5f9; color: #475569; font-family: 'JetBrains Mono', monospace;
        font-size: 0.72rem; margin-left: 8px;
    }
</style>

<a href="index.php?page=planning_view&plan_id=<?= (int)$plan['id'] ?>" class="text-muted small text-decoration-none">
    <i class="bi bi-arrow-left me-1"></i>Back to plan
</a>

<div class="fm-hero mt-2">
    <span class="badge-reg">FINANCIAL MODEL</span>
    <h5><?= e($plan['plan_name']) ?></h5>
    <p>Configure rates, escalation, curves, loan and overheads. Yearly cash flow + IRR compute on save.</p>
</div>

<form method="POST" action="index.php?page=planning_financials_save">
    <input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>">

    <div class="fm-card">
        <h6>Project Meta</h6>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="fm-label">Scenario Label</label>
                <input type="text" name="scenario_label" class="form-control form-control-sm" value="<?= e($fin['scenario_label'] ?? '') ?>" placeholder='e.g. Conservative / Stretched'>
            </div>
            <div class="col-md-3">
                <label class="fm-label">Project Duration (years)</label>
                <input type="number" name="project_duration_years" class="form-control form-control-sm" value="<?= e($fin['project_duration_years'] ?? 7) ?>" min="3" max="15">
            </div>
        </div>
    </div>

    <div class="fm-card">
        <h6>Base-Year Rates &amp; Escalation</h6>
        <p class="fm-help">Y1 rates auto-escalate at the annual escalation rate. Sale rate feeds revenue booking; construction rates feed cost.</p>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="fm-label">Sale Rate Y1 (₹/sqft carpet)</label>
                <input type="number" step="any" name="sale_rate_y1_inr_sqft" class="form-control form-control-sm" value="<?= e($fin['sale_rate_y1_inr_sqft']) ?>">
            </div>
            <div class="col-md-3">
                <label class="fm-label">Rehab Construction Y1 (₹/sqft)</label>
                <input type="number" step="any" name="rehab_construction_rate_y1_inr_sqft" class="form-control form-control-sm" value="<?= e($fin['rehab_construction_rate_y1_inr_sqft']) ?>">
            </div>
            <div class="col-md-3">
                <label class="fm-label">Sale Construction Y1 (₹/sqft)</label>
                <input type="number" step="any" name="sale_construction_rate_y1_inr_sqft" class="form-control form-control-sm" value="<?= e($fin['sale_construction_rate_y1_inr_sqft']) ?>">
            </div>
            <div class="col-md-3">
                <label class="fm-label">Annual Escalation (%)</label>
                <input type="number" step="any" name="annual_escalation_pct" class="form-control form-control-sm" value="<?= e($fin['annual_escalation_pct']) ?>">
            </div>
            <div class="col-md-3">
                <label class="fm-label">RERA Carpet Loading (%)</label>
                <input type="number" step="any" name="rera_carpet_loading_pct" class="form-control form-control-sm" value="<?= e($fin['rera_carpet_loading_pct']) ?>">
                <div class="fm-help-small">RERA Carpet = Sale BUA × this / 100</div>
            </div>
            <div class="col-md-3">
                <label class="fm-label">RERA Carpet Override (sqft)</label>
                <input type="number" step="any" name="saleable_rera_carpet_sqft_override" class="form-control form-control-sm" value="<?= e($fin['saleable_rera_carpet_sqft_override'] ?? '') ?>" placeholder="auto">
                <div class="fm-help-small">Leave blank to auto-derive</div>
            </div>
        </div>
    </div>

    <?php
    $N = (int)($fin['project_duration_years'] ?? 7);
    $curves = [
        'booking_curve'    => ['label' => 'Sales Booking',       'help' => '% of RERA inventory booked each year — must sum to 100%', 'json' => $fin['booking_curve_json']    ?? '[]'],
        'collection_curve' => ['label' => 'Cash Collection',     'help' => 'Cumulative % collected of bookings-to-date (usually 100% by Y-final)', 'json' => $fin['collection_curve_json'] ?? '[]'],
        'rehab_cost_curve' => ['label' => 'Rehab Construction',  'help' => '% of rehab construction cost spent each year — must sum to 100%', 'json' => $fin['rehab_cost_curve_json'] ?? '[]'],
        'sale_cost_curve'  => ['label' => 'Sale Construction',   'help' => '% of sale construction cost spent each year — must sum to 100%', 'json' => $fin['sale_cost_curve_json']  ?? '[]'],
        'premium_curve'    => ['label' => 'Premium &amp; Deposits','help' => '% of total premium paid each year — must sum to 100%', 'json' => $fin['premium_curve_json']    ?? '[]'],
    ];
    ?>

    <div class="fm-card">
        <h6>Year-by-Year Curves</h6>
        <p class="fm-help">Enter each row as a percentage. Y1 is Year 1 of the project. Rows are validated to sum to 100%.</p>
        <div class="table-responsive">
            <table class="table table-sm curve-table mb-0" style="font-size:0.8rem;">
                <thead class="table-light">
                    <tr>
                        <th>Curve</th>
                        <?php for ($y = 1; $y <= $N; $y++): ?>
                            <th>Y<?= $y ?> (%)</th>
                        <?php endfor; ?>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($curves as $key => $meta):
                        $arr = json_decode($meta['json'], true) ?: [];
                    ?>
                        <tr>
                            <td class="label">
                                <?= $meta['label'] ?>
                                <div class="fm-help-small" style="font-weight:400"><?= $meta['help'] ?></div>
                            </td>
                            <?php for ($y = 1; $y <= $N; $y++): $v = (float)($arr[$y-1] ?? 0) * 100; ?>
                                <td><input type="number" step="0.01" name="<?= e($key) ?>[]" value="<?= number_format($v, 2, '.', '') ?>" class="form-control form-control-sm curve-input" data-curve="<?= e($key) ?>"></td>
                            <?php endfor; ?>
                            <td><span class="curve-total" data-total-for="<?= e($key) ?>">—</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="fm-card">
        <h6>Loan / Interest</h6>
        <div class="row g-3">
            <div class="col-md-3"><label class="fm-label">Principal (₹)</label>
                <input type="number" step="any" name="loan_principal_inr" class="form-control form-control-sm" value="<?= e($fin['loan_principal_inr']) ?>">
                <div class="fm-help-small">e.g. 4,000,000,000 = ₹400 Cr</div>
            </div>
            <div class="col-md-2"><label class="fm-label">Rate (%)</label>
                <input type="number" step="any" name="loan_interest_rate_pct" class="form-control form-control-sm" value="<?= e($fin['loan_interest_rate_pct']) ?>">
            </div>
            <div class="col-md-2"><label class="fm-label">Moratorium (yrs)</label>
                <input type="number" name="loan_moratorium_years" class="form-control form-control-sm" value="<?= e($fin['loan_moratorium_years']) ?>">
            </div>
            <div class="col-md-2"><label class="fm-label">Repayment Start Y</label>
                <input type="number" name="loan_repayment_start_year" class="form-control form-control-sm" value="<?= e($fin['loan_repayment_start_year']) ?>">
            </div>
            <div class="col-md-2"><label class="fm-label">Repayment (yrs)</label>
                <input type="number" name="loan_repayment_years" class="form-control form-control-sm" value="<?= e($fin['loan_repayment_years']) ?>">
            </div>
        </div>
    </div>

    <div class="fm-card">
        <h6>Overheads</h6>
        <div class="row g-3">
            <div class="col-md-3"><label class="fm-label">Marketing (% of sales)</label>
                <input type="number" step="any" name="marketing_pct_of_sales" class="form-control form-control-sm" value="<?= e($fin['marketing_pct_of_sales']) ?>"></div>
            <div class="col-md-3"><label class="fm-label">Admin (% of costs)</label>
                <input type="number" step="any" name="admin_pct_of_costs" class="form-control form-control-sm" value="<?= e($fin['admin_pct_of_costs']) ?>"></div>
            <div class="col-md-3"><label class="fm-label">Contingency (% of costs)</label>
                <input type="number" step="any" name="contingency_pct_of_costs" class="form-control form-control-sm" value="<?= e($fin['contingency_pct_of_costs']) ?>"></div>
            <div class="col-md-3"><label class="fm-label">Other (% of costs)</label>
                <input type="number" step="any" name="other_costs_pct_of_costs" class="form-control form-control-sm" value="<?= e($fin['other_costs_pct_of_costs']) ?>"></div>
        </div>
    </div>

    <div class="fm-card">
        <h6>Society Costs (Lumps)</h6>
        <div class="row g-3">
            <div class="col-md-3"><label class="fm-label">Rent Lump (₹)</label>
                <input type="number" step="any" name="society_rent_lump_inr" class="form-control form-control-sm" value="<?= e($fin['society_rent_lump_inr']) ?>"></div>
            <div class="col-md-2"><label class="fm-label">Split Year A</label>
                <input type="number" name="society_rent_year_a" class="form-control form-control-sm" value="<?= e($fin['society_rent_year_a']) ?>"></div>
            <div class="col-md-2"><label class="fm-label">Split Year B</label>
                <input type="number" name="society_rent_year_b" class="form-control form-control-sm" value="<?= e($fin['society_rent_year_b']) ?>"></div>
            <div class="col-md-3"><label class="fm-label">Other Society Lump (₹)</label>
                <input type="number" step="any" name="society_other_lump_inr" class="form-control form-control-sm" value="<?= e($fin['society_other_lump_inr']) ?>"></div>
            <div class="col-md-2"><label class="fm-label">Other Year</label>
                <input type="number" name="society_other_year" class="form-control form-control-sm" value="<?= e($fin['society_other_year']) ?>"></div>
        </div>
    </div>

    <div class="fm-card">
        <h6>Equity &amp; Partner Share</h6>
        <div class="row g-3">
            <div class="col-md-4"><label class="fm-label">Equity Investment (₹)</label>
                <input type="number" step="any" name="equity_investment_inr" class="form-control form-control-sm" value="<?= e($fin['equity_investment_inr']) ?>"></div>
            <div class="col-md-4"><label class="fm-label">Partner Surplus Share (%)</label>
                <input type="number" step="any" name="surplus_share_partner_pct" class="form-control form-control-sm" value="<?= e($fin['surplus_share_partner_pct']) ?>"></div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-success"><i class="bi bi-calculator me-1"></i>Save &amp; Run Financial Model</button>
        <a href="index.php?page=planning_view&plan_id=<?= (int)$plan['id'] ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
(function(){
    function updateTotals() {
        document.querySelectorAll('span[data-total-for]').forEach(function(span){
            var key = span.dataset.totalFor;
            var inputs = document.querySelectorAll('input[data-curve="' + key + '"]');
            var sum = 0;
            inputs.forEach(function(inp){ sum += parseFloat(inp.value) || 0; });
            span.textContent = sum.toFixed(1) + '%';
            span.style.background = Math.abs(sum - 100) < 0.5 ? '#dcfce7' : '#fef3c7';
            span.style.color      = Math.abs(sum - 100) < 0.5 ? '#166534' : '#92400e';
        });
    }
    document.querySelectorAll('.curve-input').forEach(function(inp){
        inp.addEventListener('input', updateTotals);
    });
    updateTotals();
})();
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
