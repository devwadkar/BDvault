<?php
/**
 * AreaAnalysisV2Controller — opt-in redesigned Area Analysis wizard.
 *
 * PHASE 1 — Stage 1 only (Project Basics).
 *   Stages 2-5 are stubs until each phase is signed off. v1 wizard stays untouched
 *   so users can roll back by flipping the opt-in toggle on the v1 landing page.
 *
 * Design notes:
 *   • Writes to the SAME `area_statements` table as v1 so Financial Analysis +
 *     Best Outcome continue to work unchanged.
 *   • `wizard_version='v2'` tags a row so the v2 landing shows only its own drafts.
 *   • Universal fields that already have a column go to that column; v2-only
 *     Stage 1 fields (property-card area, zone, island-city flag, approx counts,
 *     primary road width) are stored under draft_json['v2_stage1'] — they'll be
 *     consumed by Stage 2 (recommendation) + Stage 3 (regulation-specific form)
 *     and promoted to columns later only if a calculator needs them.
 *   • NO v1 code paths are touched.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/RegulationSuggester.php';
require_once __DIR__ . '/../lib/SchemaFormRenderer.php';
require_once __DIR__ . '/../lib/AreaCalcEngine.php';

class AreaAnalysisV2Controller {

    public const STAGES = [
        1 => ['title' => 'Project Basics',       'blurb' => 'Universal details — same for every regulation'],
        2 => ['title' => 'Regulation',           'blurb' => 'See the recommended DCPR scheme + alternatives'],
        3 => ['title' => 'Regulation-Specific',  'blurb' => 'Only the fields your chosen scheme needs'],
        4 => ['title' => 'Review',               'blurb' => 'Confirm before generating the statement'],
        5 => ['title' => 'Area Statement',       'blurb' => 'Final output + PDF/Excel export'],
    ];

    // ─── Landing ──────────────────────────────────────────────────────────

    public static function index(): void {
        $db = getDB();

        $accepted = $db->query("SELECT id, proposal_name, proposal_type, region, city, plot_area_sqm, site_address
                                FROM proposals WHERE status='Accepted' ORDER BY id DESC");
        $acceptedProposals = $accepted ? $accepted->fetch_all(MYSQLI_ASSOC) : [];

        $v2 = $db->query("SELECT s.*, p.proposal_name
                          FROM area_statements s
                          JOIN proposals p ON p.id = s.proposal_id
                          WHERE s.wizard_version = 'v2'
                          ORDER BY s.updated_at DESC");
        $v2Statements = $v2 ? $v2->fetch_all(MYSQLI_ASSOC) : [];

        $stages = self::STAGES;
        require __DIR__ . '/../views/area_analysis_v2/index.php';
    }

    // ─── Start a fresh v2 draft ───────────────────────────────────────────

    public static function start(): void {
        $proposalId = (int)($_POST['proposal_id'] ?? $_GET['proposal_id'] ?? 0);
        $proposal   = self::loadProposal($proposalId);
        if (!$proposal) { flashSet('danger', 'Proposal not found.'); redirect('index.php?page=area_analysis_v2'); return; }

        $db = getDB();
        $userId = (int)TokenAuth::userId();

        // Next major version within this proposal (same rule as v1)
        $nextMajor = 1; $nextMinor = 0;
        if ($r = $db->query("SELECT MAX(version_major) AS mx FROM area_statements WHERE proposal_id=" . (int)$proposalId)) {
            $row = $r->fetch_assoc();
            if ($row && $row['mx']) $nextMajor = (int)$row['mx'] + 1;
        }

        $name = $proposal['proposal_name'] . ' — Area Statement v' . $nextMajor . '.' . $nextMinor;
        $projectName = $proposal['proposal_name'];
        $location = trim(($proposal['city'] ?? '') . ($proposal['region'] ? ', ' . $proposal['region'] : ''), ', ');
        $address  = $proposal['site_address'] ?? null;

        $stmt = $db->prepare("INSERT INTO area_statements
            (proposal_id, statement_name, wizard_version, current_step, status,
             version_major, version_minor, project_name, project_location, plot_address, created_by)
            VALUES (?, ?, 'v2', 1, 'draft', ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isiisssi', $proposalId, $name, $nextMajor, $nextMinor,
                                      $projectName, $location, $address, $userId);
        $stmt->execute();
        $newId = $stmt->insert_id;

        // Seed draft_json['v2_stage1'] with the proposal's area so the user sees it pre-filled.
        $seed = [
            'v2_stage1' => [
                'property_card_area_sqm' => $proposal['plot_area_sqm'] ?? null,
            ],
            '_meta' => ['created_at' => date('Y-m-d H:i:s')],
        ];
        $db->query("UPDATE area_statements SET draft_json='" . $db->real_escape_string(json_encode($seed)) . "'
                    WHERE id={$newId}");

        redirect("index.php?page=area_analysis_v2_stage1&statement_id={$newId}");
    }

    // ─── Stage 1 — Project Basics ─────────────────────────────────────────

    public static function stage1(): void {
        $id = (int)($_GET['statement_id'] ?? 0);
        [$statement, $proposal, $draft] = self::loadContextOrRedirect($id);
        $s1       = $draft['v2_stage1'] ?? [];
        $stages   = self::STAGES;
        $currentStage = 1;

        require __DIR__ . '/../views/area_analysis_v2/stage1_basics.php';
    }

    public static function saveStage1(): void {
        $id = (int)($_POST['statement_id'] ?? 0);
        [$statement, , $draft] = self::loadContextOrRedirect($id);
        if ($statement['status'] === 'locked') {
            flashSet('warning', 'This statement is locked. Create a new version to edit.');
            redirect("index.php?page=area_analysis_v2_stage1&statement_id={$id}"); return;
        }

        $db = getDB();
        $direction = trim((string)($_POST['direction'] ?? 'next'));
        $errors    = self::validateStage1($_POST);
        if ($errors) {
            // Still save what was entered so the form keeps the values on re-render.
            $draft['v2_stage1'] = self::collectStage1($_POST, true);
            self::saveDraft($id, $draft);
            flashSet('danger', 'Please fix the highlighted fields: ' . implode(' · ', $errors));
            redirect("index.php?page=area_analysis_v2_stage1&statement_id={$id}"); return;
        }

        // 1) Mirror the universal "column-backed" fields on area_statements.
        $projectName   = trim((string)$_POST['project_name']);
        $ctsNumber     = trim((string)$_POST['cts_number']);
        $plotAddress   = trim((string)$_POST['plot_address']);
        $projectLocation = trim((string)$_POST['project_location']);
        $ward          = trim((string)$_POST['ward']);
        $planningAuth  = trim((string)$_POST['planning_authority']);
        $devType       = trim((string)$_POST['development_type']);

        $stmt = $db->prepare("UPDATE area_statements
            SET project_name=?, cts_number=?, plot_address=?, project_location=?,
                ward=?, planning_authority=?, development_type=?
            WHERE id=?");
        $stmt->bind_param('sssssssi', $projectName, $ctsNumber, $plotAddress, $projectLocation,
                                       $ward, $planningAuth, $devType, $id);
        $stmt->execute();

        // 2) Store the v2-only extra fields under draft_json['v2_stage1'].
        $draft['v2_stage1'] = self::collectStage1($_POST, false);
        $draft['_meta']['stage1_saved_at'] = date('Y-m-d H:i:s');
        self::saveDraft($id, $draft);

        flashSet('success', 'Project basics saved.');
        if ($direction === 'next') {
            redirect("index.php?page=area_analysis_v2_stage2&statement_id={$id}");
        } else {
            redirect("index.php?page=area_analysis_v2_stage1&statement_id={$id}");
        }
    }

    // ─── Stage 2 — Regulation Recommendation ──────────────────────────────

    public static function stage2(): void {
        $id = (int)($_GET['statement_id'] ?? 0);
        [$statement, $proposal, $draft] = self::loadContextOrRedirect($id);
        $s1 = $draft['v2_stage1'] ?? [];

        // Guard: Stage 1 must be done. If missing universal fields, bounce back.
        if (empty($statement['development_type']) || empty($statement['planning_authority'])) {
            flashSet('warning', 'Complete Stage 1 (Project Basics) before picking a regulation.');
            redirect("index.php?page=area_analysis_v2_stage1&statement_id={$id}"); return;
        }

        // Build the context the suggester reads. Everything maps 1:1 from Stage 1.
        $ctx = [
            'development_type'    => $statement['development_type'] ?? '',
            'planning_authority'  => $statement['planning_authority'] ?? '',
            'region'              => !empty($s1['is_island_city']) ? 'Island City' : null,
            'project_location'    => $statement['project_location'] ?? '',
            'gross_plot_area_sqm' => $s1['property_card_area_sqm'] ?? null,
            'road_width_m'        => $s1['primary_road_width_m']   ?? null,
            'tenant_count'        => (int)($s1['approx_tenant_count'] ?? 0),
            'has_existing_bua'    => !empty($s1['has_existing_construction']),
        ];
        $suggestion = RegulationSuggester::suggest($ctx);

        // Persist the suggestion the first time, or whenever inputs changed.
        $db = getDB();
        if (empty($statement['suggested_regulation_key'])
            || $statement['suggested_regulation_key'] !== $suggestion['regulation_key']) {
            $sk = $suggestion['regulation_key']; $sc = (int)$suggestion['confidence']; $sr = $suggestion['rationale'];
            $stmt = $db->prepare("UPDATE area_statements
                SET suggested_regulation_key=?, suggested_confidence=?, suggested_rationale=?
                WHERE id=?");
            $stmt->bind_param('sisi', $sk, $sc, $sr, $id);
            $stmt->execute();
            $statement['suggested_regulation_key'] = $sk;
            $statement['suggested_confidence'] = $sc;
            $statement['suggested_rationale'] = $sr;
        }

        // What was previously selected (or default to the suggestion)
        $selected = $statement['selected_regulation_key'] ?: $suggestion['regulation_key'];

        $stages = self::STAGES;
        $currentStage = 2;
        require __DIR__ . '/../views/area_analysis_v2/stage2_recommendation.php';
    }

    public static function saveStage2(): void {
        $id  = (int)($_POST['statement_id'] ?? 0);
        $key = trim((string)($_POST['selected_regulation_key'] ?? ''));
        [$statement, , ] = self::loadContextOrRedirect($id);
        if ($statement['status'] === 'locked') {
            flashSet('warning', 'This statement is locked. Create a new version to edit.');
            redirect("index.php?page=area_analysis_v2_stage2&statement_id={$id}"); return;
        }

        if ($key === '') {
            flashSet('danger', 'Pick a regulation to continue.');
            redirect("index.php?page=area_analysis_v2_stage2&statement_id={$id}"); return;
        }

        // Guardrail: a 'pending' regulation (no calculator) cannot be the selected one yet —
        // the user can set it later via the classic wizard once/if they want to proceed on
        // review-only, but Stage 3 needs a working calculator to produce the dynamic form.
        $regs = RegulationSuggester::REGULATIONS;
        if (!isset($regs[$key])) {
            flashSet('danger', 'Unknown regulation key.');
            redirect("index.php?page=area_analysis_v2_stage2&statement_id={$id}"); return;
        }
        if (($regs[$key]['status'] ?? '') !== 'calculator') {
            flashSet('warning', 'That regulation has no calculator yet. Pick one of 33(5) · 33(10) · 33(11) · 33(20)(B) to continue, or switch to the classic wizard for review-only use.');
            redirect("index.php?page=area_analysis_v2_stage2&statement_id={$id}"); return;
        }

        $db = getDB();
        $stmt = $db->prepare("UPDATE area_statements SET selected_regulation_key=? WHERE id=?");
        $stmt->bind_param('si', $key, $id);
        $stmt->execute();

        flashSet('success', "Regulation DCPR {$key} locked in. Stage 3 will tailor the form to this scheme.");
        redirect("index.php?page=area_analysis_v2_stage3&statement_id={$id}");
    }

    // ─── Stage 3 — Regulation-specific dynamic form ───────────────────────

    /** Regulation → Stage 3 UI schema filename (under dcpr_rules/ui/). */
    public const STAGE3_SCHEMAS = [
        '33(5)'     => 'reg_33_5_stage3.json',
        '33(10)'    => 'reg_33_10_stage3.json',
        '33(11)'    => 'reg_33_11_stage3.json',
        '33(20)(B)' => 'reg_33_20_B_stage3.json',
    ];

    public static function stage3(): void {
        $id = (int)($_GET['statement_id'] ?? 0);
        [$statement, $proposal, $draft] = self::loadContextOrRedirect($id);
        $s1 = $draft['v2_stage1'] ?? [];

        $reg = $statement['selected_regulation_key'];
        if (!$reg) {
            flashSet('warning', 'Pick a regulation (Stage 2) before configuring details.');
            redirect("index.php?page=area_analysis_v2_stage2&statement_id={$id}"); return;
        }

        if (!isset(self::STAGE3_SCHEMAS[$reg])) {
            $stages = self::STAGES;
            $currentStage = 3;
            require __DIR__ . '/../views/area_analysis_v2/stage3_placeholder.php';
            return;
        }

        // Seed the renderer with existing_buildings rows so the roster pre-fills.
        $draftForRender = $draft;
        $draftForRender['buildings'] = self::loadBuildingsForRoster($id);

        $schemaPath = __DIR__ . '/../dcpr_rules/ui/' . self::STAGE3_SCHEMAS[$reg];
        $renderer = SchemaFormRenderer::fromFile($schemaPath, $draftForRender);
        $calcSummary = self::loadCalcSummary($id);
        $hasCalc = $calcSummary !== null;

        $stages = self::STAGES;
        $currentStage = 3;
        require __DIR__ . '/../views/area_analysis_v2/stage3_regspec.php';
    }

    public static function saveStage3(): void {
        $id = (int)($_POST['statement_id'] ?? 0);
        [$statement, , $draft] = self::loadContextOrRedirect($id);
        if ($statement['status'] === 'locked') {
            flashSet('warning', 'This statement is locked. Create a new version to edit.');
            redirect("index.php?page=area_analysis_v2_stage3&statement_id={$id}"); return;
        }
        $reg = $statement['selected_regulation_key'];
        if (!isset(self::STAGE3_SCHEMAS[$reg])) {
            flashSet('danger', 'Stage 3 schema not available for this regulation yet.');
            redirect("index.php?page=area_analysis_v2_stage3&statement_id={$id}"); return;
        }

        $direction = trim((string)($_POST['direction'] ?? 'next'));

        $step2 = $draft['step_2'] ?? [];
        $step3 = $draft['step_3'] ?? [];

        $pass2 = ['gross_plot_area_sqm','primary_road_width_m','reservation_area_sqm','amenity_area_sqm','reservation_included_in_total'];
        foreach ($pass2 as $k) if (array_key_exists($k, $_POST)) $step2[$k] = $_POST[$k];
        $pass3 = ['s3_eligible_total','s3_carpet_res_sqm','s3_carpet_com_sqm','s3_loading_factor',
                  's3_amenity_built_sqm','sale_bua_proposed_in_situ_sqm'];
        foreach ($pass3 as $k) if (array_key_exists($k, $_POST)) $step3[$k] = $_POST[$k];

        if (isset($_POST['roads']) && is_array($_POST['roads'])) {
            $step2['roads'] = self::compactGroup($_POST['roads'], ['width','area']);
        }
        if (isset($_POST['named']) && is_array($_POST['named'])) {
            $step2['named'] = self::compactGroup($_POST['named'], ['label','area','include']);
        }

        $draft['step_2'] = $step2;
        $draft['step_3'] = $step3;
        $draft['_meta']['stage3_saved_at'] = date('Y-m-d H:i:s');
        self::saveDraft($id, $draft);

        self::syncLandRows($id, $step2);

        // Buildings roster — writes to existing_buildings when the schema uses `buildings`.
        if (isset($_POST['buildings']) && is_array($_POST['buildings'])) {
            self::syncExistingBuildings($id, $_POST['buildings']);
        }

        $result = AreaCalcEngine::run($id);
        if (!$result['ok']) {
            flashSet('danger', 'Calculation failed: ' . ($result['error'] ?? 'unknown error'));
            redirect("index.php?page=area_analysis_v2_stage3&statement_id={$id}"); return;
        }

        $msg = "Saved. Calculator produced {$result['calc_count']} rows.";
        if (($result['discrepancies'] ?? 0) > 0) {
            $msg .= " · {$result['discrepancies']} formula discrepancies flagged — review before locking.";
        }
        flashSet('success', $msg);

        if ($direction === 'next') {
            redirect("index.php?page=area_analysis_v2_stage4&statement_id={$id}");
        } else {
            redirect("index.php?page=area_analysis_v2_stage3&statement_id={$id}");
        }
    }

    // ─── Stage 4 — Review (edit-in-place + validations + generate) ────────

    public static function stage4(): void {
        $id = (int)($_GET['statement_id'] ?? 0);
        [$statement, $proposal, $draft] = self::loadContextOrRedirect($id);
        $s1 = $draft['v2_stage1'] ?? [];
        $calcSummary = self::loadCalcSummary($id);

        if (!$calcSummary) {
            flashSet('warning', 'Run Stage 3 (Save & calculate) before Review.');
            redirect("index.php?page=area_analysis_v2_stage3&statement_id={$id}"); return;
        }

        // Load the full validation + discrepancy details for the Review panel
        $db = getDB();
        $validations = [];
        $vr = $db->query("SELECT check_label, severity, result, entered_value, required_value, message
                          FROM area_statement_valid WHERE statement_id=" . (int)$id . "
                          ORDER BY FIELD(result,'fail','warn','pass'), severity");
        while ($vr && $row = $vr->fetch_assoc()) $validations[] = $row;

        $discrepancies = [];
        $dr = $db->query("SELECT check_label, severity, expected_value, actual_value, variance_pct, formula
                          FROM formula_discrepancies WHERE statement_id=" . (int)$id . "
                          ORDER BY FIELD(severity,'error','warning','info')");
        while ($dr && $row = $dr->fetch_assoc()) $discrepancies[] = $row;

        // Land breakdown for the deductions recap
        $landRows = [];
        $lr = $db->query("SELECT component_key, component_label, area_sqm, is_deduction,
                                 include_in_total_deductions, road_width_m
                          FROM area_statement_land WHERE statement_id=" . (int)$id . "
                          ORDER BY display_order");
        while ($lr && $row = $lr->fetch_assoc()) $landRows[] = $row;

        // Building roster for 33(5) / 33(20)(B)
        $buildings = [];
        $br = $db->query("SELECT * FROM existing_buildings WHERE statement_id=" . (int)$id . " ORDER BY display_order, id");
        while ($br && $row = $br->fetch_assoc()) $buildings[] = $row;

        // Financials — needed for the In-Situ status chip in the Review view
        $financials = [];
        $fr = $db->query("SELECT * FROM area_statement_financials WHERE statement_id=" . (int)$id);
        if ($fr && $frRow = $fr->fetch_assoc()) $financials = $frRow;

        // Count fails — gate the Generate button
        $fails = 0; $warns = 0;
        foreach ($validations as $v) {
            if ($v['result'] === 'fail') $fails++;
            if ($v['result'] === 'warn') $warns++;
        }

        $stages = self::STAGES;
        $currentStage = 4;
        require __DIR__ . '/../views/area_analysis_v2/stage4_review.php';
    }

    // ─── Generate (lock the version → Stage 5) ────────────────────────────

    public static function generate(): void {
        $id = (int)($_POST['statement_id'] ?? 0);
        [$statement, , ] = self::loadContextOrRedirect($id);
        if ($statement['status'] === 'locked') {
            flashSet('info', 'This Area Statement is already locked.');
            redirect("index.php?page=area_analysis_v2_stage5&statement_id={$id}"); return;
        }

        $db = getDB();
        $fails = (int)($db->query("SELECT COUNT(*) AS c FROM area_statement_valid
                                   WHERE statement_id=" . (int)$id . " AND result='fail'")
                          ->fetch_assoc()['c'] ?? 0);
        if ($fails > 0) {
            flashSet('danger', "Can't generate — {$fails} validation error(s) still failing. Review Stage 3 and fix.");
            redirect("index.php?page=area_analysis_v2_stage4&statement_id={$id}"); return;
        }

        if (empty($statement['calculated_at'])) {
            flashSet('warning', 'Run Stage 3 (Save & calculate) before generating.');
            redirect("index.php?page=area_analysis_v2_stage3&statement_id={$id}"); return;
        }

        $db->query("UPDATE area_statements
                    SET status='locked', locked_at=CURRENT_TIMESTAMP, locked_by=" . (int)TokenAuth::userId() . "
                    WHERE id=" . (int)$id);
        flashSet('success', 'Area Statement v' . (int)$statement['version_major'] . '.' . (int)$statement['version_minor'] . ' generated and locked.');
        redirect("index.php?page=area_analysis_v2_stage5&statement_id={$id}");
    }

    // ─── Stage 5 — Final Area Statement ───────────────────────────────────

    public static function stage5(): void {
        $id = (int)($_GET['statement_id'] ?? 0);
        [$statement, $proposal, $draft] = self::loadContextOrRedirect($id);
        $s1 = $draft['v2_stage1'] ?? [];
        $calcSummary = self::loadCalcSummary($id);
        $isLocked = ($statement['status'] === 'locked');

        $stages = self::STAGES;
        $currentStage = 5;
        require __DIR__ . '/../views/area_analysis_v2/stage5_statement.php';
    }

    // Keep old method name as a safe alias for any older link.
    public static function stage4Placeholder(): void { self::stage4(); }

    public static function stage3Placeholder(): void { self::stage3(); }

    /** Normalise a posted repeat-group into column-arrays, trimming empty rows. */
    private static function compactGroup(array $raw, array $cols): array {
        $out = [];
        foreach ($cols as $c) $out[$c] = [];
        $len = 0;
        foreach ($cols as $c) {
            $len = max($len, is_array($raw[$c] ?? null) ? count($raw[$c]) : 0);
        }
        for ($i = 0; $i < $len; $i++) {
            $allBlank = true;
            foreach ($cols as $c) {
                $v = $raw[$c][$i] ?? '';
                if (is_string($v) ? trim($v) !== '' : !empty($v)) { $allBlank = false; break; }
            }
            if ($allBlank) continue;
            foreach ($cols as $c) $out[$c][] = $raw[$c][$i] ?? '';
        }
        return $out;
    }

    /** Rewrite area_statement_land rows from current step_2 so feasibility/PDF see the same breakdown v1 would. */
    private static function syncLandRows(int $statementId, array $step2): void {
        $db = getDB();
        $db->query("DELETE FROM area_statement_land WHERE statement_id=" . (int)$statementId);

        $gross = (float)($step2['gross_plot_area_sqm'] ?? 0);
        $rows = []; $order = 1;
        $rows[] = ['component_key'=>'gross_plot','label'=>'Gross Plot Area','area'=>$gross,'is_deduction'=>0,'include'=>1,'order'=>$order++];

        $roadWidths = (array)(($step2['roads']['width'] ?? []));
        $roadAreas  = (array)(($step2['roads']['area']  ?? []));
        for ($i = 0; $i < count($roadAreas); $i++) {
            $w = (float)($roadWidths[$i] ?? 0);
            $a = (float)($roadAreas[$i] ?? 0);
            if ($a <= 0) continue;
            $rows[] = ['component_key'=>'road_'.($i+1), 'label'=>'D.P. Road Setback', 'area'=>$a,
                       'is_deduction'=>1, 'include'=>1, 'order'=>$order++, 'width'=>$w];
        }

        $namedLabels = (array)(($step2['named']['label'] ?? []));
        $namedAreas  = (array)(($step2['named']['area']  ?? []));
        $namedIncl   = (array)(($step2['named']['include'] ?? []));
        for ($i = 0; $i < count($namedAreas); $i++) {
            $a = (float)($namedAreas[$i] ?? 0);
            $l = trim((string)($namedLabels[$i] ?? 'Other deduction'));
            $inc = !empty($namedIncl[$i]) ? 1 : 0;
            if ($a <= 0) continue;
            $rows[] = ['component_key'=>'named_'.($i+1), 'label'=>$l !== '' ? $l : 'Other deduction',
                       'area'=>$a, 'is_deduction'=>1, 'include'=>$inc, 'order'=>$order++];
        }

        $resv = (float)($step2['reservation_area_sqm'] ?? 0);
        $resvIncluded = !empty($step2['reservation_included_in_total']);
        if ($resv > 0) {
            $rows[] = ['component_key'=>'reservation','label'=>'Reservation Area','area'=>$resv,
                       'is_deduction'=>1, 'include'=>$resvIncluded ? 1 : 0, 'order'=>$order++];
        }

        $am = (float)($step2['amenity_area_sqm'] ?? 0);
        if ($am > 0) {
            $rows[] = ['component_key'=>'amenity','label'=>'Amenity Plot as per Reg. 14(A)','area'=>$am,
                       'is_deduction'=>1, 'include'=>1, 'order'=>$order++];
        }

        $sumDed = 0;
        foreach ($rows as $r) if ($r['is_deduction'] && $r['include']) $sumDed += $r['area'];
        $net = max(0, $gross - $sumDed);
        $rows[] = ['component_key'=>'net_plot','label'=>'Net Plot Area (for FSI computation)','area'=>$net,'is_deduction'=>0,'include'=>1,'order'=>$order++];
        $rows[] = ['component_key'=>'developable','label'=>'Developable Area','area'=>$net,'is_deduction'=>0,'include'=>1,'order'=>$order++];

        $ins = $db->prepare("INSERT INTO area_statement_land
            (statement_id, component_key, component_label, area_sqm, is_deduction,
             include_in_total_deductions, display_order, road_width_m, source)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'user')");
        foreach ($rows as $r) {
            $sw = isset($r['width']) ? (float)$r['width'] : null;
            $ins->bind_param('issdiiid',
                $statementId, $r['component_key'], $r['label'], $r['area'],
                $r['is_deduction'], $r['include'], $r['order'], $sw);
            $ins->execute();
        }
    }

    /** Load existing_buildings rows and reshape as column-arrays for the roster repeat-group. */
    private static function loadBuildingsForRoster(int $statementId): array {
        $db = getDB();
        $r = $db->query("SELECT * FROM existing_buildings WHERE statement_id=" . (int)$statementId . " ORDER BY display_order, id");
        $cols = ['label'=>[], 'res_units'=>[], 'com_units'=>[], 'res_carpet'=>[], 'com_carpet'=>[],
                 'bua'=>[], 'tenants'=>[], 'eligible'=>[]];
        while ($r && $row = $r->fetch_assoc()) {
            $cols['label'][]      = $row['building_label'];
            $cols['res_units'][]  = $row['residential_units'];
            $cols['com_units'][]  = $row['commercial_units'];
            $cols['res_carpet'][] = $row['residential_carpet_sqm'];
            $cols['com_carpet'][] = $row['commercial_carpet_sqm'];
            $cols['bua'][]        = $row['existing_bua_sqm'];
            $cols['tenants'][]    = $row['tenant_count'];
            $cols['eligible'][]   = $row['eligible_tenant_count'];
        }
        return $cols;
    }

    /** Rewrite existing_buildings from a posted buildings[*][] roster. Blank-only rows are skipped. */
    private static function syncExistingBuildings(int $statementId, array $raw): void {
        $db = getDB();
        $db->query("DELETE FROM existing_buildings WHERE statement_id=" . (int)$statementId);

        $cols = ['label','res_units','com_units','res_carpet','com_carpet','bua','tenants','eligible'];
        $len  = 0;
        foreach ($cols as $c) {
            $len = max($len, is_array($raw[$c] ?? null) ? count($raw[$c]) : 0);
        }
        if ($len === 0) return;

        $ins = $db->prepare("INSERT INTO existing_buildings
            (statement_id, building_label, residential_units, commercial_units,
             residential_carpet_sqm, commercial_carpet_sqm, existing_bua_sqm,
             tenant_count, eligible_tenant_count, display_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $order = 0;
        for ($i = 0; $i < $len; $i++) {
            $label = trim((string)($raw['label'][$i] ?? ''));
            $resU  = (int)($raw['res_units'][$i]  ?? 0);
            $comU  = (int)($raw['com_units'][$i]  ?? 0);
            $resC  = (float)($raw['res_carpet'][$i] ?? 0);
            $comC  = (float)($raw['com_carpet'][$i] ?? 0);
            $bua   = (float)($raw['bua'][$i] ?? 0);
            $ten   = (int)($raw['tenants'][$i] ?? 0);
            $elig  = (int)($raw['eligible'][$i] ?? 0);
            if ($label === '' && $resU === 0 && $comU === 0 && $resC === 0 && $comC === 0
                && $bua === 0 && $ten === 0 && $elig === 0) continue;
            if ($label === '') $label = 'Building ' . ($order + 1);
            $order++;
            $ins->bind_param('isiidddiii',
                $statementId, $label, $resU, $comU,
                $resC, $comC, $bua, $ten, $elig, $order);
            $ins->execute();
        }
    }

    /** Load key values for the Stage 4 preview. Falls back to land/calc tables when
     *  the area_statements summary columns are NULL (a pre-existing v1 quirk). */
    private static function loadCalcSummary(int $statementId): ?array {
        $db = getDB();
        $r = $db->query("SELECT * FROM area_statements WHERE id=" . (int)$statementId);
        $s = $r ? $r->fetch_assoc() : null;
        if (!$s || !$s['calculated_at']) return null;

        $rowsRes = $db->query("SELECT calc_key, calc_value, calc_unit, calc_label
                               FROM area_statement_calcs WHERE statement_id=" . (int)$statementId);
        $byKey = [];
        while ($rowsRes && $row = $rowsRes->fetch_assoc()) {
            $byKey[$row['calc_key']] = $row;
        }
        $valid = $db->query("SELECT result, COUNT(*) AS n FROM area_statement_valid
                             WHERE statement_id=" . (int)$statementId . " GROUP BY result");
        $v = ['pass'=>0,'warn'=>0,'fail'=>0];
        while ($valid && $r2 = $valid->fetch_assoc()) $v[$r2['result']] = (int)$r2['n'];

        // Net plot — summary column is often NULL; fall back to land table.
        $netPlot = (float)($s['net_plot_area_sqm'] ?? 0);
        if ($netPlot <= 0) {
            $landR = $db->query("SELECT area_sqm FROM area_statement_land
                                  WHERE statement_id=" . (int)$statementId . " AND component_key='net_plot'");
            if ($landR && $row = $landR->fetch_assoc()) $netPlot = (float)$row['area_sqm'];
        }

        return [
            'calculated_at' => $s['calculated_at'],
            'net_plot'      => $netPlot,
            'total_fsi'     => (float)($s['total_permissible_fsi'] ?? 0),
            'total_bua'     => (float)($s['total_permissible_bua_sqm'] ?? 0),
            'rehab_bua'     => (float)($s['rehab_bua_sqm'] ?? 0),
            'sale_bua'      => (float)($s['sale_bua_sqm'] ?? 0),
            'by_key'        => $byKey,
            'validations'   => $v,
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private static function loadProposal(int $id): ?array {
        if ($id <= 0) return null;
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM proposals WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    private static function loadStatement(int $id): ?array {
        if ($id <= 0) return null;
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM area_statements WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /** Returns [$statement, $proposal, $draft] or redirects to v2 landing on miss. */
    private static function loadContextOrRedirect(int $id): array {
        $statement = self::loadStatement($id);
        if (!$statement) { flashSet('danger', 'Area statement not found.'); redirect('index.php?page=area_analysis_v2'); exit; }
        $proposal = self::loadProposal((int)$statement['proposal_id']);
        $draft = json_decode($statement['draft_json'] ?? '{}', true) ?: [];
        return [$statement, $proposal, $draft];
    }

    private static function saveDraft(int $id, array $draft): void {
        $db = getDB();
        $json = json_encode($draft, JSON_UNESCAPED_UNICODE);
        $stmt = $db->prepare("UPDATE area_statements SET draft_json=? WHERE id=?");
        $stmt->bind_param('si', $json, $id);
        $stmt->execute();
    }

    /** Collect every Stage 1 v2-only field from POST into a flat array. */
    private static function collectStage1(array $post, bool $tolerant): array {
        $flt = function($k) use ($post) {
            $v = $post[$k] ?? null;
            return ($v === '' || $v === null) ? null : (float)$v;
        };
        $yes = function($k) use ($post) { return !empty($post[$k]) ? 1 : 0; };
        return [
            'property_card_area_sqm'   => $flt('property_card_area_sqm'),
            'survey_area_sqm'          => $flt('survey_area_sqm'),
            'primary_road_width_m'     => $flt('primary_road_width_m'),
            'zone_dp'                  => trim((string)($post['zone_dp'] ?? '')),
            'is_island_city'           => $yes('is_island_city'),
            'has_existing_construction'=> $yes('has_existing_construction'),
            'approx_residential_units' => $flt('approx_residential_units'),
            'approx_commercial_units'  => $flt('approx_commercial_units'),
            'approx_tenant_count'      => $flt('approx_tenant_count'),
        ];
    }

    /** Returns an array of error messages (empty means OK). */
    private static function validateStage1(array $post): array {
        $errs = [];
        $req = [
            'project_name'           => 'Project name',
            'cts_number'             => 'CTS / Survey number',
            'plot_address'           => 'Plot address',
            'project_location'       => 'City / region',
            'ward'                   => 'Ward',
            'planning_authority'     => 'Planning authority',
            'development_type'       => 'Development category',
            'property_card_area_sqm' => 'Property-card plot area',
            'primary_road_width_m'   => 'Primary access road width',
        ];
        foreach ($req as $k => $label) {
            if (!isset($post[$k]) || trim((string)$post[$k]) === '') {
                $errs[] = $label;
            }
        }
        if (!empty($post['property_card_area_sqm']) && (float)$post['property_card_area_sqm'] <= 0) {
            $errs[] = 'Property-card area must be > 0';
        }
        if (!empty($post['primary_road_width_m']) && (float)$post['primary_road_width_m'] <= 0) {
            $errs[] = 'Road width must be > 0';
        }
        // If "existing construction = yes", require at least one occupancy count.
        if (!empty($post['has_existing_construction'])) {
            $res = (float)($post['approx_residential_units'] ?? 0);
            $com = (float)($post['approx_commercial_units']  ?? 0);
            $ten = (float)($post['approx_tenant_count']      ?? 0);
            if ($res <= 0 && $com <= 0 && $ten <= 0) {
                $errs[] = 'If existing construction present, enter at least one of: residential units / commercial units / tenant count';
            }
        }
        return $errs;
    }
}
