-- Migration v13: Feasibility statement details
-- Adds columns needed to render the classic MHADA/SRA 21-row feasibility statement:
--   * area_statement_land — multiple road rows, arbitrary named deductions, include-in-total flag
--   * area_statements     — reservation sub-label (e.g. "Green Belt / ROS 2.7")
--   * area_statement_financials — sale-in-situ + RR rate inputs

ALTER TABLE area_statement_land
    ADD COLUMN label_extra VARCHAR(128) NULL AFTER component_label,
    ADD COLUMN road_width_m DECIMAL(6,2) NULL AFTER label_extra,
    ADD COLUMN include_in_total_deductions TINYINT(1) NOT NULL DEFAULT 1 AFTER is_deduction;

ALTER TABLE area_statements
    ADD COLUMN reservation_sub_label VARCHAR(128) NULL AFTER cts_number;

ALTER TABLE area_statement_financials
    ADD COLUMN sale_in_situ_sqm DECIMAL(18,2) NULL,
    ADD COLUMN snapshot_rr_land_rate DECIMAL(14,2) NULL,
    ADD COLUMN snapshot_rr_construction DECIMAL(14,2) NULL;
