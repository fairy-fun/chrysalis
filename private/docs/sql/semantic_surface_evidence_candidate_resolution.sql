-- Semantic surface evidence candidate persistence
-- MariaDB-safe drop-in.
-- Doctrine:
--   surface evidence is not canonical identity
--   candidates are evidence products
--   resolver losers must be persisted for auditability
--   advisory winner selection is stored separately from candidate generation

CREATE TABLE IF NOT EXISTS semantic_surface_evidence_candidates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    semantic_surface_evidence_id BIGINT UNSIGNED NOT NULL,
    candidate_entity_id VARCHAR(191) NOT NULL,
    resolution_method_classval_id VARCHAR(191) NOT NULL,
    candidate_score DECIMAL(6,4) NOT NULL,
    scoring_notes TEXT NULL,
    is_selected TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ssec_surface_evidence_id (semantic_surface_evidence_id),
    KEY idx_ssec_candidate_entity_id (candidate_entity_id),
    KEY idx_ssec_resolution_method (resolution_method_classval_id),
    KEY idx_ssec_selected (semantic_surface_evidence_id, is_selected),
    CONSTRAINT fk_ssec_surface_evidence
        FOREIGN KEY (semantic_surface_evidence_id)
        REFERENCES semantic_surface_evidence(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_ssec_candidate_entity
        FOREIGN KEY (candidate_entity_id)
        REFERENCES entities(id),
    CONSTRAINT fk_ssec_resolution_method_classval
        FOREIGN KEY (resolution_method_classval_id)
        REFERENCES classvals(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
