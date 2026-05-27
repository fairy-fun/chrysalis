<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Canonical calendar-event attached prose resolver
|--------------------------------------------------------------------------
|
| IMPORTANT DOCTRINE
|
| This resolver intentionally resolves prose through ATTACHMENT topology:
|
|     calendar_event
|     -> calendar_event_passages
|     -> prose_family
|     -> prose_drafts
|
| and NOT through export/publication topology:
|
|     prose_projections
|     -> published_prose_draft_id
|
| This distinction is critical.
|
| Beat derivation, character tagging, ontology extraction,
| segmentation, and prose-attached workflows must operate on
| attached narrative identity, not export/publication state.
|
| Export/publication topology remains valid for:
|
|     rendering
|     publication
|     export assembly
|     reader views
|
| but NOT ontology derivation.
|
*/

function resolve_calendar_event_attached_prose(
    PDO $pdo,
    string $calendarEventEntityId
): array {

    $calendarEventEntityId = trim($calendarEventEntityId);

    if ($calendarEventEntityId === '') {
        throw new InvalidArgumentException(
            'calendarEventEntityId cannot be empty.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve calendar event row
    |--------------------------------------------------------------------------
    */

    $eventStmt = $pdo->prepare("
        SELECT
            id,
            entity_id,
            prose_body
        FROM calendar_events
        WHERE entity_id = :entity_id
        LIMIT 1
    ");

    $eventStmt->execute([
        ':entity_id' => $calendarEventEntityId,
    ]);

    $calendarEvent = $eventStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($calendarEvent)) {
        throw new RuntimeException(
            'Calendar event not found.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve prose family identity
    |--------------------------------------------------------------------------
    |
    | Primary path:
    |
    |     semantic surface
    |
    | Fallback path:
    |
    |     calendar_event_passages
    |
    */

    $proseFamilyId = null;

    $semanticSurface = trim((string)(
        $calendarEvent['prose_body'] ?? ''
    ));

    if (
        preg_match(
            '/^prose_family:(\d+)$/',
            $semanticSurface,
            $matches
        ) === 1
    ) {
        $proseFamilyId = (int)$matches[1];
    }

    /*
    |--------------------------------------------------------------------------
    | Fallback attachment lookup
    |--------------------------------------------------------------------------
    */

    if ($proseFamilyId === null) {

        $passageStmt = $pdo->prepare("
            SELECT
                prose_family_id
            FROM calendar_event_passages
            WHERE calendar_event_id = :calendar_event_id
            ORDER BY id DESC
            LIMIT 1
        ");

        $passageStmt->execute([
            ':calendar_event_id'
                => (int)$calendarEvent['id'],
        ]);

        $passageRow = $passageStmt->fetch(PDO::FETCH_ASSOC);

        if (
            is_array($passageRow)
            && isset($passageRow['prose_family_id'])
        ) {
            $proseFamilyId = (int)(
                $passageRow['prose_family_id']
            );
        }
    }

    if (
        $proseFamilyId === null
        || $proseFamilyId <= 0
    ) {
        throw new RuntimeException(
            'No prose family attached to calendar event.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve canonical prose draft
    |--------------------------------------------------------------------------
    |
    | Current doctrine:
    |
    | newest draft in prose family
    |
    | Later:
    |
    | explicit canonical family draft selection
    |
    */

    $draftStmt = $pdo->prepare("
        SELECT
            pd.id,
            pd.entity_id,
            pd.title,
            pd.prose_body,
            pd.created_at
        FROM prose_drafts pd
        WHERE pd.prose_family_id = :prose_family_id
        ORDER BY pd.id DESC
        LIMIT 1
    ");

    $draftStmt->execute([
        ':prose_family_id' => $proseFamilyId,
    ]);

    $draft = $draftStmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($draft)) {
        throw new RuntimeException(
            'No prose drafts found for prose family.'
        );
    }

    $proseBody = trim((string)(
        $draft['prose_body'] ?? ''
    ));

    if ($proseBody === '') {
        throw new RuntimeException(
            'Resolved prose draft has empty prose_body.'
        );
    }

    return [
        'calendar_event' => [
            'id' => (int)$calendarEvent['id'],
            'entity_id' => (string)$calendarEvent['entity_id'],
            'semantic_surface' => $semanticSurface,
        ],

        'prose_family' => [
            'id' => $proseFamilyId,
        ],

        'prose_draft' => [
            'id' => (int)$draft['id'],
            'entity_id' => (string)$draft['entity_id'],
            'title' => (string)$draft['title'],
        ],

        'prose_body' => $proseBody,
    ];
}