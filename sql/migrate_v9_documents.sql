-- Migration v9: DA Intelligence & Project Data Extraction
-- Stores uploaded project documents, per-page text, extracted parameter values with source tracking, and extraction batches.

CREATE TABLE IF NOT EXISTS project_documents (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id         INT          NOT NULL,
    filename            VARCHAR(255) NOT NULL COMMENT 'Original client filename',
    stored_path         VARCHAR(512) NOT NULL COMMENT 'Relative path inside uploads/documents/',
    mime_type           VARCHAR(96)  NOT NULL,
    doc_type            VARCHAR(48)  NOT NULL DEFAULT 'DA' COMMENT 'DA | supplemental | annexure | schedule | property_card | cts | survey | feasibility | other',
    size_bytes          INT          NOT NULL DEFAULT 0,
    page_count          INT          NULL,
    text_extracted      TINYINT(1)   NOT NULL DEFAULT 0,
    extraction_note     TEXT         NULL,
    uploaded_by         INT          NULL,
    uploaded_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proposal (proposal_id),
    KEY idx_doc_type (doc_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_pages (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    document_id  INT     NOT NULL,
    page_no      INT     NOT NULL,
    text         MEDIUMTEXT NULL,
    ocr_used     TINYINT(1) NOT NULL DEFAULT 0,
    word_count   INT     NULL,
    UNIQUE KEY uq_doc_page (document_id, page_no),
    KEY idx_doc (document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS extraction_batches (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id    INT          NOT NULL,
    model_used     VARCHAR(96)  NULL,
    prompt_version INT          NOT NULL DEFAULT 1,
    tokens_in      INT          NULL,
    tokens_out     INT          NULL,
    status         VARCHAR(24)  NOT NULL DEFAULT 'success' COMMENT 'success | partial | failed',
    error_message  TEXT         NULL,
    ran_by         INT          NULL,
    ran_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proposal (proposal_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- da_extractions: one row per extracted parameter. Everything the AI finds ends up here with source tracking.
CREATE TABLE IF NOT EXISTS da_extractions (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id        INT          NOT NULL,
    batch_id           INT          NULL,
    document_id        INT          NULL COMMENT 'The specific doc the value came from',
    field_key          VARCHAR(96)  NOT NULL COMMENT 'e.g. gross_plot_area_sqm — matches planning input keys where possible',
    field_category     VARCHAR(48)  NOT NULL COMMENT 'identification | land | road | existing | rehab | rights | reservations | ownership | other',
    field_label        VARCHAR(255) NULL,
    original_text      TEXT         NULL COMMENT 'Exact quote from the document',
    extracted_value    VARCHAR(255) NULL COMMENT 'Value as stated (before normalization)',
    unit               VARCHAR(32)  NULL,
    normalized_value   VARCHAR(255) NULL COMMENT 'Numeric value in canonical unit (sqm for areas)',
    page_no            INT          NULL,
    clause_ref         VARCHAR(255) NULL COMMENT 'e.g. Schedule II, Clause 4',
    confidence         TINYINT      NULL COMMENT '0-100',
    status             VARCHAR(24)  NOT NULL DEFAULT 'auto' COMMENT 'auto | needs_review | verified | edited | rejected',
    ai_notes           TEXT         NULL COMMENT 'Why AI marked this way (for the explainer)',
    verified_by        INT          NULL,
    verified_at        TIMESTAMP    NULL,
    created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_proposal_field (proposal_id, field_key),
    KEY idx_proposal (proposal_id),
    KEY idx_status (status),
    KEY idx_category (field_category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
