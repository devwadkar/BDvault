// ── Auto-dismiss alerts after 5 seconds ─────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.alert.alert-success, .alert.alert-info').forEach(el => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
            bsAlert.close();
        }, 5000);
    });

    // Tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el);
    });

    // Live density ratio preview on proposal form
    const existU = document.querySelector('[name=existing_units]');
    const propU  = document.querySelector('[name=proposed_units]');
    if (existU && propU) {
        const preview = document.createElement('div');
        preview.className = 'form-text';
        propU.parentNode.appendChild(preview);

        const update = () => {
            const e = parseFloat(existU.value) || 0;
            const p = parseFloat(propU.value) || 0;
            if (e > 0 && p > 0) {
                const ratio = (p / e).toFixed(2);
                const ok = ratio >= 2.5;
                preview.innerHTML = `Density ratio: <strong class="text-${ok ? 'success' : 'danger'}">${ratio}×</strong> ${ok ? '✓' : '✗ (min 2.5×)'}`;
            } else {
                preview.textContent = '';
            }
        };
        existU.addEventListener('input', update);
        propU.addEventListener('input', update);
        update();
    }

    // Live profit margin preview
    const saleRate  = document.querySelector('[name=avg_sale_price_sqft]');
    const entryCost = document.querySelector('[name=entry_cost_per_sqft]');
    if (saleRate && entryCost) {
        const preview = document.createElement('div');
        preview.className = 'form-text';
        entryCost.closest('.col-md-3').querySelector('.input-group').after(preview);

        const update = () => {
            const s = parseFloat(saleRate.value) || 0;
            const c = parseFloat(entryCost.value) || 0;
            if (s > 0 && c > 0) {
                const margin = ((s - c) / s * 100).toFixed(1);
                const ok = margin >= 20;
                preview.innerHTML = `Margin: <strong class="text-${ok ? 'success' : 'danger'}">${margin}%</strong> ${ok ? '✓' : '✗ (min 20%)'}`;
            } else {
                preview.textContent = '';
            }
        };
        saleRate.addEventListener('input', update);
        entryCost.addEventListener('input', update);
        update();
    }
});
