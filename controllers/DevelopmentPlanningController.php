<?php
/**
 * DevelopmentPlanningController — Post-Proposal Development Planning.
 *
 * Workflow:
 *   Accepted proposal → Regulation picker → Dynamic input form → Calculate → View breakdown → Finalize
 *
 * Routes (see index.php):
 *   ?page=planning                                   — list of accepted proposals + their plans
 *   ?page=planning_start&proposal_id=N               — regulation picker for a proposal
 *   ?page=planning_create (POST proposal_id, reg)    — create a draft plan
 *   ?page=planning_form&plan_id=N                    — dynamic input form
 *   ?page=planning_save (POST plan_id, inputs...)    — save inputs + run calculator
 *   ?page=planning_view&plan_id=N                    — breakdown view
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/DCPRRuleLoader.php';
require_once __DIR__ . '/../lib/calculators/Reg33_10_Calculator.php';
require_once __DIR__ . '/../lib/calculators/Reg33_11_Calculator.php';
require_once __DIR__ . '/../lib/calculators/Reg33_20B_Calculator.php';
require_once __DIR__ . '/../lib/calculators/Reg33_5_Calculator.php';
require_once __DIR__ . '/../lib/calculators/Reg33_10_FinancialsCalculator.php';
require_once __DIR__ . '/../lib/validators/PlanValidator.php';
require_once __DIR__ . '/../lib/PlanAIAssistant.php';
require_once __DIR__ . '/../lib/DocumentStorage.php';
require_once __DIR__ . '/../lib/DocumentTextExtractor.php';
require_once __DIR__ . '/../lib/DAExtractionEngine.php';

class DevelopmentPlanningController {

    /** Fully-calculatable regulations — input schema + calculator + validator all in place. */
    private const SUPPORTED_REGULATIONS = [
        '33(5)'    => 'Development / Redevelopment of MHADA Housing Schemes',
        '33(10)'   => 'Redevelopment for Rehabilitation of Slum Dwellers (SRA)',
        '33(11)'   => 'Permanent Transit Camp / Rental Housing (SRA)',
        '33(20)(B)'=> 'Affordable Housing on Private Land — 30(A) + 33(20)(B) composite',
    ];

    /**
     * Rules loaded in dcpr_rules but without a calculator yet.
     */
    private const PENDING_REGULATIONS = [
        '33(7)'    => 'Cessed Buildings in Island City (MHAD Act 1976)',
        '33(7)(A)' => 'Dilapidated Tenanted Buildings (Suburbs / Non-cessed in City)',
        '33(7)(B)' => 'Non-Cessed Residential Housing Societies Redevelopment',
        '33(9)'    => 'Cluster Redevelopment (CDS / Urban Renewal)',
        '33(20)'   => 'Affordable Housing on Govt/MCGM Land — 33(20)(A) (calculator pending)',
    ];

    /** GET ?page=planning — list accepted proposals + any plans against them. */
    public static function index(): void {
        $db = getDB();

        $acceptedProposals = $db->query("
            SELECT p.id, p.proposal_name, p.proposal_type, p.region, p.city, p.status, p.plot_area_sqm, p.updated_at
            FROM proposals p
            WHERE p.status = 'Accepted'
            ORDER BY p.updated_at DESC
        ");
        $acceptedProposals = $acceptedProposals ? $acceptedProposals->fetch_all(MYSQLI_ASSOC) : [];

        $plans = $db->query("
            SELECT dp.id, dp.proposal_id, dp.regulation_key, dp.plan_name, dp.status, dp.version,
                   dp.parent_plan_id, dp.scenario_label,
                   dp.updated_at, dp.calculated_at,
                   p.proposal_name, p.region, p.city
            FROM development_plans dp
            JOIN proposals p ON p.id = dp.proposal_id
            ORDER BY dp.proposal_id DESC, COALESCE(dp.parent_plan_id, dp.id), dp.parent_plan_id IS NULL DESC, dp.created_at ASC
        ");
        $plans = $plans ? $plans->fetch_all(MYSQLI_ASSOC) : [];

        // Group into base plan + scenario children for display
        $planTree = [];
        foreach ($plans as $p) {
            $parentId = $p['parent_plan_id'] ?: $p['id'];
            if (!isset($planTree[$parentId])) {
                $planTree[$parentId] = ['base' => null, 'scenarios' => []];
            }
            if ($p['parent_plan_id']) {
                $planTree[$parentId]['scenarios'][] = $p;
            } else {
                $planTree[$parentId]['base'] = $p;
            }
        }

        // Cross-flow sync — also load Area Statements per proposal so both
        // Planning (legacy) and Area Analysis show a unified per-proposal view.
        $asRes = $db->query("SELECT s.id, s.proposal_id, s.statement_name, s.selected_regulation_key,
                                    s.status, s.version_major, s.version_minor, s.current_step,
                                    s.updated_at, s.calculated_at, s.locked_at
                             FROM area_statements s
                             ORDER BY s.proposal_id DESC, s.version_major DESC, s.version_minor DESC");
        $areaStatementsByProposal = [];
        while ($asRes && $r = $asRes->fetch_assoc()) {
            $areaStatementsByProposal[$r['proposal_id']][] = $r;
        }

        require __DIR__ . '/../views/planning/index.php';
    }

    /** GET ?page=planning_start&proposal_id=N — pick a regulation to start planning. */
    public static function start(): void {
        // Legacy planning is superseded by Area Analysis. Redirect any old bookmarks / links.
        $proposalId = (int)($_GET['proposal_id'] ?? 0);
        flashSet('info', 'Development Planning has been replaced by <b>Area Analysis</b>. '
                       . 'Starting your new plan there — the new 6-step wizard applies applicable DCPR rules automatically.');
        redirect($proposalId
            ? "index.php?page=area_analysis"  // land on picker with proposal filter-hint via UI
            : 'index.php?page=area_analysis');
    }

    /** POST ?page=planning_create — creation route retired (Phase F). Redirect. */
    public static function create(): void {
        flashSet('info', 'New Development Plans are now created through <b>Area Analysis</b>. Redirecting…');
        redirect('index.php?page=area_analysis');
        return;
        // ─── Legacy code retained below for reference (unreachable) ───
        $proposalId    = (int)($_POST['proposal_id'] ?? 0);
        $regulationKey = trim((string)($_POST['regulation_key'] ?? ''));

        if (!$proposalId || !isset(self::SUPPORTED_REGULATIONS[$regulationKey])) {
            flashSet('danger', 'Invalid proposal or unsupported regulation.');
            redirect('index.php?page=planning');
            return;
        }

        $db = getDB();
        $stmt = $db->prepare("SELECT id, proposal_name FROM proposals WHERE id = ? AND status = 'Accepted'");
        $stmt->bind_param('i', $proposalId);
        $stmt->execute();
        $proposal = $stmt->get_result()->fetch_assoc();
        if (!$proposal) {
            flashSet('danger', 'Proposal not found or not accepted.');
            redirect('index.php?page=planning');
            return;
        }

        $userId  = (int)TokenAuth::userId();
        $planName = $proposal['proposal_name'] . ' — DCPR ' . $regulationKey;

        $insert = $db->prepare("INSERT INTO development_plans (proposal_id, regulation_key, plan_name, status, created_by)
                                VALUES (?, ?, ?, 'draft', ?)");
        $insert->bind_param('issi', $proposalId, $regulationKey, $planName, $userId);
        $insert->execute();
        $planId = $insert->insert_id;

        // Pre-fill inputs from proposal row (source=proposal)
        self::prefillFromProposal($db, $planId, $proposalId, $regulationKey);

        flashSet('success', 'Development plan created. Fill in the inputs to calculate.');
        redirect("index.php?page=planning_form&plan_id={$planId}");
    }

    /** GET ?page=planning_form&plan_id=N — render dynamic input form. */
    public static function form(): void {
        $planId = (int)($_GET['plan_id'] ?? 0);
        $plan = self::loadPlan($planId);
        if (!$plan) { flashSet('danger', 'Plan not found.'); redirect('index.php?page=planning'); return; }

        $schema = self::loadInputSchema($plan['regulation_key']);
        if (!$schema) {
            flashSet('danger', 'No input schema found for regulation ' . $plan['regulation_key'] . '. Place a JSON file under dcpr_rules/schemas/.');
            redirect('index.php?page=planning');
            return;
        }

        $inputs = self::loadInputs($planId);

        require __DIR__ . '/../views/planning/form.php';
    }

    /** POST ?page=planning_save — save inputs and run calculator. */
    public static function save(): void {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $plan = self::loadPlan($planId);
        if (!$plan) { flashSet('danger', 'Plan not found.'); redirect('index.php?page=planning'); return; }

        $schema = self::loadInputSchema($plan['regulation_key']);
        if (!$schema) { flashSet('danger', 'Missing input schema.'); redirect('index.php?page=planning'); return; }

        $db = getDB();

        // Persist every schema field from POST — even blanks (blanks are important for defaults).
        $inputMap = [];
        foreach ($schema['groups'] as $group) {
            foreach ($group['fields'] as $field) {
                $key = $field['key'];
                $val = $_POST[$key] ?? '';
                $regRef = $field['regulation_ref'] ?? null;

                $stmt = $db->prepare("INSERT INTO plan_inputs (plan_id, input_key, input_value, input_type, input_source, regulation_ref)
                                      VALUES (?, ?, ?, ?, 'user', ?)
                                      ON DUPLICATE KEY UPDATE input_value = VALUES(input_value), regulation_ref = VALUES(regulation_ref)");
                $type = $field['type'] ?? 'string';
                $stmt->bind_param('issss', $planId, $key, $val, $type, $regRef);
                $stmt->execute();

                $inputMap[$key] = $val;
            }
        }

        // Run calculator based on regulation
        try {
            $calcs = self::runCalculator($plan['regulation_key'], $inputMap);
            self::persistCalculations($db, $planId, $calcs);

            // Run validator after calculation. This writes plan_validations rows.
            $validator = new PlanValidator($planId, $plan['regulation_key']);
            $counts = $validator->runAndPersist();

            // Status: 'validated' if no red; 'calculated' if any red (developer must fix).
            $newStatus = ($counts['red'] > 0) ? 'calculated' : 'validated';
            $upd = $db->prepare("UPDATE development_plans SET status = ?, calculated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $upd->bind_param('si', $newStatus, $planId);
            $upd->execute();

            $msg = "Calculation complete — " . count($calcs) . " values computed.  ";
            $msg .= "Validation: {$counts['green']} passed, {$counts['yellow']} warning" . ($counts['yellow'] === 1 ? '' : 's') . ", {$counts['red']} blocker" . ($counts['red'] === 1 ? '' : 's') . ".";
            flashSet($counts['red'] > 0 ? 'warning' : 'success', $msg);
        } catch (Throwable $e) {
            flashSet('danger', 'Calculation failed: ' . $e->getMessage());
            redirect("index.php?page=planning_form&plan_id={$planId}");
            return;
        }

        redirect("index.php?page=planning_view&plan_id={$planId}");
    }

    /** GET ?page=planning_view&plan_id=N — breakdown view. */
    public static function view(): void {
        $planId = (int)($_GET['plan_id'] ?? 0);
        $plan = self::loadPlan($planId);
        if (!$plan) { flashSet('danger', 'Plan not found.'); redirect('index.php?page=planning'); return; }

        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM proposals WHERE id = ?");
        $stmt->bind_param('i', $plan['proposal_id']);
        $stmt->execute();
        $proposal = $stmt->get_result()->fetch_assoc();

        $inputs = self::loadInputs($planId);

        $calcRes = $db->query("SELECT * FROM plan_calculations WHERE plan_id = " . (int)$planId . " ORDER BY display_order");
        $calcs = $calcRes ? $calcRes->fetch_all(MYSQLI_ASSOC) : [];

        // Group calcs by calc_group for section rendering
        $grouped = [];
        foreach ($calcs as $c) {
            $grouped[$c['calc_group']][] = $c;
        }

        // Load validations, ordered red → yellow → green so the panel surfaces issues first
        $valRes = $db->query("SELECT * FROM plan_validations WHERE plan_id = " . (int)$planId . "
                              ORDER BY FIELD(status, 'red', 'yellow', 'green'), check_key");
        $validations = $valRes ? $valRes->fetch_all(MYSQLI_ASSOC) : [];
        $validationCounts = ['red' => 0, 'yellow' => 0, 'green' => 0];
        foreach ($validations as $v) $validationCounts[$v['status']]++;

        require __DIR__ . '/../views/planning/view.php';
    }

    /**
     * POST ?page=planning_delete — delete a plan (and all its scenarios if it's a base plan).
     * Cascades: plan_inputs, plan_calculations, plan_validations, plan_financials.
     * Gated by edit_proposals.
     */
    public static function delete(): void {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $plan = self::loadPlan($planId);
        if (!$plan) { flashSet('danger', 'Plan not found.'); redirect('index.php?page=planning'); return; }

        $db = getDB();

        // Collect all plan ids that will be deleted:
        //   - the target plan itself
        //   - if it's a base plan (parent_plan_id IS NULL), all its scenarios
        $targets = [$planId];
        if (empty($plan['parent_plan_id'])) {
            $r = $db->query("SELECT id FROM development_plans WHERE parent_plan_id = " . (int)$planId);
            while ($r && $row = $r->fetch_assoc()) $targets[] = (int)$row['id'];
        }
        $inList = implode(',', array_map('intval', $targets));

        // Explicit deletes (only plan_financials has an FK cascade — the rest we handle manually)
        $db->query("DELETE FROM plan_inputs       WHERE plan_id IN ($inList)");
        $db->query("DELETE FROM plan_calculations WHERE plan_id IN ($inList)");
        $db->query("DELETE FROM plan_validations  WHERE plan_id IN ($inList)");
        $db->query("DELETE FROM plan_financials   WHERE plan_id IN ($inList)");
        $db->query("DELETE FROM development_plans WHERE id      IN ($inList)");

        $count = count($targets);
        $msg = $count === 1
            ? 'Plan deleted.'
            : "Base plan + " . ($count - 1) . " scenario(s) deleted.";
        flashSet('success', $msg);

        // Redirect: if we came from the plan view of a deleted plan, land back on planning list
        $redir = trim((string)($_POST['return_to'] ?? '')) ?: 'index.php?page=planning';
        // If the return points to a plan-view that was just deleted, fall back to planning list
        foreach ($targets as $tid) {
            if (strpos($redir, "plan_id=$tid") !== false) { $redir = 'index.php?page=planning'; break; }
        }
        redirect($redir);
    }

    /**
     * POST ?page=planning_prefill_da — pull da_extractions rows (auto/verified/edited)
     * into this plan's plan_inputs. Rejected + needs_review rows are skipped.
     */
    public static function prefillFromDA(): void {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $plan = self::loadPlan($planId);
        if (!$plan) { flashSet('danger', 'Plan not found.'); redirect('index.php?page=planning'); return; }

        [$filled, $skipped] = self::applyExtractionsToPlan($plan);
        flashSet('success', "Prefilled {$filled} field(s) from verified DA extractions." . ($skipped > 0 ? " Skipped {$skipped} field(s) not in the planning schema." : ""));
        redirect("index.php?page=planning_form&plan_id={$planId}");
    }

    /**
     * POST ?page=planning_upload_autofill — one-click flow from the planning form:
     * upload a DA / feasibility document → extract text → run Gemini extraction →
     * prefill the plan inputs from the auto-vetted rows. All in one round-trip.
     */
    public static function uploadAndAutofill(): void {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $docType = trim((string)($_POST['doc_type'] ?? 'DA'));
        $plan = self::loadPlan($planId);
        if (!$plan) { flashSet('danger', 'Plan not found.'); redirect('index.php?page=planning'); return; }

        $proposalId = (int)$plan['proposal_id'];
        $backUrl    = "index.php?page=planning_form&plan_id={$planId}";

        if (empty($_FILES['file']) || empty($_FILES['file']['name']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
            flashSet('warning', 'No file selected.'); redirect($backUrl); return;
        }
        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            flashSet('danger', 'Upload error (' . $_FILES['file']['error'] . ').'); redirect($backUrl); return;
        }

        $db = getDB();
        $userId = (int)TokenAuth::userId();
        $name = $_FILES['file']['name'];

        // 1. Reserve doc row → store file → update stored_path/mime/size
        $ins = $db->prepare("INSERT INTO project_documents (proposal_id, filename, stored_path, mime_type, doc_type, size_bytes, uploaded_by)
                             VALUES (?, ?, '', '', ?, 0, ?)");
        $ins->bind_param('issi', $proposalId, $name, $docType, $userId);
        $ins->execute();
        $docId = $ins->insert_id;

        $stored = DocumentStorage::store($proposalId, [
            'name'     => $name,
            'tmp_name' => $_FILES['file']['tmp_name'],
            'size'     => $_FILES['file']['size'],
            'type'     => $_FILES['file']['type'],
        ], $docId);
        if (!$stored['ok']) {
            $db->query("DELETE FROM project_documents WHERE id = " . (int)$docId);
            flashSet('danger', 'Upload failed: ' . $stored['error']); redirect($backUrl); return;
        }
        $upd = $db->prepare("UPDATE project_documents SET stored_path = ?, mime_type = ?, size_bytes = ? WHERE id = ?");
        $upd->bind_param('ssii', $stored['stored_path'], $stored['mime'], $stored['size'], $docId);
        $upd->execute();

        // 2. Extract text (pdftotext / OCR / DOCX)
        $textResult = DocumentTextExtractor::extract($docId);
        if (!$textResult['ok']) {
            flashSet('warning', 'File uploaded but text extraction failed: ' . ($textResult['notes'] ?? 'unknown') .
                                '. Try running "Extract Project Data with AI" from the Documents page.');
            redirect($backUrl); return;
        }

        // 3. Run AI extraction across all proposal documents
        $exResult = DAExtractionEngine::extractProposal($proposalId);
        if (!$exResult['ok']) {
            flashSet('warning', 'File uploaded but AI extraction failed: ' . ($exResult['error'] ?? 'unknown') .
                                '. Open Documents to retry.');
            redirect($backUrl); return;
        }

        // 4. Apply auto-vetted extractions to this plan's inputs
        [$filled, $skipped] = self::applyExtractionsToPlan($plan);

        $extractedCount = (int)($exResult['extracted_count'] ?? 0);
        $msg = "Uploaded «{$name}» — AI extracted {$extractedCount} field(s), auto-filled {$filled} into this plan.";
        if ($skipped > 0) $msg .= " {$skipped} extracted field(s) were outside this scheme's input schema.";
        $msg .= " Review the values below before calculating.";
        flashSet('success', $msg);
        redirect($backUrl);
    }

    /**
     * Shared helper: apply all auto/verified/edited DA extractions for a proposal
     * onto a plan's plan_inputs. Returns [$filled, $skipped].
     */
    private static function applyExtractionsToPlan(array $plan): array {
        $db = getDB();
        $planId = (int)$plan['id'];

        $stmt = $db->prepare("SELECT field_key, normalized_value, extracted_value, page_no, clause_ref, document_id, status
                              FROM da_extractions
                              WHERE proposal_id = ?
                                AND status IN ('auto', 'verified', 'edited')
                                AND (normalized_value IS NOT NULL AND normalized_value <> '')");
        $stmt->bind_param('i', $plan['proposal_id']);
        $stmt->execute();
        $res = $stmt->get_result();

        $filled = 0; $skipped = 0;
        $upsert = $db->prepare("INSERT INTO plan_inputs (plan_id, input_key, input_value, input_type, input_source, regulation_ref)
                                VALUES (?, ?, ?, 'number', 'derived', ?)
                                ON DUPLICATE KEY UPDATE input_value = VALUES(input_value), input_source = 'derived', regulation_ref = VALUES(regulation_ref)");

        while ($row = $res->fetch_assoc()) {
            $key = $row['field_key'];
            if (!self::isPlanningInputKey($plan['regulation_key'], $key)) { $skipped++; continue; }
            $val = $row['normalized_value'];
            $srcRef = 'DA';
            if ($row['clause_ref']) $srcRef .= ' — ' . $row['clause_ref'];
            if ($row['page_no'])    $srcRef .= ' · p.' . $row['page_no'];
            $upsert->bind_param('isss', $planId, $key, $val, $srcRef);
            $upsert->execute();
            $filled++;
        }
        return [$filled, $skipped];
    }

    private static function isPlanningInputKey(string $regulationKey, string $fieldKey): bool {
        $sanitized = str_replace(['(', ')', ' '], ['_', '_', '_'], $regulationKey);
        $sanitized = trim(preg_replace('/_+/', '_', $sanitized), '_');
        $path = __DIR__ . '/../dcpr_rules/schemas/reg_' . $sanitized . '_inputs.json';
        if (!file_exists($path)) return false;
        $schema = json_decode(file_get_contents($path), true);
        foreach ($schema['groups'] ?? [] as $g) {
            foreach ($g['fields'] ?? [] as $f) {
                if (($f['key'] ?? '') === $fieldKey) return true;
            }
        }
        return false;
    }

    /**
     * POST ?page=planning_clone — duplicate a plan as a scenario.
     * Inputs: plan_id, scenario_label
     * Creates a new plan row with parent_plan_id, copies plan_inputs, auto-recalculates.
     */
    public static function cloneScenario(): void {
        $planId  = (int)($_POST['plan_id'] ?? 0);
        $label   = trim((string)($_POST['scenario_label'] ?? ''));
        if ($planId <= 0 || $label === '') {
            flashSet('danger', 'Missing plan_id or scenario label.');
            redirect('index.php?page=planning');
            return;
        }

        $base = self::loadPlan($planId);
        if (!$base) { flashSet('danger', 'Base plan not found.'); redirect('index.php?page=planning'); return; }

        $db = getDB();
        $userId = (int)TokenAuth::userId();
        $newName = $base['plan_name'] . ' — ' . $label;

        // Root parent (chain of scenarios all root to the original base)
        $rootId = $base['parent_plan_id'] ?: $base['id'];

        $stmt = $db->prepare("INSERT INTO development_plans
            (proposal_id, regulation_key, plan_name, status, parent_plan_id, scenario_label, created_by)
            VALUES (?, ?, ?, 'draft', ?, ?, ?)");
        $stmt->bind_param('issisi', $base['proposal_id'], $base['regulation_key'], $newName, $rootId, $label, $userId);
        $stmt->execute();
        $newPlanId = $stmt->insert_id;

        // Copy plan_inputs (source stays 'user' — this is the same intent, editable in the clone)
        $ins = $db->prepare("INSERT INTO plan_inputs (plan_id, input_key, input_value, input_type, input_source, regulation_ref)
                             SELECT ?, input_key, input_value, input_type, input_source, regulation_ref
                             FROM plan_inputs WHERE plan_id = ?");
        $ins->bind_param('ii', $newPlanId, $planId);
        $ins->execute();

        // Auto-recalculate so the scenario is immediately comparable
        $inputMap = self::loadInputs($newPlanId);
        try {
            $calcs = self::runCalculator($base['regulation_key'], $inputMap);
            self::persistCalculations($db, $newPlanId, $calcs);
            $validator = new PlanValidator($newPlanId, $base['regulation_key']);
            $counts = $validator->runAndPersist();
            $newStatus = ($counts['red'] > 0) ? 'calculated' : 'validated';
            $upd = $db->prepare("UPDATE development_plans SET status = ?, calculated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $upd->bind_param('si', $newStatus, $newPlanId);
            $upd->execute();
        } catch (Throwable $e) {
            flashSet('warning', "Scenario cloned but auto-recalculate failed: " . $e->getMessage());
        }

        flashSet('success', "Scenario '{$label}' created. Edit inputs to explore what-if.");
        redirect("index.php?page=planning_form&plan_id={$newPlanId}");
    }

    /**
     * GET ?page=planning_compare&base_id=X&scenario_id=Y — side-by-side compare view.
     */
    public static function compare(): void {
        $baseId = (int)($_GET['base_id'] ?? 0);
        $scenId = (int)($_GET['scenario_id'] ?? 0);
        if ($baseId <= 0 || $scenId <= 0 || $baseId === $scenId) {
            flashSet('danger', 'Pick two distinct plans to compare.');
            redirect('index.php?page=planning');
            return;
        }

        $basePlan = self::loadPlan($baseId);
        $scenPlan = self::loadPlan($scenId);
        if (!$basePlan || !$scenPlan) {
            flashSet('danger', 'One of the plans was not found.');
            redirect('index.php?page=planning');
            return;
        }

        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM proposals WHERE id = ?");
        $stmt->bind_param('i', $basePlan['proposal_id']);
        $stmt->execute();
        $proposal = $stmt->get_result()->fetch_assoc();

        $baseInputs = self::loadInputs($baseId);
        $scenInputs = self::loadInputs($scenId);
        $baseCalcs  = self::loadCalcs($baseId);
        $scenCalcs  = self::loadCalcs($scenId);
        $baseVals   = self::loadValidations($baseId);
        $scenVals   = self::loadValidations($scenId);

        require __DIR__ . '/../views/planning/compare.php';
    }

    private static function loadCalcs(int $planId): array {
        $db = getDB();
        $res = $db->query("SELECT * FROM plan_calculations WHERE plan_id = " . (int)$planId . " ORDER BY display_order");
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $map = [];
        foreach ($rows as $r) $map[$r['calc_key']] = $r;
        return $map;
    }

    private static function loadValidations(int $planId): array {
        $db = getDB();
        $res = $db->query("SELECT * FROM plan_validations WHERE plan_id = " . (int)$planId);
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $map = [];
        foreach ($rows as $r) $map[$r['check_key']] = $r;
        return $map;
    }

    /**
     * POST ?page=api_planning_assistant — grounded regulatory Q&A on a plan.
     * Input JSON: {plan_id, question, history}
     * Output JSON: {success, answer, citations, warnings, grounded}
     */
    public static function apiAssistant(): void {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'POST required.']);
            return;
        }
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $planId   = (int)($input['plan_id'] ?? 0);
        $question = trim((string)($input['question'] ?? ''));
        $history  = is_array($input['history'] ?? null) ? $input['history'] : [];

        if ($planId <= 0 || $question === '') {
            echo json_encode(['success' => false, 'error' => 'plan_id and question required.']);
            return;
        }

        try {
            $result = PlanAIAssistant::ask($planId, $question, $history);
            echo json_encode($result);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => 'Assistant failed: ' . $e->getMessage()]);
        }
    }

    /**
     * GET ?page=planning_cross_scheme&proposal_id=N — cross-scheme comparison of all
     * calculated plans under a single proposal (e.g. 33(5) vs 33(10) vs 33(11) vs 33(20)(B)).
     * Emits a normalized metric table so a manager can compare Sale, Handover, FSI, Approver.
     */
    public static function crossScheme(): void {
        $proposalId = (int)($_GET['proposal_id'] ?? 0);
        if ($proposalId <= 0) { flashSet('danger', 'Proposal not specified.'); redirect('index.php?page=planning'); return; }

        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM proposals WHERE id = ?");
        $stmt->bind_param('i', $proposalId);
        $stmt->execute();
        $proposal = $stmt->get_result()->fetch_assoc();
        if (!$proposal) { flashSet('danger', 'Proposal not found.'); redirect('index.php?page=planning'); return; }

        // Load all calculated plans (base + scenarios) for this proposal
        $planRes = $db->query("SELECT * FROM development_plans
                               WHERE proposal_id = " . (int)$proposalId . "
                                 AND status IN ('calculated','validated','finalized')
                               ORDER BY parent_plan_id IS NOT NULL, id");
        $plans = $planRes ? $planRes->fetch_all(MYSQLI_ASSOC) : [];

        if (empty($plans)) {
            flashSet('warning', 'No calculated plans found for this proposal. Create + calculate at least one plan under a scheme first.');
            redirect('index.php?page=planning');
            return;
        }

        // For each plan, load its calc rows and normalize into common metrics
        $columns = [];
        foreach ($plans as $p) {
            $calcs = self::loadPlanCalcsByKey((int)$p['id']);
            $columns[] = [
                'plan'    => $p,
                'metrics' => self::normalizeCrossSchemeMetrics($p['regulation_key'], $calcs),
            ];
        }

        // Pick "best-fit" — highest full_scheme_bua_sqm
        $bestIdx = 0;
        $bestBua = -INF;
        foreach ($columns as $i => $c) {
            $bua = $c['metrics']['full_scheme_bua_sqm']['value'] ?? 0;
            if ($bua > $bestBua) { $bestBua = $bua; $bestIdx = $i; }
        }

        require __DIR__ . '/../views/planning/cross_scheme.php';
    }

    /**
     * Normalize scheme-specific calc_keys into a common cross-scheme metric map.
     * Every scheme emits FSI + BUA under different names — this maps them onto a
     * common shape so the comparison table can be scheme-agnostic.
     */
    private static function normalizeCrossSchemeMetrics(string $regKey, array $calcs): array {
        $g = fn($k) => isset($calcs[$k]) ? (float)$calcs[$k]['calc_value'] : null;

        // Base metrics (identical calc_keys across all schemes)
        $out = [
            'gross_plot_area_sqm'  => ['label' => 'Gross Plot Area',              'unit' => 'sqm',    'value' => $g('gross_plot_area_sqm')],
            'net_plot_area_sqm'    => ['label' => 'Net Plot Area (for FSI)',      'unit' => 'sqm',    'value' => $g('net_plot_area_sqm')],
            'full_scheme_fsi'      => ['label' => 'Full Scheme FSI (incl. fung.)', 'unit' => 'ratio',  'value' => $g('full_scheme_fsi') ?? $g('fsi_sanctioned')],
            'full_scheme_bua_sqm'  => ['label' => 'Full Scheme BUA (incl. fung.)', 'unit' => 'sqm',    'value' => $g('full_scheme_bua_sqm') ?? $g('total_bua_approved_sqm')],
        ];

        // Scheme-specific mapping: Owner/Sale side + Handover side + Approver
        switch ($regKey) {
            case '33(5)':
                $out['owner_sale_bua_sqm']  = ['label' => 'Society Share (incl. Rehab + Incentive + Balance) BUA', 'unit' => 'sqm', 'value' => $g('society_grand_total_sqm')];
                $out['handover_bua_sqm']    = ['label' => 'MHADA Handover BUA',                                    'unit' => 'sqm', 'value' => $g('mhada_grand_total_sqm')];
                $out['handover_authority']  = ['label' => 'Approving Authority',                                   'unit' => '',    'value_text' => 'MHADA'];
                $out['approver_note']       = ['label' => 'Basic Ratio (LR/RC) → Sharing',                         'unit' => '',    'value_text' => sprintf('%.2f → Society %d%% / MHADA %d%%', $g('basic_ratio') ?? 0, $g('society_share_pct') ?? 0, $g('mhada_share_pct') ?? 0)];
                break;

            case '33(10)':
                $out['owner_sale_bua_sqm']  = ['label' => 'Sale Component BUA',        'unit' => 'sqm', 'value' => $g('sale_component_sqm')];
                $out['handover_bua_sqm']    = ['label' => 'Rehab (SRA) Component BUA', 'unit' => 'sqm', 'value' => $g('rehab_component_sqm')];
                $out['handover_authority']  = ['label' => 'Approving Authority',       'unit' => '',    'value_text' => 'SRA'];
                $out['approver_note']       = ['label' => 'Rehab-to-Sale Ratio',       'unit' => '',    'value_text' => sprintf('%.2f (effective)', $g('sale_ratio_effective') ?? 0)];
                break;

            case '33(11)':
                $out['owner_sale_bua_sqm']  = ['label' => 'Sale + Society Total BUA',              'unit' => 'sqm', 'value' => $g('sale_society_total_sqm')];
                $out['handover_bua_sqm']    = ['label' => 'SRA-PTC Handover BUA',                  'unit' => 'sqm', 'value' => $g('sra_ptc_total_sqm')];
                $out['handover_authority']  = ['label' => 'Approving Authority',                   'unit' => '',    'value_text' => 'SRA'];
                $out['approver_note']       = ['label' => 'SRA / Sale Split of Additional FSI',    'unit' => '',    'value_text' => 'Industry 63% / 37%'];
                break;

            case '33(20)(B)':
                $out['owner_sale_bua_sqm']  = ['label' => 'Owner (Sale) Total BUA',   'unit' => 'sqm', 'value' => $g('owner_total_sqm')];
                $out['handover_bua_sqm']    = ['label' => 'AH Handover BUA (MCGM)',   'unit' => 'sqm', 'value' => $g('ah_total_sqm')];
                $out['handover_authority']  = ['label' => 'Approving Authority',      'unit' => '',    'value_text' => 'MCGM'];
                $out['approver_note']       = ['label' => 'Owner / AH Split of Additional FSI', 'unit' => '', 'value_text' => '50% / 50%'];
                break;

            default:
                $out['handover_authority']  = ['label' => 'Approving Authority', 'unit' => '', 'value_text' => 'Not mapped'];
        }

        return $out;
    }

    /** GET ?page=planning_report&plan_id=N — print-optimized feasibility report. */
    public static function report(): void {
        $planId = (int)($_GET['plan_id'] ?? 0);
        $plan = self::loadPlan($planId);
        if (!$plan) { flashSet('danger', 'Plan not found.'); redirect('index.php?page=planning'); return; }

        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM proposals WHERE id = ?");
        $stmt->bind_param('i', $plan['proposal_id']);
        $stmt->execute();
        $proposal = $stmt->get_result()->fetch_assoc();

        $inputs = self::loadInputs($planId);

        $calcRes = $db->query("SELECT * FROM plan_calculations WHERE plan_id = " . (int)$planId . " ORDER BY display_order");
        $calcs = $calcRes ? $calcRes->fetch_all(MYSQLI_ASSOC) : [];
        $grouped = [];
        foreach ($calcs as $c) { $grouped[$c['calc_group']][] = $c; }

        $valRes = $db->query("SELECT * FROM plan_validations WHERE plan_id = " . (int)$planId . "
                              ORDER BY FIELD(status, 'red', 'yellow', 'green'), check_key");
        $validations = $valRes ? $valRes->fetch_all(MYSQLI_ASSOC) : [];
        $validationCounts = ['red' => 0, 'yellow' => 0, 'green' => 0];
        foreach ($validations as $v) $validationCounts[$v['status']]++;

        // Financial model (optional — only rendered if the user has run it)
        $finStmt = $db->prepare("SELECT * FROM plan_financials WHERE plan_id = ?");
        $finStmt->bind_param('i', $planId);
        $finStmt->execute();
        $finRow = $finStmt->get_result()->fetch_assoc();
        $financialYearly  = [];
        $financialLoan    = [];
        $financialSummary = [];
        $financialLabel   = '';
        $hasFinancials    = false;
        if ($finRow && !empty($finRow['summary_output_json'])) {
            $financialYearly  = json_decode($finRow['yearly_output_json']  ?? '[]', true) ?: [];
            $financialLoan    = json_decode($finRow['loan_schedule_json']  ?? '[]', true) ?: [];
            $financialSummary = json_decode($finRow['summary_output_json'] ?? '{}', true) ?: [];
            $financialLabel   = $finRow['scenario_label'] ?? '';
            $hasFinancials    = true;
        }

        $currentUser = currentUser();

        require __DIR__ . '/../views/planning/report.php';
    }

    /** GET ?page=planning_financials_form&plan_id=N — financial-model input form. */
    public static function financialsForm(): void {
        $planId = (int)($_GET['plan_id'] ?? 0);
        $plan = self::loadPlan($planId);
        if (!$plan) { flashSet('danger', 'Plan not found.'); redirect('index.php?page=planning'); return; }

        $db = getDB();
        $fin = self::loadFinancials($planId);   // may return defaults if none stored yet

        require __DIR__ . '/../views/planning/financials_form.php';
    }

    /** POST ?page=planning_financials_save — save inputs and run model. */
    public static function financialsSave(): void {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $plan = self::loadPlan($planId);
        if (!$plan) { flashSet('danger', 'Plan not found.'); redirect('index.php?page=planning'); return; }

        $db = getDB();

        // Coerce inputs
        $scalars = [
            'project_duration_years' => (int)($_POST['project_duration_years'] ?? 7),
            'scenario_label'         => trim((string)($_POST['scenario_label'] ?? '')),
            'sale_rate_y1_inr_sqft'  => self::pnum($_POST['sale_rate_y1_inr_sqft'] ?? 0),
            'rehab_construction_rate_y1_inr_sqft' => self::pnum($_POST['rehab_construction_rate_y1_inr_sqft'] ?? 0),
            'sale_construction_rate_y1_inr_sqft'  => self::pnum($_POST['sale_construction_rate_y1_inr_sqft'] ?? 0),
            'annual_escalation_pct'  => self::pnum($_POST['annual_escalation_pct'] ?? 5),
            'rera_carpet_loading_pct'=> self::pnum($_POST['rera_carpet_loading_pct'] ?? 88),
            'saleable_rera_carpet_sqft_override' => self::pnum($_POST['saleable_rera_carpet_sqft_override'] ?? 0) ?: null,
            'loan_principal_inr'     => self::pnum($_POST['loan_principal_inr'] ?? 0),
            'loan_interest_rate_pct' => self::pnum($_POST['loan_interest_rate_pct'] ?? 11),
            'loan_moratorium_years'  => (int)($_POST['loan_moratorium_years'] ?? 3),
            'loan_repayment_start_year' => (int)($_POST['loan_repayment_start_year'] ?? 5),
            'loan_repayment_years'   => (int)($_POST['loan_repayment_years'] ?? 3),
            'marketing_pct_of_sales' => self::pnum($_POST['marketing_pct_of_sales'] ?? 3),
            'admin_pct_of_costs'     => self::pnum($_POST['admin_pct_of_costs'] ?? 3),
            'contingency_pct_of_costs' => self::pnum($_POST['contingency_pct_of_costs'] ?? 4),
            'other_costs_pct_of_costs' => self::pnum($_POST['other_costs_pct_of_costs'] ?? 3),
            'society_rent_lump_inr'  => self::pnum($_POST['society_rent_lump_inr'] ?? 0),
            'society_rent_year_a'    => (int)($_POST['society_rent_year_a'] ?? 1),
            'society_rent_year_b'    => (int)($_POST['society_rent_year_b'] ?? 3),
            'society_other_lump_inr' => self::pnum($_POST['society_other_lump_inr'] ?? 0),
            'society_other_year'     => (int)($_POST['society_other_year'] ?? 1),
            'equity_investment_inr'  => self::pnum($_POST['equity_investment_inr'] ?? 0),
            'surplus_share_partner_pct' => self::pnum($_POST['surplus_share_partner_pct'] ?? 22),
        ];

        $curveKeys = ['booking_curve', 'collection_curve', 'rehab_cost_curve', 'sale_cost_curve', 'premium_curve'];
        $curves = [];
        foreach ($curveKeys as $ck) {
            $arr = $_POST[$ck] ?? [];
            if (!is_array($arr)) $arr = [];
            $vals = array_map(fn($v) => (float)$v / 100.0, $arr);
            $curves[$ck . '_json'] = json_encode(array_values($vals));
        }

        // Upsert
        $cols = array_merge(array_keys($scalars), array_keys($curves));
        $placeholders = implode(',', array_fill(0, count($cols) + 1, '?'));
        $colList = implode(',', array_merge(['plan_id'], $cols));
        $updates = implode(',', array_map(fn($c) => "$c = VALUES($c)", $cols));

        $sql = "INSERT INTO plan_financials ($colList) VALUES ($placeholders) ON DUPLICATE KEY UPDATE $updates";
        $stmt = $db->prepare($sql);
        $vals = array_merge([$planId], array_values($scalars), array_values($curves));
        $types = str_repeat('s', count($vals));
        $stmt->bind_param($types, ...$vals);
        $stmt->execute();

        // Load calc rows for the plan into a key-map
        $planCalcs = self::loadPlanCalcsByKey($planId);
        $fin = self::loadFinancials($planId);
        $calc = new Reg33_10_FinancialsCalculator($fin, $planCalcs);
        $result = $calc->run();

        // Persist outputs
        $snap = $result['snapshot'];
        $upd = $db->prepare("UPDATE plan_financials
            SET yearly_output_json = ?, summary_output_json = ?, loan_schedule_json = ?, warnings_json = ?,
                snapshot_sale_bua_gross_sqft = ?, snapshot_rera_carpet_sqft = ?,
                snapshot_rehab_construction_sqft = ?, snapshot_sale_construction_sqft = ?,
                snapshot_premium_total_inr = ?,
                calculated_at = CURRENT_TIMESTAMP
            WHERE plan_id = ?");
        $y = json_encode($result['yearly']);
        $s = json_encode($result['summary']);
        $l = json_encode($result['loan']);
        $w = json_encode($result['warnings']);
        $upd->bind_param('ssssdddddi',
            $y, $s, $l, $w,
            $snap['sale_bua_gross_sqft'], $snap['rera_carpet_sqft'],
            $snap['rehab_construction_sqft'], $snap['sale_construction_sqft'],
            $snap['premium_total_inr'],
            $planId
        );
        $upd->execute();

        $sum = $result['summary'];
        flashSet('success', 'Financial model calculated — Total revenue ₹' . number_format($sum['total_revenue_inr']/1e7, 2) . ' Cr, Project IRR ' . ($sum['project_irr_pct'] ?? 'n/a') . '%');
        redirect("index.php?page=planning_financials_view&plan_id=$planId");
    }

    /** GET ?page=planning_financials_view&plan_id=N — render computed model. */
    public static function financialsView(): void {
        $planId = (int)($_GET['plan_id'] ?? 0);
        $plan = self::loadPlan($planId);
        if (!$plan) { flashSet('danger', 'Plan not found.'); redirect('index.php?page=planning'); return; }

        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM plan_financials WHERE plan_id = ?");
        $stmt->bind_param('i', $planId);
        $stmt->execute();
        $fin = $stmt->get_result()->fetch_assoc();
        if (!$fin || !$fin['yearly_output_json']) {
            flashSet('warning', 'No financial model calculated yet — fill in the inputs to run.');
            redirect("index.php?page=planning_financials_form&plan_id=$planId");
            return;
        }

        $yearly  = json_decode($fin['yearly_output_json'], true) ?: [];
        $loan    = json_decode($fin['loan_schedule_json'], true) ?: [];
        $summary = json_decode($fin['summary_output_json'], true) ?: [];
        $warnings= json_decode($fin['warnings_json'] ?: '[]', true) ?: [];

        require __DIR__ . '/../views/planning/financials_view.php';
    }

    private static function loadFinancials(int $planId): array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM plan_financials WHERE plan_id = ?");
        $stmt->bind_param('i', $planId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) return $row;
        // Defaults matching Parksite Sheet3 (Ghatkopar-scale)
        return [
            'plan_id'                => $planId,
            'project_duration_years' => 7,
            'scenario_label'         => '',
            'sale_rate_y1_inr_sqft'  => 18000,
            'rehab_construction_rate_y1_inr_sqft' => 2200,
            'sale_construction_rate_y1_inr_sqft'  => 2700,
            'annual_escalation_pct'  => 5.0,
            'rera_carpet_loading_pct'=> 88.0,
            'saleable_rera_carpet_sqft_override' => null,
            'loan_principal_inr'     => 4000000000,
            'loan_interest_rate_pct' => 11.0,
            'loan_moratorium_years'  => 3,
            'loan_repayment_start_year' => 5,
            'loan_repayment_years'   => 3,
            'marketing_pct_of_sales' => 3.0,
            'admin_pct_of_costs'     => 3.0,
            'contingency_pct_of_costs' => 4.0,
            'other_costs_pct_of_costs' => 3.0,
            'society_rent_lump_inr'  => 1200000000,
            'society_rent_year_a'    => 1,
            'society_rent_year_b'    => 3,
            'society_other_lump_inr' => 150000000,
            'society_other_year'     => 1,
            'equity_investment_inr'  => 350000000,
            'surplus_share_partner_pct' => 22.0,
            'booking_curve_json'     => json_encode([0, 0.15, 0.20, 0.20, 0.20, 0.20, 0.05]),
            'collection_curve_json'  => json_encode([0.30, 0.30, 0.55, 0.70, 0.80, 0.90, 1.00]),
            'rehab_cost_curve_json'  => json_encode([0.15, 0.20, 0.25, 0.25, 0.15, 0, 0]),
            'sale_cost_curve_json'   => json_encode([0.05, 0.10, 0.15, 0.20, 0.20, 0.20, 0.10]),
            'premium_curve_json'     => json_encode([0.15, 0.15, 0.10, 0.20, 0.20, 0.10, 0.10]),
        ];
    }

    private static function loadPlanCalcsByKey(int $planId): array {
        $db = getDB();
        $res = $db->query("SELECT * FROM plan_calculations WHERE plan_id = " . (int)$planId);
        $map = [];
        while ($res && $row = $res->fetch_assoc()) {
            $map[$row['calc_key']] = $row;
        }
        return $map;
    }

    private static function pnum($v): float {
        if ($v === '' || $v === null) return 0.0;
        return (float)str_replace([',', ' '], '', (string)$v);
    }

    // ─── Internal helpers ─────────────────────────────────────────────────

    private static function loadPlan(int $planId): ?array {
        if ($planId <= 0) return null;
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM development_plans WHERE id = ?");
        $stmt->bind_param('i', $planId);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        return $r ?: null;
    }

    private static function loadInputs(int $planId): array {
        $db = getDB();
        $stmt = $db->prepare("SELECT input_key, input_value FROM plan_inputs WHERE plan_id = ?");
        $stmt->bind_param('i', $planId);
        $stmt->execute();
        $res = $stmt->get_result();
        $map = [];
        while ($row = $res->fetch_assoc()) {
            $map[$row['input_key']] = $row['input_value'];
        }
        return $map;
    }

    private static function loadInputSchema(string $regulationKey): ?array {
        // Filenames use underscores instead of parens: 33(10) → reg_33_10_inputs.json
        $sanitized = str_replace(['(', ')', ' '], ['_', '_', '_'], $regulationKey);
        $sanitized = trim(preg_replace('/_+/', '_', $sanitized), '_');
        $path = __DIR__ . '/../dcpr_rules/schemas/reg_' . $sanitized . '_inputs.json';
        if (!file_exists($path)) return null;
        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    private static function prefillFromProposal(mysqli $db, int $planId, int $proposalId, string $regKey): void {
        $schema = self::loadInputSchema($regKey);
        if (!$schema) return;

        $stmt = $db->prepare("SELECT * FROM proposals WHERE id = ?");
        $stmt->bind_param('i', $proposalId);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        if (!$p) return;

        $proposalToInput = [
            'village'              => $p['region']       ?? '',
            'location'             => ($p['region'] ?? '') . ($p['city'] ? ', ' . $p['city'] : ''),
            'site_address'         => $p['site_address'] ?? '',
            'pincode'              => $p['pincode']      ?? '',
            'gross_plot_area_sqm'  => $p['plot_area_sqm'] ?? '',
        ];

        foreach ($schema['groups'] as $group) {
            foreach ($group['fields'] as $field) {
                if (($field['source'] ?? 'user') !== 'proposal') continue;
                $val = $proposalToInput[$field['key']] ?? '';
                if ($val === '' || $val === null) continue;
                $regRef = $field['regulation_ref'] ?? null;
                $type = $field['type'] ?? 'string';
                $stmt2 = $db->prepare("INSERT INTO plan_inputs (plan_id, input_key, input_value, input_type, input_source, regulation_ref)
                                       VALUES (?, ?, ?, ?, 'proposal', ?)
                                       ON DUPLICATE KEY UPDATE input_value = VALUES(input_value)");
                $stmt2->bind_param('issss', $planId, $field['key'], $val, $type, $regRef);
                $stmt2->execute();
            }
        }
    }

    private static function runCalculator(string $regulationKey, array $inputs): array {
        switch ($regulationKey) {
            case '33(5)':
                return (new Reg33_5_Calculator($inputs))->compute();
            case '33(10)':
                return (new Reg33_10_Calculator($inputs))->compute();
            case '33(11)':
                return (new Reg33_11_Calculator($inputs))->compute();
            case '33(20)(B)':
                return (new Reg33_20B_Calculator($inputs))->compute();
            default:
                throw new RuntimeException("No calculator for regulation $regulationKey yet.");
        }
    }

    private static function persistCalculations(mysqli $db, int $planId, array $calcs): void {
        // Wipe previous calcs for this plan (idempotent recalc)
        $del = $db->prepare("DELETE FROM plan_calculations WHERE plan_id = ?");
        $del->bind_param('i', $planId);
        $del->execute();

        $ins = $db->prepare("INSERT INTO plan_calculations
            (plan_id, calc_group, calc_key, calc_label, calc_value, unit,
             formula, formula_inputs, regulation_ref, rule_id, display_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($calcs as $c) {
            $ins->bind_param(
                'isssdssssii',
                $planId,
                $c['calc_group'],
                $c['calc_key'],
                $c['calc_label'],
                $c['calc_value'],
                $c['unit'],
                $c['formula'],
                $c['formula_inputs'],
                $c['regulation_ref'],
                $c['rule_id'],
                $c['display_order']
            );
            $ins->execute();
        }
    }
}
