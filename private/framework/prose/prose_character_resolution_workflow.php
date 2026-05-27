<?php

declare(strict_types=1);

require_once __DIR__
    . '/../entity/entity_resolution_candidate_factory.php';

require_once __DIR__
    . '/resolution/resolve_token_decomposition.php';

require_once __DIR__
    . '/../semantic_resolution/semantic_resolution_workflow_runner.php';

function prose_character_resolution_workflow_run(
    PDO $pdo,
    string $surfaceForm
): array {

    return semantic_resolution_workflow_runner_run(
        $pdo,
        [
            [
                'resolver_stage' =>
                    'exact_alias',

                'candidates' =>
                    entity_resolution_candidate_factory_from_surface(
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
                    ),
            ],

            [
                'resolver_stage' =>
                    'token_decomposition',

                'candidates' =>
                    prose_character_try_token_decomposition(
                        $pdo,
                        $surfaceForm
                    ),
            ],
        ],
        [
            'arbitration_stage' =>
                'prose_character_resolution',
        ]
    );
}
