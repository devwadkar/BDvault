-- Migration v7: Post-Proposal Development Planning
-- Creates the plan lifecycle tables + regulation input schema store + ASR rates stub.
-- Depends on migrate_v6 (dcpr_rules) and setup.sql (proposals).

-- ============================================================
-- development_plans: one per accepted proposal + regulation
-- ============================================================
CREATE TABLE IF NOT EXISTS development_plans (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id      INT          NOT NULL,
    regulation_key   VARCHAR(32)  NOT NULL COMMENT 'e.g. 33(10) — matches dcpr_rules.regulation_key',
    plan_name        VARCHAR(255) NULL,
    status           VARCHAR(32)  NOT NULL DEFAULT 'draft' COMMENT 'draft | calculated | validated | finalized | archived',
    version          INT          NOT NULL DEFAULT 1,
    created_by       INT          NULL COMMENT 'users.id',
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    calculated_at    TIMESTAMP    NULL,
    finalized_at     TIMESTAMP    NULL,
    notes            TEXT         NULL,
    KEY idx_proposal (proposal_id),
    KEY idx_status (status),
    KEY idx_regulation (regulation_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- plan_inputs: every user/derived parameter for a plan
-- ============================================================
CREATE TABLE IF NOT EXISTS plan_inputs (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    plan_id        INT          NOT NULL,
    input_key      VARCHAR(96)  NOT NULL COMMENT 'e.g. gross_plot_area_sqm, road_width_m',
    input_value    TEXT         NULL,
    input_type     VARCHAR(24)  NOT NULL DEFAULT 'string' COMMENT 'string | number | boolean | date | json',
    input_source   VARCHAR(16)  NOT NULL DEFAULT 'user' COMMENT 'user | derived | default | proposal',
    regulation_ref VARCHAR(64)  NULL COMMENT 'DCPR clause that governs this input, e.g. 33(10) VIII 3.4',
    UNIQUE KEY uq_plan_input (plan_id, input_key),
    KEY idx_plan (plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- plan_calculations: every derived value with full audit trail
-- ============================================================
CREATE TABLE IF NOT EXISTS plan_calculations (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    plan_id         INT          NOT NULL,
    calc_group      VARCHAR(48)  NOT NULL COMMENT 'land | fsi | rehab | sale | fungible | tdr | totals',
    calc_key        VARCHAR(96)  NOT NULL COMMENT 'e.g. net_plot_area_sqm, rehab_bua_sqm',
    calc_label      VARCHAR(255) NULL COMMENT 'Human-readable label for display',
    calc_value      DECIMAL(20,4) NULL,
    unit            VARCHAR(16)  NULL COMMENT 'sqm | sqft | percent | ratio | tenements | count',
    formula         TEXT         NULL COMMENT 'Formula in a human-readable form',
    formula_inputs  TEXT         NULL COMMENT 'JSON of {input_key: value} used in this formula',
    regulation_ref  VARCHAR(64)  NULL COMMENT 'DCPR clause reference',
    rule_id         INT          NULL COMMENT 'FK dcpr_rules.id — the JSON rule that supplied the values',
    display_order   INT          NOT NULL DEFAULT 100,
    UNIQUE KEY uq_plan_calc (plan_id, calc_key),
    KEY idx_plan (plan_id),
    KEY idx_group (calc_group),
    KEY idx_rule (rule_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- plan_validations: rule-check outputs (green/yellow/red)
-- ============================================================
CREATE TABLE IF NOT EXISTS plan_validations (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    plan_id            INT          NOT NULL,
    check_key          VARCHAR(96)  NOT NULL,
    check_label        VARCHAR(255) NULL,
    status             VARCHAR(16)  NOT NULL COMMENT 'green | yellow | red',
    entered_value      VARCHAR(255) NULL,
    required_value     VARCHAR(255) NULL,
    delta              VARCHAR(64)  NULL,
    message            TEXT         NULL,
    regulation_ref     VARCHAR(64)  NULL,
    rule_id            INT          NULL,
    recommended_action TEXT         NULL,
    UNIQUE KEY uq_plan_check (plan_id, check_key),
    KEY idx_plan (plan_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- regulation_input_schemas: dynamic form definitions per regulation
-- ============================================================
CREATE TABLE IF NOT EXISTS regulation_input_schemas (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    regulation_key VARCHAR(32)  NOT NULL,
    version        INT          NOT NULL DEFAULT 1,
    schema_json    LONGTEXT     NOT NULL COMMENT 'JSON: {groups:[{title, fields:[{key,label,type,required,help,default,unit}]}]}',
    file_path      VARCHAR(255) NULL,
    loaded_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reg_ver (regulation_key, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- asr_rates: Annual Statement of Rates (empty until user provides spreadsheet)
-- ============================================================
CREATE TABLE IF NOT EXISTS asr_rates (
    id                        INT AUTO_INCREMENT PRIMARY KEY,
    year                      VARCHAR(16)  NOT NULL COMMENT 'e.g. 2026-27',
    district                  VARCHAR(96)  NOT NULL,
    ward                      VARCHAR(16)  NULL,
    village                   VARCHAR(96)  NULL,
    zone_no                   VARCHAR(16)  NULL,
    sub_zone_no               VARCHAR(16)  NULL,
    zone_name                 VARCHAR(255) NULL,
    land_rate_per_sqm         DECIMAL(14,2) NULL,
    residential_rate_per_sqm  DECIMAL(14,2) NULL,
    office_rate_per_sqm       DECIMAL(14,2) NULL,
    shop_rate_per_sqm         DECIMAL(14,2) NULL,
    industrial_rate_per_sqm   DECIMAL(14,2) NULL,
    construction_rate_per_sqm DECIMAL(14,2) NULL,
    imported_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ward_village (ward, village),
    KEY idx_zone (zone_no, sub_zone_no),
    KEY idx_year (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
