-- Migration v19: Seed in_situ_fsi_cap for the regulations that lack it.
-- 33(10) already has it (4.0). Add 33(5), 33(11), 33(20)(B).
-- The 4.0 hardcoded in views/area_analysis/feasibility_statement.php was wrong for
-- 33(11) in particular — PTC is a full-scheme-FSI regulation (5.4).
--
-- Usage: visit index.php?page=migrate_v19 (localhost only).

INSERT IGNORE INTO regulatory_constants
    (rule_key, scope, label, numeric_value, unit, regulation_ref, description, effective_from, is_active)
VALUES
    ('in_situ_fsi_cap', '33(5)',    'Max FSI consumable in-situ (MHADA)', 4.000000, 'FSI',
     '33(5)',    'Maximum FSI the MHADA scheme may consume on the subject plot before TDR starts.',
     '2034-01-01', 1),
    ('in_situ_fsi_cap', '33(11)',   'Max FSI consumable in-situ (PTC/Rental)', 5.400000, 'FSI',
     '33(11)',   'Permanent Transit Camp / rental housing: full-scheme FSI consumable on-plot.',
     '2034-01-01', 1),
    ('in_situ_fsi_cap', '33(20)(B)', 'Max FSI consumable in-situ (Private AH)', 4.000000, 'FSI',
     '33(20)(B)','Private affordable-housing scheme on-plot FSI ceiling.',
     '2034-01-01', 1);
