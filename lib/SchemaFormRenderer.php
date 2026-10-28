<?php
/**
 * SchemaFormRenderer — minimal UI-schema driven form renderer for Stage 3 of the
 * Area Analysis v2 wizard. Reads a JSON schema describing sections + fields and
 * produces HTML + a server-side validator.
 *
 * SCOPE (deliberate — grows as more regulations onboard):
 *   • Field types: text, number, select, textarea, radio-yesno, repeat-group
 *   • Each field: label, help, unit, required, default, placeholder, options (for select)
 *   • Each section: title, blurb, fields[]
 *   • A repeat-group: a dynamic add/remove table whose rows are sub-fields.
 *
 * NOT in scope yet (deferred to later regulation pilots):
 *   • Conditional reveal (shown_if) — can be added when a regulation needs it.
 *   • Live AJAX preview — handled post-submit via server round-trip for now.
 *   • Cross-field expressions — handled in the save-handler, not the renderer.
 *
 * Schema shape (see dcpr_rules/ui/reg_33_10_stage3.json for a worked example):
 *   {
 *     "sections": [
 *       {
 *         "key": "plot_deductions",
 *         "title": "Plot & deductions",
 *         "blurb": "Enter the gross plot and every deduction…",
 *         "fields": [
 *           { "key": "gross_plot_area_sqm", "type": "number",
 *             "label": "Gross plot area", "unit": "sqm",
 *             "required": true, "min": 0, "help": "..." },
 *           { "key": "roads", "type": "repeat-group",
 *             "label": "Road setbacks", "add_label": "Add road",
 *             "columns": [
 *               { "key": "width", "type": "number", "label": "Width", "unit": "m" },
 *               { "key": "area",  "type": "number", "label": "Deduction area", "unit": "sqm" }
 *             ]
 *           }
 *         ]
 *       }
 *     ]
 *   }
 */

class SchemaFormRenderer {

    private array $schema;
    /** Decoded draft values keyed by top-level: ['v2_stage1' => [...], 'step_2' => [...], 'step_3' => [...]] */
    private array $values;

    public function __construct(array $schema, array $values = []) {
        $this->schema = $schema;
        $this->values = $values;
    }

    /** Load a schema from a JSON file. */
    public static function fromFile(string $path, array $values = []): self {
        if (!file_exists($path)) throw new RuntimeException("Schema not found: {$path}");
        $json = json_decode(file_get_contents($path), true);
        if (!is_array($json)) throw new RuntimeException("Invalid schema JSON: {$path}");
        return new self($json, $values);
    }

    public function sections(): array { return $this->schema['sections'] ?? []; }

    /** Render every section inside the current page. Echoes HTML directly. */
    public function renderAll(): void {
        foreach ($this->sections() as $i => $section) {
            $this->renderSection($section, $i + 1);
        }
    }

    public function renderSection(array $section, int $bulletNo = 0): void {
        $sKey = $section['key'] ?? ('s' . $bulletNo);
        $sTitle = $section['title'] ?? '';
        $sBlurb = $section['blurb'] ?? '';
        $bullet = $bulletNo > 0 ? chr(64 + $bulletNo) : '';  // A, B, C, …
        ?>
        <div class="v2-section" data-section="<?= self::e($sKey) ?>">
            <div class="v2-section-title">
                <?php if ($bullet): ?><span class="bullet"><?= self::e($bullet) ?></span><?php endif; ?>
                <?= self::e($sTitle) ?>
            </div>
            <?php if ($sBlurb): ?>
                <p class="form-hint" style="margin-top:-6px;margin-bottom:14px;"><?= self::e($sBlurb) ?></p>
            <?php endif; ?>

            <?php foreach ($section['fields'] ?? [] as $field) {
                $this->renderField($field, $section);
            } ?>
        </div>
        <?php
    }

    public function renderField(array $f, array $section = []): void {
        $type = $f['type'] ?? 'text';
        switch ($type) {
            case 'number':
            case 'text':
                $this->renderScalar($f, $type);
                break;
            case 'select':
                $this->renderSelect($f);
                break;
            case 'textarea':
                $this->renderTextarea($f);
                break;
            case 'radio-yesno':
                $this->renderRadioYesNo($f);
                break;
            case 'repeat-group':
                $this->renderRepeatGroup($f);
                break;
            default:
                echo '<!-- unknown field type: ' . self::e($type) . ' -->';
        }
    }

    // ─── Renderers ────────────────────────────────────────────────────────

