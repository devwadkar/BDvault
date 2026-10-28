<?php

require_once __DIR__ . '/../lib/DCPRRuleLoader.php';
require_once __DIR__ . '/../lib/RulesRegistry.php';

class DCPRController {

    /**
     * GET /?page=dcpr — Rule browser.
     */
    public static function index(): void {
        $rules = DCPRRuleLoader::fetchAll();
        require __DIR__ . '/../views/dcpr/rules.php';
    }

    /**
     * GET /?page=dcpr_rule&key=33(10) — Single rule detail.
     */
    public static function detail(): void {
        $key = $_GET['key'] ?? '';
        $rule = $key !== '' ? DCPRRuleLoader::fetchByKey($key) : null;
        if (!$rule) {
            http_response_code(404);
            flashSet('warning', 'Regulation not found: ' . htmlspecialchars($key));
            redirect('index.php?page=dcpr');
            return;
        }
        require __DIR__ . '/../views/dcpr/detail.php';
    }

    /**
     * GET /?page=dcpr_reload — Reload every JSON file into the dcpr_rules table.
     * Gated by manage_weights permission.
     */
    public static function reload(): void {
        $results = DCPRRuleLoader::loadAll();
        require __DIR__ . '/../views/dcpr/reload.php';
    }

    /**
     * GET /?page=regulatory_rules — Admin view of every regulatory constant.
     * Constants are grouped by scope with inline edit + audit history.
     */
    public static function regulatoryRules(): void {
        $scope = $_GET['scope'] ?? null;
        $rows  = RulesRegistry::getAll($scope);
        $scopes = RulesRegistry::scopes();

        // Group by scope for the view
        $byScope = [];
        foreach ($rows as $r) {
            $byScope[$r['scope']][] = $r;
        }
        require __DIR__ . '/../views/dcpr/regulatory_rules.php';
    }

    /**
     * POST /?page=regulatory_rule_update — Update a constant + write audit row.
     * Expects: id, new_value, change_reason.
     */
    public static function regulatoryRuleUpdate(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('index.php?page=regulatory_rules');
            return;
        }
        $id      = (int)($_POST['id'] ?? 0);
        $newVal  = isset($_POST['new_value']) ? (float)$_POST['new_value'] : null;
        $reason  = trim($_POST['change_reason'] ?? '') ?: null;
        $userId  = $_SESSION['user_id'] ?? null;

        if ($id <= 0 || $newVal === null) {
            flashSet('error', 'Invalid constant id or value.');
            redirect('index.php?page=regulatory_rules');
            return;
        }

        $ok = RulesRegistry::update($id, $newVal, $reason, $userId);
        if ($ok) {
            flashSet('success', "Constant updated. Audit row written.");
        } else {
            flashSet('error', 'Update failed — constant not found?');
        }
        redirect('index.php?page=regulatory_rules');
    }

    /**
     * GET /?page=regulatory_rule_audit&id=N — Audit trail for one constant.
     */
    public static function regulatoryRuleAudit(): void {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { redirect('index.php?page=regulatory_rules'); return; }
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM regulatory_constants WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $constant = $stmt->get_result()->fetch_assoc();
        if (!$constant) { redirect('index.php?page=regulatory_rules'); return; }
        $history = RulesRegistry::audit($id, 200);
        require __DIR__ . '/../views/dcpr/regulatory_rule_audit.php';
    }
}
