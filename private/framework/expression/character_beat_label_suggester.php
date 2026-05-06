<?php

declare(strict_types=1);

require_once __DIR__ . '/../calendar/calendar_projection_resolver.php';

function suggestCharacterBeatLabels(
    PDO $pdo,
    string $characterEntityId,
    ?string $projectionEntityId = null
): array {
    $projectionId = null;

    if ($projectionEntityId !== null && trim($projectionEntityId) !== '') {
        $projectionEntityId = trim($projectionEntityId);
        $projectionId = require_calendar_projection_id($pdo, $projectionEntityId);
    } else {
        $projectionEntityId = null;
    }

    $sql = "
        SELECT
            ce.id AS calendar_event_id,
            ce.entity_id AS event_entity_id,
            ce.chronology_address,
            ce.summary,
            elf.object_entity_id AS theme_entity_id,
            tal.beat_label
        FROM calendar_events ce
        JOIN calendar_event_participants cep
            ON cep.event_id = ce.id
           AND cep.entity_id = :character_entity_id
        JOIN entity_linked_facts_event elf
            ON elf.subject_entity_id = ce.entity_id
           AND elf.context_entity_id = ce.entity_id
           AND elf.fact_type_id = 'fact_type_event_theme'
        LEFT JOIN theme_author_labels tal
            ON tal.theme_entity_id = elf.object_entity_id
        " . ($projectionId !== null ? "
        WHERE ce.projection_id = :projection_id
        " : "") . "
        ORDER BY ce.chronology_address
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':character_entity_id', $characterEntityId);

    if ($projectionId !== null) {
        $stmt->bindValue(':projection_id', $projectionId, PDO::PARAM_INT);
    }

    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $proposals = [];

    foreach ($rows as $row) {
        $proposals[] = [
            'calendar_event_id' => (int)$row['calendar_event_id'],
            'event_entity_id' => $row['event_entity_id'],
            'chronology_address' => $row['chronology_address'],
            'theme_entity_id' => $row['theme_entity_id'],
            'beat_label' => $row['beat_label'],
            'proposal_type' => 'observed_beat_label',
            'source' => 'applied_event_theme_fact',
            'persist' => false,
        ];
    }

    return [
        'status' => 'ok',
        'character_entity_id' => $characterEntityId,
        'projection_entity_id' => $projectionEntityId,
        'projection_id' => $projectionId,
        'proposal_count' => count($proposals),
        'proposals' => $proposals,
    ];
}