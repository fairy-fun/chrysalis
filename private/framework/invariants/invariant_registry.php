<?php

declare(strict_types=1);

require_once __DIR__ . '/../entity/validate_event_graph_identity_contract.php';
require_once __DIR__ . '/../entity/validate_entity_text_canonical_uniqueness.php';
require_once __DIR__ . '/../entity/validate_entity_exact_match_lookup_stability.php';
require_once __DIR__ . '/validate_entity_text_entity_fk_integrity.php';
require_once __DIR__ . '/validate_entity_exactly_one_canonical_label.php';
require_once __DIR__ . '/validate_entity_type_scoped_canonical_uniqueness.php';
require_once __DIR__ . '/validate_entity_linked_facts_entity_fk_integrity.php';
require_once __DIR__ . '/validate_entity_linked_facts_fact_type_validity.php';
require_once __DIR__ . '/validate_entity_id_suggestion_determinism.php';
require_once __DIR__ . '/validate_canonical_label_write_guard_contract.php';

function get_invariant_registry(): array
{
    return [
        'validate_entity_text_entity_fk_integrity' => 'validate_entity_text_entity_fk_integrity',
        'validate_entity_exactly_one_canonical_label' => 'validate_entity_exactly_one_canonical_label',
        'validate_entity_type_scoped_canonical_uniqueness' => 'validate_entity_type_scoped_canonical_uniqueness',
        'validate_entity_linked_facts_entity_fk_integrity' => 'validate_entity_linked_facts_entity_fk_integrity',
        'validate_entity_linked_facts_fact_type_validity' => 'validate_entity_linked_facts_fact_type_validity',
        'validate_entity_id_suggestion_determinism' => 'validate_entity_id_suggestion_determinism',
        'validate_canonical_label_write_guard_contract' => 'validate_canonical_label_write_guard_contract',
    ];
}

return [
    'event_graph_identity_contract' => [
        'label' => 'event graph identity contract',
        'runner' => static function (PDO $pdo): void {
            validate_event_graph_identity_contract($pdo);
        },
    ],
    'entity_text_canonical_uniqueness' => [
        'label' => 'entity text canonical uniqueness',
        'runner' => static function (PDO $pdo): void {
            validate_entity_text_canonical_uniqueness($pdo);
        },
    ],
    'entity_exact_match_lookup_stability' => [
        'label' => 'entity exact-match lookup stability',
        'runner' => static function (PDO $pdo): void {
            validate_entity_exact_match_lookup_stability($pdo);
        },
    ],
];