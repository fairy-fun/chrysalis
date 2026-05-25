<?php
declare(strict_types=1);

require_once __DIR__ . '/../prose/prose_draft_creator.php';
require_once __DIR__ . '/../prose/prose_subevent_segmenter.php';
require_once __DIR__ . '/workflow_value_resolver.php';

function fw_execute_workflow_prose_create_draft(
    PDO $pdo,
    array $action,
    array $input = [],
    array $context = []
): array {

    $payload = fw_resolve_workflow_value(
        $action['payload'] ?? [],
        $input,
        $context
    );

    $result = create_prose_draft($pdo, $payload);

    return [
        'success' => true,
        'context' => array_merge(
            $context,
            [
                'created_prose' => $result,
            ]
        ),
    ];
}

function fw_execute_workflow_prose_upsert_calendar_event_reference_label(
    PDO $pdo,
    array $action,
    array $input = [],
    array $context = []
): array {

    $payload = fw_resolve_workflow_value(
        $action['payload'] ?? [],
        $input,
        $context
    );

    $proseFamilyEntityId = $payload['prose_family_entity_id'] ?? null;
    $calendarEventId = $payload['calendar_event_id'] ?? null;
    $referenceLabels = $payload['reference_labels']
        ?? ($payload['reference_label'] ?? null);

    if (!is_string($proseFamilyEntityId) || trim($proseFamilyEntityId) === '') {
        throw new InvalidArgumentException(
            'prose_family_entity_id must be a non-empty string'
        );
    }

    if (is_string($calendarEventId) && preg_match('/^[0-9]+$/', $calendarEventId) === 1) {
        $calendarEventId = (int) $calendarEventId;
    }

    if (!is_int($calendarEventId) || $calendarEventId < 1) {
        throw new InvalidArgumentException(
            'calendar_event_id must be a positive integer'
        );
    }

    if (is_string($referenceLabels)) {
        $referenceLabels = [$referenceLabels];
    }

    if (!is_array($referenceLabels)) {
        throw new InvalidArgumentException(
            'reference_label must be a string or reference_labels must be an array'
        );
    }

    $normalisedLabels = [];

    foreach ($referenceLabels as $referenceLabel) {
        if (!is_string($referenceLabel)) {
            throw new InvalidArgumentException(
                'reference labels must be opaque strings'
            );
        }

        $referenceLabel = trim($referenceLabel);

        if ($referenceLabel === '') {
            continue;
        }

        $normalisedLabels[$referenceLabel] = $referenceLabel;
    }

    if ($normalisedLabels === []) {
        return [
            'success' => true,
            'context' => array_merge(
                $context,
                [
                    'calendar_event_reference_labels' => [
                        'processed' => 0,
                        'inserted' => 0,
                        'existing' => 0,
                    ],
                ]
            ),
        ];
    }

    $proseFamilyId = prose_family_id_from_entity_id(
        $pdo,
        trim($proseFamilyEntityId)
    );

    if ($proseFamilyId === null) {
        throw new RuntimeException(
            'Failed to resolve prose family for reference label creation'
        );
    }

    $inserted = 0;
    $existing = 0;
    $processed = 0;
    $labels = [];

    $startedTransaction = false;

    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTransaction = true;
        }

        $selectExisting = $pdo->prepare('
            SELECT
                id,
                calendar_event_id
            FROM sxnzlfun_chrysalis.calendar_event_reference_labels
            WHERE prose_family_id = :prose_family_id
              AND reference_label = :reference_label
            LIMIT 1
        ');

        $insertLabel = $pdo->prepare('
            INSERT INTO sxnzlfun_chrysalis.calendar_event_reference_labels (
                prose_family_id,
                calendar_event_id,
                reference_label
            ) VALUES (
                :prose_family_id,
                :calendar_event_id,
                :reference_label
            )
        ');

        foreach ($normalisedLabels as $referenceLabel) {
            $selectExisting->execute([
                ':prose_family_id' => $proseFamilyId,
                ':reference_label' => $referenceLabel,
            ]);

            $row = $selectExisting->fetch(PDO::FETCH_ASSOC);

            if (is_array($row)) {
                $existingCalendarEventId = (int) $row['calendar_event_id'];

                if ($existingCalendarEventId !== $calendarEventId) {
                    throw new RuntimeException(
                        'Reference label already exists for a different calendar event in this prose family: ' .
                        $referenceLabel
                    );
                }

                $existing++;
                $processed++;
                $labels[] = $referenceLabel;
                continue;
            }

            $insertLabel->execute([
                ':prose_family_id' => $proseFamilyId,
                ':calendar_event_id' => $calendarEventId,
                ':reference_label' => $referenceLabel,
            ]);

            $inserted++;
            $processed++;
            $labels[] = $referenceLabel;
        }

        if ($startedTransaction) {
            $pdo->commit();
        }

    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    return [
        'success' => true,
        'context' => array_merge(
            $context,
            [
                'calendar_event_reference_labels' => [
                    'processed' => $processed,
                    'inserted' => $inserted,
                    'existing' => $existing,
                    'prose_family_id' => $proseFamilyId,
                    'calendar_event_id' => $calendarEventId,
                    'reference_labels' => $labels,
                ],
            ]
        ),
    ];
}


/*
|--------------------------------------------------------------------------
| NEW: PROSE DISPATCH LAYER (Tier 3 ready)
|--------------------------------------------------------------------------
*/

function fw_execute_workflow_prose_segment_subevents(
    PDO $pdo,
    array $action,
    array $input = [],
    array $context = []
): array {

    $payload = fw_resolve_workflow_value(
        $action['payload'] ?? [],
        $input,
        $context
    );

    $prose = $payload['prose'] ?? null;

    if (!is_string($prose) || trim($prose) === '') {
        return [
            'success' => false,
            'error' => 'Missing prose for segmentation',
        ];
    }

    require_once __DIR__ . '/workflow_artifact_builder.php';

    $subevents = segment_prose_into_subevents($prose);

    $artifact = build_subevent_segmentation_artifact(
        $subevents
    );

    return [
        'success' => true,

        'context' => array_merge(
            $context,
            [
                'artifact' => $artifact,
            ]
        ),
    ];
}
