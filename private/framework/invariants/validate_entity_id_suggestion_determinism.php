<?php

declare(strict_types=1);

require_once __DIR__ . '/../entity/build_entity_id.php';

/**
 * Ensure helper ID generation is deterministic for fixed fixtures.
 *
 * @return array{ok:bool, errors:list<string>}
 */
function validate_entity_id_suggestion_determinism(PDO $pdo): array
{
    unset($pdo);

    $fixtures = [
        ['label' => 'Collision Test', 'entity_type_id' => 'entity_type_song'],
        ['label' => 'Blue Moon', 'entity_type_id' => 'entity_type_song'],
        ['label' => 'Blue Moon', 'entity_type_id' => 'entity_type_event'],
    ];

    $errors = [];

    foreach ($fixtures as $fixture) {
        $base1 = build_base_entity_id($fixture['label'], $fixture['entity_type_id']);
        $base2 = build_base_entity_id($fixture['label'], $fixture['entity_type_id']);

        $fallback1 = build_fallback_entity_id($fixture['label'], $fixture['entity_type_id']);
        $fallback2 = build_fallback_entity_id($fixture['label'], $fixture['entity_type_id']);

        if ($base1 !== $base2) {
            $errors[] = 'Non-deterministic base entity ID for ' . $fixture['entity_type_id'] . ' / ' . $fixture['label'];
        }

        if ($fallback1 !== $fallback2) {
            $errors[] = 'Non-deterministic fallback entity ID for ' . $fixture['entity_type_id'] . ' / ' . $fixture['label'];
        }
    }

    $songFallback = build_fallback_entity_id('Blue Moon', 'entity_type_song');
    $eventFallback = build_fallback_entity_id('Blue Moon', 'entity_type_event');

    if ($songFallback === $eventFallback) {
        $errors[] = 'Fallback ID should differ across entity types for identical raw labels';
    }

    return [
        'ok' => count($errors) === 0,
        'errors' => $errors,
    ];
}