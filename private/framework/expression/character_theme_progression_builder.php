<?php

declare(strict_types=1);

function buildCharacterThemeProgression(
    PDO $pdo,
    string $characterEntityId,
    ?string $projectionEntityId = null
): array {
    $characterEntityId = trim($characterEntityId);
    $projectionEntityId = $projectionEntityId !== null ? trim($projectionEntityId) : null;

    if ($characterEntityId === '') {
        throw new InvalidArgumentException('characterEntityId is required');
    }

    if ($projectionEntityId === '') {
        $projectionEntityId = null;
    }

    $params = [
        'character_entity_id' => $characterEntityId,
    ];

    $projectionJoin = '';
    $projectionWhere = '';

    if ($projectionEntityId !== null) {
        $projectionJoin = <<<SQL
JOIN sxnzlfun_chrysalis.calendar_event_projection_membership cepm
    ON cepm.calendar_event_id = ce.id
SQL;

        $projectionWhere = 'AND cepm.projection_entity_id = :projection_entity_id';
        $params['projection_entity_id'] = $projectionEntityId;
    }

    $stmt = $pdo->prepare(<<<SQL
SELECT
    ce.id AS calendar_event_id,
    ce.entity_id AS event_entity_id,
    ce.chronology_address,
    ce.summary,
    elf.object_entity_id AS theme_entity_id
FROM sxnzlfun_chrysalis.calendar_events ce
$projectionJoin
JOIN sxnzlfun_chrysalis.calendar_event_participants cep
    ON cep.event_id = ce.id
   AND cep.entity_id = :character_entity_id
JOIN sxnzlfun_chrysalis.entity_linked_facts elf
    ON elf.subject_entity_id = ce.entity_id
   AND elf.fact_type_id = 'fact_type_event_theme'
WHERE NOT EXISTS (
    SELECT 1
    FROM sxnzlfun_chrysalis.calendar_events child
    WHERE child.parent_event_id = ce.id
)
$projectionWhere
ORDER BY ce.chronology_address ASC, ce.id ASC, elf.object_entity_id ASC
SQL);

    $stmt->execute($params);

    $sequence = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sequence[] = [
            'calendar_event_id' => (int)$row['calendar_event_id'],
            'event_entity_id' => $row['event_entity_id'],
            'chronology_address' => $row['chronology_address'],
            'summary' => $row['summary'],
            'theme_entity_id' => $row['theme_entity_id'],
            'beat_label' => labelThemeBeat($row['chronology_address'], $row['theme_entity_id']),
        ];
    }

    return [
        'status' => 'ok',
        'character_entity_id' => $characterEntityId,
        'projection_entity_id' => $projectionEntityId,
        'theme_sequence' => $sequence,
        'validation' => validateCharacterThemeProgression($sequence),
    ];
}

function validateCharacterThemeProgression(array $sequence): array
{
    $issues = [];

    $seenEvents = [];
    $lastChronology = null;

    foreach ($sequence as $row) {
        $eventEntityId = $row['event_entity_id'];
        $chronology = $row['chronology_address'];

        if ($chronology === null || trim((string)$chronology) === '') {
            $issues[] = [
                'type' => 'missing_chronology',
                'event_entity_id' => $eventEntityId,
            ];
        }

        if (isset($seenEvents[$eventEntityId])) {
            $issues[] = [
                'type' => 'duplicate_event_theme_fact',
                'event_entity_id' => $eventEntityId,
            ];
        }

        $seenEvents[$eventEntityId] = true;

        if ($lastChronology !== null && strnatcmp((string)$lastChronology, (string)$chronology) > 0) {
            $issues[] = [
                'type' => 'chronology_out_of_order',
                'previous' => $lastChronology,
                'current' => $chronology,
            ];
        }

        $lastChronology = $chronology;
    }

    $themeEntityIds = array_values(array_map(
        fn ($row) => $row['theme_entity_id'],
        $sequence
    ));

    $knownArcSlices = detectKnownThemeArcSlices($themeEntityIds);

    return [
        'status' => $issues === [] ? 'pass' : 'fail',
        'issues' => $issues,
        'known_arc_slices' => $knownArcSlices,
    ];
}

function detectKnownThemeArcSlices(array $themeEntityIds): array
{
    $knownArcs = [
        'shay_1_6_2_theme_arc' => [
            'entity_theme_control_under_activation',
            'entity_theme_social_evaluation',
            'entity_theme_regulated_engagement',
            'entity_theme_authority_alignment',
            'entity_theme_collective_execution',
        ],
    ];

    $detected = [];

    foreach ($knownArcs as $arcId => $arcThemeEntityIds) {
        $detected[] = [
            'arc_id' => $arcId,
            'status' => containsThemeArcSlice($themeEntityIds, $arcThemeEntityIds) ? 'present' : 'not_present',
            'theme_entity_ids' => $arcThemeEntityIds,
        ];
    }

    return $detected;
}

function containsThemeArcSlice(array $themeEntityIds, array $arcThemeEntityIds): bool
{
    $arcLength = count($arcThemeEntityIds);
    $themeCount = count($themeEntityIds);

    if ($arcLength === 0 || $themeCount < $arcLength) {
        return false;
    }

    for ($i = 0; $i <= $themeCount - $arcLength; $i++) {
        if (array_slice($themeEntityIds, $i, $arcLength) === $arcThemeEntityIds) {
            return true;
        }
    }

    return false;
}

function labelThemeBeat(string $chronologyAddress, string $themeEntityId): ?string
{
    return match ($themeEntityId) {
        'entity_theme_control_under_activation' => 'control pressure activates',
        'entity_theme_social_evaluation' => 'social evaluation pressure enters',
        'entity_theme_regulated_engagement' => 'self-regulation becomes visible behaviour',
        'entity_theme_authority_alignment' => 'authority alignment constrains choice',
        'entity_theme_collective_execution' => 'collective execution resolves the local sequence',
        default => null,
    };
}
