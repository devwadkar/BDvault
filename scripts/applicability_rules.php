<?php
/**
 * Applicability rules seed — ~50 initial rules keyed by master `code`.
 *
 * Each entry:
 *   [master_code, rule_type, condition, rationale, priority]
 *
 * rule_type ∈ {mandatory, conditional, not_applicable}
 * priority — higher is evaluated first (allows a "mandatory if BUA ≥ 20k" to
 *            override a "conditional if ≥ 5k" rule on the same master).
 *
 * These are the Stage 3 initial seed. They are rules, not law — users will
 * confirm or override per project.
 */

return [

    // ─── Universal — always mandatory ─────────────────────────────────────
    ['rera_registration', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'RERA Registration is mandatory for every real-estate project in Maharashtra.', 100],

    ['property_card', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Every project needs the Property Card as proof of title.', 100],

    ['extract_7_12', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'A 7/12 extract is universally required for land-title verification.', 100],

    ['cts_plan_true_extract', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'The CTS / CS plan true extract is required for every DCPR submission.', 100],

    ['ownership_certificate_extract', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Ownership verification is required before any approval application.', 100],

    ['encumbrances_clearance', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Encumbrances clearance is mandatory for every redevelopment project.', 100],

    // ─── IOD / CC / OC chain — mandatory for every project ────────────────
    ['intimation_of_disapproval', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'IOD is the umbrella approval needed before CC is issued — mandatory for every project.', 100],

    ['commencement_certificate', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Commencement Certificate (Plinth / Further / Full) is mandatory before work starts and progresses.', 100],

    ['occupation_certificate', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Occupation Certificate is mandatory before anyone can legally occupy the building.', 100],

    ['building_completion_certificate', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Building Completion Certificate is mandatory before OC can issue.', 100],

    ['cfo_completion_certificate', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'CFO Completion Certificate is a prerequisite for Full Occupancy Certificate.', 100],

    // ─── Authority-based ─────────────────────────────────────────────────
    ['mhada_noc', 'mandatory',
     ['any' => [
         ['field' => 'is_mhada', 'op' => 'truthy'],
     ]],
     'MHADA scheme / authority present → MHADA NOC is mandatory.', 90],

    ['mmrda_noc', 'mandatory',
     ['field' => 'planning_authority', 'op' => 'eq', 'value' => 'MMRDA'],
     'Planning authority is MMRDA → MMRDA NOC is mandatory.', 90],

    // ─── Fire safety — mandatory for every project ────────────────────────
    ['fire_clearance_cfo', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Fire Clearance (CFO) is mandatory for all residential and commercial buildings.', 95],

    // ─── Utilities / Permanent connections ───────────────────────────────
    ['permanent_power_connection', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Permanent power connection is mandatory for occupancy.', 90],

    ['permanent_water_connection', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Permanent water connection is mandatory for occupancy.', 90],

    ['permanent_sewerage_connection', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Permanent sewerage connection is mandatory for occupancy.', 90],

    ['hydraulic_engineer_noc', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Hydraulic Engineer NOC is mandatory for water-supply arrangement.', 90],

    ['storm_water_noc', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Storm Water & Drain NOC is mandatory for site-drainage approval.', 90],

    ['sewerage_noc', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Sewerage NOC is mandatory to tie into the municipal sewer line.', 90],

    ['water_supply_noc', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Water Supply NOC is mandatory for the construction phase.', 90],

    ['electric_supply_noc', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Electric Supply NOC is mandatory for the construction phase.', 90],

    // ─── BUA-based ────────────────────────────────────────────────────────
    ['environment_clearance', 'mandatory',
     ['field' => 'total_bua_sqm', 'op' => 'gte', 'value' => 20000],
     'Total BUA ≥ 20,000 sqm — Environment Clearance (MoEF/SEIAA) is mandatory under EIA Notification.', 100],

    ['environment_clearance', 'conditional',
     ['all' => [
         ['field' => 'total_bua_sqm', 'op' => 'gte', 'value' => 5000],
         ['field' => 'total_bua_sqm', 'op' => 'lt',  'value' => 20000],
     ]],
     'Total BUA between 5,000 and 20,000 sqm — Environment Clearance may apply as a Category B2.', 80],

    ['high_rise_committee', 'mandatory',
     ['field' => 'fsi_ratio', 'op' => 'gte', 'value' => 2.5],
     'High FSI consumption suggests a high-rise structure — High Rise Committee clearance is mandatory.', 90],

    ['high_rise_electrical', 'mandatory',
     ['field' => 'fsi_ratio', 'op' => 'gte', 'value' => 2.5],
     'High-rise proxy — Electrical Inspector permission is mandatory for high-rise buildings.', 90],

    // ─── Rain water harvesting — mandatory for larger plots ──────────────
    ['rain_water_harvesting', 'mandatory',
     ['field' => 'gross_plot_area_sqm', 'op' => 'gte', 'value' => 300],
     'Plot ≥ 300 sqm — Rain Water Harvesting is mandatory per DCPR.', 90],

    // ─── Site conditions ─────────────────────────────────────────────────
    ['tree_authority_noc', 'conditional',
     ['field' => 'gross_plot_area_sqm', 'op' => 'gte', 'value' => 1000],
     'Plot ≥ 1,000 sqm — trees on site are likely; Tree Authority NOC usually applies.', 80],

    ['excavation_permission', 'conditional',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Excavation Permission is typically required during site preparation.', 70],

    ['swm_noc', 'mandatory',
     ['field' => 'total_bua_sqm', 'op' => 'gte', 'value' => 5000],
     'Projects of this scale generate significant C&D waste — SWM NOC is mandatory.', 80],

    ['pest_control_noc', 'conditional',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Pest Control NOC may be required by local circulars.', 60],

    // ─── Location-based ─────────────────────────────────────────────────
    ['crz_noc', 'conditional',
     ['any' => [
         ['field' => 'is_island_city', 'op' => 'truthy'],
         ['field' => 'zone_dp', 'op' => 'in', 'value' => ['Coastal']],
     ]],
     'Island City plot or coastal zone marking — CRZ NOC is likely applicable.', 90],

    ['airport_authority_noc', 'conditional',
     ['field' => 'ward_near_airport', 'op' => 'truthy'],
     'Ward is near Mumbai airport — Airport Authority (AAI) NOC is likely required for height clearance.', 85],

    ['ancient_monument_asi', 'requires_verification',
     ['field' => 'is_island_city', 'op' => 'truthy'],
     'Island City often has ASI-protected monuments nearby — needs verification by site survey.', 70],

    ['heritage_conservation_noc', 'requires_verification',
     ['field' => 'is_island_city', 'op' => 'truthy'],
     'Island City building may fall under local heritage conservation — verify grading.', 70],

    ['metro_monorail_noc', 'requires_verification',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Metro / Monorail alignment proximity needs site-specific verification.', 50],

    ['railway_noc', 'requires_verification',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Railway proximity needs site-specific verification.', 50],

    ['highway_noc', 'requires_verification',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'NH / highway proximity needs site-specific verification.', 50],

    ['defence_department_noc', 'requires_verification',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Defence installation proximity needs site-specific verification.', 50],

    ['police_noc', 'requires_verification',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Police installation proximity needs site-specific verification.', 50],

    // ─── Land conversion ─────────────────────────────────────────────────
    ['na_order', 'requires_verification',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Non-Agriculture Order needed only if original land use was agricultural — verify from Property Card.', 60],

    ['ulc_order', 'conditional',
     ['field' => 'gross_plot_area_sqm', 'op' => 'gte', 'value' => 500],
     'Plot ≥ 500 sqm — ULC Order verification may be required for historical ULC assets.', 70],

    // ─── Technical reports ──────────────────────────────────────────────
    ['geotechnical_report', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Geotechnical report is mandatory for structural design submission.', 85],

    ['soil_investigation_report', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Soil Investigation Report is mandatory for foundation design.', 85],

    ['total_station_survey', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Total Station survey is mandatory for accurate site measurement.', 85],

    ['structural_plans_submission', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Structural plans submission is mandatory alongside IOD.', 85],

    ['lift_escalator_installation', 'mandatory',
     ['field' => 'fsi_ratio', 'op' => 'gte', 'value' => 1.5],
     'Elevated structures need lift approval — mandatory.', 85],

    // ─── Compliance / Registrations ─────────────────────────────────────
    ['labour_insurance_policy', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Labour Insurance Policy is a statutory requirement during construction.', 90],

    // ─── Phase-based milestones ─────────────────────────────────────────
    ['part_occupancy_certificate', 'conditional',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Part OC is applicable only if the scheme hands over possession in phases.', 50],

    // ─── Borewell ────────────────────────────────────────────────────────
    ['borewell_permission', 'conditional',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Borewell permission applies only if an on-site borewell is planned.', 50],

    ['bore_well_registration_cgwa', 'conditional',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'CGWA registration applies only outside municipal corporation limits.', 50],

    // ─── Supporting docs ────────────────────────────────────────────────
    ['dp_remark',  'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Development Plan remark is required for the building proposal file.', 80],

    ['tp_remarks', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Town Planning remarks are required alongside DP remarks.', 80],

    ['road_status_certificate', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Road status certificate is needed for setback / FSI verification.', 80],

    ['approved_building_plan', 'mandatory',
     ['any' => [['field' => 'statement_id', 'op' => 'exists']]],
     'Approved building plan is the primary deliverable after IOD.', 100],
];
