-- Migration v16: formula discrepancy detection
--
-- FormulaDiscrepancyDetector runs after every AreaCalcEngine::run() and cross-checks
-- calculator outputs against independent derivations of the same quantity (e.g.
-- Rehab Component ?= Rehab Tenement BUA + Amenity Block). Any mismatch beyond the
-- per-check tolerance is written here with a severity chip. Users can resolve or
-- annotate rows; the UI surfaces unresolved discrepancies in the Feasibility view
-- and Area Statement report.

CREATE TABLE IF NOT EXISTS formula_discrepancies (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    statement_id      INT           NOT NULL,
    check_key         VARCHAR(80)   NOT NULL COMMENT 'e.g. rehab_component_identity, fsi_bua_ratio',
    check_label       VARCHAR(200)  NOT NULL,
    category          VARCHAR(24)   NOT NULL DEFAULT 'cross_check' COMMENT 'cross_check | reported_vs_calc | da_extract | user_override',
    severity          ENUM('info','warning','error') NOT NULL DEFAULT 'warning',

    expected_value    DECIMAL(20,4) NULL COMMENT 'What the identity/independent derivation says',
    actual_value      DECIMAL(20,4) NULL COMMENT 'What the calculator emitted',
    variance_abs      DECIMAL(20,4) NULL COMMENT '|expected - actual|',
    variance_pct      DECIMAL(10,4) NULL COMMENT '100 * |expected - actual| / max(|expected|, 1)',
    tolerance_pct     DECIMAL(10,4) NOT NULL DEFAULT 0.01 COMMENT 'What triggered the flag',

    formula           VARCHAR(255)  NULL,
    inputs_json       TEXT          NULL COMMENT 'Snapshot of what fed the two sides',
    regulation_ref    VARCHAR(96)   NULL,
    description       TEXT          NULL,

    resolved          TINYINT(1)    NOT NULL DEFAULT 0,
    resolved_by       INT           NULL,
    resolved_at       TIMESTAMP     NULL,
    resolution_notes  TEXT          NULL,

    created_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_statement       (statement_id, resolved),
    INDEX idx_severity        (severity, resolved),
    INDEX idx_check_key       (check_key),
    FOREIGN KEY (statement_id) REFERENCES area_statements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
