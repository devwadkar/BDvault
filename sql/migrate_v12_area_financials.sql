-- Migration v12: Financial Analysis for Area Statements
-- Mirrors plan_financials shape but keyed on area_statements.id, so a locked
-- Area Statement version can carry its own financial model that survives
-- version bumps (each version gets its own row).

CREATE TABLE IF NOT EXISTS area_statement_financials (
    statement_id                            INT PRIMARY KEY,

    project_duration_years                  INT           NOT NULL DEFAULT 7,
    scenario_label                          VARCHAR(96)   NULL,

    sale_rate_y1_inr_sqft                   DECIMAL(14,2) NOT NULL DEFAULT 0,
    rehab_construction_rate_y1_inr_sqft     DECIMAL(14,2) NOT NULL DEFAULT 0,
    sale_construction_rate_y1_inr_sqft      DECIMAL(14,2) NOT NULL DEFAULT 0,

    annual_escalation_pct                   DECIMAL(6,3)  NOT NULL DEFAULT 5.000,
    rera_carpet_loading_pct                 DECIMAL(6,3)  NOT NULL DEFAULT 88.000,
    saleable_rera_carpet_sqft_override      DECIMAL(18,2) NULL,

    loan_principal_inr                      DECIMAL(18,2) NOT NULL DEFAULT 0,
    loan_interest_rate_pct                  DECIMAL(6,3)  NOT NULL DEFAULT 11.000,
    loan_moratorium_years                   INT           NOT NULL DEFAULT 3,
    loan_repayment_start_year               INT           NOT NULL DEFAULT 5,
    loan_repayment_years                    INT           NOT NULL DEFAULT 3,

    marketing_pct_of_sales                  DECIMAL(6,3)  NOT NULL DEFAULT 3.000,
    admin_pct_of_costs                      DECIMAL(6,3)  NOT NULL DEFAULT 3.000,
    contingency_pct_of_costs                DECIMAL(6,3)  NOT NULL DEFAULT 4.000,
    other_costs_pct_of_costs                DECIMAL(6,3)  NOT NULL DEFAULT 3.000,

    society_rent_lump_inr                   DECIMAL(18,2) NOT NULL DEFAULT 0,
    society_rent_year_a                     INT           NOT NULL DEFAULT 1,
    society_rent_year_b                     INT           NOT NULL DEFAULT 3,
    society_other_lump_inr                  DECIMAL(18,2) NOT NULL DEFAULT 0,
    society_other_year                      INT           NOT NULL DEFAULT 1,

    booking_curve_json                      TEXT          NULL,
    collection_curve_json                   TEXT          NULL,
    rehab_cost_curve_json                   TEXT          NULL,
    sale_cost_curve_json                    TEXT          NULL,
    premium_curve_json                      TEXT          NULL,

    equity_investment_inr                   DECIMAL(18,2) NOT NULL DEFAULT 0,
    surplus_share_partner_pct               DECIMAL(6,3)  NOT NULL DEFAULT 22.000,

    snapshot_sale_bua_gross_sqft            DECIMAL(18,2) NULL,
    snapshot_rera_carpet_sqft               DECIMAL(18,2) NULL,
    snapshot_rehab_construction_sqft        DECIMAL(18,2) NULL,
    snapshot_sale_construction_sqft         DECIMAL(18,2) NULL,
    snapshot_premium_total_inr              DECIMAL(18,2) NULL,

    yearly_output_json                      LONGTEXT      NULL,
    summary_output_json                     TEXT          NULL,
    loan_schedule_json                      TEXT          NULL,
    warnings_json                           TEXT          NULL,

    calculated_at                           TIMESTAMP     NULL,
    created_at                              TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at                              TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (statement_id) REFERENCES area_statements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
