<?php
/**
 * ApprovalCostsController — the Approval Costs module.
 *
 * Stage 2 scope (per APPROVAL_COSTS_PHASE1.md §9):
 *   • Master library browser + seed from the two intake workbooks
 *   • Per-project register workspace (checklist table + filters)
 *   • Per-approval detail drawer
 *   • Add custom (non-source) requirements to a project register
 *
 * NOT in this stage (deferred):
 *   • Applicability engine logic + rule evaluation (Stage 3)
 *   • Cost calculations against project inputs (Stage 4)
 *   • Timeline + critical tracker + dashboard (Stage 5)
 *   • Financial Analysis push (deferred per user Q3)
 */

require_once __DIR__ . '/../config/database.php';

class ApprovalCostsController {

    // ─── Landing: list statements + their register progress ───────────────

    public static function index(): void {
        $db = getDB();
        $r = $db->query("
            SELECT s.id, s.statement_name, s.selected_regulation_key, s.status,
                   s.version_major, s.version_minor, p.proposal_name,
                   COUNT(pa.id) AS approval_count,
                   SUM(CASE WHEN pa.status='approved'  THEN 1 ELSE 0 END) AS approved_count,
                   SUM(CASE WHEN pa.status IN ('applied','under_review','query_raised') THEN 1 ELSE 0 END) AS in_progress_count
            FROM area_statements s
            JOIN proposals p ON p.id = s.proposal_id
            LEFT JOIN project_approval pa ON pa.statement_id = s.id
            WHERE s.status IN ('calculated','validated','locked')
            GROUP BY s.id
            ORDER BY s.updated_at DESC
        ");
        $statements = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];

        $masterCount = (int)($db->query("SELECT COUNT(*) AS c FROM approval_master WHERE is_active=1")->fetch_assoc()['c'] ?? 0);
        $aliasCount  = (int)($db->query("SELECT COUNT(*) AS c FROM approval_master_alias")->fetch_assoc()['c'] ?? 0);
        $flaggedCount = (int)($db->query("SELECT COUNT(*) AS c FROM approval_master WHERE requires_classification=1")->fetch_assoc()['c'] ?? 0);

        require __DIR__ . '/../views/approval_costs/index.php';
    }

    // ─── Per-statement register (main workspace) ──────────────────────────

