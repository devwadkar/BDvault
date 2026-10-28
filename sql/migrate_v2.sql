-- ============================================================
-- BDvault – Migration v2
-- Run this ONCE against your live database.
-- ============================================================

-- 1. Add `role` column to users (if not already present)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS role VARCHAR(20) NOT NULL DEFAULT 'user'
        COMMENT 'admin | user';

-- 2. Widen completion_timeline_yrs from DECIMAL(4,1) → DECIMAL(6,2)
--    DECIMAL(4,1) = max 999.9   →   DECIMAL(6,2) = max 9999.99
ALTER TABLE `proposals`
    MODIFY COLUMN `completion_timeline_yrs` DECIMAL(6,2) DEFAULT NULL
        COMMENT 'project duration in years';

-- 3. Remove duplicate proposals before adding the unique index.
--    Keep the row with the LOWEST id for each (proposal_name, proposal_type, date_month) combo.
--    We use TRIM() to prevent trailing/leading space mismatches from crashing the unique index.
DELETE FROM proposals 
WHERE id NOT IN (
    SELECT min_id FROM (
        SELECT MIN(id) AS min_id 
        FROM proposals 
        GROUP BY TRIM(proposal_name), TRIM(COALESCE(proposal_type, '')), date_month
    ) AS tmp
);

-- 4. Add unique index on (proposal_name, proposal_type, date_month)
--    DROP first in case it already exists with a different name.
ALTER TABLE proposals
    DROP INDEX IF EXISTS uq_proposal_name_type_date;

ALTER TABLE proposals
    ADD UNIQUE INDEX uq_proposal_name_type_date (proposal_name(200), proposal_type(100), date_month);

-- 5. Seed a default admin user (change the password hash below!).
--    Hash below = password_hash('Admin@123', PASSWORD_DEFAULT)
--    You MUST change this password after first login.
INSERT INTO users (username, password, full_name, role)
VALUES (
    'admin',
    '$2y$10$.A9mLHzs/tmRAYL.fkncgu89BopkjDKoo.zqL/rOyy5/vMxpGZwy.',
    'System Administrator',
    'admin'
)
ON DUPLICATE KEY UPDATE
    role = 'admin';

-- ============================================================
-- Migration complete.
-- ============================================================
