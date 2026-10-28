<?php
/**
 * Shared wizard shell — call this from any step<N>.php file.
 * State is populated by the controller into $GLOBALS['__AA'] before requiring the view.
 *
 * Usage:
 *   <?php require __DIR__ . '/_wizard_shell.php'; ?>
 *   <?php _aa_shell_open(); ?>
 *       ...step body html...
 *   <?php _aa_shell_close(); ?>
 */

function _aa_shell_open() {
    $s = $GLOBALS['__AA'] ?? [];
    $statement = $s['statement'] ?? [];
    $proposal  = $s['proposal']  ?? [];
    $step      = (int)($s['step'] ?? 1);
    $steps     = $s['steps']    ?? [];
    $preview   = $s['preview']  ?? [];
    $pageTitle = ($steps[$step]['title'] ?? 'Step') . ' · ' . e($statement['statement_name'] ?? '');
    require __DIR__ . '/../layout/header.php';
    ?>
    <style>
        /* ── Area Analysis · design tokens ─────────────────────────────────── */
        :root {
            --aa-ink:      #0f172a;
            --aa-muted:    #64748b;
            --aa-faint:    #94a3b8;
            --aa-border:   #e2e8f0;
            --aa-surface:  #f8fafc;
            --aa-primary:  #4f46e5;
            --aa-primary-d:#3730a3;
            --aa-success:  #059669;
            --aa-success-l:#d1fae5;
            --aa-warning:  #d97706;
            --aa-danger:   #dc2626;
        }
        /* ── Hero ─────────────────────────────────────────────────────────── */
        .aa-hero {
            display: flex; justify-content: space-between; align-items: center;
            padding: 20px 24px; margin-bottom: 20px;
            background: #fff; border: 1px solid var(--aa-border); border-radius: 14px;
        }
        .aa-hero .h-title { font-size: 1.05rem; font-weight: 700; color: var(--aa-ink); margin: 0; letter-spacing: -0.01em; }
        .aa-hero .h-meta  { color: var(--aa-muted); font-size: 0.78rem; margin-top: 2px; }
        .aa-pill { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.02em; }
        .aa-pill.draft      { background: #fef3c7; color: #92400e; }
        .aa-pill.calculated { background: #dbeafe; color: #1e40af; }
        .aa-pill.validated  { background: var(--aa-success-l); color: var(--aa-success); }
        .aa-pill.locked     { background: var(--aa-success-l); color: var(--aa-success); }
        .aa-pill.superseded { background: #f1f5f9; color: var(--aa-muted); }
        .aa-pill.ver        { background: #eef2ff; color: var(--aa-primary-d); margin-left: 6px; }

        /* ── Stepper ──────────────────────────────────────────────────────── */
        .aa-stepper {
            display: flex; gap: 4px; padding: 8px; margin-bottom: 20px;
            background: #fff; border: 1px solid var(--aa-border); border-radius: 12px;
            overflow-x: auto;
        }
        .aa-step-link {
            flex: 1; min-width: 130px;
            display: flex; align-items: center; gap: 10px; padding: 10px 14px;
            border-radius: 8px; text-decoration: none; color: var(--aa-muted);
            font-size: 0.82rem; font-weight: 600; transition: background .15s, color .15s;
        }
        .aa-step-link:hover { background: var(--aa-surface); color: var(--aa-ink); }
        .aa-step-link.done { color: var(--aa-success); }
        .aa-step-link.current { background: var(--aa-primary); color: #fff; }
        .aa-step-link.current:hover { background: var(--aa-primary-d); color: #fff; }
        .aa-step-num {
            display: inline-flex; align-items: center; justify-content: center;
            width: 24px; height: 24px; border-radius: 999px;
            background: rgba(0,0,0,0.06); font-size: 0.72rem; font-weight: 800; flex-shrink: 0;
        }
        .aa-step-link.done .aa-step-num { background: var(--aa-success-l); color: var(--aa-success); }
        .aa-step-link.done .aa-step-num::before { content: '✓'; }
        .aa-step-link.done .aa-step-num > span { display: none; }
        .aa-step-link.current .aa-step-num { background: rgba(255,255,255,0.24); color: #fff; }

        /* ── Layout: main + preview aside ─────────────────────────────────── */
        .aa-body { display: grid; grid-template-columns: 1fr 300px; gap: 20px; align-items: start; }
        @media (max-width: 992px) { .aa-body { grid-template-columns: 1fr; } }

        /* ── Card ────────────────────────────────────────────────────────── */
        .aa-card {
            background: #fff; border: 1px solid var(--aa-border); border-radius: 14px;
            padding: 26px 28px; margin-bottom: 18px;
        }
        .aa-card-title { font-size: 1.2rem; font-weight: 700; color: var(--aa-ink); margin: 0 0 4px; letter-spacing: -0.01em; }
        .aa-card-lead  { color: var(--aa-muted); font-size: 0.88rem; margin: 0 0 22px; }

        .aa-group-title {
            font-size: 0.72rem; font-weight: 700; color: var(--aa-primary-d);
            text-transform: uppercase; letter-spacing: 0.08em;
            margin: 22px 0 12px; padding-bottom: 8px;
            border-bottom: 1px solid var(--aa-border);
        }
        .aa-group-title:first-child { margin-top: 0; }

        /* ── Form controls (bigger, cleaner) ──────────────────────────────── */
        .aa-card .form-label {
            font-size: 0.82rem; font-weight: 600; color: var(--aa-ink); margin-bottom: 4px;
        }
        .aa-card .form-label .req { color: var(--aa-danger); }
        .aa-card .form-control,
        .aa-card .form-select {
            font-size: 0.92rem; padding: 8px 12px; border-radius: 8px;
            border-color: var(--aa-border); background: #fff;
        }
        .aa-card .form-control:focus,
        .aa-card .form-select:focus {
            border-color: var(--aa-primary); box-shadow: 0 0 0 3px rgba(79,70,229,0.12);
        }
        .aa-card .form-hint { color: var(--aa-faint); font-size: 0.75rem; margin-top: 4px; }
        .aa-card .input-group-text { background: var(--aa-surface); border-color: var(--aa-border); color: var(--aa-muted); font-size: 0.82rem; }

        /* ── Live-derivation card ─────────────────────────────────────────── */
        .aa-derived {
            display: flex; align-items: center; justify-content: space-between; gap: 24px; flex-wrap: wrap;
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            border: 1px solid #86efac; border-radius: 12px;
            padding: 18px 22px; margin-top: 20px;
        }
        .aa-derived .lbl { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; color: #065f46; font-weight: 700; }
        .aa-derived .val { color: #064e3b; font-weight: 800; font-size: 1.4rem; margin-top: 2px; }
        .aa-derived .sub { color: #047857; font-size: 0.75rem; margin-top: 4px; }
        .aa-derived .val-sec { color: #92400e; font-size: 1.05rem; font-weight: 800; }

        /* ── Preview sidebar ──────────────────────────────────────────────── */
        .aa-preview {
            position: sticky; top: 14px;
            background: #fff; border: 1px solid var(--aa-border); border-radius: 14px;
            padding: 20px 22px;
        }
        .aa-preview h6 {
            font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.08em;
            font-weight: 700; color: var(--aa-primary-d); margin: 0 0 12px;
        }
        .aa-preview .pv-group-lbl {
            font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.08em;
            color: var(--aa-faint); font-weight: 700; margin: 16px 0 4px;
        }
        .aa-preview .pv-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 0.82rem; gap: 12px; }
        .aa-preview .pv-row + .pv-row { border-top: 1px solid #f1f5f9; }
        .aa-preview .pv-lbl { color: var(--aa-muted); }
        .aa-preview .pv-val { color: var(--aa-ink); font-weight: 600; text-align: right; }
        .aa-preview .pv-val.hi { color: var(--aa-success); }
        .aa-preview .pv-note { color: var(--aa-faint); font-size: 0.72rem; margin-top: 14px; line-height: 1.5; }

        /* ── Nav bar (Prev / Save / Next) ─────────────────────────────────── */
        .aa-nav {
            display: flex; justify-content: space-between; align-items: center; gap: 12px;
            margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--aa-border);
        }
        .aa-btn { padding: 10px 18px; border-radius: 8px; font-weight: 700; font-size: 0.85rem;
                  border: 0; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .aa-btn.prev    { background: transparent; color: var(--aa-muted); }
        .aa-btn.prev:hover { color: var(--aa-ink); }
        .aa-btn.save    { background: var(--aa-surface); color: var(--aa-ink); border: 1px solid var(--aa-border); }
        .aa-btn.save:hover { background: #eef2ff; border-color: #c7d2fe; }
        .aa-btn.next    { background: var(--aa-primary); color: #fff; padding: 10px 22px; }
        .aa-btn.next:hover { background: var(--aa-primary-d); }
        .aa-btn.review  { background: var(--aa-warning); color: #fff; padding: 10px 22px; }
        .aa-btn.review:hover { background: #b45309; }

        /* ── Legacy compat: pre-existing class names on step pages ─────────── */
        .aa-sub-title { display: none; }  /* replaced by .aa-group-title */
        .aa-help { color: var(--aa-faint); font-size: 0.75rem; margin-top: 4px; }
        .aa-blurb { display: none; }       /* replaced by .aa-card-lead */
        .step-pill { display: none; }      /* redundant with the stepper */
    </style>

    <a href="index.php?page=area_analysis" class="text-muted small text-decoration-none" style="font-size: 0.8rem;">
        <i class="bi bi-arrow-left me-1"></i>Back to Area Analysis
    </a>

    <div class="aa-hero mt-2">
        <div>
            <h4 class="h-title"><?= e($statement['statement_name'] ?? '') ?></h4>
            <div class="h-meta"><?= e($proposal['proposal_name'] ?? '') ?>
                <?php if ($proposal['city'] ?? null): ?> · <?= e($proposal['city']) ?><?php endif; ?>
                <?php if ($statement['selected_regulation_key'] ?? null): ?> · DCPR <?= e($statement['selected_regulation_key']) ?><?php endif; ?>
            </div>
        </div>
        <div>
            <span class="aa-pill <?= e($statement['status'] ?? 'draft') ?>"><?= strtoupper(e($statement['status'] ?? 'draft')) ?></span>
            <span class="aa-pill ver">v<?= (int)($statement['version_major'] ?? 1) ?>.<?= (int)($statement['version_minor'] ?? 0) ?></span>
        </div>
    </div>

    <nav class="aa-stepper">
        <?php foreach ($steps as $idx => $meta):
            $cls = '';
            if ($idx == $step) $cls = 'current';
            elseif ($idx < (int)($statement['current_step'] ?? 1)) $cls = 'done';
            $link = "index.php?page=area_analysis_step&statement_id={$statement['id']}&step={$idx}";
        ?>
            <a href="<?= $link ?>" class="aa-step-link <?= $cls ?>">
                <span class="aa-step-num"><span><?= $idx ?></span></span>
                <span class="d-none d-md-inline"><?= e($meta['title']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="aa-body">
        <div>
    <?php
}

function _aa_shell_close() {
    $s = $GLOBALS['__AA'] ?? [];
    $preview = $s['preview'] ?? [];
    ?>
        </div>
        <aside>
            <div class="aa-preview">
                <h6>Live Preview</h6>

                <?php
                $fmtNum = fn($v, $d = 2) => ($v === null || $v === '' ? '—' : number_format((float)$v, $d));
                $fmtSqm = fn($v) => ($v === null || $v === '' ? '—' : number_format((float)$v, 2) . ' sqm');
                ?>

                <div class="pv-row"><span class="pv-lbl">Authority</span><span class="pv-val"><?= e($preview['planning_authority'] ?? '—') ?></span></div>
                <div class="pv-row"><span class="pv-lbl">Scheme</span><span class="pv-val"><?= e(ucwords(str_replace('_', ' ', $preview['development_type'] ?? '—'))) ?></span></div>
                <div class="pv-row"><span class="pv-lbl">Regulation</span><span class="pv-val"><?= e($preview['selected_reg'] ?? '—') ?></span></div>

                <?php if (($preview['gross_plot_area_sqm'] ?? null) || ($preview['net_plot_area_sqm'] ?? null)): ?>
                    <div class="pv-group-lbl">Plot</div>
                    <div class="pv-row"><span class="pv-lbl">Gross</span><span class="pv-val"><?= $fmtSqm($preview['gross_plot_area_sqm'] ?? null) ?></span></div>
                    <div class="pv-row"><span class="pv-lbl">Net (FSI)</span><span class="pv-val hi"><?= $fmtSqm($preview['net_plot_area_sqm'] ?? null) ?></span></div>
                <?php endif; ?>

                <?php if (($preview['exist_buildings'] ?? 0) > 0): ?>
                    <div class="pv-group-lbl">Existing</div>
                    <div class="pv-row"><span class="pv-lbl">Units</span><span class="pv-val"><?= (int)$preview['exist_res_units'] ?> + <?= (int)$preview['exist_com_units'] ?></span></div>
                    <div class="pv-row"><span class="pv-lbl">BUA</span><span class="pv-val"><?= $fmtSqm($preview['exist_bua_sqm']) ?></span></div>
                    <div class="pv-row"><span class="pv-lbl">Tenants</span><span class="pv-val"><?= (int)$preview['exist_tenants'] ?> <span class="text-muted">(<?= (int)$preview['exist_eligible'] ?> eligible)</span></span></div>
                <?php endif; ?>

                <?php if (($preview['total_fsi'] ?? null) !== null || ($preview['total_bua_sqm'] ?? null) !== null): ?>
                    <div class="pv-group-lbl">Calculated</div>
                    <div class="pv-row"><span class="pv-lbl">Total FSI</span><span class="pv-val"><?= $fmtNum($preview['total_fsi'] ?? null, 3) ?></span></div>
                    <div class="pv-row"><span class="pv-lbl">Total BUA</span><span class="pv-val hi"><?= $fmtSqm($preview['total_bua_sqm'] ?? null) ?></span></div>
                    <div class="pv-row"><span class="pv-lbl">Rehab</span><span class="pv-val"><?= $fmtSqm($preview['rehab_bua_sqm'] ?? null) ?></span></div>
                    <div class="pv-row"><span class="pv-lbl">Sale</span><span class="pv-val"><?= $fmtSqm($preview['sale_bua_sqm'] ?? null) ?></span></div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
    <?php
    require __DIR__ . '/../layout/footer.php';
}

/** Prev / Save Draft / Next bar. */
function _aa_nav_bar($statement, $step, $showReview = false) {
    ?>
    <form method="POST" action="index.php?page=area_analysis_save_step" class="aa-nav">
        <input type="hidden" name="statement_id" value="<?= (int)$statement['id'] ?>">
        <input type="hidden" name="step" value="<?= (int)$step ?>">
        <div>
            <?php if ($step > 1): ?>
                <button type="submit" name="direction" value="prev" class="aa-btn prev">
                    <i class="bi bi-arrow-left"></i> Previous
                </button>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button type="submit" name="direction" value="save" class="aa-btn save">
                Save Draft
            </button>
            <?php if ($showReview): ?>
                <button type="submit" name="direction" value="review" class="aa-btn review">
                    Review &amp; Generate <i class="bi bi-arrow-right"></i>
                </button>
            <?php elseif ($step < 6): ?>
                <button type="submit" name="direction" value="next" class="aa-btn next">
                    Next: <?= e($GLOBALS['__AA']['steps'][$step + 1]['title'] ?? '') ?> <i class="bi bi-arrow-right"></i>
                </button>
            <?php endif; ?>
        </div>
    </form>
    <?php
}
