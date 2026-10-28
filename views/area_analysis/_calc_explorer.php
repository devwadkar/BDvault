<?php
/**
 * Calculation Explorer — global side-drawer that opens when any element with
 * data-calc-key + data-statement-id is clicked. Include this partial ONCE per page.
 *
 * Every element becomes clickable: `<span data-calc-key="rehab_bua_sqm" data-statement-id="42">…</span>`
 * The drawer fetches `?page=api_calc_explain&statement_id=…&calc_key=…` and renders the payload.
 */
?>
<style>
    .ce-trigger { cursor: pointer; border-bottom: 1px dashed rgba(79,70,229,0.35); transition: background .15s; }
    .ce-trigger:hover { background: rgba(79,70,229,0.06); border-bottom-color: #4f46e5; }

    .ce-scrim { position: fixed; inset: 0; background: rgba(15,23,42,0.35); z-index: 9998;
                opacity: 0; pointer-events: none; transition: opacity .18s; }
    .ce-scrim.open { opacity: 1; pointer-events: auto; }

    .ce-drawer {
        position: fixed; top: 0; right: 0; bottom: 0; width: 440px; max-width: 92vw;
        background: #fff; z-index: 9999; box-shadow: -20px 0 60px rgba(15,23,42,0.28);
        transform: translateX(100%); transition: transform .22s ease;
        display: flex; flex-direction: column;
        font-family: 'Lexend', system-ui, sans-serif;
    }
    .ce-drawer.open { transform: translateX(0); }
    .ce-head {
        padding: 20px 22px 16px; border-bottom: 1px solid #e2e8f0;
        display: flex; justify-content: space-between; align-items: start; gap: 12px;
    }
    .ce-head .ce-eyebrow { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.08em; color: #4f46e5; font-weight: 700; }
    .ce-head .ce-title   { font-weight: 700; font-size: 1rem; color: #0f172a; margin-top: 2px; letter-spacing: -0.01em; }
    .ce-close {
        background: #f1f5f9; border: 0; width: 32px; height: 32px; border-radius: 8px;
        color: #64748b; font-size: 1.1rem; cursor: pointer; flex-shrink: 0;
    }
    .ce-close:hover { background: #e2e8f0; color: #0f172a; }

    .ce-body { flex: 1; overflow-y: auto; padding: 18px 22px; }
    .ce-block { margin-bottom: 18px; }
    .ce-block-lbl { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; font-weight: 700; margin-bottom: 6px; }

    .ce-result {
        background: linear-gradient(135deg, #ecfdf5, #d1fae5);
        border: 1px solid #86efac; border-radius: 10px;
        padding: 16px 20px;
    }
    .ce-result-val { color: #064e3b; font-weight: 800; font-size: 1.7rem; letter-spacing: -0.02em; font-family: 'JetBrains Mono', Consolas, monospace; }
    .ce-result-unit { color: #059669; font-size: 0.9rem; font-weight: 600; margin-left: 4px; }

    .ce-formula {
        background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
        padding: 12px 14px; font-family: 'JetBrains Mono', Consolas, monospace;
        font-size: 0.82rem; color: #334155; line-height: 1.6; word-wrap: break-word;
    }

    .ce-inputs { display: grid; gap: 8px; }
    .ce-input-row {
        display: flex; justify-content: space-between; align-items: center; gap: 12px;
        background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
        padding: 10px 14px;
    }
    .ce-input-row .k { color: #64748b; font-size: 0.82rem; }
    .ce-input-row .v { color: #0f172a; font-weight: 700; font-family: 'JetBrains Mono', Consolas, monospace; font-size: 0.88rem; }

    .ce-ref-pill { display: inline-block; background: #eef2ff; color: #4338ca;
                   padding: 5px 12px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }

    .ce-status {
        display: flex; align-items: center; gap: 10px;
        padding: 12px 16px; border-radius: 8px; margin-top: 4px;
    }
    .ce-status.verified { background: #dcfce7; color: #065f46; border: 1px solid #86efac; }
    .ce-status.warning  { background: #fef3c7; color: #92400e; border: 1px solid #fbbf24; }
    .ce-status.error    { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    .ce-status .dot { font-size: 1rem; }
    .ce-status .txt { font-weight: 700; font-size: 0.85rem; }

    .ce-loading { text-align: center; color: #94a3b8; padding: 40px 0; font-size: 0.85rem; }
    .ce-loading .spinner-border { color: #4f46e5; }

    .ce-error {
        background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;
        border-radius: 8px; padding: 12px 16px; font-size: 0.85rem;
    }
</style>

<div class="ce-scrim" id="ceScrim" onclick="ceClose()"></div>
<aside class="ce-drawer" id="ceDrawer" role="dialog" aria-modal="true" aria-labelledby="ceTitle">
    <header class="ce-head">
        <div>
            <div class="ce-eyebrow">🔍 Calculation Explorer</div>
            <div class="ce-title" id="ceTitle">…</div>
        </div>
        <button class="ce-close" type="button" onclick="ceClose()" aria-label="Close">✕</button>
    </header>
    <div class="ce-body" id="ceBody">
        <div class="ce-loading">
            <div class="spinner-border spinner-border-sm me-2"></div>
            Loading calculation…
        </div>
    </div>
</aside>

<script>
(function () {
    const drawer = document.getElementById('ceDrawer');
    const scrim  = document.getElementById('ceScrim');
    const body   = document.getElementById('ceBody');
    const title  = document.getElementById('ceTitle');

    function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    function fmtNum(v) {
        const n = Number(v);
        if (!isFinite(n)) return esc(v);
        return n.toLocaleString('en-IN', {maximumFractionDigits: 4});
    }

    window.ceOpen = async function (statementId, calcKey) {
        drawer.classList.add('open');
        scrim.classList.add('open');
        title.textContent = 'Loading…';
        body.innerHTML = '<div class="ce-loading"><div class="spinner-border spinner-border-sm me-2"></div>Loading calculation…</div>';
        document.addEventListener('keydown', ceKey);

        try {
            const res = await fetch(`index.php?page=api_calc_explain&statement_id=${statementId}&calc_key=${encodeURIComponent(calcKey)}`);
            const d = await res.json();
            if (!d.success) { body.innerHTML = `<div class="ce-error">${esc(d.error || 'Failed to load')}</div>`; return; }
            title.textContent = d.calc_label;
            renderCalc(d);
        } catch (e) {
            body.innerHTML = `<div class="ce-error">Network error: ${esc(e.message)}</div>`;
        }
    };
    window.ceClose = function () {
        drawer.classList.remove('open');
        scrim.classList.remove('open');
        document.removeEventListener('keydown', ceKey);
    };
    function ceKey(e) { if (e.key === 'Escape') ceClose(); }

    function renderCalc(d) {
        let inputsHtml = '<div class="ce-inputs">';
        const entries = Object.entries(d.inputs || {});
        if (entries.length === 0) {
            inputsHtml += `<div class="ce-input-row"><span class="k">No inputs recorded</span><span class="v">—</span></div>`;
        } else {
            entries.forEach(([k, v]) => {
                const label = k.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                inputsHtml += `<div class="ce-input-row"><span class="k">${esc(label)}</span><span class="v">${fmtNum(v)}</span></div>`;
            });
        }
        inputsHtml += '</div>';

        body.innerHTML = `
            <div class="ce-block">
                <div class="ce-block-lbl">Result</div>
                <div class="ce-result">
                    <div>
                        <span class="ce-result-val">${fmtNum(d.calc_value)}</span>
                        <span class="ce-result-unit">${esc(d.calc_unit || '')}</span>
                    </div>
                </div>
            </div>

            <div class="ce-block">
                <div class="ce-block-lbl">Formula</div>
                <div class="ce-formula">${esc(d.formula || '—')}</div>
            </div>

            <div class="ce-block">
                <div class="ce-block-lbl">Inputs</div>
                ${inputsHtml}
            </div>

            ${d.regulation_ref ? `
                <div class="ce-block">
                    <div class="ce-block-lbl">Source · DCPR Regulation</div>
                    <span class="ce-ref-pill">${esc(d.regulation_ref)}</span>
                </div>
            ` : ''}

            <div class="ce-block">
                <div class="ce-block-lbl">Verification</div>
                <div class="ce-status ${esc(d.status)}">
                    <span class="dot">${d.status === 'verified' ? '✓' : (d.status === 'warning' ? '⚠' : '✗')}</span>
                    <span class="txt">${esc(d.status_label)}</span>
                </div>
            </div>

            <div class="ce-block">
                <div class="ce-block-lbl">Traceability</div>
                <div class="ce-input-row"><span class="k">Calc Key</span><span class="v" style="font-size:0.78rem;">${esc(d.calc_key)}</span></div>
                <div class="ce-input-row" style="margin-top: 6px;"><span class="k">Source Type</span><span class="v" style="font-size:0.82rem;">${esc(d.source)}</span></div>
            </div>
        `;
    }

    // Auto-wire: any element with data-calc-key + data-statement-id becomes clickable
    document.body.addEventListener('click', function (e) {
        const el = e.target.closest('[data-calc-key][data-statement-id]');
        if (!el) return;
        e.preventDefault();
        window.ceOpen(el.dataset.statementId, el.dataset.calcKey);
    });
    // Add trigger class to make them visually obvious
    document.querySelectorAll('[data-calc-key][data-statement-id]').forEach(el => el.classList.add('ce-trigger'));
})();
</script>
