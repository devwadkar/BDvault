<?php
/**
 * DCPRRuleLoader — reads dcpr_rules/*.json and upserts them into the dcpr_rules table.
 *
 * Rule files are structured JSON (see dcpr_rules/reg_30_fsi.json for schema).
 * Every file MUST have: regulation_key, title, version, content — the whole JSON blob is stored as `content`.
 * The loader is idempotent: (regulation_key, version) is unique — a reload replaces the row's content.
 */

require_once __DIR__ . '/../config/database.php';

class DCPRRuleLoader {

    private const RULES_DIR = __DIR__ . '/../dcpr_rules';

    /**
     * Load every JSON rule file into the dcpr_rules table.
     * Returns a per-file result array for the UI.
     */
    public static function loadAll(): array {
        $db = getDB();
        self::ensureTable($db);

        $results = [];
        $files = glob(self::RULES_DIR . '/*.json') ?: [];

        if (empty($files)) {
            return [['file' => '(none)', 'status' => 'error', 'message' => 'No JSON files in dcpr_rules/']];
        }

        foreach ($files as $file) {
            $results[] = self::loadOne($db, $file);
        }
        return $results;
    }

    /**
     * Load one JSON file. Public so a single file can be re-imported after edit.
     */
    public static function loadOne(mysqli $db, string $file): array {
        $basename = basename($file);
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return ['file' => $basename, 'status' => 'error', 'message' => 'Could not read file'];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return ['file' => $basename, 'status' => 'error', 'message' => 'Invalid JSON: ' . json_last_error_msg()];
        }

        $regKey  = $data['regulation_key'] ?? null;
        $title   = $data['title'] ?? null;
        $version = (int)($data['version'] ?? 1);
        if (!$regKey || !$title) {
            return ['file' => $basename, 'status' => 'error', 'message' => 'Missing regulation_key or title'];
        }

        $part          = $data['part'] ?? null;
        $source        = $data['source'] ?? 'DCPR 2034';
        $effectiveFrom = $data['effective_from'] ?? null;
        $refStart      = $data['source_pdf_lines']['start'] ?? null;
        $refEnd        = $data['source_pdf_lines']['end']   ?? null;
        $filePath      = 'dcpr_rules/' . $basename;
        $content       = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $sql = "INSERT INTO dcpr_rules
                  (regulation_key, title, part, source, effective_from, version, content, source_ref_start, source_ref_end, file_path)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                  title = VALUES(title),
                  part = VALUES(part),
                  source = VALUES(source),
                  effective_from = VALUES(effective_from),
                  content = VALUES(content),
                  source_ref_start = VALUES(source_ref_start),
                  source_ref_end = VALUES(source_ref_end),
                  file_path = VALUES(file_path),
                  loaded_at = CURRENT_TIMESTAMP";

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return ['file' => $basename, 'status' => 'error', 'message' => 'Prepare failed: ' . $db->error];
        }
        $stmt->bind_param(
            'sssssisiis',
            $regKey, $title, $part, $source, $effectiveFrom,
            $version, $content, $refStart, $refEnd, $filePath
        );
        if (!$stmt->execute()) {
            return ['file' => $basename, 'status' => 'error', 'message' => 'Execute failed: ' . $stmt->error];
        }

        return [
            'file'   => $basename,
            'status' => 'ok',
            'reg'    => $regKey,
            'title'  => $title,
        ];
    }

    /**
     * Fetch every rule as an array of ['regulation_key','title','part','effective_from','content'(decoded)].
     */
    public static function fetchAll(): array {
        $db = getDB();
        self::ensureTable($db);

        $res = $db->query("SELECT regulation_key, title, part, source, effective_from, version, content, file_path, loaded_at
                           FROM dcpr_rules
                           ORDER BY CASE part
                                     WHEN 'I' THEN 1 WHEN 'II' THEN 2 WHEN 'III' THEN 3
                                     WHEN 'IV' THEN 4 WHEN 'V' THEN 5 WHEN 'VI' THEN 6
                                     WHEN 'VII' THEN 7 WHEN 'VIII' THEN 8 ELSE 9 END,
                                    regulation_key");
        $rows = [];
        while ($res && $row = $res->fetch_assoc()) {
            $row['content'] = json_decode($row['content'], true);
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Fetch a single rule's decoded content by regulation_key (latest version).
     */
    public static function fetchByKey(string $regKey): ?array {
        $db = getDB();
        self::ensureTable($db);
        $stmt = $db->prepare("SELECT regulation_key, title, part, content, version
                              FROM dcpr_rules WHERE regulation_key = ?
                              ORDER BY version DESC LIMIT 1");
        if (!$stmt) return null;
        $stmt->bind_param('s', $regKey);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        if (!$row) return null;
        $row['content'] = json_decode($row['content'], true);
        return $row;
    }

    private static function ensureTable(mysqli $db): void {
        $sql = "CREATE TABLE IF NOT EXISTS dcpr_rules (
                    id                INT AUTO_INCREMENT PRIMARY KEY,
                    regulation_key    VARCHAR(32)  NOT NULL,
                    title             VARCHAR(255) NOT NULL,
                    part              VARCHAR(16)  NULL,
                    source            VARCHAR(64)  NOT NULL DEFAULT 'DCPR 2034',
                    effective_from    DATE         NULL,
                    version           INT          NOT NULL DEFAULT 1,
                    content           LONGTEXT     NOT NULL,
                    source_ref_start  INT          NULL,
                    source_ref_end    INT          NULL,
                    file_path         VARCHAR(255) NULL,
                    loaded_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_reg_version (regulation_key, version),
                    KEY idx_reg (regulation_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $db->query($sql);
    }
}
