<?php $pageTitle = 'Requirement — ' . e($approval['master_name'] ?: $approval['custom_name']); ?>
<?php require __DIR__ . '/../layout/header.php'; ?>
<?php
// Comes from controller: $approval, $aliases, $costs (master-side), $durations,
//                        $projectCosts (Stage 4 computed rows), $costTotals
require_once __DIR__ . '/../../lib/ApprovalCostCalculator.php';
$fmtPhase = fn($p) => ucfirst(str_replace('_', ' ', (string)$p));
$fmtInr = fn($v) => ApprovalCostCalculator::formatInr($v === null ? null : (float)$v);
$displayName = $approval['master_name'] ?: $approval['custom_name'];
?>

<style>
:root {
    --ac-ink: #0f172a; --ac-muted: #64748b; --ac-faint: #94a3b8;
    --ac-border: #e2e8f0; --ac-surface: #f8fafc;
    --ac-primary: #0ea5e9; --ac-primary-d: #0369a1;
    --ac-success: #059669; --ac-warning: #d97706; --ac-danger: #dc2626;
}
.d-crumb { display: flex; align-items: center; gap: 8px; margin-bottom: 14px; font-size: 0.84rem; color: var(--ac-muted); }
.d-crumb a { color: var(--ac-primary-d); text-decoration: none; font-weight: 600; }
.d-crumb .sep { color: var(--ac-faint); }

.d-wrap { display: grid; grid-template-columns: 1fr 320px; gap: 18px; align-items: start; }
@media (max-width: 980px) { .d-wrap { grid-template-columns: 1fr; } }

