-- Migration v10: Financial Modeling Layer for Development Plans
-- One row per development plan holds the full financial model inputs + computed outputs.
-- Curves (year-by-year %) are stored as JSON arrays; the computed cash-flow + summary are
-- also stored as JSON so the plan detail can render them without re-running the calculator.

CREATE TABLE IF NOT EXISTS plan_financials (
    plan_id                                 INT PRIMARY KEY,

    -- Meta
    project_duration_years                  INT           NOT NULL DEFAULT 7,
    scenario_label                          VARCHAR(96)   NULL,

    -- Base-year rates
    sale_rate_y1_inr_sqft                   DECIMAL(14,2) NOT NULL DEFAULT 0,
    rehab_construction_rate_y1_inr_sqft     DECIMAL(14,2) NOT NULL DEFAULT 0,
    sale_construction_rate_y1_inr_sqft      DECIMAL(14,2) NOT NULL DEFAULT 0,

    -- RERA + escalation
    annual_escalation_pct                   DECIMAL(6,3)  NOT NULL DEFAULT 5.000,
    rera_carpet_loading_pct                 DECIMAL(6,3)  NOT NULL DEFAULT 88.000,
    saleable_rera_carpet_sqft_override      DECIMAL(18,2) NULL COMMENT 'If set, overrides derived RERA carpet from area calc',

    -- Loan
    loan_principal_inr                      DECIMAL(18,2) NOT NULL DEFAULT 0,
    loan_interest_rate_pct                  DECIMAL(6,3)  NOT NULL DEFAULT 11.000,
    loan_moratorium_years                   INT           NOT NULL DEFAULT 3,
    loan_repayment_start_year               INT           NOT NULL DEFAULT 5,
    loan_repayment_years                    INT           NOT NULL DEFAULT 3,

    -- Overheads (percentages)
    marketing_pct_of_sales                  DECIMAL(6,3)  NOT NULL DEFAULT 3.000,
    admin_pct_of_costs                      DECIMAL(6,3)  NOT NULL DEFAULT 3.000,
    contingency_pct_of_costs                DECIMAL(6,3)  NOT NULL DEFAULT 4.000,
    other_costs_pct_of_costs                DECIMAL(6,3)  NOT NULL DEFAULT 3.000,

    -- Society costs (INR)
    society_rent_lump_inr                   DECIMAL(18,2) NOT NULL DEFAULT 0,
    society_rent_year_a                     INT           NOT NULL DEFAULT 1,
    society_rent_year_b                     INT           NOT NULL DEFAULT 3,
    society_other_lump_inr                  DECIMAL(18,2) NOT NULL DEFAULT 0,
    society_other_year                      INT           NOT NULL DEFAULT 1,

    -- Yearly-curve inputs — JSON arrays of length = project_duration_years, decimals in [0,1]
    booking_curve_json                      TEXT          NULL COMMENT 'Sales booking % by year',
    collection_curve_json                   TEXT          NULL COMMENT 'Cash collection % (cumulative gate on bookings-to-date)',
    rehab_cost_curve_json                   TEXT          NULL COMMENT 'Rehab construction % by year',
    sale_cost_curve_json                    TEXT          NULL COMMENT 'Sale construction % by year',
    premium_curve_json                      TEXT          NULL COMMENT 'Premium & deposits % by year',

    -- Equity + partner
    equity_investment_inr                   DECIMAL(18,2) NOT NULL DEFAULT 0,
    surplus_share_partner_pct               DECIMAL(6,3)  NOT NULL DEFAULT 22.000,

    -- Snapshot of upstream area figures at time of run (for audit)
    snapshot_sale_bua_gross_sqft            DECIMAL(18,2) NULL,
    snapshot_rera_carpet_sqft               DECIMAL(18,2) NULL,
    snapshot_rehab_construction_sqft        DECIMAL(18,2) NULL,
    snapshot_sale_construction_sqft         DECIMAL(18,2) NULL,
    snapshot_premium_total_inr              DECIMAL(18,2) NULL,

    -- Computed outputs
    yearly_output_json                      LONGTEXT      NULL COMMENT 'Full year-by-year cash flow rows',
    summary_output_json                     TEXT          NULL COMMENT 'Total revenue, total cost, NP before/after interest, IRR values',
    loan_schedule_json                      TEXT          NULL COMMENT 'Yearly opening balance, interest, principal, closing balance',
    warnings_json                           TEXT          NULL,

    calculated_at                           TIMESTAMP     NULL,
    created_at                              TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at                              TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (plan_id) REFERENCES development_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
