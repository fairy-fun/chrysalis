<?php
declare(strict_types=1);

require_once __DIR__ . '/prose_metadata_deriver.php';

/**
 * Prose-first ingestion hydration.
 *
 * Canonical authorial payload:
 *   - prose_body
 *
 * Optional editorial overrides:
 *   - title
 *   - summary
 *
 * Projection is not required authorial payload.
 * Projection is placement metadata.
 * Placement metadata may be obtained through interview.
 *
 * This layer converts prose-first intake into an internal
 * orchestration payload suitable for draft creation.
 */

function hydrate_prose_draft_payload(
    PDO $pdo,
    array $payload
): array {

    $proseBody = trim((string) ($payload['prose_body'] ?? ''));

    if ($proseBody === '') {
        throw new InvalidArgumentException(
            'prose_body must be a non-empty string'
        );
    }

    $metadata = derive_prose_metadata(
        $proseBody,
        $payload
    );

    $title = null;

    if (
        array_key_exists('title', $payload)
        && is_string($payload['title'])
        && trim($payload['title']) !== ''
    ) {
        $title = trim($payload['title']);
    } else {
        $title = $metadata['title'] ?? 'Untitled prose draft';
    }

    $summary = null;

    if (
        array_key_exists('summary', $payload)
        && is_string($payload['summary'])
        && trim($payload['summary']) !== ''
    ) {
        $summary = trim($payload['summary']);
    } else {
        $summary = $metadata['summary'];
    }

    /*
     * Projection interview boundary.
     *
     * Projection is currently still operationally required
     * by downstream orchestration.
     *
     * If projection is absent, return an interview contract
     * instead of silently inventing placement metadata.
     */

    if (
        !isset($payload['projection'])
        || !is_array($payload['projection'])
    ) {

        return [
            'ready_to_create_draft' => false,
            'needs_interview' => true,
            'questions' => [
                [
                    'field' => 'projection',
                    'question' => 'Where does this prose belong?',
                    'options' => [
                        'book',
                        'dream_journal',
                        'standalone_note',
                        'undecided',
                    ],
                ],
            ],
            'draft_payload' => [
                'prose_body' => $proseBody,
                'title' => $title,
                'summary' => $summary,
            ],
        ];
    }

    /*
     * Internal orchestration hydration.
     *
     * These values are system-managed infrastructure metadata,
     * not authorial payload.
     */

    return [
        'ready_to_create_draft' => true,
        'needs_interview' => false,
        'draft_payload' => [
            'entity_id' => $payload['entity_id']
                ?? ('prose:' . bin2hex(random_bytes(16))),

            'draft_status_id' => $payload['draft_status_id']
                ?? 'status_draft',

            'prose_family_entity_id'
            => $payload['prose_family_entity_id']
                ?? null,

            'author_entity_id'
            => $payload['author_entity_id']
                ?? null,

            'title' => $title,

            'summary' => $summary,

            'prose_body' => $proseBody,

            'projection' => $payload['projection'],

            'annotations' => (
                isset($payload['annotations'])
                && is_array($payload['annotations'])
            )
                ? $payload['annotations']
                : [],
        ],
    ];
}