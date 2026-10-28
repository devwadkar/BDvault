-- Migration v14: financial line items
-- Single typed table for every financial line item across categories:
-- revenue / premium / society / construction / professional / marketing / tax / other / finance
--
-- The bridge (AreaStatementFinancialsBridge) aggregates these into calculator inputs.
-- Each line item has a formula type that tells the UI + summing logic how to compute the amount.

CREATE TABLE IF NOT EXISTS area_statement_line_items (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    statement_id   INT           NOT NULL,
    category       VARCHAR(24)   NOT NULL COMMENT 'revenue|premium|society|construction|professional|marketing|tax|other|finance',
    label          VARCHAR(160)  NOT NULL,
    formula_type   ENUM('flat','area_x_rate','pct_of_base','per_unit') NOT NULL DEFAULT 'flat',
    area_sqm       DECIMAL(18,2) NULL COMMENT 'For area_x_rate rows',
    area_sqft      DECIMAL(18,2) NULL COMMENT 'For area_x_rate rows shown in sqft',
    rate_inr       DECIMAL(14,2) NULL COMMENT 'Rate per unit (₹/sqft or ₹/sqm)',
    rate_unit      VARCHAR(24)   NULL COMMENT 'sqft|sqm|unit|month',
    quantity       DECIMAL(14,2) NULL COMMENT 'For per_unit rows',
    pct            DECIMAL(6,3)  NULL COMMENT 'For pct_of_base rows',
    base_ref       VARCHAR(64)   NULL COMMENT 'For pct_of_base: which base (total_revenue / total_cost / construction_cost / rehab_bua_sqft / etc.)',
    amount_inr     DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'Computed on save',
    regulation_ref VARCHAR(96)   NULL,
    notes          TEXT          NULL,
    sort_order     INT           NOT NULL DEFAULT 0,
    created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_statement_category (statement_id, category, sort_order),
    FOREIGN KEY (statement_id) REFERENCES area_statements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
