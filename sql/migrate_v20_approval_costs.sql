-- Migration v20: Approval Costs module (Stage 2 schema).
-- Introduces an approval master library + per-project register, with full source
-- provenance from the two intake workbooks. No existing columns dropped.
--
-- Usage: visit index.php?page=migrate_v20 (localhost only).

-- ─── Master library ─────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS approval_master (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    code                  VARCHAR(80)  NOT NULL UNIQUE COMMENT 'slug — stable programmatic key',
    standardized_name     VARCHAR(255) NOT NULL,
    record_type           ENUM('STATUTORY','NOC','LAND_RECORD','TECH_REPORT','MILESTONE',
                               'REGISTRATION','UTILITY','OTHER') NOT NULL DEFAULT 'OTHER',
    category_key          VARCHAR(64)  NOT NULL COMMENT 'land_title, planning_dp, municipal, environmental, fire, location_noc, demolition, construction_inspect, completion_occupancy, registrations, technical_reports, utilities, requires_classification',
    typical_phase         ENUM('land_acquisition','pre_construction','during_construction','post_construction') NOT NULL DEFAULT 'pre_construction',
    description           TEXT         NULL,
    authority_hint        VARCHAR(128) NULL COMMENT 'free-text until approval_authority seeded',
    jurisdiction          VARCHAR(32)  NOT NULL DEFAULT 'MH/Mumbai',
    requires_classification TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'flagged items like EETC, M&E, B-21 Umbrella',
    classification_notes  TEXT         NULL,
    is_active             TINYINT(1)   NOT NULL DEFAULT 1,
    created_by            INT          NULL,
    created_at            TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cat    (category_key),
    INDEX idx_phase  (typical_phase),
    INDEX idx_rtype  (record_type),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS approval_master_alias (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    master_id       INT          NOT NULL,
    alias           VARCHAR(255) NOT NULL,
    source_workbook ENUM('A','B') NOT NULL,
    source_sheet    VARCHAR(96)  NOT NULL,
    source_row      INT          NOT NULL,
    is_primary      TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_master (master_id),
    INDEX idx_src    (source_workbook, source_sheet, source_row),
    FOREIGN KEY (master_id) REFERENCES approval_master(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS approval_master_cost_component (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    master_id       INT          NOT NULL,
    label           VARCHAR(128) NOT NULL,
    method          ENUM('fixed','range','per_sqm_plot','per_sqm_bua','per_sqft_plot',
                         'per_sqft_bua','per_tree','percent_of','deposit','unknown') NOT NULL DEFAULT 'unknown',
    rate_numeric    DECIMAL(20,6) NULL,
    rate_range_low  DECIMAL(20,6) NULL,
    rate_range_high DECIMAL(20,6) NULL,
    unit            VARCHAR(32)   NULL,
    formula_text    VARCHAR(255)  NULL,
    confidence      ENUM('verified_official','source_reported','consultant_estimate',
                         'historical_estimate','manual_estimate','pending_verification') NOT NULL DEFAULT 'source_reported',
    is_refundable   TINYINT(1)   NOT NULL DEFAULT 0,
    source_ref      VARCHAR(255) NULL,
    source_note     TEXT         NULL,
    effective_from  DATE         NULL,
    last_verified_at TIMESTAMP   NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_master (master_id),
    FOREIGN KEY (master_id) REFERENCES approval_master(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS approval_master_duration (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    master_id       INT          NOT NULL,
    label           VARCHAR(128) NOT NULL DEFAULT 'Source-reported duration',
    duration_text   VARCHAR(96)  NULL COMMENT 'e.g. "30-45 Days" preserved verbatim',
    numeric_low     INT          NULL COMMENT 'low bound in days',
    numeric_high    INT          NULL COMMENT 'high bound in days',
    duration_kind   ENUM('source_reported','internal_prep','authority_processing','elapsed_estimate') NOT NULL DEFAULT 'source_reported',
    source_ref      VARCHAR(255) NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_master (master_id),
    FOREIGN KEY (master_id) REFERENCES approval_master(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS approval_master_document_req (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    master_id     INT          NOT NULL,
    doc_name      VARCHAR(255) NOT NULL,
    is_mandatory  TINYINT(1)   NOT NULL DEFAULT 1,
    notes         TEXT         NULL,
    display_order INT          NOT NULL DEFAULT 0,
    INDEX idx_master (master_id),
    FOREIGN KEY (master_id) REFERENCES approval_master(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS approval_master_dependency (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    master_id      INT NOT NULL COMMENT 'this approval depends on prerequisite_id',
    prerequisite_id INT NOT NULL,
    kind           ENUM('hard','soft') NOT NULL DEFAULT 'soft',
    notes          TEXT NULL,
    INDEX idx_master (master_id),
    INDEX idx_prereq (prerequisite_id),
    FOREIGN KEY (master_id)       REFERENCES approval_master(id) ON DELETE CASCADE,
    FOREIGN KEY (prerequisite_id) REFERENCES approval_master(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS approval_master_applicability_rule (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    master_id      INT          NOT NULL,
    rule_type      ENUM('mandatory','conditional','not_applicable') NOT NULL,
    condition_json LONGTEXT     NOT NULL COMMENT 'JSON condition — see ApprovalApplicabilityEngine',
    rationale_template TEXT     NULL,
    priority       INT          NOT NULL DEFAULT 0 COMMENT 'higher = evaluated first',
    is_active      TINYINT(1)   NOT NULL DEFAULT 1,
    verified_by    INT          NULL,
    verified_at    TIMESTAMP    NULL,
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_master (master_id),
    FOREIGN KEY (master_id) REFERENCES approval_master(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS approval_authority (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(48)  NOT NULL UNIQUE,
    full_name       VARCHAR(255) NOT NULL,
    jurisdiction    VARCHAR(32)  NOT NULL DEFAULT 'MH/Mumbai',
    website_url     VARCHAR(255) NULL,
    contact_notes   TEXT         NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS approval_source_import (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    source_workbook   ENUM('A','B') NOT NULL,
    source_sheet      VARCHAR(96)  NOT NULL,
    source_row        INT          NOT NULL,
    original_name     VARCHAR(255) NOT NULL,
    mapped_master_id  INT          NULL,
    mapping_decision  ENUM('new','duplicate','related','unresolved') NOT NULL DEFAULT 'new',
    import_batch      VARCHAR(48)  NULL,
    imported_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    imported_by       INT          NULL,
    UNIQUE KEY uq_src (source_workbook, source_sheet, source_row),
    INDEX idx_mapped  (mapped_master_id),
    FOREIGN KEY (mapped_master_id) REFERENCES approval_master(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Per-project register ──────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS project_approval (
    id                     INT AUTO_INCREMENT PRIMARY KEY,
    statement_id           INT          NOT NULL,
    master_id              INT          NULL COMMENT 'NULL = user-added custom requirement',
    custom_name            VARCHAR(255) NULL,
    applicability          ENUM('mandatory','conditional','not_applicable','requires_verification') NOT NULL DEFAULT 'requires_verification',
    applicability_rationale TEXT        NULL,
    user_override          TINYINT(1)   NOT NULL DEFAULT 0,
    override_reason        TEXT         NULL,
    project_phase          ENUM('land_acquisition','pre_construction','during_construction','post_construction') NOT NULL DEFAULT 'pre_construction',
    criticality            ENUM('critical','high','medium','low') NOT NULL DEFAULT 'medium',
    status                 ENUM('not_started','docs_pending','ready_to_apply','applied','under_review',
                                'query_raised','approved','rejected','on_hold','not_applicable','expired') NOT NULL DEFAULT 'not_started',
    authority_hint         VARCHAR(128) NULL,
    responsible_user_id    INT          NULL,
    next_action            VARCHAR(255) NULL,
    application_reference  VARCHAR(96)  NULL,
    remarks                TEXT         NULL,
    created_by             INT          NULL,
    created_at             TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_stmt   (statement_id),
    INDEX idx_master (master_id),
    INDEX idx_status (status),
    INDEX idx_appl   (applicability),
    FOREIGN KEY (statement_id) REFERENCES area_statements(id) ON DELETE CASCADE,
    FOREIGN KEY (master_id)    REFERENCES approval_master(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_approval_cost (
    id                       INT AUTO_INCREMENT PRIMARY KEY,
    project_approval_id      INT          NOT NULL,
    master_cost_component_id INT          NULL,
    label                    VARCHAR(128) NOT NULL,
    method                   ENUM('fixed','range','per_sqm_plot','per_sqm_bua','per_sqft_plot',
                                  'per_sqft_bua','per_tree','percent_of','deposit','unknown') NOT NULL DEFAULT 'unknown',
    inputs_json              LONGTEXT     NULL,
    rate_used                DECIMAL(20,6) NULL,
    estimated_amount         DECIMAL(20,2) NULL,
    actual_amount            DECIMAL(20,2) NULL,
    confidence               ENUM('verified_official','source_reported','consultant_estimate',
                                  'historical_estimate','manual_estimate','pending_verification') NOT NULL DEFAULT 'pending_verification',
    is_refundable            TINYINT(1)   NOT NULL DEFAULT 0,
    source_ref               VARCHAR(255) NULL,
    locked_at                TIMESTAMP    NULL,
    created_at               TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_approval (project_approval_id),
    FOREIGN KEY (project_approval_id)      REFERENCES project_approval(id) ON DELETE CASCADE,
    FOREIGN KEY (master_cost_component_id) REFERENCES approval_master_cost_component(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_approval_deposit (
    id                       INT AUTO_INCREMENT PRIMARY KEY,
    project_approval_id      INT          NOT NULL,
    label                    VARCHAR(128) NOT NULL,
    deposit_paid             DECIMAL(20,2) NULL DEFAULT 0,
    refund_received          DECIMAL(20,2) NULL DEFAULT 0,
    adjusted_against         DECIMAL(20,2) NULL DEFAULT 0,
    outstanding_amount       DECIMAL(20,2) GENERATED ALWAYS AS (
        IFNULL(deposit_paid,0) - IFNULL(refund_received,0) - IFNULL(adjusted_against,0)
    ) STORED,
    status                   ENUM('outstanding','partial_refund','fully_refunded','adjusted','written_off') NOT NULL DEFAULT 'outstanding',
    notes                    TEXT         NULL,
    created_at               TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_approval (project_approval_id),
    FOREIGN KEY (project_approval_id) REFERENCES project_approval(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_approval_timeline (
    id                          INT AUTO_INCREMENT PRIMARY KEY,
    project_approval_id         INT NOT NULL,
    planned_start_at            DATE NULL,
    expected_apply_at           DATE NULL,
    actual_submitted_at         DATE NULL,
    target_decision_at          DATE NULL,
    actual_decided_at           DATE NULL,
    delay_days                  INT  NULL,
    delay_reason                TEXT NULL,
    followup_next_at            DATE NULL,
    expiry_at                   DATE NULL,
    renewal_due_at              DATE NULL,
    internal_prep_days          INT  NULL,
    authority_processing_days   INT  NULL,
    expected_elapsed_days       INT  NULL,
    actual_elapsed_days         INT  NULL,
    notes                       TEXT NULL,
    updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_approval (project_approval_id),
    FOREIGN KEY (project_approval_id) REFERENCES project_approval(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_approval_followup (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    project_approval_id INT          NOT NULL,
    event_at            TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    event_type          ENUM('submitted','query','response','approval','rejection','note','other') NOT NULL DEFAULT 'note',
    summary             VARCHAR(255) NULL,
    detail              TEXT         NULL,
    user_id             INT          NULL,
    INDEX idx_approval (project_approval_id),
    FOREIGN KEY (project_approval_id) REFERENCES project_approval(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_approval_history (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    project_approval_id INT          NOT NULL,
    field_changed       VARCHAR(64)  NOT NULL,
    from_value          TEXT         NULL,
    to_value            TEXT         NULL,
    changed_by          INT          NULL,
    changed_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_approval (project_approval_id),
    FOREIGN KEY (project_approval_id) REFERENCES project_approval(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Non-destructive extension to area_statements for rollup cache
ALTER TABLE area_statements
    ADD COLUMN approval_phase_started_at TIMESTAMP NULL,
    ADD COLUMN approval_summary_json     LONGTEXT  NULL;
