<?php $pageTitle = 'Area Analysis v2 · Stage 3 (Coming soon)'; ?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php require __DIR__ . '/_shell.php'; ?>

<?php
$reg = $statement['selected_regulation_key'] ?: $statement['suggested_regulation_key'] ?: '—';
$regTitle = (RegulationSuggester::REGULATIONS[$reg]['title'] ?? '—');
?>

<div class="v2-card">
    <h5><i class="bi bi-sliders me-2" style="color:var(--v2-primary);"></i>Stage 3 — Regulation-specific details</h5>
    <p class="lead">The dynamic form for <b>DCPR <?= e($reg) ?></b> (<?= e($regTitle) ?>) will render here. Only the fields this regulation needs — nothing else.</p>

    <div style="background:#eef2ff;border:1px solid #c7d2fe;border-radius:12px;padding:24px;text-align:center;color:#3730a3;">
        <i class="bi bi-tools" style="font-size:2.2rem;display:block;margin-bottom:10px;"></i>
        <h5 style="color:#312e81;margin:0 0 6px;">Stage 3 ships next</h5>
        <p style="margin:0;font-size:0.86rem;color:#4338ca;">
            Your Stage 1 + Stage 2 selections are saved. Once Stage 3 is signed off (dynamic form driven by the regulation schema, pilot = DCPR 33(10)),
            you'll be able to continue here. Until then, use the classic wizard for Steps 2-6.
        </p>
        <div style="margin-top:14px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
            <a href="index.php?page=area_analysis_v2_stage2&statement_id=<?= (int)$statement['id'] ?>" class="v2-btn ghost">
                <i class="bi bi-arrow-left"></i> Edit regulation choice
            </a>
            <a href="index.php?page=area_analysis_step&statement_id=<?= (int)$statement['id'] ?>&step=2" class="v2-btn primary">
                Continue in classic wizard <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>

    <div style="background:var(--v2-surface);border:1px solid var(--v2-border);border-radius:10px;padding:16px;margin-top:14px;">
        <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--v2-muted);font-weight:700;margin-bottom:10px;">
            Stages 1 &amp; 2 captured
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 20px;font-size:0.82rem;">
            <?php
            $facts = [
                'Project'            => $statement['project_name'],
                'CTS / Survey'       => $statement['cts_number'],
                'Address'            => $statement['plot_address'],
                'City / Region'      => $statement['project_location'],
                'Ward'               => $statement['ward'],
                'Planning authority' => $statement['planning_authority'],
                'Development'        => $statement['development_type'] ? ucfirst(str_replace('_',' ', $statement['development_type'])) : null,
                'Zone (DP)'          => $s1['zone_dp'] ?? null,
                'Island City'        => isset($s1['is_island_city']) ? (!empty($s1['is_island_city']) ? 'Yes' : 'No') : null,
                'Property-card area' => !empty($s1['property_card_area_sqm']) ? number_format((float)$s1['property_card_area_sqm'], 2).' sqm' : null,
                'Road width'         => !empty($s1['primary_road_width_m']) ? number_format((float)$s1['primary_road_width_m'], 2).' m' : null,
                'Existing const.'    => isset($s1['has_existing_construction'])
                                        ? (!empty($s1['has_existing_construction']) ? 'Yes' : 'No · Vacant')
                                        : null,
                'Chosen regulation'  => $statement['selected_regulation_key']
                                        ? 'DCPR ' . $statement['selected_regulation_key']
                                        : null,
                'Suggester confidence' => $statement['suggested_confidence']
                                        ? $statement['suggested_confidence'] . '%'
                                        : null,
            ];
            if (!empty($s1['has_existing_construction'])) {
                $facts['Res units (approx)'] = $s1['approx_residential_units'] ?? null;
                $facts['Com units (approx)'] = $s1['approx_commercial_units'] ?? null;
                $facts['Tenant count (approx)'] = $s1['approx_tenant_count'] ?? null;
            }
            foreach ($facts as $k => $val):
                if ($val === null || $val === '') continue; ?>
                <div style="color:var(--v2-muted);"><?= e($k) ?></div>
                <div style="color:var(--v2-ink);font-weight:600;"><?= e((string)$val) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php _v2_shell_close($statement, $s1, $draft['_meta']['stage1_saved_at'] ?? null); ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
