<?php require __DIR__ . '/_wizard_shell.php'; ?>
<?php
$land   = $extra['land']   ?? [];
$fsi    = $extra['fsi']    ?? [];
$dev    = $extra['dev']    ?? [];
$calcs  = $extra['calcs']  ?? [];
$valids = $extra['valids'] ?? [];
$hasResults = !empty($calcs);
$selectedReg = $statement['selected_regulation_key'] ?: $statement['suggested_regulation_key'];
$fmtSqm = fn($v) => $v === null || $v === '' ? '—' : number_format((float)$v, 2) . ' sqm';
$fmtRatio = fn($v) => $v === null || $v === '' ? '—' : number_format((float)$v, 3);
?>
<?php _aa_shell_open(); ?>

<style>
    .calc-table { width: 100%; font-size: 0.82rem; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; }
    .calc-table th {
        background: #f8fafc; color: #64748b; font-weight: 700; text-transform: uppercase;
        font-size: 0.66rem; letter-spacing: .04em; padding: 8px 10px; text-align: left;
        border-bottom: 1px solid #e5e7eb;
    }
    .calc-table td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
    .calc-table .num { text-align: right; font-family: 'JetBrains Mono', Consolas, monospace; font-weight: 600; color: #0f172a; white-space: nowrap; }
    .calc-table .ref { font-size: 0.7rem; color: #6366f1; font-weight: 700; white-space: nowrap; }
    .calc-table .src-user     { background: #eff6ff; color: #1e40af; }
    .calc-table .src-derived  { background: #f8fafc; color: #475569; }
    .calc-table tr.total-row td { background: #ecfdf5; font-weight: 700; color: #065f46; border-bottom: 2px solid #86efac; }
    .stmt-title {
        font-weight: 800; color: #0f172a; letter-spacing: -.01em; font-size: 0.95rem;
        margin: 20px 0 10px; display: flex; align-items: center; gap: 10px;
    }
    .stmt-title::before { content: ''; width: 4px; height: 20px; background: #4f46e5; border-radius: 2px; }
    .empty-hint {
        background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px;
        padding: 30px; text-align: center; color: #64748b; margin: 20px 0;
    }
    .empty-hint h6 { color: #0f172a; font-weight: 700; margin: 0 0 8px; }
    .btn-calc {
        background: linear-gradient(135deg, #4f46e5, #6366f1); color: #fff;
        padding: 12px 26px; border-radius: 10px; font-weight: 700; font-size: 0.9rem;
        border: 0; cursor: pointer; margin-top: 14px;
    }
    .btn-calc:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(79,70,229,0.25); }

    .valid-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px; }
    .valid-item {
        display: grid; grid-template-columns: auto 1fr auto; gap: 12px;
        padding: 12px 16px; border-radius: 10px; align-items: center;
    }
    .valid-item.pass { background: #dcfce7; border: 1px solid #86efac; color: #065f46; }
    .valid-item.warn { background: #fef3c7; border: 1px solid #fbbf24; color: #92400e; }
    .valid-item.fail { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }
    .valid-item .dot { font-size: 1.1rem; }
    .valid-item .v-label { font-weight: 700; font-size: 0.85rem; }
    .valid-item .v-msg { font-size: 0.75rem; opacity: 0.9; margin-top: 2px; }
    .valid-item .v-side { font-family: 'JetBrains Mono', Consolas, monospace; font-size: 0.72rem; text-align: right; white-space: nowrap; }
</style>

<div class="aa-card">
    <h5 class="aa-card-title">Calculate</h5>
    <p class="aa-card-lead">
        <?php if ($selectedReg): ?>
            Runs the DCPR <?= e($selectedReg) ?> calculator against the plot &amp; existing-development inputs. Every derived value cites its regulation.
        <?php else: ?>
            <span class="text-danger">No regulation selected — go back to Step 4 first.</span>
        <?php endif; ?>
    </p>

    <?php if (!$selectedReg): ?>
        <div class="empty-hint">
            <h6>Regulation not selected</h6>
            <a href="index.php?page=area_analysis_step&statement_id=<?= (int)$statement['id'] ?>&step=4" class="btn-calc" style="text-decoration:none;">
                <i class="bi bi-arrow-left me-1"></i>Go to Step 4
            </a>
        </div>
    <?php else: ?>
        <form method="POST" action="index.php?page=area_analysis_calculate" class="d-flex align-items-center gap-3 flex-wrap">
            <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
            <button type="submit" class="btn-calc">
                <i class="bi bi-calculator me-1"></i>
                <?= $hasResults ? 'Recalculate' : 'Calculate Area Statement' ?>
            </button>
            <?php if ($hasResults): ?>
                <span class="text-muted small">
                    <i class="bi bi-check-circle-fill text-success me-1"></i>Last calc:
                    <?= (int)count($calcs) ?> rows · status: <b><?= e($statement['status']) ?></b>
                </span>
            <?php endif; ?>
        </form>

        <?php
        // Stale-calc detection: for 33(10), if rehab_component_sqm exists but
        // rehab_amenity_block_sqm is 0 the persisted calc predates the amenity fix.
        $__stale = false;
        if ($hasResults && $selectedReg === '33(10)') {
            $__comp = null; $__amen = null;
            foreach ($calcs as $__c) {
                if ($__c['calc_key'] === 'rehab_component_sqm')     $__comp = (float)$__c['calc_value'];
                if ($__c['calc_key'] === 'rehab_amenity_block_sqm') $__amen = (float)$__c['calc_value'];
            }
            if ($__comp !== null && $__comp > 0 && (!$__amen || $__amen < 0.01)) $__stale = true;
        }
        ?>
        <?php if ($__stale): ?>
            <div style="background: linear-gradient(135deg,#fef3c7,#fde68a); border:1px solid #f59e0b; border-radius:10px;
                        padding:14px 18px; margin-top:16px; color:#78350f; display:flex; gap:14px; align-items:center;">
                <i class="bi bi-exclamation-triangle-fill" style="font-size:1.4rem;"></i>
                <div style="flex:1;">
                    <strong>These results are pre-amenity-fix.</strong>
                    Rehab Component isn't including the amenity block. Click <strong>Recalculate</strong>
                    above to bring Rehab Component (Row 11), Sale Component and Total BUA up to date.
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($hasResults): ?>
    <!-- Site Plan preview -->
    <div class="aa-card">
        <div class="stmt-title">Site Plan Preview</div>
        <div style="background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; text-align: center;">
            <?php require_once __DIR__ . '/../../lib/SitePlanRenderer.php';
                 echo SitePlanRenderer::render(['statement' => $statement, 'land' => $land, 'dev' => $dev], 'full'); ?>
        </div>
    </div>

    <!-- Validation traffic-lights -->
    <?php if (!empty($valids)): ?>
        <div class="aa-card">
            <div class="stmt-title">Regulatory Validation</div>
            <div class="valid-list">
                <?php foreach ($valids as $v): $dot = $v['result']==='pass'?'🟢':($v['result']==='warn'?'🟡':'🔴'); ?>
                    <div class="valid-item <?= e($v['result']) ?>">
                        <span class="dot"><?= $dot ?></span>
                        <div>
                            <div class="v-label"><?= e($v['check_label']) ?></div>
                            <div class="v-msg"><?= e($v['message']) ?></div>
                        </div>
                        <div class="v-side">
                            <div><b><?= e($v['entered_value'] ?? '—') ?></b></div>
                            <div>vs <?= e($v['required_value'] ?? '—') ?></div>
                            <?php if ($v['regulation_ref']): ?>
                                <div class="text-muted"><?= e($v['regulation_ref']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Land Statement -->
    <?php if (!empty($land)): ?>
        <div class="aa-card">
            <div class="stmt-title">Land Statement</div>
            <table class="calc-table">
                <thead>
                    <tr><th>Component</th><th class="num">Area</th><th>Source</th><th>DCPR Ref</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($land as $key => $r): ?>
                        <tr class="<?= $r['is_deduction'] ? '' : ($key === 'net_plot' || $key === 'developable' ? 'total-row' : '') ?>">
                            <td><?= e($r['component_label']) ?><?= $r['is_deduction'] ? ' <span class="text-muted small">(deduction)</span>' : '' ?></td>
                            <td class="num"><?= $fmtSqm($r['area_sqm']) ?></td>
                            <td class="small text-muted"><?= e($r['source']) ?></td>
                            <td class="ref"><?= e($r['regulation_ref'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- FSI Statement -->
    <?php if (!empty($fsi)): ?>
        <div class="aa-card">
            <div class="stmt-title">FSI Statement</div>
            <table class="calc-table">
                <thead>
                    <tr><th>Component</th><th class="num">FSI Ratio</th><th class="num">BUA</th><th>DCPR Ref</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($fsi as $r): $isTotal = strpos($r['component_key'], 'full_') === 0 || $r['component_key'] === 'fsi_sanctioned' || $r['component_key'] === 'total_bua_approved_sqm'; ?>
                        <tr class="<?= $isTotal ? 'total-row' : '' ?>">
                            <td><?= e($r['component_label']) ?></td>
                            <td class="num"><?= $r['fsi_ratio'] !== null ? $fmtRatio($r['fsi_ratio']) : '—' ?></td>
                            <td class="num"><?= $r['bua_sqm']   !== null ? $fmtSqm($r['bua_sqm'])   : '—' ?></td>
                            <td class="ref"><?= e($r['regulation_ref'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Development Statement -->
    <?php if (!empty($dev)): ?>
        <div class="aa-card">
            <div class="stmt-title">Development Statement</div>
            <table class="calc-table">
                <thead>
                    <tr><th>Component</th><th class="num">Area</th><th>Handover</th><th>DCPR Ref</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($dev as $r): $isTotal = strpos($r['component_key'], 'full_') === 0 || strpos($r['component_key'], 'total_bua') === 0; ?>
                        <tr class="<?= $isTotal ? 'total-row' : '' ?>">
                            <td><?= e($r['component_label']) ?></td>
                            <td class="num"><?= $fmtSqm($r['area_sqm']) ?></td>
                            <td class="small"><?= e($r['handover_to'] ?? '—') ?></td>
                            <td class="ref"><?= e($r['regulation_ref'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Full calc audit trail (collapsible) -->
    <div class="aa-card">
        <div class="stmt-title">Full Calculation Audit Trail (<?= count($calcs) ?> rows)</div>
        <details>
            <summary style="cursor: pointer; color: #4338ca; font-weight: 600; font-size: 0.85rem;">
                Expand to see every calc row with formula + inputs + citation
            </summary>
            <table class="calc-table" style="margin-top: 12px;">
                <thead>
                    <tr><th>Row</th><th class="num">Value</th><th>Formula</th><th>DCPR</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($calcs as $c): ?>
                        <tr>
                            <td>
                                <div><b><?= e($c['calc_label']) ?></b></div>
                                <div class="text-muted small"><?= e($c['calc_key']) ?></div>
                            </td>
                            <td class="num"><?= number_format((float)$c['calc_value'], 4) ?> <span class="text-muted small"><?= e($c['calc_unit']) ?></span></td>
                            <td class="small" style="max-width: 260px; word-wrap: break-word;">
                                <?= e($c['formula'] ?? '—') ?>
                            </td>
                            <td class="ref"><?= e($c['regulation_ref']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </details>
    </div>
<?php endif; ?>

<div class="aa-card">
    <?php _aa_nav_bar($statement, $step, true); ?>
</div>

<?php _aa_shell_close(); ?>
