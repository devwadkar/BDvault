-- Migration v17: Manual calc overrides + audit trail
--
-- Any calc_key emitted by the engine can be manually overridden. Overrides live
-- in area_statement_calc_overrides (one active row per statement/key). Every
-- activate/edit/deactivate is captured in _audit for permanent traceability.
--
-- Overrides are applied POST-compute (before persistCalcs). The override value
-- replaces calc_value; the original is preserved on the calc row itself via the
-- new is_overridden / original_value columns for inline badge rendering.
--
-- Downstream propagation: the engine's post-compute pass rewrites the calc
-- table only; formulas that consumed the ORIGINAL value in the calculator still
-- reference their original inputs. The view shows a "downstream may be stale"
-- warning when an override is in effect on a foundational value.

CREATE TABLE IF NOT EXISTS area_statement_calc_overrides (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    statement_id    INT           NOT NULL,
    calc_key        VARCHAR(80)   NOT NULL COMMENT 'e.g. net_plot_area_sqm, rehab_component_sqm',
    override_value  DECIMAL(20,4) NOT NULL,
    original_value  DECIMAL(20,4) NULL COMMENT 'What the calc emitted before override',
    reason          VARCHAR(500)  NOT NULL COMMENT 'Why the manager overrode this — required for audit',
    active          TINYINT(1)    NOT NULL DEFAULT 1,
    created_by      INT           NULL,
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- App enforces "exactly one active per (statement, calc_key)" — a UNIQUE would
    -- reject legitimate multiple-inactive-history rows for the same key.
    INDEX idx_active_lookup (statement_id, calc_key, active),
    INDEX idx_statement (statement_id, active),
    FOREIGN KEY (statement_id) REFERENCES area_statements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS area_statement_calc_overrides_audit (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    override_id       INT           NULL COMMENT 'FK to override row (NULL after cascade)',
    statement_id      INT           NOT NULL,
    calc_key          VARCHAR(80)   NOT NULL,
    action            ENUM('applied','edited','deactivated','reactivated') NOT NULL,
    old_value         DECIMAL(20,4) NULL,
    new_value         DECIMAL(20,4) NULL,
    reason            VARCHAR(500)  NULL,
    changed_by        INT           NULL,
    changed_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_statement       (statement_id, changed_at),
    INDEX idx_key             (calc_key, changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extend area_statement_calcs with override-tracking columns so views can render
-- inline badges without a join to the overrides table on every render.
ALTER TABLE area_statement_calcs
    ADD COLUMN IF NOT EXISTS is_overridden  TINYINT(1)    NOT NULL DEFAULT 0 AFTER calc_value,
    ADD COLUMN IF NOT EXISTS original_value DECIMAL(20,4) NULL           AFTER is_overridden,
    ADD COLUMN IF NOT EXISTS override_id    INT           NULL           AFTER original_value;
