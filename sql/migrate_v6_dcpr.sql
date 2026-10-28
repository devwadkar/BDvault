-- Migration v6: DCPR Advisory Engine — Rule base table
-- Stores structured DCPR 2034 regulations for the Compliance Checker + FSI Optimizer + AI Advisory layer.
-- One row per regulation key + version. Rule content is a JSON blob loaded from dcpr_rules/*.json.

CREATE TABLE IF NOT EXISTS dcpr_rules (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    regulation_key    VARCHAR(32)  NOT NULL COMMENT 'e.g. 30, 31, 32, 33(10)',
    title             VARCHAR(255) NOT NULL,
    part              VARCHAR(16)  NULL COMMENT 'DCPR part (I..VIII)',
    source            VARCHAR(64)  NOT NULL DEFAULT 'DCPR 2034',
    effective_from    DATE         NULL,
    version           INT          NOT NULL DEFAULT 1,
    content           LONGTEXT     NOT NULL COMMENT 'JSON payload from dcpr_rules/*.json',
    source_ref_start  INT          NULL COMMENT 'Start line in source PDF extraction',
    source_ref_end    INT          NULL,
    file_path         VARCHAR(255) NULL COMMENT 'Relative path of source JSON file',
    loaded_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reg_version (regulation_key, version),
    KEY idx_reg (regulation_key),
    KEY idx_source (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
