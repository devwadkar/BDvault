<?php $pageTitle = 'Regulation ' . $rule['regulation_key']; ?>
<?php require __DIR__ . '/../layout/header.php'; ?>

<style>
    .dcpr-detail-hero {
        background: linear-gradient(135deg, #0f172a, #1e293b);
        color: #fff; border-radius: 14px; padding: 22px 26px; margin-bottom: 18px;
    }
    .dcpr-detail-hero .k {
        display: inline-block; padding: 2px 12px; border-radius: 999px;
        background: rgba(99,102,241,0.25); color: #c7d2fe; font-weight: 700; font-size: 0.75rem;
    }
    .dcpr-detail-hero h4 { margin: 8px 0 4px; font-weight: 800; }
    .dcpr-detail-hero p  { margin: 0; color: #cbd5e1; font-size: 0.85rem; }
    .kv-section {
        border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px 18px; margin-bottom: 14px; background: #fff;
    }
    .kv-section h6 {
        text-transform: uppercase; letter-spacing: .06em; font-size: 0.7rem;
        color: #64748b; margin: 0 0 10px; font-weight: 700;
    }
    .kv-key   { color: #64748b; font-size: 0.78rem; font-weight: 600; }
    .kv-value { color: #0f172a; font-size: 0.88rem; }
    pre.raw-json {
        background: #0f172a; color: #e2e8f0; padding: 14px; border-radius: 10px;
        font-size: 0.72rem; max-height: 500px; overflow: auto; margin: 0;
    }
</style>

<a href="index.php?page=dcpr" class="text-muted small text-decoration-none">
    <i class="bi bi-arrow-left me-1"></i>Back to rule list
</a>

<div class="dcpr-detail-hero mt-2">
    <span class="k">Regulation <?= e($rule['regulation_key']) ?></span>
    <?php if (!empty($rule['part'])): ?>
        <span class="k" style="background:rgba(148,163,184,0.20);color:#e2e8f0;">Part <?= e($rule['part']) ?></span>
    <?php endif; ?>
    <h4><?= e($rule['title']) ?></h4>
    <p><?= e($rule['content']['source'] ?? 'DCPR 2034') ?> · v<?= e($rule['version']) ?>
       <?php if (!empty($rule['content']['effective_from'])): ?> · Effective <?= e($rule['content']['effective_from']) ?><?php endif; ?>
    </p>
</div>

<?php
$c = $rule['content'] ?? [];

// Render each top-level key of the JSON as a section. Skip metadata keys already shown in the hero.
$skipKeys = ['regulation_key','title','part','source','effective_from','version','source_pdf_lines'];

function render_dcpr_value($v, $depth = 0) {
    if (is_scalar($v) || $v === null) {
        return '<span class="kv-value">' . e((string)($v ?? '—')) . '</span>';
    }
    if (is_array($v)) {
        // Numeric list of scalars — render as bullet list
        $isList = array_keys($v) === range(0, count($v) - 1);
        if ($isList && !empty($v) && is_scalar(reset($v))) {
            $html = '<ul class="mb-0 ps-3">';
            foreach ($v as $item) { $html .= '<li class="kv-value">' . e((string)$item) . '</li>'; }
            return $html . '</ul>';
        }
        // Numeric list of arrays — render as table
        if ($isList && !empty($v) && is_array(reset($v))) {
            $cols = array_keys($v[0]);
            $html = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0" style="font-size:0.78rem"><thead class="table-light"><tr>';
            foreach ($cols as $col) { $html .= '<th>' . e(str_replace('_',' ',ucfirst($col))) . '</th>'; }
            $html .= '</tr></thead><tbody>';
            foreach ($v as $row) {
                $html .= '<tr>';
                foreach ($cols as $col) {
                    $cell = $row[$col] ?? '';
                    if (is_array($cell)) $cell = json_encode($cell);
                    $html .= '<td>' . e((string)$cell) . '</td>';
                }
                $html .= '</tr>';
            }
            return $html . '</tbody></table></div>';
        }
        // Associative map — render key/value grid
        $html = '<div class="row g-2">';
        foreach ($v as $k => $child) {
            $html .= '<div class="col-md-6"><div class="kv-key">' . e(str_replace('_',' ',ucfirst($k))) . '</div>';
            $html .= render_dcpr_value($child, $depth + 1) . '</div>';
        }
        return $html . '</div>';
    }
    return '';
}
?>

<?php foreach ($c as $key => $val): ?>
    <?php if (in_array($key, $skipKeys, true)) continue; ?>
    <div class="kv-section">
        <h6><?= e(str_replace('_',' ', $key)) ?></h6>
        <?= render_dcpr_value($val) ?>
    </div>
<?php endforeach; ?>

<details class="mt-4">
    <summary class="text-muted small" style="cursor:pointer">Show raw JSON</summary>
    <pre class="raw-json mt-2"><?= e(json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
</details>

<?php require __DIR__ . '/../layout/footer.php'; ?>
