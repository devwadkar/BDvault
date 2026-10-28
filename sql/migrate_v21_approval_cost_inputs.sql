-- Migration v21: Project inputs for cost calculation.
-- Some cost formulas need numbers that aren't derivable from Area Analysis
-- (e.g. tree count for Tree Authority NOC, borewell count for CGWA fee).
-- One key/value row per input per statement.
--
-- Usage: visit index.php?page=migrate_v21 (localhost only).

CREATE TABLE IF NOT EXISTS project_approval_input (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    statement_id  INT          NOT NULL,
    input_key     VARCHAR(64)  NOT NULL COMMENT 'e.g. tree_count, borewell_count',
    input_value   DECIMAL(20,4) NULL,
    notes         VARCHAR(255) NULL,
    updated_by    INT          NULL,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_stmt_key (statement_id, input_key),
    INDEX idx_stmt (statement_id),
    FOREIGN KEY (statement_id) REFERENCES area_statements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
