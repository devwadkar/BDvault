-- Migration v15: regulatory constants (kill all hardcoded DCPR values)
--
-- Every constant that appears verbatim in the calculators (FSI caps, loading factors,
-- fungible %, minimum carpet, zonal/additional FSI, PTC share splits, etc.) lives here.
-- RulesRegistry::get() reads from this table with per-request memoization; the
-- calculators keep their signatures — only the *default* they pass to $this->num()
-- changes from a literal to a registry lookup.
--
-- Seed values reproduce the current hardcoded numbers so calc output is byte-identical
-- after refactor. Changes go through the admin view which writes to _audit.

CREATE TABLE IF NOT EXISTS regulatory_constants (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    rule_key       VARCHAR(80)   NOT NULL COMMENT 'e.g. fungible_pct_residential',
    scope          VARCHAR(24)   NOT NULL DEFAULT 'global' COMMENT 'global | 33(5) | 33(10) | 33(11) | 33(20)(B)',
    label          VARCHAR(200)  NOT NULL,
    numeric_value  DECIMAL(20,6) NULL,
    text_value     VARCHAR(255)  NULL,
    unit           VARCHAR(24)   NULL COMMENT 'ratio | percent | sqm | sqft | inr | multiplier',
    regulation_ref VARCHAR(96)   NOT NULL COMMENT 'DCPR clause, e.g. Reg 31(3), 33(10) VIII 3.8',
    description    TEXT          NULL,
    effective_from DATE          NULL,
    effective_to   DATE          NULL,
    is_active      TINYINT(1)    NOT NULL DEFAULT 1,
    created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by     INT           NULL,

    UNIQUE KEY uq_scope_key (scope, rule_key),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS regulatory_constants_audit (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    constant_id    INT           NOT NULL,
    rule_key       VARCHAR(80)   NOT NULL,
    scope          VARCHAR(24)   NOT NULL,
    old_value      VARCHAR(255)  NULL,
    new_value      VARCHAR(255)  NULL,
    change_reason  VARCHAR(500)  NULL,
    changed_by     INT           NULL,
    changed_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_constant (constant_id, changed_at),
    INDEX idx_rule (rule_key, scope, changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Seed the current hardcoded values ────────────────────────────────────
-- ORDER MATTERS ONLY visually; uniqueness is (scope, rule_key).
-- Cross-scheme caps
INSERT IGNORE INTO regulatory_constants
    (rule_key, scope, label, numeric_value, unit, regulation_ref, description) VALUES
    ('full_scheme_fsi_cap',            'global', 'Full-scheme FSI ceiling (incl. fungible)',                5.400000, 'ratio',   'Reg 31(3)',            '4.0 base FSI + 35% fungible = 5.40 maximum for any scheme.'),
    ('basic_fsi_ceiling',              'global', 'Basic FSI ceiling before fungible',                       4.000000, 'ratio',   'Reg 30',               'Maximum basic FSI before fungible / TDR / incentive stacking.'),
    ('fungible_pct_residential',       'global', 'Fungible FSI % (residential)',                           35.000000, 'percent', 'Reg 31(3)',            'Residential fungible cap of 35% of built area.'),
    ('sqm_to_sqft',                    'global', 'Square metre → square foot conversion',                  10.763900, 'multiplier','Unit conversion',    '1 sqm = 10.7639 sqft (used in every calculator).'),

-- 33(10) SRA
    ('rehab_loading_factor',           '33(10)', 'Rehab BUA loading factor (carpet → BUA)',                 1.283000, 'multiplier', '33(10) VIII 3.2',   'Multiplier applied to rehab carpet to get rehab BUA (walls + staircase + lift + common passages).'),
    ('carpet_area_min_sqm',            '33(10)', 'Minimum carpet per rehab tenement',                      25.000000, 'sqm',     '33(10) VI 1.1',        'Statutory minimum 25 sqm carpet per rehab tenement.'),
    ('in_situ_fsi_cap',                '33(10)', 'Max FSI consumable in-situ',                              4.000000, 'ratio',   '33(10) VIII 3.8',      'BUA beyond this cap is consumable only as TDR.'),
    ('amenity_pct_default_of_rehab_bua','33(10)','Amenity block default (% of rehab tenement BUA)',        38.000000, 'percent', '33(10) VIII 3.2 + 3.10','Amenity block (Balwadi + WC + Society Office + Passage + Religious + Social) as % of Rehab Tenement BUA when explicit input is not supplied.'),

-- 33(11) SRA PTC / Rental
    ('zonal_fsi_island',               '33(11)', 'Zonal FSI — Island City',                                 1.330000, 'ratio',   '33(11)',               'Zonal FSI for Island City wards.'),
    ('zonal_fsi_suburbs',              '33(11)', 'Zonal FSI — Suburbs & Extended Suburbs',                  1.000000, 'ratio',   '33(11)',               'Zonal FSI for Suburbs and Extended Suburbs.'),
    ('additional_fsi_island',          '33(11)', 'Additional FSI cap — Island City',                        2.670000, 'ratio',   '33(11)',               'Road-width based additional FSI ceiling for Island City.'),
    ('additional_fsi_suburbs',         '33(11)', 'Additional FSI cap — Suburbs',                            3.000000, 'ratio',   '33(11)',               'Road-width based additional FSI ceiling for Suburbs.'),
    ('sra_ptc_share_pct',              '33(11)', 'SRA PTC handover share',                                 63.000000, 'percent', '33(11) A',             'Share of additional FSI handed over to SRA as PTC.'),
    ('sale_society_share_pct',         '33(11)', 'Society sale share',                                     37.000000, 'percent', '33(11) A',             'Share of additional FSI retained by society for sale.'),
    ('additional_carpet_mofa_pct',     '33(11)', 'Additional carpet uplift (MOFA)',                        50.000000, 'percent', '33(11) A',             'MOFA additional-carpet allowance vs existing.'),
    ('additional_carpet_rera_pct',     '33(11)', 'Additional carpet uplift (RERA)',                        55.000000, 'percent', '33(11) A',             'RERA additional-carpet allowance vs existing.'),
    ('carpet_to_bua_loading_mofa',     '33(11)', 'Carpet → BUA loading factor (MOFA basis)',                1.320000, 'multiplier','33(11)',             'Used in 33(11) sale BUA conversion (MOFA carpet basis).'),
    ('carpet_to_bua_loading_rera',     '33(11)', 'Carpet → BUA loading factor (RERA basis)',                1.150000, 'multiplier','33(11)',             'Used in RERA carpet → BUA conversion (walls + common areas only).'),

-- 33(5) MHADA non-cessed
    ('carpet_to_bua_loading_mofa_33_5','33(5)',  'Carpet → BUA loading factor (33(5))',                    1.320000, 'multiplier','33(5)',              'Loading factor used in 33(5) MHADA calculators.'),
    ('carpet_area_default_sqm',        '33(5)',  'Default carpet per tenement (MHADA)',                    35.000000, 'sqm',     '33(5)',                'MHADA tenement carpet default when the user leaves the field blank.'),
    ('basic_fsi_upgrade_plot_sqm',     '33(5)',  'Basic FSI 4.0 upgrade — min plot',                     4000.000000, 'sqm',     '33(5) fsi_caps',       'Plot must be ≥ 4000 sqm for Basic FSI 4.0 tier.'),
    ('basic_fsi_upgrade_road_m',       '33(5)',  'Basic FSI 4.0 upgrade — min road width',                 18.000000, 'metre',   '33(5) fsi_caps',       'Road must be ≥ 18 m for Basic FSI 4.0 tier.'),

-- 33(20)(B) Private AH
    ('zonal_fsi_island_20b',           '33(20)(B)', 'Zonal FSI — Island City',                             1.330000, 'ratio',   '33(20)(B)',            'Zonal FSI for Island City under Private AH.'),
    ('zonal_fsi_suburbs_20b',          '33(20)(B)', 'Zonal FSI — Suburbs',                                 1.000000, 'ratio',   '33(20)(B)',            'Zonal FSI for Suburbs under Private AH.'),
    ('gov_fsi',                        '33(20)(B)', 'Government free FSI',                                 0.500000, 'ratio',   '33(20)(B)',            'Free government FSI grant.'),
    ('tdr_fsi_20b',                    '33(20)(B)', 'TDR FSI cap',                                         0.900000, 'ratio',   '33(20)(B)',            'Maximum TDR FSI usable in Private AH scheme.'),
    ('target_fsi_cap_20b',             '33(20)(B)', 'Target FSI cap',                                      4.000000, 'ratio',   '33(20)(B)',            'Target scheme FSI cap before fungible.'),
    ('owner_share_pct_20b',            '33(20)(B)', 'Owner share',                                        50.000000, 'percent', '33(20)(B)',            'Owner share of the total scheme output.'),
    ('ah_share_pct_20b',               '33(20)(B)', 'Affordable-housing share',                           50.000000, 'percent', '33(20)(B)',            'AH share of the total scheme output.'),
    ('incentive_337b_pct',             '33(20)(B)', '33(7)(b) incentive %',                               15.000000, 'percent', '33(20)(B) / 33(7)(b)', 'Reservation-handover incentive percent.'),

-- Global fallback defaults used by AreaCalcEngine when Step 3 knobs are blank
    ('carpet_area_default_res_sqm',    'global', 'Default residential carpet per tenement (non-MHADA)',    27.880000, 'sqm',     'Practice standard',    'AreaCalcEngine fallback when Step 3 carpet_res is blank and scheme ≠ 33(5).'),
    ('carpet_area_default_com_sqm',    'global', 'Default commercial carpet per tenement',                 20.900000, 'sqm',     'Practice standard',    'AreaCalcEngine fallback when Step 3 carpet_com is blank.'),
    ('loading_factor_default',         'global', 'Default carpet → BUA loading factor',                     1.150000, 'multiplier','Practice standard',   'AreaCalcEngine fallback when Step 3 loading is blank.'),

-- Financial bridge premium map (all four schemes so AreaStatementFinancialsBridge is fully data-driven)
    ('premium_rate_factor',            '33(20)(B)', 'Premium rate factor (Private AH)',                    0.350000, 'ratio',   'Practice standard',    'Premium is 35% of the base ready reckoner rate for Private AH schemes.'),
    ('premium_rate_factor',            '33(5)',     'Premium rate factor (MHADA)',                         0.000000, 'ratio',   'Practice standard',    'MHADA rehab + share are premium-exempt.'),
    ('premium_rate_factor',            '33(10)',    'Premium rate factor (SRA)',                           0.250000, 'ratio',   'Practice standard',    'SRA fungible above 3.0 attracts 25% of ASR.'),
    ('premium_rate_factor',            '33(11)',    'Premium rate factor (PTC / Rental)',                  0.150000, 'ratio',   'Practice standard',    'Nominal premium for PTC / rental schemes.'),

-- 33(5) rehab constants (used by Reg33_5_Calculator)
    ('rehab_base_add_pct',             '33(5)',     'Rehab base additional carpet %',                     35.000000, 'percent', '33(5)',                'Base additional carpet % over existing per rehab tenement.'),
    ('rehab_additional_pct',           '33(5)',     'Rehab plot-size additional %',                       15.000000, 'percent', '33(5)',                'Additional plot-size incentive over base.'),
    ('min_rehab_carpet_sqm',           '33(5)',     'Minimum rehab carpet per tenement',                  35.000000, 'sqm',     '33(5)',                'Minimum rehab carpet per tenement in MHADA schemes.');
