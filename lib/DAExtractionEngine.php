<?php
/**
 * DAExtractionEngine — structured project-data extraction from Development Agreement text.
 *
 * Reads document_pages for a proposal, assembles a page-numbered corpus, sends it to Gemini
 * with a strict JSON schema (matching field_key names used by the planning module),
 * and upserts the response into da_extractions with source tracking + confidence.
 *
 * Every extracted value carries: value, unit, original_text (exact quote), page_no,
 * clause_ref, confidence (0-100), and the AI's own notes.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/AIClient.php';

class DAExtractionEngine {

    private const MAX_CONTEXT_CHARS = 180000; // ~45k tokens — safe for Gemini 2.5 flash 1M window

    /**
     * Fields to extract. Keys align with plan_inputs.input_key where possible so the
     * "Prefill from DA" bridge can drop values in without translation.
     */
    private const FIELDS = [
        // A. Project Identification
        'project_name'                => ['label' => 'Project Name',                'category' => 'identification', 'unit' => null,        'type' => 'string'],
        'project_address'             => ['label' => 'Project Address',             'category' => 'identification', 'unit' => null,        'type' => 'string'],
        'village'                     => ['label' => 'Village',                     'category' => 'identification', 'unit' => null,        'type' => 'string'],
        'taluka'                      => ['label' => 'Taluka',                      'category' => 'identification', 'unit' => null,        'type' => 'string'],
        'district'                    => ['label' => 'District',                    'category' => 'identification', 'unit' => null,        'type' => 'string'],
        'ward'                        => ['label' => 'Ward',                        'category' => 'identification', 'unit' => null,        'type' => 'string'],
        'cts_number'                  => ['label' => 'CTS Number',                  'category' => 'identification', 'unit' => null,        'type' => 'string'],
        'survey_number'               => ['label' => 'Survey Number',               'category' => 'identification', 'unit' => null,        'type' => 'string'],
        'plot_number'                 => ['label' => 'Plot Number',                 'category' => 'identification', 'unit' => null,        'type' => 'string'],
        'pincode'                     => ['label' => 'Pincode',                     'category' => 'identification', 'unit' => null,        'type' => 'string'],

        // B. Land Details
        'gross_plot_area_sqm'         => ['label' => 'Gross Plot Area',             'category' => 'land',           'unit' => 'sqm',       'type' => 'number'],
        'existing_plot_area_sqm'      => ['label' => 'Existing Plot Area (if stated separately)', 'category' => 'land', 'unit' => 'sqm', 'type' => 'number'],
        'net_plot_area_sqm'           => ['label' => 'Net Plot Area (if explicitly stated)', 'category' => 'land',   'unit' => 'sqm',       'type' => 'number'],
        'land_ownership'              => ['label' => 'Land Ownership',              'category' => 'land',           'unit' => null,        'type' => 'string'],

        // C. Road Information
        'primary_road_width_m'        => ['label' => 'Primary / Existing Road Width', 'category' => 'road',         'unit' => 'm',         'type' => 'number'],
        'secondary_road_width_m'      => ['label' => 'Secondary Road Width',        'category' => 'road',           'unit' => 'm',         'type' => 'number'],
        'existing_dp_road_width_m'    => ['label' => 'Existing DP Road Width',      'category' => 'road',           'unit' => 'm',         'type' => 'number'],
        'proposed_dp_road_width_m'    => ['label' => 'Proposed DP Road Width',      'category' => 'road',           'unit' => 'm',         'type' => 'number'],
        'dp_road_setback_primary_sqm' => ['label' => 'Primary DP Road Setback Area','category' => 'road',           'unit' => 'sqm',       'type' => 'number'],

        // D. Existing Development
        'existing_buildings_count'    => ['label' => 'Existing Buildings',          'category' => 'existing',       'unit' => 'count',     'type' => 'number'],
        'existing_bua_sqm'            => ['label' => 'Existing BUA',                'category' => 'existing',       'unit' => 'sqm',       'type' => 'number'],
        'existing_carpet_area_sqm'    => ['label' => 'Existing Carpet Area',        'category' => 'existing',       'unit' => 'sqm',       'type' => 'number'],
        'existing_floors'             => ['label' => 'Number of Existing Floors',   'category' => 'existing',       'unit' => 'count',     'type' => 'number'],
        'existing_fsi_used'           => ['label' => 'Existing FSI Used',           'category' => 'existing',       'unit' => 'ratio',     'type' => 'number'],

        // E. Rehabilitation
        'eligible_tenements'          => ['label' => 'Eligible Tenements / Occupants', 'category' => 'rehab',       'unit' => 'tenements', 'type' => 'number'],
        'welfare_tenements'           => ['label' => 'Welfare / Balwadi / PAP Tenements', 'category' => 'rehab',    'unit' => 'tenements', 'type' => 'number'],
        'carpet_area_per_tenement_sqm' => ['label' => 'Carpet Area per Tenement',   'category' => 'rehab',          'unit' => 'sqm',       'type' => 'number'],
        'rehab_buildings_count'       => ['label' => 'Number of Rehab Buildings',   'category' => 'rehab',          'unit' => 'count',     'type' => 'number'],
        'amenity_common_area_sqm'     => ['label' => 'Amenity Common Area (Balwadi + WC + Society Office + Common Passage)', 'category' => 'rehab', 'unit' => 'sqm', 'type' => 'number'],
        'religious_structures_sqm'    => ['label' => 'Religious Structures BUA',    'category' => 'rehab',          'unit' => 'sqm',       'type' => 'number'],
        'social_infra_sqm'            => ['label' => 'Social Infra BUA',            'category' => 'rehab',          'unit' => 'sqm',       'type' => 'number'],

        // F. Development Rights (DA-stated, NOT DCPR-permissible)
        'da_stated_fsi'               => ['label' => 'DA-Stated FSI',               'category' => 'rights',         'unit' => 'ratio',     'type' => 'number'],
        'da_stated_tdr_sqm'           => ['label' => 'DA-Stated TDR',               'category' => 'rights',         'unit' => 'sqm',       'type' => 'number'],
        'da_stated_fungible_pct'      => ['label' => 'DA-Stated Fungible %',        'category' => 'rights',         'unit' => 'percent',   'type' => 'number'],
        'da_stated_sale_component_sqm'=> ['label' => 'DA-Stated Sale Component',    'category' => 'rights',         'unit' => 'sqm',       'type' => 'number'],
        'da_stated_rehab_component_sqm'=> ['label' => 'DA-Stated Rehab Component',  'category' => 'rights',         'unit' => 'sqm',       'type' => 'number'],
        'applicable_regulation'       => ['label' => 'Applicable DCPR Regulation stated in DA', 'category' => 'rights', 'unit' => null,    'type' => 'string'],

        // G. Reservations & Restrictions
        'reservation_area_other_sqm'  => ['label' => 'DP Reservation Area (non-ROS)', 'category' => 'reservations', 'unit' => 'sqm',       'type' => 'number'],
        'reservation_area_ros_sqm'    => ['label' => 'ROS / Green Belt Area',       'category' => 'reservations',   'unit' => 'sqm',       'type' => 'number'],
        'nalla_setback_sqm'           => ['label' => 'Nalla / Storm Water Setback', 'category' => 'reservations',   'unit' => 'sqm',       'type' => 'number'],
        'amenity_plot_sqm'            => ['label' => 'Amenity Plot Area',           'category' => 'reservations',   'unit' => 'sqm',       'type' => 'number'],
        'crz_status'                  => ['label' => 'CRZ Status',                  'category' => 'reservations',   'unit' => null,        'type' => 'string'],
        'heritage_status'             => ['label' => 'Heritage Status',             'category' => 'reservations',   'unit' => null,        'type' => 'string'],
    ];

    /** Public entry — extracts from all docs of a proposal in one batch. */
    public static function extractProposal(int $proposalId): array {
        $db = getDB();

        // Load doc pages for this proposal, ordered by doc then page
        $sql = "SELECT d.id AS doc_id, d.filename, d.doc_type, p.page_no, p.text
                FROM project_documents d
                LEFT JOIN document_pages p ON p.document_id = d.id
                WHERE d.proposal_id = ? AND d.text_extracted = 1
                ORDER BY d.id, p.page_no";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $proposalId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (empty($rows)) {
            return ['ok' => false, 'error' => 'No extracted document text found for this proposal. Upload docs and run text extraction first.'];
        }

        [$corpus, $docId] = self::assembleCorpus($rows);
        if (trim($corpus) === '') {
            return ['ok' => false, 'error' => 'Document text is empty — likely a scanned PDF. OCR support arrives in Phase 3c.'];
        }

        // Log a batch row up-front
        $batchStmt = $db->prepare("INSERT INTO extraction_batches (proposal_id, model_used, prompt_version, tokens_in, ran_by, status) VALUES (?, ?, 1, ?, ?, 'success')");
        $model = defined('GEMINI_MODEL') ? GEMINI_MODEL : 'gemini';
        $inChars = strlen($corpus);
        $userId = (int)TokenAuth::userId();
        $batchStmt->bind_param('isii', $proposalId, $model, $inChars, $userId);
        $batchStmt->execute();
        $batchId = $batchStmt->insert_id;

        $prompt = self::buildPrompt($corpus);
        $json = AIClient::callGemini($prompt, true);

        if (!$json) {
            $upd = $db->prepare("UPDATE extraction_batches SET status = 'failed', error_message = ? WHERE id = ?");
            $err = 'Gemini did not return a response';
            $upd->bind_param('si', $err, $batchId);
            $upd->execute();
            return ['ok' => false, 'error' => $err];
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            $upd = $db->prepare("UPDATE extraction_batches SET status = 'failed', error_message = ? WHERE id = ?");
            $err = 'AI returned invalid JSON: ' . substr($json, 0, 200);
            $upd->bind_param('si', $err, $batchId);
            $upd->execute();
            return ['ok' => false, 'error' => $err];
        }

        $extracted = $data['extractions'] ?? [];
        $upsert = $db->prepare("
            INSERT INTO da_extractions
              (proposal_id, batch_id, document_id, field_key, field_category, field_label,
               original_text, extracted_value, unit, normalized_value, page_no, clause_ref, confidence, status, ai_notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
              batch_id = VALUES(batch_id),
              document_id = VALUES(document_id),
              original_text = VALUES(original_text),
              extracted_value = VALUES(extracted_value),
              unit = VALUES(unit),
              normalized_value = VALUES(normalized_value),
              page_no = VALUES(page_no),
              clause_ref = VALUES(clause_ref),
              confidence = VALUES(confidence),
              status = VALUES(status),
              ai_notes = VALUES(ai_notes)
        ");

        $count = 0;
        foreach ($extracted as $key => $blob) {
            if (!isset(self::FIELDS[$key])) continue;
            if (!is_array($blob)) continue;

            $val = $blob['value'] ?? null;
            if ($val === null || $val === '') continue;

            $meta = self::FIELDS[$key];
            $rawValue      = is_scalar($val) ? (string)$val : json_encode($val);
            $unit          = $blob['unit'] ?? $meta['unit'];
            $normalized    = self::normalize($rawValue, $meta['unit']);
            $originalText  = (string)($blob['original_text'] ?? '');
            $pageNo        = isset($blob['page']) ? (int)$blob['page'] : null;
            $clauseRef     = (string)($blob['clause'] ?? '');
            $confidence    = isset($blob['confidence']) ? max(0, min(100, (int)$blob['confidence'])) : 0;
            $notes         = (string)($blob['notes'] ?? '');
            $status        = $confidence >= 90 ? 'auto' : 'needs_review';

            $upsert->bind_param(
                'iiissssssssisss',
                $proposalId, $batchId, $docId, $key, $meta['category'], $meta['label'],
                $originalText, $rawValue, $unit, $normalized, $pageNo, $clauseRef, $confidence, $status, $notes
            );
            $upsert->execute();
            $count++;
        }

        $upd = $db->prepare("UPDATE extraction_batches SET tokens_out = ? WHERE id = ?");
        $outLen = strlen($json);
        $upd->bind_param('ii', $outLen, $batchId);
        $upd->execute();

        return ['ok' => true, 'batch_id' => $batchId, 'extracted_count' => $count];
    }

    // ─── Prompt assembly ─────────────────────────────────────────────────

    private static function buildPrompt(string $corpus): string {
        $schemaLines = [];
        foreach (self::FIELDS as $key => $meta) {
            $u = $meta['unit'] ? " ({$meta['unit']})" : '';
            $schemaLines[] = "  \"{$key}\": {\"value\": <" . $meta['type'] . " or null>, \"unit\": \"{$meta['unit']}\", \"original_text\": \"<exact quote from doc>\", \"page\": <int>, \"clause\": \"<e.g. Schedule II, Clause 4>\", \"confidence\": <0-100>, \"notes\": \"<why this confidence>\"},  // {$meta['label']}{$u}";
        }
        $schema = implode("\n", $schemaLines);

        return <<<PROMPT
You are a Real-Estate Legal Document Analyst specializing in Mumbai Development Agreements. Extract structured project data from the DA text below.

═══════════════════════════════════════════════════════════════
CRITICAL EXTRACTION RULES
═══════════════════════════════════════════════════════════════
1. Return ONE JSON object. Top-level key must be "extractions".
2. For each field, provide value, unit, original_text (EXACT verbatim quote), page, clause reference, and confidence (0-100).
3. If a field is not stated in the document, OMIT that field from "extractions" — do NOT invent, do NOT guess.
4. Numbers must be normalized to numeric (e.g. "64,219.67 sq.m." -> value: 64219.67, unit: "sqm").
5. Confidence rubric:
   - 90–100: value stated verbatim in a clear clause with unambiguous context
   - 70–89:  value derived from a table, requires visual OCR reading, or context is slightly ambiguous
   - 40–69:  value inferred from surrounding text or partial match
   - Below 40: do NOT include the field
6. If the DA quotes a "DA-stated" development right (FSI, TDR, fungible %, sale component), extract to the "da_stated_*" fields — these are DA CLAIMS not DCPR truths. Never merge them with regulatory values.
7. original_text should be 20–160 chars and MUST appear verbatim in the source.
8. page must be an integer matching the DA text below (marked as "===== PAGE N ====="). If a value spans pages, use the FIRST page it appears on.
9. clause should reference schedule/section/clause number if visible (e.g. "Schedule II, Clause 4" or "Recital 5" or "para 3.2").

═══════════════════════════════════════════════════════════════
EXPECTED JSON SHAPE (only include fields that were found)
═══════════════════════════════════════════════════════════════
{
  "extractions": {
{$schema}
  }
}

═══════════════════════════════════════════════════════════════
DEVELOPMENT AGREEMENT TEXT
═══════════════════════════════════════════════════════════════
{$corpus}

Extract now. Return ONLY the JSON object.
PROMPT;
    }

    /** Assemble a page-labelled corpus. Returns [corpus_string, primary_document_id]. */
    private static function assembleCorpus(array $rows): array {
        $chunks = [];
        $primaryDocId = null;
        $used = 0;
        $currentDoc = null;
        foreach ($rows as $r) {
            if (!$primaryDocId) $primaryDocId = (int)$r['doc_id'];
            $header = '';
            if ($currentDoc !== $r['doc_id']) {
                $header = "\n\n══════ DOCUMENT: " . $r['filename'] . " (" . $r['doc_type'] . ") ══════\n";
                $currentDoc = $r['doc_id'];
            }
            $pageMark = "===== PAGE " . (int)$r['page_no'] . " =====\n";
            $body = trim((string)$r['text']);
            if ($body === '') continue;
            $piece = $header . $pageMark . $body . "\n";
            $len = strlen($piece);
            if ($used + $len > self::MAX_CONTEXT_CHARS) break; // hard-cap
            $chunks[] = $piece;
            $used += $len;
        }
        return [implode('', $chunks), $primaryDocId ?: 0];
    }

    /**
     * Normalize a raw value string. Areas -> sqm; roads -> m; percentages -> number.
     * Returns numeric string or the original raw value if not normalizable.
     */
    private static function normalize(string $raw, ?string $canonicalUnit): string {
        if ($canonicalUnit === null) return $raw;

        // Strip commas + trailing labels
        $s = preg_replace('/[₹,]/', '', $raw);
        $s = trim($s);
        // Extract the first numeric token
        if (!preg_match('/-?\d+(?:\.\d+)?/', $s, $m)) return $raw;
        $n = (float)$m[0];

        $rawLower = strtolower($raw);
        if ($canonicalUnit === 'sqm') {
            if (strpos($rawLower, 'sq.ft') !== false || strpos($rawLower, 'sqft') !== false || strpos($rawLower, 'sq ft') !== false) {
                $n = $n / 10.7639;
            }
        }
        if ($canonicalUnit === 'sqft') {
            if (strpos($rawLower, 'sq.m') !== false || strpos($rawLower, 'sqm') !== false || strpos($rawLower, 'sq m') !== false) {
                $n = $n * 10.7639;
            }
        }
        // For 'm' (road width) accept feet -> m
        if ($canonicalUnit === 'm' && (strpos($rawLower, 'ft') !== false || strpos($rawLower, 'feet') !== false)) {
            $n = $n * 0.3048;
        }
        return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.') ?: '0';
    }

    /** Public accessor for the field catalog (used by the verification view). */
    public static function fields(): array {
        return self::FIELDS;
    }
}