    public static function register(): void {
        $statementId = (int)($_GET['statement_id'] ?? 0);
        $statement = self::loadStatement($statementId);
        if (!$statement) {
            flashSet('danger', 'Area Statement not found.');
            redirect('index.php?page=approval_costs'); return;
        }
        $proposal = self::loadProposal((int)$statement['proposal_id']);

        $db = getDB();

        // Filters
        $filterCategory = trim((string)($_GET['category']    ?? ''));
        $filterStatus   = trim((string)($_GET['status']      ?? ''));
        $filterPhase    = trim((string)($_GET['phase']       ?? ''));
        $filterAppl     = trim((string)($_GET['appl']        ?? ''));
        $filterSearch   = trim((string)($_GET['q']           ?? ''));

        // Load registered approvals (JOIN to master for display fields)
        $where = ['pa.statement_id = ' . (int)$statementId];
        if ($filterStatus !== '')   $where[] = "pa.status = '" . $db->real_escape_string($filterStatus) . "'";
        if ($filterPhase !== '')    $where[] = "pa.project_phase = '" . $db->real_escape_string($filterPhase) . "'";
        if ($filterAppl !== '')     $where[] = "pa.applicability = '" . $db->real_escape_string($filterAppl) . "'";
        if ($filterCategory !== '') $where[] = "COALESCE(m.category_key, 'custom') = '" . $db->real_escape_string($filterCategory) . "'";
        if ($filterSearch !== '')   {
            $s = $db->real_escape_string($filterSearch);
            $where[] = "(COALESCE(m.standardized_name, pa.custom_name) LIKE '%{$s}%'
                        OR pa.remarks LIKE '%{$s}%'
                        OR pa.next_action LIKE '%{$s}%')";
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT pa.*, m.standardized_name AS master_name, m.code AS master_code,
                   m.record_type, m.category_key, m.authority_hint AS master_authority,
                   m.requires_classification
            FROM project_approval pa
            LEFT JOIN approval_master m ON m.id = pa.master_id
            {$whereSql}
            ORDER BY FIELD(pa.criticality, 'critical','high','medium','low'),
                     FIELD(pa.project_phase, 'land_acquisition','pre_construction','during_construction','post_construction'),
                     COALESCE(m.standardized_name, pa.custom_name)
        ";
        $rows = $db->query($sql);
        $approvals = $rows ? $rows->fetch_all(MYSQLI_ASSOC) : [];

        // Suggestion pool — any master records not yet added to this register
        $alreadyIds = array_filter(array_map(fn($r) => (int)$r['master_id'], $approvals));
        $alreadyIdsIn = !empty($alreadyIds) ? implode(',', array_map('intval', $alreadyIds)) : '0';
        $sug = $db->query("
            SELECT id, code, standardized_name, record_type, category_key, typical_phase, authority_hint, requires_classification
            FROM approval_master
            WHERE is_active = 1 AND id NOT IN ({$alreadyIdsIn})
            ORDER BY requires_classification ASC, category_key, standardized_name
        ");
        $suggestions = $sug ? $sug->fetch_all(MYSQLI_ASSOC) : [];

        // Dashboard summary (minimal in Stage 2 — rich dashboard arrives in Stage 5)
        $summary = [
            'total'          => count($approvals),
            'mandatory'      => 0,
            'conditional'    => 0,
            'requires_verify'=> 0,
            'not_applicable' => 0,
            'critical'       => 0,
            'approved'       => 0,
            'in_progress'    => 0,
            'blocked'        => 0,
        ];
        foreach ($approvals as $a) {
            if ($a['applicability'] === 'mandatory')            $summary['mandatory']++;
            if ($a['applicability'] === 'conditional')          $summary['conditional']++;
            if ($a['applicability'] === 'requires_verification')$summary['requires_verify']++;
            if ($a['applicability'] === 'not_applicable')       $summary['not_applicable']++;
            if ($a['criticality']   === 'critical')             $summary['critical']++;
            if ($a['status']        === 'approved')             $summary['approved']++;
            if (in_array($a['status'], ['applied','under_review','query_raised'], true)) $summary['in_progress']++;
            if ($a['status']        === 'rejected' || $a['status'] === 'on_hold')        $summary['blocked']++;
        }

        $filters = compact('filterCategory','filterStatus','filterPhase','filterAppl','filterSearch');

        // Stage 4: Per-approval estimated totals (sum of estimated_amount per project_approval)
        require_once __DIR__ . '/../lib/ApprovalCostCalculator.php';
        $costTotalsByApprovalId = [];
        $ct = $db->query("
            SELECT project_approval_id,
                   SUM(CASE WHEN is_refundable=0 THEN COALESCE(estimated_amount, 0) ELSE 0 END) AS non_ref_est,
                   SUM(CASE WHEN is_refundable=1 THEN COALESCE(estimated_amount, 0) ELSE 0 END) AS refund_est,
                   SUM(CASE WHEN estimated_amount IS NULL AND confidence='pending_verification' THEN 1 ELSE 0 END) AS pending,
                   COUNT(*) AS n
            FROM project_approval_cost
            WHERE project_approval_id IN (SELECT id FROM project_approval WHERE statement_id=" . (int)$statementId . ")
            GROUP BY project_approval_id");
        while ($ct && $row = $ct->fetch_assoc()) {
            $costTotalsByApprovalId[(int)$row['project_approval_id']] = $row;
        }

        // Cost rollup (cached — may be null if never computed)
        $rollup = ApprovalCostCalculator::loadRollup($statementId);

        // Project inputs (tree_count etc.)
        $projectInputs = [];
        $pr = $db->query("SELECT input_key, input_value FROM project_approval_input WHERE statement_id=" . (int)$statementId);
        while ($pr && $row = $pr->fetch_assoc()) $projectInputs[$row['input_key']] = $row['input_value'];

        // Stage 5: compact alert counts for the top strip
        $alertSummary = self::computeAlerts($statementId);
        $alertCounts = [
            'critical_not_initiated' => count($alertSummary['critical_not_initiated']),
            'overdue'                => count($alertSummary['overdue']),
            'blocked'                => count($alertSummary['blocked']),
            'followup_due'           => count($alertSummary['followup_due']),
            'expiring'               => count($alertSummary['expiring']),
        ];

        require __DIR__ . '/../views/approval_costs/register.php';
    }

    // ─── Add selected master records to a project register ────────────────

    public static function addToRegister(): void {
        $statementId = (int)($_POST['statement_id'] ?? 0);
        $masterIds   = (array)($_POST['master_ids'] ?? []);
        $statement   = self::loadStatement($statementId);
        if (!$statement) { flashSet('danger', 'Statement not found.'); redirect('index.php?page=approval_costs'); return; }
        if (empty($masterIds)) { flashSet('warning', 'No requirements selected.'); redirect("index.php?page=approval_costs_register&statement_id={$statementId}"); return; }

        $db = getDB();
        // Avoid duplicates: look up what's already there
        $existing = [];
        $existsR = $db->query("SELECT master_id FROM project_approval WHERE statement_id=" . (int)$statementId);
        while ($existsR && $row = $existsR->fetch_assoc()) $existing[(int)$row['master_id']] = true;

        $ins = $db->prepare("
            INSERT INTO project_approval
              (statement_id, master_id, applicability, applicability_rationale,
               project_phase, criticality, status, authority_hint, created_by)
            VALUES (?, ?, 'requires_verification', 'Added from master library — applicability not yet evaluated (Stage 3).',
                    ?, 'medium', 'not_started', ?, ?)
        ");
        $added = 0;
        $userId = (int)TokenAuth::userId();
        foreach ($masterIds as $mid) {
            $mid = (int)$mid;
            if ($mid <= 0 || isset($existing[$mid])) continue;
            // Pull defaults from master
            $m = $db->query("SELECT typical_phase, authority_hint FROM approval_master WHERE id={$mid}");
            if (!$m || !($mrow = $m->fetch_assoc())) continue;
            $phase = $mrow['typical_phase'];
            $authority = $mrow['authority_hint'];
            $ins->bind_param('iissi', $statementId, $mid, $phase, $authority, $userId);
            $ins->execute();
            $added++;
        }
        flashSet('success', "Added {$added} requirement" . ($added !== 1 ? 's' : '') . ' to the register.');
        redirect("index.php?page=approval_costs_register&statement_id={$statementId}");
    }

    // ─── Add a custom (non-source) requirement ────────────────────────────

    public static function addCustom(): void {
        $statementId = (int)($_POST['statement_id'] ?? 0);
        $name        = trim((string)($_POST['custom_name'] ?? ''));
        $phase       = trim((string)($_POST['project_phase'] ?? 'pre_construction'));
        $statement   = self::loadStatement($statementId);
        if (!$statement) { flashSet('danger', 'Statement not found.'); redirect('index.php?page=approval_costs'); return; }
        if ($name === '') { flashSet('warning', 'Give the requirement a name.'); redirect("index.php?page=approval_costs_register&statement_id={$statementId}"); return; }

        $db = getDB();
        $userId = (int)TokenAuth::userId();
        $stmt = $db->prepare("
            INSERT INTO project_approval
              (statement_id, master_id, custom_name, applicability, project_phase,
               criticality, status, created_by)
            VALUES (?, NULL, ?, 'requires_verification', ?, 'medium', 'not_started', ?)
        ");
        $stmt->bind_param('issi', $statementId, $name, $phase, $userId);
        $stmt->execute();
        flashSet('success', "Added custom requirement: {$name}");
        redirect("index.php?page=approval_costs_register&statement_id={$statementId}");
    }

    // ─── Per-approval detail (drawer) ─────────────────────────────────────

    public static function detail(): void {
        $id = (int)($_GET['id'] ?? 0);
        $db = getDB();
        $r = $db->query("
            SELECT pa.*, m.standardized_name AS master_name, m.code AS master_code,
                   m.record_type, m.category_key, m.description AS master_description,
                   m.authority_hint AS master_authority, m.requires_classification,
                   m.classification_notes,
                   s.id AS stmt_id, s.statement_name, s.selected_regulation_key,
                   p.proposal_name
            FROM project_approval pa
            LEFT JOIN approval_master m ON m.id = pa.master_id
            JOIN area_statements s ON s.id = pa.statement_id
            JOIN proposals p ON p.id = s.proposal_id
            WHERE pa.id = " . (int)$id);
        $approval = $r ? $r->fetch_assoc() : null;
        if (!$approval) { flashSet('danger','Requirement not found.'); redirect('index.php?page=approval_costs'); return; }

        // Master-side aliases, costs, durations (if attached to a master)
        $aliases = []; $costs = []; $durations = [];
        if ($approval['master_id']) {
            $mid = (int)$approval['master_id'];
            $ar = $db->query("SELECT * FROM approval_master_alias WHERE master_id={$mid} ORDER BY is_primary DESC, source_workbook, source_row");
            if ($ar) while ($row = $ar->fetch_assoc()) $aliases[] = $row;
            $cr = $db->query("SELECT * FROM approval_master_cost_component WHERE master_id={$mid}");
            if ($cr) while ($row = $cr->fetch_assoc()) $costs[] = $row;
            $dr = $db->query("SELECT * FROM approval_master_duration WHERE master_id={$mid}");
            if ($dr) while ($row = $dr->fetch_assoc()) $durations[] = $row;
        }

        // Stage 4: Per-approval computed cost rows + totals
        $projectCosts = [];
        $pcq = $db->query("SELECT * FROM project_approval_cost WHERE project_approval_id=" . (int)$id);
        if ($pcq) while ($row = $pcq->fetch_assoc()) $projectCosts[] = $row;

        // Stage 5: Timeline + followups
        $timeline  = self::loadTimeline((int)$id);
        $followups = self::loadFollowups((int)$id);
        $masterDurations = [];
        if ($approval['master_id']) {
            $md = $db->query("SELECT * FROM approval_master_duration WHERE master_id=" . (int)$approval['master_id']);
            if ($md) while ($row = $md->fetch_assoc()) $masterDurations[] = $row;
        }

        $costTotals = [
            'non_refundable_est' => 0.0,
            'refundable_est'     => 0.0,
            'actual'             => 0.0,
            'pending_count'      => 0,
        ];
        foreach ($projectCosts as $pc) {
            $est = $pc['estimated_amount'] === null ? null : (float)$pc['estimated_amount'];
            $act = $pc['actual_amount']    === null ? null : (float)$pc['actual_amount'];
            if ($est === null && $pc['confidence'] === 'pending_verification') $costTotals['pending_count']++;
            if ($est !== null) {
                if ((int)$pc['is_refundable'] === 1) $costTotals['refundable_est']     += $est;
                else                                  $costTotals['non_refundable_est'] += $est;
            }
            if ($act !== null) $costTotals['actual'] += $act;
        }

        require __DIR__ . '/../views/approval_costs/detail.php';
    }

    // ─── Update core fields on a project_approval row ─────────────────────

    public static function updateApproval(): void {
        $id          = (int)($_POST['id'] ?? 0);
        $statementId = (int)($_POST['statement_id'] ?? 0);
        $db = getDB();
        $existing = $db->query("SELECT * FROM project_approval WHERE id={$id}");
        $existing = $existing ? $existing->fetch_assoc() : null;
        if (!$existing) { flashSet('danger','Not found.'); redirect('index.php?page=approval_costs'); return; }

        // Field-level updates with history logging
        $patch = [
            'applicability'       => trim((string)($_POST['applicability'] ?? $existing['applicability'])),
            'applicability_rationale' => trim((string)($_POST['applicability_rationale'] ?? $existing['applicability_rationale'])),
            'user_override'       => isset($_POST['user_override']) ? 1 : (int)$existing['user_override'],
            'override_reason'     => trim((string)($_POST['override_reason'] ?? $existing['override_reason'])),
            'project_phase'       => trim((string)($_POST['project_phase'] ?? $existing['project_phase'])),
            'criticality'         => trim((string)($_POST['criticality'] ?? $existing['criticality'])),
            'status'              => trim((string)($_POST['status'] ?? $existing['status'])),
            'authority_hint'      => trim((string)($_POST['authority_hint'] ?? $existing['authority_hint'])),
            'next_action'         => trim((string)($_POST['next_action'] ?? $existing['next_action'])),
            'application_reference'=> trim((string)($_POST['application_reference'] ?? $existing['application_reference'])),
            'remarks'             => trim((string)($_POST['remarks'] ?? $existing['remarks'])),
        ];

        // History log for changed fields
        $histStmt = $db->prepare("
            INSERT INTO project_approval_history (project_approval_id, field_changed, from_value, to_value, changed_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $userId = (int)TokenAuth::userId();
        foreach ($patch as $field => $newVal) {
            $oldVal = (string)$existing[$field];
            if ((string)$newVal !== $oldVal) {
                $histStmt->bind_param('isssi', $id, $field, $oldVal, $newVal, $userId);
                $histStmt->execute();
            }
        }

        // Apply the patch
        $upd = $db->prepare("UPDATE project_approval
            SET applicability=?, applicability_rationale=?, user_override=?, override_reason=?,
                project_phase=?, criticality=?, status=?, authority_hint=?,
                next_action=?, application_reference=?, remarks=?
            WHERE id=?");
        $upd->bind_param('ssissssssssi',
            $patch['applicability'], $patch['applicability_rationale'],
            $patch['user_override'], $patch['override_reason'],
            $patch['project_phase'], $patch['criticality'], $patch['status'],
            $patch['authority_hint'], $patch['next_action'],
            $patch['application_reference'], $patch['remarks'], $id);
        $upd->execute();

        flashSet('success', 'Requirement updated.');
        redirect("index.php?page=approval_costs_detail&id={$id}");
    }

    // ─── Delete from project register ─────────────────────────────────────

    public static function deleteApproval(): void {
        $id          = (int)($_POST['id'] ?? 0);
        $statementId = (int)($_POST['statement_id'] ?? 0);
        $db = getDB();
        $db->query("DELETE FROM project_approval WHERE id={$id}");
        flashSet('success', 'Requirement removed from register.');
        redirect("index.php?page=approval_costs_register&statement_id={$statementId}");
    }

    // ─── Master library browser (admin) ───────────────────────────────────

    public static function masterLibrary(): void {
        $db = getDB();
        $filterType     = trim((string)($_GET['type']     ?? ''));
        $filterCategory = trim((string)($_GET['category'] ?? ''));
        $filterPhase    = trim((string)($_GET['phase']    ?? ''));
        $filterFlagged  = !empty($_GET['flagged']);
        $filterSearch   = trim((string)($_GET['q']        ?? ''));

        $where = ['1=1'];
        if ($filterType !== '')     $where[] = "m.record_type = '" . $db->real_escape_string($filterType) . "'";
        if ($filterCategory !== '') $where[] = "m.category_key = '" . $db->real_escape_string($filterCategory) . "'";
        if ($filterPhase !== '')    $where[] = "m.typical_phase = '" . $db->real_escape_string($filterPhase) . "'";
        if ($filterFlagged)         $where[] = "m.requires_classification = 1";
        if ($filterSearch !== '')   {
            $s = $db->real_escape_string($filterSearch);
            $where[] = "(m.standardized_name LIKE '%{$s}%' OR m.code LIKE '%{$s}%'
                        OR EXISTS (SELECT 1 FROM approval_master_alias a
                                   WHERE a.master_id=m.id AND a.alias LIKE '%{$s}%'))";
        }
        $whereSql = implode(' AND ', $where);

        $r = $db->query("
            SELECT m.*,
                   (SELECT COUNT(*) FROM approval_master_alias WHERE master_id=m.id) AS alias_count,
                   (SELECT COUNT(*) FROM approval_master_cost_component WHERE master_id=m.id) AS cost_count,
                   (SELECT COUNT(*) FROM approval_master_duration WHERE master_id=m.id) AS duration_count
            FROM approval_master m
            WHERE {$whereSql}
            ORDER BY m.requires_classification DESC, m.record_type, m.standardized_name
        ");
        $masters = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];

        $totals = [
            'masters'   => (int)$db->query("SELECT COUNT(*) c FROM approval_master")->fetch_assoc()['c'],
            'aliases'   => (int)$db->query("SELECT COUNT(*) c FROM approval_master_alias")->fetch_assoc()['c'],
            'costs'     => (int)$db->query("SELECT COUNT(*) c FROM approval_master_cost_component")->fetch_assoc()['c'],
            'durations' => (int)$db->query("SELECT COUNT(*) c FROM approval_master_duration")->fetch_assoc()['c'],
            'flagged'   => (int)$db->query("SELECT COUNT(*) c FROM approval_master WHERE requires_classification=1")->fetch_assoc()['c'],
        ];

        $filters = compact('filterType','filterCategory','filterPhase','filterFlagged','filterSearch');
        require __DIR__ . '/../views/approval_costs/master_library.php';
    }

    // ─── Critical Tracker + Timeline (Stage 5) ────────────────────────────

    public static function tracker(): void {
        $statementId = (int)($_GET['statement_id'] ?? 0);
        $statement = self::loadStatement($statementId);
        if (!$statement) { flashSet('danger','Statement not found.'); redirect('index.php?page=approval_costs'); return; }
        $proposal = self::loadProposal((int)$statement['proposal_id']);

        $alerts = self::computeAlerts($statementId);
        $counts = [
            'critical_not_initiated' => count($alerts['critical_not_initiated']),
            'overdue'                => count($alerts['overdue']),
            'blocked'                => count($alerts['blocked']),
            'docs_pending'           => count($alerts['docs_pending']),
            'expiring'               => count($alerts['expiring']),
            'followup_due'           => count($alerts['followup_due']),
            'high_cost_low_conf'     => count($alerts['high_cost_low_conf']),
            'critical_total'         => $alerts['critical_total'],
        ];

        require __DIR__ . '/../views/approval_costs/tracker.php';
    }

    public static function updateTimeline(): void {
        $id = (int)($_POST['id'] ?? 0);
        $pa = self::loadProjectApproval($id);
        if (!$pa) { flashSet('danger','Not found.'); redirect('index.php?page=approval_costs'); return; }

        $db = getDB();
        $fields = [
            'planned_start_at'     => $_POST['planned_start_at']     ?? null,
            'expected_apply_at'    => $_POST['expected_apply_at']    ?? null,
            'actual_submitted_at'  => $_POST['actual_submitted_at']  ?? null,
            'target_decision_at'   => $_POST['target_decision_at']   ?? null,
            'actual_decided_at'    => $_POST['actual_decided_at']    ?? null,
            'followup_next_at'     => $_POST['followup_next_at']     ?? null,
            'expiry_at'            => $_POST['expiry_at']            ?? null,
            'renewal_due_at'       => $_POST['renewal_due_at']       ?? null,
        ];
        foreach ($fields as $k => $v) {
            if ($v === '' || $v === '0000-00-00') $fields[$k] = null;
        }
        $prepDays   = isset($_POST['internal_prep_days'])        && $_POST['internal_prep_days'] !== ''        ? (int)$_POST['internal_prep_days']        : null;
        $procDays   = isset($_POST['authority_processing_days']) && $_POST['authority_processing_days'] !== '' ? (int)$_POST['authority_processing_days'] : null;
        $delayDays  = isset($_POST['delay_days']) && $_POST['delay_days'] !== '' ? (int)$_POST['delay_days'] : null;
        $delayReason = trim((string)($_POST['delay_reason'] ?? ''));
        $notes       = trim((string)($_POST['notes'] ?? ''));

        // Compute expected_elapsed_days if we have prep + proc
        $expectedElapsed = ($prepDays !== null && $procDays !== null) ? ($prepDays + $procDays) : null;
        // Compute actual_elapsed_days if we have both submitted and decided
        $actualElapsed = null;
        if (!empty($fields['actual_submitted_at']) && !empty($fields['actual_decided_at'])) {
            $actualElapsed = (int)((strtotime($fields['actual_decided_at']) - strtotime($fields['actual_submitted_at'])) / 86400);
        }

        // Compute delay_days (actual_decided - target_decision)
        if ($delayDays === null && !empty($fields['target_decision_at']) && !empty($fields['actual_decided_at'])) {
            $diff = (int)((strtotime($fields['actual_decided_at']) - strtotime($fields['target_decision_at'])) / 86400);
            $delayDays = $diff > 0 ? $diff : 0;
        }

        // UPSERT
        $exists = $db->query("SELECT id FROM project_approval_timeline WHERE project_approval_id={$id}");
        if ($exists && $exists->fetch_assoc()) {
            $u = $db->prepare("UPDATE project_approval_timeline
                SET planned_start_at=?, expected_apply_at=?, actual_submitted_at=?,
                    target_decision_at=?, actual_decided_at=?,
                    delay_days=?, delay_reason=?, followup_next_at=?,
                    expiry_at=?, renewal_due_at=?,
                    internal_prep_days=?, authority_processing_days=?,
                    expected_elapsed_days=?, actual_elapsed_days=?, notes=?
                WHERE project_approval_id=?");
            $u->bind_param('sssssissssiiiisi',
                $fields['planned_start_at'], $fields['expected_apply_at'], $fields['actual_submitted_at'],
                $fields['target_decision_at'], $fields['actual_decided_at'],
                $delayDays, $delayReason, $fields['followup_next_at'],
                $fields['expiry_at'], $fields['renewal_due_at'],
                $prepDays, $procDays, $expectedElapsed, $actualElapsed, $notes, $id);
            $u->execute();
        } else {
            $i = $db->prepare("INSERT INTO project_approval_timeline
                (project_approval_id, planned_start_at, expected_apply_at, actual_submitted_at,
                 target_decision_at, actual_decided_at,
                 delay_days, delay_reason, followup_next_at,
                 expiry_at, renewal_due_at,
                 internal_prep_days, authority_processing_days,
                 expected_elapsed_days, actual_elapsed_days, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $i->bind_param('isssssissssiiiis',
                $id,
                $fields['planned_start_at'], $fields['expected_apply_at'], $fields['actual_submitted_at'],
                $fields['target_decision_at'], $fields['actual_decided_at'],
                $delayDays, $delayReason, $fields['followup_next_at'],
                $fields['expiry_at'], $fields['renewal_due_at'],
                $prepDays, $procDays, $expectedElapsed, $actualElapsed, $notes);
            $i->execute();
        }

        flashSet('success', 'Timeline updated.');
        redirect("index.php?page=approval_costs_detail&id={$id}");
    }

    public static function addFollowup(): void {
        $id   = (int)($_POST['project_approval_id'] ?? 0);
        $type = trim((string)($_POST['event_type'] ?? 'note'));
        $sum  = trim((string)($_POST['summary']    ?? ''));
        $det  = trim((string)($_POST['detail']     ?? ''));
        if ($id <= 0 || $sum === '') {
            flashSet('warning','Add a short summary.'); redirect("index.php?page=approval_costs_detail&id={$id}"); return;
        }
        $db = getDB();
        $userId = (int)TokenAuth::userId();
        $s = $db->prepare("INSERT INTO project_approval_followup
            (project_approval_id, event_type, summary, detail, user_id)
            VALUES (?, ?, ?, ?, ?)");
        $s->bind_param('isssi', $id, $type, $sum, $det, $userId);
        $s->execute();
        flashSet('success', 'Follow-up logged.');
        redirect("index.php?page=approval_costs_detail&id={$id}");
    }

    /** Bucketed alerts for the Critical Tracker. */
    private static function computeAlerts(int $statementId): array {
        $db = getDB();
        $today  = date('Y-m-d');
        $plus60 = date('Y-m-d', strtotime('+60 days'));
        $plus7  = date('Y-m-d', strtotime('+7 days'));

        $sql = "
            SELECT pa.*, m.standardized_name AS master_name, m.record_type, m.category_key,
                   t.target_decision_at, t.actual_decided_at, t.actual_submitted_at,
                   t.expiry_at, t.followup_next_at, t.delay_days,
                   t.internal_prep_days, t.authority_processing_days, t.expected_elapsed_days,
                   SUM(COALESCE(c.estimated_amount, 0)) AS cost_total,
                   MIN(c.confidence) AS min_confidence
            FROM project_approval pa
            LEFT JOIN approval_master m    ON m.id = pa.master_id
            LEFT JOIN project_approval_timeline t ON t.project_approval_id = pa.id
            LEFT JOIN project_approval_cost c    ON c.project_approval_id = pa.id
            WHERE pa.statement_id = " . (int)$statementId . "
            GROUP BY pa.id, t.id";
        $rows = $db->query($sql);
        $all = $rows ? $rows->fetch_all(MYSQLI_ASSOC) : [];

        $alerts = [
            'critical_not_initiated' => [],
            'overdue'                => [],
            'blocked'                => [],
            'docs_pending'           => [],
            'expiring'               => [],
            'followup_due'           => [],
            'high_cost_low_conf'     => [],
            'critical_total'         => 0,
        ];

        foreach ($all as $r) {
            $open = !in_array($r['status'], ['approved','not_applicable','rejected','expired'], true);
            $isCritical = ($r['criticality'] === 'critical' || $r['criticality'] === 'high');

            if ($r['criticality'] === 'critical') $alerts['critical_total']++;

            if ($isCritical && in_array($r['status'], ['not_started','docs_pending'], true)) {
                $alerts['critical_not_initiated'][] = $r;
            }
            if ($open && !empty($r['target_decision_at']) && $r['target_decision_at'] < $today && empty($r['actual_decided_at'])) {
                $alerts['overdue'][] = $r;
            }
            if (in_array($r['status'], ['query_raised','on_hold','rejected'], true)) {
                $alerts['blocked'][] = $r;
            }
            if ($r['status'] === 'docs_pending') {
                $alerts['docs_pending'][] = $r;
            }
            if (!empty($r['expiry_at']) && $r['expiry_at'] <= $plus60 && $r['expiry_at'] >= $today) {
                $alerts['expiring'][] = $r;
            }
            if (!empty($r['followup_next_at']) && $r['followup_next_at'] <= $plus7) {
                $alerts['followup_due'][] = $r;
            }
            $costTotal = (float)($r['cost_total'] ?? 0);
            $minConf   = $r['min_confidence'] ?? null;
            if ($costTotal >= 1000000 && !in_array($minConf, ['verified_official','consultant_estimate'], true)) {
                $alerts['high_cost_low_conf'][] = $r;
            }
        }

        return $alerts;
    }

    private static function loadProjectApproval(int $id): ?array {
        if ($id <= 0) return null;
        $db = getDB();
        $r = $db->query("SELECT * FROM project_approval WHERE id={$id}");
        return $r ? ($r->fetch_assoc() ?: null) : null;
    }

    private static function loadTimeline(int $projectApprovalId): ?array {
        $db = getDB();
        $r = $db->query("SELECT * FROM project_approval_timeline WHERE project_approval_id={$projectApprovalId}");
        return $r ? ($r->fetch_assoc() ?: null) : null;
    }

    private static function loadFollowups(int $projectApprovalId, int $limit = 20): array {
        $db = getDB();
        $r = $db->query("SELECT * FROM project_approval_followup
                         WHERE project_approval_id={$projectApprovalId}
                         ORDER BY event_at DESC LIMIT {$limit}");
        return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    }

    // ─── Cost calculation (Stage 4) ───────────────────────────────────────

    public static function calculateCosts(): void {
        require_once __DIR__ . '/../lib/ApprovalCostCalculator.php';
        $statementId = (int)($_POST['statement_id'] ?? 0);
        if ($statementId <= 0) { flashSet('danger','Statement not found.'); redirect('index.php?page=approval_costs'); return; }
        $result = ApprovalCostCalculator::calculateForStatement($statementId);
        flashSet('success',
            "Recomputed {$result['approvals_touched']} requirements · {$result['cost_rows_written']} cost rows written.");
        $redirect = $_POST['redirect_to'] ?? "index.php?page=approval_costs_register&statement_id={$statementId}";
        redirect($redirect);
    }

    public static function setProjectInput(): void {
        $statementId = (int)($_POST['statement_id'] ?? 0);
        $key = trim((string)($_POST['input_key'] ?? ''));
        $raw = $_POST['input_value'] ?? '';
        if ($statementId <= 0 || $key === '') {
            flashSet('danger','Missing statement or input key.'); redirect('index.php?page=approval_costs'); return;
        }
        $value = ($raw === '' ? null : (float)$raw);

        $db = getDB();
        $userId = (int)TokenAuth::userId();
        $s = $db->prepare("INSERT INTO project_approval_input (statement_id, input_key, input_value, updated_by)
                           VALUES (?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE input_value=VALUES(input_value), updated_by=VALUES(updated_by)");
        $s->bind_param('isdi', $statementId, $key, $value, $userId);
        $s->execute();

        // Auto-recalc so the UI reflects the new input immediately
        require_once __DIR__ . '/../lib/ApprovalCostCalculator.php';
        ApprovalCostCalculator::calculateForStatement($statementId);

        flashSet('success', "Project input '{$key}' saved — costs recomputed.");
        redirect("index.php?page=approval_costs_register&statement_id={$statementId}");
    }

    // ─── Applicability engine (Stage 3) ───────────────────────────────────

    public static function applicability(): void {
        require_once __DIR__ . '/../lib/ApprovalApplicabilityEngine.php';
        $statementId = (int)($_GET['statement_id'] ?? 0);
        $statement = self::loadStatement($statementId);
        if (!$statement) {
            flashSet('danger','Statement not found.'); redirect('index.php?page=approval_costs'); return;
        }
        $proposal = self::loadProposal((int)$statement['proposal_id']);

        $eval = ApprovalApplicabilityEngine::evaluate($statementId);
        $context = $eval['context'];
        $results = $eval['results'];
        $buckets = ApprovalApplicabilityEngine::bucket($results);

        // Counts for the summary tiles
        $counts = [
            'mandatory'            => count($buckets['mandatory']),
            'conditional'          => count($buckets['conditional']),
            'requires_verification'=> count($buckets['requires_verification']),
            'not_applicable'       => count($buckets['not_applicable']),
            'changed'              => count(array_filter($results, fn($r) => $r['has_changed'])),
            'to_add_mandatory'     => count(array_filter($buckets['mandatory'], fn($r) => !$r['already_registered'])),
        ];

        require __DIR__ . '/../views/approval_costs/applicability.php';
    }

    /** Bulk-apply engine suggestions to the register. */
    public static function applySuggestions(): void {
        require_once __DIR__ . '/../lib/ApprovalApplicabilityEngine.php';
        $statementId = (int)($_POST['statement_id'] ?? 0);
        $scope       = trim((string)($_POST['scope'] ?? 'mandatory')); // mandatory | mandatory_conditional | all_matched | selected
        $selected    = (array)($_POST['master_ids'] ?? []);
        $syncExisting= !empty($_POST['sync_existing']);

        $statement = self::loadStatement($statementId);
        if (!$statement) { flashSet('danger','Statement not found.'); redirect('index.php?page=approval_costs'); return; }

        $eval = ApprovalApplicabilityEngine::evaluate($statementId);
        $results = $eval['results'];

        $db = getDB();
        $userId = (int)TokenAuth::userId();
        $added = 0; $updated = 0;

        foreach ($results as $mid => $r) {
            $take = false;
            switch ($scope) {
                case 'mandatory':
                    $take = ($r['applicability'] === 'mandatory');
                    break;
                case 'mandatory_conditional':
                    $take = in_array($r['applicability'], ['mandatory','conditional'], true);
                    break;
                case 'all_matched':
                    $take = ($r['applicability'] !== 'not_applicable');
                    break;
                case 'drifted':
                    $take = (bool)$r['has_changed'];
                    break;
                case 'selected':
                    $take = in_array($mid, array_map('intval', $selected), true);
                    break;
            }
            if (!$take) continue;

            if ($r['already_registered']) {
                if ($syncExisting && $r['has_changed']) {
                    $u = $db->prepare("UPDATE project_approval
                        SET applicability=?, applicability_rationale=?
                        WHERE statement_id=? AND master_id=? AND user_override=0");
                    $appl = $r['applicability']; $ratn = $r['rationale'];
                    $u->bind_param('ssii', $appl, $ratn, $statementId, $mid);
                    $u->execute();
                    if ($u->affected_rows > 0) $updated++;
                }
                continue;
            }

            // Insert new
            $m = $r['master'];
            $i = $db->prepare("INSERT INTO project_approval
                (statement_id, master_id, applicability, applicability_rationale,
                 project_phase, criticality, status, authority_hint, created_by)
                VALUES (?, ?, ?, ?, ?, 'medium', 'not_started', NULL, ?)");
            $appl = $r['applicability']; $ratn = $r['rationale'];
            $phase = $m['typical_phase'];
            $i->bind_param('iisssi', $statementId, $mid, $appl, $ratn, $phase, $userId);
            $i->execute();
            $added++;
        }

        $msg = "Added {$added} requirement" . ($added !== 1 ? 's' : '') . '.';
        if ($updated > 0) $msg .= " Synced {$updated} existing non-overridden row" . ($updated !== 1 ? 's' : '') . '.';
        flashSet('success', $msg);
        redirect("index.php?page=approval_costs_applicability&statement_id={$statementId}");
    }

    // ─── Idempotent rule seeder ───────────────────────────────────────────

    public static function seedApplicabilityRules(): void {
        $rules = require __DIR__ . '/../scripts/applicability_rules.php';
        $db = getDB();

        // Map code → master_id
        $map = [];
        $mr = $db->query("SELECT id, code FROM approval_master");
        while ($mr && $row = $mr->fetch_assoc()) $map[$row['code']] = (int)$row['id'];

        $created = 0; $skipped = 0; $missingMaster = 0;

        // Reset existing rules so we don't duplicate
        $db->query("DELETE FROM approval_master_applicability_rule");

        $ins = $db->prepare("INSERT INTO approval_master_applicability_rule
            (master_id, rule_type, condition_json, rationale_template, priority, is_active)
            VALUES (?, ?, ?, ?, ?, 1)");

        foreach ($rules as $r) {
            [$code, $ruleType, $cond, $rationale, $priority] = $r;
            if (!isset($map[$code])) { $missingMaster++; continue; }
            $mid  = $map[$code];
            $json = json_encode($cond, JSON_UNESCAPED_UNICODE);
            $ins->bind_param('isssi', $mid, $ruleType, $json, $rationale, $priority);
            $ins->execute();
            $created++;
        }

        echo "<pre style='font-family:monospace;padding:2rem;line-height:1.7;'>";
        echo "✅ Applicability rules seeded.\n\n";
        echo "  Rules created:              {$created}\n";
        echo "  Masters not found (skipped): {$missingMaster}\n\n";
        echo "<a href='index.php?page=approval_costs'>→ Back to Approval Costs</a>";
        echo "</pre>";
    }

    // ─── Idempotent master seeder (one-time + safe to re-run) ─────────────

    public static function seedMasters(): void {
        $data = require __DIR__ . '/../scripts/approval_master_data.php';
        $db = getDB();

        $batch = date('Ymd-His');
        $createdMasters = 0; $updatedMasters = 0;
        $aliasesWritten = 0; $costsWritten = 0; $durationsWritten = 0;
        $sourceRowsWritten = 0;

        foreach ($data as $m) {
            // UPSERT master by code
            $code  = $m['code'];
            $name  = $m['name'];
            $type  = $m['type'];
            $cat   = $m['category'];
            $phase = $m['phase'];
            $authority = $m['authority']    ?? null;
            $desc  = $m['description']      ?? null;
            $flag  = !empty($m['requires_classification']) ? 1 : 0;
            $cls   = $m['classification_notes'] ?? null;

            $exist = $db->query("SELECT id FROM approval_master WHERE code = '" . $db->real_escape_string($code) . "'");
            if ($exist && $row = $exist->fetch_assoc()) {
                $masterId = (int)$row['id'];
                $u = $db->prepare("UPDATE approval_master SET standardized_name=?, record_type=?, category_key=?,
                    typical_phase=?, description=?, authority_hint=?, requires_classification=?, classification_notes=?
                    WHERE id=?");
                $u->bind_param('ssssssisi', $name, $type, $cat, $phase, $desc, $authority, $flag, $cls, $masterId);
                $u->execute();
                $updatedMasters++;
            } else {
                $i = $db->prepare("INSERT INTO approval_master
                    (code, standardized_name, record_type, category_key, typical_phase,
                     description, authority_hint, requires_classification, classification_notes, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                // 9 placeholders: code(s), name(s), type(s), cat(s), phase(s), desc(s), authority(s), flag(i), cls(s)
                $i->bind_param('sssssssis',
                    $code, $name, $type, $cat, $phase, $desc, $authority, $flag, $cls);
                $i->execute();
                $masterId = (int)$i->insert_id;
                $createdMasters++;
            }

            // Clear and re-write aliases, costs, durations (idempotency)
            $db->query("DELETE FROM approval_master_alias           WHERE master_id={$masterId}");
            $db->query("DELETE FROM approval_master_cost_component  WHERE master_id={$masterId}");
            $db->query("DELETE FROM approval_master_duration        WHERE master_id={$masterId}");

            $insA = $db->prepare("INSERT INTO approval_master_alias
                (master_id, alias, source_workbook, source_sheet, source_row, is_primary)
                VALUES (?, ?, ?, ?, ?, ?)");
            foreach (($m['aliases'] ?? []) as $al) {
                [$wb, $sheet, $row, $alias, $isPrimary] = array_pad($al, 5, 0);
                $isPrimary = (int)$isPrimary;
                $row = (int)$row;
                $insA->bind_param('isssii', $masterId, $alias, $wb, $sheet, $row, $isPrimary);
                $insA->execute();
                $aliasesWritten++;

                // Mirror to approval_source_import (UPSERT by unique source triplet)
                $db->query("INSERT INTO approval_source_import
                    (source_workbook, source_sheet, source_row, original_name,
                     mapped_master_id, mapping_decision, import_batch)
                    VALUES ('" . $db->real_escape_string($wb) . "',
                            '" . $db->real_escape_string($sheet) . "',
                            {$row},
                            '" . $db->real_escape_string($alias) . "',
                            {$masterId},
                            'new',
                            '" . $db->real_escape_string($batch) . "')
                    ON DUPLICATE KEY UPDATE
                        mapped_master_id = VALUES(mapped_master_id),
                        original_name    = VALUES(original_name),
                        mapping_decision = VALUES(mapping_decision)
                ");
                $sourceRowsWritten++;
            }

            $insC = $db->prepare("INSERT INTO approval_master_cost_component
                (master_id, label, method, rate_numeric, rate_range_low, rate_range_high,
                 unit, confidence, source_ref)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach (($m['costs'] ?? []) as $c) {
                [$label, $method, $low, $high, $unit, $confidence, $srcRef] = array_pad($c, 7, null);
                // rate_numeric: if method=fixed, use $low; if range, keep null
                $rateNumeric = ($method === 'fixed' || $method === 'per_sqm_bua' || $method === 'per_sqm_plot'
                                || $method === 'per_sqft_bua' || $method === 'per_sqft_plot' || $method === 'per_tree')
                               ? $low : null;
                $rangeLow  = ($method === 'range') ? $low  : null;
                $rangeHigh = ($method === 'range') ? $high : null;
                $insC->bind_param('issdddsss',
                    $masterId, $label, $method, $rateNumeric, $rangeLow, $rangeHigh,
                    $unit, $confidence, $srcRef);
                $insC->execute();
                $costsWritten++;
            }

            $insD = $db->prepare("INSERT INTO approval_master_duration
                (master_id, label, duration_text, numeric_low, numeric_high, source_ref)
                VALUES (?, 'Source-reported duration', ?, ?, ?, ?)");
            foreach (($m['durations'] ?? []) as $d) {
                [$text, $low, $high, $srcRef] = array_pad($d, 4, null);
                $insD->bind_param('isiis', $masterId, $text, $low, $high, $srcRef);
                $insD->execute();
                $durationsWritten++;
            }
        }

        echo "<pre style='font-family:monospace;padding:2rem;line-height:1.7;'>";
        echo "✅ Approval master seed complete.\n\n";
        echo "  Masters created:      {$createdMasters}\n";
        echo "  Masters updated:      {$updatedMasters}\n";
        echo "  Aliases written:      {$aliasesWritten}\n";
        echo "  Costs written:        {$costsWritten}\n";
        echo "  Durations written:    {$durationsWritten}\n";
        echo "  Source rows mapped:   {$sourceRowsWritten}\n\n";
        echo "<a href='index.php?page=approval_costs_master_library'>→ Browse master library</a>\n";
        echo "</pre>";
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private static function loadStatement(int $id): ?array {
        if ($id <= 0) return null;
        $db = getDB();
        $r = $db->query("SELECT * FROM area_statements WHERE id={$id}");
        return $r ? ($r->fetch_assoc() ?: null) : null;
    }

    private static function loadProposal(int $id): ?array {
        if ($id <= 0) return null;
        $db = getDB();
        $r = $db->query("SELECT * FROM proposals WHERE id={$id}");
        return $r ? ($r->fetch_assoc() ?: null) : null;
    }
}
