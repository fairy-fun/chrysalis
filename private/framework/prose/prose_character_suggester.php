<?php

declare(strict_types=1);

require_once __DIR__ . '/semantic_surface_evidence_persister.php';
require_once __DIR__ . '/semantic_surface_candidate_persister.php';
require_once __DIR__
    . '/../entity/entity_resolution_candidate_factory.php';
require_once __DIR__
    . '/resolution/resolve_token_decomposition.php';
require_once __DIR__
    . '/arbitration/semantic_candidate_arbitrator.php';
require_once __DIR__
    . '/semantic_surface_transform_pipeline.php';
require_once __DIR__
    . '/../entity/entity_surface_inventory_provider.php';

function prose_character_build_candidate_identity_key(array $candidate): string
{
    return implode('|', [
        trim((string)($candidate['resolved_entity_id'] ?? '')),
        trim((string)($candidate['resolution_method_classval_id'] ?? '')),
        trim((string)($candidate['matched_lookup_surface'] ?? '')),
        trim((string)($candidate['normalized_lookup_surface'] ?? '')),
        json_encode(array_values(is_array($candidate['transform_chain'] ?? null) ? $candidate['transform_chain'] : []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        trim((string)($candidate['resolver_stage'] ?? '')),
        trim((string)($candidate['arbitration_stage'] ?? '')),
    ]);
}

function prose_character_append_candidate(array &$candidates, array $candidate): void
{
    $identityKey = prose_character_build_candidate_identity_key($candidate);
    $candidates[$identityKey] = $candidate;
}

function prose_character_try_exact_alias(
    PDO $pdo,
    string $surfaceForm
): array {

    return entity_resolution_candidate_factory_from_surface(
        $pdo,
        $surfaceForm,
        [
            'entity_type_id' =>
                'entity_type_character',

            'resolution_method_classval_id' =>
                'RESOLUTION_METHOD_EXACT_ALIAS',

            'resolver_context' =>
                __FUNCTION__,
        ]
    );
}

function prose_character_try_token_decomposition(
    PDO $pdo,
    string $surfaceForm
): array {

    return resolve_token_decomposition(
        $pdo,
        $surfaceForm,
        [
            'entity_type_id' =>
                'entity_type_character',

            'resolution_method_classval_id' =>
                'RESOLUTION_METHOD_TOKEN_DECOMPOSITION',

            'resolver_stage' =>
                __FUNCTION__,
        ]
    );
}

function prose_character_resolve_surface_form(PDO $pdo, string $surfaceForm): array
{
    $candidates = array_merge(
        prose_character_try_exact_alias($pdo, $surfaceForm),
        prose_character_try_token_decomposition($pdo, $surfaceForm)
    );

    $arbitration =
        semantic_candidate_arbitrator_run(
            $candidates,
            [
                'arbitration_stage' =>
                    'prose_character_resolution',
            ]
        );

    return $arbitration['all_candidates'] ?? [];
}

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
            if (!is_string($surfaceForm) || trim($surfaceForm) === '') {
                continue;
            }

            if (mb_stripos($proseBody, $surfaceForm) === false) {
                continue;
            }

            $candidates = prose_character_resolve_surface_form($pdo, $surfaceForm);
            $selectedCandidate = null;

            foreach ($candidates as $candidate) {

                if (!is_array($candidate)) {
                    continue;
                }

                if ((int)(
                    $candidate['is_selected'] ?? 0
                ) !== 1) {
                    continue;
                }

                $selectedCandidate = $candidate;
                break;
            }

            if (is_array($selectedCandidate)) {
                $entityId = (string)($selectedCandidate['resolved_entity_id'] ?? '');

                if ($entityId !== '') {
                    $suggestionsByEntity[$entityId] = [
                        'resolution_status' => 'resolved',
                        'resolved_entity_id' => $entityId,
                        'candidate_label' => $selectedCandidate['candidate_label'] ?? $surfaceForm,
                        'surface_forms' => [$surfaceForm],
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