.d-card { background: #fff; border: 1px solid var(--ac-border); border-radius: 12px; padding: 20px 24px; margin-bottom: 14px; }
.d-card h5 { font-weight: 800; font-size: 1.02rem; color: var(--ac-ink); margin: 0 0 4px; letter-spacing: -0.01em; }
.d-card .sub { color: var(--ac-muted); font-size: 0.84rem; margin: 0 0 14px; }

.d-section { padding-top: 16px; margin-top: 16px; border-top: 1px solid #f1f5f9; }
.d-section:first-of-type { padding-top: 0; margin-top: 0; border-top: 0; }
.d-section-title {
    font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.08em;
    color: var(--ac-primary-d); font-weight: 800; margin: 0 0 10px;
}

.field { margin-bottom: 12px; }
.field label { display: block; font-size: 0.76rem; font-weight: 600; color: var(--ac-ink); margin-bottom: 4px; }
.field input, .field select, .field textarea {
    width: 100%; padding: 8px 12px; border: 1px solid var(--ac-border); border-radius: 8px;
    font-size: 0.86rem; background: #fff; font-family: inherit;
}
.field input:focus, .field select:focus, .field textarea:focus {
    outline: none; border-color: var(--ac-primary); box-shadow: 0 0 0 3px rgba(14,165,233,0.08);
}
.field textarea { min-height: 60px; resize: vertical; }
.row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 640px) { .row2 { grid-template-columns: 1fr; } }

.btn-ac {
    display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 8px;
    font-size: 0.84rem; font-weight: 700; text-decoration: none; cursor: pointer; border: 1px solid transparent;
}
.btn-ac.primary { background: var(--ac-primary); color: #fff; border-color: var(--ac-primary); }
.btn-ac.primary:hover { background: var(--ac-primary-d); color: #fff; }
.btn-ac.ghost { background: #fff; color: var(--ac-muted); border-color: var(--ac-border); }
.btn-ac.ghost:hover { background: var(--ac-surface); color: var(--ac-ink); }
.btn-ac.danger-ghost { background: #fff; color: var(--ac-danger); border-color: #fecaca; }
.btn-ac.danger-ghost:hover { background: #fee2e2; }

.aside-card { background: #fff; border: 1px solid var(--ac-border); border-radius: 12px; padding: 16px 18px; margin-bottom: 14px; position: sticky; top: 20px; }
.aside-card h6 { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--ac-muted); font-weight: 800; margin: 0 0 10px; }

.src-row { padding: 8px 10px; background: var(--ac-surface); border-radius: 8px; margin-bottom: 6px; font-size: 0.78rem; }
.src-row .wb { font-weight: 800; color: var(--ac-primary-d); margin-right: 6px; font-family: 'JetBrains Mono', Consolas, monospace; }
.src-row .alias { color: var(--ac-ink); }
.src-row .ref { color: var(--ac-faint); font-size: 0.7rem; margin-top: 2px; font-family: 'JetBrains Mono', Consolas, monospace; }

.cost-row, .dur-row {
    padding: 10px 12px; border: 1px solid var(--ac-border); border-radius: 8px;
    margin-bottom: 6px; font-size: 0.8rem;
}
.cost-row .lbl, .dur-row .lbl { font-weight: 700; color: var(--ac-ink); }
.cost-row .val, .dur-row .val { color: var(--ac-ink); font-family: 'JetBrains Mono', Consolas, monospace; }
.cost-row .src, .dur-row .src { color: var(--ac-faint); font-size: 0.68rem; margin-top: 3px; }

.conf-chip {
    display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 0.62rem; font-weight: 700; letter-spacing: 0.02em;
    margin-left: 6px;
}
.conf-chip.source_reported { background: #fef3c7; color: #92400e; }
.conf-chip.verified_official { background: #d1fae5; color: #065f46; }
.conf-chip.pending_verification { background: #fecaca; color: #991b1b; }
.conf-chip.manual_estimate, .conf-chip.consultant_estimate, .conf-chip.historical_estimate {
    background: #e0f2fe; color: #075985;
}

.flag-banner {
    background: #fef3c7; border: 1px solid #fcd34d; color: #92400e;
    padding: 10px 14px; border-radius: 10px; margin-bottom: 14px; font-size: 0.82rem;
}
</style>

<div class="d-crumb">
    <a href="index.php?page=approval_costs"><i class="bi bi-shield-check me-1"></i>Approval Costs</a>
    <span class="sep">›</span>
    <a href="index.php?page=approval_costs_register&statement_id=<?= (int)$approval['stmt_id'] ?>"><?= e($approval['proposal_name']) ?></a>
    <span class="sep">›</span>
    <span><?= e($displayName) ?></span>
</div>

<?php $flash = flashGet(); if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> py-2 px-3 mb-3" style="font-size:0.84rem;border-radius:8px;">
        <?= e($flash['msg']) ?>
    </div>
<?php endif; ?>

<?php if (!empty($approval['requires_classification'])): ?>
<div class="flag-banner">
    <i class="bi bi-exclamation-triangle-fill me-1"></i>
    <b>This requirement needs classification before it's used.</b>
    <?= e($approval['classification_notes']) ?>
</div>
<?php endif; ?>

<div class="d-wrap">
    <!-- MAIN -->
    <div>
        <div class="d-card">
            <h5><?= e($displayName) ?></h5>
            <?php if ($approval['master_description']): ?>
                <p class="sub"><?= e($approval['master_description']) ?></p>
            <?php endif; ?>

            <form method="POST" action="index.php?page=approval_costs_update">
                <input type="hidden" name="id" value="<?= (int)$approval['id'] ?>">
                <input type="hidden" name="statement_id" value="<?= (int)$approval['stmt_id'] ?>">

                <!-- Applicability -->
                <div class="d-section">
                    <div class="d-section-title">Applicability</div>
                    <div class="row2">
                        <div class="field">
                            <label>Status</label>
                            <select name="applicability">
                                <?php foreach (['mandatory','conditional','not_applicable','requires_verification'] as $v): ?>
                                    <option value="<?= $v ?>" <?= $approval['applicability'] === $v ? 'selected' : '' ?>><?= $fmtPhase($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>User override <span style="color:var(--ac-faint);font-size:0.72rem;">(check to mark as manually overridden)</span></label>
                            <label style="display:flex;align-items:center;gap:8px;padding:8px 12px;border:1px solid var(--ac-border);border-radius:8px;background:var(--ac-surface);font-weight:400;cursor:pointer;">
                                <input type="checkbox" name="user_override" value="1" <?= $approval['user_override'] ? 'checked' : '' ?> style="width:auto;">
                                <span style="font-size:0.84rem;">Overridden manually</span>
                            </label>
                        </div>
                    </div>
                    <div class="field">
                        <label>Rationale</label>
                        <textarea name="applicability_rationale" placeholder="Why does this apply (or not)?"><?= e($approval['applicability_rationale']) ?></textarea>
                    </div>
                    <?php if ($approval['user_override']): ?>
                    <div class="field">
                        <label>Override reason</label>
                        <textarea name="override_reason" placeholder="Explain the override"><?= e($approval['override_reason']) ?></textarea>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Classification -->
                <div class="d-section">
                    <div class="d-section-title">Classification</div>
                    <div class="row2">
                        <div class="field">
                            <label>Project phase</label>
                            <select name="project_phase">
                                <?php foreach (['land_acquisition','pre_construction','during_construction','post_construction'] as $v): ?>
                                    <option value="<?= $v ?>" <?= $approval['project_phase'] === $v ? 'selected' : '' ?>><?= $fmtPhase($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Criticality</label>
                            <select name="criticality">
                                <?php foreach (['critical','high','medium','low'] as $v): ?>
                                    <option value="<?= $v ?>" <?= $approval['criticality'] === $v ? 'selected' : '' ?>><?= ucfirst($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Progress -->
                <div class="d-section">
                    <div class="d-section-title">Progress</div>
                    <div class="row2">
                        <div class="field">
                            <label>Status</label>
                            <select name="status">
                                <?php foreach (['not_started','docs_pending','ready_to_apply','applied','under_review','query_raised','approved','rejected','on_hold','not_applicable','expired'] as $v): ?>
                                    <option value="<?= $v ?>" <?= $approval['status'] === $v ? 'selected' : '' ?>><?= $fmtPhase($v) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Application reference</label>
                            <input type="text" name="application_reference" value="<?= e($approval['application_reference']) ?>" placeholder="e.g. BPO-2026-12345">
                        </div>
                    </div>
                    <div class="field">
                        <label>Next action</label>
                        <input type="text" name="next_action" value="<?= e($approval['next_action']) ?>" placeholder="e.g. Collect latest 7/12 extract for annexure">
                    </div>
                    <div class="field">
                        <label>Authority</label>
                        <input type="text" name="authority_hint" value="<?= e($approval['authority_hint']) ?>" placeholder="<?= e($approval['master_authority'] ?: 'Issuing authority') ?>">
                    </div>
                    <div class="field">
                        <label>Remarks</label>
                        <textarea name="remarks" placeholder="Notes, dependencies, risks…"><?= e($approval['remarks']) ?></textarea>
                    </div>
                </div>

                <div style="display:flex;gap:10px;justify-content:space-between;align-items:center;padding-top:14px;margin-top:14px;border-top:1px solid #f1f5f9;">
                    <button type="button" class="btn-ac danger-ghost" onclick="document.getElementById('delForm').submit()">
                        <i class="bi bi-trash"></i> Remove
                    </button>
                    <div style="display:flex;gap:8px;">
                        <a href="index.php?page=approval_costs_register&statement_id=<?= (int)$approval['stmt_id'] ?>" class="btn-ac ghost">
                            <i class="bi bi-arrow-left"></i> Back to register
                        </a>
                        <button type="submit" class="btn-ac primary"><i class="bi bi-check-lg"></i> Save changes</button>
                    </div>
                </div>
            </form>
            <!-- Separate delete form (nested forms are invalid HTML) -->
            <form method="POST" action="index.php?page=approval_costs_delete" id="delForm"
                  onsubmit="return confirm('Remove this requirement from the register?');" style="display:none;">
                <input type="hidden" name="id" value="<?= (int)$approval['id'] ?>">
                <input type="hidden" name="statement_id" value="<?= (int)$approval['stmt_id'] ?>">
            </form>
        </div>

        <!-- Stage 5: Timeline editor ─────────────────────────────────────── -->
        <div class="d-card">
            <h5><i class="bi bi-calendar-event me-1" style="color:var(--ac-primary-d);"></i>Timeline</h5>
            <p class="sub">Planned, actual and expected dates. Delay and expected elapsed time compute automatically.</p>
            <form method="POST" action="index.php?page=approval_costs_update_timeline">
                <input type="hidden" name="id" value="<?= (int)$approval['id'] ?>">

                <div class="d-section">
                    <div class="d-section-title">Dates</div>
                    <div class="row2">
                        <div class="field">
                            <label>Planned start</label>
                            <input type="date" name="planned_start_at" value="<?= e($timeline['planned_start_at'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label>Expected application date</label>
                            <input type="date" name="expected_apply_at" value="<?= e($timeline['expected_apply_at'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label>Actual submission</label>
                            <input type="date" name="actual_submitted_at" value="<?= e($timeline['actual_submitted_at'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label>Target decision date</label>
                            <input type="date" name="target_decision_at" value="<?= e($timeline['target_decision_at'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label>Actual decision date</label>
                            <input type="date" name="actual_decided_at" value="<?= e($timeline['actual_decided_at'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label>Next follow-up</label>
                            <input type="date" name="followup_next_at" value="<?= e($timeline['followup_next_at'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label>Expiry / validity</label>
                            <input type="date" name="expiry_at" value="<?= e($timeline['expiry_at'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label>Renewal due</label>
                            <input type="date" name="renewal_due_at" value="<?= e($timeline['renewal_due_at'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="d-section">
                    <div class="d-section-title">Durations</div>
                    <?php if (!empty($masterDurations)): ?>
                        <p style="color:var(--ac-muted);font-size:0.76rem;margin:0 0 10px;">
                            <i class="bi bi-info-circle me-1"></i>Source-reported durations for this approval:
                            <?php foreach ($masterDurations as $md): ?>
                                <b><?= e($md['duration_text']) ?></b><?php if ($md['numeric_low'] !== null): ?> (<?= (int)$md['numeric_low'] ?>-<?= (int)$md['numeric_high'] ?> days)<?php endif; ?>
                            <?php endforeach; ?>
                        </p>
                    <?php endif; ?>
                    <div class="row2">
                        <div class="field">
                            <label>Internal prep (days)</label>
                            <input type="number" min="0" name="internal_prep_days" value="<?= e($timeline['internal_prep_days'] ?? '') ?>" placeholder="e.g. 15">
                        </div>
                        <div class="field">
                            <label>Authority processing (days)</label>
                            <input type="number" min="0" name="authority_processing_days" value="<?= e($timeline['authority_processing_days'] ?? '') ?>" placeholder="e.g. 45">
                        </div>
                    </div>
                    <?php if (!empty($timeline['expected_elapsed_days']) || !empty($timeline['actual_elapsed_days'])): ?>
                        <div style="background:var(--ac-surface);border:1px solid var(--ac-border);border-radius:8px;padding:10px 14px;margin-top:4px;font-size:0.82rem;color:var(--ac-ink);">
                            <?php if (!empty($timeline['expected_elapsed_days'])): ?>
                                <b>Expected elapsed:</b> <?= (int)$timeline['expected_elapsed_days'] ?> days
                            <?php endif; ?>
                            <?php if (!empty($timeline['actual_elapsed_days'])): ?>
                                · <b>Actual elapsed:</b> <?= (int)$timeline['actual_elapsed_days'] ?> days
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php
                $hasDelay = !empty($timeline['delay_days']) && (int)$timeline['delay_days'] > 0;
                if ($hasDelay || !empty($timeline['delay_reason'])): ?>
                <div class="d-section">
                    <div class="d-section-title">Delay</div>
                    <div class="row2">
                        <div class="field">
                            <label>Delay (days)</label>
                            <input type="number" min="0" name="delay_days" value="<?= e($timeline['delay_days'] ?? '') ?>">
                            <?php if ($hasDelay): ?>
                                <div style="color:var(--ac-danger);font-size:0.76rem;margin-top:4px;">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i><?= (int)$timeline['delay_days'] ?> days late
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="field">
                            <label>Reason</label>
                            <input type="text" name="delay_reason" value="<?= e($timeline['delay_reason'] ?? '') ?>" placeholder="e.g. Awaiting corrected drawings from architect">
                        </div>
                    </div>
                </div>
                <?php else: ?>
                    <input type="hidden" name="delay_days" value="<?= e($timeline['delay_days'] ?? '') ?>">
                    <input type="hidden" name="delay_reason" value="<?= e($timeline['delay_reason'] ?? '') ?>">
                <?php endif; ?>

                <div class="d-section">
                    <div class="field">
                        <label>Timeline notes</label>
                        <textarea name="notes" placeholder="Context about the schedule — dependencies, risks, assumptions"><?= e($timeline['notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:8px;padding-top:14px;border-top:1px solid #f1f5f9;">
                    <button type="submit" class="btn-ac primary"><i class="bi bi-save me-1"></i>Save timeline</button>
                </div>
            </form>
        </div>

        <!-- Stage 5: Follow-up log ────────────────────────────────────────── -->
        <div class="d-card">
            <h5><i class="bi bi-chat-left-text me-1" style="color:var(--ac-primary-d);"></i>Follow-up log <span style="font-size:0.74rem;color:var(--ac-muted);font-weight:500;">(<?= count($followups) ?> entries)</span></h5>
            <form method="POST" action="index.php?page=approval_costs_add_followup" style="padding-bottom:14px;border-bottom:1px solid #f1f5f9;margin-bottom:14px;">
                <input type="hidden" name="project_approval_id" value="<?= (int)$approval['id'] ?>">
                <div class="row2">
                    <div class="field">
                        <label>Event type</label>
                        <select name="event_type">
                            <?php foreach (['note','submitted','query','response','approval','rejection','other'] as $t): ?>
                                <option value="<?= $t ?>"><?= ucfirst($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Summary <span style="color:var(--ac-danger);">*</span></label>
                        <input type="text" name="summary" required placeholder="e.g. Submitted application file no. 12345">
                    </div>
                </div>
                <div class="field">
                    <label>Detail (optional)</label>
                    <textarea name="detail" placeholder="Longer description, document numbers, who spoke to whom…"></textarea>
                </div>
                <div style="display:flex;justify-content:flex-end;">
                    <button type="submit" class="btn-ac primary"><i class="bi bi-plus-lg me-1"></i>Log event</button>
                </div>
            </form>

            <?php if (empty($followups)): ?>
                <div style="color:var(--ac-faint);font-size:0.84rem;padding:14px 0;text-align:center;">
                    No follow-ups yet. Log submissions, queries, phone calls, and approvals as they happen.
                </div>
            <?php else: ?>
                <?php foreach ($followups as $f):
                    $eventColors = ['approval'=>'#059669','rejection'=>'#dc2626','query'=>'#d97706','submitted'=>'#0ea5e9','response'=>'#0ea5e9','note'=>'#64748b','other'=>'#64748b'];
                    $color = $eventColors[$f['event_type']] ?? '#64748b';
                ?>
                    <div style="padding:10px 14px;border-left:3px solid <?= $color ?>;background:var(--ac-surface);border-radius:0 8px 8px 0;margin-bottom:8px;">
                        <div style="display:flex;justify-content:space-between;align-items:baseline;gap:10px;flex-wrap:wrap;">
                            <div>
                                <span style="font-weight:700;color:var(--ac-ink);font-size:0.84rem;"><?= e($f['summary']) ?></span>
                                <span style="background:<?= $color ?>;color:#fff;padding:1px 7px;border-radius:4px;font-size:0.62rem;font-weight:700;margin-left:6px;text-transform:uppercase;letter-spacing:0.03em;"><?= e($f['event_type']) ?></span>
                            </div>
                            <span style="color:var(--ac-faint);font-size:0.72rem;font-family:'JetBrains Mono',Consolas,monospace;"><?= e(date('d M Y, H:i', strtotime($f['event_at']))) ?></span>
                        </div>
                        <?php if ($f['detail']): ?>
                            <div style="color:var(--ac-muted);font-size:0.8rem;margin-top:4px;"><?= e($f['detail']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Stage 4: Project cost breakdown ─────────────────────────────── -->
        <?php if (!empty($projectCosts) || !empty($costs)): ?>
        <div class="d-card">
            <h5><i class="bi bi-cash-stack me-1" style="color:var(--ac-success);"></i>Cost estimate</h5>
            <?php if (!empty($projectCosts)): ?>
                <p class="sub">Each master component applied to this project's facts (BUA, plot, tree count). Change inputs on the register and the estimate recomputes.</p>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:14px;">
                    <div style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;padding:12px 14px;">
                        <div style="font-size:0.62rem;color:#047857;text-transform:uppercase;letter-spacing:0.06em;font-weight:800;">Non-refundable</div>
                        <div style="font-family:'JetBrains Mono',Consolas,monospace;font-size:1.2rem;font-weight:800;color:#065f46;margin-top:2px;"><?= $fmtInr($costTotals['non_refundable_est']) ?></div>
                    </div>
                    <?php if ($costTotals['refundable_est'] > 0): ?>
                    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px 14px;">
                        <div style="font-size:0.62rem;color:#1d4ed8;text-transform:uppercase;letter-spacing:0.06em;font-weight:800;">Refundable</div>
                        <div style="font-family:'JetBrains Mono',Consolas,monospace;font-size:1.2rem;font-weight:800;color:#1e3a8a;margin-top:2px;"><?= $fmtInr($costTotals['refundable_est']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($costTotals['actual'] > 0): ?>
                    <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:10px;padding:12px 14px;">
                        <div style="font-size:0.62rem;color:#92400e;text-transform:uppercase;letter-spacing:0.06em;font-weight:800;">Actual</div>
                        <div style="font-family:'JetBrains Mono',Consolas,monospace;font-size:1.2rem;font-weight:800;color:#78350f;margin-top:2px;"><?= $fmtInr($costTotals['actual']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($costTotals['pending_count'] > 0): ?>
                    <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:10px;padding:12px 14px;">
                        <div style="font-size:0.62rem;color:#92400e;text-transform:uppercase;letter-spacing:0.06em;font-weight:800;">Pending input</div>
                        <div style="font-family:'JetBrains Mono',Consolas,monospace;font-size:1.2rem;font-weight:800;color:#78350f;margin-top:2px;"><?= $costTotals['pending_count'] ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <table class="rv-tbl" style="width:100%;border-collapse:collapse;font-size:0.82rem;margin-top:4px;">
                    <thead>
                        <tr style="background:var(--ac-surface);">
                            <th style="text-align:left;padding:8px 10px;font-size:0.66rem;text-transform:uppercase;letter-spacing:0.04em;color:var(--ac-muted);font-weight:700;">Component</th>
                            <th style="text-align:left;padding:8px 10px;font-size:0.66rem;text-transform:uppercase;letter-spacing:0.04em;color:var(--ac-muted);font-weight:700;">Method</th>
                            <th style="text-align:right;padding:8px 10px;font-size:0.66rem;text-transform:uppercase;letter-spacing:0.04em;color:var(--ac-muted);font-weight:700;">Rate</th>
                            <th style="text-align:right;padding:8px 10px;font-size:0.66rem;text-transform:uppercase;letter-spacing:0.04em;color:var(--ac-muted);font-weight:700;">Estimated</th>
                            <th style="text-align:left;padding:8px 10px;font-size:0.66rem;text-transform:uppercase;letter-spacing:0.04em;color:var(--ac-muted);font-weight:700;">Confidence</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projectCosts as $pc):
                            $inputs = json_decode($pc['inputs_json'] ?? '{}', true) ?: [];
                            $rate = $pc['rate_used'] === null ? null : (float)$pc['rate_used'];
                            $rangeLow  = $inputs['rate_low']  ?? $inputs['low']  ?? null;
                            $rangeHigh = $inputs['rate_high'] ?? $inputs['high'] ?? null;
                            $hasRange  = $rangeLow !== null && $rangeHigh !== null;
                        ?>
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:10px;font-weight:600;color:var(--ac-ink);">
                                    <?= e($pc['label']) ?>
                                    <?php if ((int)$pc['is_refundable'] === 1): ?>
                                        <span class="pill-sm" style="background:#eff6ff;color:#1d4ed8;margin-left:4px;">refundable</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:10px;color:var(--ac-muted);font-family:'JetBrains Mono',Consolas,monospace;font-size:0.76rem;"><?= e(str_replace('_', ' ', $pc['method'])) ?></td>
                                <td style="padding:10px;text-align:right;font-family:'JetBrains Mono',Consolas,monospace;font-size:0.8rem;">
                                    <?php if ($hasRange): ?>
                                        <?= number_format((float)$rangeLow, 2) ?> – <?= number_format((float)$rangeHigh, 2) ?>
                                        <?php if (!empty($inputs['multiplier_key'])): ?>
                                            <div style="font-size:0.66rem;color:var(--ac-faint);margin-top:2px;">× <?= e($inputs['multiplier_key']) ?></div>
                                        <?php endif; ?>
                                    <?php elseif ($rate !== null): ?>
                                        <?= number_format($rate, 2) ?>
                                    <?php else: ?>
                                        <span style="color:var(--ac-faint);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:10px;text-align:right;font-family:'JetBrains Mono',Consolas,monospace;font-weight:700;color:var(--ac-ink);">
                                    <?= $fmtInr($pc['estimated_amount']) ?>
                                </td>
                                <td style="padding:10px;">
                                    <span class="conf-chip <?= e($pc['confidence']) ?>"><?= e(str_replace('_',' ', $pc['confidence'])) ?></span>
                                </td>
                            </tr>
                            <?php if (isset($inputs['note'])): ?>
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td colspan="5" style="padding:4px 10px 10px;color:var(--ac-warning);font-size:0.74rem;font-style:italic;">
                                    <i class="bi bi-info-circle me-1"></i><?= e($inputs['note']) ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color:var(--ac-muted);font-size:0.84rem;">No cost rows computed yet.
                    <?php if (!empty($costs)): ?>
                        This master has <?= count($costs) ?> source-reported cost component<?= count($costs) !== 1 ? 's' : '' ?> waiting to be applied.
                    <?php endif; ?>
                    Hit <b>Recompute costs</b> on the register page.
                </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>

    <!-- ASIDE -->
    <aside>
        <?php if ($approval['master_id']): ?>
        <div class="aside-card">
            <h6>Source provenance</h6>
            <?php foreach ($aliases as $al): ?>
                <div class="src-row">
                    <div><span class="wb"><?= e($al['source_workbook']) ?></span><span class="alias"><?= e($al['alias']) ?></span></div>
                    <div class="ref">· <?= e($al['source_sheet']) ?> · row <?= (int)$al['source_row'] ?><?php if ($al['is_primary']): ?> · primary<?php endif; ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($costs)): ?>
        <div class="aside-card">
            <h6>Source-reported costs <span style="font-weight:500;font-size:0.68rem;color:var(--ac-faint);text-transform:none;letter-spacing:0;">(indicative — Stage 4 applies them)</span></h6>
            <?php foreach ($costs as $c):
                $val = '';
                if ($c['method'] === 'range' && $c['rate_range_low'] !== null) {
                    $val = number_format((float)$c['rate_range_low'], 2) . ' – ' . number_format((float)$c['rate_range_high'], 2);
                } elseif ($c['rate_numeric'] !== null) {
                    $val = number_format((float)$c['rate_numeric'], 2);
                } else {
                    $val = '—';
                }
            ?>
                <div class="cost-row">
                    <div class="lbl"><?= e($c['label']) ?>
                        <span class="conf-chip <?= e($c['confidence']) ?>"><?= e(str_replace('_',' ', $c['confidence'])) ?></span>
                    </div>
                    <div class="val"><?= e($val) ?> <?= e($c['unit'] ?: '') ?></div>
                    <?php if ($c['source_ref']): ?><div class="src"><?= e($c['source_ref']) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($durations)): ?>
        <div class="aside-card">
            <h6>Source-reported durations</h6>
            <?php foreach ($durations as $d): ?>
                <div class="dur-row">
                    <div class="lbl"><?= e($d['label']) ?></div>
                    <div class="val"><?= e($d['duration_text'] ?: '—') ?>
                        <?php if ($d['numeric_low'] !== null): ?>
                            <span style="color:var(--ac-faint);font-size:0.7rem;">(<?= (int)$d['numeric_low'] ?><?php if ((int)$d['numeric_high'] !== (int)$d['numeric_low']): ?>-<?= (int)$d['numeric_high'] ?><?php endif; ?> days)</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($d['source_ref']): ?><div class="src"><?= e($d['source_ref']) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="aside-card">
            <h6>Custom requirement</h6>
            <div style="color:var(--ac-muted);font-size:0.82rem;">This item was added manually — not from the master library.</div>
        </div>
        <?php endif; ?>
    </aside>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
