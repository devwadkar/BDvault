-- Migration v8: What-If Scenarios
-- Adds parent_plan_id + scenario_label to development_plans so a plan can be
-- cloned into a variant (scenario) for side-by-side comparison.

-- Add columns idempotently (fails silently on existing columns)
ALTER TABLE development_plans
    ADD COLUMN parent_plan_id INT NULL COMMENT 'Points to the base plan when this row is a scenario clone' AFTER version;

ALTER TABLE development_plans
    ADD COLUMN scenario_label VARCHAR(96) NULL COMMENT 'Short label for the scenario, e.g. "Road 15m", "Fungible 25%"' AFTER parent_plan_id;

ALTER TABLE development_plans
    ADD INDEX idx_parent (parent_plan_id);
