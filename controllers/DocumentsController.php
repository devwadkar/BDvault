<?php
/**
 * DocumentsController — Development Agreement Intelligence.
 *
 * Routes:
 *   ?page=documents&proposal_id=N              — list docs + extractions
 *   ?page=documents_upload (POST)              — accept file uploads
 *   ?page=documents_extract&proposal_id=N      — run pdftotext + Gemini extraction
 *   ?page=documents_verify (POST)              — accept/edit/reject a da_extractions row
 *   ?page=documents_download&doc_id=N          — secure file download
 *   ?page=documents_source&extraction_id=N     — modal payload (JSON) showing the source page/quote
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/DocumentStorage.php';
require_once __DIR__ . '/../lib/DocumentTextExtractor.php';
require_once __DIR__ . '/../lib/DAExtractionEngine.php';
require_once __DIR__ . '/../lib/DAExplainerAssistant.php';

class DocumentsController {

    public static function index(): void {
        $proposalId = (int)($_GET['proposal_id'] ?? 0);
        $proposal = self::loadProposal($proposalId);
        if (!$proposal) { flashSet('danger', 'Proposal not found.'); redirect('index.php?page=proposals'); return; }

        $db = getDB();
        $docs = $db->query("SELECT * FROM project_documents WHERE proposal_id = " . (int)$proposalId . " ORDER BY uploaded_at DESC");
        $docs = $docs ? $docs->fetch_all(MYSQLI_ASSOC) : [];

        $exs = $db->query("SELECT * FROM da_extractions WHERE proposal_id = " . (int)$proposalId . "
                           ORDER BY field_category, field_key");
        $exs = $exs ? $exs->fetch_all(MYSQLI_ASSOC) : [];
        // Group by category
        $exsByCat = [];
        foreach ($exs as $r) $exsByCat[$r['field_category']][] = $r;

        // Counts for the header strip
        $counts = ['auto' => 0, 'needs_review' => 0, 'verified' => 0, 'edited' => 0, 'rejected' => 0];
        foreach ($exs as $r) if (isset($counts[$r['status']])) $counts[$r['status']]++;

        $fields = DAExtractionEngine::fields();

        require __DIR__ . '/../views/documents/index.php';
    }

    /** POST — file upload from the upload widget. */
    public static function upload(): void {
        $proposalId = (int)($_POST['proposal_id'] ?? 0);
        $docType    = trim((string)($_POST['doc_type'] ?? 'DA'));
        $proposal   = self::loadProposal($proposalId);
        if (!$proposal) { flashSet('danger', 'Proposal not found.'); redirect('index.php?page=proposals'); return; }

        if (empty($_FILES['files']) || empty($_FILES['files']['name'][0])) {
            flashSet('warning', 'No files selected.');
            redirect("index.php?page=documents&proposal_id={$proposalId}");
            return;
        }

        $db = getDB();
        $uploaded = 0; $failed = [];
        $userId = (int)TokenAuth::userId();

        foreach ($_FILES['files']['name'] as $i => $name) {
            if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) {
                $failed[] = "$name (upload error " . $_FILES['files']['error'][$i] . ")";
                continue;
            }

            $one = [
                'name'     => $name,
                'tmp_name' => $_FILES['files']['tmp_name'][$i],
                'size'     => $_FILES['files']['size'][$i],
                'type'     => $_FILES['files']['type'][$i],
            ];

            // Insert stub row first to get the doc_id, then move file
            $ins = $db->prepare("INSERT INTO project_documents (proposal_id, filename, stored_path, mime_type, doc_type, size_bytes, uploaded_by)
                                 VALUES (?, ?, '', '', ?, 0, ?)");
            $ins->bind_param('issi', $proposalId, $name, $docType, $userId);
            $ins->execute();
            $docId = $ins->insert_id;

            $stored = DocumentStorage::store($proposalId, $one, $docId);
            if (!$stored['ok']) {
                $failed[] = "$name — " . $stored['error'];
                $db->query("DELETE FROM project_documents WHERE id = " . (int)$docId);
                continue;
            }

            $upd = $db->prepare("UPDATE project_documents SET stored_path = ?, mime_type = ?, size_bytes = ? WHERE id = ?");
            $upd->bind_param('ssii', $stored['stored_path'], $stored['mime'], $stored['size'], $docId);
            $upd->execute();

            // Auto-run text extraction on upload
            $ex = DocumentTextExtractor::extract($docId);
            if (!$ex['ok']) {
                // Not a hard failure — user can retry / it may need OCR/DOC handling
                $failed[] = "$name — " . $ex['notes'];
            }
            $uploaded++;
        }

        $msg = "Uploaded {$uploaded} file(s).";
        if (!empty($failed)) $msg .= " Issues: " . implode('; ', $failed);
        flashSet($uploaded > 0 ? 'success' : 'danger', $msg);
        redirect("index.php?page=documents&proposal_id={$proposalId}");
    }

    /** GET — trigger AI extraction for the proposal. */
    public static function extract(): void {
        $proposalId = (int)($_GET['proposal_id'] ?? 0);
        $proposal   = self::loadProposal($proposalId);
        if (!$proposal) { flashSet('danger', 'Proposal not found.'); redirect('index.php?page=proposals'); return; }

        $result = DAExtractionEngine::extractProposal($proposalId);
        if (!$result['ok']) {
            flashSet('danger', 'Extraction failed: ' . ($result['error'] ?? 'unknown'));
        } else {
            flashSet('success', "Extraction complete — {$result['extracted_count']} fields found. Review below.");
        }
        redirect("index.php?page=documents&proposal_id={$proposalId}");
    }

    /** POST — accept / edit / reject a single extraction row. */
    public static function verify(): void {
        header('Content-Type: application/json');
        $id     = (int)($_POST['id'] ?? 0);
        $action = trim((string)($_POST['action'] ?? '')); // accept | edit | reject
        $newVal = $_POST['value'] ?? null;

        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM da_extractions WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) { echo json_encode(['success' => false, 'error' => 'Extraction not found']); return; }

        $userId = (int)TokenAuth::userId();
        switch ($action) {
            case 'accept':
                $upd = $db->prepare("UPDATE da_extractions SET status = 'verified', verified_by = ?, verified_at = CURRENT_TIMESTAMP WHERE id = ?");
                $upd->bind_param('ii', $userId, $id);
                $upd->execute();
                echo json_encode(['success' => true, 'status' => 'verified']);
                return;
            case 'edit':
                if ($newVal === null) { echo json_encode(['success' => false, 'error' => 'value required']); return; }
                $upd = $db->prepare("UPDATE da_extractions
                                     SET status = 'edited', extracted_value = ?, normalized_value = ?, verified_by = ?, verified_at = CURRENT_TIMESTAMP
                                     WHERE id = ?");
                $upd->bind_param('ssii', $newVal, $newVal, $userId, $id);
                $upd->execute();
                echo json_encode(['success' => true, 'status' => 'edited', 'normalized' => $newVal]);
                return;
            case 'reject':
                $upd = $db->prepare("UPDATE da_extractions SET status = 'rejected', verified_by = ?, verified_at = CURRENT_TIMESTAMP WHERE id = ?");
                $upd->bind_param('ii', $userId, $id);
                $upd->execute();
                echo json_encode(['success' => true, 'status' => 'rejected']);
                return;
            default:
                echo json_encode(['success' => false, 'error' => 'unknown action']);
        }
    }

    /** GET — secure file download by proxy (files sit behind htaccess deny). */
    public static function download(): void {
        $docId = (int)($_GET['doc_id'] ?? 0);
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM project_documents WHERE id = ?");
        $stmt->bind_param('i', $docId);
        $stmt->execute();
        $doc = $stmt->get_result()->fetch_assoc();
        if (!$doc) { http_response_code(404); echo "Not found"; return; }

        $abs = DocumentStorage::absolutePath($doc['stored_path']);
        if (!file_exists($abs)) { http_response_code(404); echo "File missing"; return; }

        header('Content-Type: ' . $doc['mime_type']);
        header('Content-Disposition: inline; filename="' . rawurlencode($doc['filename']) . '"');
        header('Content-Length: ' . filesize($abs));
        readfile($abs);
        exit;
    }

    /** GET — JSON payload for the source-view modal (page snippet + confidence + notes). */
    public static function source(): void {
        header('Content-Type: application/json');
        $id = (int)($_GET['extraction_id'] ?? 0);
        $db = getDB();
        $stmt = $db->prepare("SELECT e.*, d.filename, d.id AS document_id, dp.text AS page_text
                              FROM da_extractions e
                              LEFT JOIN project_documents d ON d.id = e.document_id
                              LEFT JOIN document_pages dp ON dp.document_id = e.document_id AND dp.page_no = e.page_no
                              WHERE e.id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) { echo json_encode(['success' => false, 'error' => 'not found']); return; }

        // Highlight the original text in the page text
        $pageText = $row['page_text'] ?? '';
        $highlighted = '';
        if ($pageText && $row['original_text']) {
            $needle = $row['original_text'];
            $pos = mb_stripos($pageText, $needle);
            if ($pos !== false) {
                $ctx = 300;
                $start = max(0, $pos - $ctx);
                $len   = mb_strlen($needle) + $ctx * 2;
                $snippet = mb_substr($pageText, $start, $len);
                $highlighted = str_ireplace($needle, "<<HIT>>{$needle}<</HIT>>", $snippet);
            } else {
                $highlighted = mb_substr($pageText, 0, 800);
            }
        } else {
            $highlighted = $pageText ? mb_substr($pageText, 0, 800) : '(page text unavailable)';
        }

        echo json_encode([
            'success'         => true,
            'field_label'     => $row['field_label'],
            'value'           => $row['extracted_value'],
            'unit'            => $row['unit'],
            'normalized'      => $row['normalized_value'],
            'confidence'      => (int)$row['confidence'],
            'status'          => $row['status'],
            'original_text'   => $row['original_text'],
            'clause_ref'      => $row['clause_ref'],
            'page_no'         => $row['page_no'],
            'document_id'     => $row['document_id'],
            'filename'        => $row['filename'],
            'ai_notes'        => $row['ai_notes'],
            'page_snippet'    => $highlighted,
        ]);
    }

    /**
     * POST ?page=api_documents_assistant — grounded chat about extractions.
     */
    public static function apiAssistant(): void {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success' => false, 'error' => 'POST required.']); return; }
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $proposalId = (int)($input['proposal_id'] ?? 0);
        $question   = trim((string)($input['question'] ?? ''));
        $history    = is_array($input['history'] ?? null) ? $input['history'] : [];
        if ($proposalId <= 0 || $question === '') { echo json_encode(['success' => false, 'error' => 'proposal_id + question required.']); return; }
        try {
            echo json_encode(DAExplainerAssistant::ask($proposalId, $question, $history));
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => 'Assistant failed: ' . $e->getMessage()]);
        }
    }

    /**
     * GET ?page=documents_reconcile&proposal_id=N — DA vs DCPR reconciliation view.
     * Compares DA-stated values against the DCPR calculator's output for the latest
     * calculated plan on this proposal, and detects missing required inputs.
     */
    public static function reconcile(): void {
        $proposalId = (int)($_GET['proposal_id'] ?? 0);
        $proposal = self::loadProposal($proposalId);
        if (!$proposal) { flashSet('danger', 'Proposal not found.'); redirect('index.php?page=proposals'); return; }

        $db = getDB();

        // Load all DA extractions
        $exsRes = $db->query("SELECT * FROM da_extractions WHERE proposal_id = " . (int)$proposalId . " ORDER BY field_key");
        $extractions = $exsRes ? $exsRes->fetch_all(MYSQLI_ASSOC) : [];
        $exByKey = [];
        foreach ($extractions as $r) $exByKey[$r['field_key']] = $r;

        // Pick the latest calculated plan (any regulation) for this proposal, if any
        $planRes = $db->query("SELECT * FROM development_plans
                               WHERE proposal_id = " . (int)$proposalId . "
                                 AND status IN ('calculated','validated','finalized')
                               ORDER BY calculated_at DESC LIMIT 1");
        $plan = $planRes ? $planRes->fetch_assoc() : null;
        $regulationKey = $plan['regulation_key'] ?? '33(10)'; // fallback

        // Load DCPR calculations for the plan (or empty)
        $calcByKey = [];
        if ($plan) {
            $c = $db->query("SELECT * FROM plan_calculations WHERE plan_id = " . (int)$plan['id']);
            while ($c && $row = $c->fetch_assoc()) $calcByKey[$row['calc_key']] = $row;
        }

        // Load the input schema for the regulation to determine required inputs
        $schema = self::loadInputSchemaForReg($regulationKey);
        $requiredKeys = [];
        $optionalKeys = [];
        $labelByKey = [];
        foreach (($schema['groups'] ?? []) as $g) {
            foreach (($g['fields'] ?? []) as $f) {
                $labelByKey[$f['key']] = $f['label'];
                if (!empty($f['required'])) $requiredKeys[] = $f['key'];
                else                        $optionalKeys[] = $f['key'];
            }
        }

        // Missing = required inputs not covered by any usable DA extraction
        $missing = [];
        foreach ($requiredKeys as $k) {
            $ex = $exByKey[$k] ?? null;
            $usable = $ex && in_array($ex['status'], ['auto','verified','edited'], true) && $ex['normalized_value'] !== '' && $ex['normalized_value'] !== null;
            if (!$usable) $missing[] = ['key' => $k, 'label' => $labelByKey[$k] ?? $k];
        }

        // Reconciliation pairs — for every field the DA covers AND the DCPR calc produces,
        // show side by side. Also always include DA-stated development rights (F) → they need
        // a comparison even if no DCPR-side match exists.
        $reconciliations = self::buildReconciliations($exByKey, $calcByKey, $labelByKey);

        // Conflict flags (material discrepancies)
        $conflicts = [];
        foreach ($reconciliations as $r) {
            if ($r['flag'] === 'conflict') $conflicts[] = $r;
        }

        // Coverage stats
        $coveredCount = 0;
        foreach ($requiredKeys as $k) {
            $ex = $exByKey[$k] ?? null;
            if ($ex && in_array($ex['status'], ['auto','verified','edited'], true)) $coveredCount++;
        }
        $coverage = [
            'required_total' => count($requiredKeys),
            'covered'        => $coveredCount,
            'missing'        => count($missing),
            'conflicts'      => count($conflicts),
        ];

        require __DIR__ . '/../views/documents/reconcile.php';
    }

    private static function loadInputSchemaForReg(string $regulationKey): array {
        $sanitized = str_replace(['(', ')', ' '], ['_', '_', '_'], $regulationKey);
        $sanitized = trim(preg_replace('/_+/', '_', $sanitized), '_');
        $path = __DIR__ . '/../dcpr_rules/schemas/reg_' . $sanitized . '_inputs.json';
        if (!file_exists($path)) return ['groups' => []];
        return json_decode(file_get_contents($path), true) ?: ['groups' => []];
    }

    /**
     * Pairs DA extractions with matching DCPR calc rows, plus DA-stated development rights.
     * Returns array of rows: [key, label, da_value, da_unit, da_source, dcpr_value, dcpr_ref, delta, flag].
     * flag ∈ 'match' | 'minor' | 'conflict' | 'da_only' | 'dcpr_only'.
     */
    private static function buildReconciliations(array $exByKey, array $calcByKey, array $labelByKey): array {
        // Map DA-stated development rights → DCPR calculation counterparts
        $daStatedMap = [
            'da_stated_sale_component_sqm'  => ['calc_key' => 'sale_component_sqm',        'label' => 'Sale Component',        'kind' => 'numeric'],
            'da_stated_rehab_component_sqm' => ['calc_key' => 'rehab_component_sqm',       'label' => 'Rehabilitation Component','kind' => 'numeric'],
            'da_stated_fsi'                 => ['calc_key' => 'fsi_sanctioned',            'label' => 'FSI (Sanctioned)',      'kind' => 'ratio'],
            'da_stated_fungible_pct'        => ['calc_key' => null,                        'label' => 'Fungible %',            'kind' => 'literal', 'dcpr_literal' => 35, 'dcpr_ref' => 'Reg 31(3) max 35% (residential)'],
            'da_stated_tdr_sqm'             => ['calc_key' => 'balance_sale_bua_tdr_sqm',  'label' => 'TDR / Balance Sale BUA','kind' => 'numeric'],
        ];

        // Map neutral DA fields (not development rights, just facts) → DCPR calc counterparts
        $factMap = [
            'gross_plot_area_sqm'          => ['calc_key' => 'gross_plot_area_sqm',       'label' => 'Gross Plot Area',       'kind' => 'numeric'],
            'net_plot_area_sqm'            => ['calc_key' => 'net_plot_area_sqm',         'label' => 'Net Plot Area',         'kind' => 'numeric'],
            'primary_road_width_m'         => ['calc_key' => null,                        'label' => 'Primary Road Width',    'kind' => 'passthrough'],
            'eligible_tenements'           => ['calc_key' => null,                        'label' => 'Eligible Tenements',    'kind' => 'passthrough'],
            'carpet_area_per_tenement_sqm' => ['calc_key' => null,                        'label' => 'Carpet per Tenement',   'kind' => 'passthrough'],
            'amenity_common_area_sqm'      => ['calc_key' => null,                        'label' => 'Amenity Common Area',   'kind' => 'passthrough'],
        ];

        $rows = [];

        // Process DA-stated development rights first (these are the conflict-prone ones)
        foreach ($daStatedMap as $daKey => $meta) {
            $ex = $exByKey[$daKey] ?? null;
            if (!$ex) continue;
            $daVal = self::numOrNull($ex['normalized_value']);
            $dcprVal = null;
            $dcprRef = $meta['dcpr_ref'] ?? '';
            if ($meta['kind'] === 'literal' && isset($meta['dcpr_literal'])) {
                $dcprVal = (float)$meta['dcpr_literal'];
            } elseif ($meta['calc_key'] && isset($calcByKey[$meta['calc_key']])) {
                $dcprVal = (float)$calcByKey[$meta['calc_key']]['calc_value'];
                $dcprRef = $calcByKey[$meta['calc_key']]['regulation_ref'] ?? '';
            }
            $rows[] = self::reconRow($ex, $daKey, $meta['label'], $daVal, $dcprVal, $dcprRef, $meta['kind'], true);
        }

        // Then process neutral facts
        foreach ($factMap as $daKey => $meta) {
            $ex = $exByKey[$daKey] ?? null;
            if (!$ex) continue;
            $daVal = self::numOrNull($ex['normalized_value']);
            $dcprVal = null;
            $dcprRef = '';
            if ($meta['calc_key'] && isset($calcByKey[$meta['calc_key']])) {
                $dcprVal = (float)$calcByKey[$meta['calc_key']]['calc_value'];
                $dcprRef = $calcByKey[$meta['calc_key']]['regulation_ref'] ?? '';
            }
            $rows[] = self::reconRow($ex, $daKey, $meta['label'], $daVal, $dcprVal, $dcprRef, $meta['kind'], false);
        }

        return $rows;
    }

    private static function reconRow(array $ex, string $key, string $label, ?float $daVal, ?float $dcprVal, string $dcprRef, string $kind, bool $isRight): array {
        $flag = 'da_only';
        $deltaAbs = null; $deltaPct = null;
        if ($daVal !== null && $dcprVal !== null) {
            $deltaAbs = $daVal - $dcprVal;
            $deltaPct = ($dcprVal != 0) ? ($deltaAbs / $dcprVal * 100) : 0;
            $absPct = abs($deltaPct);
            if ($absPct <= 1)      $flag = 'match';
            elseif ($absPct <= 5)  $flag = 'minor';
            elseif ($isRight)      $flag = 'conflict';  // material delta on a DA-stated right
            else                   $flag = 'minor';    // for neutral facts we don't raise conflict
        } elseif ($daVal !== null && $dcprVal === null) {
            $flag = 'da_only';
        } elseif ($daVal === null && $dcprVal !== null) {
            $flag = 'dcpr_only';
        }
        return [
            'key'        => $key,
            'label'      => $label,
            'is_right'   => $isRight,
            'da_value'   => $daVal,
            'da_unit'    => $ex['unit'] ?? '',
            'da_source'  => trim(($ex['clause_ref'] ?? '') . ($ex['page_no'] ? ' · p.' . $ex['page_no'] : '')),
            'da_status'  => $ex['status'] ?? '',
            'dcpr_value' => $dcprVal,
            'dcpr_ref'   => $dcprRef,
            'delta_abs'  => $deltaAbs,
            'delta_pct'  => $deltaPct,
            'flag'       => $flag,
            'kind'       => $kind,
        ];
    }

    private static function numOrNull($v): ?float {
        if ($v === null || $v === '') return null;
        if (!is_numeric($v)) return null;
        return (float)$v;
    }

    private static function loadProposal(int $id): ?array {
        if ($id <= 0) return null;
        $stmt = getDB()->prepare("SELECT * FROM proposals WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }
}
