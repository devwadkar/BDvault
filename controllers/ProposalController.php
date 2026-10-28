<?php
require_once __DIR__ . '/../models/Proposal.php';
require_once __DIR__ . '/../models/Stage1Filter.php';
require_once __DIR__ . '/../models/Stage2Scorer.php';
require_once __DIR__ . '/../models/WeightsConfig.php';
require_once __DIR__ . '/../includes/ExcelReader.php';
require_once __DIR__ . '/../includes/functions.php';

class ProposalController {

    // ─── List ────────────────────────────────────────────────────────────────

    public static function list(): void {
        $filters = [
            'search'        => trim($_GET['search'] ?? ''),
            'proposal_type' => $_GET['proposal_type'] ?? '',
            'stage1_status' => $_GET['stage1_status'] ?? '',
            'category'      => $_GET['category'] ?? '',
            'region'        => $_GET['region'] ?? '',
            'status'        => $_GET['status'] ?? '',
            'sort'          => $_GET['sort'] ?? 'p.created_at',
            'dir'           => $_GET['dir'] ?? 'DESC',
        ];
        $page   = max(1, (int)($_GET['p'] ?? 1));
        $result = Proposal::getList($filters, $page, 20);
        $rows   = $result['rows'];
        $pag    = $result['pagination'];
        $regions= Proposal::getRegions();

        require __DIR__ . '/../views/proposals/list.php';
    }

    // ─── Detail ──────────────────────────────────────────────────────────────

    public static function detail(): void {
        $id       = (int)($_GET['id'] ?? 0);
        $proposal = Proposal::getDetail($id);
        if (!$proposal) { self::notFound(); return; }

        $breakdown = $proposal['score_breakdown'] ? json_decode($proposal['score_breakdown'], true) : [];
        $reasons   = $proposal['rejection_reasons'] ? json_decode($proposal['rejection_reasons'], true) : [];
        
        $marketAnalysis = Proposal::getMarketAnalysis($id);

        require __DIR__ . '/../views/proposals/detail.php';
    }

    public static function report(): void {
        $id       = (int)($_GET['id'] ?? 0);
        $proposal = Proposal::getDetail($id);
        if (!$proposal) { self::notFound(); return; }

        $breakdown = $proposal['score_breakdown'] ? json_decode($proposal['score_breakdown'], true) : [];
        $reasons   = $proposal['rejection_reasons'] ? json_decode($proposal['rejection_reasons'], true) : [];

        require __DIR__ . '/../views/proposals/report.php';
    }

    // ─── Update Decision ─────────────────────────────────────────────────────

    public static function updateDecision(): void {
        // Backend guard — double-check even though route is guarded
        requirePermission('decide_proposals');

        $id = (int)($_GET['id'] ?? 0);
        $decision = $_GET['decision'] ?? '';

        if ($id && in_array($decision, ['Ready for Review', 'Accepted', 'Rejected'], true)) {
            $db = getDB();
            $stmt = $db->prepare("UPDATE proposals SET status = ? WHERE id = ?");
            $stmt->bind_param('si', $decision, $id);
            $stmt->execute();

            flashSet('success', "Proposal decision updated to '{$decision}' successfully.");
        }

        redirect("index.php?page=proposal_detail&id={$id}");
    }

    // ─── Add / Edit form ─────────────────────────────────────────────────────

    public static function form(): void {
        $id       = (int)($_GET['id'] ?? 0);
        $proposal = $id ? Proposal::getById($id) : null;
        $errors   = [];
        $flash    = flashGet();

        require __DIR__ . '/../views/proposals/form.php';
    }

    // ─── Save (insert / update) ───────────────────────────────────────────────

    public static function save(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('index.php?page=proposals'); return; }

        $id = (int)($_POST['id'] ?? 0);

        // Backend guard: viewers can add but not edit
        if ($id) {
            requirePermission('edit_proposals');
        } else {
            requirePermission('add_proposals');
        }

        $d = self::collectFormData($_POST);

