-- Migration v18: Wizard v2 opt-in tag
-- Adds a single column so v2 statements can be identified without disrupting v1.
-- Everything else (project info, basics, draft) continues to use the existing
-- columns + draft_json. No columns are dropped, no indexes rebuilt.
--
-- Usage: visit index.php?page=migrate_v18 (localhost only, auto-guarded).

ALTER TABLE area_statements
    ADD COLUMN wizard_version ENUM('v1','v2') NOT NULL DEFAULT 'v1' AFTER status;

-- Index so the v2 landing can quickly list only v2 drafts.
ALTER TABLE area_statements
    ADD INDEX idx_wizard_version (wizard_version);
