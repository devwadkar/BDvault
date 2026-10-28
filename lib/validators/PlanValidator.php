<?php
/**
 * PlanValidator — generic assertion runner.
 *
 * Reads:
 *   - a plan's inputs (plan_inputs)
 *   - a plan's calculations (plan_calculations)
 *   - the assertions JSON for the plan's regulation (dcpr_rules/validations/reg_*_checks.json)
 *
 * Writes plan_validations rows: green | yellow | red with delta, message, regulation_ref, recommended_action.
 *
 * Extending to a new regulation: drop reg_XX_YY_checks.json under dcpr_rules/validations/.
 * The runner is scheme-agnostic; the assertions file is data.
 */

require_once __DIR__ . '/../../config/database.php';

class PlanValidator {

    private int $planId;
    private string $regulationKey;
    private array $inputs = [];   // input_key => value (typed)
    private array $calcs  = [];   // calc_key  => calc row
    private array $rulesFile = [];
    /** @var array of emitted validation rows (before persistence) */
    private array $emitted = [];

    public function __construct(int $planId, string $regulationKey) {
        $this->planId = $planId;
        $this->regulationKey = $regulationKey;
        $this->loadPlanState();
        $this->loadAssertionsFile();
    }

    /**
     * Evaluate every assertion and persist the results. Returns [red, yellow, green, total].
     */
    public function runAndPersist(): array {
        $this->emitted = [];

        foreach ($this->rulesFile['checks'] ?? [] as $check) {
            $this->evaluate($check);
        }

        $db = getDB();
        $del = $db->prepare("DELETE FROM plan_validations WHERE plan_id = ?");
        $del->bind_param('i', $this->planId);
        $del->execute();

        $ins = $db->prepare("INSERT INTO plan_validations
            (plan_id, check_key, check_label, status, entered_value, required_value, delta, message, regulation_ref, rule_id, recommended_action)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($this->emitted as $v) {
            $ins->bind_param(
                'isssssssssi',
                $v['plan_id'],
                $v['check_key'],
                $v['check_label'],
                $v['status'],
                $v['entered_value'],
                $v['required_value'],
                $v['delta'],
                $v['message'],
                $v['regulation_ref'],
                $v['recommended_action'],
                $v['rule_id']
            );
            $ins->execute();
        }

        $counts = ['red' => 0, 'yellow' => 0, 'green' => 0];
        foreach ($this->emitted as $v) $counts[$v['status']]++;
        $counts['total'] = count($this->emitted);
        return $counts;
    }

    // ─── State loading ─────────────────────────────────────────────────────

    private function loadPlanState(): void {
        $db = getDB();

        $stmt = $db->prepare("SELECT input_key, input_value FROM plan_inputs WHERE plan_id = ?");
        $stmt->bind_param('i', $this->planId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $this->inputs[$row['input_key']] = $row['input_value'];
        }

        $stmt = $db->prepare("SELECT calc_key, calc_value, unit FROM plan_calculations WHERE plan_id = ?");
        $stmt->bind_param('i', $this->planId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $this->calcs[$row['calc_key']] = $row;
        }
    }

    private function loadAssertionsFile(): void {
        $sanitized = str_replace(['(', ')', ' '], ['_', '_', '_'], $this->regulationKey);
        $sanitized = trim(preg_replace('/_+/', '_', $sanitized), '_');
        $path = __DIR__ . '/../../dcpr_rules/validations/reg_' . $sanitized . '_checks.json';
        if (!file_exists($path)) {
            $this->rulesFile = ['checks' => []];
            return;
        }
        $data = json_decode(file_get_contents($path), true);
        $this->rulesFile = is_array($data) ? $data : ['checks' => []];
    }

    // ─── Assertion evaluation ─────────────────────────────────────────────

    private function evaluate(array $check): void {
        $type = $check['type'] ?? '';
        try {
            switch ($type) {
                case 'required':          $this->evalRequired($check);       break;
                case 'min':               $this->evalMin($check);            break;
                case 'max':               $this->evalMax($check);            break;
                case 'between':           $this->evalBetween($check);        break;
                case 'less_than_or_equal':$this->evalCompare($check, '<=');  break;
                case 'less_than':         $this->evalCompare($check, '<');   break;
                case 'greater_than':      $this->evalCompare($check, '>');   break;
                default:
                    $this->emit($check, 'yellow', null, null, null,
                        "Unknown check type '$type' — skipped");
            }
        } catch (Throwable $e) {
            $this->emit($check, 'yellow', null, null, null,
                "Check failed to evaluate: " . $e->getMessage());
        }
    }

    // ─── Evaluators ───────────────────────────────────────────────────────

    private function evalRequired(array $check): void {
        $val = $this->readSource($check);
        $present = ($val !== null) && ($val !== '') && !(is_numeric($val) && (float)$val <= 0);
        if ($present) {
            $this->emit($check, 'green', (string)$val, 'present', null,
                'OK — value entered.');
        } else {
            $this->emit($check, $check['severity_on_fail'] ?? 'red',
                (string)($val ?? ''), 'required', null,
                'Required value missing or non-positive.');
        }
    }

    private function evalMin(array $check): void {
        $val = $this->readSource($check);
        $min = (float)($check['min'] ?? 0);
        if ($val === null || $val === '') {
            $this->emit($check, $check['severity_on_fail'] ?? 'yellow',
                '(empty)', '≥ ' . $min, null,
                'Value not entered — cannot check minimum.');
            return;
        }
        $v = (float)$val;
        if ($v >= $min) {
            $this->emit($check, 'green', $this->fmt($v), '≥ ' . $this->fmt($min),
                '+' . $this->fmt($v - $min),
                'OK — within limit.');
        } else {
            $this->emit($check, $check['severity_on_fail'] ?? 'red',
                $this->fmt($v), '≥ ' . $this->fmt($min),
                '-' . $this->fmt($min - $v),
                'Below minimum — see recommended action.');
        }
    }

    private function evalMax(array $check): void {
        $val = $this->readSource($check);
        $max = (float)($check['max'] ?? 0);
        if ($val === null || $val === '') {
            $this->emit($check, $check['severity_on_fail'] ?? 'yellow',
                '(empty)', '≤ ' . $max, null,
                'Value not entered — cannot check maximum.');
            return;
        }
        $v = (float)$val;
        if ($v <= $max) {
            $this->emit($check, 'green', $this->fmt($v), '≤ ' . $this->fmt($max),
                $this->fmt($max - $v),
                'OK — within limit.');
        } else {
            $this->emit($check, $check['severity_on_fail'] ?? 'red',
                $this->fmt($v), '≤ ' . $this->fmt($max),
                '+' . $this->fmt($v - $max),
                'Exceeds maximum — see recommended action.');
        }
    }

    private function evalBetween(array $check): void {
        $val = $this->readSource($check);
        $min = (float)($check['min'] ?? 0);
        $max = (float)($check['max'] ?? PHP_FLOAT_MAX);
        if ($val === null || $val === '') {
            $this->emit($check, $check['severity_on_fail'] ?? 'yellow',
                '(empty)', "$min – $max", null,
                'Value not entered — cannot range-check.');
            return;
        }
        $v = (float)$val;
        if ($v >= $min && $v <= $max) {
            $this->emit($check, 'green', $this->fmt($v), $this->fmt($min) . ' – ' . $this->fmt($max),
                null, 'OK — within range.');
        } else {
            $this->emit($check, $check['severity_on_fail'] ?? 'yellow',
                $this->fmt($v), $this->fmt($min) . ' – ' . $this->fmt($max),
                $v < $min ? '-' . $this->fmt($min - $v) : '+' . $this->fmt($v - $max),
                'Outside expected range.');
        }
    }

    private function evalCompare(array $check, string $op): void {
        $lhs = $this->readSource($check);
        $rhs = $this->readCompare($check);
        $invert = !empty($check['invert']);

        if ($lhs === null || $rhs === null) {
            $this->emit($check, $check['severity_on_fail'] ?? 'yellow',
                (string)$lhs, (string)$rhs, null,
                'Missing input/calc — cannot compare.');
            return;
        }

        $lv = (float)$lhs; $rv = (float)$rhs;
        switch ($op) {
            case '<=': $passed = ($lv <= $rv); break;
            case '<':  $passed = ($lv <  $rv); break;
            case '>':  $passed = ($lv >  $rv); break;
            default:   $passed = false;
        }
        if ($invert) $passed = !$passed;

        $delta = $this->fmt($lv - $rv);
        if ($passed) {
            $this->emit($check, 'green', $this->fmt($lv), "{$op} " . $this->fmt($rv),
                $delta, 'OK — comparison satisfied.');
        } else {
            $this->emit($check, $check['severity_on_fail'] ?? 'red',
                $this->fmt($lv), "{$op} " . $this->fmt($rv),
                $delta, 'Comparison failed — see recommended action.');
        }
    }

    // ─── Source resolution ────────────────────────────────────────────────

    private function readSource(array $check): ?string {
        $type = $check['source_type'] ?? 'input';
        $key  = $check['source_key']  ?? '';
        return $this->readAny($type, $key);
    }

    private function readCompare(array $check): ?string {
        $type = $check['compare_type'] ?? '';
        if ($type === 'literal') return (string)($check['compare_value'] ?? '');
        if ($type === 'input' || $type === 'calc') {
            return $this->readAny($type, (string)($check['compare_key'] ?? ''));
        }
        return null;
    }

    private function readAny(string $type, string $key): ?string {
        if ($key === '') return null;
        if ($type === 'input') {
            return $this->inputs[$key] ?? null;
        }
        if ($type === 'calc') {
            return isset($this->calcs[$key]) ? (string)$this->calcs[$key]['calc_value'] : null;
        }
        return null;
    }

    // ─── Emit helper ──────────────────────────────────────────────────────

    private function emit(array $check, string $status,
                          ?string $entered, ?string $required, ?string $delta,
                          string $defaultMessage): void {
        $recommended = $check['recommended_action'] ?? null;
        $message = ($status === 'green')
            ? ($check['success_message'] ?? $defaultMessage)
            : ($check['message'] ?? $defaultMessage);

        $this->emitted[] = [
            'plan_id'            => $this->planId,
            'check_key'          => $check['key'],
            'check_label'        => $check['label'] ?? $check['key'],
            'status'             => $status,
            'entered_value'      => $entered,
            'required_value'     => $required,
            'delta'              => $delta,
            'message'            => $message,
            'regulation_ref'     => $check['regulation_ref'] ?? null,
            'rule_id'            => $check['rule_id'] ?? null,
            'recommended_action' => ($status !== 'green') ? $recommended : null,
        ];
    }

    private function fmt(float $v): string {
        // Compact numeric formatting: keep up to 4 dp but strip trailing zeros
        $s = number_format($v, 4, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');
        return $s === '' ? '0' : $s;
    }
}