        if ($id) {
            Proposal::update($id, $d);
            // Re-run classification after edit
            $p   = Proposal::getById($id);
            $cfg = WeightsConfig::getActive();
            if ($p) {
                Stage1Filter::reEvaluate($p, $cfg);
                Stage2Scorer::reEvaluate($p, $cfg);
            }
            flashSet('success', 'Proposal updated and re-classified successfully.');
            redirect("index.php?page=proposal_detail&id={$id}");
        } else {
            $newId = Proposal::insert($d);
            $p     = Proposal::getById($newId);
            $cfg   = WeightsConfig::getActive();
            Stage1Filter::evaluate($p, $cfg);
            Stage2Scorer::evaluate($p, $cfg);
            flashSet('success', 'Proposal added and classified successfully.');
            redirect("index.php?page=proposal_detail&id={$newId}");
        }
    }

    // ─── Delete ──────────────────────────────────────────────────────────────

    public static function delete(): void {
        $id = (int)($_GET['id'] ?? 0);
        if ($id) Proposal::delete($id);
        flashSet('success', 'Proposal deleted.');
        redirect('index.php?page=proposals');
    }

    // ─── Excel Upload ────────────────────────────────────────────────────────

    public static function uploadForm(): void {
        $flash = flashGet();
        require __DIR__ . '/../views/proposals/upload.php';
    }

    public static function uploadProcess(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('index.php?page=upload');
            return;
        }

        $file = $_FILES['excel_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            flashSet('danger', 'Upload failed: ' . ($file['error'] ?? 'no file'));
            redirect('index.php?page=upload');
            return;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
            flashSet('danger', 'Only .xlsx, .xls, and .csv files are accepted.');
            redirect('index.php?page=upload');
            return;
        }

        $dest = UPLOAD_DIR . uniqid('import_') . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            flashSet('danger', 'Could not save uploaded file.');
            redirect('index.php?page=upload');
            return;
        }

        $source    = $_POST['source_label'] ?? 'excel_import';
        $imported  = 0;
        $skipped   = 0;
        $errors    = [];
        $cfg       = WeightsConfig::getActive();

        try {
            if ($ext === 'csv') {
                $rows = self::readCsv($dest);
            } else {
                $reader = new ExcelReader($dest);
                $rows   = $reader->getRows(1, true);
            }

            foreach ($rows as $idx => $row) {
                $mapped = Proposal::mapExcelRow($row, $source);
                if (empty($mapped['proposal_name'])) { $skipped++; continue; }

                try {
                    $newId = Proposal::importInsert($mapped);
                    if ($newId === 0) {
                        // Duplicate — already exists
                        $skipped++;
                        continue;
                    }
                    $p     = Proposal::getById($newId);
                    Stage1Filter::evaluate($p, $cfg);
                    Stage2Scorer::evaluate($p, $cfg);
                    $imported++;
                } catch (Throwable $e) {
                    $errors[] = "Row " . ($idx + 2) . ": " . $e->getMessage();
                    $skipped++;
                }
            }
        } catch (Throwable $e) {
            @unlink($dest);
            flashSet('danger', 'Error reading file: ' . $e->getMessage());
            redirect('index.php?page=upload');
            return;
        }

        // Log import
        $db   = getDB();
        $stmt = $db->prepare("INSERT INTO import_logs (filename, source_label, total_rows, imported_rows, skipped_rows, errors) VALUES (?,?,?,?,?,?)");
        $total = $imported + $skipped;
        $errJson = json_encode($errors);
        $stmt->bind_param('ssiiss', $file['name'], $source, $total, $imported, $skipped, $errJson);
        $stmt->execute();

        @unlink($dest);
        $msg = "Import complete: {$imported} proposals imported, {$skipped} skipped.";
        if ($errors) $msg .= ' Errors: ' . implode('; ', array_slice($errors, 0, 3));
        flashSet($errors ? 'warning' : 'success', $msg);
        redirect('index.php?page=proposals');
    }

    // ─── Re-run classification for one proposal ───────────────────────────────

    public static function reclassify(): void {
        $id  = (int)($_GET['id'] ?? 0);
        $p   = Proposal::getById($id);
        $cfg = WeightsConfig::getActive();
        if ($p) {
            Stage1Filter::reEvaluate($p, $cfg);
            Stage2Scorer::reEvaluate($p, $cfg);
            flashSet('success', 'Proposal re-classified with current config.');
        }
        redirect("index.php?page=proposal_detail&id={$id}");
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private static function collectFormData(array $p): array {
        return [
            'proposal_name'          => trim($p['proposal_name'] ?? ''),
            'proposal_type'          => trim($p['proposal_type'] ?? ''),
            'region'                 => trim($p['region'] ?? ''),
            'city'                   => trim($p['city'] ?? ''),
            'site_address'           => trim($p['site_address'] ?? ''),
            'pincode'                => trim($p['pincode'] ?? ''),
            'reference_owner'        => trim($p['reference_owner'] ?? ''),
            'consultant_name_number' => trim($p['consultant_name_number'] ?? ''),
            'lat_lng'                => trim($p['lat_lng'] ?? ''),
            'date_month'             => safeDate($p['date_month'] ?? ''),
            'data_source'            => 'manual',
            'plot_area_sqm'          => safeFloat($p['plot_area_sqm'] ?? ''),
            'avg_sale_price_sqft'    => safeFloat($p['avg_sale_price_sqft'] ?? ''),
            'entry_cost_per_sqft'    => safeFloat($p['entry_cost_per_sqft'] ?? ''),
            'existing_fsi_used'      => safeFloat($p['existing_fsi_used'] ?? ''),
            'free_sale_area_pct'     => safeFloat($p['free_sale_area_pct'] ?? ''),
            'society_allocation_pct' => safeFloat($p['society_allocation_pct'] ?? ''),
            'existing_units'         => safeInt($p['existing_units'] ?? ''),
            'proposed_units'         => safeInt($p['proposed_units'] ?? ''),
            'completion_timeline_yrs'=> safeFloat($p['completion_timeline_yrs'] ?? ''),
            'location_rating'        => safeInt($p['location_rating'] ?? ''),
            'approval_complexity'    => safeInt($p['approval_complexity'] ?? ''),
            'market_demand'          => safeInt($p['market_demand'] ?? ''),
            'is_sra'                 => isset($p['is_sra']) ? 1 : 0,
            'is_outright'            => isset($p['is_outright']) ? 1 : 0,
            'is_mhada'               => isset($p['is_mhada']) ? 1 : 0,
            'is_stressed'            => isset($p['is_stressed']) ? 1 : 0,
            'status'                 => !empty($p['status']) ? trim($p['status']) : 'Ready for Review',
            'remarks'                => trim($p['remarks'] ?? ''),
            'cluster'                => trim($p['cluster'] ?? ''),
        ];
    }

    private static function readCsv(string $path): array {
        $rows    = [];
        $headers = null;
        if (($fh = fopen($path, 'r')) !== false) {
            while (($cols = fgetcsv($fh)) !== false) {
                if (!$headers) { $headers = $cols; continue; }
                $rows[] = array_combine($headers, $cols);
            }
            fclose($fh);
        }
        return $rows;
    }

    private static function notFound(): void {
        http_response_code(404);
        echo '<h2>Proposal not found.</h2><a href="index.php?page=proposals">Back to list</a>';
    }
}
