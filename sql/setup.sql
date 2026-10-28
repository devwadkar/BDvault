-- ============================================================
-- PMS - Proposal Management System
-- Real Estate BD Proposal Classification & Scoring System
-- ============================================================

CREATE DATABASE IF NOT EXISTS pms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pms_db;

-- ============================================================
-- proposals: Raw input data (from Excel or manual entry)
-- ============================================================
CREATE TABLE IF NOT EXISTS proposals (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    proposal_name           VARCHAR(255) NOT NULL,
    proposal_type           VARCHAR(100),           -- Society Redevelopment, DM Model, etc.
    region                  VARCHAR(150),
    city                    VARCHAR(100),
    site_address            TEXT,
    pincode                 VARCHAR(20),
    reference_owner         VARCHAR(255),
    consultant_name_number  VARCHAR(255),
    lat_lng                 VARCHAR(100),
    date_month              DATE NULL,
    data_source             VARCHAR(50) DEFAULT 'manual', -- 'excel_23_24', 'excel_25', 'manual'

    -- Core financials & technical (Stage 1 inputs)
    plot_area_sqm           DECIMAL(14,2) DEFAULT NULL,
    avg_sale_price_sqft     DECIMAL(14,2) DEFAULT NULL,   -- carpet sale rate (₹/sqft)
    entry_cost_per_sqft     DECIMAL(14,2) DEFAULT NULL,   -- acquisition/entry cost (₹/sqft)
    existing_fsi_used       DECIMAL(6,3)  DEFAULT NULL,   -- must be ≤ 1
    free_sale_area_pct      DECIMAL(6,2)  DEFAULT NULL,   -- % of area remaining for sale after society allocation
    society_allocation_pct  DECIMAL(6,2)  DEFAULT NULL,   -- % given to society
    existing_units          INT           DEFAULT NULL,
    proposed_units          INT           DEFAULT NULL,
    completion_timeline_yrs DECIMAL(4,1)  DEFAULT NULL,   -- project duration in years

    -- Stage 2 scoring inputs (subjective ratings 1-10)
    location_rating         TINYINT UNSIGNED DEFAULT NULL, -- 1=poor, 10=excellent
    approval_complexity     TINYINT UNSIGNED DEFAULT NULL, -- 1=simple, 10=very complex
    market_demand           TINYINT UNSIGNED DEFAULT NULL, -- 1=low, 10=very high

    -- Flags
    is_sra                  TINYINT(1) DEFAULT 0,
    is_outright             TINYINT(1) DEFAULT 0,
    is_mhada                TINYINT(1) DEFAULT 0,
    is_stressed             TINYINT(1) DEFAULT 0,

    -- Pipeline tracking
    status                  VARCHAR(100),
    remarks                 TEXT,
    cluster                 VARCHAR(100),

    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_region (region),
    INDEX idx_city (city),
    INDEX idx_status (status),
    INDEX idx_proposal_type (proposal_type)
) ENGINE=InnoDB;


-- ============================================================
-- stage1_results: Hard-filter pass/fail results
-- ============================================================
CREATE TABLE IF NOT EXISTS stage1_results (
    id                          INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id                 INT NOT NULL,
    is_eligible                 TINYINT(1) NOT NULL,        -- 1=passed all, 0=rejected

    -- Individual filter results (1=pass, 0=fail, NULL=not evaluated)
    filter_sale_rate            TINYINT(1) DEFAULT NULL,    -- avg_sale_price >= min_sale_rate
    filter_fsi                  TINYINT(1) DEFAULT NULL,    -- existing_fsi_used <= max_fsi_used
    filter_free_sale_area       TINYINT(1) DEFAULT NULL,    -- free_sale_area_pct >= min_free_sale_pct
    filter_density              TINYINT(1) DEFAULT NULL,    -- proposed/existing units >= density ratio
    filter_timeline             TINYINT(1) DEFAULT NULL,    -- completion_timeline <= max_timeline_years
    filter_cost_feasibility     TINYINT(1) DEFAULT NULL,    -- profit margin >= min_profit_margin_pct

    -- Values at time of evaluation (for audit trail)
    eval_sale_rate              DECIMAL(14,2) DEFAULT NULL,
    eval_fsi_used               DECIMAL(6,3)  DEFAULT NULL,
    eval_free_sale_area_pct     DECIMAL(6,2)  DEFAULT NULL,
    eval_density_ratio          DECIMAL(6,2)  DEFAULT NULL,
    eval_timeline_yrs           DECIMAL(4,1)  DEFAULT NULL,
    eval_profit_margin_pct      DECIMAL(6,2)  DEFAULT NULL,

    rejection_reasons           TEXT,                       -- JSON array of reasons
    config_snapshot             TEXT,                       -- JSON of thresholds used
    processed_at                TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE,
    INDEX idx_proposal_id (proposal_id),
    INDEX idx_is_eligible (is_eligible)
) ENGINE=InnoDB;


