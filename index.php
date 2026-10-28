<?php
/**
 * PMS – Proposal Management System
 * Single front-controller router
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/token_auth.php';
require_once __DIR__ . '/includes/permissions.php';

// Boot token-based auth (replaces session_start() — bypasses Varnish/NFS issues)
TokenAuth::boot();

// ── Route map ────────────────────────────────────────────────────────────────
$page = preg_replace('/[^a-z0-9_]/', '', strtolower($_GET['page'] ?? 'dashboard'));

switch ($page) {

    // ── Public Auth Routes (no login required) ───────────────────────────────

    case 'login':
        require_once __DIR__ . '/controllers/AuthController.php';
        AuthController::login();
        break;

    case 'login_process':
        require_once __DIR__ . '/controllers/AuthController.php';
        AuthController::loginProcess();
        break;

    case 'logout':
        require_once __DIR__ . '/controllers/AuthController.php';
        AuthController::logout();
        break;

    // Register is disabled – redirect to login
    case 'register':
    case 'register_process':
        require_once __DIR__ . '/controllers/AuthController.php';
        AuthController::register();
        break;

    // ── All routes below require a valid login ────────────────────────────────

    // Dashboard
    case 'dashboard':
    case '':
        requireLogin();
        require_once __DIR__ . '/controllers/DashboardController.php';
        DashboardController::index();
        break;

    // Proposal list
    case 'proposals':
        requireLogin();
        require_once __DIR__ . '/controllers/ProposalController.php';
        ProposalController::list();
        break;

    // Proposal detail
    case 'proposal_detail':
        requireLogin();
        require_once __DIR__ . '/controllers/ProposalController.php';
        ProposalController::detail();
        break;

    // Proposal report
    case 'proposal_report':
        requireLogin();
        require_once __DIR__ . '/controllers/ProposalController.php';
        ProposalController::report();
        break;

    // Add form
    case 'proposal_add':
        requireLogin();
        require_once __DIR__ . '/controllers/ProposalController.php';
        ProposalController::form();
        break;

    // AI API endpoints
    case 'api_ai_extract':
        requireLogin();
        require_once __DIR__ . '/controllers/AIController.php';
        AIController::extractData();
        break;

    case 'api_ai_chat':
        requireLogin();
        require_once __DIR__ . '/controllers/AIController.php';
        AIController::chatQuery();
        break;

    case 'api_ai_stage1':
        requireLogin();
        require_once __DIR__ . '/controllers/AIController.php';
        AIController::autoEstimateStage1();
        break;

    case 'api_ai_stage2':
        requireLogin();
        require_once __DIR__ . '/controllers/AIController.php';
        AIController::autoScoreStage2();
        break;

    case 'api_market_analysis_generate':
        requireLogin();
        require_once __DIR__ . '/controllers/MarketAnalysisController.php';
        MarketAnalysisController::generateAnalysis();
        break;

    case 'api_market_analysis_full':
        requireLogin();
        require_once __DIR__ . '/controllers/MarketAnalysisController.php';
        MarketAnalysisController::generateFullAnalysis();
        break;

    case 'api_ai_underwrite':
        requireLogin();
        require_once __DIR__ . '/controllers/AIController.php';
        AIController::underwriteChat();
        break;

    // Edit form
    case 'proposal_edit':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/ProposalController.php';
        ProposalController::form();
        break;

    // Save (POST)
    case 'proposal_save':
        requireLogin();
        require_once __DIR__ . '/controllers/ProposalController.php';
        ProposalController::save();
        break;

    // Decision (GET/POST)
    case 'proposal_decision':
        requirePermission('decide_proposals');
        require_once __DIR__ . '/controllers/ProposalController.php';
        ProposalController::updateDecision();
        break;

    // Delete
    case 'proposal_delete':
        requirePermission('delete_proposals');
        require_once __DIR__ . '/controllers/ProposalController.php';
        ProposalController::delete();
        break;

    // Re-classify a single proposal
    case 'reclassify':
        requirePermission('reclassify_proposals');
        require_once __DIR__ . '/controllers/ProposalController.php';
        ProposalController::reclassify();
        break;

    // Upload form
    case 'upload':
        requirePermission('import_proposals');
        require_once __DIR__ . '/controllers/ProposalController.php';
        ProposalController::uploadForm();
        break;

    // Upload process (POST)
    case 'upload_process':
        requirePermission('import_proposals');
        require_once __DIR__ . '/controllers/ProposalController.php';
        ProposalController::uploadProcess();
        break;

    // Weights config
    case 'weights':
        requirePermission('manage_weights');
        require_once __DIR__ . '/controllers/WeightsController.php';
        WeightsController::index();
        break;

    // Weights save (POST)
    case 'weights_save':
        requirePermission('manage_weights');
        require_once __DIR__ . '/controllers/WeightsController.php';
        WeightsController::save();
        break;

    // Activate a weight config
    case 'weights_activate':
        requirePermission('manage_weights');
        require_once __DIR__ . '/controllers/WeightsController.php';
        WeightsController::activate();
        break;

    // ── Admin Routes (require manage_users permission) ────────────────────

    case 'admin_users':
        requirePermission('manage_users');
        require_once __DIR__ . '/controllers/AdminController.php';
        AdminController::users();
        break;

    case 'admin_user_create':
        requirePermission('manage_users');
        require_once __DIR__ . '/controllers/AdminController.php';
        AdminController::createUser();
        break;

    case 'admin_user_save_create':
        requirePermission('manage_users');
        require_once __DIR__ . '/controllers/AdminController.php';
        AdminController::createUserProcess();
        break;

    case 'admin_user_edit':
        requirePermission('manage_users');
        require_once __DIR__ . '/controllers/AdminController.php';
        AdminController::editUser();
        break;

    case 'admin_user_save_edit':
        requirePermission('manage_users');
        require_once __DIR__ . '/controllers/AdminController.php';
        AdminController::editUserProcess();
        break;

    case 'admin_user_delete':
        requirePermission('manage_users');
        require_once __DIR__ . '/controllers/AdminController.php';
        AdminController::deleteUser();
        break;

    // ── DA Intelligence: Documents & Extraction ────────────────────────────
    case 'documents':
        requireLogin();
        require_once __DIR__ . '/controllers/DocumentsController.php';
        DocumentsController::index();
        break;
    case 'documents_upload':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/DocumentsController.php';
        DocumentsController::upload();
        break;
    case 'documents_extract':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/DocumentsController.php';
        DocumentsController::extract();
        break;
    case 'documents_verify':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/DocumentsController.php';
        DocumentsController::verify();
        break;
    case 'documents_download':
        requireLogin();
        require_once __DIR__ . '/controllers/DocumentsController.php';
        DocumentsController::download();
        break;
    case 'documents_source':
        requireLogin();
        require_once __DIR__ . '/controllers/DocumentsController.php';
        DocumentsController::source();
        break;
    case 'documents_reconcile':
        requireLogin();
        require_once __DIR__ . '/controllers/DocumentsController.php';
        DocumentsController::reconcile();
        break;
    case 'api_documents_assistant':
        requireLogin();
        require_once __DIR__ . '/controllers/DocumentsController.php';
        DocumentsController::apiAssistant();
        break;

    case 'planning_prefill_da':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/DevelopmentPlanningController.php';
        DevelopmentPlanningController::prefillFromDA();
        break;

    case 'planning_upload_autofill':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/DevelopmentPlanningController.php';
        DevelopmentPlanningController::uploadAndAutofill();
        break;

    case 'planning_cross_scheme':
        requireLogin();
        require_once __DIR__ . '/controllers/DevelopmentPlanningController.php';
        DevelopmentPlanningController::crossScheme();
        break;

    case 'planning_delete':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/DevelopmentPlanningController.php';
        DevelopmentPlanningController::delete();
        break;

    case 'planning_financials_form':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/DevelopmentPlanningController.php';
        DevelopmentPlanningController::financialsForm();
        break;
    case 'planning_financials_save':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/DevelopmentPlanningController.php';
        DevelopmentPlanningController::financialsSave();
        break;
    case 'planning_financials_view':
        requireLogin();
        require_once __DIR__ . '/controllers/DevelopmentPlanningController.php';
        DevelopmentPlanningController::financialsView();
        break;

    case 'seed_approval_masters':
        if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') requireAdmin();
        require_once __DIR__ . '/controllers/ApprovalCostsController.php';
        ApprovalCostsController::seedMasters();
        break;

    case 'migrate_v21':
        if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') requireAdmin();
        $filePath = __DIR__ . '/sql/migrate_v21_approval_cost_inputs.sql';
        if (!file_exists($filePath)) { echo 'Migration file missing'; exit; }
        $sql = file_get_contents($filePath);
        try { $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); if ($db->connect_error) throw new Exception("Connection failed"); }
        catch (Throwable $e) { exit('DB Error: ' . $e->getMessage()); }
        $db->set_charset(DB_CHARSET);
        $stripped = implode("\n", array_filter(explode("\n", $sql), fn($l) => !preg_match('/^\s*--/', $l)));
        $lines = array_filter(array_map('trim', explode(';', $stripped))); $results = [];
        foreach ($lines as $s) { if (!$s) continue; $results[] = $db->query($s) === false ? "❌ " . $db->error : "✅ OK"; }
        echo '<pre>Migration v21 (Project inputs) Results:<br>' . implode('<br>', $results) . '</pre>';
        break;

    case 'migrate_v20':
        if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') requireAdmin();
        $filePath = __DIR__ . '/sql/migrate_v20_approval_costs.sql';
        if (!file_exists($filePath)) { echo 'Migration file missing'; exit; }
        $sql = file_get_contents($filePath);
        try { $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); if ($db->connect_error) throw new Exception("Connection failed"); }
        catch (Throwable $e) { exit('DB Error: ' . $e->getMessage()); }
        $db->set_charset(DB_CHARSET); $db->query("SET FOREIGN_KEY_CHECKS = 0");
        $stripped = implode("\n", array_filter(explode("\n", $sql), fn($l) => !preg_match('/^\s*--/', $l)));
        $lines = array_filter(array_map('trim', explode(';', $stripped))); $results = [];
        foreach ($lines as $s) { if (!$s) continue; $results[] = $db->query($s) === false ? "❌ " . $db->error : "✅ OK"; }
        $db->query("SET FOREIGN_KEY_CHECKS = 1");
        echo '<pre>Migration v20 (Approval Costs) Results:<br>' . implode('<br>', $results) . '</pre>';
        break;

    case 'migrate_v19':
        if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') requireAdmin();
        $filePath = __DIR__ . '/sql/migrate_v19_in_situ_fsi_cap.sql';
        if (!file_exists($filePath)) { echo 'Migration file missing'; exit; }
        $sql = file_get_contents($filePath);
        try { $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); if ($db->connect_error) throw new Exception("Connection failed"); }
        catch (Throwable $e) { exit('DB Error: ' . $e->getMessage()); }
        $db->set_charset(DB_CHARSET);
        $stripped = implode("\n", array_filter(explode("\n", $sql), fn($l) => !preg_match('/^\s*--/', $l)));
        $lines = array_filter(array_map('trim', explode(';', $stripped))); $results = [];
        foreach ($lines as $s) { if (!$s) continue; $results[] = $db->query($s) === false ? "❌ " . $db->error : "✅ OK"; }
        echo '<pre>Migration v19 (in_situ_fsi_cap) Results:<br>' . implode('<br>', $results) . '</pre>';
        break;

    case 'migrate_v18':
        if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') requireAdmin();
        $filePath = __DIR__ . '/sql/migrate_v18_wizard_v2.sql';
        if (!file_exists($filePath)) { echo 'Migration file missing'; exit; }
        $sql = file_get_contents($filePath);
        try { $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); if ($db->connect_error) throw new Exception("Connection failed"); }
        catch (Throwable $e) { exit('DB Error: ' . $e->getMessage()); }
        $db->set_charset(DB_CHARSET);
        $stripped = implode("\n", array_filter(explode("\n", $sql), fn($l) => !preg_match('/^\s*--/', $l)));
        $lines = array_filter(array_map('trim', explode(';', $stripped))); $results = [];
        foreach ($lines as $s) { if (!$s) continue; $results[] = $db->query($s) === false ? "❌ " . $db->error : "✅ OK"; }
        echo '<pre>Migration v18 (Wizard v2 opt-in) Results:<br>' . implode('<br>', $results) . '</pre>';
        break;

    case 'migrate_v17':
        if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') requireAdmin();
        $filePath = __DIR__ . '/sql/migrate_v17_calc_overrides.sql';
        if (!file_exists($filePath)) { echo 'Migration file missing'; exit; }
        $sql = file_get_contents($filePath);
        try { $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); if ($db->connect_error) throw new Exception("Connection failed"); }
        catch (Throwable $e) { exit('DB Error: ' . $e->getMessage()); }
        $db->set_charset(DB_CHARSET);
        $stripped = implode("\n", array_filter(explode("\n", $sql), fn($l) => !preg_match('/^\s*--/', $l)));
        $lines = array_filter(array_map('trim', explode(';', $stripped))); $results = [];
        foreach ($lines as $s) { if (!$s) continue; $results[] = $db->query($s) === false ? "❌ " . $db->error : "✅ OK"; }
        echo '<pre>Migration v17 (Calc Overrides) Results:<br>' . implode('<br>', $results) . '</pre>';
        break;

    case 'migrate_v16':
        if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') requireAdmin();
        $filePath = __DIR__ . '/sql/migrate_v16_formula_discrepancies.sql';
        if (!file_exists($filePath)) { echo 'Migration file missing'; exit; }
        $sql = file_get_contents($filePath);
        try { $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); if ($db->connect_error) throw new Exception("Connection failed"); }
        catch (Throwable $e) { exit('DB Error: ' . $e->getMessage()); }
        $db->set_charset(DB_CHARSET);
        $stripped = implode("\n", array_filter(explode("\n", $sql), fn($l) => !preg_match('/^\s*--/', $l)));
        $lines = array_filter(array_map('trim', explode(';', $stripped))); $results = [];
        foreach ($lines as $s) { if (!$s) continue; $results[] = $db->query($s) === false ? "❌ " . $db->error : "✅ OK"; }
        echo '<pre>Migration v16 (Formula Discrepancies) Results:<br>' . implode('<br>', $results) . '</pre>';
        break;

    case 'migrate_v15':
        if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') requireAdmin();
        $filePath = __DIR__ . '/sql/migrate_v15_regulatory_constants.sql';
        if (!file_exists($filePath)) { echo 'Migration file missing'; exit; }
        $sql = file_get_contents($filePath);
        try { $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); if ($db->connect_error) throw new Exception("Connection failed"); }
        catch (Throwable $e) { exit('DB Error: ' . $e->getMessage()); }
        $db->set_charset(DB_CHARSET);
        $stripped = implode("\n", array_filter(explode("\n", $sql), fn($l) => !preg_match('/^\s*--/', $l)));
        $lines = array_filter(array_map('trim', explode(';', $stripped))); $results = [];
        foreach ($lines as $s) { if (!$s) continue; $results[] = $db->query($s) === false ? "❌ " . $db->error : "✅ OK"; }
        echo '<pre>Migration v15 (Regulatory Constants) Results:<br>' . implode('<br>', $results) . '</pre>';
        break;

    case 'migrate_v14':
        if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') requireAdmin();
        $filePath = __DIR__ . '/sql/migrate_v14_line_items.sql';
        if (!file_exists($filePath)) { echo 'Migration file missing'; exit; }
        $sql = file_get_contents($filePath);
        try { $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); if ($db->connect_error) throw new Exception("Connection failed"); }
        catch (Throwable $e) { exit('DB Error: ' . $e->getMessage()); }
        $db->set_charset(DB_CHARSET);
        $stripped = implode("\n", array_filter(explode("\n", $sql), fn($l) => !preg_match('/^\s*--/', $l)));
        $lines = array_filter(array_map('trim', explode(';', $stripped))); $results = [];
        foreach ($lines as $s) { if (!$s) continue; $results[] = $db->query($s) === false ? "❌ " . $db->error : "✅ OK"; }
        echo '<pre>Migration v14 (Line Items) Results:<br>' . implode('<br>', $results) . '</pre>';
        break;

    case 'migrate_v13':
        if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') requireAdmin();
        $filePath = __DIR__ . '/sql/migrate_v13_feasibility_details.sql';
        if (!file_exists($filePath)) { echo 'Migration file missing'; exit; }
        $sql = file_get_contents($filePath);
        try { $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); if ($db->connect_error) throw new Exception("Connection failed"); }
        catch (Throwable $e) { exit('DB Error: ' . $e->getMessage()); }
        $db->set_charset(DB_CHARSET);
        $stripped = implode("\n", array_filter(explode("\n", $sql), fn($l) => !preg_match('/^\s*--/', $l)));
        $lines = array_filter(array_map('trim', explode(';', $stripped))); $results = [];
        foreach ($lines as $s) { if (!$s) continue; $results[] = $db->query($s) === false ? "❌ " . $db->error : "✅ OK"; }
        echo '<pre>Migration v13 (Feasibility details) Results:<br>' . implode('<br>', $results) . '</pre>';
        break;

    case 'migrate_v12':
        if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') requireAdmin();
        $filePath = __DIR__ . '/sql/migrate_v12_area_financials.sql';
        if (!file_exists($filePath)) { echo 'Migration file missing'; exit; }
        $sql = file_get_contents($filePath);
        try { $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); if ($db->connect_error) throw new Exception("Connection failed"); }
        catch (Throwable $e) { exit('DB Error: ' . $e->getMessage()); }
        $db->set_charset(DB_CHARSET); $db->query("SET FOREIGN_KEY_CHECKS = 0");
        $stripped = implode("\n", array_filter(explode("\n", $sql), fn($l) => !preg_match('/^\s*--/', $l)));
        $lines = array_filter(array_map('trim', explode(';', $stripped))); $results = [];
        foreach ($lines as $s) { if (!$s) continue; $results[] = $db->query($s) === false ? "❌ " . $db->error : "✅ OK"; }
        $db->query("SET FOREIGN_KEY_CHECKS = 1");
        echo '<pre>Migration v12 (Area Financials) Results:<br>' . implode('<br>', $results) . '</pre>';
        break;

    case 'migrate_v11':
        if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') requireAdmin();
        $filePath = __DIR__ . '/sql/migrate_v11_area_analysis.sql';
        if (!file_exists($filePath)) { echo 'Migration file missing'; exit; }
        $sql = file_get_contents($filePath);
        try { $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); if ($db->connect_error) throw new Exception("Connection failed"); }
        catch (Throwable $e) { exit('DB Error: ' . $e->getMessage()); }
        $db->set_charset(DB_CHARSET); $db->query("SET FOREIGN_KEY_CHECKS = 0");
        // Strip whole-line -- comments before splitting (keeps inline COMMENT '...' intact).
        $stripped = implode("\n", array_filter(
            explode("\n", $sql),
            fn($l) => !preg_match('/^\s*--/', $l)
        ));
        $lines = array_filter(array_map('trim', explode(';', $stripped)));
        $results = [];
        foreach ($lines as $s) {
            if (!$s) continue;
            $results[] = $db->query($s) === false ? "❌ " . $db->error : "✅ OK";
        }
        $db->query("SET FOREIGN_KEY_CHECKS = 1");
        echo '<pre>Migration v11 (Area Analysis) Results:<br>' . implode('<br>', $results) . '</pre>';
        break;

    case 'migrate_v10':
        if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') requireAdmin();
        $filePath = __DIR__ . '/sql/migrate_v10_financials.sql';
        if (!file_exists($filePath)) { echo 'Migration file missing'; exit; }
        $sql = file_get_contents($filePath);
        try { $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); if ($db->connect_error) throw new Exception("Connection failed"); }
        catch (Throwable $e) { exit('DB Error: ' . $e->getMessage()); }
        $db->set_charset(DB_CHARSET);
        $lines = array_filter(array_map('trim', explode(';', $sql)));
        $results = [];
        foreach ($lines as $s) {
            $t = ltrim($s);
            if (!$s || substr($t, 0, 2) === '--' || substr($t, 0, 1) === '#') continue;
            $results[] = $db->query($s) === false ? "❌ " . $db->error : "✅ OK";
        }
        echo '<pre>Migration v10 Results:<br>' . implode('<br>', $results) . '</pre>';
        break;

    // Migrate v9: DA documents/extractions
    case 'migrate_v9':
        if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') requireAdmin();
        $filePath = __DIR__ . '/sql/migrate_v9_documents.sql';
        if (!file_exists($filePath)) { echo 'Migration file missing: /sql/migrate_v9_documents.sql'; exit; }
        $sql = file_get_contents($filePath);
        try { $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); if ($db->connect_error) throw new Exception("Connection failed"); }
        catch (Throwable $e) { exit('DB Error: ' . $e->getMessage()); }
        $db->set_charset(DB_CHARSET); $db->query("SET FOREIGN_KEY_CHECKS = 0");
        $lines = array_filter(array_map('trim', explode(';', $sql))); $results = [];
        foreach ($lines as $s) {
            $trimmed = ltrim($s);
            if (!$s || substr($trimmed, 0, 2) === '--' || substr($trimmed, 0, 1) === '#') continue;
            $results[] = $db->query($s) === false ? "❌ " . $db->error : "✅ OK";
        }
        $db->query("SET FOREIGN_KEY_CHECKS = 1");
        echo '<pre>Migration v9 (DA/Documents) Results:<br>' . implode('<br>', $results) . '</pre>';
        break;

    // ── Approval Costs ────────────────────────────────────────────────────
    case 'approval_costs':
        requireLogin();
        require_once __DIR__ . '/controllers/ApprovalCostsController.php';
        ApprovalCostsController::index();
        break;
    case 'approval_costs_register':
        requireLogin();
        require_once __DIR__ . '/controllers/ApprovalCostsController.php';
        ApprovalCostsController::register();
        break;
    case 'approval_costs_add':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/ApprovalCostsController.php';
        ApprovalCostsController::addToRegister();
        break;
    case 'approval_costs_add_custom':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/ApprovalCostsController.php';
        ApprovalCostsController::addCustom();
        break;
    case 'approval_costs_detail':
        requireLogin();
        require_once __DIR__ . '/controllers/ApprovalCostsController.php';
        ApprovalCostsController::detail();
        break;
    case 'approval_costs_update':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/ApprovalCostsController.php';
        ApprovalCostsController::updateApproval();
        break;
    case 'approval_costs_delete':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/ApprovalCostsController.php';
        ApprovalCostsController::deleteApproval();
        break;
    case 'approval_costs_master_library':
        requireLogin();
        require_once __DIR__ . '/controllers/ApprovalCostsController.php';
        ApprovalCostsController::masterLibrary();
        break;
    case 'approval_costs_applicability':
        requireLogin();
        require_once __DIR__ . '/controllers/ApprovalCostsController.php';
        ApprovalCostsController::applicability();
        break;
    case 'approval_costs_apply_suggestions':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/ApprovalCostsController.php';
        ApprovalCostsController::applySuggestions();
        break;
    case 'approval_costs_calculate':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/ApprovalCostsController.php';
        ApprovalCostsController::calculateCosts();
        break;
    case 'approval_costs_tracker':
        requireLogin();
        require_once __DIR__ . '/controllers/ApprovalCostsController.php';
        ApprovalCostsController::tracker();
        break;
    case 'approval_costs_update_timeline':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/ApprovalCostsController.php';
        ApprovalCostsController::updateTimeline();
        break;
    case 'approval_costs_add_followup':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/ApprovalCostsController.php';
        ApprovalCostsController::addFollowup();
        break;
    case 'approval_costs_set_input':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/ApprovalCostsController.php';
        ApprovalCostsController::setProjectInput();
        break;
    case 'seed_applicability_rules':
        if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') requireAdmin();
        require_once __DIR__ . '/controllers/ApprovalCostsController.php';
        ApprovalCostsController::seedApplicabilityRules();
        break;

    // ── Area Analysis v2 (opt-in redesigned wizard — Stage 1 pilot) ───────
    case 'area_analysis_v2':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisV2Controller.php';
        AreaAnalysisV2Controller::index();
        break;
    case 'area_analysis_v2_start':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/AreaAnalysisV2Controller.php';
        AreaAnalysisV2Controller::start();
        break;
    case 'area_analysis_v2_stage1':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisV2Controller.php';
        AreaAnalysisV2Controller::stage1();
        break;
    case 'area_analysis_v2_save_stage1':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/AreaAnalysisV2Controller.php';
        AreaAnalysisV2Controller::saveStage1();
        break;
    case 'area_analysis_v2_stage2':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisV2Controller.php';
        AreaAnalysisV2Controller::stage2();
        break;
    case 'area_analysis_v2_save_stage2':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/AreaAnalysisV2Controller.php';
        AreaAnalysisV2Controller::saveStage2();
        break;
    case 'area_analysis_v2_stage3':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisV2Controller.php';
        AreaAnalysisV2Controller::stage3();
        break;
    case 'area_analysis_v2_save_stage3':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/AreaAnalysisV2Controller.php';
        AreaAnalysisV2Controller::saveStage3();
        break;
    case 'area_analysis_v2_stage4':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisV2Controller.php';
        AreaAnalysisV2Controller::stage4();
        break;
    case 'area_analysis_v2_generate':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/AreaAnalysisV2Controller.php';
        AreaAnalysisV2Controller::generate();
        break;
    case 'area_analysis_v2_stage5':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisV2Controller.php';
        AreaAnalysisV2Controller::stage5();
        break;

    // ── Post-Proposal Area Analysis (new wizard) ──────────────────────────
    case 'area_analysis':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::index();
        break;
    case 'area_analysis_start':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::start();
        break;
    case 'area_analysis_step':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::step();
        break;
    case 'area_analysis_save_step':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::saveStep();
        break;
    case 'area_analysis_review':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::review();
        break;
    case 'area_analysis_generate':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::generate();
        break;
    case 'area_analysis_calculate':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::calculate();
        break;
    case 'area_analysis_refresh_calc':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::refreshCalc();
        break;
    case 'area_analysis_report':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::report();
        break;
    case 'area_analysis_pdf_report':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::pdfReport();
        break;
    case 'area_analysis_dashboard':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::dashboard();
        break;
    case 'area_analysis_sensitivity':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::sensitivity();
        break;
    case 'area_analysis_scenarios':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::scenarios();
        break;
    case 'api_calc_explain':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::apiCalcExplain();
        break;
    case 'area_analysis_feasibility':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::feasibilityStatement();
        break;
    case 'area_analysis_discrepancies':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::discrepancies();
        break;
    case 'area_analysis_best_outcome':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::bestOutcome();
        break;

    case 'api_area_assistant':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::apiAssistant();
        break;

    case 'area_analysis_financials_list':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::financialsList();
        break;

    case 'area_analysis_overrides':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::overrides();
        break;
    case 'area_analysis_apply_override':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::applyOverride();
        break;
    case 'area_analysis_deactivate_override':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::deactivateOverride();
        break;
    case 'area_analysis_discrepancy_resolve':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::resolveDiscrepancy();
        break;
    case 'area_analysis_discrepancy_rescan':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::rescanDiscrepancies();
        break;
    case 'area_analysis_feasibility_excel':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::feasibilityExcel();
        break;
    case 'area_analysis_export':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::exportExcel();
        break;
    case 'area_analysis_new_version':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::newVersion();
        break;
    case 'area_analysis_compare':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::compareVersions();
        break;
    case 'area_analysis_financials_form':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::financialsForm();
        break;
    case 'area_analysis_financials_save':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::financialsSave();
        break;
    case 'area_analysis_financials_view':
        requireLogin();
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::financialsView();
        break;
    case 'area_analysis_delete':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/AreaAnalysisController.php';
        AreaAnalysisController::delete();
        break;

    // ── Post-Proposal Development Planning ────────────────────────────────
    case 'planning':
        requireLogin();
        require_once __DIR__ . '/controllers/DevelopmentPlanningController.php';
        DevelopmentPlanningController::index();
        break;

    case 'planning_start':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/DevelopmentPlanningController.php';
        DevelopmentPlanningController::start();
        break;

    case 'planning_create':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/DevelopmentPlanningController.php';
        DevelopmentPlanningController::create();
        break;

    case 'planning_form':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/DevelopmentPlanningController.php';
        DevelopmentPlanningController::form();
        break;

    case 'planning_save':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/DevelopmentPlanningController.php';
        DevelopmentPlanningController::save();
        break;

    case 'planning_view':
        requireLogin();
        require_once __DIR__ . '/controllers/DevelopmentPlanningController.php';
        DevelopmentPlanningController::view();
        break;

    case 'planning_report':
        requireLogin();
        require_once __DIR__ . '/controllers/DevelopmentPlanningController.php';
        DevelopmentPlanningController::report();
        break;

    case 'planning_clone':
        requirePermission('edit_proposals');
        require_once __DIR__ . '/controllers/DevelopmentPlanningController.php';
        DevelopmentPlanningController::cloneScenario();
        break;

    case 'planning_compare':
        requireLogin();
        require_once __DIR__ . '/controllers/DevelopmentPlanningController.php';
        DevelopmentPlanningController::compare();
        break;

    case 'api_planning_assistant':
        requireLogin();
        require_once __DIR__ . '/controllers/DevelopmentPlanningController.php';
        DevelopmentPlanningController::apiAssistant();
        break;

    // Migrate v7: create planning tables
    case 'migrate_v7':
        if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
            requireAdmin();
        }
        $filePath = __DIR__ . '/sql/migrate_v7_planning.sql';
        if (!file_exists($filePath)) { echo 'Migration file missing: /sql/migrate_v7_planning.sql'; exit; }
        $sql = file_get_contents($filePath);
        try {
            $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($db->connect_error) throw new Exception("Connection failed");
        } catch (Throwable $e) { exit('DB Error: ' . $e->getMessage()); }
        $db->set_charset(DB_CHARSET);
        $db->query("SET FOREIGN_KEY_CHECKS = 0");
        $lines = array_filter(array_map('trim', explode(';', $sql)));
        $results = [];
        foreach ($lines as $s) {
            $trimmed = ltrim($s);
            if (!$s || substr($trimmed, 0, 2) === '--' || substr($trimmed, 0, 1) === '#') continue;
            if ($db->query($s) === false) {
                $results[] = "❌ ERROR: " . $db->error . " (" . htmlspecialchars($s) . ")";
            } else {
                $results[] = "✅ OK";
            }
        }
        $db->query("SET FOREIGN_KEY_CHECKS = 1");
        echo '<pre style="font-family:monospace;padding:2rem;line-height:2"><b>Migration v7 (Planning) Results:</b><br>' . implode('<br>', $results) . '<br><br>Done. <a href="index.php?page=planning">→ Go to Planning</a></pre>';
        break;

    // ── DCPR Rule Base ────────────────────────────────────────────────────
    case 'dcpr':
        requireLogin();
        require_once __DIR__ . '/controllers/DCPRController.php';
        DCPRController::index();
        break;

    case 'dcpr_rule':
        requireLogin();
        require_once __DIR__ . '/controllers/DCPRController.php';
        DCPRController::detail();
        break;

    case 'dcpr_reload':
        requirePermission('manage_weights');
        require_once __DIR__ . '/controllers/DCPRController.php';
        DCPRController::reload();
        break;

    case 'regulatory_rules':
        requireLogin();
        require_once __DIR__ . '/controllers/DCPRController.php';
        DCPRController::regulatoryRules();
        break;

    case 'regulatory_rule_update':
        requirePermission('manage_weights');
        require_once __DIR__ . '/controllers/DCPRController.php';
        DCPRController::regulatoryRuleUpdate();
        break;

    case 'regulatory_rule_audit':
        requireLogin();
        require_once __DIR__ . '/controllers/DCPRController.php';
        DCPRController::regulatoryRuleAudit();
        break;

    // Migrate: run migrate_v6_dcpr.sql (creates dcpr_rules table)
    case 'migrate_v6':
        if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
            requireAdmin();
        }
        $filePath = __DIR__ . '/sql/migrate_v6_dcpr.sql';
        if (!file_exists($filePath)) { echo 'Migration file missing: /sql/migrate_v6_dcpr.sql'; exit; }
        $sql = file_get_contents($filePath);
        try {
            $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($db->connect_error) throw new Exception("Connection failed");
        } catch (Throwable $e) { exit('DB Error: ' . $e->getMessage()); }
        $db->set_charset(DB_CHARSET);
        $db->query("SET FOREIGN_KEY_CHECKS = 0");
        $lines = array_filter(array_map('trim', explode(';', $sql)));
        $results = [];
        foreach ($lines as $s) {
            $trimmed = ltrim($s);
            if (!$s || substr($trimmed, 0, 2) === '--' || substr($trimmed, 0, 1) === '#') continue;
            if ($db->query($s) === false) {
                $results[] = "❌ ERROR: " . $db->error . " (" . htmlspecialchars($s) . ")";
            } else {
                $results[] = "✅ OK";
            }
        }
        $db->query("SET FOREIGN_KEY_CHECKS = 1");
        echo '<pre style="font-family:monospace;padding:2rem;line-height:2"><b>Migration v6 (DCPR rules) Results:</b><br>' . implode('<br>', $results) . '<br><br>Done. <a href="index.php?page=dcpr_reload">→ Now load JSON rules</a></pre>';
        break;

    // Migrate: run migrate_v5.sql
    case 'migrate_v5':
        if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
            requireAdmin();
        }
        $filePath = __DIR__ . '/sql/migrate_v5.sql';
        if (!file_exists($filePath)) {
            echo 'Migration file missing: /sql/migrate_v5.sql';
            exit;
        }

        $sql = file_get_contents($filePath);
        try {
            $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($db->connect_error) throw new Exception("Connection failed");
        } catch (Throwable $e) {
            exit('DB Error: ' . $e->getMessage());
        }

        $db->set_charset(DB_CHARSET);
        $db->query("SET FOREIGN_KEY_CHECKS = 0");
        $lines = array_filter(array_map('trim', explode(';', $sql)));
        $results = [];
        foreach ($lines as $s) {
            $trimmed = ltrim($s);
            if (!$s || substr($trimmed, 0, 2) === '--' || substr($trimmed, 0, 1) === '#') continue;
            if ($db->query($s) === false) {
                $results[] = "❌ ERROR: " . $db->error . " (" . htmlspecialchars($s) . ")";
            } else {
                $results[] = "✅ OK";
            }
        }
        $db->query("SET FOREIGN_KEY_CHECKS = 1");
        echo '<pre><b>Migration v5 Results:</b><br>' . implode('<br>', $results) . '<br><br>Done. <a href="index.php?page=dashboard">Go back</a></pre>';
        break;

    // Migrate: run migrate_v4.sql
    case 'migrate_v4':
        if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
            requireAdmin();
        }
        $filePath = __DIR__ . '/sql/migrate_v4.sql';
        if (!file_exists($filePath)) {
            echo 'Migration file missing: /sql/migrate_v4.sql';
            exit;
        }

        $sql = file_get_contents($filePath);
        try {
            $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($db->connect_error) throw new Exception("Connection failed");
        } catch (Throwable $e) {
            exit('DB Error: ' . $e->getMessage());
        }

        $db->set_charset(DB_CHARSET);
        $db->query("SET FOREIGN_KEY_CHECKS = 0");
        $lines = array_filter(array_map('trim', explode(';', $sql)));
        $results = [];
        foreach ($lines as $s) {
            $trimmed = ltrim($s);
            if (!$s || substr($trimmed, 0, 2) === '--' || substr($trimmed, 0, 1) === '#') continue;
            if ($db->query($s) === false) {
                $results[] = "❌ ERROR: " . $db->error . " (" . htmlspecialchars($s) . ")";
            } else {
                $results[] = "✅ OK";
            }
        }
        $db->query("SET FOREIGN_KEY_CHECKS = 1");
        echo '<pre><b>Migration v4 Results:</b><br>' . implode('<br>', $results) . '<br><br>Done. <a href="index.php?page=dashboard">Go back</a></pre>';
        break;

    // Migrate: run migrate_v3.sql (localhost only for safety)
    case 'migrate_v3':
        if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
            // In a real app we'd block this, but since we are executing on the live server via the user, we should allow it if they are logged in as admin.
            requireAdmin();
        }
        $filePath = __DIR__ . '/sql/migrate_v3.sql';
        if (!file_exists($filePath)) {
            echo 'Migration file missing: /sql/migrate_v3.sql';
            exit;
        }

        $sql = file_get_contents($filePath);
        try {
            $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($db->connect_error) throw new Exception("Connection failed");
        } catch (Throwable $e) {
            exit('DB Error: ' . $e->getMessage());
        }

        $db->set_charset(DB_CHARSET);
        $db->query("SET FOREIGN_KEY_CHECKS = 0");
        $lines = array_filter(array_map('trim', explode(';', $sql)));
        $results = [];
        foreach ($lines as $s) {
            $trimmed = ltrim($s);
            if (!$s || substr($trimmed, 0, 2) === '--' || substr($trimmed, 0, 1) === '#') continue;
            if ($db->query($s) === false) {
                $results[] = "❌ ERROR: " . $db->error . " (" . htmlspecialchars($s) . ")";
            } else {
                $results[] = "✅ OK";
            }
        }
        $db->query("SET FOREIGN_KEY_CHECKS = 1");
        echo '<pre><b>Migration v3 Results:</b><br>' . implode('<br>', $results) . '<br><br>Done. <a href="index.php?page=dashboard">Go back</a></pre>';
        break;

    // Migrate: run migrate_v2.sql (localhost only for safety)
    case 'migrate':
        if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
            http_response_code(403); exit('Forbidden');
        }
        $filePath = __DIR__ . '/sql/migrate_v2.sql';
        if (!file_exists($filePath)) {
            echo '<pre style="font-family:sans-serif;padding:3rem;line-height:1.6;color:#721c24;background:#f8d7da;border:1px solid #f5c6cb;border-radius:8px;max-width:600px;margin:2rem auto">';
            echo '<h4 style="margin-top:0">⚠️ Migration File Missing</h4>';
            echo 'The migration file is missing on the server:<br>';
            echo '<code>/sql/migrate_v2.sql</code><br><br>';
            echo 'Please upload this file using FileZilla and try again.';
            echo '</pre>';
            exit;
        }

        $sql = file_get_contents($filePath);
        
        // Connect to database safely to catch errors without triggering a 500 error code
        try {
            $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($db->connect_error) {
                throw new Exception("Database connection failed: " . $db->connect_error);
            }
        } catch (Throwable $e) {
            echo '<pre style="font-family:sans-serif;padding:3rem;line-height:1.6;color:#721c24;background:#f8d7da;border:1px solid #f5c6cb;border-radius:8px;max-width:600px;margin:2rem auto">';
            echo '<h4 style="margin-top:0">❌ Database Connection Error</h4>';
            echo 'Could not connect to the database:<br>';
            echo '<code>' . htmlspecialchars($e->getMessage()) . '</code><br><br>';
            echo 'Please check your <b>config/database.php</b> file settings.';
            echo '</pre>';
            exit;
        }

        $db->set_charset(DB_CHARSET);
        $db->query("SET FOREIGN_KEY_CHECKS = 0");
        $lines = array_filter(array_map('trim', explode(';', $sql)));
        $results = [];
        foreach ($lines as $s) {
            $trimmed = ltrim($s);
            if (!$s || substr($trimmed, 0, 2) === '--' || substr($trimmed, 0, 1) === '#') continue;
            if ($db->query($s) === false) {
                $results[] = "❌ ERROR: " . $db->error . "<br><code>" . htmlspecialchars($s) . "</code>";
            } else {
                $results[] = "✅ OK";
            }
        }
        $db->query("SET FOREIGN_KEY_CHECKS = 1");
        echo '<pre style="font-family:monospace;padding:2rem;line-height:2">';
        echo "<b>Migration v2 Results:</b>\n\n";
        echo implode("\n", $results);
        echo "\n\n<b>Done.</b> <a href='index.php?page=login'>→ Go to Login</a>";
        echo '</pre>';
        break;

    // Setup: run the SQL schema (visit once, then remove access)
    case 'setup':
        if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
            http_response_code(403); exit('Forbidden');
        }
        $sql = file_get_contents(__DIR__ . '/sql/setup.sql');
        $db  = getDB();
        // Execute statement by statement
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt) $db->query($stmt);
        }
        echo '<pre style="font-family:monospace;padding:2rem">';
        echo "✅ Database setup complete.\n";
        echo "Tables created: proposals, stage1_results, stage2_scores, weights_config, import_logs\n";
        echo "Default weights config seeded.\n\n";
        echo '<a href="index.php">→ Go to Dashboard</a>';
        echo '</pre>';
        break;

    default:
        http_response_code(404);
        require_once __DIR__ . '/config/constants.php';
        echo '<div style="font-family:sans-serif;padding:3rem;text-align:center">';
        echo '<h2>404 – Page not found</h2>';
        echo '<a href="index.php">← Dashboard</a>';
        echo '</div>';
}
