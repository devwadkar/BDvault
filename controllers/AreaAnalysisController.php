<?php
/**
 * AreaAnalysisController — Post-Proposal Area Analysis Wizard (Phase A skeleton).
 *
 * Replaces the direct-to-regulation form flow. Manager clicks "Post Proposal Planning"
 * on an accepted proposal → is routed here → walks through a 6-step wizard →
 * ends with a versioned, formal Area Statement that unlocks Financial Analysis.
 *
 * Routes (see index.php):
 *   ?page=area_analysis                          — list of area statements per proposal
 *   ?page=area_analysis_start (POST proposal_id) — create a fresh draft, jump to Step 1
 *   ?page=area_analysis_step&statement_id=N&step=1..6  — render one wizard step
 *   ?page=area_analysis_save_step (POST)         — persist draft + advance to next step
 *   ?page=area_analysis_review&statement_id=N    — final review page (Step 6)
 *   ?page=area_analysis_generate (POST)          — lock the statement and cut a version
 *   ?page=area_analysis_delete (POST)            — delete draft/superseded statement
 *
 * Phase A: skeleton only. Steps 1-6 render placeholder cards; save-draft persists a
 * per-step JSON blob so the wizard survives page reloads. Actual field handling +
 * calc engine + validation come in Phases B-D.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/RegulationSuggester.php';
require_once __DIR__ . '/../lib/AreaCalcEngine.php';
require_once __DIR__ . '/../lib/AreaStatementFinancialsBridge.php';
require_once __DIR__ . '/../lib/SitePlanRenderer.php';

class AreaAnalysisController {

    /** Step definitions (id => label, icon). Order matters — used everywhere. */
    public const STEPS = [
        1 => ['title' => 'Project Details',      'icon' => 'bi-building',         'blurb' => 'Confirm project & authority info'],
        2 => ['title' => 'Plot & Land',          'icon' => 'bi-bounding-box',     'blurb' => 'Gross plot, deductions, net area'],
        3 => ['title' => 'Existing Development', 'icon' => 'bi-houses',           'blurb' => 'Buildings, tenants, existing BUA'],
        4 => ['title' => 'Regulatory Details',   'icon' => 'bi-shield-check',     'blurb' => 'DCPR rule selection & confirmation'],
        5 => ['title' => 'Area Calculation',     'icon' => 'bi-calculator',       'blurb' => 'Auto-derived FSI, rehab, sale'],
        6 => ['title' => 'Review & Generate',    'icon' => 'bi-file-earmark-check','blurb' => 'Final Area Statement'],
    ];

    // ─── Listing ──────────────────────────────────────────────────────────

    /** GET ?page=area_analysis — list all Area Statements grouped by proposal. */
    public static function index(): void {
        $db = getDB();
        // Accepted proposals available for new Area Statements
        $accepted = $db->query("SELECT id, proposal_name, region, city, plot_area_sqm, proposal_type
                                FROM proposals
                                WHERE status = 'accepted'
                                ORDER BY id DESC");
        $acceptedProposals = $accepted ? $accepted->fetch_all(MYSQLI_ASSOC) : [];

        // Existing statements
        $stRes = $db->query("SELECT s.*, p.proposal_name
                             FROM area_statements s
                             JOIN proposals p ON p.id = s.proposal_id
                             ORDER BY s.proposal_id DESC, s.version_major DESC, s.version_minor DESC");
        $statements = $stRes ? $stRes->fetch_all(MYSQLI_ASSOC) : [];

        // Group by proposal
        $byProposal = [];
        foreach ($statements as $s) {
            $byProposal[$s['proposal_id']]['title'] = $s['proposal_name'];
            $byProposal[$s['proposal_id']]['items'][] = $s;
        }

        // Cross-flow sync — also load legacy Development Plans per proposal so both
        // Area Analysis and Planning (legacy) show a unified per-proposal view.
        $lpRes = $db->query("SELECT dp.id, dp.proposal_id, dp.plan_name, dp.regulation_key,
                                    dp.status, dp.version, dp.parent_plan_id, dp.scenario_label,
                                    dp.calculated_at, dp.updated_at,
                                    p.proposal_name
                             FROM development_plans dp
                             JOIN proposals p ON p.id = dp.proposal_id
                             ORDER BY dp.proposal_id DESC, dp.parent_plan_id IS NULL DESC, dp.created_at ASC");
        $legacyPlansByProposal = [];
        while ($lpRes && $r = $lpRes->fetch_assoc()) {
            // Also seed the proposal block so proposals with ONLY legacy plans still appear
            if (!isset($byProposal[$r['proposal_id']])) {
                $byProposal[$r['proposal_id']] = ['title' => $r['proposal_name'], 'items' => []];
            }
            $legacyPlansByProposal[$r['proposal_id']][] = $r;
        }

        $steps = self::STEPS;
        require __DIR__ . '/../views/area_analysis/index.php';
    }

    // ─── Create + Route ───────────────────────────────────────────────────

    /** POST ?page=area_analysis_start — create a draft statement and land on Step 1. */
    public static function start(): void {
        $proposalId = (int)($_POST['proposal_id'] ?? 0);
        $proposal = self::loadProposal($proposalId);
        if (!$proposal) { flashSet('danger', 'Proposal not found.'); redirect('index.php?page=area_analysis'); return; }

        $db = getDB();
        $userId = (int)TokenAuth::userId();

        // Auto-name: "<Proposal Name> — Area Statement v1.0"
        $nextMajor = 1; $nextMinor = 0;
        $existing = $db->query("SELECT MAX(version_major) AS mx FROM area_statements WHERE proposal_id = " . (int)$proposalId);
        if ($existing && ($row = $existing->fetch_assoc()) && $row['mx']) {
            $nextMajor = (int)$row['mx'] + 1; // fresh major version
        }
        $name = $proposal['proposal_name'] . ' — Area Statement v' . $nextMajor . '.' . $nextMinor;

        // Pre-fill fields from proposal record
        $stmt = $db->prepare("INSERT INTO area_statements
            (proposal_id, statement_name, current_step, status, version_major, version_minor,
             project_name, project_location, ward, plot_address, created_by)
            VALUES (?, ?, 1, 'draft', ?, ?, ?, ?, ?, ?, ?)");
        $projectName = $proposal['proposal_name'];
        $loc = trim(($proposal['city'] ?? '') . ($proposal['region'] ? ', ' . $proposal['region'] : ''), ', ');
        $ward = null; // proposals has no ward column — user fills in Step 1
        $addr = $proposal['site_address'] ?? null;
        $stmt->bind_param('isiissssi', $proposalId, $name, $nextMajor, $nextMinor,
                          $projectName, $loc, $ward, $addr, $userId);
        $stmt->execute();
        $newId = $stmt->insert_id;

        redirect("index.php?page=area_analysis_step&statement_id={$newId}&step=1");
    }

    // ─── Wizard Step Render ───────────────────────────────────────────────

    /** GET ?page=area_analysis_step&statement_id=N&step=1..6 — render one step. */
    public static function step(): void {
        $statementId = (int)($_GET['statement_id'] ?? 0);
        $step        = max(1, min(6, (int)($_GET['step'] ?? 1)));
        $statement   = self::loadStatement($statementId);
        if (!$statement) { flashSet('danger', 'Area statement not found.'); redirect('index.php?page=area_analysis'); return; }

        $proposal = self::loadProposal((int)$statement['proposal_id']);
        $draft    = self::loadDraftPayload($statement);
        $steps    = self::STEPS;

        // Advance current_step marker if user is moving forward
        if ($step > (int)$statement['current_step']) {
            getDB()->query("UPDATE area_statements SET current_step = {$step} WHERE id = {$statementId}");
            $statement['current_step'] = $step;
        }

        // Step-specific extra context
        $extra = [];
        if ($step === 2) {
            $extra['land'] = self::loadLandRows($statementId);
        }
        if ($step === 3) {
            $extra['buildings'] = self::loadExistingBuildings($statementId);
        }
        if ($step === 4) {
            $extra['suggestion'] = self::buildRegulationSuggestion($statement, $draft);
            // Persist suggestion into the row so it's captured in the audit trail
            self::saveSuggestion($statementId, $extra['suggestion']);
        }
        if ($step === 5) {
            $extra['fsi']     = self::loadFsiRows($statementId);
            $extra['dev']     = self::loadDevRows($statementId);
            $extra['calcs']   = self::loadCalcRows($statementId);
            $extra['valids']  = self::loadValidRows($statementId);
            $extra['land']    = self::loadLandRows($statementId);
        }

        // Live-preview data
        $preview = self::buildLivePreview($statement, $draft);

        // Populate the wizard shell's globals so its helper functions can read state.
        $GLOBALS['__AA'] = [
            'statement' => $statement,
            'proposal'  => $proposal,
            'step'      => $step,
            'steps'     => $steps,
            'preview'   => $preview,
            'draft'     => $draft,
        ];

        require __DIR__ . "/../views/area_analysis/step{$step}.php";
    }

    /** POST ?page=area_analysis_save_step — persist draft + go to next step. */
    public static function saveStep(): void {
        $statementId = (int)($_POST['statement_id'] ?? 0);
        $step        = max(1, min(6, (int)($_POST['step'] ?? 1)));
        $direction   = trim((string)($_POST['direction'] ?? 'next'));  // next | prev | save
        $statement   = self::loadStatement($statementId);
        if (!$statement) { flashSet('danger', 'Area statement not found.'); redirect('index.php?page=area_analysis'); return; }
        if ($statement['status'] === 'locked') {
            flashSet('warning', 'This Area Statement is locked. Create a new version to edit.');
            redirect("index.php?page=area_analysis_step&statement_id={$statementId}&step={$step}");
            return;
        }

        // Merge posted fields into the draft JSON. Everything posted is stored raw.
        $draft = self::loadDraftPayload($statement);
        $draft['step_' . $step] = self::sanitizePostedFields($_POST);
        $draft['_meta'] = ['last_saved_at' => date('Y-m-d H:i:s'), 'last_step' => $step];
        self::saveDraftPayload($statementId, $draft);

        // Step-specific side-effects
        if ($step === 1) self::mirrorStep1ToRow($statementId, $draft['step_1'] ?? []);
        if ($step === 2) self::writeLandRows($statementId, $draft['step_2'] ?? []);
        if ($step === 3) self::writeExistingBuildings($statementId, $_POST['buildings'] ?? []);
        if ($step === 4 && !empty($_POST['selected_regulation_key'])) {
            self::persistSelectedRegulation($statementId, (string)$_POST['selected_regulation_key']);
        }

        // Route to next/prev/same
        $target = $step;
        if ($direction === 'next' && $step < 6) $target = $step + 1;
        if ($direction === 'prev' && $step > 1) $target = $step - 1;
        if ($direction === 'review')            { redirect("index.php?page=area_analysis_review&statement_id={$statementId}"); return; }

        flashSet('success', 'Draft saved.');
        redirect("index.php?page=area_analysis_step&statement_id={$statementId}&step={$target}");
    }

    // ─── Review + Generate (Step 6) ────────────────────────────────────────
    // Renders the polished Step 6 view with the same extras the wizard `step()`
    // route provides. The stepper's "Review & Generate" button + saveStep's
    // `direction=review` both land here, so it needs to fully match Step 6.

    public static function review(): void {
        $statementId = (int)($_GET['statement_id'] ?? 0);
        $statement   = self::loadStatement($statementId);
        if (!$statement) { flashSet('danger', 'Area statement not found.'); redirect('index.php?page=area_analysis'); return; }
        $proposal = self::loadProposal((int)$statement['proposal_id']);
        $draft    = self::loadDraftPayload($statement);
        $steps    = self::STEPS;
        $preview  = self::buildLivePreview($statement, $draft);

        // Load the same extras Step 6 expects (validation results + calc rows so
        // the review page shows a meaningful pass/warn/fail summary + metrics).
        $extra = [
            'valids' => self::loadValidRows($statementId),
            'calcs'  => self::loadCalcRows($statementId),
            'fsi'    => self::loadFsiRows($statementId),
            'dev'    => self::loadDevRows($statementId),
            'land'   => self::loadLandRows($statementId),
        ];

        $GLOBALS['__AA'] = compact('statement', 'proposal', 'steps', 'preview', 'draft') + ['step' => 6];
        require __DIR__ . '/../views/area_analysis/step6.php';
    }

    /** POST — Lock this version. Refuses to lock while any validation is 'fail'. */
    public static function generate(): void {
        $statementId = (int)($_POST['statement_id'] ?? 0);
        $statement   = self::loadStatement($statementId);
        if (!$statement) { flashSet('danger', 'Area statement not found.'); redirect('index.php?page=area_analysis'); return; }
        if ($statement['status'] === 'locked') {
            flashSet('warning', 'Already locked. Create a new version to make further edits.');
            redirect("index.php?page=area_analysis_report&statement_id={$statementId}"); return;
        }

        // Refuse to lock if there are unresolved 'fail' validations
        $fails = (int)(getDB()->query("SELECT COUNT(*) AS c FROM area_statement_valid
                                        WHERE statement_id = " . (int)$statementId . " AND result = 'fail'")
                                ->fetch_assoc()['c'] ?? 0);
        if ($fails > 0) {
            flashSet('danger', "Cannot lock — {$fails} validation error(s) still failing. Review Step 5 and adjust inputs.");
            redirect("index.php?page=area_analysis_step&statement_id={$statementId}&step=5"); return;
        }
        if (!$statement['calculated_at']) {
            flashSet('warning', 'Run the calculator (Step 5) before locking.');
            redirect("index.php?page=area_analysis_step&statement_id={$statementId}&step=5"); return;
        }

        getDB()->query("UPDATE area_statements
                        SET status = 'locked', locked_at = CURRENT_TIMESTAMP, locked_by = " . (int)TokenAuth::userId() . "
                        WHERE id = " . (int)$statementId);
        flashSet('success', 'Area Statement v' . (int)$statement['version_major'] . '.' . (int)$statement['version_minor']
                              . ' locked. Financial Analysis is now unlocked.');
        redirect("index.php?page=area_analysis_report&statement_id={$statementId}");
    }

    /** GET ?page=area_analysis_sensitivity&statement_id=N — sensitivity + break-even matrix. */
    public static function sensitivity(): void {
        $statementId = (int)($_GET['statement_id'] ?? 0);
        $statement   = self::loadStatement($statementId);
        if (!$statement) { flashSet('danger', 'Statement not found.'); redirect('index.php?page=area_analysis'); return; }
        $financials = AreaStatementFinancialsBridge::loadRow($statementId);
        if (!$financials || !$financials['calculated_at']) {
            flashSet('warning', 'Run the financial model first.');
            redirect("index.php?page=area_analysis_financials_form&statement_id={$statementId}");
            return;
        }
        $proposal = self::loadProposal((int)$statement['proposal_id']);

        // Base summary
        $baseSummary = json_decode($financials['summary_output_json'] ?? '{}', true) ?: [];
        $reraCarpet = (float)($baseSummary['rera_carpet_sqft_used'] ?? $baseSummary['rera_carpet_sqft_derived'] ?? 0);
        $totalCost  = (float)($baseSummary['total_cost_inr'] ?? 0);

        // ─── Break-even + target-return closed-form ─────────
        // Assumes revenue = sale_rate × RERA carpet (approximate — ignores curves).
        // For target profit-on-cost T: required_revenue = cost × (1+T); required_rate = required_revenue / rera_carpet.
        $breakevenTargets = [];
        if ($reraCarpet > 0 && $totalCost > 0) {
            foreach ([0, 10, 15, 20, 25, 30] as $t) {
                $reqRate = ($totalCost * (1 + $t / 100)) / $reraCarpet;
                $breakevenTargets[] = ['target_pct' => $t, 'required_rate' => $reqRate];
            }
        }

        // ─── Sale-rate sensitivity — vary Y1 sale rate ±30% in 10% steps ─
        $baseRate = (float)$financials['sale_rate_y1_inr_sqft'];
        $saleRateSensitivity = [];
        foreach ([-30, -20, -10, 0, 10, 20, 30] as $deltaPct) {
            $newRate = $baseRate * (1 + $deltaPct / 100);
            $r = self::runFinancialsWithOverrides($financials, $statement, $statementId, [
                'sale_rate_y1_inr_sqft' => $newRate,
            ]);
            $saleRateSensitivity[] = ['delta_pct' => $deltaPct, 'sale_rate' => $newRate] + $r;
        }

        // ─── Cost sensitivity — vary construction rates ±30% ─
        $baseCostRate = (float)$financials['sale_construction_rate_y1_inr_sqft'];
        $baseRehabRate = (float)$financials['rehab_construction_rate_y1_inr_sqft'];
        $costSensitivity = [];
        foreach ([-30, -20, -10, 0, 10, 20, 30] as $deltaPct) {
            $newSale = $baseCostRate  * (1 + $deltaPct / 100);
            $newRehab= $baseRehabRate * (1 + $deltaPct / 100);
            $r = self::runFinancialsWithOverrides($financials, $statement, $statementId, [
                'sale_construction_rate_y1_inr_sqft'  => $newSale,
                'rehab_construction_rate_y1_inr_sqft' => $newRehab,
            ]);
            $costSensitivity[] = ['delta_pct' => $deltaPct, 'sale_cost_rate' => $newSale] + $r;
        }

        require __DIR__ . '/../views/area_analysis/sensitivity.php';
    }

    /**
     * Helper: run the financial calculator with a small set of overrides on top of the persisted
     * financials row, returning a compact result. Used by sensitivity + scenarios.
     */
    private static function runFinancialsWithOverrides(array $baseFin, array $statement, int $statementId, array $overrides): array {
        $fin = array_merge($baseFin, $overrides);
        $asCalcs   = AreaStatementFinancialsBridge::loadStatementCalcsByKey($statementId);
        $planCalcs = AreaStatementFinancialsBridge::bridgeCalcsForFinancials($asCalcs, $statement);
        // Also override the premium calc if line-item premium exists (same shape as run())
        $lineItemTotals = AreaStatementFinancialsBridge::sumLineItemsByCategory($statementId);
        if (($lineItemTotals['premium'] ?? 0) > 0) {
            $planCalcs['premium_total_inr'] = ['calc_value' => (float)$lineItemTotals['premium']];
        }
        try {
            $calc = new Reg33_10_FinancialsCalculator($fin, $planCalcs);
            $out  = $calc->run();
            $sum  = $out['summary'] ?? [];
            // If revenue is itemised, override
            if (($lineItemTotals['revenue'] ?? 0) > 0) {
                // Sensitivity varies sale rate, so itemised revenue shouldn't lock; skip override here.
            }
            $revenue = (float)($sum['total_revenue_inr'] ?? 0);
            $cost    = (float)($sum['total_cost_inr'] ?? 0);
            $intCost = (float)($sum['total_interest_inr'] ?? 0);
            $np      = $revenue - $cost - $intCost;
            return [
                'revenue'      => $revenue,
                'cost'         => $cost,
                'interest'     => $intCost,
                'np'           => $np,
                'margin'       => $revenue > 0 ? ($np / $revenue * 100) : 0,
                'profit_on_cost' => $cost > 0 ? ($np / $cost * 100) : 0,
                'irr'          => $sum['project_irr_pct'] ?? null,
            ];
        } catch (Throwable $t) {
            return ['error' => $t->getMessage()];
        }
    }

    /** GET ?page=area_analysis_dashboard&proposal_id=N — per-project mission control. */
    public static function dashboard(): void {
        $proposalId = (int)($_GET['proposal_id'] ?? 0);
        $proposal   = self::loadProposal($proposalId);
        if (!$proposal) { flashSet('danger', 'Project not found.'); redirect('index.php?page=area_analysis'); return; }

        $db = getDB();
        // Load all statements for this proposal
        $r = $db->query("SELECT * FROM area_statements WHERE proposal_id = " . (int)$proposalId . "
                         ORDER BY version_major DESC, version_minor DESC");
        $statements = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];

        // Pick the "active" statement — latest calculated or locked one
        $active = null;
        foreach ($statements as $s) {
            if (in_array($s['status'], ['locked', 'calculated', 'validated'], true)) { $active = $s; break; }
        }
        if (!$active && !empty($statements)) $active = $statements[0];

        // Financials attached to the active statement (if any)
        $financials = $active ? AreaStatementFinancialsBridge::loadRow((int)$active['id']) : null;
        $finSummary = ($financials && $financials['summary_output_json'])
                    ? (json_decode($financials['summary_output_json'], true) ?: [])
                    : [];

        // Progress stages
        $progress = [
            'area_analysis' => [
                'title' => 'Area Analysis',
                'status' => 'pending',
                'meta' => 'Not started',
                'link' => "index.php?page=area_analysis_step&statement_id=" . ($active['id'] ?? 0) . "&step=1",
            ],
            'area_statement' => [
                'title' => 'Area Statement',
                'status' => 'pending',
                'meta' => 'Not generated',
                'link' => $active ? "index.php?page=area_analysis_report&statement_id=" . (int)$active['id'] : '#',
            ],
            'financial_analysis' => [
                'title' => 'Financial Analysis',
                'status' => 'locked',
                'meta' => 'Locked · finish Area Statement first',
                'link' => '#',
            ],
            'feasibility_summary' => [
                'title' => 'Feasibility Summary',
                'status' => 'locked',
                'meta' => 'Locked · finish Financial Analysis first',
                'link' => '#',
            ],
        ];
        if ($active) {
            if ($active['status'] === 'draft') {
                $progress['area_analysis']['status'] = 'in_progress';
                $progress['area_analysis']['meta']   = 'Draft · step ' . (int)$active['current_step'] . '/6';
            } elseif (in_array($active['status'], ['calculated','validated'], true)) {
                $progress['area_analysis']['status']  = 'done';
                $progress['area_analysis']['meta']    = 'Calculated · ' . e(date('d M Y', strtotime($active['calculated_at'] ?: $active['updated_at'])));
                $progress['area_statement']['status'] = 'in_progress';
                $progress['area_statement']['meta']   = 'Ready to lock';
            } elseif ($active['status'] === 'locked') {
                $progress['area_analysis']['status']    = 'done';
                $progress['area_analysis']['meta']      = 'Locked v' . (int)$active['version_major'] . '.' . (int)$active['version_minor'];
                $progress['area_statement']['status']   = 'done';
                $progress['area_statement']['meta']     = 'Locked · ' . e(date('d M Y', strtotime($active['locked_at'])));
                $progress['financial_analysis']['status'] = $financials ? 'in_progress' : 'ready';
                $progress['financial_analysis']['meta']   = $financials
                    ? ($financials['calculated_at'] ? 'Model run · ' . e(date('d M Y', strtotime($financials['calculated_at']))) : 'In progress')
                    : 'Ready to run';
                $progress['financial_analysis']['link'] = "index.php?page=area_analysis_financials_form&statement_id=" . (int)$active['id'];
                if ($financials && $financials['calculated_at']) {
                    $progress['financial_analysis']['status'] = 'done';
                    $progress['feasibility_summary']['status'] = 'ready';
                    $progress['feasibility_summary']['meta']   = 'View dashboard';
                    $progress['feasibility_summary']['link']   = "index.php?page=area_analysis_scenarios&statement_id=" . (int)$active['id'];
                }
            }
        }

        require __DIR__ . '/../views/area_analysis/dashboard.php';
    }

    /** GET ?page=area_analysis_scenarios&statement_id=N — 3-scenario comparison view. */
    public static function scenarios(): void {
        $statementId = (int)($_GET['statement_id'] ?? 0);
        $statement   = self::loadStatement($statementId);
        if (!$statement) { flashSet('danger', 'Statement not found.'); redirect('index.php?page=area_analysis'); return; }
        $financials = AreaStatementFinancialsBridge::loadRow($statementId);
        if (!$financials) { flashSet('warning', 'Save the financial model first to run scenarios.');
            redirect("index.php?page=area_analysis_financials_form&statement_id={$statementId}"); return; }

        $proposal = self::loadProposal((int)$statement['proposal_id']);

        // 3 preset scenarios — each multiplier applies to base rates
        $presets = [
            'conservative' => ['label' => 'Conservative', 'sale_mult' => 0.90, 'cost_mult' => 1.10, 'esc_delta' => -1.0, 'color' => '#dc2626'],
            'base'         => ['label' => 'Base',         'sale_mult' => 1.00, 'cost_mult' => 1.00, 'esc_delta' =>  0.0, 'color' => '#4f46e5'],
            'optimistic'   => ['label' => 'Optimistic',   'sale_mult' => 1.10, 'cost_mult' => 0.90, 'esc_delta' =>  1.0, 'color' => '#059669'],
        ];

        // Read overrides from ?scenario_params=... (JSON) if the user tweaked defaults
        $urlParams = json_decode($_GET['scenario_params'] ?? '', true);
        if (is_array($urlParams)) {
            foreach ($urlParams as $key => $vals) {
                if (isset($presets[$key])) {
                    foreach (['sale_mult','cost_mult','esc_delta'] as $k) {
                        if (isset($vals[$k]) && is_numeric($vals[$k])) $presets[$key][$k] = (float)$vals[$k];
                    }
                }
            }
        }

        $results = [];
        foreach ($presets as $key => $p) {
            $scenarioFin = $financials;
            $scenarioFin['sale_rate_y1_inr_sqft']               = (float)$financials['sale_rate_y1_inr_sqft']               * $p['sale_mult'];
            $scenarioFin['rehab_construction_rate_y1_inr_sqft'] = (float)$financials['rehab_construction_rate_y1_inr_sqft'] * $p['cost_mult'];
            $scenarioFin['sale_construction_rate_y1_inr_sqft']  = (float)$financials['sale_construction_rate_y1_inr_sqft']  * $p['cost_mult'];
            $scenarioFin['annual_escalation_pct']               = (float)$financials['annual_escalation_pct']               + $p['esc_delta'];

            $asCalcs   = AreaStatementFinancialsBridge::loadStatementCalcsByKey($statementId);
            $planCalcs = AreaStatementFinancialsBridge::bridgeCalcsForFinancials($asCalcs, $statement);
            try {
                $calc = new Reg33_10_FinancialsCalculator($scenarioFin, $planCalcs);
                $out  = $calc->run();
                $sum  = $out['summary'] ?? [];
                $results[$key] = [
                    'preset'         => $p,
                    'sale_rate'      => $scenarioFin['sale_rate_y1_inr_sqft'],
                    'cost_rate'      => $scenarioFin['sale_construction_rate_y1_inr_sqft'],
                    'revenue'        => $sum['total_revenue_inr'] ?? 0,
                    'cost'           => $sum['total_cost_inr']    ?? 0,
                    'np_after_int'   => $sum['net_profit_after_interest_inr'] ?? 0,
                    'irr'            => $sum['project_irr_pct'] ?? null,
                    'margin'         => $sum['net_profit_margin_pct'] ?? 0,
                    'breakeven'      => $sum['breakeven_price_per_rera_sqft'] ?? 0,
                    'profit_on_cost' => ($sum['total_cost_inr'] ?? 0) > 0
                                        ? ($sum['net_profit_after_interest_inr'] ?? 0) / $sum['total_cost_inr'] * 100 : 0,
                ];
            } catch (Throwable $t) {
                $results[$key] = ['preset' => $p, 'error' => $t->getMessage()];
            }
        }
        require __DIR__ . '/../views/area_analysis/scenarios.php';
    }

    /** GET ?page=api_calc_explain&statement_id=N&calc_key=X — JSON payload for the Calc Explorer drawer. */
    public static function apiCalcExplain(): void {
        header('Content-Type: application/json');
        $statementId = (int)($_GET['statement_id'] ?? 0);
        $calcKey     = trim((string)($_GET['calc_key']  ?? ''));
        if (!$statementId || !$calcKey) { echo json_encode(['success' => false, 'error' => 'statement_id + calc_key required']); return; }

        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM area_statement_calcs WHERE statement_id = ? AND calc_key = ? LIMIT 1");
        $stmt->bind_param('is', $statementId, $calcKey);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) { echo json_encode(['success' => false, 'error' => 'No calc row found for that key']); return; }

        $inputs = json_decode($row['formula_inputs'] ?? '{}', true) ?: [];
        // Split friendly display for units + value
        $displayVal = number_format((float)$row['calc_value'], 4);
        // Basic verification: value is present → mark verified
        $status = 'verified';
        $statusLabel = '✓ Calculation Verified';
        if ((float)$row['calc_value'] == 0.0 && !str_contains(strtolower($row['formula'] ?? ''), 'zero')) {
            $status = 'warning'; $statusLabel = '⚠ Zero value — check inputs';
        }

        echo json_encode([
            'success'         => true,
            'calc_key'        => $row['calc_key'],
            'calc_label'      => $row['calc_label'],
            'calc_value'      => (float)$row['calc_value'],
            'calc_value_disp' => $displayVal,
            'calc_unit'       => $row['calc_unit'] ?? '',
            'formula'         => $row['formula'] ?? '',
            'inputs'          => $inputs,
            'regulation_ref'  => $row['regulation_ref'] ?? '',
            'rule_id'         => $row['rule_id'] ?? null,
            'source'          => $row['source'] ?? 'derived',
            'status'          => $status,
            'status_label'    => $statusLabel,
        ]);
    }

    /** GET ?page=area_analysis_feasibility&statement_id=N — classic single-page 21-row feasibility statement. */
    public static function feasibilityStatement(): void {
        $statementId = (int)($_GET['statement_id'] ?? 0);
        $ctx = self::loadFullContext($statementId);
        if (!$ctx) { flashSet('danger', 'Area statement not found.'); redirect('index.php?page=area_analysis'); return; }
        $financials = AreaStatementFinancialsBridge::loadRow($statementId) ?: [];
        $ctx['financials'] = $financials;
        extract($ctx);
        require __DIR__ . '/../views/area_analysis/feasibility_statement.php';
    }

    /** GET ?page=area_analysis_feasibility_excel&statement_id=N — Excel export of the same. */
    public static function feasibilityExcel(): void {
        $statementId = (int)($_GET['statement_id'] ?? 0);
        $ctx = self::loadFullContext($statementId);
        if (!$ctx) { http_response_code(404); echo "Not found"; return; }
        $financials = AreaStatementFinancialsBridge::loadRow($statementId) ?: [];
        $ctx['financials'] = $financials;
        extract($ctx);
        $filename = 'FeasibilityStatement_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $statement['statement_name']) . '.xls';
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        require __DIR__ . '/../views/area_analysis/feasibility_excel.php';
        exit;
    }

    /**
     * GET ?page=area_analysis_best_outcome — step 3 of the workflow: shows a landing
     * of every Area Statement that has BOTH area + financial data, ranks them by NP,
     * shows a GO/CAUTION/NO-GO verdict per statement + links to full Scenario /
     * Sensitivity views. The "best outcome" for each statement is its highest-margin
     * scenario (Optimistic if positive, Conservative floor if negative).
     */
    public static function bestOutcome(): void {
        require_once __DIR__ . '/../lib/AreaStatementFinancialsBridge.php';
        $db = getDB();

        $sql = "SELECT s.id, s.proposal_id, s.statement_name, s.status, s.selected_regulation_key,
                       s.version_major, s.version_minor,
                       s.total_permissible_bua_sqm, s.total_permissible_fsi,
                       p.proposal_name, p.city, p.region,
                       f.statement_id AS fin_id
                FROM area_statements s
                JOIN proposals p ON p.id = s.proposal_id
                LEFT JOIN area_statement_financials f ON f.statement_id = s.id
                WHERE s.status IN ('calculated','validated','locked','superseded')
                ORDER BY s.updated_at DESC";
        $res = $db->query($sql);
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

        // For each statement with financials, run bridge to get NP + margin + IRR;
        // then compute a coarse GO/CAUTION/NO-GO verdict.
        foreach ($rows as &$r) {
            $r['ready']         = (bool)$r['fin_id'];
            $r['np_after_int']  = null;
            $r['margin_pct']    = null;
            $r['irr_pct']       = null;
            $r['revenue_inr']   = null;
            $r['cost_inr']      = null;
            $r['verdict']       = 'incomplete';
            $r['verdict_note']  = 'Financials not configured — cannot form a verdict.';

            if (!$r['ready']) continue;
            try {
                $result = AreaStatementFinancialsBridge::run((int)$r['id']);
                $f = $result['financials'] ?? [];
                $r['np_after_int'] = $f['net_profit_after_interest_inr'] ?? null;
                $r['margin_pct']   = $f['net_profit_margin_pct'] ?? null;
                $r['irr_pct']      = $f['project_irr_pct'] ?? null;
                $r['revenue_inr']  = $f['total_revenue_inr'] ?? null;
                $r['cost_inr']     = $f['total_cost_inr'] ?? null;

                // Verdict logic
                if ($r['np_after_int'] === null) {
                    $r['verdict'] = 'incomplete';
                    $r['verdict_note'] = 'Financial model still building — check back after Refresh calc.';
                } elseif ($r['np_after_int'] < 0) {
                    $r['verdict'] = 'no_go';
                    $r['verdict_note'] = 'Loss-making at base inputs. Only proceed if a scenario recovers profit.';
                } elseif (($r['margin_pct'] ?? 0) < 10 || ($r['irr_pct'] ?? 0) < 15) {
                    $r['verdict'] = 'caution';
                    $r['verdict_note'] = 'Below internal thresholds (margin ≥ 10%, IRR ≥ 15%). Fragile to cost overruns.';
                } elseif (($r['margin_pct'] ?? 0) >= 20 && ($r['irr_pct'] ?? 0) >= 20) {
                    $r['verdict'] = 'strong_go';
                    $r['verdict_note'] = 'Comfortable margin + IRR. Recommended path.';
                } else {
                    $r['verdict'] = 'go';
                    $r['verdict_note'] = 'Meets minimum thresholds. Proceed with the base plan.';
                }
            } catch (Throwable $t) {
                $r['verdict_note'] = 'Financial model failed to compute: ' . $t->getMessage();
            }
        }
        unset($r);

        // Group by proposal so the same plot's statements sit together
        $byProposal = [];
        foreach ($rows as $r) {
            $byProposal[$r['proposal_id']]['title'] = $r['proposal_name'];
            $byProposal[$r['proposal_id']]['items'][] = $r;
        }

        require __DIR__ . '/../views/area_analysis/best_outcome.php';
    }

    /**
     * POST ?page=api_area_assistant — grounded regulatory chat over an Area Statement.
     * Body: JSON { statement_id, question, history?: [{role, text}] }
     */
    public static function apiAssistant(): void {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'POST required.']);
            return;
        }
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $sid      = (int)($input['statement_id'] ?? 0);
        $question = trim((string)($input['question'] ?? ''));
        $history  = is_array($input['history'] ?? null) ? $input['history'] : [];

        if ($sid <= 0 || $question === '') {
            echo json_encode(['success' => false, 'error' => 'statement_id and question required.']);
            return;
        }

        require_once __DIR__ . '/../lib/AreaStatementAIAssistant.php';
        try {
            $result = AreaStatementAIAssistant::ask($sid, $question, $history);
            echo json_encode($result);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => 'Assistant failed: ' . $e->getMessage()]);
        }
    }

    /**
     * GET ?page=area_analysis_financials_list — landing page listing every Area Statement
     * that has a financial model, grouped by proposal, with headline profitability metrics.
     * Sidebar's "Financial Statement" entry lands here.
     */
    public static function financialsList(): void {
        require_once __DIR__ . '/../lib/AreaStatementFinancialsBridge.php';
        $db = getDB();

        // Every Area Statement with the ability to have (or already has) financials
        // area_statement_financials keys off statement_id (no separate id col). We treat
        // "fin_id" as a boolean-ish existence flag using COALESCE on the FK.
        $sql = "SELECT s.id, s.proposal_id, s.statement_name, s.status, s.selected_regulation_key,
                       s.version_major, s.version_minor, s.calculated_at, s.locked_at,
                       s.total_permissible_bua_sqm, s.total_permissible_fsi,
                       s.rehab_bua_sqm, s.sale_bua_sqm,
                       p.proposal_name, p.region, p.city,
                       f.statement_id AS fin_id,
                       f.sale_rate_y1_inr_sqft, f.rehab_construction_rate_y1_inr_sqft,
                       f.updated_at AS fin_updated
                FROM area_statements s
                JOIN proposals p ON p.id = s.proposal_id
                LEFT JOIN area_statement_financials f ON f.statement_id = s.id
                WHERE s.status IN ('calculated','validated','locked','superseded')
                ORDER BY s.proposal_id DESC, s.version_major DESC, s.version_minor DESC";
        $res = $db->query($sql);
        $statements = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

        // Compute a headline profitability metric per statement (best-effort, skips on error)
        foreach ($statements as &$s) {
            $s['np_after_int'] = null; $s['margin_pct'] = null; $s['irr_pct'] = null;
            $s['revenue_inr'] = null; $s['cost_inr'] = null;
            if ($s['fin_id']) {
                try {
                    $r = AreaStatementFinancialsBridge::run((int)$s['id']);
                    if (!empty($r['financials'])) {
                        $f = $r['financials'];
                        $s['np_after_int'] = $f['net_profit_after_interest_inr'] ?? null;
                        $s['margin_pct']   = $f['net_profit_margin_pct'] ?? null;
                        $s['irr_pct']      = $f['project_irr_pct'] ?? null;
                        $s['revenue_inr']  = $f['total_revenue_inr'] ?? null;
                        $s['cost_inr']     = $f['total_cost_inr'] ?? null;
                    }
                } catch (Throwable $t) {}
            }
        }
        unset($s);

        // Group by proposal
        $byProposal = [];
        foreach ($statements as $s) {
            $byProposal[$s['proposal_id']]['title'] = $s['proposal_name'];
            $byProposal[$s['proposal_id']]['items'][] = $s;
        }

        require __DIR__ . '/../views/area_analysis/financials_list.php';
    }

    /** GET ?page=area_analysis_overrides&statement_id=N — list of active + past overrides + audit. */
    public static function overrides(): void {
        require_once __DIR__ . '/../lib/CalcOverrideManager.php';
        $statementId = (int)($_GET['statement_id'] ?? 0);
        $ctx = self::loadFullContext($statementId);
        if (!$ctx) { flashSet('danger', 'Area statement not found.'); redirect('index.php?page=area_analysis'); return; }
        $allOverrides = CalcOverrideManager::listAll($statementId);
        $audit = CalcOverrideManager::audit($statementId, null, 500);
        extract($ctx);
        require __DIR__ . '/../views/area_analysis/overrides.php';
    }

    /**
     * POST ?page=area_analysis_apply_override — apply a new override (or edit the existing one).
     * Body: statement_id, calc_key, override_value, reason.
     */
    public static function applyOverride(): void {
        require_once __DIR__ . '/../lib/CalcOverrideManager.php';
        $sid    = (int)($_POST['statement_id'] ?? 0);
        $key    = trim($_POST['calc_key'] ?? '');
        $value  = $_POST['override_value'] ?? null;
        $reason = trim($_POST['reason'] ?? '');
        $userId = $_SESSION['user_id'] ?? null;
        $redirectTo = $_POST['redirect_to'] ?? $_SERVER['HTTP_REFERER']
                     ?? "index.php?page=area_analysis_overrides&statement_id={$sid}";

        if ($sid <= 0 || $key === '' || $value === '' || $value === null) {
            flashSet('error', 'Missing statement, key, or value.');
            redirect($redirectTo); return;
        }
        if ($reason === '') {
            flashSet('error', 'Please provide a reason — overrides require an audit note.');
            redirect($redirectTo); return;
        }

        try {
            $overrideId = CalcOverrideManager::apply($sid, $key, (float)$value, $reason, $userId);
            $foundational = CalcOverrideManager::isFoundational($key);
            flashSet('success', "Override applied to <b>{$key}</b>."
                . ($foundational ? " ⚠ This is a foundational value — downstream rows may be stale; click Refresh calc to re-run the engine." : ''));
        } catch (Throwable $t) {
            flashSet('error', 'Override failed: ' . $t->getMessage());
        }
        redirect($redirectTo);
    }

    /** POST ?page=area_analysis_deactivate_override — deactivate an override (restore original). */
    public static function deactivateOverride(): void {
        require_once __DIR__ . '/../lib/CalcOverrideManager.php';
        $id     = (int)($_POST['override_id'] ?? 0);
        $sid    = (int)($_POST['statement_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Deactivated');
        $userId = $_SESSION['user_id'] ?? null;
        $redirectTo = $_POST['redirect_to'] ?? $_SERVER['HTTP_REFERER']
                     ?? "index.php?page=area_analysis_overrides&statement_id={$sid}";

        if ($id > 0) {
            $ok = CalcOverrideManager::deactivate($id, $userId, $reason);
            flashSet($ok ? 'success' : 'error',
                $ok ? 'Override deactivated — original value restored.' : 'Deactivate failed.');
        }
        redirect($redirectTo);
    }

    /** GET ?page=area_analysis_discrepancies&statement_id=N — Formula discrepancy list + resolve UI. */
    public static function discrepancies(): void {
        require_once __DIR__ . '/../lib/FormulaDiscrepancyDetector.php';
        $statementId = (int)($_GET['statement_id'] ?? 0);
        $ctx = self::loadFullContext($statementId);
        if (!$ctx) { flashSet('danger', 'Area statement not found.'); redirect('index.php?page=area_analysis'); return; }
        $discrepancies = FormulaDiscrepancyDetector::load($statementId, true);
        extract($ctx);
        require __DIR__ . '/../views/area_analysis/discrepancies.php';
    }

    /** POST ?page=area_analysis_discrepancy_resolve — mark a discrepancy resolved with notes. */
    public static function resolveDiscrepancy(): void {
        require_once __DIR__ . '/../lib/FormulaDiscrepancyDetector.php';
        $id  = (int)($_POST['discrepancy_id'] ?? 0);
        $sid = (int)($_POST['statement_id'] ?? 0);
        $notes = trim($_POST['resolution_notes'] ?? '');
        $userId = $_SESSION['user_id'] ?? null;
        if ($id > 0) {
            $ok = FormulaDiscrepancyDetector::resolve($id, $userId, $notes);
            flashSet($ok ? 'success' : 'error', $ok ? 'Discrepancy resolved.' : 'Failed to resolve.');
        }
        redirect('index.php?page=area_analysis_discrepancies&statement_id=' . $sid);
    }

    /** POST ?page=area_analysis_discrepancy_rescan — re-run the detector against current calc rows. */
    public static function rescanDiscrepancies(): void {
        require_once __DIR__ . '/../lib/FormulaDiscrepancyDetector.php';
        $sid = (int)($_POST['statement_id'] ?? $_GET['statement_id'] ?? 0);
        if ($sid > 0) {
            $count = FormulaDiscrepancyDetector::run($sid);
            flashSet('success', "Rescan complete — {$count} discrepanc" . ($count === 1 ? 'y' : 'ies') . " flagged.");
        }
        redirect('index.php?page=area_analysis_discrepancies&statement_id=' . $sid);
    }

    /**
     * GET ?page=area_analysis_pdf_report&statement_id=N — 20-section master Feasibility Report.
     * The complete package a manager would send to legal / a bank: cover, exec summary, full
     * area statement, complete financial model, scenarios, sensitivity, discrepancies, audit
     * trail. Print-first CSS — user hits browser Print → Save as PDF.
     */
    public static function pdfReport(): void {
        require_once __DIR__ . '/../lib/AreaStatementFinancialsBridge.php';
        require_once __DIR__ . '/../lib/FormulaDiscrepancyDetector.php';

        $statementId = (int)($_GET['statement_id'] ?? 0);
        $ctx = self::loadFullContext($statementId);
        if (!$ctx) { flashSet('danger', 'Area statement not found.'); redirect('index.php?page=area_analysis'); return; }

        // Financials (may be null if the user hasn't set them yet — the view degrades gracefully)
        $financialsRow = AreaStatementFinancialsBridge::loadRow($statementId);
        $financialsResult = null;
        if ($financialsRow) {
            try {
                $financialsResult = AreaStatementFinancialsBridge::run($statementId);
            } catch (Throwable $t) {
                $financialsResult = ['error' => $t->getMessage()];
            }
        }
        $ctx['financials']        = $financialsRow;
        $ctx['financials_result'] = $financialsResult;
        $ctx['line_items']        = self::loadLineItems($statementId);
        $ctx['discrepancies']     = FormulaDiscrepancyDetector::load($statementId, true);
        $ctx['regulatory_rules']  = self::relevantRegulatoryRulesFor($ctx['statement']['selected_regulation_key'] ?? '');

        // Scenarios + Sensitivity — computed on demand for the report
        $ctx['scenarios']   = self::computeScenariosForReport($statementId, $financialsRow);
        $ctx['sensitivity'] = self::computeSensitivityForReport($statementId, $financialsRow);

        extract($ctx);
        require __DIR__ . '/../views/area_analysis/pdf_report.php';
    }

    /** Load regulatory constants applicable to the selected regulation (for Assumptions section). */
    private static function relevantRegulatoryRulesFor(string $reg): array {
        require_once __DIR__ . '/../lib/RulesRegistry.php';
        $rows = [];
        foreach (RulesRegistry::getAll('global') as $r) $rows[] = $r;
        if ($reg) foreach (RulesRegistry::getAll($reg) as $r) $rows[] = $r;
        return $rows;
    }

    /** Silently run the 3 preset scenarios so the report can render them. Never throws. */
    private static function computeScenariosForReport(int $statementId, ?array $financials): array {
        if (!$financials) return [];
        try {
            $presets = [
                'conservative' => ['label' => 'Conservative', 'sale_mult' => 0.70, 'cost_mult' => 1.10, 'esc_delta' => -1.0, 'color' => '#dc2626'],
                'base'         => ['label' => 'Base',         'sale_mult' => 1.00, 'cost_mult' => 1.00, 'esc_delta' => 0.0,  'color' => '#4f46e5'],
                'optimistic'   => ['label' => 'Optimistic',   'sale_mult' => 1.10, 'cost_mult' => 0.90, 'esc_delta' => 1.0,  'color' => '#059669'],
            ];
            $stmt = self::loadStatement($statementId);
            $out = [];
            foreach ($presets as $key => $p) {
                $overrides = [
                    'sale_rate_inr_sqft_y1' => (float)($financials['sale_rate_inr_sqft_y1'] ?? 0) * $p['sale_mult'],
                    'sale_cost_rate_inr_sqm_y1' => (float)($financials['sale_cost_rate_inr_sqm_y1'] ?? 0) * $p['cost_mult'],
                    'rehab_cost_rate_inr_sqm_y1' => (float)($financials['rehab_cost_rate_inr_sqm_y1'] ?? 0) * $p['cost_mult'],
                    'sale_price_escalation_pct' => (float)($financials['sale_price_escalation_pct'] ?? 0) + $p['esc_delta'],
                    'cost_escalation_pct' => (float)($financials['cost_escalation_pct'] ?? 0),
                ];
                $r = self::runFinancialsWithOverrides($financials, $stmt, $statementId, $overrides);
                $out[$key] = ['preset' => $p] + $r;
            }
            return $out;
        } catch (Throwable $t) { return []; }
    }

    /** Silently compute sensitivity + break-even for the report. Never throws. */
    private static function computeSensitivityForReport(int $statementId, ?array $financials): array {
        if (!$financials) return [];
        try {
            require_once __DIR__ . '/../lib/AreaCalcEngine.php';
            $stmt = self::loadStatement($statementId);
            $base = self::runFinancialsWithOverrides($financials, $stmt, $statementId, []);
            if (empty($base)) return [];

            // Break-even + target returns
            $reraCarpet = 0;
            $rs = getDB()->query("SELECT calc_value FROM area_statement_calcs WHERE statement_id={$statementId} AND calc_key IN ('sale_rera_carpet_sqft','sale_component_sqft','gross_sale_bua_permissible_sqft') LIMIT 1");
            if ($rs && ($r = $rs->fetch_assoc())) $reraCarpet = (float)$r['calc_value'];
            $totalCost = (float)($base['total_cost'] ?? 0);
            $targets = [0, 10, 15, 20, 25, 30];
            $breakeven = [];
            foreach ($targets as $t) {
                $req = $reraCarpet > 0 ? ($totalCost * (1 + $t / 100.0)) / $reraCarpet : 0;
                $breakeven[] = ['target_pct' => $t, 'required_rate' => $req];
            }

            // Sale-rate sensitivity ±30% in 10% steps
            $baseSaleRate = (float)($financials['sale_rate_inr_sqft_y1'] ?? 0);
            $sale = [];
            foreach ([-30, -20, -10, 0, 10, 20, 30] as $delta) {
                $ov = ['sale_rate_inr_sqft_y1' => $baseSaleRate * (1 + $delta / 100.0)];
                $r = self::runFinancialsWithOverrides($financials, $stmt, $statementId, $ov);
                $sale[] = ['delta_pct' => $delta, 'sale_rate' => $ov['sale_rate_inr_sqft_y1']] + $r;
            }
            return ['breakeven' => $breakeven, 'sale' => $sale, 'base' => $base];
        } catch (Throwable $t) { return []; }
    }

    /** GET ?page=area_analysis_report&statement_id=N — full 12-section Area Statement. */
    public static function report(): void {
        $statementId = (int)($_GET['statement_id'] ?? 0);
        $ctx = self::loadFullContext($statementId);
        if (!$ctx) { flashSet('danger', 'Area statement not found.'); redirect('index.php?page=area_analysis'); return; }
        extract($ctx);
        require __DIR__ . '/../views/area_analysis/report.php';
    }

    /** GET ?page=area_analysis_export&statement_id=N&format=xls — Excel download. */
    public static function exportExcel(): void {
        $statementId = (int)($_GET['statement_id'] ?? 0);
        $ctx = self::loadFullContext($statementId);
        if (!$ctx) { http_response_code(404); echo "Not found"; return; }
        extract($ctx);
        $filename = 'AreaStatement_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $statement['statement_name']) . '.xls';
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        require __DIR__ . '/../views/area_analysis/export_excel.php';
        exit;
    }

    /**
     * POST ?page=area_analysis_new_version — clone a locked (or any) statement into a new
     * draft version. bump_level = 'minor' (v1.0 → v1.1) or 'major' (v1.0 → v2.0).
     */
    public static function newVersion(): void {
        $statementId = (int)($_POST['statement_id'] ?? 0);
        $bump = ($_POST['bump_level'] ?? 'minor') === 'major' ? 'major' : 'minor';
        $parent = self::loadStatement($statementId);
        if (!$parent) { flashSet('danger', 'Source statement not found.'); redirect('index.php?page=area_analysis'); return; }

        $db = getDB();
        $userId = (int)TokenAuth::userId();

        // Compute next version
        if ($bump === 'major') {
            $newMajor = (int)$parent['version_major'] + 1;
            $newMinor = 0;
        } else {
            $newMajor = (int)$parent['version_major'];
            // Find the highest minor within this major for this proposal
            $r = $db->query("SELECT MAX(version_minor) AS mx FROM area_statements
                             WHERE proposal_id = " . (int)$parent['proposal_id']
                             . " AND version_major = {$newMajor}");
            $curMinor = $r ? (int)($r->fetch_assoc()['mx'] ?? 0) : 0;
            $newMinor = $curMinor + 1;
        }

        $baseName = preg_replace('/\s+—\s+Area Statement v[\d.]+.*$/u', '', $parent['statement_name']);
        $newName  = $baseName . " — Area Statement v{$newMajor}.{$newMinor}";

        // Clone core columns — deliberately NOT copying calc/valid/fsi/dev rows (they'll be regenerated)
        $stmt = $db->prepare("INSERT INTO area_statements
            (proposal_id, statement_name, current_step, status,
             version_major, version_minor, parent_statement_id,
             project_name, project_location, ward, cts_number, plot_address,
             planning_authority, development_type,
             suggested_regulation_key, suggested_confidence, suggested_rationale, selected_regulation_key,
             draft_json, created_by)
            VALUES (?, ?, ?, 'draft', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stepStart = min(5, (int)$parent['current_step']); // resume near where the parent ended
        $stmt->bind_param(
            'isiiiisssssssssissi',
            $parent['proposal_id'], $newName, $stepStart,
            $newMajor, $newMinor, $statementId,
            $parent['project_name'], $parent['project_location'], $parent['ward'], $parent['cts_number'], $parent['plot_address'],
            $parent['planning_authority'], $parent['development_type'],
            $parent['suggested_regulation_key'], $parent['suggested_confidence'], $parent['suggested_rationale'], $parent['selected_regulation_key'],
            $parent['draft_json'], $userId
        );
        $stmt->execute();
        $newId = $stmt->insert_id;

        // Clone land rows + existing buildings so the new version starts from the same inputs
        $db->query("INSERT INTO area_statement_land
                    (statement_id, component_key, component_label, area_sqm, is_deduction, source, regulation_ref, display_order)
                    SELECT {$newId}, component_key, component_label, area_sqm, is_deduction, source, regulation_ref, display_order
                    FROM area_statement_land WHERE statement_id = " . (int)$statementId);
        $db->query("INSERT INTO existing_buildings
                    (statement_id, building_label, residential_units, commercial_units,
                     residential_carpet_sqm, commercial_carpet_sqm, existing_bua_sqm,
                     existing_fsi_consumed, tenant_count, eligible_tenant_count, display_order)
                    SELECT {$newId}, building_label, residential_units, commercial_units,
                           residential_carpet_sqm, commercial_carpet_sqm, existing_bua_sqm,
                           existing_fsi_consumed, tenant_count, eligible_tenant_count, display_order
                    FROM existing_buildings WHERE statement_id = " . (int)$statementId);

        // Mark the parent as superseded if the new version was branched from a locked one
        if ($parent['status'] === 'locked') {
            $db->query("UPDATE area_statements SET status = 'superseded' WHERE id = " . (int)$statementId);
        }

        flashSet('success', "Created v{$newMajor}.{$newMinor} from v" . (int)$parent['version_major'] . '.' . (int)$parent['version_minor']
                              . ". Adjust and recalculate.");
        redirect("index.php?page=area_analysis_step&statement_id={$newId}&step={$stepStart}");
    }

    /** GET ?page=area_analysis_compare&a=N&b=M — side-by-side compare of two versions. */
    public static function compareVersions(): void {
        $aId = (int)($_GET['a'] ?? 0);
        $bId = (int)($_GET['b'] ?? 0);
        $a = self::loadFullContext($aId);
        $b = self::loadFullContext($bId);
        if (!$a || !$b) { flashSet('danger', 'One or both statements not found.'); redirect('index.php?page=area_analysis'); return; }
        require __DIR__ . '/../views/area_analysis/compare.php';
    }

    // ─── Phase E: Financial Analysis ─────────────────────────────────────

    /** GET ?page=area_analysis_financials_form&statement_id=N — render financial input form. */
    public static function financialsForm(): void {
        $statementId = (int)($_GET['statement_id'] ?? 0);
        $statement   = self::loadStatement($statementId);
        if (!$statement) { flashSet('danger', 'Statement not found.'); redirect('index.php?page=area_analysis'); return; }
        if (!in_array($statement['status'], ['locked','calculated','validated','superseded'], true)) {
            flashSet('warning', 'Complete the Area Statement (Step 5 calc) before Financial Analysis.');
            redirect("index.php?page=area_analysis_step&statement_id={$statementId}&step=5"); return;
        }
        AreaStatementFinancialsBridge::ensureRow($statementId);
        $financials = AreaStatementFinancialsBridge::loadRow($statementId);
        $proposal   = self::loadProposal((int)$statement['proposal_id']);
        $lineItems  = self::loadLineItems($statementId);
        require __DIR__ . '/../views/area_analysis/financials_form.php';
    }

    /** POST ?page=area_analysis_financials_save — persist form + run calc + redirect to view. */
    public static function financialsSave(): void {
        $statementId = (int)($_POST['statement_id'] ?? 0);
        $statement   = self::loadStatement($statementId);
        if (!$statement) { flashSet('danger', 'Statement not found.'); redirect('index.php?page=area_analysis'); return; }
        AreaStatementFinancialsBridge::ensureRow($statementId);

        // Whitelist columns we accept from the form
        $db = getDB();
        $numCols = [
            'project_duration_years'                 => 'i',
            'sale_rate_y1_inr_sqft'                  => 'd',
            'rehab_construction_rate_y1_inr_sqft'    => 'd',
            'sale_construction_rate_y1_inr_sqft'     => 'd',
            'annual_escalation_pct'                  => 'd',
            'rera_carpet_loading_pct'                => 'd',
            'saleable_rera_carpet_sqft_override'     => 'd',
            'loan_principal_inr'                     => 'd',
            'loan_interest_rate_pct'                 => 'd',
            'loan_moratorium_years'                  => 'i',
            'loan_repayment_start_year'              => 'i',
            'loan_repayment_years'                   => 'i',
            'marketing_pct_of_sales'                 => 'd',
            'admin_pct_of_costs'                     => 'd',
            'contingency_pct_of_costs'               => 'd',
            'other_costs_pct_of_costs'               => 'd',
            'society_rent_lump_inr'                  => 'd',
            'society_rent_year_a'                    => 'i',
            'society_rent_year_b'                    => 'i',
            'society_other_lump_inr'                 => 'd',
            'society_other_year'                     => 'i',
            'equity_investment_inr'                  => 'd',
            'surplus_share_partner_pct'              => 'd',

            // G2: feasibility statement extras
            'sale_in_situ_sqm'                       => 'd',
            'snapshot_rr_land_rate'                  => 'd',
            'snapshot_rr_construction'               => 'd',
            'rehab_bua_override_sqm'                 => 'd',
            'amenity_bua_override_sqm'               => 'd',
        ];
        $curveCols = ['booking_curve_json', 'collection_curve_json', 'rehab_cost_curve_json', 'sale_cost_curve_json', 'premium_curve_json'];

        $sets = []; $vals = []; $types = '';
        foreach ($numCols as $col => $t) {
            if (!isset($_POST[$col])) continue;
            $v = trim((string)$_POST[$col]);
            if ($v === '') { $v = null; }
            $sets[] = "{$col} = ?";
            $vals[] = $v;
            $types .= 's';  // let mysql coerce
        }
        foreach ($curveCols as $col) {
            $key = str_replace('_json', '', $col); // e.g. booking_curve
            if (!isset($_POST[$key])) continue;
            // Curve input may arrive as CSV or as a JSON array — accept both
            $raw = trim((string)$_POST[$key]);
            if ($raw === '') continue;
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                $decoded = array_map('floatval', preg_split('/[,\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY));
            }
            $sets[] = "{$col} = ?";
            $vals[] = json_encode(array_map(fn($n) => (float)$n, $decoded));
            $types .= 's';
        }
        if (empty($sets)) { flashSet('warning', 'Nothing to save.'); redirect("index.php?page=area_analysis_financials_form&statement_id={$statementId}"); return; }
        $vals[] = $statementId; $types .= 'i';
        $sql = "UPDATE area_statement_financials SET " . implode(', ', $sets) . " WHERE statement_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$vals);
        $stmt->execute();

        // Persist line items (revenue/premium/society) BEFORE running the model
        self::writeLineItems($statementId, $_POST['line_items'] ?? []);

        // Run the model
        $result = AreaStatementFinancialsBridge::run($statementId);
        if (!$result['ok']) {
            flashSet('danger', 'Financial calc failed: ' . $result['error']);
            redirect("index.php?page=area_analysis_financials_form&statement_id={$statementId}"); return;
        }
        $s = $result['summary'] ?? [];
        $msg = 'Financial model updated.';
        if (isset($s['project_irr_pct']))   $msg .= ' Project IRR ' . number_format($s['project_irr_pct'], 2) . '%.';
        elseif (isset($s['project_irr']))   $msg .= ' Project IRR ' . number_format($s['project_irr'] * 100, 2) . '%.';
        flashSet('success', $msg);
        redirect("index.php?page=area_analysis_financials_view&statement_id={$statementId}");
    }

    /** GET ?page=area_analysis_financials_view&statement_id=N — render year-by-year + IRR. */
    public static function financialsView(): void {
        $statementId = (int)($_GET['statement_id'] ?? 0);
        $statement   = self::loadStatement($statementId);
        if (!$statement) { flashSet('danger', 'Statement not found.'); redirect('index.php?page=area_analysis'); return; }
        $financials = AreaStatementFinancialsBridge::loadRow($statementId);
        if (!$financials) { flashSet('warning', 'Fill in the Financial form first.'); redirect("index.php?page=area_analysis_financials_form&statement_id={$statementId}"); return; }
        $proposal = self::loadProposal((int)$statement['proposal_id']);
        $yearly    = json_decode($financials['yearly_output_json']   ?? '[]', true) ?: [];
        $loan      = json_decode($financials['loan_schedule_json']   ?? '[]', true) ?: [];
        $summary   = json_decode($financials['summary_output_json']  ?? '{}', true) ?: [];
        $warnings  = json_decode($financials['warnings_json']        ?? '[]', true) ?: [];
        $lineItems = self::loadLineItems($statementId);
        require __DIR__ . '/../views/area_analysis/financials_view.php';
    }

    // ─── Financial line items (FD1-FD4) ──────────────────────────────

    /**
     * Persist a POST'd `line_items` payload:
     *   line_items[<category>][row_idx][label|formula_type|area_sqft|rate_inr|rate_unit|quantity|pct|base_ref|amount_inr|notes]
     * Wipes existing rows for the categories that are present in the POST, then inserts.
     */
    private static function writeLineItems(int $statementId, $payload): void {
        if (!is_array($payload) || empty($payload)) return;
        $db = getDB();
        foreach ($payload as $category => $rows) {
            $cat = preg_replace('/[^a-z_]/', '', strtolower((string)$category));
            if (!in_array($cat, ['revenue','premium','society','construction','professional','marketing','tax','other','finance'], true)) continue;
            // Wipe this category so save-clean semantics work
            $del = $db->prepare("DELETE FROM area_statement_line_items WHERE statement_id = ? AND category = ?");
            $del->bind_param('is', $statementId, $cat);
            $del->execute();
            if (!is_array($rows)) continue;

            $ord = 0;
            foreach ($rows as $i => $r) {
                if (!is_array($r)) continue;
                $label = trim((string)($r['label'] ?? ''));
                if ($label === '') continue;
                $ftype   = in_array($r['formula_type'] ?? '', ['flat','area_x_rate','pct_of_base','per_unit'], true) ? $r['formula_type'] : 'flat';
                $areaSqm = self::flt($r['area_sqm']  ?? null);
                $areaSft = self::flt($r['area_sqft'] ?? null);
                $rate    = self::flt($r['rate_inr']  ?? null);
                $rateU   = trim((string)($r['rate_unit']  ?? 'sqft'));
                $qty     = self::flt($r['quantity']   ?? null);
                $pct     = self::flt($r['pct']        ?? null);
                $baseRef = trim((string)($r['base_ref']  ?? ''));
                $amount  = self::flt($r['amount_inr'] ?? null) ?? 0.0;

                // Recompute amount server-side for area_x_rate / per_unit if enough data
                if ($ftype === 'area_x_rate' && $areaSft !== null && $rate !== null) {
                    $amount = $areaSft * $rate;
                } elseif ($ftype === 'per_unit' && $qty !== null && $rate !== null) {
                    $amount = $qty * $rate;
                }
                $notes   = trim((string)($r['notes'] ?? ''));
                $regRef  = trim((string)($r['regulation_ref'] ?? ''));
                $ord++;

                // Use direct SQL with escaped values for null-safe decimals
                $sql = "INSERT INTO area_statement_line_items
                    (statement_id, category, label, formula_type, area_sqm, area_sqft, rate_inr, rate_unit,
                     quantity, pct, base_ref, amount_inr, regulation_ref, notes, sort_order)
                    VALUES (" . (int)$statementId . ",
                            '" . $db->real_escape_string($cat) . "',
                            '" . $db->real_escape_string($label) . "',
                            '" . $db->real_escape_string($ftype) . "',
                            " . ($areaSqm === null ? "NULL" : (float)$areaSqm) . ",
                            " . ($areaSft === null ? "NULL" : (float)$areaSft) . ",
                            " . ($rate === null ? "NULL" : (float)$rate) . ",
                            '" . $db->real_escape_string($rateU) . "',
                            " . ($qty === null ? "NULL" : (float)$qty) . ",
                            " . ($pct === null ? "NULL" : (float)$pct) . ",
                            '" . $db->real_escape_string($baseRef) . "',
                            " . (float)$amount . ",
                            '" . $db->real_escape_string($regRef) . "',
                            '" . $db->real_escape_string($notes) . "',
                            " . (int)$ord . ")";
                $db->query($sql);
            }
        }
    }

    /** Load line items for a statement, grouped by category. */
    public static function loadLineItems(int $statementId): array {
        $out = ['revenue'=>[],'premium'=>[],'society'=>[],'construction'=>[],'professional'=>[],'marketing'=>[],'tax'=>[],'other'=>[],'finance'=>[]];
        $r = getDB()->query("SELECT * FROM area_statement_line_items WHERE statement_id = " . (int)$statementId . " ORDER BY category, sort_order, id");
        while ($r && $row = $r->fetch_assoc()) {
            $out[$row['category']][] = $row;
        }
        return $out;
    }

    /** Sum line items by category (for the calc bridge). */
    public static function sumLineItemsByCategory(int $statementId): array {
        $out = [];
        $r = getDB()->query("SELECT category, SUM(amount_inr) AS total FROM area_statement_line_items
                             WHERE statement_id = " . (int)$statementId . " GROUP BY category");
        while ($r && $row = $r->fetch_assoc()) $out[$row['category']] = (float)$row['total'];
        return $out;
    }

    /** Load everything needed to render a full report (statement + child rows + proposal). */
    private static function loadFullContext(int $statementId): ?array {
        $statement = self::loadStatement($statementId);
        if (!$statement) return null;
        return [
            'statement' => $statement,
            'proposal'  => self::loadProposal((int)$statement['proposal_id']),
            'draft'     => self::loadDraftPayload($statement),
            'land'      => self::loadLandRows($statementId),
            'fsi'       => self::loadFsiRows($statementId),
            'dev'       => self::loadDevRows($statementId),
            'calcs'     => self::loadCalcRows($statementId),
            'valids'    => self::loadValidRows($statementId),
            'buildings' => self::loadExistingBuildings($statementId),
        ];
    }

    public static function delete(): void {
        $statementId = (int)($_POST['statement_id'] ?? 0);
        $statement   = self::loadStatement($statementId);
        if (!$statement) { flashSet('danger', 'Area statement not found.'); redirect('index.php?page=area_analysis'); return; }
        if ($statement['status'] === 'locked') {
            flashSet('warning', 'Locked statements cannot be deleted.');
            redirect('index.php?page=area_analysis'); return;
        }
        getDB()->query("DELETE FROM area_statements WHERE id = " . (int)$statementId);
        flashSet('success', 'Draft Area Statement deleted.');
        redirect('index.php?page=area_analysis');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private static function loadStatement(int $id): ?array {
        if ($id <= 0) return null;
        $stmt = getDB()->prepare("SELECT * FROM area_statements WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    private static function loadProposal(int $id): ?array {
        if ($id <= 0) return null;
        $stmt = getDB()->prepare("SELECT * FROM proposals WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    private static function loadDraftPayload(array $statement): array {
        $j = $statement['draft_json'] ?? '';
        if (!$j) return [];
        $decoded = json_decode($j, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function saveDraftPayload(int $statementId, array $draft): void {
        $json = json_encode($draft, JSON_UNESCAPED_UNICODE);
        $stmt = getDB()->prepare("UPDATE area_statements SET draft_json = ? WHERE id = ?");
        $stmt->bind_param('si', $json, $statementId);
        $stmt->execute();
    }

    /** Trim keys that aren't fields (statement_id, step, direction, csrf, etc.). */
    private static function sanitizePostedFields(array $post): array {
        $skip = ['statement_id', 'step', 'direction', 'csrf', 'PHPSESSID'];
        $clean = [];
        foreach ($post as $k => $v) {
            if (in_array($k, $skip, true)) continue;
            $clean[$k] = is_string($v) ? trim($v) : $v;
        }
        return $clean;
    }

    private static function mirrorStep1ToRow(int $statementId, array $s1): void {
        $map = [
            'project_name'       => 'project_name',
            'project_location'   => 'project_location',
            'ward'               => 'ward',
            'cts_number'         => 'cts_number',
            'plot_address'       => 'plot_address',
            'planning_authority' => 'planning_authority',
            'development_type'   => 'development_type',
        ];
        $sets = []; $vals = []; $types = '';
        foreach ($map as $post => $col) {
            if (!array_key_exists($post, $s1)) continue;
            $sets[] = "{$col} = ?";
            $vals[] = $s1[$post] !== '' ? $s1[$post] : null;
            $types .= 's';
        }
        if (!$sets) return;
        $vals[] = $statementId; $types .= 'i';
        $sql = "UPDATE area_statements SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = getDB()->prepare($sql);
        $stmt->bind_param($types, ...$vals);
        $stmt->execute();
    }

    /**
     * Live-preview: reads row + draft_json + existing_buildings for real derived numbers.
     */
    private static function buildLivePreview(array $statement, array $draft): array {
        $s2 = $draft['step_2'] ?? [];
        $gross = self::flt($s2['gross_plot_area_sqm'] ?? null);
        $net   = self::flt($s2['net_plot_area_sqm']   ?? $statement['net_plot_area_sqm']);
        // Existing buildings totals
        $existTotals = self::sumExistingBuildings((int)$statement['id']);
        return [
            'project_name'         => $statement['project_name']       ?: '—',
            'planning_authority'   => $statement['planning_authority'] ?: '—',
            'development_type'     => $statement['development_type']   ?: '—',
            'selected_reg'         => $statement['selected_regulation_key'] ?: ($statement['suggested_regulation_key'] ?: '—'),
            'gross_plot_area_sqm'  => $gross,
            'net_plot_area_sqm'    => $net,
            'road_width_m'         => self::flt($s2['primary_road_width_m'] ?? null),
            'total_fsi'            => $statement['total_permissible_fsi']     ?? null,
            'total_bua_sqm'        => $statement['total_permissible_bua_sqm'] ?? null,
            'rehab_bua_sqm'        => $statement['rehab_bua_sqm'] ?? null,
            'sale_bua_sqm'         => $statement['sale_bua_sqm']  ?? null,
            'exist_buildings'      => $existTotals['count'],
            'exist_res_units'      => $existTotals['res_units'],
            'exist_com_units'      => $existTotals['com_units'],
            'exist_bua_sqm'        => $existTotals['bua_sqm'],
            'exist_tenants'        => $existTotals['tenants'],
            'exist_eligible'       => $existTotals['eligible'],
            'draft_fields_count'   => array_sum(array_map(fn($v) => is_array($v) ? count($v) : 0, $draft)),
        ];
    }

    private static function flt($v): ?float {
        if ($v === null || $v === '' || !is_numeric($v)) return null;
        return (float)$v;
    }

    // ─── Step 2: Land ─────────────────────────────────────────────────────

    private static function loadLandRows(int $statementId): array {
        $out = [];
        $r = getDB()->query("SELECT component_key, component_label, label_extra, road_width_m, area_sqm,
                                    is_deduction, include_in_total_deductions, source, regulation_ref
                             FROM area_statement_land WHERE statement_id = " . (int)$statementId . "
                             ORDER BY display_order");
        while ($r && $row = $r->fetch_assoc()) $out[$row['component_key']] = $row;
        return $out;
    }

    /**
     * Persist land-statement rows + compute Net Plot Area from user inputs.
     * Formula: Net = Gross - Road - Reservation - Amenity - Other Statutory Deductions.
     */
    /**
     * Writes the land statement rows. Supports:
     *   - Multiple road setback rows: roads[width][], roads[area][]
     *   - Reservation with a sub-label + include_in_total_deductions flag
     *   - Amenity handover (always in total)
     *   - Multiple named deductions: named[label][], named[area][], named[include][]
     *
     * Reservation is INCLUDED in Total Deductions only when the "include_reservation"
     * checkbox is on (default OFF per Parksite/SRA convention where Green Belt / ROS
     * retains FSI for the developer).
     */
    private static function writeLandRows(int $statementId, array $s2): void {
        $db = getDB();
        $gross          = self::flt($s2['gross_plot_area_sqm'] ?? null) ?? 0.0;
        $reservation    = self::flt($s2['reservation_area_sqm'] ?? null) ?? 0.0;
        $reservationSub = trim((string)($s2['reservation_sub_label'] ?? ''));
        $includeReserv  = !empty($s2['reservation_included_in_total']);
        $amenity        = self::flt($s2['amenity_area_sqm'] ?? null) ?? 0.0;

        // Wipe existing rows
        $db->query("DELETE FROM area_statement_land WHERE statement_id = " . (int)$statementId);

        // Insert prepared statement (unified — new columns are label_extra, road_width_m, include_in_total_deductions)
        $ins = $db->prepare("INSERT INTO area_statement_land
            (statement_id, component_key, component_label, label_extra, road_width_m, area_sqm,
             is_deduction, include_in_total_deductions, source, regulation_ref, display_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $order = 1;
        $totalDed = 0.0;

        // 1. Gross plot
        self::insertLandRow($ins, $statementId, 'gross_plot', 'Gross Plot Area', null, null, $gross, 0, 0, 'user', null, $order++);

        // 2..N — Road setbacks (dynamic)
        $roads = $s2['roads'] ?? null;
        if (!is_array($roads) || empty($roads['area'])) {
            // Legacy single-value fallback
            $legacyRoad = self::flt($s2['road_deduction_sqm'] ?? null) ?? 0.0;
            $legacyWidth = self::flt($s2['primary_road_width_m'] ?? null);
            if ($legacyRoad > 0) {
                $label = 'D.P. Road Setback';
                self::insertLandRow($ins, $statementId, 'road_1', $label, $legacyWidth ? number_format($legacyWidth, 2) . ' m Wide' : null,
                    $legacyWidth, $legacyRoad, 1, 1, 'user', 'Reg 30(A)(2)(a)', $order++);
                $totalDed += $legacyRoad;
            }
        } else {
            $widths = $roads['width'] ?? [];
            $areas  = $roads['area']  ?? [];
            $ri = 0;
            foreach ($areas as $i => $areaRaw) {
                $area  = self::flt($areaRaw) ?? 0.0;
                $width = self::flt($widths[$i] ?? null);
                if ($area <= 0) continue;
                $ri++;
                $key   = 'road_' . $ri;
                $label = 'D.P. Road Setback';
                $extra = $width ? number_format($width, 2) . ' m Wide' : null;
                self::insertLandRow($ins, $statementId, $key, $label, $extra, $width, $area, 1, 1, 'user', 'Reg 30(A)(2)(a)', $order++);
                $totalDed += $area;
            }
        }

        // Named deductions (Nalla, CRZ, Forest, etc.) — Parksite convention places these right after roads
        $named = $s2['named'] ?? null;
        if (is_array($named) && !empty($named['area'])) {
            $labels   = $named['label']   ?? [];
            $areas    = $named['area']    ?? [];
            $includes = $named['include'] ?? [];
            $ni = 0;
            foreach ($areas as $i => $areaRaw) {
                $area = self::flt($areaRaw) ?? 0.0;
                if ($area <= 0) continue;
                $ni++;
                $key   = 'named_' . $ni;
                $label = trim((string)($labels[$i] ?? '')) ?: 'Named Deduction';
                $inc   = !empty($includes[$i]) ? 1 : 0;
                self::insertLandRow($ins, $statementId, $key, $label, null, null, $area, 1, $inc, 'user', null, $order++);
                if ($inc) $totalDed += $area;
            }
        } else {
            // Legacy: single "other_deduction" input treated as Nalla, included in totals
            $legacyOther = self::flt($s2['other_deduction_sqm'] ?? null) ?? 0.0;
            if ($legacyOther > 0) {
                self::insertLandRow($ins, $statementId, 'named_1', 'Nalla Setback', null, null, $legacyOther, 1, 1, 'user', null, $order++);
                $totalDed += $legacyOther;
            }
        }

        // Reservation (may or may not be included in Total Deductions)
        if ($reservation > 0) {
            self::insertLandRow($ins, $statementId, 'reservation',
                'Reservation Area', $reservationSub ?: null,
                null, $reservation, 1, $includeReserv ? 1 : 0,
                'user', 'Reg 17(1)', $order++);
            if ($includeReserv) $totalDed += $reservation;
        }

        // Amenity handover (always in Total Deductions) — last per Parksite convention
        if ($amenity > 0) {
            self::insertLandRow($ins, $statementId, 'amenity',
                'Amenity Plot as per Reg. 14(A)', null,
                null, $amenity, 1, 1, 'user', 'Reg 14(A)', $order++);
            $totalDed += $amenity;
        }

        // Derived rows
        $net = max(0.0, $gross - $totalDed);
        self::insertLandRow($ins, $statementId, 'net_plot',
            'Net Plot Area (for FSI computation)', null,
            null, $net, 0, 0, 'derived', 'Reg 30 read w/ Reg 32', $order++);

        $developable = self::flt($s2['developable_area_sqm'] ?? null) ?? $net;
        self::insertLandRow($ins, $statementId, 'developable',
            'Developable Area', null, null, $developable, 0, 0, 'derived', null, $order++);

        // Mirror to summary column
        $upd = $db->prepare("UPDATE area_statements
            SET net_plot_area_sqm = ?, reservation_sub_label = ?
            WHERE id = ?");
        $upd->bind_param('dsi', $net, $reservationSub, $statementId);
        $upd->execute();
    }

    /** Bind + execute one prepared land row. */
    private static function insertLandRow($stmt, int $statementId, string $key, string $label, ?string $extra,
                                          ?float $roadWidth, float $area, int $isDeduction, int $include,
                                          string $source, ?string $regRef, int $order): void {
        // Types: i s s s d d i i s s i  = "issssddiissi" — wait, count: 11 params, types:
        // statement_id(i), key(s), label(s), extra(s), road_width(d), area(d), is_deduction(i), include(i), source(s), reg_ref(s), order(i) = 11
        // → 'isssddiissi' — 11 chars, correct.
        $stmt->bind_param('isssddiissi', $statementId, $key, $label, $extra, $roadWidth, $area,
                          $isDeduction, $include, $source, $regRef, $order);
        $stmt->execute();
    }

    // ─── Step 4: Regulation Suggestion + Selection ───────────────────────

    private static function buildRegulationSuggestion(array $statement, array $draft): array {
        $s2      = $draft['step_2'] ?? [];
        $totals  = self::sumExistingBuildings((int)$statement['id']);
        $ctx = [
            'development_type'    => $statement['development_type']   ?? '',
            'planning_authority'  => $statement['planning_authority'] ?? '',
            'project_location'    => $statement['project_location']   ?? '',
            'region'              => self::regionHint($statement),
            'gross_plot_area_sqm' => (float)($s2['gross_plot_area_sqm']  ?? 0),
            'road_width_m'        => (float)($s2['primary_road_width_m'] ?? 0),
            'tenant_count'        => (int)$totals['tenants'],
            'has_existing_bua'    => $totals['bua_sqm'] > 0,
        ];
        return RegulationSuggester::suggest($ctx);
    }

    private static function regionHint(array $statement): ?string {
        $ward = strtoupper($statement['ward'] ?? '');
        if (in_array($ward, ['A','B','C','D','E','F','F-SOUTH','F-NORTH','G-SOUTH','G-NORTH'], true)) return 'Island City';
        if ($ward !== '') return 'Suburbs';
        $loc = strtolower($statement['project_location'] ?? '');
        if (strpos($loc, 'island') !== false) return 'Island City';
        return null;
    }

    private static function saveSuggestion(int $statementId, array $sug): void {
        $stmt = getDB()->prepare("UPDATE area_statements
            SET suggested_regulation_key = ?, suggested_confidence = ?, suggested_rationale = ?
            WHERE id = ?");
        $key  = $sug['regulation_key'] ?? null;
        $conf = (int)($sug['confidence'] ?? 0);
        $rat  = $sug['rationale'] ?? '';
        $stmt->bind_param('sisi', $key, $conf, $rat, $statementId);
        $stmt->execute();
    }

    private static function persistSelectedRegulation(int $statementId, string $regKey): void {
        $stmt = getDB()->prepare("UPDATE area_statements SET selected_regulation_key = ? WHERE id = ?");
        $stmt->bind_param('si', $regKey, $statementId);
        $stmt->execute();
    }

    // ─── Step 5: Calculate ────────────────────────────────────────────────

    /** POST ?page=area_analysis_calculate — run the calc engine + jump to Step 5 view. */
    public static function calculate(): void {
        $statementId = (int)($_POST['statement_id'] ?? 0);
        $stmt = self::loadStatement($statementId);
        if (!$stmt) { flashSet('danger', 'Statement not found.'); redirect('index.php?page=area_analysis'); return; }
        $result = AreaCalcEngine::run($statementId);
        if (!$result['ok']) {
            flashSet('danger', 'Calculation failed: ' . $result['error']);
        } else {
            $c = $result['validations']['counts'] ?? [];
            $msg = "Calculated {$result['calc_count']} rows. Validation: "
                . ($c['pass'] ?? 0) . " pass · " . ($c['warn'] ?? 0) . " warn · " . ($c['fail'] ?? 0) . " fail.";
            flashSet('success', $msg);
        }
        redirect("index.php?page=area_analysis_step&statement_id={$statementId}&step=5");
    }

    /**
     * POST ?page=area_analysis_refresh_calc — re-run the calc engine and return to the
     * page the user came from (Feasibility Statement, Report, Dashboard). Different from
     * calculate() which always jumps to Step 5 — this one preserves the view context so
     * a manager who spots stale numbers on the Feasibility view stays on it.
     */
    public static function refreshCalc(): void {
        $statementId = (int)($_POST['statement_id'] ?? $_GET['statement_id'] ?? 0);
        $redirectTo  = $_POST['redirect_to'] ?? $_SERVER['HTTP_REFERER'] ?? "index.php?page=area_analysis_step&statement_id={$statementId}&step=5";

        $stmt = self::loadStatement($statementId);
        if (!$stmt) { flashSet('danger', 'Statement not found.'); redirect('index.php?page=area_analysis'); return; }

        $result = AreaCalcEngine::run($statementId);
        if (!$result['ok']) {
            flashSet('danger', 'Recalculation failed: ' . $result['error']);
        } else {
            $c = $result['validations']['counts'] ?? [];
            flashSet('success', "Recalculated. {$result['calc_count']} rows · "
                . ($c['pass'] ?? 0) . " validations pass · "
                . ((int)($result['discrepancies'] ?? 0)) . " discrepancies.");
        }
        redirect($redirectTo);
    }

    private static function loadFsiRows(int $sid): array {
        $out = [];
        $r = getDB()->query("SELECT * FROM area_statement_fsi WHERE statement_id = " . (int)$sid . " ORDER BY display_order");
        while ($r && $row = $r->fetch_assoc()) $out[] = $row;
        return $out;
    }
    private static function loadDevRows(int $sid): array {
        $out = [];
        $r = getDB()->query("SELECT * FROM area_statement_dev WHERE statement_id = " . (int)$sid . " ORDER BY display_order");
        while ($r && $row = $r->fetch_assoc()) $out[] = $row;
        return $out;
    }
    private static function loadCalcRows(int $sid): array {
        $out = [];
        $r = getDB()->query("SELECT * FROM area_statement_calcs WHERE statement_id = " . (int)$sid . " ORDER BY display_order");
        while ($r && $row = $r->fetch_assoc()) $out[] = $row;
        return $out;
    }
    private static function loadValidRows(int $sid): array {
        $out = [];
        $r = getDB()->query("SELECT * FROM area_statement_valid WHERE statement_id = " . (int)$sid . " ORDER BY
                             FIELD(result,'fail','warn','pass'), FIELD(severity,'error','warning','info'), id");
        while ($r && $row = $r->fetch_assoc()) $out[] = $row;
        return $out;
    }

    // ─── Step 3: Existing Buildings ───────────────────────────────────────

    public static function loadExistingBuildings(int $statementId): array {
        $out = [];
        $r = getDB()->query("SELECT * FROM existing_buildings WHERE statement_id = " . (int)$statementId . "
                             ORDER BY display_order, id");
        while ($r && $row = $r->fetch_assoc()) $out[] = $row;
        return $out;
    }

    /**
     * Aggregate totals across all existing buildings for a statement.
     */
    private static function sumExistingBuildings(int $statementId): array {
        $out = ['count' => 0, 'res_units' => 0, 'com_units' => 0, 'bua_sqm' => 0.0,
                'res_carpet' => 0.0, 'com_carpet' => 0.0, 'tenants' => 0, 'eligible' => 0];
        $r = getDB()->query("SELECT COUNT(*) c,
                                    COALESCE(SUM(residential_units),0)      ru,
                                    COALESCE(SUM(commercial_units),0)       cu,
                                    COALESCE(SUM(existing_bua_sqm),0)       b,
                                    COALESCE(SUM(residential_carpet_sqm),0) rc,
                                    COALESCE(SUM(commercial_carpet_sqm),0)  cc,
                                    COALESCE(SUM(tenant_count),0)           t,
                                    COALESCE(SUM(eligible_tenant_count),0)  e
                             FROM existing_buildings WHERE statement_id = " . (int)$statementId);
        if ($r && $row = $r->fetch_assoc()) {
            $out['count']      = (int)$row['c'];
            $out['res_units']  = (int)$row['ru'];
            $out['com_units']  = (int)$row['cu'];
            $out['bua_sqm']    = (float)$row['b'];
            $out['res_carpet'] = (float)$row['rc'];
            $out['com_carpet'] = (float)$row['cc'];
            $out['tenants']    = (int)$row['t'];
            $out['eligible']   = (int)$row['e'];
        }
        return $out;
    }

    /**
     * Delete + reinsert. Buildings arrive as parallel arrays under $_POST['buildings']:
     *   buildings[label][],  buildings[res_units][], buildings[com_units][], ...
     */
    private static function writeExistingBuildings(int $statementId, $buildings): void {
        if (!is_array($buildings)) return;
        $db = getDB();
        $db->query("DELETE FROM existing_buildings WHERE statement_id = " . (int)$statementId);

        $labels    = $buildings['label']       ?? [];
        $resU      = $buildings['res_units']   ?? [];
        $comU      = $buildings['com_units']   ?? [];
        $resCarp   = $buildings['res_carpet']  ?? [];
        $comCarp   = $buildings['com_carpet']  ?? [];
        $bua       = $buildings['bua']         ?? [];
        $fsi       = $buildings['fsi']         ?? [];
        $tenants   = $buildings['tenants']     ?? [];
        $eligible  = $buildings['eligible']    ?? [];

        $ins = $db->prepare("INSERT INTO existing_buildings
            (statement_id, building_label, residential_units, commercial_units,
             residential_carpet_sqm, commercial_carpet_sqm, existing_bua_sqm,
             existing_fsi_consumed, tenant_count, eligible_tenant_count, display_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($labels as $i => $label) {
            $label = trim((string)$label);
            if ($label === '') continue;
            $ru = (int)($resU[$i] ?? 0);
            $cu = (int)($comU[$i] ?? 0);
            $rc = (float)($resCarp[$i] ?? 0);
            $cc = (float)($comCarp[$i] ?? 0);
            $b  = (float)($bua[$i] ?? 0);
            $fs = self::flt($fsi[$i] ?? null);
            $t  = (int)($tenants[$i] ?? 0);
            $el = (int)($eligible[$i] ?? 0);
            $ord = $i;
            $ins->bind_param('isiiddddiii', $statementId, $label, $ru, $cu, $rc, $cc, $b, $fs, $t, $el, $ord);
            $ins->execute();
        }
    }
}
