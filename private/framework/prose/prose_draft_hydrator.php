<?php
declare(strict_types=1);

require_once __DIR__ . '/prose_metadata_deriver.php';
require_once __DIR__ . '/prose_projection_interviewer.php';

/**
 * Prose-first intake hydration.
 *
 * Canonical authorial payload:
 *   - prose_body
 *
 * Optional editorial overrides:
 *   - title
 *   - summary
 *
 * Placement metadata:
 *   - projection
 *
 * Infrastructure metadata is intentionally excluded
 * from this layer.
 */

function hydrate_prose_draft_payload(
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
        $title = $metadata['title']
            ?? 'Untitled prose draft';
    }

    $summary = null;

    if (
        array_key_exists('summary', $payload)
        && is_string($payload['summary'])
        && trim($payload['summary']) !== ''
    ) {
        $summary = trim($payload['summary']);
    } else {
        $summary = $metadata['summary'] ?? null;
    }

    $normalized = [
        'prose_body' => $proseBody,
        'title' => $title,
        'summary' => $summary,
        'annotations' => (
            isset($payload['annotations'])
            && is_array($payload['annotations'])
        )
            ? $payload['annotations']
            : [],
    ];

    /*
     * Projection remains operationally meaningful,
     * but is not canonical authorial content.
     */

    if (
        isset($payload['projection'])
        && is_array($payload['projection'])
    ) {
        $normalized['projection'] = $payload['projection'];
    }
    /*
 * Intake completeness contract.
 *
 * We do not invent placement metadata.
 */

    if (!isset($normalized['projection'])) {

        $interview = begin_prose_projection_interview();

        return [
            'ready_to_create_draft' => false,
            'needs_interview' => true,
            'questions' => $interview['questions'],
            'draft_payload' => $normalized,
        ];
    }

    return [
        'ready_to_create_draft' => true,
        'needs_interview' => false,
        'questions' => [],
        'draft_payload' => $normalized,
    ];

}