    private function renderScalar(array $f, string $type): void {
        $key   = $f['key'];
        $value = $this->resolve($f);
        $req   = !empty($f['required']) ? '<span class="req">*</span>' : '';
        $hint  = $f['help']  ?? '';
        $unit  = $f['unit']  ?? '';
        $placeholder = $f['placeholder'] ?? ($type === 'number' ? '0.00' : '');
        $step  = $f['step']  ?? ($type === 'number' ? 'any' : null);
        $min   = $f['min']   ?? null;
        $max   = $f['max']   ?? null;
        $cols  = (int)($f['cols'] ?? 6);  // 12-col grid
        $tipTitle = $f['tip'] ?? '';
        ?>
        <div class="v2-field" data-key="<?= self::e($key) ?>" style="<?= $cols < 12 ? "max-width:" . ($cols/12 * 100) . "%;" : "" ?>">
            <label><?= self::e($f['label'] ?? $key) ?> <?= $req ?>
                <?php if ($tipTitle): ?><span class="hint-icon" title="<?= self::e($tipTitle) ?>">?</span><?php endif; ?>
            </label>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="<?= self::e($type) ?>" name="<?= self::e($key) ?>"
                       value="<?= self::e((string)$value) ?>"
                       <?= $step !== null ? 'step="' . self::e((string)$step) . '"' : '' ?>
                       <?= $min  !== null ? 'min="'  . self::e((string)$min)  . '"' : '' ?>
                       <?= $max  !== null ? 'max="'  . self::e((string)$max)  . '"' : '' ?>
                       <?= !empty($f['required']) ? 'required' : '' ?>
                       placeholder="<?= self::e((string)$placeholder) ?>"
                       style="flex:1;">
                <?php if ($unit): ?>
                    <span style="color:var(--v2-muted);font-size:0.82rem;white-space:nowrap;"><?= self::e($unit) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($hint): ?><div class="hint"><?= self::e($hint) ?></div><?php endif; ?>
        </div>
        <?php
    }

    private function renderSelect(array $f): void {
        $key   = $f['key'];
        $value = (string)$this->resolve($f);
        $req   = !empty($f['required']) ? '<span class="req">*</span>' : '';
        $hint  = $f['help'] ?? '';
        $opts  = $f['options'] ?? [];
        ?>
        <div class="v2-field" data-key="<?= self::e($key) ?>">
            <label><?= self::e($f['label'] ?? $key) ?> <?= $req ?></label>
            <select name="<?= self::e($key) ?>" <?= !empty($f['required']) ? 'required' : '' ?>>
                <option value="">Select…</option>
                <?php foreach ($opts as $opt):
                    $v = is_array($opt) ? ($opt['value'] ?? '') : $opt;
                    $l = is_array($opt) ? ($opt['label'] ?? $v) : $opt;
                    ?>
                    <option value="<?= self::e($v) ?>" <?= $value === $v ? 'selected' : '' ?>>
                        <?= self::e($l) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($hint): ?><div class="hint"><?= self::e($hint) ?></div><?php endif; ?>
        </div>
        <?php
    }

    private function renderTextarea(array $f): void {
        $key = $f['key']; $v = (string)$this->resolve($f);
        $req = !empty($f['required']) ? '<span class="req">*</span>' : '';
        ?>
        <div class="v2-field" data-key="<?= self::e($key) ?>">
            <label><?= self::e($f['label'] ?? $key) ?> <?= $req ?></label>
            <textarea name="<?= self::e($key) ?>" <?= !empty($f['required']) ? 'required' : '' ?>
                      placeholder="<?= self::e($f['placeholder'] ?? '') ?>"><?= self::e($v) ?></textarea>
            <?php if (!empty($f['help'])): ?><div class="hint"><?= self::e($f['help']) ?></div><?php endif; ?>
        </div>
        <?php
    }

    private function renderRadioYesNo(array $f): void {
        $key = $f['key'];
        $v = $this->resolve($f);
        $isYes = ($v === 1 || $v === '1' || $v === true);
        $yesLabel = $f['yes_label'] ?? 'Yes';
        $noLabel  = $f['no_label']  ?? 'No';
        ?>
        <div class="v2-field" data-key="<?= self::e($key) ?>">
            <label><?= self::e($f['label'] ?? $key) ?></label>
            <div class="v2-toggle">
                <input type="radio" name="<?= self::e($key) ?>" id="<?= self::e($key) ?>_no" value="0" <?= !$isYes ? 'checked' : '' ?>>
                <label for="<?= self::e($key) ?>_no"><?= self::e($noLabel) ?></label>
                <input type="radio" name="<?= self::e($key) ?>" id="<?= self::e($key) ?>_yes" value="1" <?= $isYes ? 'checked' : '' ?>>
                <label for="<?= self::e($key) ?>_yes"><?= self::e($yesLabel) ?></label>
            </div>
            <?php if (!empty($f['help'])): ?><div class="hint"><?= self::e($f['help']) ?></div><?php endif; ?>
        </div>
        <?php
    }

