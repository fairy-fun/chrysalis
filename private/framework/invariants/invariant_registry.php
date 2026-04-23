<?php

declare(strict_types=1);

require_once __DIR__ . '/../entity/validate_event_graph_identity_contract.php';
require_once __DIR__ . '/../entity/validate_entity_text_canonical_uniqueness.php';
require_once __DIR__ . '/../entity/validate_entity_exact_match_lookup_stability.php';

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