-- ============================================================
-- stage2_scores: Weighted scoring & probability results
-- ============================================================
CREATE TABLE IF NOT EXISTS stage2_scores (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id             INT NOT NULL,

    -- Sub-scores (0–100 each)
    score_profit_margin     DECIMAL(6,2) DEFAULT NULL,
    score_location          DECIMAL(6,2) DEFAULT NULL,
    score_risk              DECIMAL(6,2) DEFAULT NULL,
    score_cost_efficiency   DECIMAL(6,2) DEFAULT NULL,
    score_sale_velocity     DECIMAL(6,2) DEFAULT NULL,

    -- Weighted contributions (sub_score * weight / 100)
    contrib_profit_margin   DECIMAL(6,2) DEFAULT NULL,
    contrib_location        DECIMAL(6,2) DEFAULT NULL,
    contrib_risk            DECIMAL(6,2) DEFAULT NULL,
    contrib_cost_efficiency DECIMAL(6,2) DEFAULT NULL,
    contrib_sale_velocity   DECIMAL(6,2) DEFAULT NULL,

    total_score             DECIMAL(6,2) DEFAULT NULL,      -- 0–100
    probability_pct         DECIMAL(6,2) DEFAULT NULL,      -- 0–100%
    category                VARCHAR(50)  DEFAULT NULL,      -- High Potential / Moderate / Low

    score_breakdown         TEXT,                           -- JSON explainability detail
    weights_used            TEXT,                           -- JSON snapshot of weights applied
    processed_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE,
    INDEX idx_proposal_id (proposal_id),
    INDEX idx_total_score (total_score),
    INDEX idx_category (category)
) ENGINE=InnoDB;


-- ============================================================
-- weights_config: Tunable parameters for Stage 1 & Stage 2
-- ============================================================
CREATE TABLE IF NOT EXISTS weights_config (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    config_name             VARCHAR(100) NOT NULL UNIQUE,
    description             VARCHAR(255),

    -- Stage 1 thresholds
    min_sale_rate           DECIMAL(14,2) DEFAULT 60000.00, -- ₹/sqft
    max_fsi_used            DECIMAL(6,3)  DEFAULT 1.000,
    min_free_sale_pct       DECIMAL(6,2)  DEFAULT 60.00,    -- %
    min_density_ratio       DECIMAL(6,2)  DEFAULT 2.50,     -- proposed/existing units
    max_timeline_years      DECIMAL(4,1)  DEFAULT 5.0,      -- years
    min_profit_margin_pct   DECIMAL(6,2)  DEFAULT 20.00,    -- %

    -- Stage 2 weights (must sum to 100)
    weight_profit_margin    DECIMAL(6,2)  DEFAULT 25.00,
    weight_location         DECIMAL(6,2)  DEFAULT 20.00,
    weight_risk             DECIMAL(6,2)  DEFAULT 20.00,
    weight_cost_efficiency  DECIMAL(6,2)  DEFAULT 20.00,
    weight_sale_velocity    DECIMAL(6,2)  DEFAULT 15.00,

    -- Score thresholds for category assignment
    threshold_high          DECIMAL(6,2)  DEFAULT 70.00,    -- score >= this = High Potential
    threshold_moderate      DECIMAL(6,2)  DEFAULT 50.00,    -- score >= this = Moderate

    is_active               TINYINT(1) DEFAULT 0,
    created_by              VARCHAR(100)  DEFAULT 'system',
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_is_active (is_active)
) ENGINE=InnoDB;


-- ============================================================
-- import_logs: Track Excel import history
-- ============================================================
CREATE TABLE IF NOT EXISTS import_logs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    filename        VARCHAR(255),
    source_label    VARCHAR(100),
    total_rows      INT DEFAULT 0,
    imported_rows   INT DEFAULT 0,
    skipped_rows    INT DEFAULT 0,
    errors          TEXT,
    imported_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- ============================================================
-- Seed: Default active weights configuration
-- ============================================================
INSERT INTO weights_config
    (config_name, description, is_active)
VALUES
    ('Default BD Config',
     'Standard business development evaluation weights based on Hiranandani criteria',
     1)
ON DUPLICATE KEY UPDATE is_active = 1;


-- ============================================================
-- users: Secure user authentication accounts
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    username                VARCHAR(100) NOT NULL UNIQUE,
    password                VARCHAR(255) NOT NULL,
    full_name               VARCHAR(150),
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
