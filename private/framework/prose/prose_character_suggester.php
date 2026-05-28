<?php

declare(strict_types=1);

require_once __DIR__
    . '/../entity/entity_surface_inventory_provider.php';

require_once __DIR__
    . '/prose_character_resolution_workflow.php';

require_once __DIR__
    . '/spans/find_surface_spans.php';

function suggest_prose_characters(PDO $pdo, string $proseBody, array $context = []): array
{
    $suggestionsByEntity = [];
    $unresolved = [];

    foreach (
        entity_surface_inventory_provider_fetch_character_surfaces(
            $pdo
        )
        as $surfaceRecord
    ) {

        $surfaceForm = trim((string)(
            $surfaceRecord['surface'] ?? ''
        ));

        if ($surfaceForm === '') {
            continue;
        }

        $surfaceSpans = prose_character_find_surface_spans(
            $proseBody,
            $surfaceForm
        );

        if ($surfaceSpans === []) {
            continue;
        }

        $resolution = prose_character_resolution_workflow_run(
            $pdo,
            $surfaceForm
        );

        $selectedCandidate = (
            $resolution['selected_candidate']
            ?? null
        );

        if (is_array($selectedCandidate)) {
            $entityId = (string)($selectedCandidate['resolved_entity_id'] ?? '');

            if ($entityId !== '') {
                $suggestionsByEntity[$entityId] = [
                    'resolution_status' => 'resolved',
                    'resolved_entity_id' => $entityId,
                    'candidate_label' => $selectedCandidate['candidate_label'] ?? $surfaceForm,
                    'surface_forms' => [$surfaceForm],
                    'surface_spans' => $surfaceSpans,
                    'candidate_score' => (float)($selectedCandidate['candidate_score'] ?? 0.0),
                    'resolution_method_classval_id' => $selectedCandidate['resolution_method_classval_id'] ?? null,
                ];
            }
        } else {
            $unresolved[$surfaceForm] = [
                'resolution_status' => 'unresolved',
                'resolved_entity_id' => null,
                'candidate_label' => $surfaceForm,
                'surface_forms' => [$surfaceForm],
                'surface_spans' => $surfaceSpans,
                'candidate_score' => 0.0,
                'resolution_method_classval_id' => 'RESOLUTION_METHOD_UNRESOLVED',
            ];
        }
    }

    return [
        'suggestions' => [
            'characters' => array_merge(array_values($suggestionsByEntity), array_values($unresolved)),
        ],
        'suggestion_count' => count($suggestionsByEntity),
        'unresolved_surface_count' => count($unresolved),
        'mutates_character_ontology' => false,
        'approval_required' => true,
    ];
}
