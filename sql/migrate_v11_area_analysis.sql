-- Migration v11: Post-Proposal Area Analysis (new wizard flow)
-- Replaces the direct-to-regulation planning path with a rule-driven wizard that
-- produces a formal, versioned Area Statement. The old development_plans schema
-- stays in place for legacy plans, new work goes through area_statements.
--
-- Architecture (see design doc §17):
--   Input Layer      → area_statements + child tables (typed inputs + calc rows)
--   Regulatory Layer → dcpr_rules/*.json (already exists)
--   Calc Engine      → existing Reg33_*_Calculator.php classes
--   Validation Layer → area_statement_valid rows
--   Output Layer     → area_statements versioning (v1.0, v1.1, v2.0)

-- =====================================================================
-- 1. area_statements — one row per version of a proposal's Area Statement
-- =====================================================================
CREATE TABLE IF NOT EXISTS area_statements (
    id                        INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id               INT           NOT NULL,
    statement_name            VARCHAR(255)  NOT NULL COMMENT 'Human-readable label, e.g. "Ghatkopar Parksite AS v1.0"',

    -- Wizard state
    current_step              TINYINT       NOT NULL DEFAULT 1 COMMENT '1..6 = which wizard step is active',
    status                    ENUM('draft','calculated','validated','locked','superseded') NOT NULL DEFAULT 'draft',

    -- Versioning (semantic — user picks bump level at Save)
    version_major             INT           NOT NULL DEFAULT 1,
    version_minor             INT           NOT NULL DEFAULT 0,
    parent_statement_id       INT           NULL COMMENT 'Prior version this was branched from',

    -- Step 1: Project Information (mostly derived from proposal, editable)
    project_name              VARCHAR(255)  NULL,
    project_location          VARCHAR(255)  NULL,
    ward                      VARCHAR(64)   NULL,
    cts_number                VARCHAR(255)  NULL,
    plot_address              TEXT          NULL,
    planning_authority        ENUM('MCGM','SRA','MHADA','MMRDA','Collector','Other') NULL,
    development_type          ENUM('redevelopment','sra','mhada','society_redevelopment','land_development','cluster','other') NULL,

    -- Step 4: Regulatory selection
    suggested_regulation_key  VARCHAR(32)   NULL COMMENT 'e.g. 33(10) — proposed by rule engine',
    suggested_confidence      TINYINT       NULL COMMENT '0-100 rule-engine confidence',
    suggested_rationale       TEXT          NULL COMMENT 'Why the engine picked this regulation',
    selected_regulation_key   VARCHAR(32)   NULL COMMENT 'What the user confirmed (may override)',

    -- Step 5 outputs (denormalised summary for list + preview panel)
    net_plot_area_sqm         DECIMAL(18,2) NULL,
    total_permissible_fsi     DECIMAL(8,4)  NULL,
    total_permissible_bua_sqm DECIMAL(18,2) NULL,
    rehab_bua_sqm             DECIMAL(18,2) NULL,
    sale_bua_sqm              DECIMAL(18,2) NULL,

    -- Lock (immutable after this)
    locked_at                 TIMESTAMP     NULL,
    locked_by                 INT           NULL,
    calculated_at             TIMESTAMP     NULL,

    -- Draft snapshot of full input state, so a partial wizard survives page reloads.
    -- Full-fat data is written to child tables at Save. This is the fast-restore JSON.
    draft_json                LONGTEXT      NULL,

    created_by                INT           NULL,
    created_at                TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at                TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_proposal        (proposal_id),
    INDEX idx_status          (status),
    INDEX idx_parent          (parent_statement_id),
    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 2. area_statement_land — Land Statement rows
-- (Gross, Road Deduction, Reservation, Amenity, Other Deduction, Net, Developable)
-- =====================================================================
CREATE TABLE IF NOT EXISTS area_statement_land (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    statement_id      INT           NOT NULL,
    component_key     VARCHAR(64)   NOT NULL COMMENT 'gross_plot | road_deduction | reservation | amenity | other_deduction | net_plot | developable',
    component_label   VARCHAR(128)  NOT NULL,
    area_sqm          DECIMAL(18,2) NULL,
    is_deduction      TINYINT(1)    NOT NULL DEFAULT 0,
    source            ENUM('user','derived','regulation') NOT NULL DEFAULT 'user',
    regulation_ref    VARCHAR(96)   NULL,
    display_order     INT           NOT NULL DEFAULT 0,
    created_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_statement_component (statement_id, component_key),
    INDEX idx_statement (statement_id),
    FOREIGN KEY (statement_id) REFERENCES area_statements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 3. area_statement_fsi — FSI Statement rows
-- (Basic, Premium, TDR, Incentive, Additional, Fungible, Total)
-- =====================================================================
CREATE TABLE IF NOT EXISTS area_statement_fsi (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    statement_id      INT           NOT NULL,
    component_key     VARCHAR(64)   NOT NULL COMMENT 'basic | premium | tdr | incentive | additional | fungible | total',
    component_label   VARCHAR(128)  NOT NULL,
    fsi_ratio         DECIMAL(8,4)  NULL,
    bua_sqm           DECIMAL(18,2) NULL,
    source            ENUM('user','derived','regulation') NOT NULL DEFAULT 'derived',
    regulation_ref    VARCHAR(96)   NULL,
    display_order     INT           NOT NULL DEFAULT 0,
    created_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_statement_component (statement_id, component_key),
    INDEX idx_statement (statement_id),
    FOREIGN KEY (statement_id) REFERENCES area_statements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 4. area_statement_dev — Development Statement rows
-- (Rehab-Res, Rehab-Com, Common, Sale, Other, Total)
-- =====================================================================
CREATE TABLE IF NOT EXISTS area_statement_dev (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    statement_id      INT           NOT NULL,
    component_key     VARCHAR(64)   NOT NULL,
    component_label   VARCHAR(128)  NOT NULL,
    area_sqm          DECIMAL(18,2) NULL,
    handover_to       VARCHAR(64)   NULL COMMENT 'SRA | MHADA | MCGM | society | free-sale',
    source            ENUM('user','derived','regulation') NOT NULL DEFAULT 'derived',
    regulation_ref    VARCHAR(96)   NULL,
    display_order     INT           NOT NULL DEFAULT 0,
    created_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_statement_component (statement_id, component_key),
    INDEX idx_statement (statement_id),
    FOREIGN KEY (statement_id) REFERENCES area_statements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 5. area_statement_calcs — full formula audit trail
-- One row per computed value. Enables the "click any number to see how it was derived" UX.
-- =====================================================================
CREATE TABLE IF NOT EXISTS area_statement_calcs (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    statement_id      INT           NOT NULL,
    calc_key          VARCHAR(96)   NOT NULL,
    calc_label        VARCHAR(255)  NOT NULL,
    calc_value        DECIMAL(20,4) NULL,
    calc_unit         VARCHAR(24)   NULL,
    formula           TEXT          NULL COMMENT 'Human-readable formula string',
    formula_inputs    TEXT          NULL COMMENT 'JSON — {input_key: value} used in this calc',
    regulation_ref    VARCHAR(96)   NULL,
    rule_id           VARCHAR(128)  NULL COMMENT 'Optional pointer into dcpr_rules JSON',
    source            ENUM('user','derived','regulation') NOT NULL DEFAULT 'derived',
    display_order     INT           NOT NULL DEFAULT 0,
    created_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_statement (statement_id),
    INDEX idx_calc_key  (calc_key),
    FOREIGN KEY (statement_id) REFERENCES area_statements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 6. area_statement_valid — validation results (traffic-light per rule)
-- =====================================================================
CREATE TABLE IF NOT EXISTS area_statement_valid (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    statement_id      INT           NOT NULL,
    check_key         VARCHAR(96)   NOT NULL,
    check_label       VARCHAR(255)  NOT NULL,
    severity          ENUM('info','warning','error') NOT NULL DEFAULT 'info',
    result            ENUM('pass','warn','fail') NOT NULL DEFAULT 'pass',
    entered_value     VARCHAR(96)   NULL,
    required_value    VARCHAR(96)   NULL,
    delta             VARCHAR(96)   NULL,
    message           TEXT          NULL,
    regulation_ref    VARCHAR(96)   NULL,
    created_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_statement (statement_id),
    INDEX idx_result    (result),
    FOREIGN KEY (statement_id) REFERENCES area_statements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 7. existing_buildings — dynamic multi-building rows for redevelopment (Step 3)
-- =====================================================================
CREATE TABLE IF NOT EXISTS existing_buildings (
    id                        INT AUTO_INCREMENT PRIMARY KEY,
    statement_id              INT           NOT NULL,
    building_label            VARCHAR(96)   NOT NULL COMMENT 'e.g. "Building A", "Wing 1"',
    residential_units         INT           NOT NULL DEFAULT 0,
    commercial_units          INT           NOT NULL DEFAULT 0,
    residential_carpet_sqm    DECIMAL(18,2) NOT NULL DEFAULT 0,
    commercial_carpet_sqm     DECIMAL(18,2) NOT NULL DEFAULT 0,
    existing_bua_sqm          DECIMAL(18,2) NOT NULL DEFAULT 0,
    existing_fsi_consumed     DECIMAL(8,4)  NULL,
    tenant_count              INT           NOT NULL DEFAULT 0,
    eligible_tenant_count     INT           NOT NULL DEFAULT 0,
    display_order             INT           NOT NULL DEFAULT 0,
    created_at                TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_statement (statement_id),
    FOREIGN KEY (statement_id) REFERENCES area_statements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