    private function renderRepeatGroup(array $f): void {
        $key    = $f['key'];
        $cols   = $f['columns'] ?? [];
        $raw    = $this->resolve($f);  // Expected shape: ['width' => [...], 'area' => [...]]
        $rows   = $this->normaliseGroupRows($raw, $cols);
        $addLabel = $f['add_label'] ?? 'Add row';
        $title    = $f['label'] ?? $key;
        ?>
        <div class="v2-field" data-key="<?= self::e($key) ?>">
            <label><?= self::e($title) ?></label>
            <?php if (!empty($f['help'])): ?><div class="hint" style="margin-bottom:8px;margin-top:0;"><?= self::e($f['help']) ?></div><?php endif; ?>
            <table class="v2-rep-tbl" id="<?= self::e($key) ?>_tbl" style="width:100%;font-size:0.84rem;border-collapse:collapse;">
                <thead>
                    <tr style="font-size:0.68rem;color:var(--v2-muted);text-transform:uppercase;letter-spacing:0.04em;">
                        <?php foreach ($cols as $c): ?>
                            <th style="padding:6px 8px;text-align:left;font-weight:700;"><?= self::e($c['label'] ?? $c['key']) ?></th>
                        <?php endforeach; ?>
                        <th style="width:30px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr class="v2-rep-row">
                            <?php foreach ($cols as $c):
                                $ck = $c['key']; $t = $c['type'] ?? 'text'; $u = $c['unit'] ?? '';
                                $rv = $r[$ck] ?? ''; ?>
                                <td style="padding:4px;">
                                    <div style="display:flex;gap:4px;align-items:center;">
                                        <input type="<?= self::e($t) ?>" step="any" name="<?= self::e($key) ?>[<?= self::e($ck) ?>][]"
                                               value="<?= self::e((string)$rv) ?>"
                                               style="flex:1;padding:7px 9px;border:1px solid var(--v2-border);border-radius:6px;font-size:0.84rem;font-family:'JetBrains Mono',Consolas,monospace;">
                                        <?php if ($u): ?><span style="color:var(--v2-muted);font-size:0.72rem;white-space:nowrap;"><?= self::e($u) ?></span><?php endif; ?>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                            <td style="padding:4px;">
                                <button type="button" onclick="v2RepRm(this)" title="Remove"
                                        style="background:transparent;border:0;color:var(--v2-danger);cursor:pointer;padding:4px 8px;">×</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" onclick="v2RepAdd('<?= self::e($key) ?>')"
                    style="background:transparent;color:var(--v2-primary);border:1px dashed #c7d2fe;padding:6px 14px;border-radius:6px;font-size:0.76rem;font-weight:600;cursor:pointer;margin-top:6px;">
                + <?= self::e($addLabel) ?>
            </button>
        </div>
        <?php
    }

    /** Normalise group row data into [ [col1 => v, col2 => v], ... ] regardless of input shape. */
    private function normaliseGroupRows($raw, array $cols): array {
        if (!is_array($raw)) return [[]];
        // "column of arrays" shape: ['width' => [a,b], 'area' => [c,d]]
        $hasColArrays = false;
        foreach ($cols as $c) {
            if (isset($raw[$c['key']]) && is_array($raw[$c['key']])) { $hasColArrays = true; break; }
        }
        $rows = [];
        if ($hasColArrays) {
            $count = 0;
            foreach ($cols as $c) {
                if (isset($raw[$c['key']]) && is_array($raw[$c['key']])) {
                    $count = max($count, count($raw[$c['key']]));
                }
            }
            for ($i = 0; $i < $count; $i++) {
                $r = [];
                foreach ($cols as $c) {
                    $r[$c['key']] = $raw[$c['key']][$i] ?? '';
                }
                $rows[] = $r;
            }
        }
        if (empty($rows)) $rows[] = [];
        return $rows;
    }

    /** Walk a dotted path like "step_2.gross_plot_area_sqm" against $values. Default from schema if missing. */
    private function resolve(array $f) {
        $path   = $f['path'] ?? $f['key'];
        $parts  = explode('.', $path);
        $cur    = $this->values;
        foreach ($parts as $p) {
            if (is_array($cur) && array_key_exists($p, $cur)) $cur = $cur[$p];
            else return $f['default'] ?? '';
        }
        if ($cur === null || $cur === '') return $f['default'] ?? '';
        return $cur;
    }

    // ─── Validation (server-side) ─────────────────────────────────────────

    /** Returns an array of error messages (empty = OK). */
    public function validate(array $post): array {
        $errs = [];
        foreach ($this->sections() as $section) {
            foreach (($section['fields'] ?? []) as $f) {
                $errs = array_merge($errs, $this->validateField($f, $post));
            }
        }
        return $errs;
    }

    private function validateField(array $f, array $post): array {
        $errs = [];
        $key  = $f['key'];
        if (($f['type'] ?? '') === 'repeat-group') {
            // Not required by default; data check happens field-by-field at save time.
            return [];
        }
        $v = $post[$key] ?? '';
        if (!empty($f['required']) && (is_string($v) ? trim($v) === '' : empty($v))) {
            $errs[] = ($f['label'] ?? $key) . ' is required';
        }
        if (($f['type'] ?? '') === 'number' && $v !== '' && !is_numeric($v)) {
            $errs[] = ($f['label'] ?? $key) . ' must be a number';
        }
        if (isset($f['min']) && is_numeric($v) && (float)$v < (float)$f['min']) {
            $errs[] = ($f['label'] ?? $key) . ' must be ≥ ' . $f['min'];
        }
        return $errs;
    }

    private static function e($s): string {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}
